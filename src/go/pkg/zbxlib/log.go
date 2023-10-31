/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxcommon.h"

int	zbx_agent_pid;

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
	zbx_init_library_common(zbx_log_go_impl);
}

*/
import "C"

import (
	"git.zabbix.com/ap/plugin-support/log"
)

func SetLogLevel(level int) {
	C.zbx_set_log_level(C.int(level))
}

func init() {
	log.Tracef("Calling C function \"getpid()\"")
	C.zbx_agent_pid = C.getpid()
	C.log_init()
}
