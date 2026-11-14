package main

import (
	"strings"
	"testing"
)

// A token made by Laravel's App\Services\GameProxyToken::make() (generated with
// PHP, secret "test-secret", expires in 2100): the two sides must agree.
const phpToken = "eyJ1Ijo0MiwiZyI6Im9tLW5vbS1ydW4iLCJvIjoiaHR0cHM6Ly9nYW1lcy5leGFtcGxlLmNvbS9vbS1ub20vaW5kZXguaHRtbD9sYW5nPXZpIiwiZSI6NDEwMjQ0NDgwMH0.zi0am6958WrY8ubEXqlZiaAkYYEjvPM64Hjjf6jCZ5c"

func TestVerifyTokenFromLaravel(t *testing.T) {
	c, err := verifyToken(phpToken, "test-secret")
	if err != nil {
		t.Fatalf("token from PHP rejected: %v", err)
	}
	if c.User != 42 || c.Slug != "om-nom-run" || c.Upstream != "https://games.example.com/om-nom/index.html?lang=vi" {
		t.Fatalf("wrong claims: %+v", c)
	}
}

func TestVerifyTokenRejects(t *testing.T) {
	if _, err := verifyToken(phpToken, "other-secret"); err == nil {
		t.Error("wrong secret accepted")
	}
	parts := strings.Split(phpToken, ".")
	if _, err := verifyToken(parts[0]+".AAAA", "test-secret"); err == nil {
		t.Error("forged signature accepted")
	}
	if _, err := verifyToken("nonsense", "test-secret"); err == nil {
		t.Error("garbage accepted")
	}
	if _, err := verifyToken(phpToken, ""); err == nil {
		t.Error("empty secret accepted")
	}
}
