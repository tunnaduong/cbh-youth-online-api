package main

import "testing"

func TestBlocklistWithGameOptions(t *testing.T) {
	base := newBlocklist([]string{"adnxs.com", "imasdk.googleapis.com"})

	// The game's own settings from the database: block one more host, and never
	// block the ad SDK host (this game hangs without it).
	game := base.With([]string{"ads.partner.com"}, []string{"imasdk.googleapis.com"})
	if !game.HostBlocked("ads.partner.com") || !game.HostBlocked("x.adnxs.com") {
		t.Error("extra / base hosts should be blocked")
	}
	if game.HostBlocked("imasdk.googleapis.com") {
		t.Error("an allowed host must not be blocked")
	}

	// The shared list is untouched by one game's settings.
	if !base.HostBlocked("imasdk.googleapis.com") || base.HostBlocked("ads.partner.com") {
		t.Error("the base list changed")
	}
	if base.With(nil, nil) != base {
		t.Error("no options should reuse the same list")
	}
}

func TestClaimsOptionsAreOptional(t *testing.T) {
	// The vector in token_test.go has no "x": options must stay optional.
	c, err := verifyToken(phpToken, "test-secret")
	if err != nil || c.Options != nil {
		t.Fatalf("expected no options, got %+v (%v)", c.Options, err)
	}
}
