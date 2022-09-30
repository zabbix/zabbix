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

	"gopkg.in/mgo.v2/bson"
	"zabbix.com/pkg/zbxerr"
)

type lldCfgEntity struct {
	ReplicaSet string `json:"{#REPLICASET}"`
	Hostname   string `json:"{#HOSTNAME}"`
	MongodURI  string `json:"{#MONGOD_URI}"`
}

type shardMap struct {
	Map map[string]string
}

// configDiscoveryHandler
// https://docs.mongodb.com/manual/reference/command/getShardMap/#dbcmd.getShardMap
func configDiscoveryHandler(s Session, params map[string]string) (interface{}, error) {
	var cfgServers shardMap
	err := s.DB("admin").Run(&bson.D{
		bson.DocElem{
			Name:  "getShardMap",
			Value: 1,
		},
		bson.DocElem{
			Name:  "maxTimeMS",
			Value: s.GetMaxTimeMS(),
		},
	}, &cfgServers)

	if err != nil {
		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	lld := make([]lldCfgEntity, 0)

	if servers, ok := cfgServers.Map["config"]; ok {
		var rs string

		hosts := servers

		h := strings.SplitN(hosts, "/", 2)
		if len(h) > 1 {
			rs = h[0]
			hosts = h[1]
		}

		for _, hostport := range strings.Split(hosts, ",") {
			host, _, err := net.SplitHostPort(hostport)
			if err != nil {
				return nil, zbxerr.ErrorCannotParseResult.Wrap(err)
			}

			lld = append(lld, lldCfgEntity{
				Hostname:   host,
				MongodURI:  fmt.Sprintf("%s://%s", uriDefaults.Scheme, hostport),
				ReplicaSet: rs,
			})
		}
	} else {
		return nil, zbxerr.ErrorCannotParseResult
	}

	jsonRes, err := json.Marshal(lld)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
