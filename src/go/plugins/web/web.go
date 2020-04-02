/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
	"bufio"
	"bytes"
	"fmt"
	"net"
	"net/http"
	"net/http/httputil"
	"net/url"
	"regexp"
	"strconv"
	"strings"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/version"
	"zabbix.com/pkg/zbxregexp"
)

type Options struct {
	Timeout int `conf:"optional,range=1:30"`
}

type Plugin struct {
	plugin.Base
	options Options
}

var impl Plugin

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	if p.options.Timeout == 0 {
		p.options.Timeout = global.Timeout
	}
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}

func disableRedirect(req *http.Request, via []*http.Request) error {
	return http.ErrUseLastResponse
}

func (p *Plugin) webPageGet(params []string, dump bool) (string, error) {
	req, err := http.NewRequest("GET", params[0], nil)
	if err != nil {
		return "", fmt.Errorf("Cannot create new request: %s", err)
	}

	req.Header = map[string][]string{
		"User-Agent": {"Zabbix " + version.Long()},
	}

	client := &http.Client{
		Transport: &http.Transport{
			Proxy:             http.ProxyFromEnvironment,
			DisableKeepAlives: true,
			DialContext: (&net.Dialer{
				LocalAddr: &net.TCPAddr{IP: net.ParseIP(agent.Options.SourceIP), Port: 0},
			}).DialContext,
		},
		Timeout:       time.Duration(p.options.Timeout) * time.Second,
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

func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (interface{}, error) {
	if len(params) == 0 || params[0] == "" {
		return nil, fmt.Errorf("Invalid first parameter.")
	}

	u, err := url.Parse(params[0])
	if err != nil {
		return nil, fmt.Errorf("Cannot parse url: %s", err)
	}

	if u.Scheme == "" || u.Opaque != "" {
		params[0] = "http://" + params[0]
	}

	if len(params) > 2 && params[2] != "" {
		params[0] += ":" + params[2]
	}

	if len(params) > 1 && params[1] != "" {
		if params[1][0] != '/' {
			params[0] += "/"
		}

		params[0] += params[1]
	}

	switch key {
	case "web.page.regexp":
		var length *int
		var output string

		if len(params) > 6 {
			return nil, fmt.Errorf("Too many parameters.")
		}

		if len(params) < 4 {
			return nil, fmt.Errorf("Invalid number of parameters.")
		}

		rx, err := regexp.Compile(params[3])
		if err != nil {
			return nil, fmt.Errorf("Invalid forth parameter: %s", err)
		}

		if len(params) > 4 && params[4] != "" {
			if n, err := strconv.Atoi(params[4]); err != nil {
				return nil, fmt.Errorf("Invalid fifth parameter: %s", err)
			} else {
				length = &n
			}
		}

		if len(params) > 5 && params[5] != "" {
			output = params[5]
		} else {
			output = "\\0"
		}

		s, err := p.webPageGet(params, true)
		if err != nil {
			return nil, err
		}

		scanner := bufio.NewScanner(strings.NewReader(s))
		for scanner.Scan() {
			if out, ok := zbxregexp.ExecuteRegex(scanner.Bytes(), rx, []byte(output)); ok {
				if length != nil {
					out = agent.CutAfterN(out, *length)
				}
				return out, nil
			}
		}

		return "", nil
	case "web.page.perf":
		if len(params) > 3 {
			return nil, fmt.Errorf("Too many parameters.")
		}

		start := time.Now()

		_, err := p.webPageGet(params, false)
		if err != nil {
			return nil, err
		}

		return time.Since(start).Seconds(), nil
	default:
		if len(params) > 3 {
			return nil, fmt.Errorf("Too many parameters.")
		}

		return p.webPageGet(params, true)
	}

}

func init() {
	plugin.RegisterMetrics(&impl, "Web",
		"web.page.get", "Get content of a web page.",
		"web.page.perf", "Loading time of full web page (in seconds).",
		"web.page.regexp", "Find string on a web page.")
}
