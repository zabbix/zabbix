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

package mongodb

import (
	"encoding/json"
	"fmt"
	"net"
	"strings"
	"time"

	"zabbix.com/pkg/zbxerr"
)

type lldShEntity struct {
	ID        string `json:"{#ID}"`
	Hostname  string `json:"{#HOSTNAME}"`
	MongodURI string `json:"{#MONGOD_URI}"`
	State     string `json:"{#STATE}"`
}

type shEntry struct {
	ID    string      `bson:"_id"`
	Host  string      `bson:"host"`
	State json.Number `bson:"state"`
}

// shardsDiscoveryHandler
// https://docs.mongodb.com/manual/reference/method/sh.status/#sh.status
func shardsDiscoveryHandler(s Session, _ map[string]string) (interface{}, error) {
	var shards []shEntry

	if err := s.DB("config").C("shards").Find(nil).Sort(sortAsc).
		SetMaxTime(time.Duration(s.GetMaxTimeMS()) * time.Millisecond).
		All(&shards); err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	lld := make([]lldShEntity, 0)

	for _, sh := range shards {
		hosts := sh.Host

		h := strings.SplitN(sh.Host, "/", 2)
		if len(h) > 1 {
			hosts = h[1]
		}

		for _, hostport := range strings.Split(hosts, ",") {
			host, _, err := net.SplitHostPort(hostport)
			if err != nil {
				return nil, zbxerr.ErrorCannotParseResult.Wrap(err)
			}

			lld = append(lld, lldShEntity{
				ID:        sh.ID,
				Hostname:  host,
				MongodURI: fmt.Sprintf("%s://%s", uriDefaults.Scheme, hostport),
				State:     sh.State.String(),
			})
		}
	}

	jsonLLD, err := json.Marshal(lld)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonLLD), nil
}
