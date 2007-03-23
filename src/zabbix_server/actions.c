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


#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <string.h>

#include <time.h>

#include <sys/socket.h>
#include <errno.h>

/* Functions: pow(), round() */
#include <math.h>

#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "security.h"

#include "actions.h"
#include "expression.h"

#include "poller/poller.h"
#include "poller/checks_agent.h"

/******************************************************************************
 *                                                                            *
 * Function: send_to_user_medias                                              *
 *                                                                            *
 * Purpose: send notifications to user's medias (email, sms, whatever)        *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *             userid  - user id                                              *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: Cannot use action->userid as it may also be groupid              *
 *                                                                            *
 ******************************************************************************/
static	void	send_to_user_medias(DB_EVENT *event,DB_OPERATION *operation, zbx_uint64_t userid)
{
	DB_MEDIA media;
	DB_RESULT result;
	DB_ROW	row;

	zabbix_log( LOG_LEVEL_DEBUG, "In send_to_user_medias(objectid:" ZBX_FS_UI64 ")",
		event->objectid);

	result = DBselect("select mediatypeid,sendto,active,severity,period from media where active=%d and userid=" ZBX_FS_UI64,
		MEDIA_STATUS_ACTIVE,
		userid);

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(media.mediatypeid, row[0]);

		media.sendto	= row[1];
		media.active	= atoi(row[2]);
		media.severity	= atoi(row[3]);
		media.period	= row[4];

		zabbix_log( LOG_LEVEL_DEBUG, "Trigger severity [%d] Media severity [%d] Period [%s]",
			event->trigger_priority,
			media.severity,
			media.period);
		if(((1<<event->trigger_priority)&media.severity)==0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Won't send message (severity)");
			continue;
		}
		if(check_time_period(media.period, (time_t)NULL) == 0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Won't send message (period)");
			continue;
		}

		DBadd_alert(operation->actionid, userid, event->objectid, media.mediatypeid,media.sendto,operation->shortdata,operation->longdata);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End send_to_user_medias()");
}

/******************************************************************************
 *                                                                            *
 * Function: send_to_user                                                     *
 *                                                                            *
 * Purpose: send notifications to user or user groupd                         *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: action->recipient specifies user or group                        *
 *                                                                            *
 ******************************************************************************/
static	void	send_to_user(DB_EVENT *event, DB_ACTION *action, DB_OPERATION *operation)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	userid;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_to_user()");

	if(operation->object == OPERATION_OBJECT_USER)
	{
		send_to_user_medias(event, operation, operation->objectid);
	}
	else if(operation->object == OPERATION_OBJECT_GROUP)
	{
		result = DBselect("select u.userid from users u, users_groups ug where ug.usrgrpid=" ZBX_FS_UI64 " and ug.userid=u.userid",
			operation->objectid);
		while((row=DBfetch(result)))
		{
			ZBX_STR2UINT64(userid, row[0]);
			send_to_user_medias(event, operation, userid);
		}
		DBfree_result(result);
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unknown object type [%d] for operationid [" ZBX_FS_UI64 "]",
			operation->object,
			operation->operationid);
		zabbix_syslog("Unknown object type [%d] for operationid [" ZBX_FS_UI64 "]",
			operation->object,
			operation->operationid);
	}
	zabbix_log(LOG_LEVEL_DEBUG, "End send_to_user()");
}


/******************************************************************************
 *                                                                            *
 * Function: run_remote_commands                                              *
 *                                                                            *
 * Purpose: run remote command on specific host                               *
 *                                                                            *
 * Parameters: host_name - host name                                          *
 *             command - remote command                                       *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

static void run_remote_command(char* host_name, char* command)
{
	int ret = 9;
	
	AGENT_RESULT	agent_result;
	DB_ITEM         item;
	DB_RESULT	result;
	DB_ROW		row;
	
	assert(host_name);
	assert(command);

	zabbix_log(LOG_LEVEL_DEBUG, "In run_remote_command(hostname:%s,command:%s)",
		host_name,
		command);

	result = DBselect("select distinct host,ip,useip,port,dns from hosts where host='%s' and " ZBX_COND_NODEID,
			host_name,
			LOCAL_NODE("hostid"));
	row = DBfetch(result);
	if(row)
	{
		item.host_name = row[0];
		item.host_ip=row[1];
		item.useip=atoi(row[2]);
		item.port=atoi(row[3]);
		item.host_dns=row[4];
		
		zbx_snprintf(item.key,ITEM_KEY_LEN_MAX,"system.run[%s,nowait]",command);
		
		alarm(CONFIG_TIMEOUT);
		
		ret = get_value_agent(&item, &agent_result);

		alarm(0);
	}
	DBfree_result(result);
	
	zabbix_log(LOG_LEVEL_DEBUG, "End run_remote_command(result:%d)",
		ret);
}

/******************************************************************************
 *                                                                            *
 * Function: get_next_command                                                 *
 *                                                                            *
 * Purpose: parse action script on remote commands                            *
 *                                                                            *
 * Parameters: command_list - command list                                    *
 *             alias - (output) of host name or group name                    *
 *             is_group - (output) 0 if alias is a host name                  *
 *                               1 if alias is a group name                   *
 *             command - (output) remote command                              *
 *                                                                            *
 * Return value: 0 - correct comand is readed                                 *
 *               1 - EOL                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

#define CMD_ALIAS 0
#define CMD_REM_COMMAND 1

static int get_next_command(char** command_list, char** alias, int* is_group, char** command)
{
	int state = CMD_ALIAS;
	int len = 0;
	int i = 0;
	
	assert(alias);
	assert(is_group);
	assert(command);

	zabbix_log(LOG_LEVEL_DEBUG, "In get_next_command(command_list:%s)",
		*command_list);

	*alias = NULL;
	*is_group = 0;
	*command = NULL;
	

	if((*command_list)[0] == '\0' || (*command_list)==NULL) {
		zabbix_log(LOG_LEVEL_DEBUG, "Result get_next_command [EOL]");
		return 1;
	}

	*alias = *command_list;
	len = strlen(*command_list);

	for(i=0; i < len; i++)
	{
		if(state == CMD_ALIAS)
		{
			if((*command_list)[i] == '#'){
				*is_group = 1;
				(*command_list)[i] = '\0';
				state = CMD_REM_COMMAND;
				*command = &(*command_list)[i+1];
			}else if((*command_list)[i] == ':'){
				*is_group = 0;
				(*command_list)[i] = '\0';
				state = CMD_REM_COMMAND;
				*command = &(*command_list)[i+1];
			}
		} else if(state == CMD_REM_COMMAND) {
			if((*command_list)[i] == '\r')
			{
				(*command_list)[i] = '\0';
			} else if((*command_list)[i] == '\n')
			{
				(*command_list)[i] = '\0';
				(*command_list) = &(*command_list)[i+1];
				break;
			}
		}
		if((*command_list)[i+1] == '\0')
		{
			(*command_list) = &(*command_list)[i+1];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End get_next_command(alias:%s,is_group:%i,command:%s)",
		*alias,
		*is_group,
		*command);
	
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: run_commands                                                     *
 *                                                                            *
 * Purpose: run remote commandlist for specific action                        *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *                                                                            *
 * Return value: nothing                                                      *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: commands devided with newline                                    *
 *                                                                            *
 ******************************************************************************/
static	void	run_commands(DB_EVENT *event, DB_OPERATION *operation)
{
	DB_RESULT result;
	DB_ROW		row;

	char *cmd_list = NULL;
	char *alias = NULL;
	char *command = NULL;
	int is_group = 0;

	assert(event);
	assert(operation);

	zabbix_log( LOG_LEVEL_DEBUG, "In run_commands(operationid:" ZBX_FS_UI64 ")",
		operation->operationid);

	cmd_list = operation->longdata;
	while(get_next_command(&cmd_list,&alias,&is_group,&command)!=1)
	{
		if(!alias || !command) continue;
		if(alias == '\0' || command == '\0') continue;
		if(is_group)
		{
			result = DBselect("select distinct h.host from hosts_groups hg,hosts h, groups g where hg.hostid=h.hostid and hg.groupid=g.groupid and g.name='%s' and" ZBX_COND_NODEID,
				alias,
				LOCAL_NODE("h.hostid"));
			while((row=DBfetch(result)))
			{
				run_remote_command(row[0], command);
			}
			
			DBfree_result(result);
		}
		else
		{
			run_remote_command(alias, command);
		}
/*		DBadd_alert(action->actionid,trigger->triggerid, userid, media.mediatypeid,media.sendto,action->subject,action->scripts); */ /* TODO !!! Add alert for remote commands !!! */
	}
	zabbix_log( LOG_LEVEL_DEBUG, "End run_commands()");
}

/******************************************************************************
 *                                                                            *
 * Function: check_action_condition                                           *
 *                                                                            *
 * Purpose: check if event matches single condition                           *
 *                                                                            *
 * Parameters: event - event to check                                         *
 *             condition - condition for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	check_action_condition(DB_EVENT *event, DB_CONDITION *condition)
{
	DB_RESULT result;
	DB_ROW	row;
	zbx_uint64_t	groupid;
	zbx_uint64_t	hostid;
	zbx_uint64_t	condition_value;
	int		value_int;

	char		*tmp_str = NULL;

	int	ret = FAIL;

	zabbix_log( LOG_LEVEL_DEBUG, "In check_action_condition [actionid:" ZBX_FS_UI64 ",conditionid:" ZBX_FS_UI64 ",cond.value:%s]",
		condition->actionid,
		condition->conditionid,
		condition->value);

	if(condition->conditiontype == CONDITION_TYPE_HOST_GROUP)
	{
		ZBX_STR2UINT64(condition_value, condition->value);
		result = DBselect("select distinct hg.groupid from hosts_groups hg,hosts h, items i, functions f, triggers t where hg.hostid=h.hostid and h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=" ZBX_FS_UI64,
			event->objectid);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			while((row=DBfetch(result)))
			{
				ZBX_STR2UINT64(groupid, row[0]);
				if(condition_value == groupid)
				{
					ret = SUCCEED;
					break;
				}
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			ret = SUCCEED;
			while((row=DBfetch(result)))
			{
				ZBX_STR2UINT64(groupid, row[0]);
				if(condition_value == groupid)
				{
					ret = FAIL;
					break;
				}
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
		DBfree_result(result);
	}
	else if(condition->conditiontype == CONDITION_TYPE_HOST)
	{
		ZBX_STR2UINT64(condition_value, condition->value);
		result = DBselect("select distinct h.hostid from hosts h, items i, functions f, triggers t where h.hostid=i.hostid and i.itemid=f.itemid and f.triggerid=t.triggerid and t.triggerid=" ZBX_FS_UI64,
			event->objectid);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			while((row=DBfetch(result)))
			{
				ZBX_STR2UINT64(hostid, row[0]);
				if(condition_value == hostid)
				{
					ret = SUCCEED;
					break;
				}
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			ret = SUCCEED;
			while((row=DBfetch(result)))
			{
				ZBX_STR2UINT64(hostid, row[0]);
				if(condition_value == hostid)
				{
					ret = FAIL;
					break;
				}
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
			condition->operator,
			condition->conditionid);
		}
		DBfree_result(result);
	}
	else if(condition->conditiontype == CONDITION_TYPE_TRIGGER)
	{
		ZBX_STR2UINT64(condition_value, condition->value);
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_TRIGGER [" ZBX_FS_UI64 ":%s]",
			condition_value,
			condition->value);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(event->objectid == condition_value)
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			if(event->objectid != condition_value)
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [%d]",
				condition->operator,
				condition->conditionid);
		}
	}
	else if(condition->conditiontype == CONDITION_TYPE_TRIGGER_NAME)
	{
		tmp_str = zbx_dsprintf(tmp_str, "%s", event->trigger_description);
		
		substitute_simple_macros(event, NULL, &tmp_str, MACRO_TYPE_TRIGGER_DESCRIPTION);
		
		if(condition->operator == CONDITION_OPERATOR_LIKE)
		{
			if(strstr(tmp_str, condition->value) != NULL)
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_LIKE)
		{
			if(strstr(tmp_str, condition->value) == NULL)
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
		zbx_free(tmp_str);
	}
	else if(condition->conditiontype == CONDITION_TYPE_TRIGGER_SEVERITY)
	{
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(event->trigger_priority == atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_NOT_EQUAL)
		{
			if(event->trigger_priority != atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_MORE_EQUAL)
		{
			if(event->trigger_priority >= atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else if(condition->operator == CONDITION_OPERATOR_LESS_EQUAL)
		{
			if(event->trigger_priority <= atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
	}
	else if(condition->conditiontype == CONDITION_TYPE_TRIGGER_VALUE)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_TRIGGER_VALUE [%d:%s]",
			event->value,
			condition->value);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(event->value == atoi(condition->value))
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
	}
	else if(condition->conditiontype == CONDITION_TYPE_TIME_PERIOD)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_TRIGGER_VALUE [%d:%s]",
			event->value,
			condition->value);
		if(condition->operator == CONDITION_OPERATOR_IN)
		{
			if(check_time_period(condition->value, (time_t)NULL)==1)
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
	}
	else if(condition->conditiontype == CONDITION_TYPE_DHOST_IP)
	{
		/* Not implemente yet */
	}
	else if(condition->conditiontype == CONDITION_TYPE_DSERVICE_TYPE)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_DSERVICE_TYPE [%d:%s]",
			event->value,
			condition->value);
		value_int = atoi(condition->value);
		result = DBselect("select dserviceid from dservices where type=%d and dserviceid=" ZBX_FS_UI64,
			value_int,
			event->objectid);
		if(condition->operator == CONDITION_OPERATOR_EQUAL)
		{
			if(row && DBis_null(row[0]) != SUCCEED)
			{
				ret = SUCCEED;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
				condition->operator,
				condition->conditionid);
		}
		DBfree_result(result);
	}
	else if(condition->conditiontype == CONDITION_TYPE_DSERVICE_PORT)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "CONDITION_TYPE_DSERVICE_TYPE [%d:%s]",
			event->value,
			condition->value);
		value_int = atoi(condition->value);
		result = DBselect("select port from dservices where dserviceid=" ZBX_FS_UI64,
			event->objectid);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(condition->operator == CONDITION_OPERATOR_EQUAL)
			{
				if(value_int == atoi(row[0]))	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_LESS_EQUAL)
			{
				if(atoi(row[0]) <= value_int)	ret = SUCCEED;
			}
			else if(condition->operator == CONDITION_OPERATOR_MORE_EQUAL)
			{
				if(atoi(row[0]) >= value_int)	ret = SUCCEED;
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Unsupported operator [%d] for condition id [" ZBX_FS_UI64 "]",
					condition->operator,
					condition->conditionid);
			}
		}
		DBfree_result(result);
	}
	else
	{
		zabbix_log( LOG_LEVEL_ERR, "Condition type [%d] is unknown for condition id [" ZBX_FS_UI64 "]",
			condition->conditiontype,
			condition->conditionid);
	}

	if(FAIL==ret)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Condition is FALSE");
	}
	else
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Condition is TRUE");
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End check_action_condition()");
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_action_conditions                                          *
 *                                                                            *
 * Purpose: check if actions has to be processed for the event                *
 *          (check all condition of the action)                               *
 *                                                                            *
 * Parameters: event - event to check                                         *
 *             actionid - action ID for matching                             *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	check_action_conditions(DB_EVENT *event, DB_ACTION *action)
{
	DB_RESULT result;
	DB_ROW row;

	DB_CONDITION	condition;

	/* SUCCEED required for ACTION_EVAL_TYPE_AND_OR */	
	int	ret = SUCCEED;
	int	old_type = -1;
	int	cond;
	int	num = 0;
	int	exit = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In check_action_conditions (actionid:" ZBX_FS_UI64 ")",
		action->actionid);

	result = DBselect("select conditionid,actionid,conditiontype,operator,value from conditions where actionid=" ZBX_FS_UI64 " order by conditiontype",
		action->actionid);

	while((row=DBfetch(result)))
	{
		num++;

		ZBX_STR2UINT64(condition.conditionid, row[0]);
		ZBX_STR2UINT64(condition.actionid, row[1]);
		condition.conditiontype=atoi(row[2]);
		condition.operator=atoi(row[3]);
		condition.value=row[4];

		switch (action->evaltype) {
			case ACTION_EVAL_TYPE_AND_OR:
				/* OR conditions */
				if(old_type == condition.conditiontype)
				{
					if(check_action_condition(event, &condition) == SUCCEED)
						ret = SUCCEED;
				}
				/* AND conditions */
				else
				{
					/* Break if PREVIOUS AND condition is FALSE */
					if(ret == FAIL)
					{
						exit = 1;
					}
					else if(check_action_condition(event, &condition) == FAIL)
					{
						ret = FAIL;
					}
				}
		
				old_type = condition.conditiontype;
				break;
			case ACTION_EVAL_TYPE_AND:
				cond = check_action_condition(event, &condition);
				/* Break if any of AND conditions is FALSE */
				if(cond == FAIL)
				{
					ret = FAIL; exit = 1;
				}
				else
				{
					ret = SUCCEED;
				}
				break;
			case ACTION_EVAL_TYPE_OR:
				cond = check_action_condition(event, &condition);
				/* Break if any of OR conditions is TRUE */
				if(cond == SUCCEED)
				{
					ret = SUCCEED; exit = 1;
				}
				else
				{
					ret = FAIL;
				}
				break;
			default:
				zabbix_log( LOG_LEVEL_DEBUG, "End check_action_conditions (result:%d)",
					(FAIL==ret)?"FALSE":"TRUE");
				ret = FAIL;
				break;

		}

	}
	DBfree_result(result);

	/* Ifnot conditions defined, return SUCCEED*/ 
	if(num == 0)	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "End check_action_conditions (result:%d)",
		(FAIL==ret)?"FALSE":"TRUE");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: execute_operations                                               *
 *                                                                            *
 * Purpose: execute all operations linked to the action                       *
 *                                                                            *
 * Parameters: action - action to execute operations for                      *
 *                                                                            *
 * Return value: -                                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	execute_operations(DB_EVENT *event, DB_ACTION *action)
{
	DB_RESULT	result;
	DB_ROW		row;
	
	DB_OPERATION	operation;

	zabbix_log( LOG_LEVEL_DEBUG, "In execute_operations(actionid:" ZBX_FS_UI64 ")",
		action->actionid);

	result = DBselect("select operationid,actionid,operationtype,object,objectid,shortdata,longdata from operations where actionid=" ZBX_FS_UI64,
			action->actionid);
	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(operation.operationid,	row[0]);
		ZBX_STR2UINT64(operation.actionid,	row[1]);
		operation.operationtype			= atoi(row[2]);
		operation.object			= atoi(row[3]);
		ZBX_STR2UINT64(operation.objectid,	row[4]);

		operation.shortdata		= strdup(row[5]);
		operation.longdata		= strdup(row[6]);

		substitute_macros(event, action, &operation.shortdata);
		substitute_macros(event, action, &operation.longdata);
			
		if(operation.operationtype == OPERATION_TYPE_MESSAGE)
			send_to_user(event,action,&operation);
		else
			run_commands(event,&operation);

		zbx_free(operation.shortdata);
		zbx_free(operation.longdata);
	}
}


/******************************************************************************
 *                                                                            *
 * Function: process_actions                                                  *
 *                                                                            *
 * Purpose: process all actions that match single event                       *
 *                                                                            *
 * Parameters: event - event to apply actions for                             *
 *                                                                            *
 * Return value: -                                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: we check also trigger dependencies                               *
 *                                                                            *
 ******************************************************************************/
void	process_actions(DB_EVENT *event)
{
	DB_RESULT result;
	DB_ROW row;
	
	DB_ACTION action;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_actions(eventid:" ZBX_FS_UI64 ")",
		event->eventid);

	if(TRIGGER_VALUE_TRUE == event->value)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Check dependencies");

		result = DBselect("select count(*) from trigger_depends d,triggers t where d.triggerid_down=" ZBX_FS_UI64 " and d.triggerid_up=t.triggerid and t.value=%d",
			event->objectid,
			TRIGGER_VALUE_TRUE);
		row=DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(atoi(row[0])>0)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "Will not apply actions");
				DBfree_result(result);
				return;
			}
		}
		DBfree_result(result);
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Processing actions");

	result = DBselect("select actionid,evaltype,status,eventsource from actions where status=%d and eventsource=%d and" ZBX_COND_NODEID,
		ACTION_STATUS_ACTIVE,
		event->source,
		LOCAL_NODE("actionid"));

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(action.actionid, row[0]);
		action.evaltype		= atoi(row[1]);
		action.status		= atoi(row[2]);
		action.eventsource	= atoi(row[3]);

		if(check_action_conditions(event, &action) == SUCCEED)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Conditions match our event. Execute operations.");

			execute_operations(event, &action);

		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Conditions do not match our event. Do not execute operations.");
		}
	}
	DBfree_result(result);
	zabbix_log( LOG_LEVEL_DEBUG, "End process_actions()");
}
