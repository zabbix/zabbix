/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

func ValueToResult(itemid uint64, ts time.Time, v interface{}) (result *plugin.Result) {
	var value string
	switch v.(type) {
	case *plugin.Result:
		return v.(*plugin.Result)
	case plugin.Result:
		r := v.(plugin.Result)
		return &r
	case string:
		value = v.(string)
	case *string:
		value = *v.(*string)
	case int:
		value = strconv.FormatInt(int64(v.(int)), 10)
	case int64:
		value = strconv.FormatInt(v.(int64), 10)
	case uint:
		value = strconv.FormatUint(uint64(v.(uint)), 10)
	case uint64:
		value = strconv.FormatUint(v.(uint64), 10)
	case float32:
		value = strconv.FormatFloat(float64(v.(float32)), 'f', 6, 64)
	case float64:
		value = strconv.FormatFloat(v.(float64), 'f', 6, 64)
	default:
		// note that this conversion is slow and it's better to return known value type
		value = fmt.Sprintf("%v", v)
	}
	return &plugin.Result{Itemid: itemid, Value: &value, Ts: ts}
}
