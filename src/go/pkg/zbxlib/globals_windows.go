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
#include "zbxstr.h"
#include "zbxsysinfo.h"
#include "zbxcomms.h"
#include "zbxwin32.h"
#include "../src/zabbix_agent/metrics/metrics.h"

#cgo LDFLAGS: -lDbghelp -lpsapi -lws2_32 -lWevtapi -ldnsapi
#cgo pcre2 LDFLAGS: -lpcre2-8
#cgo openssl LDFLAGS: -lssl -lcrypto

zbx_metric_t	parameters_common[] = {NULL};
zbx_metric_t	*get_parameters_common(void)
{
	return &parameters_common[0];
}

zbx_metric_t	parameters_common_local[] = {NULL};
zbx_metric_t	*get_parameters_common_local(void)
{
	return &parameters_common_local[0];
}

#define ZBX_MESSAGE_BUF_SIZE	1024

char	*zbx_strerror_from_system(zbx_syserror_t error)
{
	size_t		offset = 0;
	wchar_t		wide_string[ZBX_MESSAGE_BUF_SIZE];
	static __thread char	utf8_string[ZBX_MESSAGE_BUF_SIZE];

	offset += zbx_snprintf(utf8_string, sizeof(utf8_string), "[0x%08lX] ", error);

	if (0 == FormatMessageW(FORMAT_MESSAGE_FROM_SYSTEM | FORMAT_MESSAGE_IGNORE_INSERTS, NULL, error,
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), wide_string, ZBX_MESSAGE_BUF_SIZE, NULL))
	{
		zbx_snprintf(utf8_string + offset, sizeof(utf8_string) - offset,
				"unable to find message text [0x%08lX]", GetLastError());

		return utf8_string;
	}

	zbx_unicode_to_utf8_static(wide_string, utf8_string + offset, (int)(sizeof(utf8_string) - offset));

	zbx_rtrim(utf8_string, "\r\n ");

	return utf8_string;
}

int	perf_counter(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Not supported."));
	return SYSINFO_RET_FAIL;
}

DWORD	zbx_get_builtin_counter_index(zbx_builtin_counter_ref_t counter_ref)
{
	return 0;
}

DWORD	zbx_get_builtin_object_index(zbx_builtin_counter_ref_t object_ref)
{
	return 0;
}

*/
import "C"
