/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
	"zabbix/internal/plugin"
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
		value = strconv.Itoa(v.(int))
	case int64:
		value = strconv.Itoa(v.(int))
	case uint:
		value = strconv.Itoa(v.(int))
	case uint64:
		value = strconv.Itoa(v.(int))
	case float32:
		value = strconv.FormatFloat(float64(v.(float32)), 'g', -1, 64)
	case float64:
		value = strconv.FormatFloat(v.(float64), 'g', -1, 64)
	default:
		// note that this conversion is slow and
		value = fmt.Sprintf("%v", v)
	}
	return &plugin.Result{Itemid: itemid, Value: &value, Ts: ts}
}
