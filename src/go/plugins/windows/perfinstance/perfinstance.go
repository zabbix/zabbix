// +build windows

package perfinstance

import (
	"encoding/json"
	"errors"
	"fmt"
	"time"

	"zabbix.com/pkg/pdh"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/win32"
)

//Plugin -
type Plugin struct {
	plugin.Base
	nextObjectRefresh  time.Time
	nextEngNameRefresh time.Time
}

var impl Plugin

//Export -
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

func init() {
	plugin.RegisterMetrics(&impl, "WindowsPerfInstance",
		"perf_instance.discovery", "Get Windows performance instance object list.",
		"perf_instance_en.discovery", "Get Windows performance instance object English list.",
	)
	impl.SetCapacity(1)
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
