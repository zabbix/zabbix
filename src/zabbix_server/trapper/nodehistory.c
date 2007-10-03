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
	char		*tmp = NULL, value_esc[MAX_STRING_LEN], source[MAX_STRING_LEN], *r;
	int		tmp_allocated = 1024, table;
	zbx_uint64_t	id, itemid, value_uint;
	int		clock, timestamp, severity;
	double		value;
	int		res = FAIL;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_record [%s]",
		record);

	tmp = zbx_malloc(tmp, tmp_allocated);

	r = record;
	r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
	table = atoi(tmp);

	r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
	ZBX_STR2UINT64(itemid, tmp);

	r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
	clock = atoi(tmp);

	if(table == ZBX_TABLE_HISTORY)
	{
		r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
		value = atof(tmp);

		res = DBadd_history(itemid, value, clock);

		DBexecute("update items set lastvalue='" ZBX_FS_DBL "', lastclock=%d where itemid=" ZBX_FS_UI64,
			value,
			clock,
			itemid);

	}
	else if(table == ZBX_TABLE_HISTORY_UINT)
	{
		r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
		ZBX_STR2UINT64(value_uint, tmp);

		res = DBadd_history_uint(itemid, value_uint, clock);

		DBexecute("update items set lastvalue='" ZBX_FS_UI64 "', lastclock=%d where itemid=" ZBX_FS_UI64,
			value_uint,
			clock,
			itemid);
	}
	else if(table == ZBX_TABLE_HISTORY_STR)
	{
		r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);

		res = DBadd_history_str(itemid, tmp, clock);

		DBescape_string(tmp, value_esc, MAX_STRING_LEN);

		DBexecute("update items set lastvalue='%s', lastclock=%d where itemid=" ZBX_FS_UI64,
			value_esc,
			clock,
			itemid);
	}
	else if(table == ZBX_TABLE_HISTORY_LOG)
	{
		r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
		ZBX_STR2UINT64(id, tmp);

		r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
		timestamp = atoi(tmp);

		r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
		strcpy(source, tmp);

		r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
		severity = atoi(tmp);

		r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
		zbx_hex2binary(tmp);

		res = DBadd_history_log(id, itemid, tmp, clock, timestamp, source, severity);
	}
	zbx_free(tmp);

	return res;
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
int	node_history(char *data)
{
	char	*start, *newline = NULL, *tmp = NULL;
	int	tmp_allocated = 128;
	int	firstline=1;
	int	nodeid=0;
	int	sender_nodeid=0;
	int	datalen;

	datalen=strlen(data);

	zabbix_log( LOG_LEVEL_DEBUG, "In node_history(len:%d)", datalen);

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
			zabbix_log( LOG_LEVEL_DEBUG, "In node_history() process header [%s]", start);
			start = zbx_get_next_field(start, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* History */
			start = zbx_get_next_field(start, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
			sender_nodeid=atoi(tmp);
			start = zbx_get_next_field(start, &tmp, &tmp_allocated, ZBX_DM_DELIMITER);
			nodeid=atoi(tmp);

			firstline=0;
			zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Received history from node %d for node %d datalen %d",
					CONFIG_NODEID,
					sender_nodeid,
					nodeid,
					datalen);
		}
		else
		{
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
