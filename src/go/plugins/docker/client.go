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

package docker

import (
	"context"
	"encoding/json"
	"io/ioutil"
	"net"
	"net/http"
	"path"
	"time"

	"zabbix.com/pkg/zbxerr"
)

type client struct {
	client http.Client
}

func newClient(socketPath string, timeout int) *client {
	transport := &http.Transport{
		DialContext: func(_ context.Context, _, _ string) (net.Conn, error) {
			return net.Dial("unix", socketPath)
		},
	}

	client := client{}
	client.client = http.Client{
		Transport: transport,
		Timeout:   time.Duration(timeout) * time.Second,
	}

	return &client
}

func (cli *client) Query(queryPath string) ([]byte, error) {
	resp, err := cli.client.Get("http://" + path.Join(dockerVersion, queryPath))
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}
	defer resp.Body.Close()

	body, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	if resp.StatusCode != http.StatusOK {
		var apiErr ErrorMessage

		if err = json.Unmarshal(body, &apiErr); err != nil {
			return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
		}

		return nil, zbxerr.New(apiErr.Message)
	}

	return body, nil
}
