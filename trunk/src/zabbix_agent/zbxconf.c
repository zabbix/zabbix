/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
	const char	**pline;
	char		*error = NULL;
	LPTSTR		wcounterPath;
	int		period;

	for (pline = lines; NULL != *pline; pline++)
	{
		if (3 < num_param(*pline))
		{
			error = zbx_strdup(error, "Required parameter missing.");
			goto pc_fail;
		}

		if (0 != get_param(*pline, 1, name, sizeof(name)))
		{
			error = zbx_strdup(error, "Cannot parse key.");
			goto pc_fail;
		}

		if (0 != get_param(*pline, 2, counterpath, sizeof(counterpath)))
		{
			error = zbx_strdup(error, "Cannot parse counter path.");
			goto pc_fail;
		}

		if (0 != get_param(*pline, 3, interval, sizeof(interval)))
		{
			error = zbx_strdup(error, "Cannot parse interval.");
			goto pc_fail;
		}

		wcounterPath = zbx_acp_to_unicode(counterpath);
		zbx_unicode_to_utf8_static(wcounterPath, counterpath, PDH_MAX_COUNTER_PATH);
		zbx_free(wcounterPath);

		if (FAIL == check_counter_path(counterpath))
		{
			error = zbx_strdup(error, "Invalid counter path.");
			goto pc_fail;
		}

		period = atoi(interval);

		if (1 > period || MAX_COLLECTOR_PERIOD < period)
		{
			error = zbx_strdup(NULL, "Interval out of range.");
			goto pc_fail;
		}

		if (NULL == add_perf_counter(name, counterpath, period, &error))
		{
			if (NULL == error)
				error = zbx_strdup(error, "Failed to add new performance counter.");
			goto pc_fail;
		}

		continue;
pc_fail:
		zabbix_log(LOG_LEVEL_CRIT, "cannot add performance counter \"%s\": %s", *pline, error);
		zbx_free(error);

		exit(EXIT_FAILURE);
	}
}
#endif	/* _WINDOWS */

#ifdef _AIX
void	tl_version(void)
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
