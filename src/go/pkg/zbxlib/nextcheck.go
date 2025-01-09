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

	"golang.zabbix.com/sdk/log"
)

func GetNextcheckSeconds(itemid uint64, delay string, from time.Time) int {
	nextcheck, _, nextcheck_err := GetNextcheck(itemid, delay, from)

	if nextcheck_err != nil {
		return 0
	}

	return int(nextcheck.Unix())
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
