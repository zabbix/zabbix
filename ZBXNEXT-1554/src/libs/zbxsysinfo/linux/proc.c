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
#include "log.h"

static int	get_cmdline(FILE *f_cmd, char **line, size_t *line_offset)
{
	size_t	line_alloc = ZBX_KIBIBYTE, n;

	rewind(f_cmd);

	*line = zbx_malloc(*line, line_alloc + 2);
	*line_offset = 0;

	while (0 != (n = fread(*line + *line_offset, 1, line_alloc - *line_offset, f_cmd)))
	{
		*line_offset += n;

		if (0 != feof(f_cmd))
			break;

		line_alloc *= 2;
		*line = zbx_realloc(*line, line_alloc + 2);
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
			default:
				return FAIL;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: byte_value_from_proc_file                                        *
 *                                                                            *
 * Purpose: Read amount of memory in bytes from a string in /proc file.       *
 *          For example, reading "VmSize:   176712 kB" from  /proc/1/status   *
 *          will produce a result 176712*1024 = 180953088 bytes               *
 *                                                                            *
 * Parameters:                                                                *
 *     f     - [IN] file to read from                                         *
 *     s     - [IN] beginning part of string to search for, e.g. "VmSize:\t"  *
 *     bytes - [OUT] result in bytes                                          *
 *                                                                            *
 * Return value: SUCCEED - successful reading,                                *
 *               NOSUPPORTED - the search string was not found. For example,  *
 *                             /proc/NNN/status files for kernel threads do   *
 *                             not contain "VmSize:" string.                  *
 *               FAIL - the search string was found but could not be parsed.  *
 *                                                                            *
 ******************************************************************************/
static int byte_value_from_proc_file(FILE *f, const char *s, zbx_uint64_t *bytes)
{
	char	buf[MAX_STRING_LEN], *p, *p_unit;
	size_t	sz;
	int	ret = NOTSUPPORTED;

	sz = strlen(s);
	p = buf + sz;

	while (NULL != fgets(buf, (int)sizeof(buf), f))
	{
		if (0 != strncmp(buf, s, sz))
			continue;

		if (NULL == (p_unit = strrchr(p, ' ')))
		{
			ret = FAIL;
			break;
		}

		*p_unit++ = '\0';

		while (' ' == *p)
			p++;

		if (FAIL == is_uint64(p, bytes))
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

static int get_total_memory(zbx_uint64_t *total_memory)
{
	FILE	*f;
	int	ret = FAIL;

	if (NULL != (f = fopen("/proc/meminfo", "r")))
	{
		ret = byte_value_from_proc_file(f, "MemTotal:", total_memory);
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
	int		do_task, proccount = 0, invalid_read = 0, mem_type_tried = 0, mem_type_code, res;
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
			if (0 == errno)
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Specified user does not exist."));
			else
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));

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

		if (0 == strcmp(entries->d_name, "self"))
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
				if (SUCCEED != (res = byte_value_from_proc_file(f_stat, mem_type_search, &byte_value)))
				{
					if (NOTSUPPORTED == res)
					{
						continue;
					}
					else	/* FAIL */
					{
						invalid_read = 1;
						goto out;
					}
				}
				break;
			case ZBX_SIZE:
				{
					zbx_uint64_t	m;

					/* VmData, VmStk and VmExe follow in /proc/PID/status file in that order. */
					/* Therefore we do not rewind f_stat between calls. */

					mem_type_search = "VmData:\t";

					if (SUCCEED == (res = byte_value_from_proc_file(f_stat, mem_type_search,
							&byte_value)))
					{
						mem_type_search = "VmStk:\t";

						if (SUCCEED == (res = byte_value_from_proc_file(f_stat, mem_type_search,
								&m)))
						{
							byte_value += m;
							mem_type_search = "VmExe:\t";

							if (SUCCEED == (res = byte_value_from_proc_file(f_stat,
									mem_type_search, &m)))
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
							goto out;
						}
					}
				}
				break;
			case ZBX_PMEM:
				{
					mem_type_search = "VmRSS:\t";
					res = byte_value_from_proc_file(f_stat, mem_type_search, &byte_value);

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
						goto out;
					}
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
out:
	zbx_fclose(f_cmd);
	zbx_fclose(f_stat);
	closedir(dir);

	if ((0 == proccount && 0 != mem_type_tried) || 0 != invalid_read)
	{
		char	*s = NULL;

		s = zbx_strdup(NULL, mem_type_search);
		zbx_rtrim(s, ":\t");
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot get amount of \"%s\" memory.", s));
		zbx_free(s);
		return SYSINFO_RET_FAIL;
	}

	if (ZBX_PMEM != mem_type_code)
	{
		if (ZBX_DO_AVG == do_task)
			SET_DBL_RESULT(result, proccount == 0 ? 0 : (double)mem_size / (double)proccount);
		else
			SET_UI64_RESULT(result, mem_size);
	}
	else
	{
		if (ZBX_DO_AVG == do_task)
			SET_DBL_RESULT(result, proccount == 0 ? 0 : pct_size / (double)proccount);
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
	int		zbx_proc_stat;
	zbx_uint64_t	proccount = 0;

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
			if (0 == errno)
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Specified user does not exist."));
			else
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain user information: %s",
						zbx_strerror(errno)));

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

	if (NULL == (dir = opendir("/proc")))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot open /proc: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	while (NULL != (entries = readdir(dir)))
	{
		zbx_fclose(f_cmd);
		zbx_fclose(f_stat);

		if (0 == strcmp(entries->d_name, "self"))
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

	SET_UI64_RESULT(result, proccount);

	return SYSINFO_RET_OK;
}
