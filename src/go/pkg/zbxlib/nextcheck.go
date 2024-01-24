/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../include

#include "zbxexpr.h"

int	zbx_get_agent_item_nextcheck(zbx_uint64_t itemid, const char *delay, int now,
		int *nextcheck, int *scheduling, char **error);
*/
import "C"

import (
	"errors"
	"time"
	"unsafe"

	"git.zabbix.com/ap/plugin-support/log"
)

func GetCheckIntervalSeconds(itemid uint64, delay string, from time.Time, prev time.Time) int {
	nextcheck, _, nextcheck_err := GetNextcheck(itemid, delay, from)

	if nextcheck_err == nil {
		return int((nextcheck.Sub(from) + time.Second/2) / time.Second)
	}

	if prev.IsZero() {
		return 1
	}

	return int((from.Sub(prev) + time.Second/2) / time.Second)
}

func GetNextcheck(itemid uint64, delay string, from time.Time) (nextcheck time.Time, scheduling bool, err error) {
	var cnextcheck, cscheduling C.int
	var cerr *C.char

	cdelay := C.CString(delay)
	defer func() {
		log.Tracef("Calling C function \"free(cdelay)\"")
		C.free(unsafe.Pointer(cdelay))
	}()

	now := from.Unix()
	log.Tracef("Calling C function \"zbx_get_agent_item_nextcheck()\"")
	ret := C.zbx_get_agent_item_nextcheck(C.zbx_uint64_t(itemid), cdelay, C.int(now),
		&cnextcheck, &cscheduling, &cerr)

	if ret != Succeed {
		err = errors.New(C.GoString(cerr))
		log.Tracef("Calling C function \"free()\"")
		C.free(unsafe.Pointer(cerr))
	} else {
		nextcheck = time.Unix(int64(cnextcheck), 0)
		if Succeed == cscheduling {
			scheduling = true
		}
	}

	return
}
