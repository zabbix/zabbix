//go:build windows
// +build windows

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

package registry

import (
	"encoding/json"
	"errors"
	"fmt"
	"regexp"
	"strings"

	"golang.org/x/sys/windows/registry"
	"zabbix.com/pkg/plugin"
	zbxregistry "zabbix.com/pkg/registry"
)

// Plugin -
type Plugin struct {
	plugin.Base
}

var impl Plugin

type registryKey struct {
	Fullkey    string `json:"fullkey"`
	Lastsubkey string `json:"lastsubkey"`
}

type registryValue struct {
	Fullkey    string      `json:"fullkey"`
	Lastsubkey string      `json:"lastsubkey"`
	Name       string      `json:"name"`
	Data       interface{} `json:"data"`
	Type       string      `json:"type"`
}

const (
	RegistryDiscoveryModeKeys   = 0
	RegistryDiscoveryModeValues = 1
)

func getHive(key string) (hive registry.Key, e error) {
	switch {
	case key == "HKLM":
		fallthrough
	case key == "HKEY_LOCAL_MACHINE":
		return registry.LOCAL_MACHINE, nil
	case key == "HKCU":
		fallthrough
	case key == "HKEY_CURRENT_USER":
		return registry.CURRENT_USER, nil
	case key == "HKCR":
		fallthrough
	case key == "HKEY_CLASSES_ROOT":
		return registry.CLASSES_ROOT, nil
	case key == "HKU":
		fallthrough
	case key == "HKEY_USERS":
		return registry.USERS, nil
	case key == "HKPD":
		fallthrough
	case key == "HKEY_PERFORMANCE_DATA":
		return registry.PERFORMANCE_DATA, nil
	}
	return 0, errors.New("Failed to parse key.")
}

func discoverValues(hive registry.Key, fullkey string, discovered_values []registryValue, current_key string, re string) (result []registryValue, e error) {
	k, err := registry.OpenKey(hive, fullkey, registry.READ)
	if err != nil {
		return nil, err
	}
	defer k.Close()

	subkeys, err := k.ReadSubKeyNames(0)
	if err != nil {
		return []registryValue{}, err
	}

	values, err := k.ReadValueNames(0)
	if err != nil {
		return []registryValue{}, err
	}

	for _, v := range values {
		data, valtype, err := zbxregistry.ConvertValue(k, v)

		if err != nil {
			continue
		}

		if re != "" {
			match, _ := regexp.MatchString(re, v)

			if match {
				discovered_values = append(discovered_values,
					registryValue{fullkey, current_key, v, data, valtype})
			}
		} else {
			discovered_values = append(discovered_values,
				registryValue{fullkey, current_key, v, data, valtype})
		}
	}

	for _, subkey := range subkeys {
		new_fullkey := fullkey + "\\" + subkey
		discovered_values, _ = discoverValues(hive, new_fullkey, discovered_values, subkey, re)
	}

	return discovered_values, nil
}

func discoverKeys(hive registry.Key, fullkey string, subkeys []registryKey) (result []registryKey, e error) {
	k, err := registry.OpenKey(hive, fullkey, registry.ENUMERATE_SUB_KEYS)
	if err != nil {
		return nil, err
	}

	s, err := k.ReadSubKeyNames(0)
	defer k.Close()

	if err != nil {
		fmt.Print(2)
		return nil, err
	}

	subkeys = append(subkeys, registryKey{fullkey, ""})

	for _, i := range s {
		current_key := fullkey + "\\" + i
		subkeys = append(subkeys, registryKey{current_key, i})
		discoverKeys(hive, current_key, subkeys)
	}

	return subkeys, nil
}

func splitFullkey(fullkey string) (hive registry.Key, key string, e error) {
	idx := strings.Index(fullkey, "\\")

	if idx == -1 {
		return 0, "", errors.New("Failed to parse registry key.")
	}

	hive, e = getHive(fullkey[:idx])
	key = fullkey[idx+1:]

	return
}

func getValue(params []string) (result interface{}, err error) {
	if len(params) > 2 {
		return nil, errors.New("Too many parameters.")
	}

	if len(params) < 1 {
		return nil, errors.New("Registry key is not supplied.")
	}

	fullkey := params[0]

	hive, key, e := splitFullkey(fullkey)
	if e != nil {
		return nil, e
	}

	var value string

	if len(params) == 2 {
		value = params[1]
	}

	handle, err := registry.OpenKey(hive, key, registry.QUERY_VALUE)
	defer handle.Close()
	if err != nil {
		return nil, err
	}

	result, _, err = zbxregistry.ConvertValue(handle, value)
	if err != nil {
		return nil, err
	}

	if x, ok := result.([]string); ok {
		var j []byte
		j, err = json.Marshal(x)
		result = string(j)
	}

	return
}

func discover(params []string) (result string, err error) {
	var j []byte
	var re string

	if len(params) > 3 {
		return "", errors.New("Too many parameters.")
	}

	if len(params) < 1 {
		return "", errors.New("Registry key is not supplied.")
	}

	fullkey := params[0]

	hive, key, e := splitFullkey(fullkey)
	if e != nil {
		return "", e
	}

	mode := -1

	if len(params) > 1 {
		if params[1] == "keys" || params[1] == "" {
			mode = RegistryDiscoveryModeKeys
		} else if params[1] == "values" {
			mode = RegistryDiscoveryModeValues
			if len(params) == 3 {
				re = params[2]
			}
		}
	} else {
		mode = RegistryDiscoveryModeValues
	}

	if mode == RegistryDiscoveryModeKeys {
		results := make([]registryKey, 0)
		results, err = discoverKeys(hive, key, results)
		j, _ = json.Marshal(results)
	} else if mode == RegistryDiscoveryModeValues {
		results := make([]registryValue, 0)
		results, err = discoverValues(hive, key, results, "", re)
		j, _ = json.Marshal(results)
	} else {
		return "", errors.New("Invalid 'mode' parameter.")
	}

	return string(j), err
}

// Export -
func (p *Plugin) Export(key string, params []string, ctx plugin.ContextProvider) (result interface{}, err error) {
	switch key {
	case "registry.data":
		return getValue(params)
	case "registry.get":
		return discover(params)
	default:
		return nil, plugin.UnsupportedMetricError
	}
}

func init() {
	plugin.RegisterMetrics(&impl, "Registry",
		"registry.data", "Return value of the registry key.",
		"registry.get", "Discover registry key and its subkeys.",
	)
}
