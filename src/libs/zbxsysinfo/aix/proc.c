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

static int	check_procstate(struct procentry64 *procentry, int zbx_proc_stat)
{
	if (ZBX_PROC_STAT_ALL == zbx_proc_stat)
		return SUCCEED;

	switch (zbx_proc_stat)
	{
		case ZBX_PROC_STAT_RUN:
			return SRUN == procentry->pi_state ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_SLEEP:
			return SSLEEP == procentry->pi_state ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_ZOMB:
			return SZOMB == procentry->pi_state ? SUCCEED : FAIL;
	}

	return FAIL;
}

static int	check_procargs(struct procentry64 *procentry, const char *proccomm)
{
	int	i;
	char	procargs[MAX_STRING_LEN];

	if (0 != getargs(procentry, (int)sizeof(*procentry), procargs, (int)sizeof(procargs)))
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

	if (i == sizeof(procargs) - 1)
		procargs[i] = '\0';

	return NULL != zbx_regexp_match(procargs, proccomm, NULL) ? SUCCEED : FAIL;
}

int	PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*param, *procname, *proccomm;
	struct passwd		*usrinfo;
	struct procentry64	procentry;
	pid_t			pid = 0;
	int			do_task;
	zbx_uint64_t		memsize = 0, proccount = 0, value;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		if (NULL == (usrinfo = getpwnam(param)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain user information."));
			return SYSINFO_RET_FAIL;
		}
	}
	else
		usrinfo = NULL;

	param = get_rparam(request, 2);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "sum"))
		do_task = ZBX_DO_SUM;
	else if (0 == strcmp(param, "avg"))
		do_task = ZBX_DO_AVG;
	else if (0 == strcmp(param, "max"))
		do_task = ZBX_DO_MAX;
	else if (0 == strcmp(param, "min"))
		do_task = ZBX_DO_MIN;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	proccomm = get_rparam(request, 3);

	while (0 < getprocs64(&procentry, (int)sizeof(struct procentry64), NULL, 0, &pid, 1))
	{
		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, procentry.pi_comm))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != procentry.pi_uid)
			continue;

		if (NULL != proccomm && '\0' != *proccomm && SUCCEED != check_procargs(&procentry, proccomm))
			continue;

		value = procentry.pi_size;
		value <<= 12;	/* number of pages to bytes */

		if (0 == proccount++)
		{
			memsize = value;
		}
		else
		{
			if (ZBX_DO_MAX == do_task)
				memsize = MAX(memsize, value);
			else if (ZBX_DO_MIN == do_task)
				memsize = MIN(memsize, value);
			else
				memsize += value;
		}
        }

	if (ZBX_DO_AVG == do_task)
		SET_DBL_RESULT(result, 0 == proccount ? 0 : memsize / (double)proccount);
	else
		SET_UI64_RESULT(result, memsize);

	return SYSINFO_RET_OK;
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*param, *procname, *proccomm;
	struct passwd		*usrinfo;
	struct procentry64	procentry;
	pid_t			pid = 0;
	int			zbx_proc_stat;
	zbx_uint64_t		proccount = 0;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		if (NULL == (usrinfo = getpwnam(param)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain user information."));
			return SYSINFO_RET_FAIL;
		}
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
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	proccomm = get_rparam(request, 3);

	while (0 < getprocs64(&procentry, (int)sizeof(struct procentry64), NULL, 0, &pid, 1))
	{
		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, procentry.pi_comm))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != procentry.pi_uid)
			continue;

		if (SUCCEED != check_procstate(&procentry, zbx_proc_stat))
			continue;

		if (NULL != proccomm && '\0' != *proccomm && SUCCEED != check_procargs(&procentry, proccomm))
			continue;

		proccount++;
        }

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
