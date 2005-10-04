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

int	DBadd_new_host(char *server, int port, int status, int useip, char *ip, int disable_until, int available)
{
	char	sql[MAX_STRING_LEN];
	int	hostid;

	snprintf(sql, sizeof(sql)-1,"insert into hosts (host,port,status,useip,ip,disable_until,available) values ('%s',%d,%d,%d,'%d',%d,%d)", server, port, status, useip, ip, disable_until, available);
	if(FAIL == DBexecute(sql))
	{
		return FAIL;
	}

	hostid=DBinsert_id();

	if(hostid==0)
	{
		return FAIL;
	}

	return hostid;
}

int	DBhost_exists(char *server)
{
	DB_RESULT	*result;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;

	snprintf(sql,sizeof(sql)-1,"select hostid from hosts where host='%s'", server);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		ret = FAIL;
	}
	DBfree_result(result);

	return ret;
}
