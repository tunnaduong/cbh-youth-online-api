package main

import (
	"bytes"
	"encoding/json"
	"net/http"
	"net/url"
	"regexp"
	"strings"
	"time"
)

// --- Set-Cookie ------------------------------------------------------------

// rewriteSetCookie turns a game's Set-Cookie into one the browser keeps for OUR
// host: Domain dropped (it named the game's host), and SameSite=None; Secure
// so it still works in an iframe on another site. It also says whether the
// cookie sets (name, value) or deletes (remove) so the jar can follow.
func rewriteSetCookie(raw string, secure bool) (name, value string, remove bool, out string, ok bool) {
	resp := http.Response{Header: http.Header{"Set-Cookie": {raw}}}
	cookies := resp.Cookies()
	if len(cookies) == 0 {
		return "", "", false, "", false
	}
	c := cookies[0]
	remove = c.MaxAge < 0 || (!c.Expires.IsZero() && c.Expires.Before(time.Now()))
	c.Domain = ""
	if secure {
		c.Secure = true
		c.SameSite = http.SameSiteNoneMode
	} else {
		c.SameSite = http.SameSiteLaxMode
	}
	return c.Name, c.Value, remove, c.String(), true
}

// --- text rewriting --------------------------------------------------------

// isTextual: responses worth rewriting (the game's page, scripts, styles, data).
func isTextual(contentType string) bool {
	ct := strings.ToLower(contentType)
	for _, p := range []string{"text/html", "application/javascript", "text/javascript", "application/x-javascript", "text/css", "application/json", "text/plain", "application/xhtml"} {
		if strings.Contains(ct, p) {
			return true
		}
	}
	return false
}

func isHTML(contentType string) bool {
	return strings.Contains(strings.ToLower(contentType), "text/html")
}

// replaceOrigin points absolute links to the game's own host at us, so its
// scripts and requests keep going through the proxy.
func replaceOrigin(body []byte, up *url.URL, ourOrigin string) []byte {
	host := up.Host
	ourHost := strings.TrimPrefix(strings.TrimPrefix(ourOrigin, "https://"), "http://")

	body = bytes.ReplaceAll(body, []byte("https://"+host), []byte(ourOrigin))
	body = bytes.ReplaceAll(body, []byte("http://"+host), []byte(ourOrigin))
	body = bytes.ReplaceAll(body, []byte("//"+host), []byte("//"+ourHost))
	// JSON-escaped slashes: https:\/\/host
	body = bytes.ReplaceAll(body, []byte(`https:\/\/`+host), []byte(strings.ReplaceAll(ourOrigin, "/", `\/`)))
	return body
}

var (
	scriptTag = regexp.MustCompile(`(?is)<script\b([^>]*)>.*?</script\s*>`)
	iframeTag = regexp.MustCompile(`(?is)<iframe\b([^>]*)>.*?</iframe\s*>`)
	linkTag   = regexp.MustCompile(`(?is)<link\b([^>]*)>`)
	imgTag    = regexp.MustCompile(`(?is)<img\b([^>]*)>`)
	srcAttr   = regexp.MustCompile(`(?is)\b(?:src|href)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))`)
	headTag   = regexp.MustCompile(`(?i)<head\b[^>]*>`)
)

func attrURL(attrs string) string {
	m := srcAttr.FindStringSubmatch(attrs)
	if m == nil {
		return ""
	}
	for _, g := range m[1:] {
		if g != "" {
			return g
		}
	}
	return ""
}

// stripAds drops <script>, <iframe>, <link> and <img> elements whose address
// is on the blocklist.
func stripAds(html string, bl *Blocklist) string {
	drop := func(re *regexp.Regexp, s string) string {
		return re.ReplaceAllStringFunc(s, func(tag string) string {
			m := re.FindStringSubmatch(tag)
			if len(m) > 1 && bl.URLBlocked(attrURL(m[1])) {
				return ""
			}
			return tag
		})
	}
	html = drop(scriptTag, html)
	html = drop(iframeTag, html)
	html = drop(linkTag, html)
	html = drop(imgTag, html)
	return html
}

// scriptJSON makes JSON safe inside an inline <script>.
func scriptJSON(v any) string {
	b, _ := json.Marshal(v)
	s := string(b)
	s = strings.ReplaceAll(s, "<", `<`)
	s = strings.ReplaceAll(s, ">", `>`)
	s = strings.ReplaceAll(s, " ", ` `)
	s = strings.ReplaceAll(s, " ", ` `)
	return s
}

const cosmeticCSS = `ins.adsbygoogle,.adsbygoogle,iframe[src*="doubleclick"],iframe[src*="googlesyndication"],[id^="google_ads"],[id^="div-gpt-ad"],[id^="ad-slot"]{display:none!important}`

// pageScript runs first in every proxied page: it restores the saved
// localStorage (when the account's copy is newer), keeps pushing changes back,
// and stops ad scripts / frames / pop-ups the HTML pass could not see (the ones
// a script creates later).
const pageScriptTemplate = `(function(){
try{
var S=__STORAGE__,V=__VERSION__,B=__BLOCKED__;
var KEY='__gp_v';
var lv=+(localStorage.getItem(KEY)||0);
if(V>lv){for(var k in S){try{localStorage.setItem(k,S[k])}catch(e){}}try{localStorage.setItem(KEY,String(V))}catch(e){}}
function snap(){var o={};for(var i=0;i<localStorage.length;i++){var k=localStorage.key(i);if(k!==KEY)o[k]=localStorage.getItem(k)}return JSON.stringify(o)}
var last=snap();
function push(){var s=snap();if(s===last)return;last=s;
fetch('/__gp/save',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',keepalive:true,body:'{"storage":'+s+'}'})
.then(function(r){return r.json()}).then(function(j){if(j&&j.updated_at)try{localStorage.setItem(KEY,String(j.updated_at))}catch(e){}}).catch(function(){})}
setInterval(push,15000);
addEventListener('pagehide',push);
document.addEventListener('visibilitychange',function(){if(document.hidden)push()});
function bad(u){try{var h=new URL(String(u),location.href).hostname;if(h===location.hostname)return false;return B.some(function(d){return h===d||h.slice(-d.length-1)==='.'+d})}catch(e){return false}}
function blockSrc(P){if(!P)return;var d=Object.getOwnPropertyDescriptor(P,'src');if(!d||!d.set)return;
Object.defineProperty(P,'src',{get:d.get,configurable:true,set:function(v){if(bad(v)){var el=this;setTimeout(function(){try{el.dispatchEvent(new Event('error'))}catch(e){}},0);return}d.set.call(this,v)}})}
blockSrc(window.HTMLScriptElement&&HTMLScriptElement.prototype);
blockSrc(window.HTMLIFrameElement&&HTMLIFrameElement.prototype);
blockSrc(window.HTMLImageElement&&HTMLImageElement.prototype);
var sa=Element.prototype.setAttribute;
Element.prototype.setAttribute=function(k,v){if(String(k).toLowerCase()==='src'&&bad(v)&&/^(SCRIPT|IFRAME|IMG)$/.test(this.tagName))return;return sa.call(this,k,v)};
var of=window.fetch;
window.fetch=function(u){var x=u&&u.url?u.url:u;if(bad(x))return Promise.resolve(new Response('',{status:204}));return of.apply(this,arguments)};
var xo=XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.open=function(m,u){if(bad(u)){arguments[1]='data:,'}return xo.apply(this,arguments)};
window.open=function(){return null};
}catch(e){}
})();`

// headSnippet is what gets inserted into the game's <head>.
func headSnippet(storage map[string]string, version int64, bl *Blocklist) string {
	if storage == nil {
		storage = map[string]string{}
	}
	script := strings.NewReplacer(
		"__STORAGE__", scriptJSON(storage),
		"__VERSION__", scriptJSON(version),
		"__BLOCKED__", scriptJSON(bl.List()),
	).Replace(pageScriptTemplate)
	return "<script>" + script + "</script><style>" + cosmeticCSS + "</style>"
}

// processHTML: ads out, our script in.
func processHTML(body []byte, bl *Blocklist, snippet string) []byte {
	html := stripAds(string(body), bl)
	if loc := headTag.FindStringIndex(html); loc != nil {
		return []byte(html[:loc[1]] + snippet + html[loc[1]:])
	}
	return []byte(snippet + html)
}
