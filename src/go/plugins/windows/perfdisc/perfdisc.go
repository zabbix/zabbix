package prefdisc

import (
	"encoding/json"
	"errors"
	"fmt"
	"zabbix/src/go/pkg/win32"

	"golang.org/x/sys/windows"
	"zabbix.com/pkg/plugin"
)

const (
	langDefault = 0
	langEnglish = 1
)

//Plugin -
type Plugin struct {
	plugin.Base
}

type processName struct {
	idx     int
	engName string
}

var impl Plugin
var (
	modPdhDll = windows.NewLazyDLL("pdh.dll")

	procPdhEnumObjectItems = modPdhDll.NewProc("PdhEnumObjectItemsW")
)

//Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	// var lang int
	// switch key {
	// case "perf_counter":
	// 	lang = langDefault
	// case "perf_counter_en":
	// 	lang = langEnglish
	// default:
	// 	return nil, fmt.Errorf("unsupported metric: '%s'", key)
	// }

	if len(params) > 2 {
		return nil, errors.New("too many parameters")
	}
	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("invalid first parameter")
	}

	counters, instances, err := win32.PdhEnumObjectItems(params[0])
	if err != nil {
		return nil, fmt.Errorf("failed to get performance discovery object: %s", err.Error())
	}

	resp := make(map[string]string)
	for i, inst := range instances {
		if len(counters) < i {
			break
		}
		resp[inst] = counters[i]
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
}
