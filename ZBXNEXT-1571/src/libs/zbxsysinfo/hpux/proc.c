/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

# ifndef _PSTAT64
# define _PSTAT64 /*Narrow (32-bit) applications use this flag to switch to the wide (64-bit) interfaces*/
# endif

#include "common.h"
#include "sysinfo.h"
#include "sys/pstat.h"

#define BURST ((size_t)10)

static int check_procstate(struct pst_status pst, int zbx_proc_stat)
{
	if (ZBX_PROC_STAT_ALL == zbx_proc_stat)
		return SUCCEED;

	switch (zbx_proc_stat)
	{
		case ZBX_PROC_STAT_RUN:
			return pst.pst_stat == PS_RUN ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_SLEEP:
			return pst.pst_stat == PS_SLEEP ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_ZOMB:
			return pst.pst_stat == PS_ZOMBIE ? SUCCEED : FAIL;
	}

	return FAIL;
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char tmp[MAX_STRING_LEN], *procname, *proccomm, *param;
	struct passwd *usrinfo;
	zbx_uint64_t proccount = 0;
	int zbx_proc_stat, i, count, idx = 0;
	struct pst_status pst[BURST];

	if (4 < request->nparam)
		return SYSINFO_RET_FAIL;

	procname = get_rparam(request, 0);
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

	memset(pst, 0, BURST * sizeof(struct pst_status));
	while ((count = pstat_getproc(pst, sizeof(pst[0]), BURST, idx)) > 0) {
		for (i = 0; i < count; i++) {
			if (NULL != procname && '\0' != *procname && 0 != strcmp(pst[i].pst_ucomm, procname))
				continue;

			if (NULL != usrinfo && usrinfo->pw_uid != pst[i].pst_uid)
				continue;

			if (NULL != proccomm && '\0' != *proccomm && NULL == zbx_regexp_match(pst[i].pst_cmd, proccomm, NULL))
				continue;

			if (FAIL == check_procstate(pst[i], zbx_proc_stat))
				continue;

			proccount++;
		}

		idx = pst[count-1].pst_idx + 1;
		memset(pst, 0, BURST * sizeof(struct pst_status));
	}

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}

#undef BURST

