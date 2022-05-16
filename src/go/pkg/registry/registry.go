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
	"encoding/base64"
	"errors"

	"golang.org/x/sys/windows/registry"
)

func ConvertValue(k registry.Key, value string) (result interface{}, stype string, err error) {
	_, valueType, err := k.GetValue(value, nil)
	if err != nil {
		return nil, "", err
	}

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
			return nil, "", err
		}
		return val, stype, nil
	case registry.BINARY:
		stype = "REG_BINARY"
		val, _, err := k.GetBinaryValue(value)
		if err != nil {
			return nil, "", err
		}
		return base64.StdEncoding.EncodeToString(val), "REG_BINARY", nil
	case registry.QWORD:
		stype = "REG_QWORD"
		fallthrough
	case registry.DWORD:
		if stype == "" {
			stype = "REG_DWORD"
		}
		val, _, err := k.GetIntegerValue(value)
		if err != nil {
			return nil, "", err
		}
		return val, stype, nil
	case registry.MULTI_SZ:
		val, _, err := k.GetStringsValue(value)
		if err != nil {
			return nil, "", err
		}
		return val, "REG_MULTI_SZ", nil
	}

	return "", "", errors.New("Unsupported registry data type.")
}
