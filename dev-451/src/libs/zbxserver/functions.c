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
 * Function: update_triggers                                                  *
 *                                                                            *
 * Purpose: re-calculate and update values of triggers related to the item    *
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
	char		*exp;
	int		exp_value;
	char		error[MAX_STRING_LEN];

	DB_TRIGGER	trigger;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In update_triggers [itemid:" ZBX_FS_UI64 "]", itemid);

	result = DBselect(
		"select distinct t.triggerid,t.expression,t.description,"
			"t.url,t.comments,t.status,t.value,t.priority,t.type"
		" from triggers t,functions f,items i"
		" where i.status<>%d"
			" and i.itemid=f.itemid"
			" and t.status=%d"
			" and f.triggerid=t.triggerid"
			" and f.itemid=" ZBX_FS_UI64,
		ITEM_STATUS_NOTSUPPORTED,
		TRIGGER_STATUS_ENABLED,
		itemid);

	while ((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(trigger.triggerid, row[0]);
		strscpy(trigger.expression, row[1]);
		strscpy(trigger.description, row[2]);
		trigger.url		= row[3];
		trigger.comments	= row[4];
		trigger.status		= atoi(row[5]);
		trigger.value		= atoi(row[6]);
		trigger.priority	= atoi(row[7]);
		trigger.type		= atoi(row[8]);

		exp = strdup(trigger.expression);
		if (SUCCEED != evaluate_expression(&exp_value, &exp, time(NULL), &trigger, error, sizeof(error)))
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
/*			We shouldn't update trigger value if expressions failed	*/
			DBupdate_trigger_value(&trigger, TRIGGER_VALUE_UNKNOWN, time(NULL), error);
		}
		else
		{
			DBupdate_trigger_value(&trigger, exp_value, time(NULL), NULL);
		}
		zbx_free(exp);
	}

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End update_triggers [" ZBX_FS_UI64 "]", itemid);
}

/******************************************************************************
 *                                                                            *
 * Function: dc_add_history                                                   *
 *                                                                            *
 * Purpose: add new value to the cache                                        *
 *                                                                            *
 * Parameters: item - item data                                               *
 *             value - new value of the item                                  *
 *             now   - value time                                             *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	dc_add_history(zbx_uint64_t itemid, unsigned char value_type, AGENT_RESULT *value, int now,
		int timestamp, char *source, int severity, int logeventid, int lastlogsize, int mtime)
{
/*	if (value->type & AR_UINT64)
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
			value->text);*/

	switch (value_type) {
		case ITEM_VALUE_TYPE_FLOAT:
			if (GET_DBL_RESULT(value))
				DCadd_history(itemid, value->dbl, now);
			break;
		case ITEM_VALUE_TYPE_STR:
			if (GET_STR_RESULT(value))
				DCadd_history_str(itemid, value->str, now);
			break;
		case ITEM_VALUE_TYPE_LOG:
			if (GET_STR_RESULT(value))
				DCadd_history_log(itemid, value->str, now, timestamp, source, severity,
						logeventid, lastlogsize, mtime);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (GET_UI64_RESULT(value))
				DCadd_history_uint(itemid, value->ui64, now);
			break;
		case ITEM_VALUE_TYPE_TEXT:
			if (GET_TEXT_RESULT(value))
				DCadd_history_text(itemid, value->text, now);
			break;
		default:
			zabbix_log(LOG_LEVEL_ERR, "Unknown value type [%d] for itemid [" ZBX_FS_UI64 "]",
				value_type,
				itemid);
	}
/*	zabbix_log( LOG_LEVEL_DEBUG, "End of dc_add_history");*/
}
