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

#include "zbxsysinfo.h"
#include "../sysinfo.h"

#ifdef HAVE_SYS_UTSNAME_H
#	include <sys/utsname.h>
#endif

int	system_uname(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct utsname	name;

	if (-1 == uname(&name))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	SET_STR_RESULT(result, zbx_dsprintf(NULL, "%s %s %s %s %s %s", name.sysname, name.nodename, name.release,
			name.version, name.machine, name.idnumber));

	return SYSINFO_RET_OK;
}
