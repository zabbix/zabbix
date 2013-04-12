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

#ifdef HAVE_ODBC
#	include "zbxodbc.h"
#endif /* HAVE_ODBC */

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
static char* get_param_value(char* params, const char* param_name)
{
	char
		*p = NULL,
		*l = NULL,
		*n = NULL,
		*r = NULL,
		*buf = NULL;

	for(p = params; p && *p; p++)
	{
		r = NULL;

		/* trim left spaces */
		for(; *p == ' '; p++);

		/* find '=' */
		for(n = p; *n && *n != '\n'; n++)
		{
			if(*n == '=')
			{
				/* trim right spaces */
				for(l = n - 1; *l == ' '; l--);
				l++;

				/* compare parameter name */
				if(l - p != strlen(param_name))		break;
				if(strncmp(p, param_name, l - p))	break;

				r = n+1;
				break;
			}
		}

		/* find EOL */
		for(p = n; *p && *p != '\n'; p++);

		/* allocate result */
		if(r)
		{
			/* trim right EOL characters */
			while(*p == '\r' || *p == '\n' || *p == '\0') p--;
			p++;

			/* allocate result */
			buf = zbx_malloc(buf, p - r + 1);
			memmove(buf, r, p - r);
			buf[p - r] = '\0';

			break;
		}
	}

	if(buf == NULL)
	{
		/* allocate result */
		buf = zbx_malloc(buf, 1);
		*buf = '\0';
	}
	return buf;
}
#endif /* HAVE_ODBC */

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
#ifdef HAVE_ODBC
	ZBX_ODBC_DBH	dbh;
	ZBX_ODBC_ROW	row;

	char
		*db_dsn = NULL,
		*db_user = NULL,
		*db_pass = NULL,
		*db_sql = NULL;
#endif /* HAVE_ODBC */

	int	ret = NOTSUPPORTED;

	init_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "In database monitor: %s", item->key_orig);

#ifdef HAVE_ODBC

	#define DB_ODBC_SELECT_KEY "db.odbc.select["

	if (0 == strncmp(item->key, DB_ODBC_SELECT_KEY, strlen(DB_ODBC_SELECT_KEY)))
	{
		db_dsn = get_param_value(item->params, "DSN");
		db_user = get_param_value(item->params, "user");
		db_pass = get_param_value(item->params, "password");
		db_sql  = get_param_value(item->params, "sql");

		if (SUCCEED == odbc_DBconnect(&dbh, db_dsn, db_user, db_pass))
		{
			if (NULL != (row = odbc_DBfetch(odbc_DBselect(&dbh, db_sql))))
			{
				if (SUCCEED == set_result_type(result, item->value_type, item->data_type, row[0]))
					ret = SUCCEED;
			}
			else
				SET_MSG_RESULT(result, strdup(get_last_odbc_strerror()));

			odbc_DBclose(&dbh);
		}
		else
			SET_MSG_RESULT(result, strdup(get_last_odbc_strerror()));

		zbx_free(db_dsn);
		zbx_free(db_user);
		zbx_free(db_pass);
		zbx_free(db_sql);
	}

#endif /* HAVE_ODBC */

	/*
	 * TODO:
	 *
	 * db.*.select[]
	 * db.*.ping
	 *   ...
	 *
	 */

	return ret;
}
