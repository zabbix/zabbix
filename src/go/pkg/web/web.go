/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package web

import (
	"bytes"
	"crypto/tls"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/http/httputil"
	"time"

	"golang.org/x/net/html/charset"
	"golang.org/x/text/transform"
	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/sdk/log"
)

// Get makes a GET request to the provided web page url, using an http client, provides a response dump if dump
// parameter is set
func Get(url string, timeout time.Duration, dump bool) (string, error) {
	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return "", fmt.Errorf("Cannot create new request: %w", err)
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
		return "", fmt.Errorf("Cannot get content of web page: %w", err)
	}

	defer resp.Body.Close()

	if !dump {
		return "", nil
	}

	b, err := io.ReadAll(resp.Body)
	if err != nil {
		return "", fmt.Errorf("Cannot get content of web page: %w", err)
	}

	e, name, _ := charset.DetermineEncoding(b, resp.Header.Get("content-type"))
	if err != nil {
		return "", nil
	}

	log.Debugf("determined encoding '%s'", name)

	r := transform.NewReader(bytes.NewReader(b), e.NewDecoder())

	b, err = io.ReadAll(r)
	if err != nil {
		return "", fmt.Errorf("Cannot decode content of web page: %w", err)
	}
	h, err := httputil.DumpResponse(resp, false)
	if err != nil {
		return "", fmt.Errorf("Cannot get header of web page: %w", err)
	}

	return string(h) + string(b), nil
}

func disableRedirect(req *http.Request, via []*http.Request) error {
	return http.ErrUseLastResponse
}
