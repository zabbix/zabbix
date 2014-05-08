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
#include "zbxconf.h"

#include "cfg.h"
#include "log.h"
#include "alias.h"
#include "sysinfo.h"
#ifdef _WINDOWS
#	include "perfstat.h"
#endif
#include "comms.h"

#if defined(ZABBIX_DAEMON)
#	include "daemon.h"
#endif

char	*CONFIG_HOSTS_ALLOWED		= NULL;
char	*CONFIG_HOSTNAME		= NULL;
char	*CONFIG_HOSTNAME_ITEM		= NULL;
char	*CONFIG_HOST_METADATA		= NULL;
char	*CONFIG_HOST_METADATA_ITEM	= NULL;

int	CONFIG_ENABLE_REMOTE_COMMANDS	= 0;
int	CONFIG_LOG_REMOTE_COMMANDS	= 0;
int	CONFIG_UNSAFE_USER_PARAMETERS	= 0;
int	CONFIG_LISTEN_PORT		= ZBX_DEFAULT_AGENT_PORT;
int	CONFIG_REFRESH_ACTIVE_CHECKS	= 120;
char	*CONFIG_LISTEN_IP		= NULL;
char	*CONFIG_SOURCE_IP		= NULL;
int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;

int	CONFIG_BUFFER_SIZE		= 100;
int	CONFIG_BUFFER_SEND		= 5;

int	CONFIG_MAX_LINES_PER_SECOND	= 100;

char	*CONFIG_LOAD_MODULE_PATH	= NULL;

char	**CONFIG_ALIASES		= NULL;
char	**CONFIG_LOAD_MODULE		= NULL;
char	**CONFIG_USER_PARAMETERS	= NULL;
#if defined(_WINDOWS)
char	**CONFIG_PERF_COUNTERS		= NULL;
#endif

char	*CONFIG_USER			= NULL;

/******************************************************************************
 *                                                                            *
 * Function: load_aliases                                                     *
 *                                                                            *
 * Purpose: load aliases from configuration                                   *
 *                                                                            *
 * Parameters: lines - aliase entries from configuration file                 *
 *                                                                            *
 * Comments: calls add_alias() for each entry                                 *
 *                                                                            *
 ******************************************************************************/
void	load_aliases(char **lines)
{
	char	**pline, *r, *c;

	for (pline = lines; NULL != *pline; pline++)
	{
		r = *pline;

		if (SUCCEED != parse_key(&r) || ':' != *r)
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add alias \"%s\": invalid character at position %ld",
					*pline, (r - *pline) + 1);
			exit(EXIT_FAILURE);
		}

		c = r++;

		if (SUCCEED != parse_key(&r) || '\0' != *r)
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add alias \"%s\": invalid character at position %ld",
					*pline, (r - *pline) + 1);
			exit(EXIT_FAILURE);
		}

		*c++ = '\0';

		add_alias(*pline, c);

		*--c = ':';
	}
}

/******************************************************************************
 *                                                                            *
 * Function: load_user_parameters                                             *
 *                                                                            *
 * Purpose: load user parameters from configuration                           *
 *                                                                            *
 * Parameters: lines - user parameter entries from configuration file         *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments: calls add_user_parameter() for each entry                        *
 *                                                                            *
 ******************************************************************************/
void	load_user_parameters(char **lines)
{
	char	*p, **pline, error[MAX_STRING_LEN];

	for (pline = lines; NULL != *pline; pline++)
	{
		if (NULL == (p = strchr(*pline, ',')))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add user parameter \"%s\": not comma-separated", *pline);
			exit(EXIT_FAILURE);
		}
		*p = '\0';

		if (FAIL == add_user_parameter(*pline, p + 1, error, sizeof(error)))
		{
			*p = ',';
			zabbix_log(LOG_LEVEL_CRIT, "cannot add user parameter \"%s\": %s", *pline, error);
			exit(EXIT_FAILURE);
		}
		*p = ',';
	}
}

#ifdef _WINDOWS
/******************************************************************************
 *                                                                            *
 * Function: load_perf_counters                                               *
 *                                                                            *
 * Purpose: load performance counters from configuration                      *
 *                                                                            *
 * Parameters: lines - array of PerfCounter configuration entries             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	load_perf_counters(const char **lines)
{
	char		name[MAX_STRING_LEN], counterpath[PDH_MAX_COUNTER_PATH], interval[8];
	const char	**pline, *msg;
	wchar_t		*wcounterPath;

#define ZBX_PC_FAIL(_msg) {msg = _msg; goto pc_fail;}

	for (pline = lines; NULL != *pline; pline++)
	{
		if (3 < num_param(*pline))
			ZBX_PC_FAIL("required parameter missing");

		if (0 != get_param(*pline, 1, name, sizeof(name)))
			ZBX_PC_FAIL("cannot parse key");

		if (0 != get_param(*pline, 2, counterpath, sizeof(counterpath)))
			ZBX_PC_FAIL("cannot parse counter path");

		if (0 != get_param(*pline, 3, interval, sizeof(interval)))
			ZBX_PC_FAIL("cannot parse interval");

		wcounterPath = zbx_acp_to_unicode(counterpath);
		zbx_unicode_to_utf8_static(wcounterPath, counterpath, PDH_MAX_COUNTER_PATH);
		zbx_free(wcounterPath);

		if (FAIL == check_counter_path(counterpath))
			ZBX_PC_FAIL("invalid counter path");

		if (NULL == add_perf_counter(name, counterpath, atoi(interval)))
			ZBX_PC_FAIL("cannot add counter");

		continue;
pc_fail:
		zabbix_log(LOG_LEVEL_CRIT, "PerfCounter '%s' FAILED: %s", *pline, msg);
		exit(EXIT_FAILURE);
	}
#undef ZBX_PC_FAIL
}
#endif	/* _WINDOWS */

#ifdef _AIX
void	tl_version()
{
#ifdef _AIXVERSION_610
#	define ZBX_AIX_TL	"6100 and above"
#elif _AIXVERSION_530
#	ifdef HAVE_AIXOSLEVEL_530006
#		define ZBX_AIX_TL	"5300-06 and above"
#	else
#		define ZBX_AIX_TL	"5300-00,01,02,03,04,05"
#	endif
#elif _AIXVERSION_520
#	define ZBX_AIX_TL	"5200"
#elif _AIXVERSION_510
#	define ZBX_AIX_TL	"5100"
#endif
#ifdef ZBX_AIX_TL
	printf("Supported technology levels: %s\n", ZBX_AIX_TL);
#endif /* ZBX_AIX_TL */
#undef ZBX_AIX_TL
}
#endif /* _AIX */
