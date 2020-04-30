package perfinstance

import (
	"encoding/json"
	"errors"
	"fmt"
	"sync"
	"time"

	"zabbix.com/pkg/pdh"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/win32"
)

//Plugin -
type Plugin struct {
	plugin.Base
	refreshMux         sync.Mutex
	reloadMux          sync.RWMutex
	instances          []string
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

	p.refreshObjects()
	var name string
	switch key {
	case "perf_instance.get":
		name = params[0]
	case "perf_instance_en.get":
		if name = p.getLocalName(params[0]); name == "" {
			var reloaded bool
			if reloaded, err = p.reloadEngObjectNames(); err != nil {
				return nil, fmt.Errorf("Failed to get instance object English names: %s.", err.Error())
			}
			if reloaded {
				if name = p.getLocalName(params[0]); name == "" {
					return nil, errors.New("Unsupported metric.")
				}
			} else {
				return nil, errors.New("Unsupported metric.")
			}
		}
	default:
		return nil, plugin.UnsupportedMetricError
	}

	var instances []win32.Instance
	if instances, err = win32.PdhEnumObjectItems(name); err != nil {
		return nil, fmt.Errorf("Failed to get performance instance objects: %s.", err.Error())
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
		"perf_instance.get", "Get Windows performance instance object list.",
		"perf_instance_en.get", "Get Windows performance instance object English list.",
	)
}

func (p *Plugin) refreshObjects() error {
	p.refreshMux.Lock()
	defer p.refreshMux.Unlock()
	if time.Now().After(p.nextObjectRefresh) {
		p.Debugf("refreshing local Windows object cache")
		if _, err := win32.PdhEnumObject(); err != nil {
			return fmt.Errorf("Failed to refresh object cache: %s.", err.Error())
		}
		p.nextObjectRefresh = time.Now().Add(time.Minute)
	}

	return nil
}

func (p *Plugin) reloadEngObjectNames() (bool, error) {
	p.reloadMux.Lock()
	defer p.reloadMux.Unlock()
	if time.Now().After(p.nextEngNameRefresh) {
		p.Debugf("loading English Windows objects")
		if err := pdh.LocateObjectsAndDefaultCounters(false); err != nil {
			return true, fmt.Errorf("Failed to load English Windows objects: %s.", err.Error())
		}
		p.nextEngNameRefresh = time.Now().Add(time.Minute)
	}

	return false, nil
}

func (p *Plugin) getLocalName(engName string) string {
	p.reloadMux.RLock()
	defer p.reloadMux.RUnlock()
	for _, obj := range pdh.Objects {
		if obj.EngName == engName {
			return obj.Name
		}
	}

	return ""
}
