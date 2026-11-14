package main

import (
	"crypto/sha256"
	"encoding/hex"
	"log"
	"net/url"
	"sort"
	"sync"
	"time"
)

const (
	maxSessions   = 300
	sessionIdle   = 30 * time.Minute
	flushInterval = 10 * time.Second
)

// Session is one player in one game. It is rebuilt from the signed token (the
// cookie holds the token), so a restart of the server loses nothing: the data
// itself lives in Laravel.
type Session struct {
	mu       sync.Mutex
	claims   *Claims
	upstream *url.URL
	cookies  map[string]string // what the browser holds for the game
	storage  map[string]string // localStorage as last pushed by the page
	version  int64             // updated_at of the stored save
	dirty    bool
	lastSeen time.Time
}

// Sessions is the table of live sessions, keyed by a hash of the token.
type Sessions struct {
	mu  sync.Mutex
	m   map[string]*Session
	api *API
}

func newSessions(api *API) *Sessions {
	return &Sessions{m: map[string]*Session{}, api: api}
}

func tokenKey(token string) string {
	sum := sha256.Sum256([]byte(token))
	return hex.EncodeToString(sum[:16])
}

// get returns the session for a verified token, creating it (and loading the
// account's save from Laravel) the first time.
func (s *Sessions) get(token string, claims *Claims) (*Session, error) {
	key := tokenKey(token)

	s.mu.Lock()
	if sess, ok := s.m[key]; ok {
		sess.touch()
		s.mu.Unlock()
		return sess, nil
	}
	s.mu.Unlock()

	up, err := url.Parse(claims.Upstream)
	if err != nil || (up.Scheme != "http" && up.Scheme != "https") || up.Host == "" {
		return nil, errBadToken
	}

	sess := &Session{
		claims:   claims,
		upstream: up,
		cookies:  map[string]string{},
		storage:  map[string]string{},
		lastSeen: time.Now(),
	}
	if data, err := s.api.load(claims.User, claims.Slug); err != nil {
		// Playing still works without the save; it just starts fresh.
		log.Printf("load save user=%d game=%s: %v", claims.User, claims.Slug, err)
	} else {
		for _, c := range data.Cookies {
			sess.cookies[c.Name] = c.Value
		}
		sess.storage = data.Storage
		sess.version = data.UpdatedAt
	}

	s.mu.Lock()
	defer s.mu.Unlock()
	if existing, ok := s.m[key]; ok { // lost a race: use the winner
		return existing, nil
	}
	if len(s.m) >= maxSessions {
		s.evictOldestLocked()
	}
	s.m[key] = sess
	return sess, nil
}

func (s *Sessions) evictOldestLocked() {
	var oldestKey string
	var oldest time.Time
	for k, v := range s.m {
		v.mu.Lock()
		seen := v.lastSeen
		v.mu.Unlock()
		if oldestKey == "" || seen.Before(oldest) {
			oldestKey, oldest = k, seen
		}
	}
	if oldestKey != "" {
		s.flush(s.m[oldestKey])
		delete(s.m, oldestKey)
	}
}

func (x *Session) touch() {
	x.mu.Lock()
	x.lastSeen = time.Now()
	x.mu.Unlock()
}

// setCookie / deleteCookie record what the game does to the cookie jar.
func (x *Session) setCookie(name, value string) {
	x.mu.Lock()
	if x.cookies[name] != value {
		x.cookies[name] = value
		x.dirty = true
	}
	x.mu.Unlock()
}

func (x *Session) deleteCookie(name string) {
	x.mu.Lock()
	if _, ok := x.cookies[name]; ok {
		delete(x.cookies, name)
		x.dirty = true
	}
	x.mu.Unlock()
}

// observeCookies syncs the jar with the Cookie header of a request: what the
// browser sends is what it holds (it also picks up cookies set by game scripts).
func (x *Session) observeCookies(seen map[string]string) {
	x.mu.Lock()
	defer x.mu.Unlock()
	changed := len(seen) != len(x.cookies)
	if !changed {
		for k, v := range seen {
			if old, ok := x.cookies[k]; !ok || old != v {
				changed = true
				break
			}
		}
	}
	if changed {
		x.cookies = seen
		x.dirty = true
	}
}

func (x *Session) setStorage(storage map[string]string) {
	x.mu.Lock()
	x.storage = storage
	x.dirty = true
	x.mu.Unlock()
}

func (x *Session) snapshot() (cookies []Cookie, storage map[string]string, version int64) {
	x.mu.Lock()
	defer x.mu.Unlock()
	names := make([]string, 0, len(x.cookies))
	for k := range x.cookies {
		names = append(names, k)
	}
	sort.Strings(names)
	for _, k := range names {
		cookies = append(cookies, Cookie{Name: k, Value: x.cookies[k]})
	}
	storage = make(map[string]string, len(x.storage))
	for k, v := range x.storage {
		storage[k] = v
	}
	return cookies, storage, x.version
}

// flush writes a session to Laravel if it changed. It returns the new version.
func (s *Sessions) flush(x *Session) int64 {
	x.mu.Lock()
	if !x.dirty {
		v := x.version
		x.mu.Unlock()
		return v
	}
	x.dirty = false
	claims := x.claims
	x.mu.Unlock()

	cookies, storage, _ := x.snapshot()
	updated, err := s.api.save(claims.User, claims.Slug, cookies, storage)
	if err != nil {
		log.Printf("save user=%d game=%s: %v", claims.User, claims.Slug, err)
		x.mu.Lock()
		x.dirty = true // try again next tick
		x.mu.Unlock()
		return 0
	}
	x.mu.Lock()
	x.version = updated
	x.mu.Unlock()
	return updated
}

// run flushes changed sessions every few seconds and drops idle ones.
func (s *Sessions) run() {
	t := time.NewTicker(flushInterval)
	defer t.Stop()
	for range t.C {
		s.mu.Lock()
		all := make([]*Session, 0, len(s.m))
		keys := make([]string, 0, len(s.m))
		for k, v := range s.m {
			all = append(all, v)
			keys = append(keys, k)
		}
		s.mu.Unlock()

		now := time.Now()
		for i, sess := range all {
			s.flush(sess)
			sess.mu.Lock()
			idle := now.Sub(sess.lastSeen) > sessionIdle
			dirty := sess.dirty
			sess.mu.Unlock()
			if idle && !dirty {
				s.mu.Lock()
				delete(s.m, keys[i])
				s.mu.Unlock()
			}
		}
	}
}
