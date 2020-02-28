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
	address   string
	localAddr net.Addr
	tlsConfig *tls.Config
}

func (c *activeConnection) Write(data []byte, timeout time.Duration) (err error) {
	b, err := zbxcomms.Exchange(c.address, &c.localAddr, timeout, data, c.tlsConfig)
	if err != nil {
		return err
	}

	var response agentDataResponse

	err = json.Unmarshal(b, &response)
	if err != nil {
		return err
	}

	if response.Response != "success" {
		if len(response.Info) != 0 {
			return fmt.Errorf("%s", response.Info)
		}
		return errors.New("unsuccessful response")
	}

	return nil
}

func (c *activeConnection) Addr() (s string) {
	return c.address
}

func (c *activeConnection) CanRetry() (enabled bool) {
	return true
}
