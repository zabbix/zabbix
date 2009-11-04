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
#include "md5.h"

int	SYSTEM_SWAP_SIZE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	struct sysinfo	info;
	char		swapdev[32], mode[32];

	assert(result);

	init_result(result);

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
		SET_UI64_RESULT(result, info.freeswap * (zbx_uint64_t)info.mem_unit)
	else if (0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, info.totalswap * (zbx_uint64_t)info.mem_unit)
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, (info.totalswap - info.freeswap) * (zbx_uint64_t)info.mem_unit)
#else
	if ('\0' == *mode || 0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, info.freeswap)
	else if (0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, info.totalswap)
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, info.totalswap - info.freeswap)
#endif
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, 100.0 * ((double)info.freeswap / (double)info.totalswap))
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, 100.0 - 100.0 * ((double)info.freeswap / (double)info.totalswap))
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

struct swap_stat_s {
	zbx_uint64_t rio;
	zbx_uint64_t rsect;
	zbx_uint64_t rpag;
	zbx_uint64_t wio;
	zbx_uint64_t wsect;
	zbx_uint64_t wpag;
};

#if defined(KERNEL_2_4)
#	define INFO_FILE_NAME	"/proc/partitions"
#	define PARSE(line)	if(sscanf(line,"%*d %*d %*d %s " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d", \
				name,			/* name */ \
				&(result->rio),		/* rio */ \
				&(result->rsect),	/* rsect */ \
				&(result->wio),		/* rio */ \
				&(result->wsect)	/* wsect */ \
				) != 5) continue
#else
#	define INFO_FILE_NAME	"/proc/diskstats"
#	define PARSE(line)	if(sscanf(line, "%*d %*d %s " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d " \
					ZBX_FS_UI64 " %*d " ZBX_FS_UI64 " %*d %*d %*d %*d", \
				name,			/* name */ \
				&(result->rio),		/* rio */ \
				&(result->rsect),	/* rsect */ \
				&(result->wio),		/* wio */ \
				&(result->wsect)	/* wsect */ \
				) != 5)  \
					if(sscanf(line,"%*d %*d %s " \
						ZBX_FS_UI64 " " ZBX_FS_UI64 " " \
						ZBX_FS_UI64 " " ZBX_FS_UI64, \
					name,			/* name */ \
					&(result->rio),		/* rio */ \
					&(result->rsect),	/* rsect */ \
					&(result->wio),		/* wio */ \
					&(result->wsect)	/* wsect */ \
					) != 5) continue
#endif

static int get_swap_dev_stat(const char *interface, struct swap_stat_s *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	line[MAX_STRING_LEN];

	char	name[MAX_STRING_LEN];

	FILE *f;

	assert(result);

	if(NULL != (f = fopen(INFO_FILE_NAME,"r")))
	{
		while(fgets(line,MAX_STRING_LEN,f) != NULL)
		{
			PARSE(line);

			if(strncmp(name, interface, MAX_STRING_LEN) == 0)
			{
				ret = SYSINFO_RET_OK;
				break;
			}
		}
		fclose(f);
	}

	if(ret != SYSINFO_RET_OK)
	{
		result->rio	= 0;
		result->rsect	= 0;
		result->wio	= 0;
		result->wsect	= 0;
	}
	return ret;
}

static int	get_swap_pages(struct swap_stat_s *result)
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

			result->wpag	= value1;
			result->rpag	= value2;

			ret = SYSINFO_RET_OK;
			break;
		};
		zbx_fclose(f);
	}

	if(ret != SYSINFO_RET_OK)
	{
		result->wpag	= 0;
		result->rpag	= 0;
	}

	return ret;
}

static int	get_swap_stat(const char *interface, struct swap_stat_s *result)
{
	int			ret = SYSINFO_RET_FAIL;
	struct swap_stat_s	curr;
	FILE			*f;
	char			line[MAX_STRING_LEN], *s;

	memset(result, 0, sizeof(struct swap_stat_s));

	if(0 == strcmp(interface, "all"))
	{
		ret = get_swap_pages(result);
		interface = NULL;
	}

	if(NULL != (f = fopen("/proc/swaps","r")) )
	{
		while (fgets(line, sizeof(line), f))
		{
			s = strchr(line,' ');
			if (s && s != line && strncmp(line, "/dev/", 5) == 0)
			{
				*s = 0;

				if(interface && 0 != strcmp(interface, line+5)) continue;

				if(SYSINFO_RET_OK == get_swap_dev_stat(line+5, &curr))
				{
					result->rio	+= curr.rio;
					result->rsect	+= curr.rsect;
					result->wio	+= curr.wio;
					result->wsect	+= curr.wsect;

					ret = SYSINFO_RET_OK;
				}
			}
		}
		fclose(f);
	}

	return ret;
}

int	SYSTEM_SWAP_IN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char			swapdev[32], mode[32];
	struct swap_stat_s	ss;

	assert(result);

	init_result(result);

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
		SET_UI64_RESULT(result, ss.wpag)
	else if (0 == strcmp(mode, "sectors"))
		SET_UI64_RESULT(result, ss.wsect)
	else if (0 == strcmp(mode, "count"))
		SET_UI64_RESULT(result, ss.wio)
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}

int	SYSTEM_SWAP_OUT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char			swapdev[32], mode[32];
	struct swap_stat_s	ss;

	assert(result);

	init_result(result);

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
		SET_UI64_RESULT(result, ss.rpag)
	else if (0 == strcmp(mode, "sectors"))
		SET_UI64_RESULT(result, ss.rsect)
	else if (0 == strcmp(mode, "count"))
		SET_UI64_RESULT(result, ss.rio)
	else
		return SYSINFO_RET_FAIL;

	return SYSINFO_RET_OK;
}
