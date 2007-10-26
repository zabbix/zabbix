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
static int	process_record(int sender_nodeid, int nodeid, char *record, char **tmp, int *tmp_allocated)
{
	char		value_esc[MAX_STRING_LEN], source[MAX_STRING_LEN], *r;
	zbx_uint64_t	id, itemid, value_uint;
	int		table, clock, timestamp, severity;
	double		value_dbl;
	int		res = FAIL;

	zabbix_log( LOG_LEVEL_DEBUG, "In process_record [%s]",
		record);

	r = record;
	if (NULL == r)
		goto error;

	r = zbx_get_next_field(r, tmp, tmp_allocated, ZBX_DM_DELIMITER);
	table = atoi(*tmp);

	if (NULL == r)
		goto error;

	r = zbx_get_next_field(r, tmp, tmp_allocated, ZBX_DM_DELIMITER);
	ZBX_STR2UINT64(itemid, *tmp);

	if (NULL == r)
		goto error;

	r = zbx_get_next_field(r, tmp, tmp_allocated, ZBX_DM_DELIMITER);
	clock = atoi(*tmp);

	if (NULL == r)
		goto error;

	r = zbx_get_next_field(r, tmp, tmp_allocated, ZBX_DM_DELIMITER);

	switch (table) {
	case ZBX_TABLE_HISTORY		:
		value_dbl = atof(*tmp);

		res = DBadd_history(itemid, value_dbl, clock);

		DBexecute("update items set lastvalue='"ZBX_FS_DBL"',lastclock=%d "
			"where itemid="ZBX_FS_UI64,
			value_dbl,
			clock,
			itemid);
		break;
	case ZBX_TABLE_HISTORY_UINT	:
		ZBX_STR2UINT64(value_uint, *tmp);

		res = DBadd_history_uint(itemid, value_uint, clock);

		DBexecute("update items set lastvalue='"ZBX_FS_UI64"',lastclock=%d "
			"where itemid="ZBX_FS_UI64,
			value_uint,
			clock,
			itemid);
		break;
	case ZBX_TABLE_HISTORY_STR	:
		zbx_hex2binary(*tmp);

		res = DBadd_history_str(itemid, *tmp, clock);

		DBescape_string(*tmp, value_esc, MAX_STRING_LEN);

		DBexecute("update items set lastvalue='%s',lastclock=%d "
			"where itemid=" ZBX_FS_UI64,
			value_esc,
			clock,
			itemid);
		break;
	case ZBX_TABLE_HISTORY_LOG	:
		ZBX_STR2UINT64(id, *tmp);

		if (NULL == r)
			goto error;

		r = zbx_get_next_field(r, tmp, tmp_allocated, ZBX_DM_DELIMITER);
		timestamp = atoi(*tmp);

		if (NULL == r)
			goto error;

		r = zbx_get_next_field(r, tmp, tmp_allocated, ZBX_DM_DELIMITER);
		strcpy(source, *tmp);

		if (NULL == r)
			goto error;

		r = zbx_get_next_field(r, tmp, tmp_allocated, ZBX_DM_DELIMITER);
		severity = atoi(*tmp);

		if (NULL == r)
			goto error;

		r = zbx_get_next_field(r, tmp, tmp_allocated, ZBX_DM_DELIMITER);
		zbx_hex2binary(*tmp);

		res = DBadd_history_log(id, itemid, *tmp, clock, timestamp, source, severity, NULL);
		break;
	}

	return res;
error:
	zabbix_log( LOG_LEVEL_ERR, "NODE %d: Received invalid record from node %d for node %d [%s]",
		CONFIG_NODEID,
		sender_nodeid,
		nodeid,
		record);

	return FAIL;
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
	char	*r, *newline = NULL, *tmp = NULL;
	int	tmp_allocated = 1024;
	int	firstline=1;
	int	nodeid=0;
	int	sender_nodeid=0;
	int	datalen;

	assert(data);

	datalen = strlen(data);

	zabbix_log( LOG_LEVEL_DEBUG, "In node_history(len:%d)", datalen);

	DBbegin();

	tmp = zbx_malloc(tmp, tmp_allocated);

	for (r = data; *r != '\0';) {
		if (NULL != (newline = strchr(r, '\n')))
			*newline = '\0';

		if (firstline == 1) {
/*			zabbix_log( LOG_LEVEL_DEBUG, "In node_history() process header [%s]", r);*/
			r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* constant 'History' */
			r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* sender_nodeid */
			sender_nodeid=atoi(tmp);
			r = zbx_get_next_field(r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* nodeid */
			nodeid=atoi(tmp);

			zabbix_log( LOG_LEVEL_WARNING, "NODE %d: Received history from node %d for node %d datalen %d",
				CONFIG_NODEID,
				sender_nodeid,
				nodeid,
				datalen);
			firstline=0;
		} else
			process_record(sender_nodeid, nodeid, r, &tmp, &tmp_allocated);

		if (newline != NULL) {
			*newline = '\n';
			r = newline + 1;
		} else
			break;
	}
	zbx_free(tmp);

	DBcommit();

	return SUCCEED;
}
