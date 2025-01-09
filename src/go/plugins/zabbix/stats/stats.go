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

package stats

import (
	"encoding/json"
	"fmt"
	"net"
	"strconv"
	"time"

	"golang.zabbix.com/agent2/pkg/zbxcomms"
	"golang.zabbix.com/sdk/conf"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/plugin"
)

const defaultServerPort = 10051

var impl Plugin

type Options struct {
	plugin.SystemOptions `conf:"optional,name=System"`
	SourceIP             string `conf:"optional"`
}

// Plugin -
type Plugin struct {
	plugin.Base
	localAddr net.Addr
	options   Options
}

type queue struct {
	From string `json:"from,omitempty"`
	To   string `json:"to,omitempty"`
}

type message struct {
	Request string `json:"request"`
	Type    string `json:"type,omitempty"`
	Params  *queue `json:"params,omitempty"`
}

type response struct {
	Response string `json:"response"`
	Info     string `json:"info,omitempty"`
}

func init() {
	err := plugin.RegisterMetrics(
		&impl, "ZabbixStats",
		"zabbix.stats", "Return a set of Zabbix server or proxy internal "+
			"metrics or return number of monitored items in the queue which are delayed on Zabbix server or proxy.",
	)
	if err != nil {
		panic(errs.Wrap(err, "failed to register metrics"))
	}
}

func (p *Plugin) getRemoteZabbixStats(addr string, req []byte, timeout int) ([]byte, error) {
	var parse response

	resp, errs, _ := zbxcomms.Exchange(
		zbxcomms.NewAddress(addr),
		&p.localAddr,
		time.Duration(timeout)*time.Second,
		time.Duration(timeout)*time.Second,
		req,
	)

	if errs != nil {
		return nil, fmt.Errorf("Cannot obtain internal statistics: %s", errs[0])
	}

	if len(resp) <= 0 {
		return nil, fmt.Errorf("Cannot obtain internal statistics: received empty response.")
	}

	err := json.Unmarshal(resp, &parse)
	if err != nil {
		return nil, fmt.Errorf("Value should be a JSON object.")
	}

	if parse.Response != "success" {
		if len(parse.Info) != 0 {
			return nil, fmt.Errorf("Cannot obtain internal statistics: %s", parse.Info)
		}

		return nil, fmt.Errorf("Cannot find tag: info")
	}

	return resp, nil
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (interface{}, error) {
	var addr string
	var m message
	var q queue

	if len(params) > 5 {
		return nil, fmt.Errorf("Too many parameters.")
	}

	if len(params) < 1 || params[0] == "" {
		addr = fmt.Sprintf("127.0.0.1:%d", defaultServerPort)
	} else {
		addr = params[0]
		if len(params) > 1 && params[1] != "" {
			port, err := strconv.ParseUint(params[1], 10, 16)
			if err != nil {
				return nil, fmt.Errorf("Invalid second parameter.")
			}

			addr = fmt.Sprintf("%s:%d", addr, port)
		} else {
			addr = fmt.Sprintf("%s:%d", addr, defaultServerPort)
		}
	}

	if len(params) > 2 {
		if params[2] != "queue" {
			return nil, fmt.Errorf("Invalid third parameter.")
		}

		if len(params) > 3 {
			if len(params) > 4 {
				q.To = params[4]
			}

			q.From = params[3]
		}

		m.Params = &q
		m.Type = "queue"
	}

	m.Request = "zabbix.stats"
	req, err := json.Marshal(m)
	if err != nil {
		return nil, fmt.Errorf("Cannot obtain internal statistics: %s", err)
	}

	resp, err := p.getRemoteZabbixStats(addr, req, ctx.Timeout())
	if err != nil {
		return nil, err
	}

	str := string(resp)

	return str, nil
}

func (p *Plugin) Configure(global *plugin.GlobalOptions, options interface{}) {
	if err := conf.Unmarshal(options, &p.options); err != nil {
		p.Warningf("cannot unmarshal configuration options: %s", err)
	}
	if p.options.SourceIP == "" {
		p.options.SourceIP = global.SourceIP
	}
}

func (p *Plugin) Validate(options interface{}) error {
	var o Options
	return conf.Unmarshal(options, &o)
}
