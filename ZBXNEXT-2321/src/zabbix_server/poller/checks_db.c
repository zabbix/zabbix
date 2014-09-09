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

#include "checks_db.h"

#include "zbxodbc.h"
#include "log.h"
#include "zbxjson.h"

static int	get_result_table_col_names(ZBX_ODBC_DBH *dbh, char **buffer, size_t cnt)
{
	int		ret = FAIL, i, j, rc;
	char		str[MAX_STRING_LEN];
	SQLSMALLINT	ret_len;

	for (i = 0; i < cnt; i++)
	{
		rc = SQLColAttribute(dbh->hstmt, i + 1, SQL_DESC_LABEL, str, MAX_STRING_LEN, &ret_len, NULL);

		if (rc != SQL_SUCCESS || MAX_STRING_LEN <= ret_len || '\0' == str[0])
		{
			if (0 < i)
			{
				for (j = 0; j < i; j++)
				{
					zbx_free(buffer[j]);
					buffer[j] = NULL;
				}
			}

			break;
		}

		buffer[i] = zbx_strdup(NULL, str);
	}

	if (i == cnt)
		ret = SUCCEED;

	return ret;
}

static int	db_odbc_discovery(DC_ITEM *item, AGENT_REQUEST *request, AGENT_RESULT *result)
{
	const char	*__function_name = "db_odbc_discovery";
	int		ret = NOTSUPPORTED, failure = 0, i, j;
	ZBX_ODBC_DBH	dbh;
	ZBX_ODBC_ROW	row;
	char		**column_names, colname[MAX_STRING_LEN];
	struct zbx_json	json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() query:'%s'", __function_name, item->params);

	do
	{
		if (2 != request->nparam)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			break;
		}

		if (SUCCEED != odbc_DBconnect(&dbh, request->params[1], item->username, item->password, CONFIG_TIMEOUT))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, get_last_odbc_strerror()));
			break;
		}

		if (NULL != odbc_DBselect(&dbh, item->params))
		{
			column_names = zbx_malloc(NULL, sizeof(char *) * dbh.col_num);

			if (SUCCEED == get_result_table_col_names(&dbh, column_names, dbh.col_num))
			{
				for (i = 0; i < dbh.col_num && 0 == failure; i++)
				{
					char	*str = column_names[i];
					size_t	str_len = strlen(str);

					for (j = 0; j < str_len; j++ )
					{
						if (FAIL == is_macro_char(toupper(str[j])))
						{
							failure = 1;
							break;
						}

						if (0 != isalpha(str[j]) && 0 == isupper(str[j]))
							str[j] = toupper(str[j]);
					}
				}

				if (0 == failure)
				{
					zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
					zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

					while (NULL != (row = odbc_DBfetch(&dbh)))
					{
						zbx_json_addobject(&json, NULL);

						for (i = 0; i < dbh.col_num; i++)
						{
							zbx_snprintf(colname, MAX_STRING_LEN, "{#%s}",
								column_names[i]);

							zbx_json_addstring(&json, colname, row[i], ZBX_JSON_TYPE_STRING);
						}

						zbx_json_close(&json);
					}

					zbx_json_close(&json);

					SET_STR_RESULT(result, zbx_strdup(NULL, json.buffer));

					zbx_json_free(&json);

					ret = SUCCEED;
				}
			}
			else
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Failed to obtain resulting tables column names"));

			zbx_free(column_names);
		}
		else
			SET_MSG_RESULT(result, zbx_strdup(NULL, get_last_odbc_strerror()));

		odbc_DBclose(&dbh);
	}
	while(0);

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

	if (SUCCEED == odbc_DBconnect(&dbh, request->params[1], item->username, item->password, CONFIG_TIMEOUT))
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
