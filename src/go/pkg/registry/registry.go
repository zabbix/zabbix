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
	"encoding/json"
	"encoding/base64"
	"golang.org/x/sys/windows/registry"
)

type RegistryKey struct {
	Fullkey    string      `json:"fullkey"`
	Lastsubkey interface{} `json:"lastsubkey"`
}

type RegistryValue struct {
	Fullkey    string      `json:"fullkey"`
	Lastsubkey interface{} `json:"lastsubkey"`
	Name       string      `json:"name"`
	Data       string      `json:"data"`
}

const RegistryDiscoveryModeKeys = 0
const RegistryDiscoveryModeValues = 1

func discoverValues(fullkey string, values []RegistryValue, current_key string) (result []RegistryValue, e error) {
	k, err := registry.OpenKey(registry.LOCAL_MACHINE, fullkey, registry.QUERY_VALUE|registry.ENUMERATE_SUB_KEYS)
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
		fmt.Print()
		data, err := convertValue(k, v)
		sdata := fmt.Sprintf("%v", data)
		if err != nil {
			continue
		}
		values = append(values, RegistryValue{fullkey, current_key, v, sdata})
	}

	for _, i := range s {
		new_fullkey := fullkey + "\\" + i
		values, _ = discoverValues(new_fullkey, values, i)
	}

	return values, nil
}

func discoverKeys(fullkey string, subkeys []RegistryKey) (result []RegistryKey, e error) {
	k, err := registry.OpenKey(registry.LOCAL_MACHINE, fullkey, registry.ENUMERATE_SUB_KEYS)
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
		discoverKeys(current_key, subkeys)
	}

	return subkeys, nil
}

func convertValue(k registry.Key, value string) (result interface{}, err error) {
	_, valueType, _ := k.GetValue(value, nil)
	defer k.Close()

	switch valueType {
		case registry.NONE:
			return "", nil
		case registry.EXPAND_SZ:
		case registry.SZ:
			val, _, err := k.GetStringValue(value)
			if err != nil {
				return nil, errors.New("Failed to retrieve value.")
			}
			return val, nil
		case registry.BINARY:
			val, _, err := k.GetBinaryValue(value)
			if err != nil {
				return nil, errors.New("Failed to retrieve value.")
			}
			return base64.StdEncoding.EncodeToString(val), nil
		case registry.DWORD, registry.QWORD:
			val, _, err := k.GetIntegerValue(value)
			if err != nil {
				return nil, errors.New("Failed to retrieve value.")
			}
			return val, nil
		case registry.MULTI_SZ:
			val, _, err := k.GetStringsValue(value)
			if err != nil {
				return nil, errors.New("Failed to retrieve value.")
			}
			return val, nil
	}
	return "", errors.New("Not found.")
}

func GetValue(params []string) (result interface{}, err error) {
	if len(params) < 1 || len(params) > 2 {
		return nil, errors.New("Invalid number of parameters.")
	}

	fullkey := params[0]

	var value string

	if len(params) == 2 {
		value = params[1]
	}

	handle, err := registry.OpenKey(registry.LOCAL_MACHINE, fullkey, registry.QUERY_VALUE)
	if err != nil {
		return err, nil
	}

	return convertValue(handle, value)
}

func Discover(params []string) (result string, err error) {
	var j []byte

	if len(params) == 0 || len(params) > 3 {
		return "", errors.New("Invalid number of parameters.")
	}

	fullkey := params[0]
	mode := -1

	if len(params) > 1 {
		if params[1] == "keys" || params[1] == "" {
			mode = RegistryDiscoveryModeKeys
		} else if params[1] == "values" {
			mode = RegistryDiscoveryModeValues
		}
	} else {
		mode = RegistryDiscoveryModeValues
	}

	if mode == RegistryDiscoveryModeKeys {
		results := make([]RegistryKey, 0)
		results, _ = discoverKeys(fullkey, results)
		j, _ = json.Marshal(results)
	} else if mode == RegistryDiscoveryModeValues {
		results := make([]RegistryValue, 0)
		results, _ = discoverValues(fullkey, results, "")
		j, _ = json.Marshal(results)
	} else {
		return "", errors.New("Invalid mode parameter.")
	}

	return string(j), nil
}