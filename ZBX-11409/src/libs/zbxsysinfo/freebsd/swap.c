/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

int	SYSTEM_SWAP_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
/*
 *  FreeBSD 7.0 i386
 */
#ifdef XSWDEV_VERSION	/* defined in <vm/vm_param.h> */
	char		*swapdev, *mode;
	int		mib[16], *mib_dev;
	size_t		sz, mib_sz;
	struct xswdev	xsw;
	zbx_uint64_t	total = 0, used = 0;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	sz = ARRSIZE(mib);
	if (-1 == sysctlnametomib("vm.swap_info", mib, &sz))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain \"vm.swap_info\" system parameter: %s",
				zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	mib_sz = sz + 1;
	mib_dev = &(mib[sz]);

	*mib_dev = 0;
	sz = sizeof(xsw);

	while (-1 != sysctl(mib, mib_sz, &xsw, &sz, NULL, 0))
	{
		if (NULL == swapdev || '\0' == *swapdev || 0 == strcmp(swapdev, "all")	/* default parameter */
				|| 0 == strcmp(swapdev, devname(xsw.xsw_dev, S_IFCHR)))
		{
			total += (zbx_uint64_t)xsw.xsw_nblks;
			used += (zbx_uint64_t)xsw.xsw_used;
		}
		(*mib_dev)++;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "free"))	/* default parameter */
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
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
#else
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Agent was compiled without support for \"xswdev\" structure."));
	return SYSINFO_RET_FAIL;
#endif
}
