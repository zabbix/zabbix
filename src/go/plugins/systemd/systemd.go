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

package systemd

import (
	"encoding/json"
	"fmt"
	"path/filepath"
	"reflect"
	"sync"

	"zabbix.com/pkg/plugin"

	"github.com/godbus/dbus"
)

// Plugin -
type Plugin struct {
	plugin.Base
	connections []*dbus.Conn
	mutex       sync.Mutex
}

var impl Plugin

type unit struct {
	Name        string `json:"{#UNIT.NAME}"`
	Description string `json:"{#UNIT.DESCRIPTION}"`
	LoadState   string `json:"{#UNIT.LOADSTATE}"`
	ActiveState string `json:"{#UNIT.ACTIVESTATE}"`
	SubState    string `json:"{#UNIT.SUBSTATE}"`
	Followed    string `json:"{#UNIT.FOLLOWED}"`
	Path        string `json:"{#UNIT.PATH}"`
	JobID       uint32 `json:"{#UNIT.JOBID}"`
	JobType     string `json:"{#UNIT.JOBTYPE}"`
	JobPath     string `json:"{#UNIT.JOBPATH}"`
}

func (p *Plugin) getConnection() (*dbus.Conn, error) {
	var err error
	var conn *dbus.Conn

	p.mutex.Lock()
	defer p.mutex.Unlock()

	if len(p.connections) == 0 {
		conn, err = dbus.SystemBusPrivate()
		if err != nil {
			return nil, err
		}
		err = conn.Auth(nil)
		if err != nil {
			conn.Close()
			return nil, err
		}

		err = conn.Hello()
		if err != nil {
			conn.Close()
			return nil, err
		}

	} else {
		conn = p.connections[len(p.connections)-1]
		p.connections = p.connections[:len(p.connections)-1]
	}

	return conn, nil
}

func (p *Plugin) releaseConnection(conn *dbus.Conn) {
	p.mutex.Lock()
	defer p.mutex.Unlock()
	p.connections = append(p.connections, conn)
}

func zbxNum2hex(c byte) byte {
	if c >= 10 {
		return c + 0x57 /* a-f */
	}
	return c + 0x30 /* 0-9 */
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (interface{}, error) {
	conn, err := p.getConnection()

	if nil != err {
		return nil, fmt.Errorf("Cannot establish connection to any available bus: %s", err)
	}

	defer p.releaseConnection(conn)

	switch key {
	case "systemd.unit.discovery":
		var ext string

		if len(params) > 1 {
			return nil, fmt.Errorf("Too many parameters.")
		}

		if len(params) == 0 || len(params[0]) == 0 {
			ext = ".service"
		} else {
			switch params[0] {
			case "service", "target", "automount", "device", "mount", "path", "scope", "slice", "snapshot", "socket", "swap", "timer":
				ext = "." + params[0]
			case "all":
				ext = ""
			default:
				return nil, fmt.Errorf("Invalid first parameter.")
			}
		}

		var units []unit
		obj := conn.Object("org.freedesktop.systemd1", dbus.ObjectPath("/org/freedesktop/systemd1"))
		err := obj.Call("org.freedesktop.systemd1.Manager.ListUnits", 0).Store(&units)

		if nil != err {
			return nil, fmt.Errorf("Cannot retrieve list of units: %s", err)
		}

		array := make([]*unit, len(units))
		j := 0
		for i := 0; i < len(units); i++ {
			if len(ext) != 0 && ext != filepath.Ext(units[i].Name) {
				continue
			}
			array[j] = &units[i]
			j++
		}

		jsonArray, err := json.Marshal(array[:j])

		if nil != err {
			return nil, fmt.Errorf("Cannot create JSON array: %s", err)
		}

		return string(jsonArray), nil
	case "systemd.unit.info":
		var property, unitType string
		var value interface{}

		if len(params) > 3 {
			return nil, fmt.Errorf("Too many parameters.")
		}

		if len(params) < 1 || len(params[0]) == 0 {
			return nil, fmt.Errorf("Invalid first parameter.")
		}

		if len(params) < 2 || len(params[1]) == 0 {
			property = "ActiveState"
		} else {
			property = params[1]
		}

		if len(params) < 3 || len(params[2]) == 0 || params[2] == "Unit" {
			unitType = "Unit"
		} else {
			unitType = params[2]
		}

		name := params[0]

		nameEsc := make([]byte, len(name)*3)
		j := 0
		for i := 0; i < len(name); i++ {
			if (name[i] >= 'A' && name[i] <= 'Z') ||
				(name[i] >= 'a' && name[i] <= 'z') ||
				((name[i] >= '0' && name[i] <= '9') && i != 0) {
				nameEsc[j] = name[i]
				j++
				continue
			}
			nameEsc[j] = '_'
			j++
			nameEsc[j] = zbxNum2hex((name[i] >> 4) & 0xf)
			j++
			nameEsc[j] = zbxNum2hex(name[i] & 0xf)
			j++
		}

		obj := conn.Object("org.freedesktop.systemd1", dbus.ObjectPath("/org/freedesktop/systemd1/unit/"+string(nameEsc[:j])))
		err := obj.Call("org.freedesktop.DBus.Properties.Get", 0, "org.freedesktop.systemd1."+unitType, property).Store(&value)

		if nil != err {
			return nil, fmt.Errorf("Cannot get unit property: %s", err)
		}

		switch reflect.TypeOf(value).Kind() {
		case reflect.Slice:
			fallthrough
		case reflect.Array:
			ret, err := json.Marshal(value)

			if nil != err {
				return nil, fmt.Errorf("Cannot create JSON array: %s", err)
			}

			return string(ret), nil
		}

		return value, nil
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func init() {
	plugin.RegisterMetrics(&impl, "Systemd",
		"systemd.unit.discovery", "Returns JSON array of discovered units, usage: systemd.unit.discovery[<type>].",
		"systemd.unit.info", "Returns the unit info, usage: systemd.unit.info[unit,<parameter>,<interface>].")
}
