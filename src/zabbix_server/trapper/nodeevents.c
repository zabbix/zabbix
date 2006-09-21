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

#include "dbsync.h"
#include "nodeevents.h"

/******************************************************************************
 *                                                                            *
 * Function: process_record                                                   *
 *                                                                            *
 * Purpose: process record update                                             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_record(int nodeid, char *record)
{
	zbx_uint64_t	eventid;
	zbx_uint64_t	triggerid;
	int	clock;
	int	value;
	int	acknowledged;
	
	char	tmp[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_WARNING, "In process_record [%s]", record);

	get_field(record,tmp,0);
	sscanf(tmp,ZBX_FS_UI64,&eventid);
	get_field(record,tmp,1);
	sscanf(tmp,ZBX_FS_UI64,&triggerid);
	get_field(record,tmp,2);
	clock=atoi(tmp);
	get_field(record,tmp,3);
	value=atoi(tmp);
	get_field(record,tmp,4);
	acknowledged=atoi(tmp);

	DBexecute("insert into events (eventid, triggerid, clock, value, acknowledged)"
		" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,%d)",
		eventid, triggerid, clock, value, acknowledged);

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Function: node_events                                                      *
 *                                                                            *
 * Purpose: process new events received from a salve node                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	node_events(char *data)
{
	char	*s;
	int	firstline=1;
	int	nodeid=0;
	int	sender_nodeid=0;
	char	tmp[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_WARNING, "In node_events(len:%d)", strlen(data));

       	s=(char *)strtok(data,"\n");
	while(s!=NULL)
	{
		if(firstline == 1)
		{
//			zabbix_log( LOG_LEVEL_WARNING, "First line [%s]", s);
			get_field(s,tmp,1);
			sender_nodeid=atoi(tmp);
			get_field(s,tmp,2);
			nodeid=atoi(tmp);
			firstline=0;
			zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Received events from node %d for node %d", CONFIG_NODEID, sender_nodeid, nodeid);
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Got line [%s]", s);
			process_record(nodeid, s);
		}

       		s=(char *)strtok(NULL,"\n");
	}
	return SUCCEED;
}
