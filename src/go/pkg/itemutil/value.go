/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

package itemutil

import (
	"fmt"
	"strconv"
	"time"

	"git.zabbix.com/ap/plugin-support/plugin"
)

const StateNotSupported = 1

func ValueToString(u interface{}) string {
	switch v := u.(type) {
	case string:
		return u.(string)
	case *string:
		return *u.(*string)
	case int:
		return strconv.FormatInt(int64(v), 10)
	case int8:
		return strconv.FormatInt(int64(v), 10)
	case int16:
		return strconv.FormatInt(int64(v), 10)
	case int32:
		return strconv.FormatInt(int64(v), 10)
	case int64:
		return strconv.FormatInt(v, 10)
	case uint:
		return strconv.FormatUint(uint64(v), 10)
	case uint8:
		return strconv.FormatUint(uint64(v), 10)
	case uint16:
		return strconv.FormatUint(uint64(v), 10)
	case uint32:
		return strconv.FormatUint(uint64(v), 10)
	case uint64:
		return strconv.FormatUint(v, 10)
	case float32:
		return strconv.FormatFloat(float64(v), 'f', 6, 64)
	case float64:
		return strconv.FormatFloat(v, 'f', 6, 64)
	default:
		// note that this conversion is slow and it's better to return known value type
		return fmt.Sprintf("%v", u)
	}
}

func ValueToResult(itemid uint64, ts time.Time, u interface{}) (result *plugin.Result) {
	var value string
	switch v := u.(type) {
	case *plugin.Result:
		return v
	case plugin.Result:
		return &v
	default:
		value = ValueToString(u)
	}

	return &plugin.Result{Itemid: itemid, Value: &value, Ts: ts}
}
