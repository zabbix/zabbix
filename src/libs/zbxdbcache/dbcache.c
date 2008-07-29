/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
#include "zlog.h"
#include "threads.h"

#include "db.h"
#include "dbcache.h"
#include "mutexs.h"
#include "zbxserver.h"

#define	LOCK_CACHE	zbx_mutex_lock(&cache_lock)
#define	UNLOCK_CACHE	zbx_mutex_unlock(&cache_lock)

#define ZBX_GET_SHM_DBCACHE_KEY(smk_key) 								\
	{if( -1 == (shm_key = ftok(CONFIG_FILE, (int)'c') )) 						\
        { 												\
                zbx_error("Can not create IPC key for path '%s', try to create for path '.' [%s]",	\
				CONFIG_FILE, strerror(errno)); 						\
                if( -1 == (shm_key = ftok(".", (int)'c') )) 						\
                { 											\
                        zbx_error("Can not create IPC key for path '.' [%s]", strerror(errno)); 	\
                        exit(1); 									\
                } 											\
        }}

ZBX_DC_CACHE		*cache = NULL;
static ZBX_MUTEX	cache_lock;

static char		*sql = NULL;
static int		sql_allocated = 65536;

zbx_process_t		zbx_process;

/******************************************************************************
 *                                                                            *
 * Function: DCmass_update_triggers                                           *
 *                                                                            *
 * Purpose: re-calculate and updates values of triggers related to the items  *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksander Vladishev                             *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCmass_update_triggers(ZBX_DC_HISTORY *history, int history_num)
{
	char		*exp;
	char		error[MAX_STRING_LEN];
	int		exp_value;
	DB_TRIGGER	trigger;
	DB_RESULT	result;
	DB_ROW		row;
	int		sql_offset = 0, i;
	ZBX_DC_HISTORY	*h;
	zbx_uint64_t	itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_update_triggers()");

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 1024,
			"select distinct t.triggerid,t.expression,t.description,t.url,t.comments,t.status,t.value,t.priority"
			",t.type,f.itemid from triggers t,functions f,items i where i.status not in (%d) and i.itemid=f.itemid"
			" and t.status=%d and f.triggerid=t.triggerid and f.itemid in (",
			ITEM_STATUS_NOTSUPPORTED,
			TRIGGER_STATUS_ENABLED);

	for (i = 0; i < history_num; i++)
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ZBX_FS_UI64 ",",
				history[i].itemid);

	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ")");
	}

	result = DBselect("%s", sql);

	sql_offset = 0;

	while (NULL != (row = DBfetch(result)))
	{
		trigger.triggerid	= zbx_atoui64(row[0]);
		strscpy(trigger.expression, row[1]);
		strscpy(trigger.description, row[2]);
		trigger.url		= row[3];
		trigger.comments	= row[4];
		trigger.status		= atoi(row[5]);
		trigger.value		= atoi(row[6]);
		trigger.priority	= atoi(row[7]);
		trigger.type		= atoi(row[8]);
		itemid			= zbx_atoui64(row[9]);

		h = NULL;

		for (i = 0; i < history_num; i++)
		{
			if (itemid == history[i].itemid)
			{
				h = &history[i];
				break;
			}
		}

		if (NULL == h)
			continue;

		exp = strdup(trigger.expression);

		if (evaluate_expression(&exp_value, &exp, &trigger, error, sizeof(error)) != 0)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Expression [%s] cannot be evaluated [%s]",
					trigger.expression,
					error);
			zabbix_syslog("Expression [%s] cannot be evaluated [%s]",
					trigger.expression,
					error);
/*			We shouldn't update triggervalue if expressions failed */
/*			DBupdate_trigger_value(&trigger, exp_value, time(NULL), error);*/
		}
		else
			DBupdate_trigger_value(&trigger, exp_value, h->clock, NULL);

		zbx_free(exp);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_update_item                                               *
 *                                                                            *
 * Purpose: update items info after new values is received                    *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev, Aleksander Vladishev           *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_update_item(ZBX_DC_HISTORY *history, int history_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	char		value_esc[ITEM_LASTVALUE_LEN_MAX];
	int		sql_offset = 0, i;
	ZBX_DC_HISTORY	*h;
	double		value_float;
	zbx_uint64_t	value_uint64;

	zabbix_log( LOG_LEVEL_DEBUG, "In DCmass_update_item()");

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 1024,
			"select %s where h.hostid = i.hostid and i.itemid in (",
			ZBX_SQL_ITEM_SELECT,
			TRIGGER_STATUS_ENABLED);

	for (i = 0; i < history_num; i++)
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ZBX_FS_UI64 ",",
				history[i].itemid);

	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ")");
	}

	result = DBselect("%s", sql);

	sql_offset = 0;

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	while (NULL != (row = DBfetch(result)))
	{
		DBget_item_from_db(&item, row);

		h = NULL;

		for (i = 0; i < history_num; i++)
		{
			if (item.itemid == history[i].itemid)
			{
				h = &history[i];
				break;
			}
		}

		if (NULL == h)
			continue;

/*		if (item.type == ITEM_TYPE_ZABBIX_ACTIVE || item.type == ITEM_TYPE_TRAPPER || item.type == ITEM_TYPE_HTTPTEST)*/
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "update items set lastclock=%d",
				h->clock);
/*		else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "update items set nextcheck=%d,lastclock=%d",
					calculate_item_nextcheck(item.itemid, item.type, item.delay, item.delay_flex, h->clock),
					h->clock);*/

		if (item.delta == ITEM_STORE_AS_IS)
		{
			if (h->value_type == ITEM_VALUE_TYPE_FLOAT)
				zbx_snprintf(value_esc, sizeof(value_esc), ZBX_FS_DBL, h->value.value_float);
			else if (h->value_type == ITEM_VALUE_TYPE_UINT64)
				zbx_snprintf(value_esc, sizeof(value_esc), ZBX_FS_UI64, h->value.value_uint64);
			else if (h->value_type == ITEM_VALUE_TYPE_STR
					|| h->value_type == ITEM_VALUE_TYPE_TEXT
					|| h->value_type == ITEM_VALUE_TYPE_LOG)
				DBescape_string(h->value.value_str, value_esc, sizeof(value_esc));
			else
				*value_esc = '\0';

			if (h->value_type == ITEM_VALUE_TYPE_LOG)
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"prevvalue=lastvalue,lastvalue='%s',lastlogsize=%d",
						value_esc,
						h->lastlogsize);
			else
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512, ",prevvalue=lastvalue,lastvalue='%s'",
						value_esc);
		}
		else if (item.delta == ITEM_STORE_SPEED_PER_SECOND)	/* Logic for delta as speed of change */
		{
			if (h->value_type == ITEM_VALUE_TYPE_FLOAT)
			{
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_dbl <= h->value.value_float)
				{
					/* In order to continue normal processing, we assume difference 1 second
					   Otherwise function update_functions and update_triggers won't work correctly*/
					if (h->clock != item.lastclock)
						value_float = (h->value.value_float - item.prevorgvalue_dbl)
								/ (h->clock - item.lastclock);
					else
						value_float = h->value.value_float - item.prevorgvalue_dbl;

					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevvalue=lastvalue,prevorgvalue='" ZBX_FS_DBL "'"
							",lastvalue='" ZBX_FS_DBL "'",
							h->value.value_float,
							value_float);
				}
				else
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevorgvalue='" ZBX_FS_DBL "'",
							h->value.value_float);
			}
			else if (h->value_type == ITEM_VALUE_TYPE_UINT64)
			{
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_uint64 <= h->value.value_uint64)
				{
					if (h->clock != item.lastclock)
						value_uint64 = (h->value.value_uint64 - item.prevorgvalue_uint64)
								/ (h->clock - item.lastclock);
					else
						value_uint64 = h->value.value_uint64 - item.prevorgvalue_uint64;

					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevvalue=lastvalue,prevorgvalue='" ZBX_FS_UI64 "'"
							",lastvalue='" ZBX_FS_UI64 "'",
							h->value.value_uint64,
							value_uint64);
				}
				else
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevorgvalue='" ZBX_FS_DBL "'",
							h->value.value_uint64);
			}
		}
		else if (item.delta == ITEM_STORE_SIMPLE_CHANGE)	/* Real delta: simple difference between values */
		{
			if (h->value_type == ITEM_VALUE_TYPE_FLOAT)
			{
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_dbl <= h->value.value_float)
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevvalue=lastvalue,prevorgvalue='" ZBX_FS_DBL "'"
							",lastvalue='" ZBX_FS_DBL "'",
							h->value.value_float,
							h->value.value_float - item.prevorgvalue_dbl);
				else
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevorgvalue='" ZBX_FS_DBL "'",
							h->value.value_float);
			}
			else if(h->value_type == ITEM_VALUE_TYPE_UINT64)
			{
				if (item.prevorgvalue_null == 0 && item.prevorgvalue_uint64 <= h->value.value_uint64)
				{
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevvalue=lastvalue,prevorgvalue='" ZBX_FS_UI64 "'"
							",lastvalue='" ZBX_FS_UI64 "'",
							h->value.value_uint64,
							h->value.value_uint64 - item.prevorgvalue_uint64);
				}
				else
				{
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
							",prevorgvalue='" ZBX_FS_UI64 "'",
							h->value.value_uint64);
				}
			}
		}

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, " where itemid=" ZBX_FS_UI64 ";\n",
				item.itemid);

/* Update item status if required */
		if (item.status == ITEM_STATUS_NOTSUPPORTED)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Parameter [%s] became supported by agent on host [%s]",
					item.key,
					item.host_name);
			zabbix_syslog("Parameter [%s] became supported by agent on host [%s]",
					item.key,
					item.host_name);

			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
					"update items set status=%d,error='' where itemid=" ZBX_FS_UI64 ";\n",
					ITEM_STATUS_ACTIVE,
					item.itemid);
		}
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_proxy_update_item                                         *
 *                                                                            *
 * Purpose: update items info after new values is received                    *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev, Aleksander Vladishev           *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_proxy_update_item(ZBX_DC_HISTORY *history, int history_num)
{
	int	sql_offset = 0, i;

	zabbix_log( LOG_LEVEL_DEBUG, "In DCmass_proxy_update_item()");

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_LOG)
		{
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
					"update items set lastlogsize=%d where itemid=" ZBX_FS_UI64 ";\n",
					history[i].lastlogsize,
					history[i].itemid);
		}
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_function_update                                           *
 *                                                                            *
 * Purpose: update functions lastvalue after new values is received           *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksander Vladishev                             *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void DCmass_function_update(ZBX_DC_HISTORY *history, int history_num)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_FUNCTION	function;
	DB_ITEM		item;
	char		*lastvalue;
	char		value[MAX_STRING_LEN], value_esc[MAX_STRING_LEN];
	int		sql_offset = 0, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_function_update()");

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 1024,
			"select distinct %s,f.function,f.parameter,f.itemid,f.lastvalue from %s,functions f,triggers t"
			" where f.itemid=i.itemid and h.hostid=i.hostid and f.triggerid=t.triggerid and t.status in (%d)"
			" and f.itemid in (",
			ZBX_SQL_ITEM_FIELDS,
			ZBX_SQL_ITEM_TABLES,
			TRIGGER_STATUS_ENABLED);

	for (i = 0; i < history_num; i++)
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ZBX_FS_UI64 ",",
				history[i].itemid);

	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 32, ")");
	}

	result = DBselect("%s", sql);

	sql_offset = 0;

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

	while (NULL != (row = DBfetch(result)))
	{
		DBget_item_from_db(&item, row);

		function.function	= row[ZBX_SQL_ITEM_FIELDS_NUM];
		function.parameter	= row[ZBX_SQL_ITEM_FIELDS_NUM + 1];
		function.itemid		= zbx_atoui64(row[ZBX_SQL_ITEM_FIELDS_NUM + 2]);
/*		It is not required to check lastvalue for NULL here */
		lastvalue		= row[ZBX_SQL_ITEM_FIELDS_NUM + 3];

		if (FAIL == evaluate_function(value, &item, function.function, function.parameter))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Evaluation failed for function:%s",
					function.function);
			continue;
		}

		/* Update only if lastvalue differs from new one */
		if (DBis_null(lastvalue) == SUCCEED || strcmp(lastvalue, value) != 0)
		{
			DBescape_string(value, value_esc, MAX_STRING_LEN);
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 1024,
					"update functions set lastvalue='%s' where itemid=" ZBX_FS_UI64
					" and function='%s' and parameter='%s';\n",
					value_esc,
					function.itemid,
					function.function,
					function.parameter);
		}
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_add_history                                               *
 *                                                                            *
 * Purpose: inserting new history data after new values is received           *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_add_history(ZBX_DC_HISTORY *history, int history_num)
{
	int		sql_offset = 0, i;
	char		value_esc[MAX_STRING_LEN], *value_esc_dyn;
	int		history_text_num, history_log_num;
	zbx_uint64_t	id;
#ifdef HAVE_MYSQL
	int		tmp_offset;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_add_history()");

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

/*
 * history
 */
#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
			"insert into history values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_FLOAT)
		{
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d," ZBX_FS_DBL "),",
					history[i].itemid,
					history[i].clock,
					history[i].value.value_float);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history values "
					"(" ZBX_FS_UI64 ",%d," ZBX_FS_DBL ");\n",
					history[i].itemid,
					history[i].clock,
					history[i].value.value_float);
#endif
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

	if (CONFIG_NODE_NOHISTORY == 0 && CONFIG_MASTER_NODEID > 0)
	{
#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				"insert into history_sync (nodeid,itemid,clock,value) values ");
#endif

		for (i = 0; i < history_num; i++)
		{
			if (history[i].value_type == ITEM_VALUE_TYPE_FLOAT)
			{
#ifdef HAVE_MYSQL
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_DBL "),",
						get_nodeid_by_id(history[i].itemid),
						history[i].itemid,
						history[i].clock,
						history[i].value.value_float);
#else
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"insert into history_sync (nodeid,itemid,clock,value) values "
						"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_DBL ");\n",
						get_nodeid_by_id(history[i].itemid),
						history[i].itemid,
						history[i].clock,
						history[i].value.value_float);
#endif
			}
		}

#ifdef HAVE_MYSQL
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

/*
 * history_uint
 */
#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
			"insert into history_uint values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_UINT64)
		{
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d," ZBX_FS_UI64 "),",
					history[i].itemid,
					history[i].clock,
					history[i].value.value_uint64);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_uint values "
					"(" ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ");\n",
					history[i].itemid,
					history[i].clock,
					history[i].value.value_uint64);
#endif
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

	if (CONFIG_NODE_NOHISTORY == 0 && CONFIG_MASTER_NODEID > 0)
	{
#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				"insert into history_uint_sync (nodeid,itemid,clock,value) values ");
#endif

		for (i = 0; i < history_num; i++)
		{
			if (history[i].value_type == ITEM_VALUE_TYPE_UINT64)
			{
#ifdef HAVE_MYSQL
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_UI64 "),",
						get_nodeid_by_id(history[i].itemid),
						history[i].itemid,
						history[i].clock,
						history[i].value.value_uint64);
#else
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"insert into history_uint_sync (nodeid,itemid,clock,value) values "
						"(%d," ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ");\n",
						get_nodeid_by_id(history[i].itemid),
						history[i].itemid,
						history[i].clock,
						history[i].value.value_uint64);
#endif
			}
		}

#ifdef HAVE_MYSQL
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

/*
 * history_str
 */
#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
			"insert into history_str values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_STR)
		{
			DBescape_string(history[i].value.value_str, value_esc, sizeof(value_esc));
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d,'%s'),",
					history[i].itemid,
					history[i].clock,
					value_esc);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into history_str values "
					"(" ZBX_FS_UI64 ",%d,'%s');\n",
					history[i].itemid,
					history[i].clock,
					value_esc);
#endif
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

	if (CONFIG_NODE_NOHISTORY == 0 && CONFIG_MASTER_NODEID > 0)
	{
#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				"insert into history_str_sync (nodeid,itemid,clock,value) values ");
#endif

		for (i = 0; i < history_num; i++)
		{
			if (history[i].value_type == ITEM_VALUE_TYPE_STR)
			{
				DBescape_string(history[i].value.value_str, value_esc, sizeof(value_esc));
#ifdef HAVE_MYSQL
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"(%d," ZBX_FS_UI64 ",%d,'%s'),",
						get_nodeid_by_id(history[i].itemid),
						history[i].itemid,
						history[i].clock,
						value_esc);
#else
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
						"insert into history_str_sync (nodeid,itemid,clock,value) values "
						"(%d," ZBX_FS_UI64 ",%d,'%s');\n",
						get_nodeid_by_id(history[i].itemid),
						history[i].itemid,
						history[i].clock,
						value_esc);
#endif
			}
		}

#ifdef HAVE_MYSQL
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

	history_text_num = 0;
	history_log_num = 0;

	for (i = 0; i < history_num; i++)
		if (history[i].value_type == ITEM_VALUE_TYPE_TEXT)
			history_text_num++;
		else if (history[i].value_type == ITEM_VALUE_TYPE_LOG)
			history_log_num++;

/*
 * history_text
 */
	if (history_text_num > 0)
	{
		id = DBget_maxid_num("history_text", "id", history_text_num);

#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				"insert into history_text values ");
#endif

		for (i = 0; i < history_num; i++)
		{
			if (history[i].value_type == ITEM_VALUE_TYPE_TEXT)
			{
				value_esc_dyn = DBdyn_escape_string(history[i].value.value_str);
#ifdef HAVE_MYSQL
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s'),",
						id,
						history[i].itemid,
						history[i].clock,
						value_esc_dyn);
#else
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
						"insert into history_text values "
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,'%s');\n",
						id,
						history[i].itemid,
						history[i].clock,
						value_esc_dyn);
#endif
				zbx_free(value_esc_dyn);
				id++;
			}
		}

#ifdef HAVE_MYSQL
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

/*
 * history_log
 */
	if (history_log_num > 0)
	{
		id = DBget_maxid_num("history_log", "id", history_log_num);

#ifdef HAVE_MYSQL
		tmp_offset = sql_offset;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				"insert into history_log values ");
#endif

		for (i = 0; i < history_num; i++)
		{
			if (history[i].value_type == ITEM_VALUE_TYPE_LOG)
			{
				value_esc_dyn = DBdyn_escape_string(history[i].value.value_str);
#ifdef HAVE_MYSQL
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s'),",
						id,
						history[i].itemid,
						history[i].clock,
						history[i].timestamp,
						history[i].source,
						history[i].severity,
						value_esc_dyn);
#else
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
						"insert into history_log values "
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s');\n",
						id,
						history[i].itemid,
						history[i].clock,
						history[i].timestamp,
						history[i].source,
						history[i].severity,
						value_esc_dyn);
#endif
				zbx_free(value_esc_dyn);
				id++;
			}
		}

#ifdef HAVE_MYSQL
		if (sql[sql_offset - 1] == ',')
		{
			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
		}
		else
			sql_offset = tmp_offset;
#endif
	}

#ifdef HAVE_MYSQL
	sql[sql_offset] = '\0';
#endif

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

/******************************************************************************
 *                                                                            *
 * Function: DCmass_proxy_add_history                                         *
 *                                                                            *
 * Purpose: inserting new history data after new values is received           *
 *                                                                            *
 * Parameters: history - array of history data                                *
 *             history_num - number of history structures                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCmass_proxy_add_history(ZBX_DC_HISTORY *history, int history_num)
{
	int		sql_offset = 0, i;
	char		value_esc[MAX_STRING_LEN], *value_esc_dyn;
#ifdef HAVE_MYSQL
	int		tmp_offset;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In DCmass_proxy_add_history()");

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
			"insert into proxy_history (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_FLOAT)
		{
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d,'" ZBX_FS_DBL "'),",
					history[i].itemid,
					history[i].clock,
					history[i].value.value_float);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into proxy_history (itemid,clock,value) values "
					"(" ZBX_FS_UI64 ",%d,'" ZBX_FS_DBL "');\n",
					history[i].itemid,
					history[i].clock,
					history[i].value.value_float);
#endif
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
			"insert into proxy_history (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_UINT64)
		{
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d,'" ZBX_FS_UI64 "'),",
					history[i].itemid,
					history[i].clock,
					history[i].value.value_uint64);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into proxy_history (itemid,clock,value) values "
					"(" ZBX_FS_UI64 ",%d,'" ZBX_FS_UI64 "');\n",
					history[i].itemid,
					history[i].clock,
					history[i].value.value_uint64);
#endif
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
			"insert into proxy_history (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_STR)
		{
			DBescape_string(history[i].value.value_str, value_esc, sizeof(value_esc));
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"(" ZBX_FS_UI64 ",%d,'%s'),",
					history[i].itemid,
					history[i].clock,
					value_esc);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
					"insert into proxy_history (itemid,clock,value) values " 
					"(" ZBX_FS_UI64 ",%d,'%s');\n",
					history[i].itemid,
					history[i].clock,
					value_esc);
#endif
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
			"insert into proxy_history (itemid,clock,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_TEXT)
		{
			value_esc_dyn = DBdyn_escape_string(history[i].value.value_str);
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
					"(" ZBX_FS_UI64 ",%d,'%s'),",
					history[i].itemid,
					history[i].clock,
					value_esc_dyn);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
					"insert into proxy_history (itemid,clock,value) values "
					"(" ZBX_FS_UI64 ",%d,'%s');\n",
					history[i].itemid,
					history[i].clock,
					value_esc_dyn);
#endif
			zbx_free(value_esc_dyn);
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	tmp_offset = sql_offset;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				"insert into proxy_history (itemid,clock,timestamp,source,severity,value) values ");
#endif

	for (i = 0; i < history_num; i++)
	{
		if (history[i].value_type == ITEM_VALUE_TYPE_LOG)
		{
			value_esc_dyn = DBdyn_escape_string(history[i].value.value_str);
#ifdef HAVE_MYSQL
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s'),",
					history[i].itemid,
					history[i].clock,
					history[i].timestamp,
					history[i].source,
					history[i].severity,
					value_esc_dyn);
#else
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
					"insert into proxy_history (itemid,cloc,timestamp,source,severityk,value) values "
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s');\n",
					history[i].itemid,
					history[i].clock,
					history[i].timestamp,
					history[i].source,
					history[i].severity,
					value_esc_dyn);
#endif
			zbx_free(value_esc_dyn);
		}
	}

#ifdef HAVE_MYSQL
	if (sql[sql_offset - 1] == ',')
	{
		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ";\n");
	}
	else
		sql_offset = tmp_offset;
#endif

#ifdef HAVE_MYSQL
	sql[sql_offset] = '\0';
#endif

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		DBexecute("%s", sql);
}

static int DCitem_already_exists(ZBX_DC_HISTORY *history, int history_num, zbx_uint64_t itemid)
{
	int	i;

	for (i = 0; i < history_num; i++)
		if (itemid == history[i].itemid)
			return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync                                                           *
 *                                                                            *
 * Purpose: writes updates and new data from pool to database                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: number of synced values                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	DCsync_history(int sync_type)
{
	static ZBX_DC_HISTORY	history[ZBX_SYNC_MAX];
	int			i, j, history_num, n, f;
	int			syncs;
	int			total_num = 0;
/*	double			sec;
	sec = zbx_time();*/

	zabbix_log(LOG_LEVEL_DEBUG, "In DCsync_history(history_first:%d history_num:%d)",
			cache->history_first,
			cache->history_num);

	if (0 == cache->history_num)
		return 0;

	syncs = cache->history_num / ZBX_SYNC_MAX;

	do
	{
		LOCK_CACHE;

		history_num = 0;
		n = cache->history_num;
		f = cache->history_first;

		while (n > 0 && history_num < ZBX_SYNC_MAX)
		{
			if (zbx_process == ZBX_PROCESS_PROXY || FAIL == DCitem_already_exists(history, history_num, cache->history[f].itemid))
			{
				memcpy(&history[history_num], &cache->history[f], sizeof(ZBX_DC_HISTORY));
				if (history[history_num].value_type == ITEM_VALUE_TYPE_STR
						|| history[history_num].value_type == ITEM_VALUE_TYPE_TEXT
						|| history[history_num].value_type == ITEM_VALUE_TYPE_LOG)
				{
					history[history_num].value.value_str = strdup(cache->history[f].value.value_str);

					if (history[history_num].value_type == ITEM_VALUE_TYPE_LOG)
						history[history_num].source = strdup(cache->history[f].source);
				}

				for (j = f; j != cache->history_first; j = (j == 0 ? ZBX_HISTORY_SIZE : j) - 1)
				{
					i = (j == 0 ? ZBX_HISTORY_SIZE : j) - 1;
					memcpy(&cache->history[j], &cache->history[i], sizeof(ZBX_DC_HISTORY));
				}

				cache->history_num--;
				cache->history_first++;
				cache->history_first = cache->history_first % ZBX_HISTORY_SIZE;

				history_num++;
			}

			n--;
			f++;
			f = f % ZBX_HISTORY_SIZE;
		}

		UNLOCK_CACHE;

		if (0 == history_num)
			break;

		if (NULL == sql)
			sql = zbx_malloc(sql, sql_allocated);

		DBbegin();

		if (zbx_process == ZBX_PROCESS_SERVER)
		{
			DCmass_add_history(history, history_num);
			DCmass_update_item(history, history_num);
			DCmass_function_update(history, history_num);
			DCmass_update_triggers(history, history_num);
		}
		else
		{
			DCmass_proxy_add_history(history, history_num);
			DCmass_proxy_update_item(history, history_num);
		}

		DBcommit();

		for (i = 0; i < history_num; i ++)
		{
			if (history[i].value_type == ITEM_VALUE_TYPE_STR
					|| history[i].value_type == ITEM_VALUE_TYPE_TEXT
					|| history[i].value_type == ITEM_VALUE_TYPE_LOG)
			{
				zbx_free(history[i].value.value_str);

				if (history[i].value_type == ITEM_VALUE_TYPE_LOG)
					zbx_free(history[i].source);
			}
		}
		total_num += history_num;
	} while (--syncs > 0 || sync_type == ZBX_SYNC_FULL);

/*	zabbix_log(LOG_LEVEL_CRIT, "DCsync_history first:%6d; cache:%6d; synced:%4d; spent " ZBX_FS_DBL " seconds",
			cache->history_first,
			cache->history_num,
			total_num,
			zbx_time() - sec);*/
	return total_num;
}

/******************************************************************************
 *                                                                            *
 * Function: DCvacuum_text                                                    *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void DCvacuum_text()
{
	char	*first_text;
	int	i, index;
	size_t	offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCvacuum_text()");

	/* vacuumng text buffer */
	first_text = NULL;
	for (i = 0; i < cache->history_num; i++)
	{
		index = (cache->history_first + i) % ZBX_HISTORY_SIZE;
		if (cache->history[index].value_type == ITEM_VALUE_TYPE_STR
				|| cache->history[index].value_type == ITEM_VALUE_TYPE_TEXT
				|| cache->history[index].value_type == ITEM_VALUE_TYPE_LOG)
		{
			first_text = cache->history[index].value.value_str;
			break;
		}
	}

	if (NULL != first_text)
	{
		offset = first_text - cache->text;
		for (i = 0; i < cache->history_num; i++)
		{
			index = (cache->history_first + i) % ZBX_HISTORY_SIZE;
			if (cache->history[index].value_type == ITEM_VALUE_TYPE_STR
					|| cache->history[index].value_type == ITEM_VALUE_TYPE_TEXT
					|| cache->history[index].value_type == ITEM_VALUE_TYPE_LOG)
			{
				cache->history[index].value.value_str -= offset;

				if (cache->history[index].value_type == ITEM_VALUE_TYPE_LOG)
					cache->history[index].source -= offset;
			}
		}
		cache->last_text -= offset;
	} else
		cache->last_text = cache->text;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_history_ptr                                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_HISTORY *DCget_history_ptr(zbx_uint64_t itemid, size_t text_len)
{
	ZBX_DC_HISTORY	*history;
	int		index;
	size_t		free_len;

retry:
	if (cache->history_num >= ZBX_HISTORY_SIZE)
	{
		UNLOCK_CACHE;

		zabbix_log(LOG_LEVEL_DEBUG, "History buffer is full. Sleeping for 1 second.");
		sleep(1);

		LOCK_CACHE;

		goto retry;
	}

	if (text_len > sizeof(cache->text))
	{
		zabbix_log(LOG_LEVEL_ERR, "Insufficient shared memory");
		exit(-1);
	}

	free_len = sizeof(cache->text) - (cache->last_text - cache->text);

	if (text_len > free_len)
	{
		DCvacuum_text();

		free_len = sizeof(cache->text) - (cache->last_text - cache->text);

		if (text_len > free_len)
		{
			UNLOCK_CACHE;

			zabbix_log(LOG_LEVEL_DEBUG, "History text buffer is full. Sleeping for 1 second.");
			sleep(1);

			LOCK_CACHE;

			goto retry;
		}
	}

	index = (cache->history_first + cache->history_num) % ZBX_HISTORY_SIZE;
	history = &cache->history[index];

	cache->history_num++;

	return history;
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_trend_nearestindex                                         *
 *                                                                            *
 * Purpose: find nearest index by itemid in array of ZBX_DC_TREND             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	DCget_trend_nearestindex(zbx_uint64_t itemid)
{
	int	first_index, last_index, index;

	if (cache->trends_num == 0)
		return 0;

	first_index = 0;
	last_index = cache->trends_num - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (cache->trends[index].itemid == itemid)
			return index;
		else if (last_index == first_index)
		{
			if (cache->trends[index].itemid < itemid)
				index++;
			return index;
		}
		else if (cache->trends[index].itemid < itemid)
			first_index = index + 1;
		else
			last_index = index;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCget_trend                                                      *
 *                                                                            *
 * Purpose: find existing or add new structure and return pointer             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: pointer to a new structure or NULL if array is full          *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static ZBX_DC_TREND	*DCget_trend(zbx_uint64_t itemid)
{
	int	index;

	index = DCget_trend_nearestindex(itemid);
	if (index < cache->trends_num && cache->trends[index].itemid == itemid)
		return &cache->trends[index];

	if (cache->trends_num == ZBX_TREND_SIZE)
		return NULL;

	memmove(&cache->trends[index + 1], &cache->trends[index], sizeof(ZBX_DC_TREND) * (cache->trends_num - index));
	memset(&cache->trends[index], 0, sizeof(ZBX_DC_TREND));
	cache->trends[index].itemid = itemid;
	cache->trends_num++;

	return &cache->trends[index];
}

/******************************************************************************
 *                                                                            *
 * Function: DCflush_trend                                                    *
 *                                                                            *
 * Purpose: flush trend to the database                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCflush_trend(ZBX_DC_TREND *trend)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		num;
	trend_value_t	value_min, value_avg, value_max;

	switch (trend->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			result = DBselect("select num,value_min,value_avg,value_max from trends"
					" where itemid=" ZBX_FS_UI64 " and clock=%d",
					trend->itemid,
					trend->clock);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			result = DBselect("select num,value_min,value_avg,value_max from trends_uint"
					" where itemid=" ZBX_FS_UI64 " and clock=%d",
					trend->itemid,
					trend->clock);
			break;
		default:
			zabbix_log(LOG_LEVEL_CRIT, "Invalid value type for trends.");
			exit(-1);
	}

	if (NULL != (row = DBfetch(result)))
	{
		num = atoi(row[0]);
		switch (trend->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				value_min.value_float = atof(row[1]);
				value_avg.value_float = atof(row[2]);
				value_max.value_float = atof(row[3]);

				if (value_min.value_float < trend->value_min.value_float)
					trend->value_min.value_float = value_min.value_float;
				if (value_max.value_float > trend->value_max.value_float)
					trend->value_max.value_float = value_max.value_float;
				trend->value_avg.value_float = (trend->num * trend->value_avg.value_float
						+ num * value_avg.value_float) / (trend->num + num);
				trend->num += num;

				DBexecute("update trends set num=%d,value_min=" ZBX_FS_DBL ",value_avg=" ZBX_FS_DBL
						",value_max=" ZBX_FS_DBL " where itemid=" ZBX_FS_UI64 " and clock=%d",
						trend->num,
						trend->value_min.value_float,
						trend->value_avg.value_float,
						trend->value_max.value_float,
						trend->itemid,
						trend->clock);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				value_min.value_uint64 = zbx_atoui64(row[1]);
				value_avg.value_uint64 = zbx_atoui64(row[2]);
				value_max.value_uint64 = zbx_atoui64(row[3]);

				if (value_min.value_uint64 < trend->value_min.value_uint64)
					trend->value_min.value_uint64 = value_min.value_uint64;
				if (value_max.value_uint64 > trend->value_max.value_uint64)
					trend->value_max.value_uint64 = value_max.value_uint64;
				trend->value_avg.value_uint64 = (trend->num * trend->value_avg.value_uint64
						+ num * value_avg.value_uint64) / (trend->num + num);
				trend->num += num;
				
				DBexecute("update trends_uint set num=%d,value_min=" ZBX_FS_UI64 ",value_avg=" ZBX_FS_UI64
						",value_max=" ZBX_FS_UI64 " where itemid=" ZBX_FS_UI64 " and clock=%d",
						trend->num,
						trend->value_min.value_uint64,
						trend->value_avg.value_uint64,
						trend->value_max.value_uint64,
						trend->itemid,
						trend->clock);
				break;
		}
	}
	else
	{
		switch (trend->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				DBexecute("insert into trends (itemid,clock,num,value_min,value_avg,value_max)"
						" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_DBL "," ZBX_FS_DBL "," ZBX_FS_DBL ")",
						trend->itemid,
						trend->clock,
						trend->num,
						trend->value_min.value_float,
						trend->value_avg.value_float,
						trend->value_max.value_float);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				DBexecute("insert into trends_uint (itemid,clock,num,value_min,value_avg,value_max)"
						" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
						trend->itemid,
						trend->clock,
						trend->num,
						trend->value_min.value_uint64,
						trend->value_avg.value_uint64,
						trend->value_max.value_uint64);
				break;
		}
	}
	DBfree_result(result);

	trend->clock = 0;
	trend->num = 0;
	memset(&trend->value_min, 0, sizeof(trend_value_t));
	memset(&trend->value_avg, 0, sizeof(trend_value_t));
	memset(&trend->value_max, 0, sizeof(trend_value_t));
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_trends                                                    *
 *                                                                            *
 * Purpose: flush all trends to the database                                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCsync_trends()
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In DCsync_trends(trends_num: %d)",
			cache->trends_num);
	
	for (i = 0; i < cache->trends_num; i ++)
		DCflush_trend(&cache->trends[i]);

	cache->trends_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "End of DCsync_trends()");
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_trend                                                      *
 *                                                                            *
 * Purpose: add new value to the trends                                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCadd_trend(ZBX_DC_HISTORY *history)
{
	ZBX_DC_TREND	*trend = NULL, trend_static;
	int		clock;

	clock = history->clock - history->clock % 3600;
	
	if (NULL != (trend = DCget_trend(history->itemid)))
	{
		if (trend->num > 0 && (trend->clock != clock || trend->value_type != history->value_type))
			DCflush_trend(trend);

		trend->value_type	= history->value_type;
		trend->clock		= clock;

		switch (trend->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				if (trend->num == 0 || history->value.value_float < trend->value_min.value_float)
					trend->value_min.value_float = history->value.value_float;
				if (trend->num == 0 || history->value.value_float > trend->value_max.value_float)
					trend->value_max.value_float = history->value.value_float;
				trend->value_avg.value_float = (trend->num * trend->value_avg.value_float
						+ history->value.value_float) / (trend->num + 1);
				trend->num++;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				if (trend->num == 0 || history->value.value_uint64 < trend->value_min.value_uint64)
					trend->value_min.value_uint64 = history->value.value_uint64;
				if (trend->num == 0 || history->value.value_uint64 > trend->value_max.value_uint64)
					trend->value_max.value_uint64 = history->value.value_uint64;
				trend->value_avg.value_uint64 = (trend->num * trend->value_avg.value_uint64
						+ history->value.value_uint64) / (trend->num + 1);
				trend->num++;
				break;
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Insufficient space for trends. Flushing to disk.");

		trend_static.itemid = history->itemid;
		trend_static.clock = clock;
		trend_static.value_type = history->value_type;
		trend_static.num = 1;
		switch (trend_static.value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				trend_static.value_min.value_float = history->value.value_float;
				trend_static.value_avg.value_float = history->value.value_float;
				trend_static.value_max.value_float = history->value.value_float;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				trend_static.value_min.value_uint64 = history->value.value_uint64;
				trend_static.value_avg.value_uint64 = history->value.value_uint64;
				trend_static.value_max.value_uint64 = history->value.value_uint64;
				break;
		}

		DCflush_trend(trend);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history                                                    *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCadd_history(zbx_uint64_t itemid, double value, int clock)
{
	ZBX_DC_HISTORY	*history;

	LOCK_CACHE;

	history = DCget_history_ptr(itemid, 0);

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_FLOAT;
	history->value.value_float	= value;

	DCadd_trend(history);

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history_uint                                               *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCadd_history_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock)
{
	ZBX_DC_HISTORY	*history;

	LOCK_CACHE;

	history = DCget_history_ptr(itemid, 0);

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_UINT64;
	history->value.value_uint64	= value;

	DCadd_trend(history);

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history_str                                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCadd_history_str(zbx_uint64_t itemid, char *value, int clock)
{
	ZBX_DC_HISTORY	*history;
	size_t		len;

	LOCK_CACHE;

	len = strlen(value) + 1;
	history = DCget_history_ptr(itemid, len);

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_STR;
	history->value.value_str	= cache->last_text;
	zbx_strlcpy(cache->last_text, value, len);
	cache->last_text		+= len;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history_text                                               *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCadd_history_text(zbx_uint64_t itemid, char *value, int clock)
{
	ZBX_DC_HISTORY	*history;
	size_t		len;

	LOCK_CACHE;

	len = strlen(value) + 1;
	history = DCget_history_ptr(itemid, len);

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_TEXT;
	history->value.value_str	= cache->last_text;
	zbx_strlcpy(cache->last_text, value, len);
	cache->last_text		+= len;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: DCadd_history_log                                                *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alekasander Vladishev                                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DCadd_history_log(zbx_uint64_t itemid, char *value, int clock, int timestamp, char *source, int severity, int lastlogsize)
{
	ZBX_DC_HISTORY	*history;
	size_t		len1, len2;

	LOCK_CACHE;

	len1 = strlen(value) + 1;
	len2 = strlen(source) + 1;
	history = DCget_history_ptr(itemid, len1 + len2);

	history->itemid			= itemid;
	history->clock			= clock;
	history->value_type		= ITEM_VALUE_TYPE_LOG;
	history->value.value_str	= cache->last_text;
	zbx_strlcpy(cache->last_text, value, len1);
	cache->last_text		+= len1;
	history->timestamp		= timestamp;
	history->source			= cache->last_text;
	zbx_strlcpy(cache->last_text, source, len2);
	cache->last_text		+= len2;
	history->severity		= severity;
	history->lastlogsize		= lastlogsize;

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Function: init_database_cache                                              *
 *                                                                            *
 * Purpose: Allocate shared memory for database cache                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	init_database_cache(zbx_process_t p)
{
#define ZBX_MAX_ATTEMPTS 10
	int	attempts = 0;

	key_t	shm_key;
	int	shm_id;

	zbx_process = p;

	ZBX_GET_SHM_DBCACHE_KEY(shm_key);

lbl_create:
	if ( -1 == (shm_id = shmget(shm_key, sizeof(ZBX_DC_CACHE), IPC_CREAT | IPC_EXCL | 0666 /* 0022 */)) )
	{
		if( EEXIST == errno )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Shared memory already exists for database cache, trying to recreate.");

			shm_id = shmget(shm_key, 0 /* get reference */, 0666 /* 0022 */);

			shmctl(shm_id, IPC_RMID, 0);
			if ( ++attempts > ZBX_MAX_ATTEMPTS )
			{
				zabbix_log(LOG_LEVEL_CRIT, "Can't recreate shared memory for database cache. [too many attempts]");
				exit(1);
			}
			if ( attempts > (ZBX_MAX_ATTEMPTS / 2) )
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Wait 1 sec for next attemt of database cache memory allocation.");
				sleep(1);
			}
			goto lbl_create;
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "Can't allocate shared memory for collector. [%s]",strerror(errno));
			exit(1);
		}
	}
	
	cache = shmat(shm_id, 0, 0);

	if ((void*)(-1) == cache)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Can't attach shared memory for database cache. [%s]",strerror(errno));
		exit(FAIL);
	}

	if(ZBX_MUTEX_ERROR == zbx_mutex_create_force(&cache_lock, ZBX_MUTEX_CACHE))
	{
		zbx_error("Unable to create mutex for database cache");
		exit(FAIL);
	}

	cache->last_text = cache->text;
}

/******************************************************************************
 *                                                                            *
 * Function: DCsync_all                                                       *
 *                                                                            *
 * Purpose: writes updates and new data from pool and cache data to database  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	DCsync_all()
{
	zabbix_log(LOG_LEVEL_DEBUG,"In DCsync_all()");

	DCsync_history(ZBX_SYNC_FULL);
	if (zbx_process == ZBX_PROCESS_SERVER)
		DCsync_trends();

	zabbix_log(LOG_LEVEL_DEBUG,"End of DCsync_all()");
}

/******************************************************************************
 *                                                                            *
 * Function: free_database_cache                                              *
 *                                                                            *
 * Purpose: Free memory aloccated for database cache                          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	free_database_cache()
{

	key_t	shm_key;
	int	shm_id;

	zabbix_log(LOG_LEVEL_DEBUG, "In free_database_cache()");

	if (NULL == cache)
		return;

	DCsync_all();

	LOCK_CACHE;
	
	ZBX_GET_SHM_DBCACHE_KEY(shm_key);

	shm_id = shmget(shm_key, sizeof(ZBX_DC_CACHE), 0);

	if (-1 == shm_id)
	{
		zabbix_log(LOG_LEVEL_ERR, "Can't find shared memory for database cache. [%s]",strerror(errno));
		exit(1);
	}

	shmctl(shm_id, IPC_RMID, 0);

	cache = NULL;

	UNLOCK_CACHE;

	zbx_mutex_destroy(&cache_lock);

	zabbix_log(LOG_LEVEL_DEBUG,"End of free_database_cache()");
}
