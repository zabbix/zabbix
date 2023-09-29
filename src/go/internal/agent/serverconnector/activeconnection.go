/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	session   string
}

func (c *activeConnection) Write(data []byte, timeout time.Duration) (bool, []error) {
	upload := true

	b, errs, _ := zbxcomms.Exchange(&c.addresses, &c.localAddr, timeout, time.Second*time.Duration(c.timeout),
		data, c.tlsConfig)
	if errs != nil {
		return upload, errs
	}

	var response agentDataResponse

	err := json.Unmarshal(b, &response)
	if err != nil {
		return upload, []error{err}
	}

	if response.HistoryUpload == "disabled" {
		upload = false
	}

	if response.Response != "success" {
		if len(response.Info) != 0 {
			return upload, []error{fmt.Errorf("%s", response.Info)}
		}

		return upload, []error{errors.New("unsuccessful response")}
	}

	return upload, nil
}

func (c *activeConnection) Addr() (s string) {
	return c.addresses[0]
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
