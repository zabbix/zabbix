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
	char		tablename[MAX_STRING_LEN];
	char		fieldname[MAX_STRING_LEN];
	zbx_uint64_t	recid;
	int		op;
	int		valuetype;
	char		value_esc[MAX_STRING_LEN];
	int		i;
	char		*key=NULL;
	DB_RESULT	result;
	DB_ROW		row;
	char		*r, *buffer = NULL, *tmp = NULL, *fields_update = NULL, *fields = NULL, *values = NULL;
	int		buffer_allocated = 16*1024;
	int		tmp_allocated = 16*1024, tmp_offset = 0;
	int		fields_update_allocated = 16*1024, fields_update_offset = 0;
	int		fields_allocated = 4*1024, fields_offset = 0;
	int		values_allocated = 16*1024, values_offset = 0;
#if defined(HAVE_POSTGRESQL)
	int		len;
#endif /* HAVE_POSTGRESQL */

	zabbix_log( LOG_LEVEL_DEBUG, "In process_record [%s]", record);

	r = record;
	buffer = zbx_malloc(buffer, buffer_allocated);
	tmp = zbx_malloc(tmp, tmp_allocated);
	fields_update = zbx_malloc(fields_update, fields_update_allocated);
	fields = zbx_malloc(fields, fields_allocated);
	values = zbx_malloc(values, values_allocated);

	r = zbx_get_next_field(r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
	strcpy(tablename, buffer);

	r = zbx_get_next_field(r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
	sscanf(buffer, ZBX_FS_UI64,&recid);

	r = zbx_get_next_field(r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
	op = atoi(buffer);

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
		zabbix_log( LOG_LEVEL_WARNING, "Cannot find key field for table [%s]",
			tablename);
		return FAIL;
	}
	if(op==NODE_CONFIGLOG_OP_DELETE)
	{
		zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 16*1024, "delete from %s where %s=" ZBX_FS_UI64,
			tablename,
			key,
			recid);
		DBexecute("%s", tmp);
		return SUCCEED;
	}

	while(r != NULL)
	{
		r = zbx_get_next_field(r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
		strcpy(fieldname, buffer);

		r = zbx_get_next_field(r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
		valuetype=atoi(buffer);

		r = zbx_get_next_field(r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);
		if(op==NODE_CONFIGLOG_OP_UPDATE || op==NODE_CONFIGLOG_OP_ADD)
		{
			if(strcmp(buffer, "NULL") == 0)
			{
				zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset, 16*1024, "%s=NULL,",
					fieldname);
				zbx_snprintf_alloc(&values, &values_allocated, &values_offset, 16*1024, "NULL,");
			}
			else
			{
				if(valuetype == ZBX_TYPE_INT || valuetype == ZBX_TYPE_UINT || valuetype == ZBX_TYPE_ID || valuetype == ZBX_TYPE_FLOAT)
				{
					zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset, 16*1024, "%s=%s,",
						fieldname,
						buffer);
					zbx_snprintf_alloc(&values, &values_allocated, &values_offset, 16*1024, "%s,",
						buffer);
				}
				else if(valuetype == ZBX_TYPE_BLOB)
				{
					if(*buffer == '\0')
					{
						zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset, 16*1024, "%s='',",
							fieldname);
						zbx_snprintf_alloc(&values, &values_allocated, &values_offset, 16*1024, "'',");
					}
					else
					{
#if defined(HAVE_POSTGRESQL)
						len = zbx_hex2binary(buffer);
						zbx_pg_escape_bytea((u_char *)buffer, len, &tmp, &tmp_allocated);
						zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset, 16*1024, "%s='%s',",
							fieldname,
							tmp);
						zbx_snprintf_alloc(&values, &values_allocated, &values_offset, 16*1024, "'%s',",
							tmp);
#else
						zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset, 16*1024, "%s=0x%s,",
							fieldname,
							buffer);
						zbx_snprintf_alloc(&values, &values_allocated, &values_offset, 16*1024, "0x%s,",
							buffer);
#endif
					}
				}
				else /* ZBX_TYPE_TEXT, ZBX_TYPE_CHAR */
				{
					zbx_hex2binary(buffer);
					DBescape_string(buffer, value_esc,MAX_STRING_LEN);

					zbx_snprintf_alloc(&fields_update, &fields_update_allocated, &fields_update_offset, 16*1024, "%s='%s',",
						fieldname,
						value_esc);
					zbx_snprintf_alloc(&values, &values_allocated, &values_offset, 16*1024, "'%s',",
						value_esc);
				}
			}

			zbx_snprintf_alloc(&fields, &fields_allocated, &fields_offset, 4*1024, "%s,", fieldname);
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Unknown record operation [%d]",
				op);
			return FAIL;
		}
	}
	if(fields_offset != 0)		fields[fields_offset - 1]='\0';
	if(fields_update_offset != 0)	fields_update[fields_update_offset - 1]='\0';
	if(values_offset != 0)		values[values_offset - 1]='\0';

	if(op==NODE_CONFIGLOG_OP_UPDATE)
	{
		zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 16*1024, "update %s set %s where %s=" ZBX_FS_UI64,
			tablename,
			fields_update,
			key,
			recid);
/*zabbix_log( LOG_LEVEL_WARNING, "----- process_record() [5] [tmp_allocated:%d] [tmp_offset:%d] [tmp:%s]", tmp_allocated, tmp_offset, tmp);*/
	}
	else if(op==NODE_CONFIGLOG_OP_ADD)
	{
		result = DBselect("select 0 from %s where %s=" ZBX_FS_UI64,
			tablename,
			key,
			recid);
		row = DBfetch(result);
		if(row)
		{
			zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 16*1024, "update %s set %s where %s=" ZBX_FS_UI64,
				tablename,
				fields_update,
				key,
				recid);
		}
		else
		{
			zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 16*1024, "insert into %s (%s) values(%s)",
				tablename,
				fields,
				values);
		}
		DBfree_result(result);
	}
/*	zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]", tmp);*/
	if(FAIL == DBexecute("%s",tmp))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Failed [%s]",
			record);
	}

	zbx_free(buffer);
	zbx_free(tmp);
	zbx_free(fields_update);
	zbx_free(fields);
	zbx_free(values);

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
	char	*start, *newline = NULL, *tmp = NULL;
	int	tmp_allocated = 128;
	int	firstline=1;
	int	nodeid=0;
	int	sender_nodeid=0;
	int	datalen;

	datalen=strlen(data);

	zabbix_log( LOG_LEVEL_DEBUG, "In node_sync(len:%d)", datalen);

	DBbegin();

	tmp = zbx_malloc(tmp, tmp_allocated);

	for(start = data; *start != '\0';)
	{
		if(NULL != (newline = strchr(start, '\n')))
		{
			*newline = '\0';
		}

		if(firstline == 1)
		{
/*			zabbix_log( LOG_LEVEL_DEBUG, "First line [%s]", start);*/ 
			start = zbx_get_next_field(start, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* Data */
			start = zbx_get_next_field(start, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
			sender_nodeid=atoi(tmp);
			start = zbx_get_next_field(start, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
			nodeid=atoi(tmp);

			firstline=0;
			zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Received data from node %d for node %d datalen %d",
					CONFIG_NODEID,
					sender_nodeid,
					nodeid,
					datalen);
		}
		else
		{
/*			zabbix_log( LOG_LEVEL_DEBUG, "Got line [%s]", start);*/
			process_record(nodeid, start);
		}

		if(newline != NULL)
		{
			*newline = '\n';
			start = newline + 1;
			newline = NULL;
		}
		else
		{
			break;
		}
	}
	zbx_free(tmp);

	DBcommit();

	return SUCCEED;
}
