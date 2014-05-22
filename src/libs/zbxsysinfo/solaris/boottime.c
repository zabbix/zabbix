/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "common.h"
#include "sysinfo.h"
#include "log.h"

int	SYSTEM_BOOTTIME(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	kstat_ctl_t	*kc;
	kstat_t		*kp;
	kstat_named_t	*kn;
	int		ret = SYSINFO_RET_FAIL;

	if (NULL == (kc = kstat_open()))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open kernel statistics facility: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (kp = kstat_lookup(kc, "unix", 0, "system_misc")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot look up in kernel statistics facility: %s",
				zbx_strerror(errno)));
		goto clean;
	}

	if (-1 == kstat_read(kc, kp, 0))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot read from kernel statistics facility: %s",
				zbx_strerror(errno)));
		goto clean;
	}

	if (NULL == (kn = (kstat_named_t *)kstat_data_lookup(kp, "boot_time")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot look up data in kernel statistics facility: %s",
				zbx_strerror(errno)));
		goto clean;
	}

	SET_UI64_RESULT(result, get_kstat_numeric_value(kn));
	ret = SYSINFO_RET_OK;
clean:
	kstat_close(kc);

	return ret;
}
