//go:build windows
// +build windows

/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

package registry

import (
	"encoding/base64"
	"encoding/json"
	"errors"
	"regexp"
	"strings"

	"golang.org/x/sys/windows/registry"
	"golang.zabbix.com/sdk/plugin"
	"golang.zabbix.com/sdk/zbxerr"
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
	RegistryDiscoveryModeValues = iota
	RegistryDiscoveryModeKeys
)

func getHive(key string) (hive registry.Key, e error) {
	switch key {
	case "HKLM", "HKEY_LOCAL_MACHINE":
		return registry.LOCAL_MACHINE, nil
	case "HKCU", "HKEY_CURRENT_USER":
		return registry.CURRENT_USER, nil
	case "HKCR", "HKEY_CLASSES_ROOT":
		return registry.CLASSES_ROOT, nil
	case "HKU", "HKEY_USERS":
		return registry.USERS, nil
	case "HKPD", "HKEY_PERFORMANCE_DATA":
		return registry.PERFORMANCE_DATA, nil
	}

	return 0, errors.New("Failed to parse registry key.")
}

func convertValue(k registry.Key, value string) (result interface{}, stype string, err error) {
	_, valueType, err := k.GetValue(value, nil)
	if err != nil {
		return nil, "", err
	}

	switch valueType {
	case registry.NONE:
		return "", "REG_NONE", nil
	case registry.EXPAND_SZ:
		stype = "REG_EXPAND_SZ"
		result, _, err = k.GetStringValue(value)
	case registry.SZ:
		stype = "REG_SZ"
		result, _, err = k.GetStringValue(value)
	case registry.BINARY:
		stype = "REG_BINARY"
		if val, _, err := k.GetBinaryValue(value); err == nil {
			result = base64.StdEncoding.EncodeToString(val)
		} else {
			return nil, "", err
		}
	case registry.QWORD:
		stype = "REG_QWORD"
		result, _, err = k.GetIntegerValue(value)
	case registry.DWORD:
		stype = "REG_DWORD"
		result, _, err = k.GetIntegerValue(value)
	case registry.MULTI_SZ:
		stype = "REG_MULTI_SZ"
		result, _, err = k.GetStringsValue(value)
	default:
		return nil, "", errors.New("Unsupported registry data type.")
	}

	return result, stype, err
}

func discoverValues(hive registry.Key, fullkey string, discovered_values []registryValue, current_key string,
	re *regexp.Regexp, shive string) (result []registryValue, e error) {

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
		data, valtype, err := convertValue(k, v)

		if err != nil {
			continue
		}

		if re != nil {
			if re.MatchString(v) {
				discovered_values = append(discovered_values,
					registryValue{shive + "\\" + fullkey, current_key, v, data, valtype})
			}
		} else {
			discovered_values = append(discovered_values,
				registryValue{shive + "\\" + fullkey, current_key, v, data, valtype})
		}
	}

	for _, subkey := range subkeys {
		new_fullkey := fullkey + "\\" + subkey
		discovered_values, _ = discoverValues(hive, new_fullkey, discovered_values, subkey, re, shive)
	}

	return discovered_values, nil
}

func discoverKeys(hive registry.Key, fullkey string, subkeys []registryKey, shive string) (result []registryKey, e error) {
	k, err := registry.OpenKey(hive, fullkey, registry.ENUMERATE_SUB_KEYS)
	if err != nil {
		return nil, err
	}

	s, err := k.ReadSubKeyNames(0)
	defer k.Close()

	if err != nil {
		return nil, err
	}

	for _, i := range s {
		current_key := fullkey + "\\" + i
		subkeys = append(subkeys, registryKey{shive + "\\" + current_key, i})
		subkeys, _ = discoverKeys(hive, current_key, subkeys, shive)
	}

	return subkeys, nil
}

func splitFullkey(fullkey string) (hive registry.Key, key string, shive string, e error) {
	idx := strings.Index(fullkey, "\\")

	if idx == -1 {
		return 0, "", "", errors.New("Failed to parse registry key.")
	}

	shive = fullkey[:idx]
	hive, e = getHive(shive)
	key = fullkey[idx+1:]

	return
}

func getValue(params []string) (result interface{}, err error) {
	if len(params) > 2 {
		return nil, zbxerr.ErrorTooManyParameters
	}

	if len(params) < 1 || params[0] == "" {
		return nil, errors.New("Registry key is not supplied.")
	}

	fullkey := params[0]

	hive, key, _, e := splitFullkey(fullkey)
	if e != nil {
		return nil, e
	}

	var value string

	if len(params) == 2 {
		value = params[1]
	}

	handle, err := registry.OpenKey(hive, key, registry.QUERY_VALUE)
	if err != nil {
		return nil, err
	}
	defer handle.Close()

	result, _, err = convertValue(handle, value)
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
	var re *regexp.Regexp

	if len(params) > 3 {
		return "", zbxerr.ErrorTooManyParameters
	}

	if len(params) < 1 || params[0] == "" {
		return "", errors.New("Registry key is not supplied.")
	}

	fullkey := params[0]

	hive, key, shive, e := splitFullkey(fullkey)
	if e != nil {
		return "", e
	}

	mode := RegistryDiscoveryModeValues

	if len(params) > 1 {
		switch params[1] {
		case "values", "":
			// default mode - RegistryDiscoveryModeValues
		case "keys":
			mode = RegistryDiscoveryModeKeys
		default:
			return "", errors.New("Invalid 'mode' parameter.")
		}

		if len(params) == 3 {
			if mode != RegistryDiscoveryModeValues {
				return "", zbxerr.ErrorTooManyParameters
			}
			if re, err = regexp.Compile(params[2]); err != nil {
				return "", err
			}
		}
	}
	switch mode {
	case RegistryDiscoveryModeKeys:
		results := make([]registryKey, 0)
		results, err = discoverKeys(hive, key, results, shive)
		j, _ = json.Marshal(results)
	case RegistryDiscoveryModeValues:
		results := make([]registryValue, 0)
		results, err = discoverValues(hive, key, results, "", re, shive)
		j, _ = json.Marshal(results)
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
