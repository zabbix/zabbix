package perfinstance

import (
	"encoding/json"
	"errors"
	"fmt"
	"strconv"
	"sync"
	"time"

	"zabbix.com/pkg/pdh"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/win32"
)

const hkeyPerformanceText = 0x80000050

//Plugin -
type Plugin struct {
	plugin.Base
	mutex       sync.Mutex
	instances   []string
	nextRefresh time.Time
}

type object struct {
	idx     int
	engName string
	name    string
}

var impl Plugin
var objects []object

//Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (response interface{}, err error) {
	if len(params) > 2 {
		return nil, errors.New("Too many parameters")
	}

	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter")
	}

	var name string
	switch key {
	case "perf_instance.get":
		name = params[0]
	case "perf_instance_en.get":
		name, err = getLocalName(params[0])
		if err != nil {
			return nil, err
		}
	default:
		return nil, errors.New("Unsupported metric.")
	}

	if err = p.setInstances(name); err != nil {
		return nil, err
	}

	resp := []interface{}{}
	for _, inst := range p.instances {
		instance := struct {
			Instance string `json:"{#INSTANCE}"`
		}{
			inst,
		}
		resp = append(resp, instance)
	}

	respJSON, err := json.Marshal(resp)
	if err != nil {
		return nil, fmt.Errorf("Failed to parse discovery object response: %s", err.Error())
	}

	return string(respJSON), nil
}

func init() {
	plugin.RegisterMetrics(&impl, "WindowsPerfDisc",
		"perf_instance.get", "Get Windows performance instance object list.",
		"perf_instance_en.get", "Get Windows performance instance object English list.",
	)

	if err := refreshObjects(); err != nil {
		panic(err)
	}
}

func refreshObjects() error {
	objects = []object{}
	locNames, err := win32.PdhEnumObject()
	if err != nil {
		return err
	}

	for _, name := range locNames {
		idx, err := win32.PdhLookupPerfIndexByName(name)
		if err != nil {
			continue
		}
		objects = append(objects, object{idx, "", name})
	}

	for i := range objects {
		objects[i].engName = pdh.Counters[strconv.Itoa(objects[i].idx)]
	}

	return nil
}

func (p *Plugin) setInstances(name string) (err error) {
	p.mutex.Lock()
	defer p.mutex.Unlock()
	if time.Now().After(p.nextRefresh) {
		p.Debugf("refreshing local windows object list")
		if err = refreshObjects(); err != nil {
			return fmt.Errorf("Failed to update object list %s", err.Error())
		}
		p.nextRefresh = time.Now().Add(time.Minute)
	}

	p.instances, err = win32.PdhEnumObjectItems(name)
	p.instances = filterInstances(p.instances)
	if err != nil {
		p.mutex.Unlock()
		return fmt.Errorf("Failed to get performance instance object: %s", err.Error())
	}
	return nil
}

func getLocalName(engName string) (string, error) {
	for _, obj := range objects {
		if obj.engName == engName {
			return obj.name, nil
		}
	}

	return "", errors.New("Unsupported English object name.")
}

func filterInstances(inst []string) []string {
	u := make([]string, 0, len(inst))
	m := make(map[string]bool)

	for _, val := range inst {
		if _, ok := m[val]; !ok {
			m[val] = true
			u = append(u, val)
		}
	}

	return u
}
