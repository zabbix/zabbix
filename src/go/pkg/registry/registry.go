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
	"errors"
	"fmt"
	"strings"
	"regexp"
	"encoding/json"
	"encoding/base64"
	"golang.org/x/sys/windows/registry"
)

type RegistryKey struct {
	Fullkey    string      `json:"fullkey"`
	Lastsubkey string `json:"lastsubkey"`
}

type RegistryValue struct {
	Fullkey    string      `json:"fullkey"`
	Lastsubkey string `json:"lastsubkey"`
	Name       string      `json:"name"`
	Data       string      `json:"data"`
	Type       interface{}      `json:"type"`
}

const RegistryDiscoveryModeKeys = 0
const RegistryDiscoveryModeValues = 1

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

func discoverValues(hive registry.Key, fullkey string, values []RegistryValue, current_key string, re string) (result []RegistryValue, e error) {
	k, err := registry.OpenKey(hive, fullkey, registry.QUERY_VALUE|registry.ENUMERATE_SUB_KEYS)
	if err != nil {
		return nil, err
	}
	defer k.Close()

	s, err := k.ReadSubKeyNames(0)

	if err != nil {
		return nil, err
	}

	v, err := k.ReadValueNames(0)
	if err != nil {
		return nil, err
	}

	for _, v := range v {
		data, valtype, err := convertValue(k, v)
		sdata := fmt.Sprintf("%v", data)

		if err != nil {
			continue
		}

		if re != "" {
			match, _ := regexp.MatchString(re, v)

			if match {
				values = append(values, RegistryValue{fullkey, current_key, v, sdata, valtype})
			}
		} else {
			values = append(values, RegistryValue{fullkey, current_key, v, sdata, valtype})
		}
	}

	for _, i := range s {
		new_fullkey := fullkey + "\\" + i
		values, _ = discoverValues(hive, new_fullkey, values, i, re)
	}

	return values, nil
}

func discoverKeys(hive registry.Key, fullkey string, subkeys []RegistryKey) (result []RegistryKey, e error) {
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
		subkeys = append(subkeys, RegistryKey{current_key, i})
		discoverKeys(hive, current_key, subkeys)
	}

	return subkeys, nil
}

func convertValue(k registry.Key, value string) (result interface{}, stype string, err error) {
	_, valueType, _ := k.GetValue(value, nil)
	defer k.Close()

	switch valueType {
		case registry.NONE:
			return "", "REG_NONE", nil
		case registry.EXPAND_SZ:
			stype = "REG_EXPAND_SZ"
			fallthrough
		case registry.SZ:
			if stype == "" {
				stype = "REG_SZ"
			}
			val, _, err := k.GetStringValue(value)
			if err != nil {
				return nil, "", errors.New("Failed to retrieve value.")
			}
			return val, stype, nil
		case registry.BINARY:
			stype = "REG_BINARY"
			val, _, err := k.GetBinaryValue(value)
			if err != nil {
				return nil, "", errors.New("Failed to retrieve value.")
			}
			return base64.StdEncoding.EncodeToString(val), "REG_BINARY", nil
		case registry.DWORD:
			stype = "REG_QWORD"
			fallthrough
		case registry.QWORD:
			if stype == "" {
				stype = "REG_DWORD"
			}
			val, _, err := k.GetIntegerValue(value)
			if err != nil {
				return nil, "", errors.New("Failed to retrieve value.")
			}
			return val, stype, nil
		case registry.MULTI_SZ:
			val, _, err := k.GetStringsValue(value)
			if err != nil {
				return nil, "", errors.New("Failed to retrieve value.")
			}
			return val, "REG_MULTI_SZ", nil
	}

	return "", "", errors.New("Value not found.")
}

func splitFullkey(fullkey string) (hive registry.Key, key string, e error) {
	idx := strings.Index(fullkey, "\\")

	if idx == -1 {
		return 0, "", errors.New("Failed to parse key.")
	}

	hive, e = getHive(fullkey[:idx])
	key = fullkey[idx+1:]
	return
}

func GetValue(params []string) (result interface{}, err error) {
	if len(params) < 1 || len(params) > 2 {
		return nil, errors.New("Invalid number of parameters.")
	}

	fullkey := params[0]

	hive, key, e := splitFullkey(fullkey)
	if e != nil {
		return err, nil
	}

	var value string

	if len(params) == 2 {
		value = params[1]
	}

	handle, err := registry.OpenKey(hive, key, registry.QUERY_VALUE)
	if err != nil {
		return err, nil
	}

	result, _, err = convertValue(handle, value)
	return 
}

func Discover(params []string) (result string, err error) {
	var j []byte
	var re string

	if len(params) == 0 || len(params) > 3 {
		return "", errors.New("Invalid number of parameters.")
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
		results := make([]RegistryKey, 0)
		results, _ = discoverKeys(hive, key, results)
		j, _ = json.Marshal(results)
	} else if mode == RegistryDiscoveryModeValues {
		results := make([]RegistryValue, 0)
		results, _ = discoverValues(hive, key, results, "", re)
		j, _ = json.Marshal(results)
	} else {
		return "", errors.New("Invalid mode parameter.")
	}

	return string(j), nil
}
