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
#include "stats.h"

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

/******************************************************************************
 *                                                                            *
 * Function: proc_read_cpu_util                                               *
 *                                                                            *
 * Purpose: reads process cpu utilization values from /proc/[pid]/stat file   *
 *                                                                            *
 * Parameters: procutil - [IN/OUT] the process cpu utilization data           *
 *                                                                            *
 * Return value: SUCCEED - the process cpu utilization data was read          *
 *                         successfully                                       *
 *               <0      - otherwise, -errno code is returned                 *
 *                                                                            *
 ******************************************************************************/
static int	proc_read_cpu_util(zbx_procstat_util_t *procutil)
{
	int		fd, n;
	char		tmp[MAX_STRING_LEN];
	psinfo_t	psinfo;
	pstatus_t	pstatus;

	zbx_snprintf(tmp, sizeof(tmp), "/proc/%d/psinfo", (int)procutil->pid);

	if (-1 == (fd = open(tmp, O_RDONLY)))
		return -errno;

	n = read(fd, &psinfo, sizeof(psinfo));
	close(fd);

	if (-1 == n)
		return -errno;

	procutil->starttime = psinfo.pr_start.tv_sec;

	zbx_snprintf(tmp, sizeof(tmp), "/proc/%d/status", (int)procutil->pid);

	if (-1 == (fd = open(tmp, O_RDONLY)))
		return -errno;

	n = read(fd, &pstatus, sizeof(pstatus));
	close(fd);

	if (-1 == n)
		return -errno;

	/* convert cpu utilization time to clock ticks */
	procutil->utime = ((zbx_uint64_t)pstatus.pr_utime.tv_sec * 1e9 + pstatus.pr_utime.tv_nsec) *
			sysconf(_SC_CLK_TCK) / 1e9;

	procutil->stime = ((zbx_uint64_t)pstatus.pr_stime.tv_sec * 1e9 + pstatus.pr_stime.tv_nsec) *
			sysconf(_SC_CLK_TCK) / 1e9;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_proc_get_stats                                               *
 *                                                                            *
 * Purpose: get process cpu utilization data                                  *
 *                                                                            *
 * Parameters: procs     - [IN/OUT] an array of process utilization data      *
 *             procs_num - [IN] the number of items in procs array            *
 *                                                                            *
 ******************************************************************************/
void	zbx_proc_get_stats(zbx_procstat_util_t *procs, int procs_num)
{
	const char	*__function_name = "zbx_proc_get_stats";
	int	i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() procs_num:%d", __function_name, procs_num);

	for (i = 0; i < procs_num; i++)
		procs[i].error = proc_read_cpu_util(&procs[i]);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_proc_get_pids                                                *
 *                                                                            *
 * Purpose: get pids matching the specified process name, user name and       *
 *          command line                                                      *
 *                                                                            *
 * Parameters: procname    - [IN] the process name, NULL - all                *
 *             username    - [IN] the user name, NULL - all                   *
 *             cmdline     - [IN] the command line, NULL - all                *
 *             pids        - [OUT] the vector of matching pids                *
 *                                                                            *
 * Return value: SUCCEED   - the pids were read successfully                  *
 *               -errno    - failed to read pids                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_proc_get_pids(const char *procname, const char *username, const char *cmdline, zbx_uint64_t flags,
		zbx_vector_uint64_t *pids)
{
	const char	*__function_name = "zbx_proc_get_pids";
	DIR		*dir;
	struct dirent	*entries;
	struct passwd	*usrinfo;
	char		tmp[MAX_STRING_LEN];
	int		pid, ret = FAIL, fd = -1;
	psinfo_t	psinfo;	/* In the correct procfs.h, the structure name is psinfo_t */
#ifdef HAVE_ZONE_H
	zoneid_t	zoneid;
#endif

	zabbix_log(LOG_LEVEL_TRACE, "In %s() procname:%s username:%s cmdline:%s zone:%d", __function_name,
			ZBX_NULL2EMPTY_STR(procname), ZBX_NULL2EMPTY_STR(username), ZBX_NULL2EMPTY_STR(cmdline), flags);

	if (NULL != username)
	{
		/* in the case of invalid user there are no matching processes, set empty result */
		if (NULL == (usrinfo = getpwnam(username)))
		{
			ret = SUCCEED;
			goto out;
		}
	}
	else
		usrinfo = NULL;

	if (NULL == (dir = opendir("/proc")))
	{
		ret = -errno;
		goto out;
	}

#ifdef HAVE_ZONE_H
	zoneid = getzoneid();
#endif

	while (NULL != (entries = readdir(dir)))
	{
		/* skip entries not containing pids */
		if (FAIL == is_uint32(entries->d_name, &pid))
			continue;

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

		if (NULL != cmdline && '\0' != *cmdline && NULL == zbx_regexp_match(psinfo.pr_psargs, cmdline, NULL))
			continue;

#ifdef HAVE_ZONE_H
		if (0 == (ZBX_PROCSTAT_FLAGS_ZONE_ALL & flags) && psinfo.pr_zoneid != zoneid)
			continue;
#endif

		zbx_vector_uint64_append(pids, (zbx_uint64_t)pid);
	}

	closedir(dir);
	if (-1 != fd)
		close(fd);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): %s", __function_name, zbx_result_string(ret));

	return ret;
}

int	PROC_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*procname, *username, *cmdline, *tmp, *flags;
	char		*errmsg = NULL;
	int		period, type, ret;
	double		value;
	zbx_uint64_t	zoneflag;

	/* proc.cpu.util[<procname>,<username>,(user|system),<cmdline>,(avg1|avg5|avg15)] */
	if (6 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

#ifndef HAVE_ZONE_H
	if (5 == request->nparam && GLOBAL_ZONEID != getzoneid())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported sixth parameter."));
		return SYSINFO_RET_FAIL;
	}
#endif

	/* zbx_procstat_get_* functions expect NULL for default values -       */
	/* convert empty procname, username and cmdline strings to NULL values */
	if (NULL != (procname = get_rparam(request, 0)) && '\0' == *procname)
		procname = NULL;

	if (NULL != (username = get_rparam(request, 1)) && '\0' == *username)
		username = NULL;

	if (NULL != (cmdline = get_rparam(request, 3)) && '\0' == *cmdline)
		cmdline = NULL;

	/* utilization type parameter (user|system) */
	if (NULL == (tmp = get_rparam(request, 2)) || '\0' == *tmp || 0 == strcmp(tmp, "total"))
	{
		type = ZBX_PROCSTAT_CPU_TOTAL;
	}
	else if (0 == strcmp(tmp, "user"))
	{
		type = ZBX_PROCSTAT_CPU_USER;
	}
	else if (0 == strcmp(tmp, "system"))
	{
		type = ZBX_PROCSTAT_CPU_SYSTEM;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* mode parameter (avg1|avg5|avg15) */
	if (NULL == (tmp = get_rparam(request, 4)) || '\0' == *tmp || 0 == strcmp(tmp, "avg1"))
	{
		period = SEC_PER_MIN;
	}
	else if ( 0 == strcmp(tmp, "avg5"))
	{
		period = SEC_PER_MIN * 5;
	}
	else if ( 0 == strcmp(tmp, "avg15"))
	{
		period = SEC_PER_MIN * 15;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == (flags = get_rparam(request, 5)) || '\0' == *flags || 0 == strcmp(flags, "current"))
	{
		zoneflag = ZBX_PROCSTAT_FLAGS_ZONE_CURRENT;
	}
	else if(0 == strcmp(flags, "all"))
	{
		zoneflag = ZBX_PROCSTAT_FLAGS_ZONE_ALL;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid sixth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED != zbx_procstat_collector_started())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	if (SUCCEED != (ret = zbx_procstat_get_util(procname, username, cmdline, zoneflag, period, type, &value,
			&errmsg)))
	{
		/* zbx_procstat_get_* functions will return FAIL when either a collection   */
		/* error was registered or if less than 2 data samples were collected.      */
		/* In the first case the errmsg will contain error message.                 */
		if (NULL != errmsg)
		{
			SET_MSG_RESULT(result, errmsg);
			return SYSINFO_RET_FAIL;
		}
	}
	else
		SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}
