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

/* Enable wide (64-bit) interfaces for narrow (32-bit) applications (see pstat(2) for details). */
/* Without this on some HP-UX systems you can get runtime error when calling pstat_getproc(): */
/* Value too large to be stored in data type */
#ifndef _PSTAT64
#	define _PSTAT64
#endif

#include "common.h"
#include "sysinfo.h"
#include <sys/pstat.h>
#include "zbxregexp.h"

static int	check_procstate(struct pst_status pst, int zbx_proc_stat)
{
	if (ZBX_PROC_STAT_ALL == zbx_proc_stat)
		return SUCCEED;

	switch (zbx_proc_stat)
	{
		case ZBX_PROC_STAT_RUN:
			return PS_RUN == pst.pst_stat ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_SLEEP:
			return PS_SLEEP == pst.pst_stat ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_ZOMB:
			return PS_ZOMBIE == pst.pst_stat ? SUCCEED : FAIL;
	}

	return FAIL;
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#define ZBX_BURST	((size_t)10)

	char			*procname, *proccomm, *param;
	struct passwd		*usrinfo;
	zbx_uint64_t		proccount = 0;
	int			zbx_proc_stat, i, count, idx = 0;
	struct pst_status	pst[ZBX_BURST];

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	procname = get_rparam(request, 0);
	if (NULL != procname && '\0' == *procname)
		procname = NULL;

	param = get_rparam(request, 1);
	if (NULL != param && '\0' != *param)
	{
		if (NULL == (usrinfo = getpwnam(param)))	/* incorrect user name */
			return SYSINFO_RET_FAIL;
	}
	else
		usrinfo = NULL;

	param = get_rparam(request, 2);
	if (NULL == param || '\0' == *param || 0 == strcmp(param, "all"))
		zbx_proc_stat = ZBX_PROC_STAT_ALL;
	else if (0 == strcmp(param, "run"))
		zbx_proc_stat = ZBX_PROC_STAT_RUN;
	else if (0 == strcmp(param, "sleep"))
		zbx_proc_stat = ZBX_PROC_STAT_SLEEP;
	else if (0 == strcmp(param, "zomb"))
		zbx_proc_stat = ZBX_PROC_STAT_ZOMB;
	else
		return SYSINFO_RET_FAIL;

	proccomm = get_rparam(request, 3);
	if (NULL != proccomm && '\0' == *proccomm)
		proccomm = NULL;

	memset(pst, 0, sizeof(pst));

	while (0 < (count = pstat_getproc(pst, sizeof(*pst), ZBX_BURST, idx)))
	{
		for (i = 0; i < count; i++)
		{
			if (NULL != procname && 0 != strcmp(pst[i].pst_ucomm, procname))
				continue;

			if (NULL != usrinfo && usrinfo->pw_uid != pst[i].pst_uid)
				continue;

			if (NULL != proccomm && NULL == zbx_regexp_match(pst[i].pst_cmd, proccomm, NULL))
				continue;

			if (FAIL == check_procstate(pst[i], zbx_proc_stat))
				continue;

			proccount++;
		}

		idx = pst[count - 1].pst_idx + 1;
		memset(pst, 0, sizeof(pst));
	}

	if (-1 == count)
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
