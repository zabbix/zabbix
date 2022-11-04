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

/* cspell:disable */

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../include

#include "common.h"
#include "sysinfo.h"
#include "module.h"
typedef int (*zbx_agent_check_t)(AGENT_REQUEST *request, AGENT_RESULT *result);

static int execute_check(const char *key, zbx_agent_check_t check_func, char **value, char **error)
{
	int ret = FAIL;
	char **pvalue;
	AGENT_RESULT result;
	AGENT_REQUEST request;

	init_request(&request);
	init_result(&result);
	if (SUCCEED != parse_item_key(key, &request))
	{
		*value = zbx_strdup(NULL, "Invalid item key format.");
		goto out;
	}
	if (SYSINFO_RET_OK != check_func(&request, &result))
	{
		if (0 != ISSET_MSG(&result))
		{
			*error = zbx_strdup(NULL, result.msg);
		}
		else
			*error = zbx_strdup(NULL, "Unknown error.");
		goto out;
	}

	if (NULL != (pvalue = GET_TEXT_RESULT(&result)))
		*value = zbx_strdup(NULL, *pvalue);

	ret = SUCCEED;
out:
	free_result(&result);
	free_request(&request);
	return ret;
}

*/
import "C"

import (
	"errors"
	"unsafe"

	"zabbix.com/pkg/itemutil"
	"zabbix.com/pkg/log"
)

func ExecuteCheck(key string, params []string) (result *string, err error) {
	cfunc := resolveMetric(key)

	if cfunc == nil {
		return nil, errors.New("Unsupported item key.")
	}

	var cvalue, cerrmsg *C.char
	ckey := C.CString(itemutil.MakeKey(key, params))
	log.Tracef("Calling C function \"execute_check()\"")
	if C.execute_check(ckey, C.zbx_agent_check_t(cfunc), &cvalue, &cerrmsg) == Succeed {
		if cvalue != nil {
			value := C.GoString(cvalue)
			result = &value
		}
		log.Tracef("Calling C function \"free()\"")
		C.free(unsafe.Pointer(cvalue))

	} else {
		err = errors.New(C.GoString(cerrmsg))
		log.Tracef("Calling C function \"free()\"")
		C.free(unsafe.Pointer(cerrmsg))
	}
	log.Tracef("Calling C function \"free()\"")
	C.free(unsafe.Pointer(ckey))

	return
}
