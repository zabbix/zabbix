/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "zbxjson.h"
#include "log.h"

#ifdef HAVE_UNIXODBC

#include "zbxodbc.h"

static int	get_result_columns(ZBX_ODBC_DBH *dbh, char **buffer)
{
	int		ret = SUCCEED, i, j;
	char		str[MAX_STRING_LEN];
	SQLRETURN	rc;
	SQLSMALLINT	len;

	for (i = 0; i < dbh->col_num; i++)
	{
		rc = SQLColAttribute(dbh->hstmt, i + 1, SQL_DESC_LABEL, str, sizeof(str), &len, NULL);

		if (SQL_SUCCESS != rc || sizeof(str) <= len || '\0' == *str)
		{
			for (j = 0; j < i; j++)
				zbx_free(buffer[j]);

			ret = FAIL;
			break;
		}

		buffer[i] = zbx_strdup(NULL, str);
	}

	return ret;
}

static int	db_odbc_discovery(DC_ITEM *item, AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*__function_name = "db_odbc_discovery";

	int		ret = NOTSUPPORTED, i, j;
	ZBX_ODBC_DBH	dbh;
	ZBX_ODBC_ROW	row;
	char		**columns, *p, macro[MAX_STRING_LEN];
	struct zbx_json	json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() query:'%s'", __function_name, item->params);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	if (SUCCEED != odbc_DBconnect(&dbh, request->params[1], item->username, item->password, CONFIG_TIMEOUT))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, get_last_odbc_strerror()));
		goto out;
	}

	if (NULL != odbc_DBselect(&dbh, item->params))
	{
		columns = zbx_malloc(NULL, sizeof(char *) * dbh.col_num);

		if (SUCCEED == get_result_columns(&dbh, columns))
		{
			for (i = 0; i < dbh.col_num; i++)
				zabbix_log(LOG_LEVEL_DEBUG, "%s() column[%d]:'%s'", __function_name, i + 1, columns[i]);

			for (i = 0; i < dbh.col_num; i++)
			{
				for (p = columns[i]; '\0' != *p; p++)
				{
					if (0 != isalpha((unsigned char)*p))
						*p = toupper((unsigned char)*p);

					if (SUCCEED != is_macro_char(*p))
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL,
								"Cannot convert column #%d name to macro.", i + 1));
						goto clean;
					}
				}

				for (j = 0; j < i; j++)
				{
					if (0 == strcmp(columns[i], columns[j]))
					{
						SET_MSG_RESULT(result, zbx_dsprintf(NULL,
								"Duplicate macro name: {#%s}.", columns[i]));
						goto clean;
					}
				}
			}

			zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
			zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

			while (NULL != (row = odbc_DBfetch(&dbh)))
			{
				zbx_json_addobject(&json, NULL);

				for (i = 0; i < dbh.col_num; i++)
				{
					zbx_snprintf(macro, MAX_STRING_LEN, "{#%s}", columns[i]);
					zbx_json_addstring(&json, macro, row[i], ZBX_JSON_TYPE_STRING);
				}

				zbx_json_close(&json);
			}

			zbx_json_close(&json);

			SET_STR_RESULT(result, zbx_strdup(NULL, json.buffer));

			zbx_json_free(&json);

			ret = SUCCEED;
clean:
			for (i = 0; i < dbh.col_num; i++)
				zbx_free(columns[i]);
		}
		else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain column names."));

		zbx_free(columns);
	}
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, get_last_odbc_strerror()));

	odbc_DBclose(&dbh);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	db_odbc_select(DC_ITEM *item, AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*__function_name = "db_odbc_select";

	int		ret = NOTSUPPORTED;
	ZBX_ODBC_DBH	dbh;
	ZBX_ODBC_ROW	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() query:'%s'", __function_name, item->params);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	if (SUCCEED != odbc_DBconnect(&dbh, request->params[1], item->username, item->password, CONFIG_TIMEOUT))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, get_last_odbc_strerror()));
		goto out;
	}

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
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

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
 ******************************************************************************/
int	get_value_db(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_db";

	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key_orig:'%s'", __function_name, item->key_orig);

	init_request(&request);

	if (SUCCEED == parse_item_key(item->key, &request))
	{
		if (0 == strcmp(request.key, "db.odbc.select"))
			ret = db_odbc_select(item, &request, result);
		else if (0 == strcmp(request.key, "db.odbc.discovery"))
			ret = db_odbc_discovery(item, &request, result);
		else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported item key for this item type."));
	}
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item parameter format."));

	free_request(&request);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#endif	/* HAVE_UNIXODBC */
