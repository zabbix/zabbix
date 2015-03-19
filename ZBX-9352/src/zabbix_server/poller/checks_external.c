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

#include "common.h"
#include "log.h"
#include "zbxexec.h"

#include "checks_external.h"

extern char	*CONFIG_EXTERNALSCRIPTS;

/******************************************************************************
 *                                                                            *
 * Function: get_value_external                                               *
 *                                                                            *
 * Purpose: retrieve data from script executed on Zabbix server               *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *                         and result_str (as string)                         *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Mike Nestor, rewritten by Alexander Vladishev                      *
 *                                                                            *
 ******************************************************************************/
int	get_value_external(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_external";
	char		key[MAX_STRING_LEN], params[MAX_STRING_LEN], error[ITEM_ERROR_LEN_MAX],
			*cmd = NULL, *buf = NULL;
	size_t		cmd_alloc = ZBX_KIBIBYTE, cmd_offset = 0;
	int		rc, ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name, item->key_orig);

	if (0 == (rc = parse_command(item->key, key, sizeof(key), params, sizeof(params))))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Key is badly formatted"));
		goto notsupported;
	}

	cmd = zbx_malloc(cmd, cmd_alloc);
	zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, "%s/%s", CONFIG_EXTERNALSCRIPTS, key);

	if (-1 == access(cmd, X_OK))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "%s: %s", cmd, zbx_strerror(errno)));
		goto notsupported;
	}

	if (2 == rc)	/* key with parameters */
	{
		int	i, n;
		char	param[MAX_STRING_LEN], *param_esc;

		if (0 == (n = num_param(params)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Key is badly formatted"));
			goto notsupported;
		}

		for (i = 1; i <= n; i++)
		{
			if (0 != get_param(params, i, param, sizeof(param)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				*param = '\0';
			}

			param_esc = zbx_dyn_escape_string(param, "\"\\");
			zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, " \"%s\"", param_esc);
			zbx_free(param_esc);
		}
	}

	if (SUCCEED == zbx_execute(cmd, &buf, error, sizeof(error), CONFIG_TIMEOUT))
	{
		zbx_rtrim(buf, ZBX_WHITESPACE);

		if (SUCCEED == set_result_type(result, item->value_type, item->data_type, buf))
			ret = SUCCEED;

		zbx_free(buf);
	}
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));
notsupported:
	zbx_free(cmd);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
