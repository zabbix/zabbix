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

package zbxlib

import (
	"C"
	"unsafe"
)
import (
	"errors"
	"time"
	"zabbix/internal/plugin"
)

//export processValue
func processValue(citem unsafe.Pointer, cvalue *C.char, cstate C.int, clastLogsize C.ulong, cmtime C.int) {
	var value string
	var err error
	if cstate == ItemStateNormal {
		value = C.GoString(cvalue)
	} else {
		err = errors.New(C.GoString(cvalue))
	}

	item := (*LogItem)(citem)

	lastLogsize := uint64(clastLogsize)
	mtime := int(cmtime)
	result := &plugin.Result{
		Itemid:      item.Itemid,
		Value:       &value,
		Ts:          time.Now(),
		Error:       err,
		LastLogsize: &lastLogsize,
		Mtime:       &mtime,
	}
	item.Output.Write(result)
}
