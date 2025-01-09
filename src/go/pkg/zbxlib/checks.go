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

/* cspell:disable */

/*
#cgo CFLAGS: -I${SRCDIR}/../../../../include
#cgo CFLAGS: -I${SRCDIR}/../../../../include/common

#include "zbxsysinfo.h"
#include "module.h"
typedef int (*zbx_agent_check_t)(AGENT_REQUEST *request, AGENT_RESULT *result);

static int execute_check(const char *key, zbx_agent_check_t check_func, char **value, char **error)
{
	int ret = FAIL;
	char **pvalue;
	AGENT_RESULT result;
	AGENT_REQUEST request;

	zbx_init_agent_request(&request);
	zbx_init_agent_result(&result);
	if (SUCCEED != zbx_parse_item_key(key, &request))
	{
		*value = zbx_strdup(NULL, "Invalid item key format.");
		goto out;
	}
	if (SYSINFO_RET_OK != check_func(&request, &result))
	{
		if (0 != ZBX_ISSET_MSG(&result))
		{
			*error = zbx_strdup(NULL, result.msg);
		}
		else
			*error = zbx_strdup(NULL, "Unknown error.");
		goto out;
	}

	if (NULL != (pvalue = ZBX_GET_TEXT_RESULT(&result)))
		*value = zbx_strdup(NULL, *pvalue);

	ret = SUCCEED;
out:
	zbx_free_agent_result(&result);
	zbx_free_agent_request(&request);
	return ret;
}

*/
import "C"

import (
	"errors"
	"unsafe"

	"golang.zabbix.com/agent2/pkg/itemutil"
	"golang.zabbix.com/sdk/log"
)

func ExecuteCheck(key string, params []string) (result *string, err error) {
	cfunc := resolveMetric(key)

	if cfunc == nil {
		return nil, errors.New("Unsupported item key.")
	}

	var cvalue, cerrmsg *C.char
	ckey := C.CString(itemutil.MakeKey(key, params))
	defer func() {
		log.Tracef("Calling C function \"free(ckey)\"")
		C.free(unsafe.Pointer(ckey))
	}()

	log.Tracef("Calling C function \"execute_check()\"")
	if C.execute_check(ckey, C.zbx_agent_check_t(cfunc), &cvalue, &cerrmsg) == Succeed {
		if cvalue != nil {
			value := C.GoString(cvalue)
			result = &value
		}
		log.Tracef("Calling C function \"free(cvalue)\"")
		C.free(unsafe.Pointer(cvalue))

	} else {
		err = errors.New(C.GoString(cerrmsg))
		log.Tracef("Calling C function \"free(cerrmsg)\"")
		C.free(unsafe.Pointer(cerrmsg))
	}

	return
}
