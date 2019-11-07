package stats

import (
	"encoding/json"
	"fmt"
	"net"
	"strconv"
	"time"

	"zabbix.com/internal/agent"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/zbxcomms"
)

// Plugin -
type Plugin struct {
	plugin.Base
	timeout   time.Duration
	localAddr net.Addr
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

const defaultServerPort = 10051

var impl Plugin

func (p *Plugin) getRemoteZabbixStats(addr string, req []byte) ([]byte, error) {
	var parse response

	resp, err := zbxcomms.Exchange(addr, &p.localAddr, p.timeout, req)

	if err != nil {
		return nil, fmt.Errorf("Cannot obtain internal statistics: %s", err)
	}

	if len(resp) <= 0 {
		return nil, fmt.Errorf("Cannot obtain internal statistics: received empty response.")
	}

	err = json.Unmarshal(resp, &parse)

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

	resp, err := p.getRemoteZabbixStats(addr, req)

	if err != nil {
		return nil, err
	}

	str := string(resp)

	return str, nil
}

// Configure -
func (p *Plugin) Configure(options map[string]string) {
	p.timeout = time.Second * time.Duration(agent.Options.Timeout)
	p.localAddr = &net.TCPAddr{IP: net.ParseIP(agent.Options.SourceIP), Port: 0}
}

func init() {
	plugin.RegisterMetrics(&impl, "ZabbixStats", "zabbix.stats", "Return a set of Zabbix server or proxy internal "+
		"metrics or return number of monitored items in the queue which are delayed on Zabbix server or proxy.")
}
