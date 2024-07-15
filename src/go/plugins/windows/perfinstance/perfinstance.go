//go:build windows
// +build windows

/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

package perfinstance

import (
	"encoding/json"
	"errors"
	"fmt"
	"time"

	"golang.zabbix.com/agent2/pkg/pdh"
	"golang.zabbix.com/agent2/pkg/win32"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
)

var impl Plugin

// Plugin -
type Plugin struct {
	plugin.Base
	nextObjectRefresh  time.Time
	nextEngNameRefresh time.Time
}

func init() {
	err := plugin.RegisterMetrics(&impl, "WindowsPerfInstance",
		"perf_instance.discovery", "Get Windows performance instance object list.",
		"perf_instance_en.discovery", "Get Windows performance instance object English list.",
	)
	if err != nil {
		panic(zbxerr.New("failed to register metrics").Wrap(err))
	}

	impl.SetCapacity(1)
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (response interface{}, err error) {
	if len(params) > 1 {
		return nil, errors.New("Too many parameters.")
	}

	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter.")
	}

	if err = p.refreshObjects(); err != nil {
		p.Warningf("Cannot refresh object cache: %s", err.Error())
	}

	var name string
	switch key {
	case "perf_instance.discovery":
		name = params[0]
	case "perf_instance_en.discovery":
		if name = p.getLocalName(params[0]); name == "" {
			if err = p.reloadEngObjectNames(); err != nil {
				return nil, fmt.Errorf("Cannot obtain object's English names: %s", err.Error())
			}
			if name = p.getLocalName(params[0]); name == "" {
				return nil, errors.New("Cannot obtain object's localized name.")
			}
		}
	default:
		return nil, plugin.UnsupportedMetricError
	}

	var instances []win32.Instance
	if instances, err = win32.PdhEnumObjectItems(name); err != nil {
		return nil, fmt.Errorf("Cannot find object: %s", err.Error())
	}

	if len(instances) < 1 {
		return "[]", nil
	}

	var respJSON []byte
	if respJSON, err = json.Marshal(instances); err != nil {
		return
	}

	return string(respJSON), nil
}

func (p *Plugin) refreshObjects() (err error) {
	if time.Now().After(p.nextObjectRefresh) {
		_, err = win32.PdhEnumObject()
		p.nextObjectRefresh = time.Now().Add(time.Minute)
	}

	return
}

func (p *Plugin) reloadEngObjectNames() (err error) {
	if time.Now().After(p.nextEngNameRefresh) {
		err = pdh.LocateObjectsAndDefaultCounters(false)
		p.nextEngNameRefresh = time.Now().Add(time.Minute)
	}

	return
}

func (p *Plugin) getLocalName(engName string) (name string) {
	name, _ = pdh.ObjectsNames[engName]
	return
}
