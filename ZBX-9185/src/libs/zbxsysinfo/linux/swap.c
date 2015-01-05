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
#include "log.h"

int	SYSTEM_SWAP_SIZE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	struct sysinfo	info;
	char		*swapdev, *mode;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))	/* default parameter */
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	if (0 != sysinfo(&info))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain system information: %s", zbx_strerror(errno)));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, info.freeswap * (zbx_uint64_t)info.mem_unit);
	else if (0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, info.totalswap * (zbx_uint64_t)info.mem_unit);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, (info.totalswap - info.freeswap) * (zbx_uint64_t)info.mem_unit);
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, info.totalswap ? 100.0 * (info.freeswap / (double)info.totalswap) : 0.0);
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, info.totalswap ? 100.0 - 100.0 * (info.freeswap / (double)info.totalswap) : 0.0);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

typedef struct
{
	zbx_uint64_t rio;
	zbx_uint64_t rsect;
	zbx_uint64_t rpag;
	zbx_uint64_t wio;
	zbx_uint64_t wsect;
	zbx_uint64_t wpag;
}
swap_stat_t;

#ifdef KERNEL_2_4
#	define INFO_FILE_NAME	"/proc/partitions"
#	define PARSE(line)								\
											\
		if (6 != sscanf(line, "%d %d %*d %*s "					\
				ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d "			\
				ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d",	\
				&rdev_major, 		/* major */			\
				&rdev_minor, 		/* minor */			\
				&result->rio,		/* rio */			\
				&result->rsect,		/* rsect */			\
				&result->wio,		/* wio */			\
				&result->wsect		/* wsect */			\
				)) continue
#else
#	define INFO_FILE_NAME	"/proc/diskstats"
#	define PARSE(line)								\
											\
		if (6 != sscanf(line, "%d %d %*s "					\
				ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d "			\
				ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d",	\
				&rdev_major, 		/* major */			\
				&rdev_minor, 		/* minor */			\
				&result->rio,		/* rio */			\
				&result->rsect,		/* rsect */			\
				&result->wio,		/* wio */			\
				&result->wsect		/* wsect */			\
				))							\
			if (6 != sscanf(line, "%d %d %*s "				\
					ZBX_FS_UI64 " " ZBX_FS_UI64 " "			\
					ZBX_FS_UI64 " " ZBX_FS_UI64,			\
					&rdev_major, 		/* major */		\
					&rdev_minor, 		/* minor */		\
					&result->rio,		/* rio */		\
					&result->rsect,		/* rsect */		\
					&result->wio,		/* wio */		\
					&result->wsect		/* wsect */		\
					)) continue
#endif

static int	get_swap_dev_stat(const char *swapdev, swap_stat_t *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		line[MAX_STRING_LEN];
	int		rdev_major, rdev_minor;
	zbx_stat_t	dev_st;
	FILE		*f;

	assert(result);

	if (-1 == zbx_stat(swapdev, &dev_st))
		return ret;

	if (NULL == (f = fopen(INFO_FILE_NAME, "r")))
		return ret;

	while (NULL != fgets(line, sizeof(line), f))
	{
		PARSE(line);

		if (rdev_major == major(dev_st.st_rdev) && rdev_minor == minor(dev_st.st_rdev))
		{
			ret = SYSINFO_RET_OK;
			break;
		}
	}
	fclose(f);

	return ret;
}

static int	get_swap_pages(swap_stat_t *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	line[MAX_STRING_LEN];
#ifndef KERNEL_2_4
	char	st = 0;
#endif
	FILE	*f;

#ifdef KERNEL_2_4
	if (NULL != (f = fopen("/proc/stat", "r")))
#else
	if (NULL != (f = fopen("/proc/vmstat", "r")))
#endif
	{
		while (NULL != fgets(line, sizeof(line), f))
		{
#ifdef KERNEL_2_4
			if (0 != strncmp(line, "swap ", 5))

			if (2 != sscanf(line + 5, ZBX_FS_UI64 " " ZBX_FS_UI64, &result->rpag, &result->wpag))
				continue;
#else
			if (0x00 == (0x01 & st) && 0 == strncmp(line, "pswpin ", 7))
			{
				sscanf(line + 7, ZBX_FS_UI64, &result->rpag);
				st |= 0x01;
			}
			else if (0x00 == (0x02 & st) && 0 == strncmp(line, "pswpout ", 8))
			{
				sscanf(line + 8, ZBX_FS_UI64, &result->wpag);
				st |= 0x02;
			}

			if (0x03 != st)
				continue;
#endif
			ret = SYSINFO_RET_OK;
			break;
		};

		zbx_fclose(f);
	}

	if (SYSINFO_RET_OK != ret)
	{
		result->rpag = 0;
		result->wpag = 0;
	}

	return ret;
}

static int	get_swap_stat(const char *swapdev, swap_stat_t *result)
{
	int		offset = 0, ret = SYSINFO_RET_FAIL;
	swap_stat_t	curr;
	FILE		*f;
	char		line[MAX_STRING_LEN], *s;

	memset(result, 0, sizeof(swap_stat_t));

	if (NULL == swapdev || '\0' == *swapdev || 0 == strcmp(swapdev, "all"))
	{
		ret = get_swap_pages(result);
		swapdev = NULL;
	}
	else if (0 != strncmp(swapdev, "/dev/", 5))
		offset = 5;

	if (NULL == (f = fopen("/proc/swaps", "r")))
		return ret;

	while (NULL != fgets(line, sizeof(line), f))
	{
		if (0 != strncmp(line, "/dev/", 5))
			continue;

		if (NULL == (s = strchr(line, ' ')))
			continue;

		*s = '\0';

		if (NULL != swapdev && 0 != strcmp(swapdev, line + offset))
			continue;

		if (SYSINFO_RET_OK == get_swap_dev_stat(line, &curr))
		{
			result->rio += curr.rio;
			result->rsect += curr.rsect;
			result->wio += curr.wio;
			result->wsect += curr.wsect;

			ret = SYSINFO_RET_OK;
		}
	}
	fclose(f);

	return ret;
}

int	SYSTEM_SWAP_IN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*swapdev, *mode;
	swap_stat_t	ss;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_swap_stat(swapdev, &ss))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain swap information."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "pages"))
	{
		if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}

		SET_UI64_RESULT(result, ss.rpag);
	}
	else if (0 == strcmp(mode, "sectors"))
		SET_UI64_RESULT(result, ss.rsect);
	else if (0 == strcmp(mode, "count"))
		SET_UI64_RESULT(result, ss.rio);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_OUT(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char		*swapdev, *mode;
	swap_stat_t	ss;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	swapdev = get_rparam(request, 0);
	mode = get_rparam(request, 1);

	if (SYSINFO_RET_OK != get_swap_stat(swapdev, &ss))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain swap information."));
		return SYSINFO_RET_FAIL;
	}

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "pages"))
	{
		if (NULL != swapdev && '\0' != *swapdev && 0 != strcmp(swapdev, "all"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SYSINFO_RET_FAIL;
		}

		SET_UI64_RESULT(result, ss.wpag);
	}
	else if (0 == strcmp(mode, "sectors"))
		SET_UI64_RESULT(result, ss.wsect);
	else if (0 == strcmp(mode, "count"))
		SET_UI64_RESULT(result, ss.wio);
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	return SYSINFO_RET_OK;
}
