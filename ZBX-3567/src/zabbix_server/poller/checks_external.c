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
	char		*conn, *params = NULL, *command = NULL,
			*p, *pl, *pr = NULL, error[ITEM_ERROR_LEN_MAX],
			*buf = NULL;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name, item->key_orig);

	conn = item->host.useip == 1 ? item->host.ip : item->host.dns;

	if (NULL != (pl = strchr(item->key, '[')))
	{
		*pl = '\0';
		params = pl + 1;

		if (NULL != (pr = strchr(params, ']')))
			*pr = '\0';
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "External check is not supported."
						" No closing bracket ']' found."));
			ret = NOTSUPPORTED;
			goto exit;
		}
	}

	if (NULL != params)
		command = zbx_dsprintf(command, "%s/%s %s %s",
				CONFIG_EXTERNALSCRIPTS, item->key, conn, params);
	else
		command = zbx_dsprintf(command, "%s/%s %s",
				CONFIG_EXTERNALSCRIPTS, item->key, conn);

	if (SUCCEED == zbx_execute(command, &buf, error, sizeof(error), CONFIG_TIMEOUT))
	{
		/* we only care about the first line */
		if (NULL != (p = strchr(buf, '\n')))
			*p = '\0';

		zbx_rtrim(buf, ZBX_WHITESPACE);

		if ('\0' == *buf)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Script returned nothing"));
			ret = NOTSUPPORTED;
		}
		else if (SUCCEED != set_result_type(result, item->value_type, item->data_type, buf))
			ret = NOTSUPPORTED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot execute script: %s", error);
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));
		ret = NOTSUPPORTED;
	}

	zbx_free(buf);
	zbx_free(command);
exit:
	if (NULL != pl)
		*pl = '[';
	if (NULL != pr)
		*pr = ']';

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
