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
#include "zodbc.h"
#include "checks_db.h"
#include "log.h"

char* get_param_value(char* params, const char* param_name)
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

		/* find '=' symbol */
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
			/* trim right EOL symbols */
			while(*p == '\r' || *p == '\n' || *p == '\0') p--;
			p++;
			
			/* allocate result */
			buf = malloc(p - r + 1);
			memmove(buf, r, p - r);
			buf[p - r] = '\0';

			break;
		}
	}
	
	if(buf == NULL)
	{
		/* allocate result */
		buf = malloc(1);
		*buf = '\0';
	}
	return buf;
}

/******************************************************************************
 *                                                                            *
 * Function: get_value_db                                                     *
 *                                                                            *
 * Purpose: retrieve data from database                                       *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data succesfully retrieved and stored in result    *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_value_db(DB_ITEM *item, AGENT_RESULT *result)
{
	ZBX_ODBC_DBH	dbh;
	ZBX_ODBC_ROW	row;

	char
		*db_name = NULL,
		*db_user = NULL,
		*db_pass = NULL,
		*db_sql = NULL;
	
	int	ret = NOTSUPPORTED;

	char    sql[MAX_STRING_LEN];
	
	init_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "In database monitor: %s", item->key);
	
#ifdef HAVE_ODBC

	#define DB_ODBC_SELECT_KEY "db.odbc.select["
	
	if(strncmp(item->key, DB_ODBC_SELECT_KEY, strlen(DB_ODBC_SELECT_KEY)) == 0)
	{
		snprintf(sql,sizeof(sql)-1,"select " ZBX_SQL_ITEM_SELECT " where i.itemid='%i'", item->itemid);
		zabbix_log(LOG_LEVEL_DEBUG, "SQL [%s]", sql);
		row = DBfetch(DBselect(sql));
		if(row)
		{
			DBget_item_from_db(item,row);
			
			db_name = get_param_value(item->params,"database");
			db_user = get_param_value(item->params,"user");
			db_pass = get_param_value(item->params,"pass");
			db_sql  = get_param_value(item->params,"sql");
			
			if(SUCCEED == odbc_DBconnect(&dbh, db_name, db_user, db_pass))
			{
				row = odbc_DBfetch(odbc_DBselect(&dbh, db_sql));
				if(row)
				{
					if(SUCCEED == set_result_type(result, item->value_type, row[0]))
					{
						zabbix_log(LOG_LEVEL_DEBUG, "Result accepted with type 0x%02X [%s]", item->value_type, row[0]);
						ret = SUCCEED;
					}
				}
				odbc_DBclose(&dbh);
			}

			if(db_name)	free(db_name);
			if(db_user)	free(db_user);
			if(db_pass)	free(db_pass);
			if(db_sql)	free(db_sql);
		}
		return ret;
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

	return NOTSUPPORTED;
}
