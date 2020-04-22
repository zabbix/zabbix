package prefdisc

import (
	"encoding/json"
	"errors"
	"fmt"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/win32"
)

//Plugin -
type Plugin struct {
	plugin.Base
}

type object struct {
	idx     int
	engName string
	name    string
}

var impl Plugin
var objects []object

//Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	if len(params) > 2 {
		return nil, errors.New("too many parameters")
	}

	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("invalid first parameter")
	}

	var name string
	for _, obj := range objects {
		fmt.Printf("name: %s\n", obj.name)
		fmt.Printf("engName: %s\n", obj.engName)
		if obj.engName == params[0] {
			name = obj.name
			break
		}
	}

	_, instances, err := win32.PdhEnumObjectItems(name)
	if err != nil {
		return nil, fmt.Errorf("failed to get performance discovery object: %s", err.Error())
	}

	var resp []interface{}
	for _, inst := range instances {
		instance := struct {
			Instance string `json:"{#INSTANCE}"`
		}{
			inst,
		}
		resp = append(resp, instance)
	}

	respJSON, err := json.Marshal(resp)
	if err != nil {
		return nil, fmt.Errorf("failed to parse discovery object response: %s", err.Error())
	}

	return string(respJSON), nil
}

func init() {
	plugin.RegisterMetrics(&impl, "WindowsPerfDisc",
		"perf_counter.discovery", "List of any Windows performance discovery objects.",
		"perf_counter_en.discovery", "List of any Windows performance discovery objects in English.",
	)

	objectNames, err := win32.PdhEnumObject()
	if err != nil {
		panic(err)
	}

	for _, engName := range objectNames {
		idx, _ := win32.PdhLookupPerfIndexByName(engName)
		name, _ := win32.PdhLookupPerfNameByIndex(idx)
		objects = append(objects, object{idx, engName, name})
	}
}
