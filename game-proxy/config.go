package main

import (
	"bufio"
	"os"
	"strconv"
	"strings"
)

// Config comes from the environment; Laravel sets it (game-proxy:start /
// game-proxy:ensure pass what is in config/gameproxy.php).
type Config struct {
	Listen        string
	Secret        string
	APIURL        string
	ParentOrigins []string
	MaxSaveBytes  int
	Blocklist     *Blocklist
}

func loadConfig() Config {
	cfg := Config{
		Listen:       env("GP_LISTEN", "127.0.0.1:8099"),
		Secret:       os.Getenv("GP_SECRET"),
		APIURL:       strings.TrimRight(env("GP_API_URL", "http://127.0.0.1"), "/"),
		MaxSaveBytes: 512 * 1024,
	}
	if n, err := strconv.Atoi(os.Getenv("GP_MAX_SAVE_BYTES")); err == nil && n > 0 {
		cfg.MaxSaveBytes = n
	}
	for _, o := range strings.Split(os.Getenv("GP_PARENT_ORIGINS"), ",") {
		if o = strings.TrimSpace(o); o != "" {
			cfg.ParentOrigins = append(cfg.ParentOrigins, o)
		}
	}

	hosts := append([]string{}, defaultBlockedHosts...)
	if path := os.Getenv("GP_BLOCKLIST_FILE"); path != "" {
		hosts = append(hosts, readHostFile(path)...)
	}
	cfg.Blocklist = newBlocklist(hosts)
	return cfg
}

func env(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

// readHostFile reads one host per line; blank lines and # comments are skipped.
func readHostFile(path string) []string {
	f, err := os.Open(path)
	if err != nil {
		return nil
	}
	defer f.Close()

	var hosts []string
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		line := strings.TrimSpace(sc.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		hosts = append(hosts, line)
	}
	return hosts
}
