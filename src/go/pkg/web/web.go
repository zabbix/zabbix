/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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

	"git.zabbix.com/ap/plugin-support/log"
	"golang.org/x/net/html/charset"
	"golang.org/x/text/transform"
	"zabbix.com/internal/agent"
	"zabbix.com/pkg/version"
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
