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

#include "common.h"

#ifdef HAVE_ODBC
#	include "zbxodbc.h"
#endif

#include "checks_db.h"
#include "log.h"

#ifdef HAVE_ODBC
/******************************************************************************
 *                                                                            *
 * Function: get_param_value                                                  *
 *                                                                            *
 * Purpose: retrieve parameter value by name                                  *
 *                                                                            *
 * Parameters: params - list of params                                        *
 *             param_name - name of requested parameter                       *
 *                                                                            *
 * Return value: NULL - if parameter missing param_name,                      *
 *               else return value in new allocated memory                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: this function allocates memory, required zbx_free for result!!!  *
 *           one parameter format: param1_name=param1_value                   *
 *           parameters separated by '\n'                                     *
 *                                                                            *
 ******************************************************************************/
static char	*get_param_value(char *params, const char *param_name)
{
	char	*p, *l, *n, *r, *buf = NULL;

	assert(NULL != params);

	for (p = params; '\0' != *p; p++)
	{
		r = NULL;

		/* trim left spaces */
		for (; 0 != isspace(*p); p++)
			;

		/* find '=' */
		for (n = p; '\0' != *n && '\n' != *n; n++)
		{
			if ('=' == *n)
			{
				/* trim right spaces */
				for (l = n - 1; 0 != isspace(*l); l--)
					;

				l++;

				/* compare parameter name */
				if (l - p != strlen(param_name) || 0 != strncmp(p, param_name, l - p))
					break;

				r = n + 1;

				break;
			}
		}

		/* find EOL */
		for (p = n; '\0' != *p && '\n' != *p; p++)
			;

		/* allocate result */
		if (NULL != r)
		{
			/* trim right EOL characters */
			while ('\r' == *p || '\n' == *p || '\0' == *p)
				p--;

			p++;

			/* allocate result */
			buf = zbx_malloc(buf, p - r + 1);
			memmove(buf, r, p - r);
			buf[p - r] = '\0';

			break;
		}
	}

	if (NULL == buf)
		buf = zbx_strdup(buf, "");

	return buf;
}
#endif	/* HAVE_ODBC */

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
#ifdef HAVE_ODBC
	ZBX_ODBC_DBH	dbh;
	ZBX_ODBC_ROW	row;
	char		*db_dsn = NULL, *db_user = NULL, *db_pass = NULL, *db_sql = NULL;
#endif
	int		ret = NOTSUPPORTED;

	init_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key_orig:'%s'", __function_name, item->key_orig);

#ifdef HAVE_ODBC

#define DB_ODBC_SELECT_KEY	"db.odbc.select["

	if (0 == strncmp(item->key, DB_ODBC_SELECT_KEY, strlen(DB_ODBC_SELECT_KEY)))
	{
		db_dsn = get_param_value(item->params, "DSN");
		db_user = get_param_value(item->params, "user");
		db_pass = get_param_value(item->params, "password");
		db_sql = get_param_value(item->params, "sql");

		if (SUCCEED == odbc_DBconnect(&dbh, db_dsn, db_user, db_pass, CONFIG_TIMEOUT))
		{
			if (NULL != odbc_DBselect(&dbh, db_sql))
			{
				if (NULL != (row = odbc_DBfetch(&dbh)))
				{
					if (NULL == row[0])
					{
						SET_MSG_RESULT(result, zbx_strdup(NULL, "SQL query returned NULL "
								"value."));
					}
					else if (SUCCEED == set_result_type(result, item->value_type, item->data_type,
							row[0]))
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
						SET_MSG_RESULT(result, zbx_strdup(NULL, "SQL query returned empty "
								"result."));
				}
			}
			else
				SET_MSG_RESULT(result, zbx_strdup(NULL, get_last_odbc_strerror()));

			odbc_DBclose(&dbh);
		}
		else
			SET_MSG_RESULT(result, zbx_strdup(NULL, get_last_odbc_strerror()));

		zbx_free(db_dsn);
		zbx_free(db_user);
		zbx_free(db_pass);
		zbx_free(db_sql);
	}
#undef DB_ODBC_SELECT_KEY

#endif	/* HAVE_ODBC */

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
