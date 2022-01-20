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

// Package conf provides .conf file loading and unmarshalling
package conf

import (
	"bytes"
	"fmt"
)

// Node structure is used to store parsed conf file parameters or parameter components.
type Node struct {
	Name  string
	Nodes []interface{}
	Line  int

	used        bool
	parent      *Node
	level       int
	includeFail bool
}

type Value struct {
	Value []byte
	Line  int
}

// get returns child node by name
func (n *Node) get(name string) (node *Node) {
	for _, v := range n.Nodes {
		if child, ok := v.(*Node); ok && child.Name == name {
			return child
		}
	}
	return nil
}

// add appends new child node
func (n *Node) add(name []byte, value []byte, lineNum int) {
	var node *Node
	var key string

	split := bytes.IndexByte(name, '.')
	if split == -1 {
		key = string(name)
	} else {
		key = string(name[:split])
	}

	if node = n.get(key); node == nil {
		node = &Node{
			Name:   string(key),
			used:   false,
			Nodes:  make([]interface{}, 0),
			parent: n,
			Line:   lineNum}
		n.Nodes = append(n.Nodes, node)
	}

	if split != -1 {
		node.add(name[split+1:], value, lineNum)
	} else {
		node.Nodes = append(node.Nodes, &Value{Value: value, Line: lineNum})
	}
}

// checkUsage checks if all conf nodes were recognized.
// This is done by recursively checking 'used' flag for all nodes.
func (n *Node) checkUsage() (err error) {
	for _, v := range n.Nodes {
		if child, ok := v.(*Node); ok {
			if !child.used {
				return child.newError("unknown parameter")
			}
			if err = child.checkUsage(); err != nil {
				return
			}
		}
	}
	return
}

// markUsed marks node and its children as used
func (n *Node) markUsed(used bool) {
	n.used = used
	for _, v := range n.Nodes {
		if child, ok := v.(*Node); ok {
			child.markUsed(used)
		}
	}
}

// getValue returns node value or meta data default value or nil if
// metadata 'optional' tag is set. Otherwise error is returned.
func (n *Node) getValue(meta *Meta) (value *string, err error) {
	if n != nil {
		var tmp string
		for _, v := range n.Nodes {
			if val, ok := v.(*Value); ok {
				tmp = string(val.Value)
			}
			value = &tmp
		}
	}

	if value == nil && meta != nil {
		if meta.defaultValue != nil {
			value = meta.defaultValue
		} else if meta.optional {
			return
		} else {
			return nil, fmt.Errorf("cannot find mandatory parameter %s", meta.name)
		}
	}
	return
}

// newNodeError creates error based on the specified node. The error message will
// have full node name (parameter name up to the node, including it) and the line
// number where parameter was defined.
func (n *Node) newError(format string, a ...interface{}) (err error) {
	if n == nil {
		return fmt.Errorf(format, a...)
	}
	var name string
	for parent := n; parent.parent != nil; parent = parent.parent {
		if name == "" {
			name = parent.Name
		} else {
			name = parent.Name + "." + name
		}
	}
	desc := fmt.Sprintf(format, a...)
	return fmt.Errorf("invalid parameter %s at line %d: %s", name, n.Line, desc)
}
