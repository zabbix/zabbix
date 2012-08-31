/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "sysinfo.h"

int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo	info;
	char		swapdev[32], mode[32];

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, swapdev, sizeof(swapdev)))
		*swapdev = '\0';

	if ('\0' != *swapdev && 0 != strcmp(swapdev, "all"))	/* default parameter */
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (0 != sysinfo(&info))
		return SYSINFO_RET_FAIL;

#ifdef HAVE_SYSINFO_MEM_UNIT
	if ('\0' == *mode || 0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, info.freeswap * (zbx_uint64_t)info.mem_unit);
	else if (0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, info.totalswap * (zbx_uint64_t)info.mem_unit);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, (info.totalswap - info.freeswap) * (zbx_uint64_t)info.mem_unit);
#else
	if ('\0' == *mode || 0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, info.freeswap);
	else if (0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, info.totalswap);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, info.totalswap - info.freeswap);
#endif
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, info.totalswap ? 100.0 * ((double)info.freeswap /
					(double)info.totalswap) : 0.0);
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, info.totalswap ? 100.0 - 100.0 * ((double)info.freeswap /
					(double)info.totalswap) : 0.0);
	else
		return SYSINFO_RET_FAIL;

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

#if defined(KERNEL_2_4)
#	define INFO_FILE_NAME	"/proc/partitions"
#	define PARSE(line)	if (sscanf(line, "%d %d %*d %*s " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d", \
				&rdev_major, 		/* major */ \
				&rdev_minor, 		/* minor */ \
				&(result->rio),		/* rio */ \
				&(result->rsect),	/* rsect */ \
				&(result->wio),		/* rio */ \
				&(result->wsect)	/* wsect */ \
				) != 6) continue
#else
#	define INFO_FILE_NAME	"/proc/diskstats"
#	define PARSE(line)	if (sscanf(line, "%d %d %*s " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d", \
				&rdev_major, 		/* major */ \
				&rdev_minor, 		/* minor */ \
				&(result->rio),		/* rio */ \
				&(result->rsect),	/* rsect */ \
				&(result->wio),		/* wio */ \
				&(result->wsect)	/* wsect */ \
				) != 6)  \
					if (sscanf(line, "%d %d %*s " \
						ZBX_FS_UI64 " " ZBX_FS_UI64 " " \
						ZBX_FS_UI64 " " ZBX_FS_UI64, \
					&rdev_major, 		/* major */ \
					&rdev_minor, 		/* minor */ \
					&(result->rio),		/* rio */ \
					&(result->rsect),	/* rsect */ \
					&(result->wio),		/* wio */ \
					&(result->wsect)	/* wsect */ \
					) != 6) continue
#endif

static int	get_swap_dev_stat(const char *interface, swap_stat_t *result)
{
	int		ret = SYSINFO_RET_FAIL;
	char		line[MAX_STRING_LEN]/*, name[MAX_STRING_LEN]*/;
	int		rdev_major, rdev_minor;
	struct stat	dev_st;
	FILE		*f;

	assert(result);

	if (-1 == stat(interface, &dev_st))
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
	int		ret = SYSINFO_RET_FAIL;
	char		line[MAX_STRING_LEN];
	char		name[MAX_STRING_LEN];
	zbx_uint64_t	value1, value2;
	FILE		*f;

	if(NULL != (f = fopen("/proc/stat","r")) )
	{
		while(fgets(line, sizeof(line), f))
		{
			if(sscanf(line, "%10s " ZBX_FS_UI64 " " ZBX_FS_UI64, name, &value1, &value2) != 3)
				continue;

			if(strcmp(name, "swap"))
				continue;

			result->wpag = value1;
			result->rpag = value2;

			ret = SYSINFO_RET_OK;
			break;
		};
		zbx_fclose(f);
	}

	if(ret != SYSINFO_RET_OK)
	{
		result->wpag = 0;
		result->rpag = 0;
	}

	return ret;
}

static int	get_swap_stat(const char *interface, swap_stat_t *result)
{
	int		offset = 0, ret = SYSINFO_RET_FAIL;
	swap_stat_t	curr;
	FILE		*f;
	char		line[MAX_STRING_LEN], *s;

	memset(result, 0, sizeof(swap_stat_t));

	if (0 == strcmp(interface, "all"))
	{
		ret = get_swap_pages(result);
		interface = NULL;
	}
	else if (0 != strncmp(interface, "/dev/", 5))
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

		if (NULL != interface && 0 != strcmp(interface, line + offset))
			continue;

		if (SYSINFO_RET_OK == get_swap_dev_stat(line, &curr))
		{
			result->rio	+= curr.rio;
			result->rsect	+= curr.rsect;
			result->wio	+= curr.wio;
			result->wsect	+= curr.wsect;

			ret = SYSINFO_RET_OK;
		}
	}
	fclose(f);

	return ret;
}

int	SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		swapdev[MAX_STRING_LEN], mode[32];
	swap_stat_t	ss;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, swapdev, sizeof(swapdev)))
		*swapdev = '\0';

	if ('\0' == *swapdev)	/* default parameter */
		zbx_snprintf(swapdev, sizeof(swapdev), "all");

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (SYSINFO_RET_OK != get_swap_stat(swapdev, &ss))
		return SYSINFO_RET_FAIL;

	if (('\0' == *mode || 0 == strcmp(mode, "pages"))
			&& 0 == strcmp(swapdev, "all"))	/* default parameter */
		SET_UI64_RESULT(result, ss.wpag);
	else if (0 == strcmp(mode, "sectors"))
		SET_UI64_RESULT(result, ss.wsect);
	else if (0 == strcmp(mode, "count"))
		SET_UI64_RESULT(result, ss.wio);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char		swapdev[MAX_STRING_LEN], mode[32];
	swap_stat_t	ss;

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, swapdev, sizeof(swapdev)))
		*swapdev = '\0';

	if ('\0' == *swapdev)	/* default parameter */
		zbx_snprintf(swapdev, sizeof(swapdev), "all");

	if (0 != get_param(param, 2, mode, sizeof(mode)))
		*mode = '\0';

	if (SYSINFO_RET_OK != get_swap_stat(swapdev, &ss))
		return SYSINFO_RET_FAIL;

	if (('\0' == *mode || 0 == strcmp(mode, "pages"))
			&& 0 == strcmp(swapdev, "all"))	/* default parameter */
		SET_UI64_RESULT(result, ss.rpag);
	else if (0 == strcmp(mode, "sectors"))
		SET_UI64_RESULT(result, ss.rsect);
	else if (0 == strcmp(mode, "count"))
		SET_UI64_RESULT(result, ss.rio);
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}
