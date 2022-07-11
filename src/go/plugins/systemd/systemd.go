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

package systemd

import (
	"encoding/json"
	"fmt"
	"path/filepath"
	"reflect"
	"strings"
	"sync"

	"git.zabbix.com/ap/plugin-support/plugin"

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
	Name        string
	Description string
	LoadState   string
	ActiveState string
	SubState    string
	Followed    string
	Path        string
	JobID       uint32
	JobType     string
	JobPath     string
}

type unitFile struct {
	Name            string
	EnablementState string
}

type unitJson struct {
	Name          string `json:"{#UNIT.NAME}"`
	Description   string `json:"{#UNIT.DESCRIPTION}"`
	LoadState     string `json:"{#UNIT.LOADSTATE}"`
	ActiveState   string `json:"{#UNIT.ACTIVESTATE}"`
	SubState      string `json:"{#UNIT.SUBSTATE}"`
	Followed      string `json:"{#UNIT.FOLLOWED}"`
	Path          string `json:"{#UNIT.PATH}"`
	JobID         uint32 `json:"{#UNIT.JOBID}"`
	JobType       string `json:"{#UNIT.JOBTYPE}"`
	JobPath       string `json:"{#UNIT.JOBPATH}"`
	UnitFileState string `json:"{#UNIT.UNITFILESTATE}"`
}

type state struct {
	State int    `json:"state"`
	Text  string `json:"text"`
}

type stateMapping struct {
	unitName   string
	stateNames []string
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
	case "systemd.unit.get":
		return p.get(params, conn)
	case "systemd.unit.discovery":
		return p.discovery(params, conn)
	case "systemd.unit.info":
		return p.info(params, conn)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func (p *Plugin) get(params []string, conn *dbus.Conn) (interface{}, error) {
	var unitType string
	var values map[string]interface{}

	if len(params) > 2 {
		return nil, fmt.Errorf("Too many parameters.")
	}

	if len(params) == 0 || len(params[0]) == 0 {
		return nil, fmt.Errorf("Invalid first parameter.")
	}

	if len(params) < 2 || len(params[1]) == 0 {
		unitType = "Unit"
	} else {
		unitType = params[1]
	}

	obj := conn.Object("org.freedesktop.systemd1", dbus.ObjectPath("/org/freedesktop/systemd1/unit/"+getName(params[0])))
	err := obj.Call("org.freedesktop.DBus.Properties.GetAll", 0, "org.freedesktop.systemd1."+unitType).Store(&values)
	if err != nil {
		return nil, fmt.Errorf("Cannot get unit property: %s", err)
	}

	if unitType == "Unit" {
		p.setUnitStates(values)
	}

	val, err := json.Marshal(values)
	if err != nil {
		return nil, fmt.Errorf("Cannot create JSON array: %s", err)
	}

	return string(val), nil
}

func (p *Plugin) discovery(params []string, conn *dbus.Conn) (interface{}, error) {
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

	var unitFiles []unitFile
	if err = obj.Call("org.freedesktop.systemd1.Manager.ListUnitFiles", 0).Store(&unitFiles); err != nil {
		return nil, fmt.Errorf("Cannot retrieve list of unit files: %s", err)
	}

	var array []unitJson
	for _, u := range units {
		if len(ext) != 0 && ext != filepath.Ext(u.Name) {
			continue
		}

		UnitFileState, err := p.info([]string{u.Name, "UnitFileState"}, conn)
		if err != nil {
			p.Debugf("Failed to retrieve unit file state for %s, err:", u.Name, err.Error())
			continue
		}

		var state string
		switch reflect.TypeOf(UnitFileState).Kind() {
		case reflect.String:
			state = UnitFileState.(string)
		default:
			p.Debugf("Unit file state is not string for %s", u.Name)
			continue
		}

		array = append(array, unitJson{u.Name, u.Description, u.LoadState, u.ActiveState,
			u.SubState, u.Followed, u.Path, u.JobID, u.JobType, u.JobPath, state,
		})
	}

	for _, f := range unitFiles {
		unitFileExt := filepath.Ext(f.Name)
		basePath := filepath.Base(f.Name)
		if f.EnablementState != "disabled" || (len(ext) != 0 && ext != unitFileExt) ||
			strings.HasSuffix(strings.TrimSuffix(f.Name, unitFileExt), "@") || /* skip unit templates */
			isEnabledUnit(array, basePath) {
			continue
		}

		unitPath := "/org/freedesktop/systemd1/unit/" + getName(basePath)

		var details map[string]interface{}
		obj = conn.Object("org.freedesktop.systemd1", dbus.ObjectPath(unitPath))
		err = obj.Call("org.freedesktop.DBus.Properties.GetAll", 0, "org.freedesktop.systemd1.Unit").Store(&details)
		if err != nil {
			p.Debugf("Cannot get unit properties for disabled unit %s, err:", basePath, err.Error())
			continue
		}

		array = append(array, unitJson{basePath, "", "", "inactive", "", "", unitPath, 0, "", "", f.EnablementState})
	}

	jsonArray, err := json.Marshal(array)
	if nil != err {
		return nil, fmt.Errorf("Cannot create JSON array: %s", err)
	}

	return string(jsonArray), nil
}

func (p *Plugin) info(params []string, conn *dbus.Conn) (interface{}, error) {
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

	obj := conn.Object("org.freedesktop.systemd1", dbus.ObjectPath("/org/freedesktop/systemd1/unit/"+getName(params[0])))
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
}

func getName(name string) string {
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
	return string(nameEsc[:j])
}

func (p *Plugin) setUnitStates(v map[string]interface{}) {
	mappings := []stateMapping{
		{"LoadState", []string{"loaded", "error", "masked"}},
		{"ActiveState", []string{"active", "reloading", "inactive", "failed", "activating", "deactivating"}},
		{"UnitFileState", []string{"enabled", "enabled-runtime", "linked", "linked-runtime", "masked", "masked-runtime", "static", "disabled", "invalid"}},
	}

	for _, mapping := range mappings {
		p.createStateMapping(v, mapping.unitName, mapping.stateNames)
	}
}

func (p *Plugin) createStateMapping(v map[string]interface{}, key string, names []string) {
	if value, ok := v[key].(string); ok {
		for i, name := range names {
			if value == name {
				v[key] = &state{i + 1, value}
				return
			}
		}
		v[key] = &state{0, value}
		p.Debugf("cannot create mapping for '%s' unit state: unknown state '%s'", key, value)
	} else {
		p.Debugf("cannot create mapping for '%s' unit state: unit state with information type string not found", key)
	}

}

func isEnabledUnit(units []unitJson, p string) bool {
	for _, u := range units {
		if u.Name == p {
			return true
		}
	}
	return false
}

func init() {
	plugin.RegisterMetrics(&impl, "Systemd",
		"systemd.unit.get", "Returns the bulked info, usage: systemd.unit.get[unit,<interface>].",
		"systemd.unit.discovery", "Returns JSON array of discovered units, usage: systemd.unit.discovery[<type>].",
		"systemd.unit.info", "Returns the unit info, usage: systemd.unit.info[unit,<parameter>,<interface>].",
	)
}
