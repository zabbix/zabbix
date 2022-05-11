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

#include "checks_db.h"

#ifdef HAVE_UNIXODBC

#include "log.h"
#include "../odbc/odbc.h"

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve data from database                                       *
 *                                                                            *
 * Parameters: item   - [IN] item we are interested in                        *
 *             result - [OUT] check result                                    *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 ******************************************************************************/
int	get_value_db(const DC_ITEM *item, AGENT_RESULT *result)
{
	AGENT_REQUEST		request;
	const char		*dsn, *connection = NULL;
	zbx_odbc_data_source_t	*data_source;
	zbx_odbc_query_result_t	*query_result;
	char			*error = NULL;
	int			(*query_result_to_text)(zbx_odbc_query_result_t *query_result, char **text, char **error),
				ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key_orig:'%s' query:'%s'", __func__, item->key_orig, item->params);

	init_request(&request);

	if (SUCCEED != parse_item_key(item->key, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item key format."));
		goto out;
	}

	if (0 == strcmp(request.key, "db.odbc.select"))
	{
		query_result_to_text = zbx_odbc_query_result_to_string;
	}
	else if (0 == strcmp(request.key, "db.odbc.discovery"))
	{
		query_result_to_text = zbx_odbc_query_result_to_lld_json;
	}
	else if (0 == strcmp(request.key, "db.odbc.get"))
	{
		query_result_to_text = zbx_odbc_query_result_to_json;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key for this item type."));
		goto out;
	}

	if (2 > request.nparam || 3 < request.nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	/* request.params[0] is ignored and is only needed to distinguish queries of same DSN */

	dsn = request.params[1];

	if (2 < request.nparam)
		connection = request.params[2];

	if ((NULL == dsn || '\0' == *dsn) && (NULL == connection || '\0' == *connection))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid database connection settings."));
		goto out;
	}

	if (NULL != (data_source = zbx_odbc_connect(dsn, connection, item->username, item->password, CONFIG_TIMEOUT,
			&error)))
	{
		if (NULL != (query_result = zbx_odbc_select(data_source, item->params, &error)))
		{
			char	*text = NULL;

			if (SUCCEED == query_result_to_text(query_result, &text, &error))
			{
				SET_TEXT_RESULT(result, text);
				ret = SUCCEED;
			}

			zbx_odbc_query_result_free(query_result);
		}

		zbx_odbc_data_source_free(data_source);
	}

	if (SUCCEED != ret)
		SET_MSG_RESULT(result, error);
out:
	free_request(&request);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#endif	/* HAVE_UNIXODBC */
