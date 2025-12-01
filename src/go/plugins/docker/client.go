/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

package docker

import (
	"context"
	"net"
	"net/http"
	"time"
)

func newClient(socketPath string, timeout int) *http.Client {
	transport := &http.Transport{
		DialContext: func(_ context.Context, _, _ string) (net.Conn, error) {
			return net.Dial("unix", socketPath)
		},
	}

	return &http.Client{
		Transport: transport,
		Timeout:   time.Duration(timeout) * time.Second,
	}
}
