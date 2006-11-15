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


#include <stdlib.h>
#include <stdio.h>

#include <string.h>
#include <strings.h>

#include "db.h"
#include "log.h"
#include "zlog.h"
#include "common.h"

zbx_uint64_t	DBadd_action(zbx_uint64_t triggerid, zbx_uint64_t userid, char *subject, char *message, int scope, int severity, int recipient, zbx_uint64_t usrgrpid)
{
	int	actionid;
	char	subject_esc[ACTION_SUBJECT_LEN_MAX];
	char	message_esc[MAX_STRING_LEN];
	int 	exec_res;

	DBescape_string(subject,subject_esc,ACTION_SUBJECT_LEN_MAX);
	DBescape_string(message,message_esc,MAX_STRING_LEN);

	if(recipient == RECIPIENT_TYPE_GROUP)
	{
		userid = usrgrpid;
	}

	if(FAIL == (exec_res = DBexecute("insert into actions (triggerid, userid, subject, message, scope, severity, recipient) values (' ZBX_FS_UI64 ',' ZBX_FS_UI64 ', '%s', '%s', %d, %d, %d)", triggerid, userid, subject_esc, message_esc, scope, severity, recipient)))
	{
		return FAIL;
	}

	actionid = DBinsert_id(exec_res, "actions", "actionid");

	if(actionid==0)
	{
		return FAIL;
	}

	return actionid;
}

int	DBget_action_by_actionid(zbx_uint64_t actionid,DB_ACTION *action)
{
	DB_RESULT	result;
	DB_ROW		row;
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBget_action_by_actionid(%d)", actionid);

	result = DBselect("select userid,recipient,subject,message from actions where actionid=" ZBX_FS_UI64, actionid);
	row=DBfetch(result);

	if(!row)
	{
		ret = FAIL;
	}
	else
	{
		action->actionid=actionid;
		ZBX_STR2UINT64(action->userid, row[0]);
		action->recipient=atoi(row[1]);
		strscpy(action->subject,row[2]);
		strscpy(action->message,row[3]);
	}

	DBfree_result(result);

	return ret;
}
