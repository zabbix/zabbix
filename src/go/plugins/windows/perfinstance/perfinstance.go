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
	mutex       sync.Mutex
	instances   []string
	nextRefresh time.Time
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
		name, err = getLocalName(params[0])
		if err != nil {
			return
		}
	default:
		return nil, plugin.UnsupportedMetricError
	}

	var instances []string
	if instances, err = win32.PdhEnumObjectItems(name); err != nil {
		return nil, fmt.Errorf("Failed to get performance instance object: %s.", err.Error())
	}

	resp := []interface{}{}
	for _, inst := range instances {
		instance := struct {
			Instance string `json:"{#INSTANCE}"`
		}{
			inst,
		}
		resp = append(resp, instance)
	}

	var respJSON []byte
	if respJSON, err = json.Marshal(resp); err != nil {
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
	p.mutex.Lock()
	defer p.mutex.Unlock()
	if time.Now().After(p.nextRefresh) {
		p.Debugf("refreshing local windows object cache")
		if _, err := win32.PdhEnumObject(); err != nil {
			return fmt.Errorf("Failed to refresh object cache: %s.", err.Error())
		}
		p.nextRefresh = time.Now().Add(time.Minute)
	}

	return nil
}

func getLocalName(engName string) (string, error) {
	for _, obj := range pdh.Objects {
		if obj.EngName == engName {
			return obj.Name, nil
		}
	}

	return "", errors.New("Unsupported English object name.")
}
