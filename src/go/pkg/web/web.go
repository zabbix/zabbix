/*
** Copyright (C) 2001-2026 Zabbix SIA
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
	"errors"
	"io"
	"net"
	"net/http"
	"net/http/httputil"
	"time"

	"golang.org/x/net/html/charset"
	"golang.org/x/text/transform"
	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/pkg/version"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
)

var errTooManyRedirects = errs.New("too many redirects")

// Get returns the specified URL content with a timeout. If dump is true,
// it returns the response headers (including all responses if following redirects)
// concatenated with the final response body. Parameter redirectLimit specifies
// the maximum number of redirects to follow; a value of 0 disables redirect following.
func Get(url string, timeout time.Duration, dump bool, redirectLimit int) (string, error) {
	var chain [][]byte

	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return "", errs.Wrap(err, "cannot create new request")
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
		CheckRedirect: redirectPolicy(redirectLimit, &chain),
	}

	resp, err := client.Do(req)
	if err != nil {
		if errors.Is(err, errTooManyRedirects) {
			return "", errs.Wrapf(err, "maximum number of redirects (%d) exceeded", redirectLimit)
		}

		return "", errs.Wrap(err, "cannot get content of web page")
	}

	defer resp.Body.Close()

	if !dump {
		return "", nil
	}

	b, err := io.ReadAll(resp.Body)
	if err != nil {
		return "", errs.Wrap(err, "cannot get content of web page")
	}

	e, name, _ := charset.DetermineEncoding(b, resp.Header.Get("content-type"))
	if err != nil {
		return "", nil
	}

	log.Debugf("determined encoding '%s'", name)

	r := transform.NewReader(bytes.NewReader(b), e.NewDecoder())

	b, err = io.ReadAll(r)
	if err != nil {
		return "", errs.Wrap(err, "cannot decode content of web page")
	}
	h, err := httputil.DumpResponse(resp, false)
	if err != nil {
		return "", errs.Wrap(err, "cannot get header of web page")
	}

	return string(bytes.Join(chain, nil)) + string(h) + string(b), nil
}

// redirectPolicy returns function that follows at most limit redirects, to be compatible with cURL,
// append the headers of every response that is redirected to chain.
func redirectPolicy(limit int, chain *[][]byte) func(req *http.Request, via []*http.Request) error {
	return func(req *http.Request, via []*http.Request) error {
		if limit <= 0 {
			return http.ErrUseLastResponse
		}

		if len(via) > limit {
			return errTooManyRedirects
		}

		h, err := httputil.DumpResponse(req.Response, false)
		if err != nil {
			return errs.Wrap(err, "cannot get header of web page")
		}

		*chain = append(*chain, h)

		return nil
	}
}
