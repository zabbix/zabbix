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

package handlers

import (
	"encoding/json"
	"strconv"

	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/zbxerr"
)

type node struct {
	ID       int64   `json:"id"`
	Class    string  `json:"device_class"`
	Name     string  `json:"name"`
	Type     string  `json:"type"`
	Children []int64 `json:"children"`
}

type crushTree struct {
	Nodes []node `json:"nodes"`
}

type osdEntity struct {
	OSDName string `json:"{#osdname}"`
	Class   string `json:"{#class}"`
	Host    string `json:"{#host}"`
}

type osdDumpPool struct {
	Pools []struct { //nolint:revive //part of ceph response.
		Name      string `json:"pool_name"`
		CrushRule int64  `json:"crush_rule"`
	} `json:"pools"`
}

type poolEntity struct {
	PoolName  string `json:"{#poolname}"`
	CrushRule string `json:"{#crushrule}"`
}

type step struct {
	Op       string `json:"op"`
	Item     int64  `json:"item"`
	ItemName string `json:"item_name"`
}

type crushRule struct {
	ID    int64  `json:"rule_id"`
	Name  string `json:"rule_name"`
	Steps []step `json:"steps"`
}

// osdDiscoveryHandler returns list of OSDs in LLD format.
func osdDiscoveryHandler(data map[Command][]byte) (any, error) {
	var (
		tree crushTree
		host *node
	)

	lld := make([]osdEntity, 0)

	err := json.Unmarshal(data[cmdOSDCrushTree], &tree)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	for i, n := range tree.Nodes {
		switch n.Type {
		case "host":
			host = &tree.Nodes[i]

			continue
		case "osd":
			if host == nil {
				continue
			}

			for _, cid := range host.Children {
				if n.ID == cid {
					lld = append(lld, osdEntity{
						OSDName: strconv.FormatInt(n.ID, 10),
						Class:   n.Class,
						Host:    host.Name,
					})
				}
			}

		default:
			continue
		}
	}

	jsonLLD, err := json.Marshal(lld)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(jsonLLD), nil
}

// getStepOpTake finds a step with a "take" op for a given rule.
func getStepOpTake(rule crushRule) (*step, error) {
	for _, s := range rule.Steps {
		if s.Op == "take" {
			return &s, nil
		}
	}

	return nil, errs.New(`cannot find step with "take" op for rule ` + rule.Name)
}

// osdDiscoveryHandler returns list of pools in LLD format.
func poolDiscoveryHandler(data map[Command][]byte) (any, error) {
	var (
		poolsDump  osdDumpPool
		crushRules []crushRule
	)

	lld := make([]poolEntity, 0)

	err := json.Unmarshal(data[cmdOSDDump], &poolsDump)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	err = json.Unmarshal(data[cmdOSDCrushRuleDump], &crushRules)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotUnmarshalJSON)
	}

	for _, pool := range poolsDump.Pools {
		for _, rule := range crushRules {
			if pool.CrushRule == rule.ID {
				s, err := getStepOpTake(rule) //nolint:govet //shadowed error here does not cause confusion
				if err != nil {
					return nil, errs.WrapConst(err, zbxerr.ErrorCannotParseResult)
				}

				lld = append(lld, poolEntity{
					PoolName:  pool.Name,
					CrushRule: s.ItemName,
				})

				break
			}
		}
	}

	jsonLLD, err := json.Marshal(lld)
	if err != nil {
		return nil, errs.WrapConst(err, zbxerr.ErrorCannotMarshalJSON)
	}

	return string(jsonLLD), nil
}
