package web

import (
	"bytes"
	"crypto/tls"
	"fmt"
	"net"
	"net/http"
	"net/http/httputil"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/version"
)

// Get makes a GET request to the provided web page url, using an http client, provides a response dump if dump
// parameter is set
func Get(url string, timeout time.Duration, dump bool) (string, error) {
	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return "", fmt.Errorf("Cannot create new request: %s", err)
	}

	req.Header = map[string][]string{
		"User-Agent": {"Zabbix " + version.Long()},
	}

	client := &http.Client{
		Transport: &http.Transport{
			TLSClientConfig:   &tls.Config{InsecureSkipVerify: true},
			Proxy:             http.ProxyFromEnvironment,
			DisableKeepAlives: true,
			DialContext: (&net.Dialer{
				LocalAddr: &net.TCPAddr{IP: net.ParseIP(agent.Options.SourceIP), Port: 0},
			}).DialContext,
		},
		Timeout:       timeout,
		CheckRedirect: disableRedirect,
	}

	resp, err := client.Do(req)
	if err != nil {
		return "", fmt.Errorf("Cannot get content of web page: %s", err)
	}

	defer resp.Body.Close()

	if !dump {
		return "", nil
	}

	b, err := httputil.DumpResponse(resp, true)
	if err != nil {
		return "", fmt.Errorf("Cannot get content of web page: %s", err)
	}

	return string(bytes.TrimRight(b, "\r\n")), nil
}

func disableRedirect(req *http.Request, via []*http.Request) error {
	return http.ErrUseLastResponse
}
