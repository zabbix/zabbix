/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	"zabbix/pkg/tls"
	"zabbix/pkg/zbxcomms"
)

type activeConnection struct {
	address   string
	localAddr net.Addr
	timeout   int
	tlsConfig *tls.Config
}

func (c *activeConnection) Write(data []byte) (n int, err error) {
	b, err := zbxcomms.Exchange(c.address, &c.localAddr, time.Second*time.Duration(c.timeout), data, c.tlsConfig)
	if err != nil {
		return 0, err
	}

	var response agentDataResponse

	err = json.Unmarshal(b, &response)
	if err != nil {
		return 0, err
	}

	if response.Response != "success" {
		if len(response.Info) != 0 {
			return 0, fmt.Errorf("%s", response.Info)
		}
		return 0, errors.New("unsuccessful response")
	}

	return len(data), nil
}

func (c *activeConnection) Addr() (s string) {
	return c.address
}

func (c *activeConnection) CanRetry() (enabled bool) {
	return true
}
