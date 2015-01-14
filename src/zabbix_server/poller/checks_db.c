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

#include "checks_db.h"

#include "zbxodbc.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: get_value_db                                                     *
 *                                                                            *
 * Purpose: retrieve data from database                                       *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_value_db(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_db";
	ZBX_ODBC_DBH	dbh;
	ZBX_ODBC_ROW	row;
	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key_orig:'%s'", __function_name, item->key_orig);

	init_request(&request);

	if (SUCCEED != parse_item_key(item->key, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Key is badly formatted."));
		goto out;
	}

	if (0 != strcmp(request.key, "db.odbc.select"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Item key is not supported."));
		goto out;
	}

	if (2 != request.nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	if (SUCCEED == odbc_DBconnect(&dbh, request.params[1], item->username, item->password, CONFIG_TIMEOUT))
	{
		if (NULL != odbc_DBselect(&dbh, item->params))
		{
			if (NULL != (row = odbc_DBfetch(&dbh)))
			{
				if (NULL == row[0])
				{
					SET_MSG_RESULT(result, zbx_strdup(NULL, "SQL query returned NULL value."));
				}
				else if (SUCCEED == set_result_type(result, item->value_type, item->data_type, row[0]))
				{
					ret = SUCCEED;
				}
			}
			else
			{
				const char	*last_error = get_last_odbc_strerror();

				if ('\0' != *last_error)
					SET_MSG_RESULT(result, zbx_strdup(NULL, last_error));
				else
					SET_MSG_RESULT(result, zbx_strdup(NULL, "SQL query returned empty result."));
			}
		}
		else
			SET_MSG_RESULT(result, zbx_strdup(NULL, get_last_odbc_strerror()));

		odbc_DBclose(&dbh);
	}
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, get_last_odbc_strerror()));
out:
	free_request(&request);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
