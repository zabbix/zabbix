package prefdisc

import (
	"encoding/json"
	"errors"
	"fmt"
	"strconv"
	"unsafe"

	"golang.org/x/sys/windows"
	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/win32"
)

const hkeyPerformanceText = 0x80000050

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
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (interface{}, error) {
	if len(params) > 2 {
		return nil, errors.New("Too many parameters")
	}

	if len(params) == 0 || params[0] == "" {
		return nil, errors.New("Invalid first parameter")
	}

	var name string
	switch key {
	case "perf_counter.discovery":
		name = params[0]
	case "perf_counter_en.discovery":
		var err error
		name, err = getLocalName(params[0])
		if err != nil {
			return nil, err
		}
	default:
		return nil, errors.New("Unsupported metric.")
	}

	_, instances, err := win32.PdhEnumObjectItems(name)
	if err != nil {
		return nil, fmt.Errorf("Failed to get performance discovery object: %s", err.Error())
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

	respJSON, err := json.Marshal(resp)
	if err != nil {
		return nil, fmt.Errorf("Failed to parse discovery object response: %s", err.Error())
	}

	return string(respJSON), nil
}

func init() {
	plugin.RegisterMetrics(&impl, "WindowsPerfDisc",
		"perf_counter.discovery", "List of any Windows performance discovery objects.",
		"perf_counter_en.discovery", "List of any Windows performance discovery objects in English.",
	)

	locNames, err := win32.PdhEnumObject()
	if err != nil {
		panic(err)
	}

	for _, name := range locNames {
		idx, err := win32.PdhLookupPerfIndexByName(name)
		if err != nil {
			continue
		}
		objects = append(objects, object{idx, "", name})
	}

	locateEnglishObj()
}

func locateEnglishObj() error {
	var size uint32
	counter := windows.StringToUTF16Ptr("Counter")
	if err := windows.RegQueryValueEx(hkeyPerformanceText, counter, nil, nil, nil, &size); err != nil {
		return err
	}
	buf := make([]uint16, size/2)

	if err := windows.RegQueryValueEx(hkeyPerformanceText, counter, nil, nil, (*byte)(unsafe.Pointer(&buf[0])), &size); err != nil {
		return err
	}

	var wcharIndex, wcharName []uint16
	for len(buf) != 0 {
		wcharIndex, buf = nextField(buf)
		if len(wcharIndex) == 0 {
			break
		}
		wcharName, buf = nextField(buf)
		if len(wcharName) == 0 {
			break
		}

		for i := range objects {
			idx, err := strconv.Atoi(windows.UTF16ToString(wcharIndex))
			if err != nil {
				return err
			}
			if objects[i].idx == idx {
				objects[i].engName = windows.UTF16ToString(wcharName)
			}
		}
	}

	return nil
}

func nextField(buf []uint16) ([]uint16, []uint16) {
	start := -1
	for i, c := range buf {
		if c != 0 {
			start = i
			break
		}
	}
	if start == -1 {
		return []uint16{}, []uint16{}
	}
	for i, c := range buf[start:] {
		if c == 0 {
			return buf[start : start+i], buf[start+i+1:]
		}
	}
	return buf[start:], []uint16{}
}

func getLocalName(engName string) (string, error) {
	for _, obj := range objects {
		if obj.engName == engName {
			return obj.name, nil
		}
	}

	return "", errors.New("Unsupported English object name.")
}
