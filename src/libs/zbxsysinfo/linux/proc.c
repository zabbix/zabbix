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

#include "proc.h"

#include "common.h"
#include "sysinfo.h"
#include "zbxregexp.h"
#include "log.h"
#include "stats.h"

extern int	CONFIG_TIMEOUT;

typedef struct
{
	pid_t		pid;
	uid_t		uid;

	char		*name;

	/* the process name taken from the 0th argument */
	char		*name_arg0;

	/* process command line in format <arg0> <arg1> ... <argN>\0 */
	char		*cmdline;
}
zbx_sysinfo_proc_t;

/******************************************************************************
 *                                                                            *
 * Purpose: frees process data structure                                      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_sysinfo_proc_free(zbx_sysinfo_proc_t *proc)
{
	zbx_free(proc->name);
	zbx_free(proc->name_arg0);
	zbx_free(proc->cmdline);

	zbx_free(proc);
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

static int	cmp_status(FILE *f_stat, const char *procname)
{
	char	tmp[MAX_STRING_LEN];

	rewind(f_stat);

	while (NULL != fgets(tmp, (int)sizeof(tmp), f_stat))
	{
		if (0 != strncmp(tmp, "Name:\t", 6))
			continue;

		zbx_rtrim(tmp + 6, "\n");
		if (0 == strcmp(tmp + 6, procname))
			return SUCCEED;
		break;
	}

	return FAIL;
}

static int	check_procname(FILE *f_cmd, FILE *f_stat, const char *procname)
{
	char	*tmp = NULL, *p;
	size_t	l;
	int	ret = SUCCEED;

	if (NULL == procname || '\0' == *procname)
		return SUCCEED;

	/* process name in /proc/[pid]/status contains limited number of characters */
	if (SUCCEED == cmp_status(f_stat, procname))
		return SUCCEED;

	if (SUCCEED == get_cmdline(f_cmd, &tmp, &l))
	{
		if (NULL == (p = strrchr(tmp, '/')))
			p = tmp;
		else
			p++;

		if (0 == strcmp(p, procname))
			goto clean;
	}

	ret = FAIL;
clean:
	zbx_free(tmp);

	return ret;
}

static int	check_user(FILE *f_stat, struct passwd *usrinfo)
{
	char	tmp[MAX_STRING_LEN], *p, *p1;
	uid_t	uid;

	if (NULL == usrinfo)
		return SUCCEED;

	rewind(f_stat);

	while (NULL != fgets(tmp, (int)sizeof(tmp), f_stat))
	{
		if (0 != strncmp(tmp, "Uid:\t", 5))
			continue;

		p = tmp + 5;

		if (NULL != (p1 = strchr(p, '\t')))
			*p1 = '\0';

		uid = (uid_t)atoi(p);

		if (usrinfo->pw_uid == uid)
			return SUCCEED;
		break;
	}

	return FAIL;
}

static int	check_proccomm(FILE *f_cmd, const char *proccomm)
{
	char	*tmp = NULL;
	size_t	i, l;
	int	ret = SUCCEED;

	if (NULL == proccomm || '\0' == *proccomm)
		return SUCCEED;

	if (SUCCEED == get_cmdline(f_cmd, &tmp, &l))
	{
		for (i = 0, l -= 2; i < l; i++)
			if ('\0' == tmp[i])
				tmp[i] = ' ';

		if (NULL != zbx_regexp_match(tmp, proccomm, NULL))
			goto clean;
	}

	ret = FAIL;
clean:
	zbx_free(tmp);

	return ret;
}

static int	check_procstate(FILE *f_stat, int zbx_proc_stat)
{
	char	tmp[MAX_STRING_LEN], *p;

	if (ZBX_PROC_STAT_ALL == zbx_proc_stat)
		return SUCCEED;

	rewind(f_stat);

	while (NULL != fgets(tmp, (int)sizeof(tmp), f_stat))
	{
		if (0 != strncmp(tmp, "State:\t", 7))
			continue;

		p = tmp + 7;

		switch (zbx_proc_stat)
		{
			case ZBX_PROC_STAT_RUN:
				return ('R' == *p) ? SUCCEED : FAIL;
			case ZBX_PROC_STAT_SLEEP:
				return ('S' == *p) ? SUCCEED : FAIL;
			case ZBX_PROC_STAT_ZOMB:
				return ('Z' == *p) ? SUCCEED : FAIL;
			case ZBX_PROC_STAT_DISK:
				return ('D' == *p) ? SUCCEED : FAIL;
			case ZBX_PROC_STAT_TRACE:
				return ('T' == *p) ? SUCCEED : FAIL;
			default:
				return FAIL;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Read amount of memory in bytes from a string in /proc file.       *
 *          For example, reading "VmSize:   176712 kB" from /proc/1/status    *
 *          will produce a result 176712*1024 = 180953088 bytes               *
 *                                                                            *
 * Parameters:                                                                *
 *     f     - [IN] file to read from                                         *
 *     label - [IN] label to look for, e.g. "VmData:\t"                       *
 *     guard - [IN] label before which to stop, e.g. "VmStk:\t" (optional)    *
 *     bytes - [OUT] result in bytes                                          *
 *                                                                            *
 * Return value: SUCCEED - successful reading,                                *
 *               NOTSUPPORTED - the search string was not found. For example, *
 *                              /proc/NNN/status files for kernel threads do  *
 *                              not contain "VmSize:" string.                 *
 *               FAIL - the search string was found but could not be parsed.  *
 *                                                                            *
 ******************************************************************************/
int	byte_value_from_proc_file(FILE *f, const char *label, const char *guard, zbx_uint64_t *bytes)
{
	char	buf[MAX_STRING_LEN], *p_value, *p_unit;
	size_t	label_len, guard_len;
	long	pos = 0;
	int	ret = NOTSUPPORTED;

	label_len = strlen(label);
	p_value = buf + label_len;

	if (NULL != guard)
	{
		guard_len = strlen(guard);
		if (0 > (pos = ftell(f)))
			return FAIL;
	}

	while (NULL != fgets(buf, (int)sizeof(buf), f))
	{
		if (NULL != guard)
		{
			if (0 == strncmp(buf, guard, guard_len))
			{
				if (0 != fseek(f, pos, SEEK_SET))
					ret = FAIL;
				break;
			}

			if (0 > (pos = ftell(f)))
			{
				ret = FAIL;
				break;
			}
		}

		if (0 != strncmp(buf, label, label_len))
			continue;

		if (NULL == (p_unit = strrchr(p_value, ' ')))
		{
			ret = FAIL;
			break;
		}

		*p_unit++ = '\0';

		while (' ' == *p_value)
			p_value++;

		if (FAIL == is_uint64(p_value, bytes))
		{
			ret = FAIL;
			break;
		}

		zbx_rtrim(p_unit, "\n");

		if (0 == strcasecmp(p_unit, "kB"))
			*bytes <<= 10;
		else if (0 == strcasecmp(p_unit, "mB"))
			*bytes <<= 20;
		else if (0 == strcasecmp(p_unit, "GB"))
			*bytes <<= 30;
		else if (0 == strcasecmp(p_unit, "TB"))
			*bytes <<= 40;

		ret = SUCCEED;
		break;
	}

	return ret;
}

static int	get_total_memory(zbx_uint64_t *total_memory)
{
	FILE	*f;
	int	ret = FAIL;

	if (NULL != (f = fopen("/proc/meminfo", "r")))
	{
		ret = byte_value_from_proc_file(f, "MemTotal:", NULL, total_memory);
		zbx_fclose(f);
	}

	return ret;
}

int	PROC_MEM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#define ZBX_SIZE	0
#define ZBX_RSS		1
#define ZBX_VSIZE	2
#define ZBX_PMEM	3
#define ZBX_VMPEAK	4
#define ZBX_VMSWAP	5
#define ZBX_VMLIB	6
#define ZBX_VMLCK	7
#define ZBX_VMPIN	8
#define ZBX_VMHWM	9
#define ZBX_VMDATA	10
#define ZBX_VMSTK	11
#define ZBX_VMEXE	12
#define ZBX_VMPTE	13

	char		tmp[MAX_STRING_LEN], *procname, *proccomm, *param;
	DIR		*dir;
	struct dirent	*entries;
	struct passwd	*usrinfo;
	FILE		*f_cmd = NULL, *f_stat = NULL;
	zbx_uint64_t	mem_size = 0, byte_value = 0, total_memory;
	double		pct_size = 0.0, pct_value = 0.0;
	int		do_task, res, proccount = 0, invalid_user = 0, invalid_read = 0;
	int		mem_type_tried = 0, mem_type_code;
	char		*mem_type = NULL;
	const char	*mem_type_search = NULL;

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
	mem_type = get_rparam(request, 4);

	/* Comments for process memory types were compiled from: */
	/*    man 5 proc */
	/*    https://www.kernel.org/doc/Documentation/filesystems/proc.txt */
	/*    Himanshu Arora, Linux Processes explained - Part II, http://mylinuxbook.com/linux-processes-part2/ */

	if (NULL == mem_type || '\0' == *mem_type || 0 == strcmp(mem_type, "vsize"))
	{
		mem_type_code = ZBX_VSIZE;		/* current virtual memory size (total program size) */
		mem_type_search = "VmSize:\t";
	}
	else if (0 == strcmp(mem_type, "rss"))
	{
		mem_type_code = ZBX_RSS;		/* current resident set size (size of memory portions) */
		mem_type_search = "VmRSS:\t";
	}
	else if (0 == strcmp(mem_type, "pmem"))
	{
		mem_type_code = ZBX_PMEM;		/* percentage of real memory used by process */
	}
	else if (0 == strcmp(mem_type, "size"))
	{
		mem_type_code = ZBX_SIZE;		/* size of process (code + data + stack) */
	}
	else if (0 == strcmp(mem_type, "peak"))
	{
		mem_type_code = ZBX_VMPEAK;		/* peak virtual memory size */
		mem_type_search = "VmPeak:\t";
	}
	else if (0 == strcmp(mem_type, "swap"))
	{
		mem_type_code = ZBX_VMSWAP;		/* size of swap space used */
		mem_type_search = "VmSwap:\t";
	}
	else if (0 == strcmp(mem_type, "lib"))
	{
		mem_type_code = ZBX_VMLIB;		/* size of shared libraries */
		mem_type_search = "VmLib:\t";
	}
	else if (0 == strcmp(mem_type, "lck"))
	{
		mem_type_code = ZBX_VMLCK;		/* size of locked memory */
		mem_type_search = "VmLck:\t";
	}
	else if (0 == strcmp(mem_type, "pin"))
	{
		mem_type_code = ZBX_VMPIN;		/* size of pinned pages, they are never swappable */
		mem_type_search = "VmPin:\t";
	}
	else if (0 == strcmp(mem_type, "hwm"))
	{
		mem_type_code = ZBX_VMHWM;		/* peak resident set size ("high water mark") */
		mem_type_search = "VmHWM:\t";
	}
	else if (0 == strcmp(mem_type, "data"))
	{
		mem_type_code = ZBX_VMDATA;		/* size of data segment */
		mem_type_search = "VmData:\t";
	}
	else if (0 == strcmp(mem_type, "stk"))
	{
		mem_type_code = ZBX_VMSTK;		/* size of stack segment */
		mem_type_search = "VmStk:\t";
	}
	else if (0 == strcmp(mem_type, "exe"))
	{
		mem_type_code = ZBX_VMEXE;		/* size of text (code) segment */
		mem_type_search = "VmExe:\t";
	}
	else if (0 == strcmp(mem_type, "pte"))
	{
		mem_type_code = ZBX_VMPTE;		/* size of page table entries */
		mem_type_search = "VmPTE:\t";
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (1 == invalid_user)	/* handle 0 for non-existent user after all parameters have been parsed and validated */
		goto out;

	if (ZBX_PMEM == mem_type_code)
	{
		if (SUCCEED != get_total_memory(&total_memory))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain amount of total memory: %s",
					zbx_strerror(errno)));
			return SYSINFO_RET_FAIL;
		}

		if (0 == total_memory)	/* this should never happen but anyway - avoid crash due to dividing by 0 */
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Total memory reported is 0."));
			return SYSINFO_RET_FAIL;
		}
	}

	if (NULL == (dir = opendir("/proc")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	while (NULL != (entries = readdir(dir)))
	{
		zbx_fclose(f_cmd);
		zbx_fclose(f_stat);

		if (0 == atoi(entries->d_name))
			continue;

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/cmdline", entries->d_name);

		if (NULL == (f_cmd = fopen(tmp, "r")))
			continue;

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/status", entries->d_name);

		if (NULL == (f_stat = fopen(tmp, "r")))
			continue;

		if (FAIL == check_procname(f_cmd, f_stat, procname))
			continue;

		if (FAIL == check_user(f_stat, usrinfo))
			continue;

		if (FAIL == check_proccomm(f_cmd, proccomm))
			continue;

		rewind(f_stat);

		if (0 == mem_type_tried)
			mem_type_tried = 1;

		switch (mem_type_code)
		{
			case ZBX_VSIZE:
			case ZBX_RSS:
			case ZBX_VMPEAK:
			case ZBX_VMSWAP:
			case ZBX_VMLIB:
			case ZBX_VMLCK:
			case ZBX_VMPIN:
			case ZBX_VMHWM:
			case ZBX_VMDATA:
			case ZBX_VMSTK:
			case ZBX_VMEXE:
			case ZBX_VMPTE:
				res = byte_value_from_proc_file(f_stat, mem_type_search, NULL, &byte_value);

				if (NOTSUPPORTED == res)
					continue;

				if (FAIL == res)
				{
					invalid_read = 1;
					goto clean;
				}
				break;
			case ZBX_SIZE:
				{
					zbx_uint64_t	m;

					/* VmData, VmStk and VmExe follow in /proc/PID/status file in that order. */
					/* Therefore we do not rewind f_stat between calls. */

					mem_type_search = "VmData:\t";

					if (SUCCEED == (res = byte_value_from_proc_file(f_stat, mem_type_search, NULL,
							&byte_value)))
					{
						mem_type_search = "VmStk:\t";

						if (SUCCEED == (res = byte_value_from_proc_file(f_stat, mem_type_search,
								NULL, &m)))
						{
							byte_value += m;
							mem_type_search = "VmExe:\t";

							if (SUCCEED == (res = byte_value_from_proc_file(f_stat,
									mem_type_search, NULL, &m)))
							{
								byte_value += m;
							}
						}
					}

					if (SUCCEED != res)
					{
						if (NOTSUPPORTED == res)
						{
							/* NOTSUPPORTED - at least one of data strings not found in */
							/* the /proc/PID/status file */
							continue;
						}
						else	/* FAIL */
						{
							invalid_read = 1;
							goto clean;
						}
					}
				}
				break;
			case ZBX_PMEM:
				mem_type_search = "VmRSS:\t";
				res = byte_value_from_proc_file(f_stat, mem_type_search, NULL, &byte_value);

				if (SUCCEED == res)
				{
					pct_value = ((double)byte_value / (double)total_memory) * 100.0;
				}
				else if (NOTSUPPORTED == res)
				{
					continue;
				}
				else	/* FAIL */
				{
					invalid_read = 1;
					goto clean;
				}
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
clean:
	zbx_fclose(f_cmd);
	zbx_fclose(f_stat);
	closedir(dir);

	if ((0 == proccount && 0 != mem_type_tried) || 0 != invalid_read)
	{
		char	*s;

		s = zbx_strdup(NULL, mem_type_search);
		zbx_rtrim(s, ":\t");
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot get amount of \"%s\" memory.", s));
		zbx_free(s);
		return SYSINFO_RET_FAIL;
	}
out:
	if (ZBX_PMEM != mem_type_code)
	{
		if (ZBX_DO_AVG == do_task)
			SET_DBL_RESULT(result, 0 == proccount ? 0 : (double)mem_size / (double)proccount);
		else
			SET_UI64_RESULT(result, mem_size);
	}
	else
	{
		if (ZBX_DO_AVG == do_task)
			SET_DBL_RESULT(result, 0 == proccount ? 0 : pct_size / (double)proccount);
		else
			SET_DBL_RESULT(result, pct_size);
	}

	return SYSINFO_RET_OK;

#undef ZBX_SIZE
#undef ZBX_RSS
#undef ZBX_VSIZE
#undef ZBX_PMEM
#undef ZBX_VMPEAK
#undef ZBX_VMSWAP
#undef ZBX_VMLIB
#undef ZBX_VMLCK
#undef ZBX_VMPIN
#undef ZBX_VMHWM
#undef ZBX_VMDATA
#undef ZBX_VMSTK
#undef ZBX_VMEXE
#undef ZBX_VMPTE
}

int	PROC_NUM(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		tmp[MAX_STRING_LEN], *procname, *proccomm, *param;
	DIR		*dir;
	struct dirent	*entries;
	struct passwd	*usrinfo;
	FILE		*f_cmd = NULL, *f_stat = NULL;
	int		proccount = 0, invalid_user = 0, zbx_proc_stat;

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
	else if (0 == strcmp(param, "disk"))
		zbx_proc_stat = ZBX_PROC_STAT_DISK;
	else if (0 == strcmp(param, "trace"))
		zbx_proc_stat = ZBX_PROC_STAT_TRACE;
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
		zbx_fclose(f_cmd);
		zbx_fclose(f_stat);

		if (0 == atoi(entries->d_name))
			continue;

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/cmdline", entries->d_name);

		if (NULL == (f_cmd = fopen(tmp, "r")))
			continue;

		zbx_snprintf(tmp, sizeof(tmp), "/proc/%s/status", entries->d_name);

		if (NULL == (f_stat = fopen(tmp, "r")))
			continue;

		if (FAIL == check_procname(f_cmd, f_stat, procname))
			continue;

		if (FAIL == check_user(f_stat, usrinfo))
			continue;

		if (FAIL == check_proccomm(f_cmd, proccomm))
			continue;

		if (FAIL == check_procstate(f_stat, zbx_proc_stat))
			continue;

		proccount++;
	}
	zbx_fclose(f_cmd);
	zbx_fclose(f_stat);
	closedir(dir);
out:
	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns process name                                              *
 *                                                                            *
 * Parameters: pid -      [IN] the process identifier                         *
 *             procname - [OUT] the process name                              *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 * Comments: The process name is allocated by this function and must be freed *
 *           by the caller.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	proc_get_process_name(pid_t pid, char **procname)
{
	int	n, fd;
	char	tmp[MAX_STRING_LEN], *pend, *pstart;

	zbx_snprintf(tmp, sizeof(tmp), "/proc/%d/stat", (int)pid);

	if (-1 == (fd = open(tmp, O_RDONLY)))
		return FAIL;

	n = read(fd, tmp, sizeof(tmp));
	close(fd);

	if (-1 == n)
		return FAIL;

	for (pend = tmp + n - 1; ')' != *pend && pend > tmp; pend--)
		;

	*pend = '\0';

	if (NULL == (pstart = strchr(tmp, '(')))
		return FAIL;

	*procname = zbx_strdup(NULL, pstart + 1);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns process command line                                      *
 *                                                                            *
 * Parameters: pid            - [IN] the process identifier                   *
 *             cmdline        - [OUT] the process command line                *
 *             cmdline_nbytes - [OUT] the number of bytes in the command line *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 * Comments: The command line is allocated by this function and must be freed *
 *           by the caller.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	proc_get_process_cmdline(pid_t pid, char **cmdline, size_t *cmdline_nbytes)
{
	char	tmp[MAX_STRING_LEN];
	int	fd, n;
	size_t	cmdline_alloc = ZBX_KIBIBYTE;

	*cmdline_nbytes = 0;
	zbx_snprintf(tmp, sizeof(tmp), "/proc/%d/cmdline", (int)pid);

	if (-1 == (fd = open(tmp, O_RDONLY)))
		return FAIL;

	*cmdline = (char *)zbx_malloc(NULL, cmdline_alloc);

	while (0 < (n = read(fd, *cmdline + *cmdline_nbytes, cmdline_alloc - *cmdline_nbytes)))
	{
		*cmdline_nbytes += n;

		if (*cmdline_nbytes == cmdline_alloc)
		{
			cmdline_alloc *= 2;
			*cmdline = (char *)zbx_realloc(*cmdline, cmdline_alloc);
		}
	}

	close(fd);

	if (0 < *cmdline_nbytes)
	{
		/* add terminating NUL if it is missing due to processes setting their titles or other reasons */
		if ('\0' != (*cmdline)[*cmdline_nbytes - 1])
		{
			if (*cmdline_nbytes == cmdline_alloc)
			{
				cmdline_alloc += 1;
				*cmdline = (char *)zbx_realloc(*cmdline, cmdline_alloc);
			}

			(*cmdline)[*cmdline_nbytes] = '\0';
			*cmdline_nbytes += 1;
		}
	}
	else
	{
		zbx_free(*cmdline);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns process user identifier                                   *
 *                                                                            *
 * Parameters: pid - [IN] the process identifier                              *
 *             uid - [OUT] the user identifier                                *
 *                                                                            *
 * Return value: SUCCEED                                                      *
 *               FAIL                                                         *
 *                                                                            *
 ******************************************************************************/
static int	proc_get_process_uid(pid_t pid, uid_t *uid)
{
	char		tmp[MAX_STRING_LEN];
	zbx_stat_t	st;

	zbx_snprintf(tmp, sizeof(tmp), "/proc/%d", (int)pid);

	if (0 != zbx_stat(tmp, &st))
		return FAIL;

	*uid = st.st_uid;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: read 64 bit unsigned space or zero character terminated integer   *
 *          from a text string                                                *
 *                                                                            *
 * Parameters: ptr   - [IN] the text string                                   *
 *             value - [OUT] the parsed value                                 *
 *                                                                            *
 * Return value: The length of the parsed text or FAIL if parsing failed.     *
 *                                                                            *
 ******************************************************************************/
static int	proc_read_value(const char *ptr, zbx_uint64_t *value)
{
	const char	*start = ptr;
	int		len;

	while (' ' != *ptr && '\0' != *ptr)
		ptr++;

	len = ptr - start;

	if (SUCCEED == is_uint64_n(start, len, value))
		return len;

	return FAIL;
}

/******************************************************************************
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
	int	n, offset, fd, ret = SUCCEED;
	char	tmp[MAX_STRING_LEN], *ptr;

	zbx_snprintf(tmp, sizeof(tmp), "/proc/%d/stat", (int)procutil->pid);

	if (-1 == (fd = open(tmp, O_RDONLY)))
		return -errno;

	if (-1 == (n = read(fd, tmp, sizeof(tmp) - 1)))
	{
		ret = -errno;
		goto out;
	}

	tmp[n] = '\0';

	/* skip to the end of process name to avoid dealing with possible spaces in process name */
	if (NULL == (ptr = strrchr(tmp, ')')))
	{
		ret = -EFAULT;
		goto out;
	}

	n = 0;

	while ('\0' != *ptr)
	{
		if (' ' != *ptr++)
			continue;

		switch (++n)
		{
			case 12:
				if (FAIL == (offset = proc_read_value(ptr, &procutil->utime)))
				{
					ret = -EINVAL;
					goto out;
				}
				ptr += offset;

				break;
			case 13:
				if (FAIL == (offset = proc_read_value(ptr, &procutil->stime)))
				{
					ret = -EINVAL;
					goto out;
				}
				ptr += offset;

				break;
			case 20:
				if (FAIL == proc_read_value(ptr, &procutil->starttime))
				{
					ret = -EINVAL;
					goto out;
				}

				goto out;
		}
	}

	ret = -ENODATA;
out:
	close(fd);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the process name matches filter                         *
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
 * Purpose: checks if the process user matches filter                         *
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
 * Purpose: checks if the process command line matches filter                 *
 *                                                                            *
 ******************************************************************************/
static int	proc_match_cmdline(const zbx_sysinfo_proc_t *proc, const char *cmdline)
{
	if (NULL == cmdline)
		return SUCCEED;

	if (NULL != proc->cmdline && NULL != zbx_regexp_match(proc->cmdline, cmdline, NULL))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get process cpu utilization data                                  *
 *                                                                            *
 * Parameters: procs     - [IN/OUT] an array of process utilization data      *
 *             procs_num - [IN] the number of items in procs array            *
 *                                                                            *
 ******************************************************************************/
void	zbx_proc_get_process_stats(zbx_procstat_util_t *procs, int procs_num)
{
	int	i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() procs_num:%d", __func__, procs_num);

	for (i = 0; i < procs_num; i++)
		procs[i].error = proc_read_cpu_util(&procs[i]);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create process object with the specified properties               *
 *                                                                            *
 * Parameters: pid   - [IN] the process identifier                            *
 *             flags - [IN] the flags specifying properties to set            *
 *                                                                            *
 * Return value: The created process object or NULL if property reading       *
 *               failed.                                                      *
 *                                                                            *
 ******************************************************************************/
static zbx_sysinfo_proc_t	*proc_create(int pid, unsigned int flags)
{
	char			*procname = NULL, *cmdline = NULL, *name_arg0 = NULL;
	uid_t			uid = (uid_t)-1;
	zbx_sysinfo_proc_t	*proc = NULL;
	int			ret = FAIL;
	size_t			cmdline_nbytes;

	if (0 != (flags & ZBX_SYSINFO_PROC_USER) && SUCCEED != proc_get_process_uid(pid, &uid))
		goto out;

	if (0 != (flags & (ZBX_SYSINFO_PROC_CMDLINE | ZBX_SYSINFO_PROC_NAME)) &&
			SUCCEED != proc_get_process_cmdline(pid, &cmdline, &cmdline_nbytes))
	{
		goto out;
	}

	if (0 != (flags & ZBX_SYSINFO_PROC_NAME) && SUCCEED != proc_get_process_name(pid, &procname))
		goto out;

	if (NULL != cmdline)
	{
		char		*ptr;
		unsigned int	i;

		if (0 != (flags & ZBX_SYSINFO_PROC_NAME))
		{
			if (NULL == (ptr = strrchr(cmdline, '/')))
				name_arg0 = zbx_strdup(NULL, cmdline);
			else
				name_arg0 = zbx_strdup(NULL, ptr + 1);
		}

		/* according to proc(5) the arguments are separated by '\0' */
		for (i = 0; i < cmdline_nbytes - 1; i++)
			if ('\0' == cmdline[i])
				cmdline[i] = ' ';
	}

	ret = SUCCEED;
out:
	if (SUCCEED == ret)
	{
		proc = (zbx_sysinfo_proc_t *)zbx_malloc(NULL, sizeof(zbx_sysinfo_proc_t));

		proc->pid = pid;
		proc->uid = uid;
		proc->name = procname;
		proc->cmdline = cmdline;
		proc->name_arg0 = name_arg0;
	}
	else
	{
		zbx_free(procname);
		zbx_free(cmdline);
		zbx_free(name_arg0);
	}

	return proc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get system processes                                              *
 *                                                                            *
 * Parameters: processes - [OUT] the system processes                         *
 *             flags     - [IN] the flags specifying the process properties   *
 *                              that must be returned                         *
 *                                                                            *
 * Return value: SUCCEED - the system processes were retrieved successfully   *
 *               FAIL    - failed to open /proc directory                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_proc_get_processes(zbx_vector_ptr_t *processes, unsigned int flags)
{
	DIR			*dir;
	struct dirent		*entries;
	int			ret = FAIL, pid;
	zbx_sysinfo_proc_t	*proc;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	if (NULL == (dir = opendir("/proc")))
		goto out;

	while (NULL != (entries = readdir(dir)))
	{
		/* skip entries not containing pids */
		if (FAIL == is_uint32(entries->d_name, &pid))
			continue;

		if (NULL == (proc = proc_create(pid, flags)))
			continue;

		zbx_vector_ptr_append(processes, proc);
	}

	closedir(dir);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s(): %s, processes:%d", __func__, zbx_result_string(ret),
			processes->values_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees process vector read by zbx_proc_get_processes function      *
 *                                                                            *
 * Parameters: processes - [IN/OUT] the process vector to free                *
 *                                                                            *
 ******************************************************************************/
void	zbx_proc_free_processes(zbx_vector_ptr_t *processes)
{
	zbx_vector_ptr_clear_ext(processes, (zbx_mem_free_func_t)zbx_sysinfo_proc_free);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get pids matching the specified process name, user name and       *
 *          command line                                                      *
 *                                                                            *
 * Parameters: processes   - [IN] the list of system processes                *
 *             procname    - [IN] the process name, NULL - all                *
 *             username    - [IN] the user name, NULL - all                   *
 *             cmdline     - [IN] the command line, NULL - all                *
 *             pids        - [OUT] the vector of matching pids                *
 *                                                                            *
 * Return value: SUCCEED   - the pids were read successfully                  *
 *               -errno    - failed to read pids                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_proc_get_matching_pids(const zbx_vector_ptr_t *processes, const char *procname, const char *username,
		const char *cmdline, zbx_uint64_t flags, zbx_vector_uint64_t *pids)
{
	struct passwd		*usrinfo;
	int			i;
	zbx_sysinfo_proc_t	*proc;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() procname:%s username:%s cmdline:%s flags:" ZBX_FS_UI64, __func__,
			ZBX_NULL2EMPTY_STR(procname), ZBX_NULL2EMPTY_STR(username), ZBX_NULL2EMPTY_STR(cmdline), flags);

	if (NULL != username)
	{
		/* in the case of invalid user there are no matching processes, return empty vector */
		if (NULL == (usrinfo = getpwnam(username)))
			goto out;
	}
	else
		usrinfo = NULL;

	for (i = 0; i < processes->values_num; i++)
	{
		proc = (zbx_sysinfo_proc_t *)processes->values[i];

		if (SUCCEED != proc_match_user(proc, usrinfo))
			continue;

		if (SUCCEED != proc_match_name(proc, procname))
			continue;

		if (SUCCEED != proc_match_cmdline(proc, cmdline))
			continue;

		zbx_vector_uint64_append(pids, (zbx_uint64_t)proc->pid);
	}
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

int	PROC_CPU_UTIL(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*procname, *username, *cmdline, *tmp;
	char		*errmsg = NULL;
	int		period, type;
	double		value;
	zbx_timespec_t	ts_timeout, ts;

	/* proc.cpu.util[<procname>,<username>,(user|system),<cmdline>,(avg1|avg5|avg15)] */
	/*                   0          1           2            3             4          */
	if (5 < request->nparam)
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

	if (SUCCEED != zbx_procstat_collector_started())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Collector is not started."));
		return SYSINFO_RET_FAIL;
	}

	zbx_timespec(&ts_timeout);
	ts_timeout.sec += CONFIG_TIMEOUT;

	while (SUCCEED != zbx_procstat_get_util(procname, username, cmdline, 0, period, type, &value, &errmsg))
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
