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

/*
#cgo LDFLAGS: -Wl,--start-group
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxcomms/libzbxcomms.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxcommon/libzbxcommon.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxlog/libzbxlog.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxcrypto/libzbxcrypto.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxsys/libzbxsys.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxnix/libzbxnix.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxconf/libzbxconf.a
#cgo LDFLAGS: ${SRCDIR}/../../../../../src/libs/zbxcompress/libzbxcompress.a
#cgo LDFLAGS: -Wl,--end-group
#cgo LDFLAGS: -lz

#include "../../../../../include/common.h"

const char	*progname = NULL;
const char	title_message[] = "agent";
const char	syslog_app_name[] = "agent";
const char	*usage_message[] = {};
unsigned char	program_type	= 0x80;
const char	*help_message[] = {};

int	zbx_get_agent_item_nextcheck(zbx_uint64_t itemid, const char *delay, unsigned char state, int now,
		int refresh_unsupported, int *nextcheck, char **error);

*/
import "C"

import (
	"errors"
	"time"
	"unsafe"
)

const (
	ITEM_STATE_NORMAL       = 0
	ITEM_STATE_NOTSUPPORTED = 1
)

func GetNextcheck(itemid uint64, delay string, from time.Time, unsupported bool, refresh_unsupported int) (nextcheck time.Time, err error) {

	var cnextcheck C.int
	var cerr *C.char
	var state int
	cdelay := C.CString(delay)

	if unsupported {
		state = ITEM_STATE_NOTSUPPORTED
	} else {
		state = ITEM_STATE_NORMAL
	}
	now := from.Unix()
	ret := C.zbx_get_agent_item_nextcheck(C.ulong(itemid), cdelay, C.uchar(state), C.int(now),
		C.int(refresh_unsupported), &cnextcheck, &cerr)

	if ret != 0 {
		err = errors.New(C.GoString(cerr))
		C.free(unsafe.Pointer(cerr))
	} else {
		nextcheck = time.Unix(int64(cnextcheck), 0)
	}
	C.free(unsafe.Pointer(cdelay))

	return
}
