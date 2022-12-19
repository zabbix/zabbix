/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
			return SACTIVE == procentry->pi_state && 0 != procentry->pi_cpu ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_SLEEP:
			return SACTIVE == procentry->pi_state && 0 == procentry->pi_cpu ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_ZOMB:
			return SZOMB == procentry->pi_state ? SUCCEED : FAIL;
	}

	return FAIL;
}

static int	check_procargs(struct procentry64 *procentry, const char *proccomm)
{
	unsigned int	i;
	char		procargs[MAX_BUFFER_LEN];

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
#define ZBX_VSIZE	0
#define ZBX_RSS		1
#define ZBX_PMEM	2
#define ZBX_SIZE	3
#define ZBX_DSIZE	4
#define ZBX_TSIZE	5
#define ZBX_SDSIZE	6
#define ZBX_DRSS	7
#define ZBX_TRSS	8

/* The pi_???_l2psize fields are described as: log2 of a proc's ??? pg sz */
/* Basically it's bits per page, so define 12 bits (4kb) for earlier AIX  */
/* versions that do not support those fields.                             */
#ifdef _AIX61
#	define ZBX_L2PSIZE(field) 	field
#else
#	define ZBX_L2PSIZE(field)	12
#endif

	char			*param, *procname, *proccomm, *mem_type = NULL;
	struct passwd		*usrinfo;
	struct procentry64	procentry;
	pid_t			pid = 0;
	int			do_task, mem_type_code, proccount = 0, invalid_user = 0;
	zbx_uint64_t		mem_size = 0, byte_value = 0;
	double			pct_size = 0.0, pct_value = 0.0;

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		if (NULL == (usrinfo = getpwnam(param)))
			invalid_user = 1;
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
	mem_type = get_rparam(request, 4);

	if (NULL == mem_type || '\0' == *mem_type || 0 == strcmp(mem_type, "vsize"))
	{
		mem_type_code = ZBX_VSIZE;		/* virtual memory size */
	}
	else if (0 == strcmp(mem_type, "rss"))
	{
		mem_type_code = ZBX_RSS;		/* resident set size */
	}
	else if (0 == strcmp(mem_type, "pmem"))
	{
		mem_type_code = ZBX_PMEM;		/* percentage of real memory used by process */
	}
	else if (0 == strcmp(mem_type, "size"))
	{
		mem_type_code = ZBX_SIZE;		/* size of process (code + data) */
	}
	else if (0 == strcmp(mem_type, "dsize"))
	{
		mem_type_code = ZBX_DSIZE;		/* data size */
	}
	else if (0 == strcmp(mem_type, "tsize"))
	{
		mem_type_code = ZBX_TSIZE;		/* text size */
	}
	else if (0 == strcmp(mem_type, "sdsize"))
	{
		mem_type_code = ZBX_SDSIZE;		/* data size from shared library */
	}
	else if (0 == strcmp(mem_type, "drss"))
	{
		mem_type_code = ZBX_DRSS;		/* data resident set size */
	}
	else if (0 == strcmp(mem_type, "trss"))
	{
		mem_type_code = ZBX_TRSS;		/* text resident set size */
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	while (0 < getprocs64(&procentry, (int)sizeof(struct procentry64), NULL, 0, &pid, 1))
	{
		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, procentry.pi_comm))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != procentry.pi_uid)
			continue;

		if (NULL != proccomm && '\0' != *proccomm && SUCCEED != check_procargs(&procentry, proccomm))
			continue;

		switch (mem_type_code)
		{
			case ZBX_VSIZE:
				/* historically default proc.mem[] on AIX */
				byte_value = (zbx_uint64_t)procentry.pi_size << 12;	/* number of pages to bytes */
				break;
			case ZBX_RSS:
				/* try to be compatible with "ps -o rssize" */
				byte_value = ((zbx_uint64_t)procentry.pi_drss << ZBX_L2PSIZE(procentry.pi_data_l2psize)) +
						((zbx_uint64_t)procentry.pi_trss << ZBX_L2PSIZE(procentry.pi_text_l2psize));
				break;
			case ZBX_PMEM:
				/* try to be compatible with "ps -o pmem" */
				pct_value = procentry.pi_prm;
				break;
			case ZBX_SIZE:
				/* try to be compatible with "ps gvw" SIZE column */
				byte_value = (zbx_uint64_t)procentry.pi_dvm << ZBX_L2PSIZE(procentry.pi_data_l2psize);
				break;
			case ZBX_DSIZE:
				byte_value = procentry.pi_dsize;
				break;
			case ZBX_TSIZE:
				/* try to be compatible with "ps gvw" TSIZ column */
				byte_value = procentry.pi_tsize;
				break;
			case ZBX_SDSIZE:
				byte_value = procentry.pi_sdsize;
				break;
			case ZBX_DRSS:
				byte_value = (zbx_uint64_t)procentry.pi_drss << ZBX_L2PSIZE(procentry.pi_data_l2psize);
				break;
			case ZBX_TRSS:
				byte_value = (zbx_uint64_t)procentry.pi_trss << ZBX_L2PSIZE(procentry.pi_text_l2psize);
				break;
		}

		if (ZBX_PMEM != mem_type_code)
		{
			if (0 != proccount++)
			{
				if (ZBX_DO_MAX == do_task)
					mem_size = MAX(mem_size, byte_value);
				else if (ZBX_DO_MIN == do_task)
					mem_size = MIN(mem_size, byte_value);
				else
					mem_size += byte_value;
			}
			else
				mem_size = byte_value;
		}
		else
		{
			if (0 != proccount++)
			{
				if (ZBX_DO_MAX == do_task)
					pct_size = MAX(pct_size, pct_value);
				else if (ZBX_DO_MIN == do_task)
					pct_size = MIN(pct_size, pct_value);
				else
					pct_size += pct_value;
			}
			else
				pct_size = pct_value;
		}
	}
out:
	if (ZBX_PMEM != mem_type_code)
	{
		if (ZBX_DO_AVG == do_task)
			SET_DBL_RESULT(result, 0 == proccount ? 0.0 : (double)mem_size / (double)proccount);
		else
			SET_UI64_RESULT(result, mem_size);
	}
	else
	{
		if (ZBX_DO_AVG == do_task)
			SET_DBL_RESULT(result, 0 == proccount ? 0.0 : pct_size / (double)proccount);
		else
			SET_DBL_RESULT(result, pct_size);
	}

	return SYSINFO_RET_OK;

#undef ZBX_L2PSIZE

#undef ZBX_SIZE
#undef ZBX_RSS
#undef ZBX_VSIZE
#undef ZBX_PMEM
#undef ZBX_TSIZE
#undef ZBX_DSIZE
#undef ZBX_SDSIZE
#undef ZBX_DRSS
#undef ZBX_TRSS
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*param, *procname, *proccomm;
	struct passwd		*usrinfo;
	struct procentry64	procentry;
	pid_t			pid = 0;
	int			proccount = 0, invalid_user = 0, zbx_proc_stat;

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
			invalid_user = 1;
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

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

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
out:
	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
