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

package zbxlib

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../include

#include "common.h"

int	zbx_get_agent_item_nextcheck(zbx_uint64_t itemid, const char *delay, unsigned char state, int now,
		int refresh_unsupported, int *nextcheck, char **error);
*/
import "C"

import (
	"errors"
	"time"
	"unsafe"

	"zabbix.com/pkg/log"
)

func GetNextcheck(itemid uint64, delay string, from time.Time, unsupported bool, refresh_unsupported int) (nextcheck time.Time, err error) {
	var cnextcheck C.int
	var cerr *C.char
	var state int
	cdelay := C.CString(delay)

	if unsupported {
		state = ItemStateNotsupported
	} else {
		state = ItemStateNormal
	}
	now := from.Unix()
	log.Tracef("Calling C function \"zbx_get_agent_item_nextcheck()\"")
	ret := C.zbx_get_agent_item_nextcheck(C.zbx_uint64_t(itemid), cdelay, C.uchar(state), C.int(now),
		C.int(refresh_unsupported), &cnextcheck, &cerr)

	if ret != Succeed {
		err = errors.New(C.GoString(cerr))
		log.Tracef("Calling C function \"free()\"")
		C.free(unsafe.Pointer(cerr))
	} else {
		nextcheck = time.Unix(int64(cnextcheck), 0)
	}
	log.Tracef("Calling C function \"free()\"")
	C.free(unsafe.Pointer(cdelay))

	return
}
