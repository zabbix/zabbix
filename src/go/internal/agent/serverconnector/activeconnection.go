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

package serverconnector

import (
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"time"

	"golang.zabbix.com/agent2/pkg/tls"
	"golang.zabbix.com/agent2/pkg/zbxcomms"
)

var errUnsuccessfulResponse = errors.New("unsuccessful response")

type activeConnection struct {
	address   zbxcomms.AddressSet
	hostname  string
	localAddr net.Addr
	tlsConfig *tls.Config
	timeout   int
	session   string
}

func (c *activeConnection) Write(data []byte, timeout time.Duration) (bool, bool, []error) {
	upload := false
	b, commsFailed, errs, _ := zbxcomms.ExchangeWithRedirect(c.address, &c.localAddr, timeout,
		time.Second*time.Duration(c.timeout), data, c.tlsConfig)
	if errs != nil {
		return upload, commsFailed, errs
	}

	var response agentDataResponse

	err := json.Unmarshal(b, &response)
	if err != nil {
		c.address.Next()

		return upload, false, []error{err}
	}

	if response.Response != "success" {
		c.address.Next()

		if len(response.Info) != 0 {
			return upload, false, []error{fmt.Errorf("%w: %s", errUnsuccessfulResponse, response.Info)}
		}

		return upload, false, []error{errUnsuccessfulResponse}
	}

	if response.HistoryUpload != "disabled" {
		upload = true
	}

	return upload, false, nil
}

func (c *activeConnection) Addr() (s string) {
	return c.address.Get()
}

func (c *activeConnection) Session() (s string) {
	return c.session
}

func (c *activeConnection) Hostname() (s string) {
	return c.hostname
}

func (c *activeConnection) CanRetry() (enabled bool) {
	return true
}
