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

package ceph

import (
	"encoding/json"
	"fmt"
	"zabbix.com/pkg/log"

	"zabbix.com/pkg/zbxerr"
)

type Step struct {
	Op       string `json:"op"`
	Item     int64  `json:"item"`
	ItemName string `json:"item_name"`
}

type OSDCrushRule struct {
	Id    int64  `json:"rule_id"`
	Name  string `json:"rule_name"`
	Steps []Step `json:"steps"`
}

type Node struct {
	Id       int64   `json:"id"`
	Name     string  `json:"name"`
	Type     string  `json:"type"`
	Children []int64 `json:"children"`
}

type OSDCrushTree struct {
	Nodes []Node `json:"nodes"`
}

type OSDEntity struct {
	OSDName   string `json:"{#OSDNAME}"`
	CrushRule string `json:"{#CRUSHRULE}"`
}

// getNode TODO.
func getNode(tree []Node, nodeId int64) (*Node, error) {
	for _, node := range tree {
		if node.Id == nodeId {
			return &node, nil
		}
	}

	return nil, fmt.Errorf(`cannot find node "%d"`, nodeId)
}

// getStepOpTake TODO.
func getStepOpTake(rule OSDCrushRule) (*Step, error) {
	for _, step := range rule.Steps {
		if step.Op == "take" {
			return &step, nil
		}
	}

	return nil, fmt.Errorf(`cannot find step with "take" op for rule %q`, rule.Name)
}

// walkCrushTree TODO.
func walkCrushTree(tree []Node, rootNodeId int64) (res []*Node, err error) {
	rootNode, err := getNode(tree, rootNodeId)
	if err != nil {
		return nil, err
	}

	if rootNode.Type == "osd" {
		return []*Node{rootNode}, nil
	}

	for _, childId := range rootNode.Children {
		childNodes, err := walkCrushTree(tree, childId)
		if err != nil {
			return nil, err
		}

		if len(childNodes) > 0 {
			res = append(res, childNodes...)
		}
	}

	return
}

// OSDDiscoveryHandler TODO.
func OSDDiscoveryHandler(data ...[]byte) (interface{}, error) {
	var (
		crushRules []OSDCrushRule
		crushTree  OSDCrushTree
		lld        []OSDEntity
	)

	roundCache := make(map[int64][]*Node)

	err := json.Unmarshal(data[0], &crushRules)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	err = json.Unmarshal(data[1], &crushTree)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	for _, rule := range crushRules {
		step, err := getStepOpTake(rule)
		if err != nil {
			return nil, zbxerr.ErrorCannotParseResult.Wrap(err)
		}

		if _, ok := roundCache[step.Item]; !ok {
			rootNode, err := getNode(crushTree.Nodes, step.Item)
			if err != nil {
				return nil, zbxerr.ErrorCannotParseResult.Wrap(err)
			}

			if rootNode.Type != "root" {
				continue
			}

			roundCache[step.Item], err = walkCrushTree(crushTree.Nodes, step.Item)
			if err != nil {
				log.Errf(err.Error())
			}

			for _, node := range roundCache[step.Item] {
				lld = append(lld, OSDEntity{
					OSDName:   node.Name,
					CrushRule: step.ItemName,
				})
			}
		}
	}

	jsonLLD, err := json.Marshal(lld)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonLLD), nil
}

type cephOsdDumpPool struct {
	Pools []struct {
		Name      string `json:"pool_name"`
		CrushRule int64  `json:"crush_rule"`
	} `json:"pools"`
}

type poolEntity struct {
	PoolName  string `json:"{#POOLNAME}"`
	CrushRule string `json:"{#CRUSHRULE}"`
}

// OSDDiscoveryHandler TODO.
func poolDiscoveryHandler(data ...[]byte) (interface{}, error) {
	var (
		poolsDump  cephOsdDumpPool
		crushRules []OSDCrushRule
		lld        []poolEntity
	)

	err := json.Unmarshal(data[0], &poolsDump)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	err = json.Unmarshal(data[1], &crushRules)
	if err != nil {
		return nil, zbxerr.ErrorCannotUnmarshalJSON.Wrap(err)
	}

	for _, pool := range poolsDump.Pools {
		for _, rule := range crushRules {
			if pool.CrushRule == rule.Id {
				step, err := getStepOpTake(rule)
				if err != nil {
					return nil, zbxerr.ErrorCannotParseResult.Wrap(err)
				}

				lld = append(lld, poolEntity{
					PoolName:  pool.Name,
					CrushRule: step.ItemName,
				})

				break
			}
		}
	}

	jsonLLD, err := json.Marshal(lld)
	if err != nil {
		return nil, zbxerr.ErrorCannotMarshalJSON.Wrap(err)
	}

	return string(jsonLLD), nil
}
