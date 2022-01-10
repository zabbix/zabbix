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

package itemutil

import (
	"fmt"
	"strconv"
	"time"

	"zabbix.com/pkg/plugin"
)

const StateNotSupported = 1

func ValueToResult(itemid uint64, ts time.Time, u interface{}) (result *plugin.Result) {
	var value string
	switch v := u.(type) {
	case *plugin.Result:
		return v
	case plugin.Result:
		return &v
	case string:
		value = u.(string)
	case *string:
		value = *u.(*string)
	case int:
		value = strconv.FormatInt(int64(v), 10)
	case int8:
		value = strconv.FormatInt(int64(v), 10)
	case int16:
		value = strconv.FormatInt(int64(v), 10)
	case int32:
		value = strconv.FormatInt(int64(v), 10)
	case int64:
		value = strconv.FormatInt(v, 10)
	case uint:
		value = strconv.FormatUint(uint64(v), 10)
	case uint8:
		value = strconv.FormatUint(uint64(v), 10)
	case uint16:
		value = strconv.FormatUint(uint64(v), 10)
	case uint32:
		value = strconv.FormatUint(uint64(v), 10)
	case uint64:
		value = strconv.FormatUint(v, 10)
	case float32:
		value = strconv.FormatFloat(float64(v), 'f', 6, 64)
	case float64:
		value = strconv.FormatFloat(v, 'f', 6, 64)
	default:
		// note that this conversion is slow and it's better to return known value type
		value = fmt.Sprintf("%v", u)
	}
	return &plugin.Result{Itemid: itemid, Value: &value, Ts: ts}
}
