/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"

int	SYSTEM_UPTIME(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_SYSINFO_UPTIME
	struct sysinfo info;

	if (0 == sysinfo(&info))
	{
		SET_UI64_RESULT(result, info.uptime);
		return SYSINFO_RET_OK;
	}
	else
		return SYSINFO_RET_FAIL;
#else
#ifdef HAVE_FUNCTION_SYSCTL_KERN_BOOTTIME
	struct timeval	uptime;
	int		mib[2], len, now;

	mib[0] = CTL_KERN;
	mib[1] = KERN_BOOTTIME;

	len = sizeof(uptime);

	if (0 != sysctl(mib, 2, &uptime, (size_t *)&len, NULL, 0))
		return SYSINFO_RET_FAIL;

	now = time(NULL);

	SET_UI64_RESULT(result, now-uptime.tv_sec);
	return SYSINFO_RET_OK;
#else
/* Solaris */
#ifdef HAVE_KSTAT_H
	kstat_ctl_t   *kc;
	kstat_t       *kp;
	kstat_named_t *kn;

	long          hz;
	long          secs;

	/* open kstat */
	kc = kstat_open();
	if (0 == kc)
		return SYSINFO_RET_FAIL;

	/* read uptime counter */
	kp = kstat_lookup(kc, "unix", 0, "system_misc");
	if (0 == kp)
	{
		kstat_close(kc);
		return SYSINFO_RET_FAIL;
	}

	if (-1 == kstat_read(kc, kp, 0))
	{
		kstat_close(kc);
		return SYSINFO_RET_FAIL;
	}

	hz = sysconf(_SC_CLK_TCK);

	/* make sure we do not divide by 0 */
	assert(hz);

	kn = (kstat_named_t*)kstat_data_lookup(kp, "clk_intr");
	secs = get_kstat_numeric_value(kn) / hz;

	/* close kstat */
	kstat_close(kc);

	SET_UI64_RESULT(result, secs);
	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
#endif
#endif
}
