package main

import (
	"net/url"
	"strings"
	"testing"
)

func TestSetCookieRewrite(t *testing.T) {
	name, value, remove, out, ok := rewriteSetCookie("save=abc123; Domain=games.example.com; Path=/; Max-Age=3600; HttpOnly", true)
	if !ok || name != "save" || value != "abc123" || remove {
		t.Fatalf("parsed wrong: %v %q %q %v", ok, name, value, remove)
	}
	if strings.Contains(strings.ToLower(out), "domain=") {
		t.Errorf("Domain kept: %s", out)
	}
	if !strings.Contains(out, "Secure") || !strings.Contains(out, "SameSite=None") {
		t.Errorf("not usable in an iframe: %s", out)
	}

	// Max-Age=0 means "delete now".
	if _, _, remove, _, _ = rewriteSetCookie("save=; Max-Age=0; Path=/", true); !remove {
		t.Error("a Max-Age=0 cookie should be a delete")
	}
}

func TestStripAds(t *testing.T) {
	bl := newBlocklist(defaultBlockedHosts)
	in := `<html><head>
<script src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
<script src="/game.js"></script>
<script>var x = 1;</script>
<iframe src="//ads.doubleclick.net/x"></iframe>
<link rel="stylesheet" href="/game.css">
<img src="https://static.criteo.net/p.gif">
</head><body>ok</body></html>`
	out := stripAds(in, bl)

	for _, gone := range []string{"googlesyndication", "doubleclick", "criteo"} {
		if strings.Contains(out, gone) {
			t.Errorf("%s survived:\n%s", gone, out)
		}
	}
	for _, kept := range []string{`src="/game.js"`, "var x = 1;", `href="/game.css"`, "<body>ok</body>"} {
		if !strings.Contains(out, kept) {
			t.Errorf("%q was removed:\n%s", kept, out)
		}
	}
}

func TestBlocklistMatchesSubdomains(t *testing.T) {
	bl := newBlocklist([]string{"adnxs.com"})
	if !bl.HostBlocked("secure.adnxs.com") || !bl.HostBlocked("adnxs.com") {
		t.Error("subdomain not blocked")
	}
	if bl.HostBlocked("notadnxs.com") || bl.HostBlocked("example.com") {
		t.Error("unrelated host blocked")
	}
	if bl.URLBlocked("/local/ad.js") {
		t.Error("relative URL must never be blocked")
	}
}

func TestReplaceOrigin(t *testing.T) {
	up, _ := url.Parse("https://games.example.com/g/index.html")
	body := []byte(`a="https://games.example.com/x.js" b="//games.example.com/y.js" c="https://cdn.other.com/z.js"`)
	got := string(replaceOrigin(body, up, "https://play.example.net"))
	if !strings.Contains(got, `"https://play.example.net/x.js"`) || !strings.Contains(got, `"//play.example.net/y.js"`) {
		t.Errorf("origin not replaced: %s", got)
	}
	if !strings.Contains(got, "https://cdn.other.com/z.js") {
		t.Errorf("other hosts must stay: %s", got)
	}
}

func TestProcessHTMLInjectsSnippetFirst(t *testing.T) {
	bl := newBlocklist(defaultBlockedHosts)
	snip := headSnippet(map[string]string{"coins": "</script>12"}, 7, bl)
	out := string(processHTML([]byte("<html><head><title>x</title></head><body></body></html>"), bl, snip))
	i, j := strings.Index(out, "<script>"), strings.Index(out, "<title>")
	if i < 0 || j < 0 || i > j {
		t.Errorf("snippet must come right after <head>:\n%s", out)
	}
	if strings.Count(out, "</script>") != 1 {
		t.Errorf("saved value broke out of the script:\n%s", out)
	}
}
