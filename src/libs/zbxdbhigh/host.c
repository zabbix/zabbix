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

/* for setproctitle() */
#include <sys/types.h>
#include <unistd.h>

#include <string.h>
#include <strings.h>

#include "db.h"
#include "log.h"
#include "zlog.h"
#include "common.h"

int	DBadd_new_host(server, port, status, useip, ip, disable_until, available)
{
	char	sql[MAX_STRING_LEN];
	int	hostid;

	snprintf(sql, sizeof(sql)-1,"insert into hosts (host,port,status,useip,ip,disable_until,available) values ('%s',%d,%d,useip,'%d',%d,%d)", server, port, status, useip, ip, disable_until, available);
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
