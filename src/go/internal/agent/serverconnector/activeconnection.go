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

type activeConnection struct {
	address   zbxcomms.AddressSet
	hostname  string
	localAddr net.Addr
	tlsConfig *tls.Config
	timeout   int
	session   string
}

func (c *activeConnection) Write(data []byte, timeout time.Duration) (bool, []error) {
	upload := true

	b, errs, _ := zbxcomms.ExchangeWithRedirect(c.address, &c.localAddr, timeout,
		time.Second*time.Duration(c.timeout), data, c.tlsConfig)
	if errs != nil {
		return upload, errs
	}

	var response agentDataResponse

	err := json.Unmarshal(b, &response)
	if err != nil {
		c.address.Next()
		return upload, []error{err}
	}

	if response.HistoryUpload == "disabled" {
		upload = false
	}

	if response.Response != "success" {
		c.address.Next()

		if len(response.Info) != 0 {
			return upload, []error{fmt.Errorf("%s", response.Info)}
		}

		return upload, []error{errors.New("unsuccessful response")}
	}

	return upload, nil
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
