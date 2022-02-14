/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
package webpage

import (
	"bufio"
	"fmt"
	"net/url"
	"regexp"
	"strconv"
	"strings"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/conf"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/web"
	"zabbix.com/pkg/zbxregexp"
)

type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	Timeout              int `conf:"optional,range=1:30"`
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

		s, err := web.Get(params[0], time.Duration(p.options.Timeout)*time.Second, true)
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

		_, err := web.Get(params[0], time.Duration(p.options.Timeout)*time.Second, false)
		if err != nil {
			return nil, err
		}

		return time.Since(start).Seconds(), nil
	default:
		if len(params) > 3 {
			return nil, fmt.Errorf("Too many parameters.")
		}

		return web.Get(params[0], time.Duration(p.options.Timeout)*time.Second, true)
	}

}

func init() {
	plugin.RegisterMetrics(&impl, "WebPage",
		"web.page.get", "Get content of a web page.",
		"web.page.perf", "Loading time of full web page (in seconds).",
		"web.page.regexp", "Find string on a web page.")
}
