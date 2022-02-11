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
	"errors"
	"strings"

	"gopkg.in/mgo.v2/bson"
	"zabbix.com/pkg/zbxerr"
)

const (
	statePrimary   = 1
	stateSecondary = 2
)

const nodeHealthy = 1

type Member struct {
	health int
	name   string
	optime int
	ptr    interface{}
	state  int
}

type rawMember = map[string]interface{}

var errUnknownStructure = errors.New("failed to parse the members structure")

func parseMembers(raw []interface{}) (result []Member, err error) {
	var (
		members     []Member
		primaryNode Member
	)

	for _, m := range raw {
		member := Member{}
		ok := true

		if v, ok := m.(rawMember)["name"].(string); ok {
			member.name = v
		}

		if v, ok := m.(rawMember)["health"].(float64); ok {
			member.health = int(v)
		}

		if v, ok := m.(rawMember)["optime"].(map[string]interface{}); ok {
			if ts, ok := v["ts"].(bson.MongoTimestamp); ok {
				member.optime = int(ts >> 32)
			} else {
				member.optime = int(int64(v["ts"].(float64)) >> 32)
			}
		}

		if v, ok := m.(rawMember)["state"].(int); ok {
			member.state = v
		}

		if !ok {
			return nil, errUnknownStructure
		}

		member.ptr = m

		if member.state == statePrimary {
			primaryNode = member
		} else {
			members = append(members, member)
		}
	}

	result = append([]Member{primaryNode}, members...)
	if len(result) == 0 {
		return nil, errUnknownStructure
	}

	return result, nil
}

func injectExtendedMembersStats(raw []interface{}) error {
	members, err := parseMembers(raw)
	if err != nil {
		return err
	}

	unhealthyNodes := []string{}
	unhealthyCount := 0
	primary := members[0]

	for _, node := range members {
		node.ptr.(rawMember)["lag"] = primary.optime - node.optime

		if node.state == stateSecondary && node.health != nodeHealthy {
			unhealthyNodes = append(unhealthyNodes, node.name)
			unhealthyCount++
		}
	}

	primary.ptr.(rawMember)["unhealthyNodes"] = unhealthyNodes
	primary.ptr.(rawMember)["unhealthyCount"] = unhealthyCount
	primary.ptr.(rawMember)["totalNodes"] = len(members) - 1

	return nil
}

// replSetStatusHandler
// https://docs.mongodb.com/manual/reference/command/replSetGetStatus/index.html
func replSetStatusHandler(s Session, _ map[string]string) (interface{}, error) {
	var replSetGetStatus map[string]interface{}

	err := s.DB("admin").Run(&bson.D{
		bson.DocElem{
			Name:  "replSetGetStatus",
			Value: 1,
		},
		bson.DocElem{
			Name:  "maxTimeMS",
			Value: s.GetMaxTimeMS(),
		},
	}, &replSetGetStatus)

	if err != nil {
		if strings.Contains(err.Error(), "not running with --replSet") {
			return "{}", nil
		}

		return nil, zbxerr.ErrorCannotFetchData.Wrap(err)
	}

	err = injectExtendedMembersStats(replSetGetStatus["members"].([]interface{}))
	if err != nil {
		return nil, zbxerr.ErrorCannotParseResult.Wrap(err)
	}

	jsonRes, err := json.Marshal(replSetGetStatus)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonRes), nil
}
