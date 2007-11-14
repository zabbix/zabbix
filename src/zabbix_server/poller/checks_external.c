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
#include "checks_external.h"

/******************************************************************************
 *                                                                            *
 * Function: get_value_external                                               *
 *                                                                            *
 * Purpose: retrieve data from script executed on ZABBIX server               *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data succesfully retrieved and stored in result    *
 *                         and result_str (as string)                         *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Mike Nestor                                                        *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 **************************************************  ****************************/
int     get_value_external(DB_ITEM *item, AGENT_RESULT *result)
{
	FILE*   fp;
	char    scriptname[MAX_STRING_LEN];
	char    key[MAX_STRING_LEN];
	char    params[MAX_STRING_LEN];
	char    error[MAX_STRING_LEN];
	char    cmd[MAX_STRING_LEN];
	char    msg[MAX_STRING_LEN];
	char    *p,*p2;
	int     i;

	int     ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_external([%s])",item->key);

	init_result(result);

	strscpy(key, item->key);
	if((p2=strchr(key,'[')) != NULL)
	{
		*p2=0;
		strscpy(scriptname,key);
		zabbix_log( LOG_LEVEL_DEBUG, "scriptname [%s]",scriptname);
		*p2='[';
		p2++;
	}
	else    ret = NOTSUPPORTED;

	if(ret == SUCCEED)
	{
		if((ret == SUCCEED) && (p=strchr(p2,']')) != NULL)
		{
			*p=0;
			strscpy(params,p2);
			zabbix_log( LOG_LEVEL_DEBUG, "params [%s]",params);
			*p=']';
			p++;
		}
		else    ret = NOTSUPPORTED;
	}
	else
	{
		zbx_snprintf(error,MAX_STRING_LEN-1,"External check [%s] is not supported", item->key);
		zabbix_log( LOG_LEVEL_DEBUG, "%s", error);
		SET_STR_RESULT(result, strdup(error));
		return NOTSUPPORTED;
	}

	zbx_snprintf(cmd, MAX_STRING_LEN-1, "%s/%s %s %s",
		CONFIG_EXTERNALSCRIPTS,
		scriptname,
/*		item->host_name,*/
		item->useip == 1 ? item->host_ip : item->host_dns,
		params);
	zabbix_log( LOG_LEVEL_DEBUG, "%s", cmd );
	if (NULL == (fp = popen(cmd, "r"))) 
	{
		zbx_snprintf(error,MAX_STRING_LEN-1,"External check [%s] is not supported, failed execution", item->key);
		zabbix_log( LOG_LEVEL_DEBUG, "%s", error);
		SET_STR_RESULT(result, strdup(error));
		return NOTSUPPORTED;
	}

	/* we only care about the first line */
	memset(msg,0,sizeof(msg));
	if(NULL != fgets(msg, sizeof(msg)-1, fp))
	{
		for (i = 0; i < MAX_STRING_LEN && msg[i] != 0; ++i) 
		{
			if (msg[i] == '\n') 
			{
				msg[i] = 0;
				break;
			}
		}
		zabbix_log( LOG_LEVEL_DEBUG, "Result [%s]", msg);

		set_result_type(result,item->value_type,strdup(msg));
	}
	else
	{
		zbx_snprintf(error,MAX_STRING_LEN-1,"Script %s/%s returned nothing.",
			CONFIG_EXTERNALSCRIPTS,
			scriptname);
		zabbix_log( LOG_LEVEL_WARNING, "%s", error);
		SET_STR_RESULT(result, strdup(error));
		ret = NOTSUPPORTED;
	}

	/* cleanup */
	pclose(fp);

	return ret;
}
