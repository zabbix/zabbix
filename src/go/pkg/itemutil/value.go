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

package itemutil

import (
	"fmt"
	"strconv"
	"time"

	"golang.zabbix.com/sdk/plugin"
)

const StateNotSupported = 1

func ValueToString(u interface{}) *string {
	var s string

	switch v := u.(type) {
	case string:
		s = v
	case *string:
		return v
	case int:
		s = strconv.FormatInt(int64(v), 10)
	case int8:
		s = strconv.FormatInt(int64(v), 10)
	case int16:
		s = strconv.FormatInt(int64(v), 10)
	case int32:
		s = strconv.FormatInt(int64(v), 10)
	case int64:
		s = strconv.FormatInt(v, 10)
	case uint:
		s = strconv.FormatUint(uint64(v), 10)
	case uint8:
		s = strconv.FormatUint(uint64(v), 10)
	case uint16:
		s = strconv.FormatUint(uint64(v), 10)
	case uint32:
		s = strconv.FormatUint(uint64(v), 10)
	case uint64:
		s = strconv.FormatUint(v, 10)
	case float32:
		s = strconv.FormatFloat(float64(v), 'f', 6, 64)
	case float64:
		s = strconv.FormatFloat(v, 'f', 6, 64)
	default:
		// note that this conversion is slow and it's better to return known value type
		s = fmt.Sprintf("%v", u)
	}

	return &s
}

func ValueToResult(itemid uint64, ts time.Time, u interface{}) (result *plugin.Result) {
	var value *string
	switch v := u.(type) {
	case *plugin.Result:
		return v
	case plugin.Result:
		return &v
	case nil:
		return &plugin.Result{Itemid: itemid, Value: nil, Ts: ts}
	default:
		value = ValueToString(u)
	}

	return &plugin.Result{Itemid: itemid, Value: value, Ts: ts}
}
