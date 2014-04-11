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
#include "zbxregexp.h"

#define DO_SUM	0
#define DO_MAX	1
#define DO_MIN	2
#define DO_AVG	3

static int	check_procstate(struct procsinfo *procsinfo, int zbx_proc_stat)
{
	if (ZBX_PROC_STAT_ALL == zbx_proc_stat)
		return SUCCEED;

	switch (zbx_proc_stat)
	{
		case ZBX_PROC_STAT_RUN:
			return SRUN == procsinfo->pi_state ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_SLEEP:
			return SSLEEP == procsinfo->pi_state ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_ZOMB:
			return SZOMB == procsinfo->pi_state ? SUCCEED : FAIL;
	}

	return FAIL;
}

static int	check_procargs(struct procsinfo *procsinfo, const char *proccomm)
{
	int	i;
	char	procargs[MAX_STRING_LEN];

	if (0 != getargs(&procsinfo, sizeof(procsinfo), procargs, sizeof(procargs)))
		return FAIL;

	for (i = 0; i < sizeof(procargs) - 1; i++)
	{
		if ('\0' == procargs[i])
		{
			if ('\0' == procargs[i + 1])
				break;

			procargs[i] = ' ';
		}
	}

	if (i == sizeof(procargs))
		procargs[i] = '\0';

	return NULL != zbx_regexp_match(procargs, proccomm, NULL) ? SUCCEED : FAIL;
}

int	PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*param, *procname, *proccomm;
	struct passwd		*usrinfo;
	struct procsinfo	procsinfo;
	pid_t			pid = 0;
	int			do_task;
	zbx_uint64_t		memsize = 0, proccount = 0, value;

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

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "sum"))
		do_task = DO_SUM;
	else if (0 == strcmp(param, "avg"))
		do_task = DO_AVG;
	else if (0 == strcmp(param, "max"))
		do_task = DO_MAX;
	else if (0 == strcmp(param, "min"))
		do_task = DO_MIN;
	else
		return SYSINFO_RET_FAIL;

	proccomm = get_rparam(request, 3);

	while (0 < getprocs(&procsinfo, (int)sizeof(struct procsinfo), NULL, 0, &pid, 1))
	{
		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, procsinfo.pi_comm))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != procsinfo.pi_uid)
			continue;

		if (NULL != proccomm && '\0' != *proccomm && SUCCEED != check_procargs(&procsinfo, proccomm))
			continue;

		value = procsinfo.pi_size;
		value <<= 12;	/* number of pages to bytes */

		if (0 == proccount++)
		{
			memsize = value;
		}
		else
		{
			if (DO_MAX == do_task)
				memsize = MAX(memsize, value);
			else if (DO_MIN == do_task)
				memsize = MIN(memsize, value);
			else
				memsize += value;
		}
        }

	if (DO_AVG == do_task)
		SET_DBL_RESULT(result, 0 == proccount ? 0 : memsize / (double)proccount);
	else
		SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*param, *procname, *proccomm;
	struct passwd		*usrinfo;
	struct procsinfo	procsinfo;
	pid_t			pid = 0;
	int			zbx_proc_stat;
	zbx_uint64_t		proccount = 0;

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

	while (0 < getprocs(&procsinfo, (int)sizeof(struct procsinfo), NULL, 0, &pid, 1))
	{
		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, procsinfo.pi_comm))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != procsinfo.pi_uid)
			continue;

		if (SUCCEED != check_procstate(&procsinfo, zbx_proc_stat))
			continue;

		if (NULL != proccomm && '\0' != *proccomm && SUCCEED != check_procargs(&procsinfo, proccomm))
			continue;

		proccount++;
        }

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
