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

#include "comms.h"
#include "db.h"
#include "dbcache.h"
#include "log.h"
#include "zlog.h"

#include "zbxserver.h"
#include "evalfunc.h"
#include "expression.h"

/******************************************************************************
 *                                                                            *
 * Function: update_functions                                                 *
 *                                                                            *
 * Purpose: re-calculate and updates values of functions related to the item  *
 *                                                                            *
 * Parameters: item - item to update functions for                            *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	update_functions(DB_ITEM *item)
{
	DB_FUNCTION	function;
	DB_RESULT	result;
	DB_ROW		row;
	char		value[MAX_STRING_LEN];
	char		*value_esc, *parameter_esc;
	char		*lastvalue;
	int		ret=SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In update_functions(" ZBX_FS_UI64 ")",
		item->itemid);

/* Oracle does'n support this */
/*	zbx_snprintf(sql,sizeof(sql),"select function,parameter,itemid,lastvalue from functions where itemid=%d group by function,parameter,itemid order by function,parameter,itemid",item->itemid);*/
	result = DBselect("select distinct function,parameter,itemid,lastvalue from functions where itemid=" ZBX_FS_UI64,
		item->itemid);

	while((row=DBfetch(result)))
	{
		function.function=row[0];
		function.parameter=row[1];
		ZBX_STR2UINT64(function.itemid,row[2]);
/*		function.itemid=atoi(row[2]); */
/*		It is not required to check lastvalue for NULL here */
		lastvalue=row[3];

		zabbix_log( LOG_LEVEL_DEBUG, "ItemId:" ZBX_FS_UI64 " Evaluating %s(%s)",
			function.itemid,
			function.function,
			function.parameter);

		ret = evaluate_function(value,item,function.function,function.parameter);
		if( FAIL == ret)	
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Evaluation failed for function:%s",
				function.function);
			continue;
		}
		if (ret == SUCCEED)
		{
			/* Update only if lastvalue differs from new one */
			if (DBis_null(lastvalue) == SUCCEED || 0 != strcmp(lastvalue, value))
			{
				value_esc = DBdyn_escape_string(value);
				parameter_esc = DBdyn_escape_string(function.parameter);

				DBexecute("update functions set lastvalue='%s' where itemid=" ZBX_FS_UI64 " and function='%s' and parameter='%s'",
						value_esc,
						function.itemid,
						function.function,
						parameter_esc);

				zbx_free(parameter_esc);
				zbx_free(value_esc);
			}
			else
				zabbix_log( LOG_LEVEL_DEBUG, "Do not update functions, same value");
		}
	}

	DBfree_result(result);

	zabbix_log( LOG_LEVEL_DEBUG, "End update_functions()");
}

/******************************************************************************
 *                                                                            *
 * Function: update_triggers                                                  *
 *                                                                            *
 * Purpose: re-calculate and updates values of triggers related to the item   *
 *                                                                            *
 * Parameters: itemid - item to update trigger values for                     *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	update_triggers(zbx_uint64_t itemid)
{
	char	*exp;
	char	error[MAX_STRING_LEN];
	int	exp_value;
	DB_TRIGGER	trigger;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log( LOG_LEVEL_DEBUG, "In update_triggers [itemid:" ZBX_FS_UI64 "]",
		itemid);

	result = DBselect("select distinct t.triggerid,t.expression,t.description,t.url,t.comments,t.status,t.value,t.priority,t.type from triggers t,functions f,items i where i.status<>%d and i.itemid=f.itemid and t.status=%d and f.triggerid=t.triggerid and f.itemid=" ZBX_FS_UI64,
		ITEM_STATUS_NOTSUPPORTED,
		TRIGGER_STATUS_ENABLED,
		itemid);

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(trigger.triggerid,row[0]);
		strscpy(trigger.expression,row[1]);
		strscpy(trigger.description,row[2]);
		trigger.url		= row[3];
		trigger.comments	= row[4];
		trigger.status		= atoi(row[5]);
		trigger.value		= atoi(row[6]);
		trigger.priority	= atoi(row[7]);
		trigger.type		= atoi(row[8]);

		exp = strdup(trigger.expression);
		if( evaluate_expression(&exp_value, &exp, &trigger, error, sizeof(error)) != 0 )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Expression [%s] for item [" ZBX_FS_UI64 "][%s] cannot be evaluated: %s",
				trigger.expression,
				itemid,
				zbx_host_key_string(itemid),
				error);
			zabbix_syslog("Expression [%s] for item [" ZBX_FS_UI64 "][%s] cannot be evaluated: %s",
				trigger.expression,
				itemid,
				zbx_host_key_string(itemid),
				error);
/*			DBupdate_trigger_value(&trigger, exp_value, time(NULL), error);*//* We shouldn't update triggervalue if expressions failed */
		}
		else
		{
			DBupdate_trigger_value(&trigger, exp_value, time(NULL), NULL);
		}
		zbx_free(exp);
	}
	DBfree_result(result);
	zabbix_log( LOG_LEVEL_DEBUG, "End update_triggers [" ZBX_FS_UI64 "]",
		itemid);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_history                                                   *
 *                                                                            *
 * Purpose: add new value to the cache                                        *
 *                                                                            *
 * Parameters: item - item data                                               *
 *             value - new value of the item                                  *
 *             now   - new value of the item                                  *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	dc_add_history(DB_ITEM *item, AGENT_RESULT *value, int now)
{
	if (value->type & AR_UINT64)
		zabbix_log(LOG_LEVEL_DEBUG, "In dc_add_history(itemid:" ZBX_FS_UI64 ",key:\"%s\",value_type:%d,UINT64:"ZBX_FS_UI64")",
			item->itemid,
			item->key,
			item->value_type,
			value->ui64);
	if (value->type & AR_STRING)
		zabbix_log(LOG_LEVEL_DEBUG, "In dc_add_history(itemid:" ZBX_FS_UI64 ",key:\"%s\",value_type:%d,STRING:%s)",
			item->itemid,
			item->key,
			item->value_type,
			value->str);
	if (value->type & AR_DOUBLE)
		zabbix_log(LOG_LEVEL_DEBUG, "In dc_add_history(itemid:" ZBX_FS_UI64 ",key:\"%s\",value_type:%d,DOUBLE:"ZBX_FS_DBL")",
			item->itemid,
			item->key,
			item->value_type,
			value->dbl);
	if (value->type & AR_TEXT)
		zabbix_log(LOG_LEVEL_DEBUG, "In dc_add_history(itemid: "ZBX_FS_UI64 ",key:\"%s\",value_type:%d,TEXT:[%s])",
			item->itemid,
			item->key,
			item->value_type,
			value->text);

	switch (item->value_type) {
		case ITEM_VALUE_TYPE_FLOAT:
			if (GET_DBL_RESULT(value))
				DCadd_history(item->itemid, value->dbl, now);
			break;
		case ITEM_VALUE_TYPE_STR:
			if (GET_STR_RESULT(value))
				DCadd_history_str(item->itemid, value->str, now);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (GET_STR_RESULT(value))
				DCadd_history_log(item->itemid, value->str, now, item->timestamp, item->eventlog_source,
						item->eventlog_severity, item->lastlogsize);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (GET_UI64_RESULT(value))
				DCadd_history_uint(item->itemid, value->ui64, now);
			break;
		case ITEM_VALUE_TYPE_TEXT:
			if (GET_TEXT_RESULT(value))
				DCadd_history_text(item->itemid, value->text, now);
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "Unknown value type [%d] for itemid [" ZBX_FS_UI64 "]",
				item->value_type,
				item->itemid);
	}
	zabbix_log( LOG_LEVEL_DEBUG, "End of dc_add_history");
}

/******************************************************************************
 *                                                                            *
 * Function: add_history                                                      *
 *                                                                            *
 * Purpose: add new value to history                                          *
 *                                                                            *
 * Parameters: item - item data                                               *
 *             value - new value of the item                                  *
 *             now   - new value of the item                                  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	add_history(DB_ITEM *item, AGENT_RESULT *value, int now)
{
	int		ret = FAIL;
	zbx_uint64_t	value_uint64;
	double		value_double;

	zabbix_log(LOG_LEVEL_DEBUG, "In add_history(key:%s,value_type:%X,type:%X)",
		item->key,
		item->value_type,
		value->type);

	if (value->type & AR_UINT64)
		zabbix_log(LOG_LEVEL_DEBUG, "In add_history(itemid:"ZBX_FS_UI64",UINT64:"ZBX_FS_UI64")",
			item->itemid,
			value->ui64);
	if (value->type & AR_STRING)
		zabbix_log(LOG_LEVEL_DEBUG, "In add_history(itemid:"ZBX_FS_UI64",STRING:%s)",
			item->itemid,
			value->str);
	if (value->type & AR_DOUBLE)
		zabbix_log(LOG_LEVEL_DEBUG, "In add_history(itemid:"ZBX_FS_UI64",DOUBLE:"ZBX_FS_DBL")",
			item->itemid,
			value->dbl);
	if (value->type & AR_TEXT)
		zabbix_log(LOG_LEVEL_DEBUG, "In add_history(itemid:"ZBX_FS_UI64",TEXT:[%s])",
			item->itemid,
			value->text);

	switch (item->value_type) {
	case ITEM_VALUE_TYPE_FLOAT:
		if (NULL == GET_DBL_RESULT(value))
			break;

		switch (item->delta) {			/* Should we store delta or original value? */
		case ITEM_STORE_AS_IS:
			if (item->history > 0)
				DBadd_history(item->itemid, value->dbl, now);
			ret = SUCCEED;
			break;
		case ITEM_STORE_SPEED_PER_SECOND:	/* Delta as speed of change */
			if (0 == item->prevorgvalue_null && item->prevorgvalue_dbl <= value->dbl && item->lastclock < now)
			{
				if (item->history > 0)
				{
					value_double = (value->dbl - item->prevorgvalue_dbl) / (now - item->lastclock);
					DBadd_history(item->itemid, value_double, now);
				}
				ret = SUCCEED;
			}
			break;
		case ITEM_STORE_SIMPLE_CHANGE:		/* Real delta: simple difference between values */
			if (0 == item->prevorgvalue_null && item->prevorgvalue_dbl <= value->dbl)
			{
				if (item->history > 0)
					DBadd_history(item->itemid, (value->dbl - item->prevorgvalue_dbl), now);
				ret = SUCCEED;
			}
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "Value not stored for itemid [" ZBX_FS_UI64 "]. Unknown delta [%d]",
					item->itemid,
					item->delta);
			zabbix_syslog("Value not stored for itemid [" ZBX_FS_UI64 "]. Unknown delta [%d]",
					item->itemid,
					item->delta);
		}
		break;
	case ITEM_VALUE_TYPE_UINT64:
		if (NULL == GET_UI64_RESULT(value))
			break;

		switch (item->delta) {			/* Should we store delta or original value? */
		case ITEM_STORE_AS_IS:
			if (item->history > 0)
				DBadd_history_uint(item->itemid, value->ui64, now);
			ret = SUCCEED;
			break;
		case ITEM_STORE_SPEED_PER_SECOND:	/* Delta as speed of change */
			if (0 == item->prevorgvalue_null && item->prevorgvalue_uint64 <= value->ui64 && item->lastclock < now)
			{
				if (item->history > 0)
				{
					value_uint64 = (zbx_uint64_t)(value->ui64 - item->prevorgvalue_uint64) / (now - item->lastclock);
					DBadd_history_uint(item->itemid, value_uint64, now);
				}
				ret = SUCCEED;
			}
			break;
		case ITEM_STORE_SIMPLE_CHANGE:		/* Real delta: simple difference between values */
			if (0 == item->prevorgvalue_null && item->prevorgvalue_uint64 <= value->ui64)
			{
				if (item->history > 0)
					DBadd_history_uint(item->itemid, value->ui64 - item->prevorgvalue_uint64, now);
				ret = SUCCEED;
			}
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "Value not stored for itemid [" ZBX_FS_UI64 "]. Unknown delta [%d]",
					item->itemid,
					item->delta);
			zabbix_syslog("Value not stored for itemid [" ZBX_FS_UI64 "]. Unknown delta [%d]",
					item->itemid,
					item->delta);
		}
		break;
	case ITEM_VALUE_TYPE_STR:
		if (NULL == GET_STR_RESULT(value))
			break;

		if (item->history > 0)
			DBadd_history_str(item->itemid, value->str, now);
		ret = SUCCEED;
		break;
	case ITEM_VALUE_TYPE_LOG:
		if (NULL == GET_STR_RESULT(value))
			break;

		if (item->history > 0)
			DBadd_history_log(item->itemid, value->str, now, item->timestamp, item->eventlog_source,
					item->eventlog_severity, item->lastlogsize);
		ret = SUCCEED;
		break;
	case ITEM_VALUE_TYPE_TEXT:
		if (NULL == GET_TEXT_RESULT(value))
			break;

		if (item->history > 0)
			DBadd_history_text(item->itemid,value->text,now);
		ret = SUCCEED;
		break;
	default:
		zabbix_log(LOG_LEVEL_ERR, "Unknown value type [%d] for itemid [" ZBX_FS_UI64 "]",
				item->value_type,
				item->itemid);
		zabbix_syslog("Unknown value type [%d] for itemid [" ZBX_FS_UI64 "]",
				item->value_type,
				item->itemid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of add_history():%s", ret == SUCCEED ? "SUCCEED" : "FAIL");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: update_item                                                      *
 *                                                                            *
 * Purpose: update item info after new value is received                      *
 *                                                                            *
 * Parameters: item - item data                                               *
 *             value - new value of the item                                  *
 *             now   - current timestamp                                      * 
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev                                 *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	update_item(DB_ITEM *item, AGENT_RESULT *value, time_t now)
{
	char		value_esc[MAX_STRING_LEN];
	zbx_uint64_t	value_uint64;
	double		value_double;

	zabbix_log(LOG_LEVEL_DEBUG, "In update_item()");

	*value_esc	= '\0';
	item->nextcheck	= calculate_item_nextcheck(item->itemid, item->type, item->delay, item->delay_flex, now);

	switch (item->value_type) {
	case ITEM_VALUE_TYPE_FLOAT:
		if (NULL == GET_DBL_RESULT(value))
			break;

		switch (item->delta) {			/* Should we store delta or original value? */
		case ITEM_STORE_AS_IS:
			DBexecute("update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='" ZBX_FS_DBL "',lastclock=%d"
					" where itemid=" ZBX_FS_UI64,
					item->nextcheck,
					value->dbl,
					(int)now,
					item->itemid);
			break;
		case ITEM_STORE_SPEED_PER_SECOND:	/* Delta as speed of change */
			if (0 == item->prevorgvalue_null && item->prevorgvalue_dbl <= value->dbl)
			{
				/* In order to continue normal processing, we assume difference 1 second
				   Otherwise function update_functions and update_triggers won't work correctly*/
				if (now != item->lastclock)
					value_double = (value->dbl - item->prevorgvalue_dbl) / (now - item->lastclock);
				else
					value_double = value->dbl - item->prevorgvalue_dbl;

				DBexecute("update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue='" ZBX_FS_DBL "',"
						"lastvalue='" ZBX_FS_DBL "',lastclock=%d where itemid=" ZBX_FS_UI64,
						item->nextcheck,
						value->dbl,
						value_double,
						(int)now,
						item->itemid);
				SET_DBL_RESULT(value, value_double);
			}
			else
			{
				DBexecute("update items set nextcheck=%d,prevorgvalue='" ZBX_FS_DBL "',lastclock=%d where itemid=" ZBX_FS_UI64,
						item->nextcheck,
						value->dbl,
						(int)now,
						item->itemid);
			}
			break;
		case ITEM_STORE_SIMPLE_CHANGE:		/* Real delta: simple difference between values */
			if (0 == item->prevorgvalue_null && item->prevorgvalue_dbl <= value->dbl)
			{
				value_double = value->dbl - item->prevorgvalue_dbl;
				DBexecute("update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue='" ZBX_FS_DBL "',"
						"lastvalue='" ZBX_FS_DBL "',lastclock=%d where itemid=" ZBX_FS_UI64,
						item->nextcheck,
						value->dbl,
						value_double,
						(int)now,
						item->itemid);
				SET_DBL_RESULT(value, value_double);
			}
			else
			{
				DBexecute("update items set nextcheck=%d,prevorgvalue='" ZBX_FS_DBL "',lastclock=%d where itemid=" ZBX_FS_UI64,
						item->nextcheck,
						value->dbl,
						(int)now,
						item->itemid);
			}
			break;
		}
		break;
	case ITEM_VALUE_TYPE_UINT64:
		if (NULL == GET_UI64_RESULT(value))
			break;

		switch (item->delta) {			/* Should we store delta or original value? */
		case ITEM_STORE_AS_IS:
			DBexecute("update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='" ZBX_FS_UI64 "',lastclock=%d"
					" where itemid=" ZBX_FS_UI64,
					item->nextcheck,
					value->ui64,
					(int)now,
					item->itemid);
			break;
		case ITEM_STORE_SPEED_PER_SECOND:	/* Delta as speed of change */
			if (0 == item->prevorgvalue_null && item->prevorgvalue_uint64 <= value->ui64)
			{
				if (now != item->lastclock)
					value_uint64 = (zbx_uint64_t)(value->ui64 - item->prevorgvalue_uint64) / (now - item->lastclock);
				else
					value_uint64 = value->ui64 - item->prevorgvalue_uint64;

				DBexecute("update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue='" ZBX_FS_UI64 "',"
						"lastvalue='" ZBX_FS_UI64 "',lastclock=%d where itemid=" ZBX_FS_UI64,
						item->nextcheck,
						value->ui64,
						value_uint64,
						(int)now,
						item->itemid);
				SET_UI64_RESULT(value, value_uint64);
			}
			else
			{
				DBexecute("update items set nextcheck=%d,prevorgvalue='" ZBX_FS_UI64 "',lastclock=%d where itemid=" ZBX_FS_UI64,
						item->nextcheck,
						value->ui64,
						(int)now,
						item->itemid);
			}
			break;
		case ITEM_STORE_SIMPLE_CHANGE:		/* Real delta: simple difference between values */
			if (0 == item->prevorgvalue_null && item->prevorgvalue_uint64 <= value->ui64)
			{
				value_uint64 = value->ui64 - item->prevorgvalue_uint64;
				DBexecute("update items set nextcheck=%d,prevvalue=lastvalue,prevorgvalue='" ZBX_FS_UI64 "',"
						"lastvalue='" ZBX_FS_UI64 "',lastclock=%d where itemid=" ZBX_FS_UI64,
						item->nextcheck,
						value->ui64,
						value_uint64,
						(int)now,
						item->itemid);
				SET_UI64_RESULT(value, value_uint64);
			}
			else
			{
				DBexecute("update items set nextcheck=%d,prevorgvalue='" ZBX_FS_UI64 "',lastclock=%d where itemid=" ZBX_FS_UI64,
						item->nextcheck,
						value->ui64,
						(int)now,
						item->itemid);
			}
			break;
		}
		break;
	case ITEM_VALUE_TYPE_STR:
	case ITEM_VALUE_TYPE_TEXT:
		if (NULL == GET_STR_RESULT(value))
			break;

		DBescape_string(value->str, value_esc, sizeof(value_esc));
		DBexecute("update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='%s',lastclock=%d"
				" where itemid=" ZBX_FS_UI64,
				item->nextcheck,
				value_esc,
				(int)now,
				item->itemid);
		break;
	case ITEM_VALUE_TYPE_LOG:
		if (NULL == GET_STR_RESULT(value))
			break;

		DBescape_string(value->str, value_esc, sizeof(value_esc));
		DBexecute("update items set nextcheck=%d,prevvalue=lastvalue,lastvalue='%s',lastclock=%d,lastlogsize=%d"
				" where itemid=" ZBX_FS_UI64,
				item->nextcheck,
				value_esc,
				(int)now,
				item->lastlogsize,
				item->itemid);
		break;
	}

	item->prevvalue_str	= item->lastvalue_str;
	item->prevvalue_dbl	= item->lastvalue_dbl;
	item->prevvalue_uint64	= item->lastvalue_uint64;
	item->prevvalue_null	= item->lastvalue_null;

	item->lastvalue_uint64	= value->ui64;
	item->lastvalue_dbl	= value->dbl;
	item->lastvalue_str	= value->str;
	item->lastvalue_null	= 0;

	/* Required for nodata() */
	item->lastclock		= now;

	/* Update item status if required */
	if (item->status == ITEM_STATUS_NOTSUPPORTED)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Parameter [%s] became supported by agent on host [%s]",
			item->key,
			item->host_name);
		zabbix_syslog("Parameter [%s] became supported by agent on host [%s]",
			item->key,
			item->host_name);

		item->status = ITEM_STATUS_ACTIVE;
		DBexecute("update items set status=%d,error='' where itemid=" ZBX_FS_UI64,
				item->status,
				item->itemid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of update_item()");
}

/******************************************************************************
 *                                                                            *
 * Function: process_new_value                                                *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters: item - item data                                               *
 *             value - new value of the item                                  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: for trapper poller process                                       *
 *                                                                            *
 ******************************************************************************/
void	process_new_value(DB_ITEM *item, AGENT_RESULT *value, time_t now)
{
	zabbix_log( LOG_LEVEL_DEBUG, "In process_new_value(%s)",
		item->key);

	if( ITEM_MULTIPLIER_USE == item->multiplier )
	{
		if( ITEM_VALUE_TYPE_FLOAT == item->value_type )
		{
			if(GET_DBL_RESULT(value))
			{
				UNSET_RESULT_EXCLUDING(value, AR_DOUBLE);
				SET_DBL_RESULT(value, value->dbl * strtod(item->formula, NULL));
			}
		}
		else if( ITEM_VALUE_TYPE_UINT64 == item->value_type )
		{
			if(GET_UI64_RESULT(value))
			{
				UNSET_RESULT_EXCLUDING(value, AR_UINT64);
				if(is_uint(item->formula) == SUCCEED)
				{
					SET_UI64_RESULT(value, value->ui64 * zbx_atoui64((item->formula)));
				}
				else
				{
					SET_UI64_RESULT(value, (zbx_uint64_t)((double)value->ui64 * strtod(item->formula, NULL)));
				}
			}
		}
	}

	if (0 == CONFIG_DBSYNCER_FORKS)
	{
		if (SUCCEED == add_history(item, value, now))
		{
			update_item(item, value, now);
			update_functions(item);
			update_triggers(item->itemid);
		}
		else
			update_item(item, value, now);
	}
	else
		dc_add_history(item, value, now);
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_add_history                                                *
 *                                                                            *
 * Purpose: add new value to history                                          *
 *                                                                            *
 * Parameters: item - item data                                               *
 *             value - new value of the item                                  *
 *             now   - new value of the item                                  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	proxy_add_history(DB_ITEM *item, AGENT_RESULT *value, int now)
{
	if (value->type & AR_UINT64)
		zabbix_log(LOG_LEVEL_DEBUG, "In proxy_add_history(itemid:" ZBX_FS_UI64 ",key:\"%s\",value_type:%d,UINT64:"ZBX_FS_UI64")",
			item->itemid,
			item->key,
			item->value_type,
			value->ui64);
	if (value->type & AR_STRING)
		zabbix_log(LOG_LEVEL_DEBUG, "In proxy_add_history(itemid:" ZBX_FS_UI64 ",key:\"%s\",value_type:%d,STRING:%s)",
			item->itemid,
			item->key,
			item->value_type,
			value->str);
	if (value->type & AR_DOUBLE)
		zabbix_log(LOG_LEVEL_DEBUG, "In proxy_add_history(itemid:" ZBX_FS_UI64 ",key:\"%s\",value_type:%d,DOUBLE:"ZBX_FS_DBL")",
			item->itemid,
			item->key,
			item->value_type,
			value->dbl);
	if (value->type & AR_TEXT)
		zabbix_log(LOG_LEVEL_DEBUG, "In proxy_add_history(itemid: "ZBX_FS_UI64 ",key:\"%s\",value_type:%d,TEXT:[%s])",
			item->itemid,
			item->key,
			item->value_type,
			value->text);

	switch (item->value_type) {
		case ITEM_VALUE_TYPE_FLOAT:
			if (GET_DBL_RESULT(value))
				DBproxy_add_history(item->itemid, value->dbl, now);
			break;
		case ITEM_VALUE_TYPE_STR:
			if (GET_STR_RESULT(value))
				DBproxy_add_history_str(item->itemid, value->str, now);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (GET_STR_RESULT(value))
				DBproxy_add_history_log(item->itemid, value->str, now, item->timestamp, item->eventlog_source,
						item->eventlog_severity, item->lastlogsize);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (GET_UI64_RESULT(value))
				DBproxy_add_history_uint(item->itemid, value->ui64, now);
			break;
		case ITEM_VALUE_TYPE_TEXT:
			if (GET_TEXT_RESULT(value))
				DBproxy_add_history_text(item->itemid, value->str, now);
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "Unknown value type [%d] for itemid [" ZBX_FS_UI64 "]",
				item->value_type,
				item->itemid);
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End of proxy_add_history");
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_update_item                                                *
 *                                                                            *
 * Purpose: update item info after new value is received                      *
 *                                                                            *
 * Parameters: item - item data                                               *
 *             value - new value of the item                                  *
 *             now   - current timestamp                                      * 
 *                                                                            *
 * Author: Alexei Vladishev, Eugene Grigorjev                                 *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	proxy_update_item(DB_ITEM *item, AGENT_RESULT *value, time_t now)
{
	zabbix_log( LOG_LEVEL_DEBUG, "In proxy_update_item()");

	item->nextcheck	= calculate_item_nextcheck(item->itemid, item->type, item->delay, item->delay_flex, now);

	if (item->value_type == ITEM_VALUE_TYPE_LOG) {
		DBexecute("update items set nextcheck=%d,lastlogsize=%d where itemid=" ZBX_FS_UI64,
			item->nextcheck,
			item->lastlogsize,
			item->itemid);
	} else {
		DBexecute("update items set nextcheck=%d where itemid=" ZBX_FS_UI64,
			item->nextcheck,
			item->itemid);
	}

	item->prevvalue_str	= item->lastvalue_str;
	item->prevvalue_dbl	= item->lastvalue_dbl;
	item->prevvalue_uint64	= item->lastvalue_uint64;
	item->prevvalue_null	= item->lastvalue_null;

	item->lastvalue_uint64	= value->ui64;
	item->lastvalue_dbl	= value->dbl;
	item->lastvalue_str	= value->str;
	item->lastvalue_null	= 0;

	if (item->status == ITEM_STATUS_NOTSUPPORTED) {
		zabbix_log( LOG_LEVEL_WARNING, "Parameter [%s] became supported by agent on host [%s]",
			item->key,
			item->host_name);
		zabbix_syslog("Parameter [%s] became supported by agent on host [%s]",
			item->key,
			item->host_name);
		item->status = ITEM_STATUS_ACTIVE;
		DBexecute("update items set status=%d where itemid=" ZBX_FS_UI64,
			ITEM_STATUS_ACTIVE,
			item->itemid);
	}

	/* Required for nodata() */
	item->lastclock = now;

	zabbix_log( LOG_LEVEL_DEBUG, "End proxy_update_item()");
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_process_new_value                                          *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters: item - item data                                               *
 *             value - new value of the item                                  *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: for trapper poller process                                       *
 *                                                                            *
 ******************************************************************************/
void	proxy_process_new_value(DB_ITEM *item, AGENT_RESULT *value, time_t now)
{
	zabbix_log( LOG_LEVEL_DEBUG, "In proxy_process_new_value(%s)",
		item->key);

	if (0 == CONFIG_DBSYNCER_FORKS)
	{
		proxy_add_history(item, value, now);
		proxy_update_item(item, value, now);
	}
	else
		dc_add_history(item, value, now);
}
