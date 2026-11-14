package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"errors"
	"strings"
	"time"
)

// Claims is the payload of the signed play link Laravel makes
// (app/Services/GameProxyToken.php): who plays, which game, which address the
// game is streamed from, and when the link stops working.
type Claims struct {
	User     int64  `json:"u"`
	Slug     string `json:"g"`
	Upstream string `json:"o"`
	Expires  int64  `json:"e"`
}

var errBadToken = errors.New("invalid or expired token")

// verifyToken checks the HMAC-SHA256 signature and the expiry.
func verifyToken(token, secret string) (*Claims, error) {
	parts := strings.Split(token, ".")
	if len(parts) != 2 || secret == "" {
		return nil, errBadToken
	}

	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write([]byte(parts[0]))
	expected := base64.RawURLEncoding.EncodeToString(mac.Sum(nil))
	if !hmac.Equal([]byte(expected), []byte(parts[1])) {
		return nil, errBadToken
	}

	raw, err := base64.RawURLEncoding.DecodeString(parts[0])
	if err != nil {
		return nil, errBadToken
	}
	var c Claims
	if err := json.Unmarshal(raw, &c); err != nil {
		return nil, errBadToken
	}
	if c.Expires < time.Now().Unix() || c.User <= 0 || c.Slug == "" || c.Upstream == "" {
		return nil, errBadToken
	}
	return &c, nil
}
