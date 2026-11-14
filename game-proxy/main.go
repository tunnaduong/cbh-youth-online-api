// game-proxy streams a third-party browser game through our own origin so that
// (1) the account's cookies and localStorage can be saved in Laravel and
// restored on another device, and (2) ad hosts can be blocked. It holds no data
// itself: Laravel signs the play link and stores the saves. See README.md.
package main

import (
	"bytes"
	"compress/gzip"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"net/http/httputil"
	"net/url"
	"strconv"
	"strings"
	"time"
)

const (
	cookieName     = "cbh_gp"
	maxRewriteSize = 8 << 20 // bigger text files are streamed untouched
)

type ctxKey struct{}

// reqInfo travels with the request from handleProxy to the proxy's callbacks.
// The origin and scheme are those of the browser's own request: by the time
// ModifyResponse runs, the request it sees already points at the game's host.
type reqInfo struct {
	sess   *Session
	origin string
	secure bool
}

type server struct {
	cfg      Config
	sessions *Sessions
	proxy    *httputil.ReverseProxy
}

func main() {
	cfg := loadConfig()
	if cfg.Secret == "" {
		log.Fatal("GP_SECRET is empty - refusing to start")
	}

	s := &server{cfg: cfg, sessions: newSessions(newAPI(cfg), cfg.Blocklist)}
	s.proxy = s.newProxy()
	go s.sessions.run()

	mux := http.NewServeMux()
	mux.HandleFunc("/healthz", func(w http.ResponseWriter, r *http.Request) { _, _ = io.WriteString(w, "ok") })
	mux.HandleFunc("/play", s.handlePlay)
	mux.HandleFunc("/__gp/save", s.handleSave)
	mux.HandleFunc("/", s.handleProxy)

	srv := &http.Server{
		Addr:              cfg.Listen,
		Handler:           mux,
		ReadHeaderTimeout: 10 * time.Second,
		IdleTimeout:       120 * time.Second,
	}
	log.Printf("game-proxy listening on %s (%d blocked hosts)", cfg.Listen, len(cfg.Blocklist.List()))
	log.Fatal(srv.ListenAndServe())
}

// --- helpers ---------------------------------------------------------------

func isSecure(r *http.Request) bool {
	return r.TLS != nil || strings.EqualFold(r.Header.Get("X-Forwarded-Proto"), "https")
}

func ourOrigin(r *http.Request) string {
	scheme := "http"
	if isSecure(r) {
		scheme = "https"
	}
	return scheme + "://" + r.Host
}

func deny(w http.ResponseWriter, status int, msg string) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Header().Set("Cache-Control", "no-store")
	w.WriteHeader(status)
	fmt.Fprintf(w, `<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><body style="font-family:sans-serif;background:#000;color:#fff;text-align:center;padding:48px 16px">%s</body>`, msg)
}

const msgExpired = "Phiên chơi đã hết hạn. Hãy thoát ra rồi mở lại game."

// sessionFor reads the session cookie (it holds the signed token).
func (s *server) sessionFor(r *http.Request) (*Session, string, error) {
	c, err := r.Cookie(cookieName)
	if err != nil {
		return nil, "", errBadToken
	}
	claims, err := verifyToken(c.Value, s.cfg.Secret)
	if err != nil {
		return nil, "", err
	}
	sess, err := s.sessions.get(c.Value, claims)
	return sess, c.Value, err
}

// --- /play?t=TOKEN ---------------------------------------------------------

// handlePlay checks the signed link, starts the session, puts the account's
// saved cookies into the browser and sends it on to the game's own page.
func (s *server) handlePlay(w http.ResponseWriter, r *http.Request) {
	token := r.URL.Query().Get("t")
	claims, err := verifyToken(token, s.cfg.Secret)
	if err != nil {
		deny(w, http.StatusForbidden, msgExpired)
		return
	}
	sess, err := s.sessions.get(token, claims)
	if err != nil {
		deny(w, http.StatusBadGateway, "Không mở được game này.")
		return
	}

	secure := isSecure(r)
	sameSite := http.SameSiteLaxMode
	if secure {
		sameSite = http.SameSiteNoneMode // the game sits in an iframe on another site
	}
	maxAge := int(time.Until(time.Unix(claims.Expires, 0)).Seconds())

	http.SetCookie(w, &http.Cookie{
		Name: cookieName, Value: token, Path: "/", MaxAge: maxAge,
		HttpOnly: true, Secure: secure, SameSite: sameSite,
	})

	cookies, _, _ := sess.snapshot()
	for _, c := range cookies {
		http.SetCookie(w, &http.Cookie{
			Name: c.Name, Value: c.Value, Path: "/", MaxAge: 365 * 24 * 3600,
			Secure: secure, SameSite: sameSite,
		})
	}

	entry := sess.upstream.RequestURI()
	if entry == "" {
		entry = "/"
	}
	w.Header().Set("Cache-Control", "no-store")
	http.Redirect(w, r, entry, http.StatusFound)
}

// --- /__gp/save (POST from the injected script) -----------------------------

func (s *server) handleSave(w http.ResponseWriter, r *http.Request) {
	// The session cookie is SameSite=None, so another site could make the
	// browser send it: only accept the request from our own pages.
	if r.Method != http.MethodPost || r.Header.Get("Origin") != ourOrigin(r) {
		http.Error(w, "forbidden", http.StatusForbidden)
		return
	}
	sess, _, err := s.sessionFor(r)
	if err != nil {
		http.Error(w, "session expired", http.StatusUnauthorized)
		return
	}

	var body struct {
		Storage map[string]string `json:"storage"`
	}
	r.Body = http.MaxBytesReader(w, r.Body, int64(s.cfg.MaxSaveBytes))
	if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
		http.Error(w, "bad request", http.StatusBadRequest)
		return
	}
	sess.setStorage(body.Storage)
	updated := s.sessions.flush(sess)

	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Cache-Control", "no-store")
	_ = json.NewEncoder(w).Encode(map[string]int64{"updated_at": updated})
}

// --- the proxy -------------------------------------------------------------

func (s *server) handleProxy(w http.ResponseWriter, r *http.Request) {
	sess, _, err := s.sessionFor(r)
	if err != nil {
		deny(w, http.StatusUnauthorized, msgExpired)
		return
	}
	info := &reqInfo{sess: sess, origin: ourOrigin(r), secure: isSecure(r)}
	ctx := context.WithValue(r.Context(), ctxKey{}, info)
	s.proxy.ServeHTTP(w, r.WithContext(ctx))
}

func infoOf(r *http.Request) *reqInfo {
	info, _ := r.Context().Value(ctxKey{}).(*reqInfo)
	return info
}

func (s *server) newProxy() *httputil.ReverseProxy {
	transport := &http.Transport{
		Proxy:                 http.ProxyFromEnvironment,
		DialContext:           (&net.Dialer{Timeout: 10 * time.Second, KeepAlive: 30 * time.Second}).DialContext,
		ForceAttemptHTTP2:     true,
		MaxIdleConnsPerHost:   16,
		IdleConnTimeout:       90 * time.Second,
		TLSHandshakeTimeout:   10 * time.Second,
		ResponseHeaderTimeout: 30 * time.Second,
	}

	return &httputil.ReverseProxy{
		Transport: transport,
		Rewrite: func(pr *httputil.ProxyRequest) {
			info := infoOf(pr.In)
			sess := info.sess
			up := sess.upstream
			pr.Out.URL.Scheme = up.Scheme
			pr.Out.URL.Host = up.Host
			pr.Out.Host = up.Host

			// Cookies: drop ours, learn the rest.
			seen := map[string]string{}
			var forward []string
			for _, c := range pr.In.Cookies() {
				if c.Name == cookieName {
					continue
				}
				seen[c.Name] = c.Value
				forward = append(forward, c.Name+"="+c.Value)
			}
			sess.observeCookies(seen)
			pr.Out.Header.Del("Cookie")
			if len(forward) > 0 {
				pr.Out.Header.Set("Cookie", strings.Join(forward, "; "))
			}

			// Look like the browser talking to the game directly.
			origin := up.Scheme + "://" + up.Host
			if pr.In.Header.Get("Origin") != "" {
				pr.Out.Header.Set("Origin", origin)
			}
			if ref := pr.In.Header.Get("Referer"); ref != "" {
				pr.Out.Header.Set("Referer", strings.Replace(ref, info.origin, origin, 1))
			}
			pr.Out.Header.Del("X-Forwarded-For")
			pr.Out.Header.Del("X-Forwarded-Host")
			pr.Out.Header.Del("X-Forwarded-Proto")

			// gzip is the one encoding we can read back when rewriting text.
			if strings.Contains(pr.In.Header.Get("Accept-Encoding"), "gzip") {
				pr.Out.Header.Set("Accept-Encoding", "gzip")
			} else {
				pr.Out.Header.Del("Accept-Encoding")
			}
		},
		ModifyResponse: s.modifyResponse,
		ErrorHandler: func(w http.ResponseWriter, r *http.Request, err error) {
			log.Printf("proxy %s %s: %v", r.Method, r.URL.Path, err)
			deny(w, http.StatusBadGateway, "Game không phản hồi. Thử lại sau.")
		},
		BufferPool: newBufferPool(),
	}
}

func (s *server) modifyResponse(resp *http.Response) error {
	info := infoOf(resp.Request)
	sess, origin, secure := info.sess, info.origin, info.secure
	h := resp.Header

	// Cookies: keep the jar in step and re-point them at our host.
	if raw := h.Values("Set-Cookie"); len(raw) > 0 {
		h.Del("Set-Cookie")
		for _, line := range raw {
			name, value, remove, out, ok := rewriteSetCookie(line, secure)
			if !ok {
				continue
			}
			if remove {
				sess.deleteCookie(name)
			} else {
				sess.setCookie(name, value)
			}
			h.Add("Set-Cookie", out)
		}
	}

	// The game may only be framed by our own sites.
	h.Del("X-Frame-Options")
	h.Del("Content-Security-Policy")
	h.Del("Content-Security-Policy-Report-Only")
	h.Del("Strict-Transport-Security")
	frame := "'self'"
	if len(s.cfg.ParentOrigins) > 0 {
		frame += " " + strings.Join(s.cfg.ParentOrigins, " ")
	}
	h.Set("Content-Security-Policy", "frame-ancestors "+frame)

	// A redirect to the game's own host stays on us.
	if loc := h.Get("Location"); loc != "" {
		if u, err := url.Parse(loc); err == nil && u.Host == sess.upstream.Host {
			u.Scheme, u.Host = "", ""
			h.Set("Location", u.String())
		}
	}

	ct := h.Get("Content-Type")
	if !isTextual(ct) || resp.StatusCode == http.StatusNoContent || resp.StatusCode == http.StatusNotModified || resp.StatusCode == http.StatusPartialContent {
		return nil
	}
	if resp.ContentLength > maxRewriteSize {
		return nil
	}

	// Read (at most maxRewriteSize), undoing gzip; anything else is left alone.
	var reader io.Reader = resp.Body
	switch strings.ToLower(h.Get("Content-Encoding")) {
	case "":
	case "gzip":
		gz, err := gzip.NewReader(resp.Body)
		if err != nil {
			return nil
		}
		reader = gz
	default:
		return nil
	}
	raw, err := io.ReadAll(io.LimitReader(reader, maxRewriteSize+1))
	if err != nil {
		return err
	}
	if len(raw) > maxRewriteSize { // too big after all: stream what we have + the rest
		resp.Body = io.NopCloser(io.MultiReader(bytes.NewReader(raw), reader))
		return nil
	}
	resp.Body.Close()

	body := replaceOrigin(raw, sess.upstream, origin)
	if isHTML(ct) {
		_, storage, version := sess.snapshot()
		body = processHTML(body, sess.blocked, headSnippet(storage, version, sess.blocked))
		// Personal (it carries this account's save): never cache.
		h.Set("Cache-Control", "private, no-store")
	}

	resp.Body = io.NopCloser(bytes.NewReader(body))
	resp.ContentLength = int64(len(body))
	h.Set("Content-Length", strconv.Itoa(len(body)))
	h.Del("Content-Encoding")
	h.Del("ETag")
	h.Del("Content-MD5")
	return nil
}
