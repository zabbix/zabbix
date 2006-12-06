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
#include "nodesync.h"

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
	char	tablename[MAX_STRING_LEN];
	char	fieldname[MAX_STRING_LEN];
	zbx_uint64_t	recid;
	int	op;
	int	valuetype;
	char	value[MAX_STRING_LEN];
	char	value_esc[MAX_STRING_LEN];
	char	tmp[MAX_STRING_LEN];
	char	sql[MAX_STRING_LEN];
	int	i;
	char	*key=NULL;
	char	fields[MAX_STRING_LEN];
	char	fields_update[MAX_STRING_LEN];
	char	values[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

/*	zabbix_log( LOG_LEVEL_WARNING, "In process_record [%s]", record);*/

	zbx_get_field(record,tablename,0,'|');
	zbx_get_field(record,tmp,1,'|');
	sscanf(tmp,ZBX_FS_UI64,&recid);
	zbx_get_field(record,tmp,2,'|');
	op=atoi(tmp);

	for(i=0;tables[i].table!=0;i++)
	{
		if(strcmp(tables[i].table, tablename)==0)
		{
			key=tables[i].recid;
			break;
		}
	}

	if(key == NULL)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot find key field for table [%s]",tablename);
		return FAIL;
	}
	if(op==NODE_CONFIGLOG_OP_DELETE)
	{
		zbx_snprintf(tmp,sizeof(tmp),"delete from %s where %s=" ZBX_FS_UI64 " and nodeid=%d", tablename, key, recid, nodeid);
		zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]", sql);
		return SUCCEED;
	}

	i=3;
	fields[0]=0;
	fields_update[0]=0;
	values[0]=0;
	while(zbx_get_field(record,fieldname,i++,'|')==SUCCEED)
	{
		tmp[0]=0;
		zbx_get_field(record,tmp,i++,'|');
		valuetype=atoi(tmp);
		value[0]=0;
		zbx_get_field(record,value,i++,'|');
		if(op==NODE_CONFIGLOG_OP_UPDATE || op==NODE_CONFIGLOG_OP_ADD)
		{
			if(strcmp(value,"NULL")==0)
			{
				zbx_snprintf(tmp,sizeof(tmp),"%s=NULL,", fieldname);
				zbx_strlcat(fields_update,tmp,sizeof(fields));

				zbx_snprintf(tmp,sizeof(tmp),"NULL,", value);
			}
			else
			{
				if(valuetype == ZBX_TYPE_INT || valuetype == ZBX_TYPE_UINT || valuetype == ZBX_TYPE_ID)
				{
					zbx_snprintf(tmp,sizeof(tmp),"%s=%s,", fieldname, value);
					zbx_strlcat(fields_update,tmp,sizeof(fields));

					zbx_snprintf(tmp,sizeof(tmp),"%s,", value);
				}
				else
				{
					DBescape_string(value, value_esc,MAX_STRING_LEN);

					zbx_snprintf(tmp,sizeof(tmp),"%s='%s',", fieldname, value_esc);
					zbx_strlcat(fields_update,tmp,sizeof(fields));
	
					zbx_snprintf(tmp,sizeof(tmp),"'%s',", value_esc);
				}
			}

			zbx_strlcat(values,tmp,sizeof(values));
/*			zabbix_log( LOG_LEVEL_WARNING, "VALUES [%s]", values);*/
			zbx_snprintf(tmp,sizeof(tmp),"%s,", fieldname);
			zbx_strlcat(fields,tmp,sizeof(fields));
/*			zabbix_log( LOG_LEVEL_WARNING, "FIELDS [%s]", fields);*/
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Unknown record operation [%d]",op);
			return FAIL;
		}
	}
	if(fields[0]!=0)	fields[strlen(fields)-1]=0;
	if(fields_update[0]!=0)	fields_update[strlen(fields_update)-1]=0;
	if(values[0]!=0)	values[strlen(values)-1]=0;

	if(op==NODE_CONFIGLOG_OP_UPDATE)
	{
		zbx_snprintf(sql,sizeof(sql),"update %s set %s where %s=" ZBX_FS_UI64, tablename, fields_update, key, recid);
	}
	else if(op==NODE_CONFIGLOG_OP_ADD)
	{
		result = DBselect("select 0 from %s where %s=" ZBX_FS_UI64, tablename, key, recid);
		row = DBfetch(result);
		if(row)
		{
			zbx_snprintf(sql,sizeof(sql),"update %s set %s where %s=" ZBX_FS_UI64, tablename, fields_update, key, recid);
		}
		else
		{
			zbx_snprintf(sql,sizeof(sql),"insert into %s (%s) values(%s)", tablename, fields, values);
		}
		DBfree_result(result);
	}
/*	zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]", sql);*/
	if(FAIL == DBexecute(sql))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Failed [%s]", record);
	}

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Function: node_sync                                                        *
 *                                                                            *
 * Purpose: process configuration changes received from a node                *
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
int	node_sync(char *data)
{
	char	*s;
	int	firstline=1;
	int	nodeid=0;
	int	sender_nodeid=0;
	char	tmp[MAX_STRING_LEN];
	int	datalen;

	datalen=strlen(data);

	zabbix_log( LOG_LEVEL_DEBUG, "In node_sync(len:%d)", datalen);

	DBbegin();

       	s=(char *)strtok(data,"\n");
	while(s!=NULL)
	{
		if(firstline == 1)
		{
/*			zabbix_log( LOG_LEVEL_WARNING, "First line [%s]", s); */
			zbx_get_field(s,tmp,1,'|');
			sender_nodeid=atoi(tmp);
			zbx_get_field(s,tmp,2,'|');
			nodeid=atoi(tmp);
			firstline=0;
			zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Received data from node %d for node %d datalen %d",
					CONFIG_NODEID, sender_nodeid, nodeid, datalen);
		}
		else
		{
/*			zabbix_log( LOG_LEVEL_WARNING, "Got line [%s]", s);*/
			process_record(nodeid, s);
		}

       		s=(char *)strtok(NULL,"\n");
	}
	DBcommit();

	return SUCCEED;
}
