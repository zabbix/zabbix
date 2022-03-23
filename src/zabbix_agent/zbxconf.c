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

#include "zbxconf.h"

#include "log.h"
#include "alias.h"
#include "sysinfo.h"

#ifdef _WINDOWS
#	include "perfstat.h"
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: load aliases from configuration                                   *
 *                                                                            *
 * Parameters: lines - aliases from configuration file                        *
 *                                                                            *
 * Comments: calls add_alias() for each entry                                 *
 *                                                                            *
 ******************************************************************************/
void	load_aliases(char **lines)
{
	char	**pline;

	for (pline = lines; NULL != *pline; pline++)
	{
		char		*c;
		const char	*r = *pline;

		if (SUCCEED != parse_key(&r) || ':' != *r)
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add alias \"%s\": invalid character at position %d",
					*pline, (int)((r - *pline) + 1));
			exit(EXIT_FAILURE);
		}

		c = (char *)r++;

		if (SUCCEED != parse_key(&r) || '\0' != *r)
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot add alias \"%s\": invalid character at position %d",
					*pline, (int)((r - *pline) + 1));
			exit(EXIT_FAILURE);
		}

		*c++ = '\0';

		add_alias(*pline, c);

		*--c = ':';
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: load user parameters from configuration                           *
 *                                                                            *
 * Parameters: lines - user parameter entries from configuration file         *
 *             error - error message                                          *
 *                                                                            *
 * Return value: SUCCEED - successfully loaded user parameters                *
 *               FAIL    - failed to load user parameters                     *
 *                                                                            *
 * Comments: calls add_user_parameter() for each entry                        *
 *                                                                            *
 ******************************************************************************/
int	load_user_parameters(char **lines, char **err)
{
	char	*p, **pline, error[MAX_STRING_LEN];

	for (pline = lines; NULL != *pline; pline++)
	{
		if (NULL == (p = strchr(*pline, ',')))
		{
			*err = zbx_dsprintf(*err, "user parameter \"%s\": not comma-separated", *pline);
			return FAIL;
		}
		*p = '\0';

		if (FAIL == add_user_parameter(*pline, p + 1, error, sizeof(error)))
		{
			*p = ',';
			*err = zbx_dsprintf(*err, "user parameter \"%s\": %s", *pline, error);
			return FAIL;
		}
		*p = ',';
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Adds key access rule from configuration                           *
 *                                                                            *
 * Parameters: value - [IN] key access rule parameter value                   *
 *             cfg   - [IN] configuration parameter information               *
 *                                                                            *
 * Return value: SUCCEED - successful execution                               *
 *               FAIL    - failed to add rule                                 *
 *                                                                            *
 ******************************************************************************/
int	load_key_access_rule(const char *value, const struct cfg_line *cfg)
{
	unsigned char	rule_type;

	if (0 == strcmp(cfg->parameter, "AllowKey"))
		rule_type = ZBX_KEY_ACCESS_ALLOW;
	else if (0 == strcmp(cfg->parameter, "DenyKey"))
		rule_type = ZBX_KEY_ACCESS_DENY;
	else
		return FAIL;

	return add_key_access_rule(cfg->parameter, (char *)value, rule_type);
}

#ifdef _WINDOWS
/******************************************************************************
 *                                                                            *
 * Purpose: load performance counters from configuration                      *
 *                                                                            *
 * Parameters: def_lines - array of PerfCounter configuration entries         *
 *             eng_lines - array of PerfCounterEn configuration entries       *
 *                                                                            *
 ******************************************************************************/
void	load_perf_counters(const char **def_lines, const char **eng_lines)
{
	char		name[MAX_STRING_LEN], counterpath[PDH_MAX_COUNTER_PATH], interval[8];
	const char	**pline, **lines;
	char		*error = NULL;
	LPTSTR		wcounterPath;
	int		period;

	for (lines = def_lines;; lines = eng_lines)
	{
		zbx_perf_counter_lang_t lang = (lines == def_lines) ? PERF_COUNTER_LANG_DEFAULT : PERF_COUNTER_LANG_EN;

		for (pline = lines; NULL != *pline; pline++)
		{
			if (3 < num_param(*pline))
			{
				error = zbx_strdup(error, "Required parameter missing.");
				goto pc_fail;
			}

			if (0 != get_param(*pline, 1, name, sizeof(name), NULL))
			{
				error = zbx_strdup(error, "Cannot parse key.");
				goto pc_fail;
			}

			if (0 != get_param(*pline, 2, counterpath, sizeof(counterpath), NULL))
			{
				error = zbx_strdup(error, "Cannot parse counter path.");
				goto pc_fail;
			}

			if (0 != get_param(*pline, 3, interval, sizeof(interval), NULL))
			{
				error = zbx_strdup(error, "Cannot parse interval.");
				goto pc_fail;
			}

			wcounterPath = zbx_acp_to_unicode(counterpath);
			zbx_unicode_to_utf8_static(wcounterPath, counterpath, PDH_MAX_COUNTER_PATH);
			zbx_free(wcounterPath);

			if (FAIL == check_counter_path(counterpath, lang == PERF_COUNTER_LANG_DEFAULT))
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

			if (NULL == add_perf_counter(name, counterpath, period, lang, &error))
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

		if (lines == eng_lines)
			break;
	}
}
#else
/******************************************************************************
 *                                                                            *
 * Purpose: load user parameters from configuration file                      *
 *                                                                            *
 ******************************************************************************/
static int	load_config_user_params(void)
{
	struct cfg_line	cfg[] =
	{
		/* PARAMETER,			VAR,					TYPE,
			MANDATORY,	MIN,			MAX */
		{"UserParameter",		&CONFIG_USER_PARAMETERS,		TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{NULL}
	};

	return parse_cfg_file(CONFIG_FILE, cfg, ZBX_CFG_FILE_REQUIRED, ZBX_CFG_NOT_STRICT, ZBX_CFG_NO_EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reload user parameters                                            *
 *                                                                            *
 * Parameters: process_type - process type                                    *
 *             process_num - process number                                   *
 *                                                                            *
 ******************************************************************************/
void	reload_user_parameters(unsigned char process_type, int process_num)
{
	char		*error = NULL;
	ZBX_METRIC	*metrics_fallback = NULL;

	zbx_strarr_init(&CONFIG_USER_PARAMETERS);

	if (FAIL == load_config_user_params())
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot reload user parameters [%s #%d]: error processing configuration file",
				get_process_type_string(process_type), process_num);
		goto out;
	}

	get_metrics_copy(&metrics_fallback);
	remove_user_parameters();

	if (FAIL == load_user_parameters(CONFIG_USER_PARAMETERS, &error))
	{
		set_metrics(metrics_fallback);
		zabbix_log(LOG_LEVEL_ERR, "cannot reload user parameters [%s #%d], %s",
				get_process_type_string(process_type), process_num, error);
		zbx_free(error);
		goto out;
	}

	free_metrics_ext(&metrics_fallback);
	zabbix_log(LOG_LEVEL_INFORMATION, "user parameters reloaded [%s #%d]", get_process_type_string(process_type),
			process_num);
out:
	zbx_strarr_free(&CONFIG_USER_PARAMETERS);
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
