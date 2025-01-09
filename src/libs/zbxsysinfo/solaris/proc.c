/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxsysinfo.h"
#include "../sysinfo.h"

#include "../common/stats.h"
#include "../common/procstat.h"

#include "zbxregexp.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxtime.h"

#include <procfs.h>

#if !defined(HAVE_ZONE_H) && defined(HAVE_SYS_UTSNAME_H)
#	include <sys/utsname.h>
#endif

typedef struct
{
	pid_t		pid;
	uid_t		uid;

	char		*name;

	/* process name extracted from the first argument (usually executable path) */
	char		*name_arg0;

	/* process command line in format <arg0> <arg1> ... <argN>\0 */
	char		*cmdline;

#ifdef HAVE_ZONE_H
	zoneid_t	zoneid;
#endif
}
zbx_sysinfo_proc_t;

#ifndef HAVE_ZONE_H
/* helper functions for case if agent is compiled on Solaris 9 or earlier where zones are not supported */
/* but is running on a newer Solaris where zones are supported */

/******************************************************************************
 *                                                                            *
 * Purpose: gets Solaris version at runtime                                   *
 *                                                                            *
 * Parameters:                                                                *
 *     major_version - [OUT] major version (e.g. 5)                           *
 *     minor_version - [OUT] minor version (e.g. 9 for Solaris 9, 10 for      *
 *                           Solaris 10, 11 for Solaris 11)                   *
 * Return value:                                                              *
 *     SUCCEED - no errors, FAIL - error occurred                             *
 *                                                                            *
 ******************************************************************************/
static int	zbx_solaris_version_get(unsigned int *major_version, unsigned int *minor_version)
{
	struct utsname	name;

	if (-1 == uname(&name))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): uname() failed: %s", __func__, zbx_strerror(errno));

		return FAIL;
	}

	/* expected result in name.release: "5.9" - Solaris 9, "5.10" - Solaris 10, "5.11" - Solaris 11 */

	if (2 != sscanf(name.release, "%u.%u", major_version, minor_version))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): sscanf() failed on: \"%s\"", __func__, name.release);
		THIS_SHOULD_NEVER_HAPPEN;

		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: finds if zones are supported                                      *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - zones are supported                                          *
 *     FAIL - Zones are not supported or error occurred. For our purposes     *
 *            error counts as 'no support' for zones.                         *
 *                                                                            *
 ******************************************************************************/
static int	zbx_detect_zone_support(void)
{
#define ZBX_ZONE_SUPPORT_UNKNOWN	0
#define ZBX_ZONE_SUPPORT_YES		1
#define ZBX_ZONE_SUPPORT_NO		2
	static int	zone_support = ZBX_ZONE_SUPPORT_UNKNOWN;
	unsigned int	major, minor;

	switch (zone_support)
	{
		case ZBX_ZONE_SUPPORT_NO:
			return FAIL;
		case ZBX_ZONE_SUPPORT_YES:
			return SUCCEED;
		default:
			/* zones are supported in Solaris 10 and later (minimum version is "5.10") */

			if (SUCCEED == zbx_solaris_version_get(&major, &minor) &&
					((5 == major && 10 <= minor) || 5 < major))
			{
				zone_support = ZBX_ZONE_SUPPORT_YES;
				return SUCCEED;
			}
			else	/* failure to get Solaris version also results in "zones not supported" */
			{
				zone_support = ZBX_ZONE_SUPPORT_NO;
				return FAIL;
			}
	}
#undef ZBX_ZONE_SUPPORT_UNKNOWN
#undef ZBX_ZONE_SUPPORT_YES
#undef ZBX_ZONE_SUPPORT_NO
}
#endif

static void	zbx_sysinfo_proc_clear(zbx_sysinfo_proc_t *proc)
{
	zbx_free(proc->name);
	zbx_free(proc->cmdline);
	zbx_free(proc->name_arg0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees process data structure                                      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_sysinfo_proc_free(zbx_sysinfo_proc_t *proc)
{
	zbx_sysinfo_proc_clear(proc);
	zbx_free(proc);
}

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

static int	get_cmdline(FILE *f_cmd, char **line, size_t *line_offset)
{
	size_t	line_alloc = ZBX_KIBIBYTE, n;

	rewind(f_cmd);

	*line = (char *)zbx_malloc(*line, line_alloc + 2);
	*line_offset = 0;

	while (0 != (n = fread(*line + *line_offset, 1, line_alloc - *line_offset, f_cmd)))
	{
		*line_offset += n;

		if (0 != feof(f_cmd))
			break;

		line_alloc *= 2;
		*line = (char *)zbx_realloc(*line, line_alloc + 2);
	}

	if (0 == ferror(f_cmd))
	{
		if (0 == *line_offset || '\0' != (*line)[*line_offset - 1])
			(*line)[(*line_offset)++] = '\0';
		if (1 == *line_offset || '\0' != (*line)[*line_offset - 2])
			(*line)[(*line_offset)++] = '\0';

		return SUCCEED;
	}

	zbx_free(*line);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets single process information                                   *
 *                                                                            *
 * Parameters: pid    - [IN]                                                  *
 *             flags  - [IN] flags specifying process properties that must be *
 *                           returned                                         *
 *             proc   - [OUT] process data                                    *
 *             psinfo - [OUT] raw process information data                    *
 * Return value: SUCCEED - process information was retrieved successfully     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proc_get_process_info(const char *pid, unsigned int flags, zbx_sysinfo_proc_t *proc, psinfo_t *psinfo)
{
	int		fd, n;
	char		path[MAX_STRING_LEN];
	FILE		*fp;
	psinfo_t	psinfo_local;

	/* skip entries not containing pids */
	memset(proc, 0, sizeof(zbx_sysinfo_proc_t));
	if (FAIL == zbx_is_uint32(pid, &proc->pid))
		return FAIL;

	zbx_snprintf(path, sizeof(path), "/proc/%s/psinfo", pid);

	if (-1 == (fd = open(path, O_RDONLY)))
		return FAIL;

	if (NULL == psinfo)
		psinfo = &psinfo_local;

	n = read(fd, psinfo, sizeof(*psinfo));
	close(fd);

	if (-1 == n)
		return FAIL;

#ifdef HAVE_ZONE_H
	proc->zoneid = psinfo->pr_zoneid;
#endif

	if (0 != (flags & ZBX_SYSINFO_PROC_USER))
		proc->uid = psinfo->pr_uid;

	if (0 != (flags & ZBX_SYSINFO_PROC_NAME))
		proc->name = zbx_strdup(NULL, psinfo->pr_fname);

	if (0 != (flags & ZBX_SYSINFO_PROC_NAME) || 0 != (flags & ZBX_SYSINFO_PROC_CMDLINE))
	{
		zbx_snprintf(path, sizeof(path), "/proc/%s/cmdline", pid);
		if (NULL != (fp = fopen(path, "r")))
		{
			char	*line = NULL, *ptr;
			size_t	l;

			if (SUCCEED == get_cmdline(fp, &line, &l))
			{
				if (0 != (flags & ZBX_SYSINFO_PROC_NAME))
				{
					if (NULL == (ptr = strrchr(line, '/')))
						proc->name_arg0 = zbx_strdup(NULL, line);
					else
						proc->name_arg0 = zbx_strdup(NULL, ptr + 1);
				}

				if (0 != (flags & ZBX_SYSINFO_PROC_CMDLINE))
				{
					l = l - 2;

					for (int i = 0; i < l; i++)
					{
						if ('\0' == line[i])
							line[i] = ' ';
					}

					proc->cmdline = zbx_strdup(NULL, line);
				}
				zbx_free(line);
			}
			fclose(fp);
		}

		if (0 != (flags & ZBX_SYSINFO_PROC_CMDLINE) && NULL == proc->cmdline)
			proc->cmdline = zbx_strdup(NULL, psinfo->pr_psargs);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if process name matches filter                             *
 *                                                                            *
 ******************************************************************************/
static int	proc_match_name(const zbx_sysinfo_proc_t *proc, const char *procname)
{
	if (NULL == procname)
		return SUCCEED;

	if (NULL != proc->name && 0 == strcmp(procname, proc->name))
		return SUCCEED;

	if (NULL != proc->name_arg0 && 0 == strcmp(procname, proc->name_arg0))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if process user matches filter                             *
 *                                                                            *
 ******************************************************************************/
static int	proc_match_user(const zbx_sysinfo_proc_t *proc, const struct passwd *usrinfo)
{
	if (NULL == usrinfo)
		return SUCCEED;

	if (proc->uid == usrinfo->pw_uid)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if process command line matches filter                     *
 *                                                                            *
 ******************************************************************************/
static int	proc_match_cmdline(const zbx_sysinfo_proc_t *proc, const zbx_regexp_t *cmdline_rxp)
{
	if (NULL == cmdline_rxp)
		return SUCCEED;

	if (NULL != proc->cmdline && 0 == zbx_regexp_match_precompiled(proc->cmdline, cmdline_rxp))
		return SUCCEED;

	return FAIL;
}

#ifdef HAVE_ZONE_H
/******************************************************************************
 *                                                                            *
 * Purpose: checks if process zone matches filter                             *
 *                                                                            *
 ******************************************************************************/
static int	proc_match_zone(const zbx_sysinfo_proc_t *proc, zbx_uint64_t flags, zoneid_t zoneid)
{
	if (0 != (ZBX_PROCSTAT_FLAGS_ZONE_ALL & flags))
		return SUCCEED;

	if (proc->zoneid == zoneid)
		return SUCCEED;

	return FAIL;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: checks if process properties (except zone) matches filter         *
 *                                                                            *
 ******************************************************************************/
static int	proc_match_props(const zbx_sysinfo_proc_t *proc, const struct passwd *usrinfo, const char *procname,
		const zbx_regexp_t *cmdline_rxp)
{
	if (SUCCEED != proc_match_user(proc, usrinfo))
		return FAIL;

	if (SUCCEED != proc_match_name(proc, procname))
		return FAIL;

	if (SUCCEED != proc_match_cmdline(proc, cmdline_rxp))
		return FAIL;

	return SUCCEED;
}

int	proc_mem(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*procname, *proccomm, *param, *memtype = NULL, *rxp_error = NULL;
	DIR			*dir;
	struct dirent		*entries;
	struct passwd		*usrinfo;
	psinfo_t		psinfo;	/* In the correct procfs.h, the structure name is psinfo_t */
	int			do_task, proccount = 0, invalid_user = 0, proc_props = 0, ret = SYSINFO_RET_OK;
	zbx_uint64_t		mem_size = 0, byte_value = 0;
	double			pct_size = 0.0, pct_value = 0.0;
	size_t			*p_value;
	zbx_regexp_t		*proccomm_rxp = NULL;

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	if (NULL != (procname = get_rparam(request, 0)) && '\0' != *procname)
		proc_props |= ZBX_SYSINFO_PROC_NAME;
	else
		procname = NULL;

	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		proc_props |= ZBX_SYSINFO_PROC_USER;
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				ret = SYSINFO_RET_FAIL;
				goto clean;
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
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	if (NULL != (proccomm = get_rparam(request, 3)) && '\0' != *proccomm)
	{
		proc_props |= ZBX_SYSINFO_PROC_CMDLINE;

		if (SUCCEED != zbx_regexp_compile(proccomm, &proccomm_rxp, &rxp_error))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid regular expression in fourth parameter: "
					"%s", rxp_error));

			zbx_free(rxp_error);
			ret = SYSINFO_RET_FAIL;
			goto clean;
		}
	}
	else
		proccomm = NULL;

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
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	if (NULL == (dir = opendir("/proc")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc: %s", zbx_strerror(errno)));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	while (NULL != (entries = readdir(dir)))
	{
		zbx_sysinfo_proc_t	proc;

		if (SUCCEED != proc_get_process_info(entries->d_name, proc_props, &proc, &psinfo))
			continue;

		if (SUCCEED == proc_match_props(&proc, usrinfo, procname, proccomm_rxp))
		{
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
				/* % of system memory used by process, measured in 16-bit binary fractions in */
				/* the range 0.0 - 1.0 with the binary point to the right of the most         */
				/* significant bit. 1.0 == 0x8000                                             */
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

		zbx_sysinfo_proc_clear(&proc);
	}

	closedir(dir);
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
clean:
	if (NULL != proccomm_rxp)
		zbx_regexp_free(proccomm_rxp);

	return ret;
}

int	proc_num(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char			*procname, *proccomm, *param, *zone_parameter, *rxp_error = NULL;
	DIR			*dir;
	struct dirent		*entries;
	struct passwd		*usrinfo;
	int			proccount = 0, invalid_user = 0, proc_props = 0, zbx_proc_stat, ret = SYSINFO_RET_OK;
#ifdef HAVE_ZONE_H
	zoneid_t		zoneid;
	int			zoneflag;
#endif
	zbx_regexp_t		*proccomm_rxp = NULL;

	if (5 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	if (NULL != (procname = get_rparam(request, 0)) && '\0' != *procname)
		proc_props |= ZBX_SYSINFO_PROC_NAME;
	else
		procname = NULL;

	param = get_rparam(request, 1);

	if (NULL != param && '\0' != *param)
	{
		proc_props |= ZBX_SYSINFO_PROC_USER;
		errno = 0;

		if (NULL == (usrinfo = getpwnam(param)))
		{
			if (0 != errno)
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));
				ret = SYSINFO_RET_FAIL;
				goto clean;
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
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	if (NULL != (proccomm = get_rparam(request, 3)) && '\0' != *proccomm)
	{
		proc_props |= ZBX_SYSINFO_PROC_CMDLINE;

		if (SUCCEED != zbx_regexp_compile(proccomm, &proccomm_rxp, &rxp_error))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid regular expression in fourth parameter: "
					"%s", rxp_error));

			zbx_free(rxp_error);
			ret = SYSINFO_RET_FAIL;
			goto clean;
		}
	}
	else
		proccomm = NULL;

	if (NULL == (zone_parameter = get_rparam(request, 4)) || '\0' == *zone_parameter
			|| 0 == strcmp(zone_parameter, "current"))
	{
#ifdef HAVE_ZONE_H
		zoneflag = ZBX_PROCSTAT_FLAGS_ZONE_CURRENT;
#else
		if (SUCCEED == zbx_detect_zone_support())
		{
			/* Agent has been compiled on Solaris 9 or earlier where zones are not supported */
			/* but now it is running on a system with zone support. This agent cannot limit */
			/* results to only current zone. */

			SET_MSG_RESULT(result, zbx_strdup(NULL, "The fifth parameter value \"current\" cannot be used"
					" with agent running on a Solaris version with zone support, but compiled on"
					" a Solaris version without zone support. Consider using \"all\" or install"
					" agent with Solaris zone support."));
			ret = SYSINFO_RET_FAIL;
			goto clean;
		}
#endif
	}
	else if (0 == strcmp(zone_parameter, "all"))
	{
#ifdef HAVE_ZONE_H
		zoneflag = ZBX_PROCSTAT_FLAGS_ZONE_ALL;
#endif
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}
#ifdef HAVE_ZONE_H
	zoneid = getzoneid();
#endif

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	if (NULL == (dir = opendir("/proc")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc: %s", zbx_strerror(errno)));
		ret = SYSINFO_RET_FAIL;
		goto clean;
	}

	while (NULL != (entries = readdir(dir)))
	{
		zbx_sysinfo_proc_t	proc;
		psinfo_t		psinfo;	/* In the correct procfs.h, the structure name is psinfo_t */

		if (SUCCEED != proc_get_process_info(entries->d_name, proc_props, &proc, &psinfo))
			continue;

		if (SUCCEED == proc_match_props(&proc, usrinfo, procname, proccomm_rxp))
		{
#ifdef HAVE_ZONE_H
			if (SUCCEED != proc_match_zone(&proc, zoneflag, zoneid))
			{
				zbx_sysinfo_proc_clear(&proc);
				continue;
			}

#endif
			if (SUCCEED != check_procstate(&psinfo, zbx_proc_stat))
			{
				zbx_sysinfo_proc_clear(&proc);
				continue;
			}

			proccount++;
		}

		zbx_sysinfo_proc_clear(&proc);
	}

	if (0 != closedir(dir))
		zabbix_log(LOG_LEVEL_WARNING, "%s(): cannot close /proc: %s", __func__, zbx_strerror(errno));
out:
	SET_UI64_RESULT(result, proccount);
clean:
	if (NULL != proccomm_rxp)
		zbx_regexp_free(proccomm_rxp);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads process cpu utilization values from /proc/[pid]/usage file  *
 *                                                                            *
 * Parameters: procutil - [IN/OUT] process cpu utilization data               *
 *                                                                            *
 * Return value: SUCCEED - process cpu utilization data was read              *
 *                         successfully                                       *
 *               <0      - otherwise, -errno code is returned                 *
 *                                                                            *
 * Comments: We use /proc/[pid]/usage since /proc/[pid]/status contains       *
 *           sensitive information and by default can only be read by the     *
 *           owner or privileged user.                                        *
 *                                                                            *
 *           In addition to user and system-call CPU time the                 *
 *           /proc/[pid]/usage also contains CPU time spent in trap context   *
 *           Currently trap CPU time is not taken into account.               *
 *                                                                            *
 *           prstat(1) skips processes 0 (sched), 2 (pageout) and 3 (fsflush) *
 *           however we take them into account.                               *
 *                                                                            *
 ******************************************************************************/
static int	proc_read_cpu_util(zbx_procstat_util_t *procutil)
{
	int		fd, n;
	char		tmp[MAX_STRING_LEN];
	psinfo_t	psinfo;
	prusage_t	prusage;

	zbx_snprintf(tmp, sizeof(tmp), "/proc/%d/psinfo", (int)procutil->pid);

	if (-1 == (fd = open(tmp, O_RDONLY)))
		return -errno;

	n = read(fd, &psinfo, sizeof(psinfo));
	close(fd);

	if (-1 == n)
		return -errno;

	procutil->starttime = psinfo.pr_start.tv_sec;

	zbx_snprintf(tmp, sizeof(tmp), "/proc/%d/usage", (int)procutil->pid);

	if (-1 == (fd = open(tmp, O_RDONLY)))
		return -errno;

	n = read(fd, &prusage, sizeof(prusage));
	close(fd);

	if (-1 == n)
		return -errno;

	/* convert cpu utilization time to clock ticks */
	procutil->utime = ((zbx_uint64_t)prusage.pr_utime.tv_sec * 1e9 + prusage.pr_utime.tv_nsec) *
			sysconf(_SC_CLK_TCK) / 1e9;

	procutil->stime = ((zbx_uint64_t)prusage.pr_stime.tv_sec * 1e9 + prusage.pr_stime.tv_nsec) *
			sysconf(_SC_CLK_TCK) / 1e9;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets process cpu utilization data                                 *
 *                                                                            *
 * Parameters: procs     - [IN/OUT] array of process utilization data         *
 *             procs_num - [IN] number of items in procs array                *
 *                                                                            *
 ******************************************************************************/
void	zbx_proc_get_process_stats(zbx_procstat_util_t *procs, int procs_num)
{
	zabbix_log(LOG_LEVEL_TRACE, "In %s() procs_num:%d", __func__, procs_num);

	for (int i = 0; i < procs_num; i++)
		procs[i].error = proc_read_cpu_util(&procs[i]);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets system processes                                             *
 *                                                                            *
 * Parameters: processes - [OUT] system processes                             *
 *             flags     - [IN] flags specifying process properties that must *
 *                              be returned                                   *
 *                                                                            *
 * Return value: SUCCEED - system processes were retrieved successfully       *
 *               FAIL    - failed to open /proc directory                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_proc_get_processes(zbx_vector_ptr_t *processes, unsigned int flags)
{
	DIR			*dir;
	struct dirent		*entries;
	int			ret = FAIL;
	zbx_sysinfo_proc_t	*proc = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	if (NULL == (dir = opendir("/proc")))
		goto out;

	while (NULL != (entries = readdir(dir)))
	{
		if (NULL == proc)
		{
			proc = (zbx_sysinfo_proc_t *)zbx_malloc(NULL, sizeof(zbx_sysinfo_proc_t));
			memset(proc, 0, sizeof(zbx_sysinfo_proc_t));
		}

		if (SUCCEED == proc_get_process_info(entries->d_name, flags, proc, NULL))
		{
			zbx_vector_ptr_append(processes, proc);
			proc = NULL;
		}
	}
	closedir(dir);

	zbx_free(proc);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): %s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees process vector read by zbx_proc_get_processes function      *
 *                                                                            *
 * Parameters: processes - [IN/OUT] process vector to free                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_proc_free_processes(zbx_vector_ptr_t *processes)
{
	zbx_vector_ptr_clear_ext(processes, (zbx_mem_free_func_t)zbx_sysinfo_proc_free);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets pids matching specified process name, user name and command  *
 *          line                                                              *
 *                                                                            *
 * Parameters: processes   - [IN]                                             *
 *             procname    - [IN] NULL - all                                  *
 *             username    - [IN] ...                                         *
 *             cmdline     - [IN] ...                                         *
 *             flags       - [IN]                                             *
 *             pids        - [OUT] vector of matching pids                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_proc_get_matching_pids(const zbx_vector_ptr_t *processes, const char *procname, const char *username,
		const char *cmdline, zbx_uint64_t flags, zbx_vector_uint64_t *pids)
{
	struct passwd		*usrinfo;
	zbx_sysinfo_proc_t	*proc;
#ifdef HAVE_ZONE_H
	zoneid_t		zoneid;
#endif
	zbx_regexp_t		*proccomm_rxp = NULL;
	char			*rxp_error = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() procname:%s username:%s cmdline:%s zone:%llu", __func__,
			ZBX_NULL2EMPTY_STR(procname), ZBX_NULL2EMPTY_STR(username), ZBX_NULL2EMPTY_STR(cmdline),
			(long long unsigned)flags);

	if (NULL != username)
	{
		/* in the case of invalid user there are no matching processes, return empty vector */
		if (NULL == (usrinfo = getpwnam(username)))
			goto out;
	}
	else
		usrinfo = NULL;

	if (NULL != cmdline && SUCCEED != zbx_regexp_compile(cmdline, &proccomm_rxp, &rxp_error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() Invalid regular expression: %s", __func__, rxp_error);
		zbx_free(rxp_error);
		goto out;
	}

#ifdef HAVE_ZONE_H
	zoneid = getzoneid();
#endif

	for (int i = 0; i < processes->values_num; i++)
	{
		proc = (zbx_sysinfo_proc_t *)processes->values[i];

		if (SUCCEED != proc_match_props(proc, usrinfo, procname, proccomm_rxp))
			continue;

#ifdef HAVE_ZONE_H
		if (SUCCEED != proc_match_zone(proc, flags, zoneid))
			continue;
#endif

		zbx_vector_uint64_append(pids, (zbx_uint64_t)proc->pid);
	}
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

int	proc_cpu_util(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*procname, *username, *cmdline, *tmp, *flags;
	char		*errmsg = NULL;
	int		period, type;
	double		value;
	zbx_uint64_t	zoneflag;
	zbx_timespec_t	ts_timeout, ts;

	/* proc.cpu.util[<procname>,<username>,(user|system),<cmdline>,(avg1|avg5|avg15),(current|all)] */
	if (6 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

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
	else if (0 == strcmp(tmp, "avg5"))
	{
		period = SEC_PER_MIN * 5;
	}
	else if (0 == strcmp(tmp, "avg15"))
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
#ifndef HAVE_ZONE_H
		if (SUCCEED == zbx_detect_zone_support())
		{
			/* Agent has been compiled on Solaris 9 or earlier where zones are not supported */
			/* but now it is running on a system with zone support. This agent cannot limit */
			/* results to only current zone. */

			SET_MSG_RESULT(result, zbx_strdup(NULL, "The sixth parameter value \"current\" cannot be used"
					" with agent running on a Solaris version with zone support, but compiled on"
					" a Solaris version without zone support. Consider using \"all\" or install"
					" agent with Solaris zone support."));
			return SYSINFO_RET_FAIL;
		}

		/* zones are not supported, the agent can accept 6th parameter with default value "current" */
#endif
		zoneflag = ZBX_PROCSTAT_FLAGS_ZONE_CURRENT;
	}
	else if (0 == strcmp(flags, "all"))
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

	zbx_timespec(&ts_timeout);
	ts_timeout.sec += sysinfo_get_config_timeout();

	while (SUCCEED != zbx_procstat_get_util(procname, username, cmdline, zoneflag, period, type, &value, &errmsg))
	{
		/* zbx_procstat_get_* functions will return FAIL when either a collection   */
		/* error was registered or if less than 2 data samples were collected.      */
		/* In the first case the errmsg will contain error message.                 */
		if (NULL != errmsg)
		{
			SET_MSG_RESULT(result, errmsg);
			return SYSINFO_RET_FAIL;
		}

		zbx_timespec(&ts);

		if (0 > zbx_timespec_compare(&ts_timeout, &ts))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Timeout while waiting for collector data."));
			return SYSINFO_RET_FAIL;
		}

		sleep(1);
	}

	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
}
