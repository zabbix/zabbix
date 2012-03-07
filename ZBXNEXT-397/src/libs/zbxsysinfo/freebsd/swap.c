/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"

int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
/*
 *  FreeBSD 7.0 i386
 */
#ifdef XSWDEV_VERSION	/* defined in <vm/vm_param.h> */
	char		swapdev[64], mode[64];
	int		mib[16], *mib_dev;
	size_t		sz, mib_sz;
	struct xswdev	xsw;
	zbx_uint64_t	total = 0, used = 0;

        assert(result);

        init_result(result);

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, swapdev, sizeof(swapdev)))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	sz = sizeof(mib) / sizeof(mib[0]);
	if (-1 == sysctlnametomib("vm.swap_info", mib, &sz))
		return FAIL;

	mib_sz = sz + 1;
	mib_dev = &(mib[sz]);

	*mib_dev = 0;
	sz = sizeof(xsw);

	while (-1 != sysctl(mib, mib_sz, &xsw, &sz, NULL, 0))
	{
		if ('\0' == *swapdev || 0 == strcmp(swapdev, "all")	/* default parameter */
				|| 0 == strcmp(swapdev, devname(xsw.xsw_dev, S_IFCHR)))
		{
			total += (zbx_uint64_t)xsw.xsw_nblks;
			used += (zbx_uint64_t)xsw.xsw_used;
		}
		(*mib_dev)++;
	}

	if ('\0' == *mode || 0 == strcmp(mode, "free"))	/* default parameter */
		SET_UI64_RESULT(result, (total - used) * getpagesize());
	else if (0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, total * getpagesize());
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, used * getpagesize());
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, total ? ((double)(total - used) * 100.0 / (double)total) : 0.0);
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, total ? ((double)used * 100.0 / (double)total) : 0.0);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif
}
