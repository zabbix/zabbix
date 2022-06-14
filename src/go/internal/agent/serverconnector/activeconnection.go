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

package serverconnector

import (
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"time"

	"zabbix.com/pkg/tls"
	"zabbix.com/pkg/zbxcomms"
)

type activeConnection struct {
	addresses []string
	hostname  string
	localAddr net.Addr
	tlsConfig *tls.Config
	timeout   int
}

func (c *activeConnection) Write(data []byte, timeout time.Duration) []error {
	b, errs := zbxcomms.Exchange(&c.addresses, &c.localAddr, timeout, time.Second*time.Duration(c.timeout),
		data, c.tlsConfig)
	if errs != nil {
		return errs
	}

	var response agentDataResponse

	err := json.Unmarshal(b, &response)
	if err != nil {
		return []error{err}
	}

	if response.Response != "success" {
		if len(response.Info) != 0 {
			return []error{fmt.Errorf("%s", response.Info)}
		}

		return []error{errors.New("unsuccessful response")}
	}

	return nil
}

func (c *activeConnection) Addr() (s string) {
	return c.addresses[0]
}

func (c *activeConnection) Hostname() (s string) {
	return c.hostname
}

func (c *activeConnection) CanRetry() (enabled bool) {
	return true
}
