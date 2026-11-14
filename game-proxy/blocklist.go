package main

import (
	"net/url"
	"strings"
)

// defaultBlockedHosts: well-known ad / tracker hosts. A host in this list also
// blocks every subdomain. Extra hosts go in game-proxy/blocklist.txt (see
// GP_BLOCKLIST_FILE), one per line, so ad hosts can be added without a rebuild.
var defaultBlockedHosts = []string{
	"doubleclick.net",
	"googlesyndication.com",
	"googleadservices.com",
	"adservice.google.com",
	"imasdk.googleapis.com",
	"google-analytics.com",
	"googletagmanager.com",
	"googletagservices.com",
	"adnxs.com",
	"taboola.com",
	"outbrain.com",
	"criteo.com",
	"criteo.net",
	"adsrvr.org",
	"pubmatic.com",
	"rubiconproject.com",
	"openx.net",
	"amazon-adsystem.com",
	"smartadserver.com",
	"adform.net",
	"casalemedia.com",
	"moatads.com",
	"advertising.com",
	"popads.net",
	"exoclick.com",
	"propellerads.com",
	"adcolony.com",
	"applovin.com",
	"unityads.unity3d.com",
	"ads.yahoo.com",
}

// Blocklist answers "is this URL an ad / tracker host?".
type Blocklist struct {
	hosts map[string]bool
	list  []string
}

func newBlocklist(hosts []string) *Blocklist {
	b := &Blocklist{hosts: map[string]bool{}}
	for _, h := range hosts {
		h = strings.ToLower(strings.TrimSpace(h))
		h = strings.TrimPrefix(h, "*.")
		if h != "" && !b.hosts[h] {
			b.hosts[h] = true
			b.list = append(b.list, h)
		}
	}
	return b
}

// HostBlocked: the host, or any parent domain of it, is in the list.
func (b *Blocklist) HostBlocked(host string) bool {
	host = strings.ToLower(strings.TrimSuffix(host, "."))
	for host != "" {
		if b.hosts[host] {
			return true
		}
		i := strings.IndexByte(host, '.')
		if i < 0 {
			return false
		}
		host = host[i+1:]
	}
	return false
}

// URLBlocked works on an absolute or protocol-relative URL; a relative URL
// points at the game itself and is never blocked.
func (b *Blocklist) URLBlocked(raw string) bool {
	raw = strings.TrimSpace(raw)
	if strings.HasPrefix(raw, "//") {
		raw = "https:" + raw
	}
	u, err := url.Parse(raw)
	if err != nil || u.Host == "" {
		return false
	}
	return b.HostBlocked(u.Hostname())
}

// List is the host list handed to the page-side shim.
func (b *Blocklist) List() []string { return b.list }
