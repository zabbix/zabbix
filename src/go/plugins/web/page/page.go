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
package webpage

import (
	"bufio"
	"fmt"
	"net/url"
	"regexp"
	"strconv"
	"strings"
	"time"

	"golang.zabbix.com/agent2/internal/agent"
	"golang.zabbix.com/agent2/pkg/web"
	"golang.zabbix.com/agent2/pkg/zbxregexp"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

var impl Plugin

type Plugin struct {
	plugin.Base
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "WebPage",
		"web.page.get", "Get content of a web page.",
		"web.page.perf", "Loading time of full web page (in seconds).",
		"web.page.regexp", "Find string on a web page.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
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

		s, err := web.Get(params[0], time.Duration(ctx.Timeout())*time.Second, true)
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

		_, err := web.Get(params[0], time.Duration(ctx.Timeout())*time.Second, false)
		if err != nil {
			return nil, err
		}

		return time.Since(start).Seconds(), nil
	default:
		if len(params) > 3 {
			return nil, fmt.Errorf("Too many parameters.")
		}

		return web.Get(params[0], time.Duration(ctx.Timeout())*time.Second, true)
	}
}
