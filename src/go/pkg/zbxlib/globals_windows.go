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
#include "common.h"
#include "sysinfo.h"
#include "comms.h"
#include "perfmon.h"
#include "../src/zabbix_agent/metrics.h"

#cgo LDFLAGS: -Wl,--start-group
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/misc.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/str.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/file.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/alias.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/time.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/fatal.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/disk.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/threads.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/iprange.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/md5.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/sysinfo.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/vector.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/hashset.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/zbxregexp.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/algodefs.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/persistent_state.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/logfiles.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/json.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/json_parser.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/jsonpath.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/jsonobj.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/sha256crypt.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/variant.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/sysinfo_system.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/sysinfo_dns.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/sysinfo_file.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/sysinfo_dir.o
#cgo LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/eventlog.o
#cgo openssl LDFLAGS: ${SRCDIR}/../../../../build/mingw/output/tls_version.o
#cgo LDFLAGS: -lDbghelp -lpsapi -lws2_32 -lWevtapi -ldnsapi
#cgo pcre  LDFLAGS: -lpcre
#cgo pcre2 LDFLAGS: -lpcre2-8
#cgo openssl LDFLAGS: -lssl -lcrypto
#cgo LDFLAGS: -Wl,--end-group

int CONFIG_TIMEOUT = 3;
int CONFIG_MAX_LINES_PER_SECOND = 20;
int CONFIG_EVENTLOG_MAX_LINES_PER_SECOND = 20;
char ZBX_THREAD_LOCAL *CONFIG_HOSTNAME = NULL;
int	CONFIG_UNSAFE_USER_PARAMETERS= 0;
int	CONFIG_ENABLE_REMOTE_COMMANDS= 0;
char *CONFIG_SOURCE_IP = NULL;

int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

const char	*progname = NULL;
const char	title_message[] = "agent";
const char	*usage_message[] = {};
const char	*help_message[] = {};

ZBX_METRIC	parameters_common[] = {NULL};
ZBX_METRIC	parameters_common_local[] = {NULL};

#define ZBX_MESSAGE_BUF_SIZE	1024

char	*strerror_from_system(unsigned long error)
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

int	PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Not supported."));
	return SYSINFO_RET_FAIL;
}

DWORD	get_builtin_counter_index(zbx_builtin_counter_ref_t counter_ref)
{
	return 0;
}

DWORD	get_builtin_object_index(zbx_builtin_counter_ref_t object_ref)
{
	return 0;
}
*/
import "C"
