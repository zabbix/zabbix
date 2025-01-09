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

#include "zbxcommon.h"
#if !defined(_WINDOWS) && !defined(__MINGW32__)
#	include "zbxnix.h"
#else
#	include "zbxwin32.h"
#endif

int	zbx_agent_pid;

ZBX_GET_CONFIG_VAR2(const char*, const char*, zbx_progname, NULL)

void handleZabbixLog(int level, const char *message);

void zbx_log_go_impl(int level, const char *fmt, va_list args)
{
	// no need to allocate memory for message if level is set to log.None (-1)
	if (zbx_agent_pid == getpid() && -1 != level)
	{
		va_list	tmp;
		size_t	size;

		va_copy(tmp, args);

		// zbx_vsnprintf_check_len() cannot return negative result
		size = (size_t)zbx_vsnprintf_check_len(fmt, tmp) + 2;

		va_end(tmp);

		char	*message = (char *)zbx_malloc(NULL, size);
		zbx_vsnprintf(message, size, fmt, args);

		handleZabbixLog(level, message);
		zbx_free(message);
	}
}

void	zbx_handle_log(void)
{
	// rotation is handled by go logger backend
}

int	zbx_redirect_stdio(const char *filename)
{
	// rotation is handled by go logger backend
	return FAIL;
}

void	log_init(void)
{
	zbx_init_library_common(zbx_log_go_impl, get_zbx_progname, zbx_backtrace);
}

*/
import "C"

import (
	"golang.zabbix.com/sdk/log"
)

func SetLogLevel(level int) {
	C.zbx_set_log_level(C.int(level))
}

func init() {
	log.Tracef("Calling C function \"getpid()\"")
	C.zbx_agent_pid = C.getpid()
	C.log_init()
}
