/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include <procfs.h>
#include "common.h"
#include "sysinfo.h"
#include "zbxregexp.h"
#include "log.h"

static int	check_procstate(psinfo_t *psinfo, int zbx_proc_stat)
{
	if (zbx_proc_stat == ZBX_PROC_STAT_ALL)
		return SUCCEED;

	switch (zbx_proc_stat)
	{
		case ZBX_PROC_STAT_RUN:
			return (psinfo->pr_lwp.pr_state == SRUN || psinfo->pr_lwp.pr_state == SONPROC) ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_SLEEP:
			return (psinfo->pr_lwp.pr_state == SSLEEP) ? SUCCEED : FAIL;
		case ZBX_PROC_STAT_ZOMB:
			return (psinfo->pr_lwp.pr_state == SZOMB) ? SUCCEED : FAIL;
	}

	return FAIL;
}

int	PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		tmp[MAX_STRING_LEN], *procname, *proccomm, *param, *memtype = NULL;
	DIR		*dir;
	struct dirent	*entries;
	struct passwd	*usrinfo;
	psinfo_t	psinfo;	/* In the correct procfs.h, the structure name is psinfo_t */
	int		fd = -1, do_task, proccount = 0, invalid_user = 0;
	zbx_uint64_t	mem_size = 0, byte_value = 0;
	double		pct_size = 0.0, pct_value = 0.0;
	size_t		*p_value;

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				return SYSINFO_RET_FAIL;
			}

			invalid_user = 1;
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
	memtype = get_rparam(request, 4);

	if (NULL == memtype || '\0' == *memtype || 0 == strcmp(memtype, "vsize"))
	{
		p_value = &psinfo.pr_size;	/* size of process image in Kbytes */
	}
	else if (0 == strcmp(memtype, "rss"))
	{
		p_value = &psinfo.pr_rssize;	/* resident set size in Kbytes */
	}
	else if (0 == strcmp(memtype, "pmem"))
	{
		p_value = NULL;			/* for % of system memory used by process */
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	if (NULL == (dir = opendir("/proc")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	while (NULL != (entries = readdir(dir)))
	{
		if (-1 != fd)
		{
			close(fd);
			fd = -1;
		}

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/psinfo", entries->d_name);

		if (-1 == (fd = open(tmp, O_RDONLY)))
			continue;

		if (-1 == read(fd, &psinfo, sizeof(psinfo)))
			continue;

		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, psinfo.pr_fname))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != psinfo.pr_uid)
			continue;

		if (NULL != proccomm && '\0' != *proccomm && NULL == zbx_regexp_match(psinfo.pr_psargs, proccomm, NULL))
			continue;

		if (NULL != p_value)
		{
			/* pr_size or pr_rssize in Kbytes */
			byte_value = *p_value << 10;	/* kB to Byte */

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
			/* % of system memory used by process, measured in 16-bit binary fractions in the range */
			/* 0.0 - 1.0 with the binary point to the right of the most significant bit. 1.0 == 0x8000 */
			pct_value = (double)((int)psinfo.pr_pctmem * 100) / 32768.0;

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

	closedir(dir);
	if (-1 != fd)
		close(fd);
out:
	if (NULL != p_value)
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
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		tmp[MAX_STRING_LEN], *procname, *proccomm, *param;
	DIR		*dir;
	struct dirent	*entries;
	zbx_stat_t	buf;
	struct passwd	*usrinfo;
	psinfo_t	psinfo;	/* In the correct procfs.h, the structure name is psinfo_t */
	int		fd = -1, proccount = 0, invalid_user = 0, zbx_proc_stat;

	if (4 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	procname = get_rparam(request, 0);
	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				return SYSINFO_RET_FAIL;
			}

			invalid_user = 1;
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

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	if (NULL == (dir = opendir("/proc")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	while (NULL != (entries = readdir(dir)))
	{
		if (-1 != fd)
		{
			close(fd);
			fd = -1;
		}

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/psinfo", entries->d_name);

		if (0 != zbx_stat(tmp, &buf))
			continue;

		if (-1 == (fd = open(tmp, O_RDONLY)))
			continue;

		if (-1 == read(fd, &psinfo, sizeof(psinfo)))
			continue;

		if (NULL != procname && '\0' != *procname && 0 != strcmp(procname, psinfo.pr_fname))
			continue;

		if (NULL != usrinfo && usrinfo->pw_uid != psinfo.pr_uid)
			continue;

		if (FAIL == check_procstate(&psinfo, zbx_proc_stat))
			continue;

		if (NULL != proccomm && '\0' != *proccomm && NULL == zbx_regexp_match(psinfo.pr_psargs, proccomm, NULL))
			continue;

		proccount++;
	}

	closedir(dir);
	if (-1 != fd)
		close(fd);
out:
	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
