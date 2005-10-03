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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>

#include <string.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "common.h"

int	autoregister(char *server)
{
	DB_RESULT	*result;

	int	ret=SUCCEED;
	char	sql[MAX_STRING_LEN];
	char	*pattern;
	int	i;
	
	zabbix_log( LOG_LEVEL_WARNING, "In autoregister(%s)",server);

	snprintf(sql,sizeof(sql)-1,"select id,pattern,priority,hostid from functions order by priority");

	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		pattern=DBget_field(result,i,0);
	}

	DBfreeResult(result);

	return ret;
}
