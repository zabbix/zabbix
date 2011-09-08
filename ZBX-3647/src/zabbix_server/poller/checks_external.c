/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_value_external(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_external";
	char		key[MAX_STRING_LEN], params[MAX_STRING_LEN], param[MAX_STRING_LEN],
			error[ITEM_ERROR_LEN_MAX], *cmd = NULL, *buf = NULL, *addr_esc, *param_esc;
	int		n, cmd_alloc = ZBX_KIBIBYTE, cmd_offset = 0, ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name, item->key_orig);

	switch (parse_command(item->key, key, sizeof(key), params, sizeof(params)))
	{
		case 0:
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Key is badly formatted"));
			goto notsupported;
		case 1:	/* key without parameters */
			*param = '\0';
			break;
		case 2:	/* key with parameters */
			if (0 == (n = num_param(params)))
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Key is badly formatted"));
				goto notsupported;
			}

			if (1 < n)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters"));
				goto notsupported;
			}

			if (0 != get_param(params, 1, param, sizeof(param)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				*param = '\0';
			}
			break;
		default:
			assert(0);
	}

	cmd = zbx_malloc(cmd, cmd_alloc);
	zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, strlen(CONFIG_EXTERNALSCRIPTS) + strlen(key) + 2,
			"%s/%s", CONFIG_EXTERNALSCRIPTS, key);

	if (-1 == access(cmd, F_OK | X_OK))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "%s: %s", cmd, zbx_strerror(errno)));
		goto notsupported;
	}

	addr_esc = zbx_dyn_escape_string(item->interface.addr, "\"\\");
	zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, strlen(addr_esc) + 4, " \"%s\"", addr_esc);
	zbx_free(addr_esc);

	if ('\0' != *param)
	{
		param_esc = zbx_dyn_escape_string(param, "\"\\");
		zbx_snprintf_alloc(&cmd, &cmd_alloc, &cmd_offset, strlen(param_esc) + 4, " \"%s\"", param_esc);
		zbx_free(param_esc);
	}

	zabbix_log(LOG_LEVEL_CRIT, "%s() '%s'", __function_name, cmd);

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
