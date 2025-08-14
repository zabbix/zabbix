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

package ceph

import (
	"encoding/json"

	"golang.zabbix.com/agent2/plugins/ceph/conn"
	"golang.zabbix.com/agent2/plugins/ceph/handlers"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

func (*Plugin) nativeRequest(
	connection *conn.Conn,
	cmd handlers.Command,
	args map[string]string,
) ([]byte, error) {
	params := map[string]string{
		"prefix": string(cmd),
		"format": "json",
	}

	for k, v := range args {
		params[k] = v
	}

	monCommand, err := json.Marshal(params)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	res, _, err := connection.Command(monCommand)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotFetchData)
	}

	return res, nil
}

func (p *Plugin) asyncNativeRequest(
	connection *conn.Conn,
	meta *handlers.MetricMeta,
) <-chan *response {
	ch := make(chan *response, len(meta.Commands))

	for _, cmd := range meta.Commands {
		go func() {
			res, err := p.nativeRequest(connection, cmd, meta.Arguments)
			if err != nil {
				ch <- &response{
					err: err,
				}

				return
			}

			ch <- &response{
				command: cmd,
				data:    res,
				err:     nil,
			}
		}()
	}

	return ch
}
