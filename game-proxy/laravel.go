package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"time"
)

// Cookie is one cookie the account keeps for a game (name + value only: the
// browser re-creates the attributes).
type Cookie struct {
	Name  string `json:"name"`
	Value string `json:"value"`
}

// SaveData is what Laravel stores per account and game.
type SaveData struct {
	Cookies   []Cookie          `json:"cookies"`
	Storage   map[string]string `json:"storage"`
	UpdatedAt int64             `json:"updated_at"`
}

// API talks to the Laravel internal endpoints
// (app/Http/Controllers/GameProxyController.php), authenticated with the
// shared secret. It is the only thing that knows where the data lives.
type API struct {
	base   string
	secret string
	client *http.Client
}

func newAPI(cfg Config) *API {
	return &API{
		base:   cfg.APIURL,
		secret: cfg.Secret,
		client: &http.Client{Timeout: 6 * time.Second},
	}
}

func (a *API) savesURL(user int64, slug string) string {
	return fmt.Sprintf("%s/v1.0/internal/game-proxy/saves/%d/%s", a.base, user, url.PathEscape(slug))
}

func (a *API) load(user int64, slug string) (*SaveData, error) {
	req, err := http.NewRequest(http.MethodGet, a.savesURL(user, slug), nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("X-Game-Proxy-Key", a.secret)
	req.Header.Set("Accept", "application/json")

	resp, err := a.client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("load save: status %d", resp.StatusCode)
	}

	var data SaveData
	if err := json.NewDecoder(io.LimitReader(resp.Body, 4<<20)).Decode(&data); err != nil {
		return nil, err
	}
	if data.Storage == nil {
		data.Storage = map[string]string{}
	}
	return &data, nil
}

// save writes the cookies + storage and returns the new updated_at.
func (a *API) save(user int64, slug string, cookies []Cookie, storage map[string]string) (int64, error) {
	if cookies == nil {
		cookies = []Cookie{}
	}
	if storage == nil {
		storage = map[string]string{}
	}
	body, err := json.Marshal(map[string]any{"cookies": cookies, "storage": storage})
	if err != nil {
		return 0, err
	}

	req, err := http.NewRequest(http.MethodPut, a.savesURL(user, slug), bytes.NewReader(body))
	if err != nil {
		return 0, err
	}
	req.Header.Set("X-Game-Proxy-Key", a.secret)
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Content-Type", "application/json")

	resp, err := a.client.Do(req)
	if err != nil {
		return 0, err
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<16))
	if resp.StatusCode != http.StatusOK {
		return 0, fmt.Errorf("save: status %d: %s", resp.StatusCode, strconv.Quote(string(raw)))
	}

	var out struct {
		UpdatedAt int64 `json:"updated_at"`
	}
	_ = json.Unmarshal(raw, &out)
	return out.UpdatedAt, nil
}
