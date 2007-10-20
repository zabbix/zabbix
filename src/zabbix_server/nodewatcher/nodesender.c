/* 
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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

#include "common.h"

#include "cfg.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "dbsync.h"
#include "nodecomms.h"
#include "nodesender.h"

#define	ZBX_NODE_MASTER	0
#define	ZBX_NODE_SLAVE	1

/******************************************************************************
 *                                                                            *
 * Function: get_slave_node                                                   *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int get_slave_node(int nodeid, int synked_nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		master_nodeid;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_slave_node(%d)",
		nodeid);

	result = DBselect("select masterid from nodes where nodeid=%d",
		synked_nodeid);
	if (NULL != (row = DBfetch(result)))
		master_nodeid = atoi(row[0]);
	else
		master_nodeid = 0;
	DBfree_result(result);

	if (master_nodeid == 0)
		return 0;
	if (master_nodeid == nodeid)
		return synked_nodeid;
	return get_slave_node(nodeid, master_nodeid);
}

/******************************************************************************
 *                                                                            *
 * Function: get_master_node                                                  *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int get_master_node(int nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_master_node(%d)",
		nodeid);

	result = DBselect("select masterid from nodes where nodeid=%d",
		nodeid);
	if (NULL != (row = DBfetch(result))) {
		ret = atoi(row[0]);
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_config_data                                                 *
 *                                                                            *
 * Purpose: send configuration changes to required node                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
char *get_config_data(int synked_nodeid, int dest_nodetype)
{
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	DB_ROW		row2;

	char	*data = NULL, *hex = NULL, *sql = NULL, c, sync[131], *s, *r[2], *d[2];
	int	data_offset=0, sql_offset = 0;
	int	data_allocated=1024, hex_allocated=1024, sql_allocated=8*1024;
	int	t, f, j, rowlen, found = 0;
	
	zabbix_log( LOG_LEVEL_DEBUG, "In get_config_data(synked_node:%d,dest_nodetype:%s)",
		synked_nodeid,
		dest_nodetype == ZBX_NODE_MASTER ? "MASTER" : "SLAVE");

	data = zbx_malloc(data, data_allocated);
	hex = zbx_malloc(hex, hex_allocated);
	sql = zbx_malloc(sql, sql_allocated);
	c = '1';

	/* Find updated records */
	result = DBselect("select curr.tablename,curr.recordid,prev.cksum,curr.cksum,prev.sync "
		"from node_cksum curr, node_cksum prev "
		"where curr.nodeid=%1$d and prev.nodeid=%1$d and "
		"curr.tablename=prev.tablename and curr.recordid=prev.recordid and "
		"curr.cksumtype=%3$d and prev.cksumtype=%2$d "
		/*" and curr.tablename='hosts' "*/
		"union all "
	/* Find new records */
		"select curr.tablename,curr.recordid,prev.cksum,curr.cksum,curr.sync "
		"from node_cksum curr left join node_cksum prev "
		"on prev.nodeid=%1$d and prev.tablename=curr.tablename and "
		"prev.recordid=curr.recordid and prev.cksumtype=%2$d "
		"where curr.nodeid=%1$d and curr.cksumtype=%3$d and prev.tablename is null "
		/*" and curr.tablename='hosts' "*/
		"union all "
	/* Find deleted records */
		"select prev.tablename,prev.recordid,prev.cksum,curr.cksum,prev.sync "
		"from node_cksum prev left join node_cksum curr "
		"on prev.nodeid=curr.nodeid and curr.nodeid=%1$d and curr.tablename=prev.tablename and "
		"curr.recordid=prev.recordid and curr.cksumtype=%3$d "
		"where prev.nodeid=%1$d and prev.cksumtype=%2$d and curr.tablename is null"
		/*" and prev.tablename='hosts' "*/,
		synked_nodeid,
		NODE_CKSUM_TYPE_OLD,  /* prev */
		NODE_CKSUM_TYPE_NEW); /* curr */

	zbx_snprintf_alloc(&data, &data_allocated, &data_offset, 128, "Data%c%d%c%d\n",
		ZBX_DM_DELIMITER,
		CONFIG_NODEID,
		ZBX_DM_DELIMITER,
		synked_nodeid);

	while (NULL != (row = DBfetch(result))) {
		for (t = 0; tables[t].table != 0 && strcmp(tables[t].table, row[0]) != 0; t++)
			;

		/* Found table */
		if (tables[t].table == 0) {
			zabbix_log( LOG_LEVEL_WARNING, "Cannot find table [%s]",
				row[0]);
			continue;
		}

		if (DBis_null(row[4]) == FAIL)
			strcpy(sync, row[4]);
		else
			memset(sync, ' ', sizeof(sync));
		s = sync;

		/* Special (simpler) processing for operation DELETE */
		if (DBis_null(row[2]) == FAIL && DBis_null(row[3]) == SUCCEED &&
			((dest_nodetype == ZBX_NODE_SLAVE && *s != c) ||
			(dest_nodetype == ZBX_NODE_MASTER && *(s+1) != c))) {
			zbx_snprintf_alloc(&data, &data_allocated, &data_offset, 128, "%s%c%s%c%d\n",
				row[0],
				ZBX_DM_DELIMITER,
				row[1],
				ZBX_DM_DELIMITER,
				NODE_CONFIGLOG_OP_DELETE);
			found = 1;
			continue;
		}

		r[0] = row[2];
		r[1] = row[3];
		f = 0;
		sql_offset = 0;
		s += 2;

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "select ");
		do {
			if ((tables[t].fields[f].flags & ZBX_SYNC) == 0)
				f++;

			if (strcmp(tables[t].recid, tables[t].fields[f].name) == 0)
				f++;

			d[0] = NULL;
			d[1] = NULL;
			if (NULL != r[0] && NULL != (d[0] = strchr(r[0], ZBX_CKSUM_DELIMITER)))
				*d[0] = '\0';
			if (NULL != r[1] && NULL != (d[1] = strchr(r[1], ZBX_CKSUM_DELIMITER)))
				*d[1] = '\0';

			if (r[0] == NULL || r[1] == NULL || (dest_nodetype == ZBX_NODE_SLAVE && *s != c) ||
				(dest_nodetype == ZBX_NODE_MASTER && *(s+1) != c) || strcmp(r[0], r[1]) != 0) {
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "%s,length(%1$s),", 
					tables[t].fields[f].name);
			}
			s += 2;

			if (d[0] != NULL) {
				*d[0] = ZBX_CKSUM_DELIMITER;
				r[0] = d[0] + 1;
			} 
			if (d[1] != NULL) {
				*d[1] = ZBX_CKSUM_DELIMITER;
				r[1] = d[1] + 1;
			} 

			if (d[0] == NULL && d[1] == NULL)
				break;
			f++;
		} while (1);

		if (sql[sql_offset-1] != ',')
			continue;

		sql_offset--;
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, " from %s where %s=%s",
			row[0],
			tables[t].recid,
			row[1]);

		result2 = DBselect("%s", sql);
		if (NULL == (row2=DBfetch(result2)))
			goto out;

		zbx_snprintf_alloc(&data, &data_allocated, &data_offset, 128, "%s%c%s%c%d",
			row[0],
			ZBX_DM_DELIMITER,
			row[1],
			ZBX_DM_DELIMITER,
			NODE_CONFIGLOG_OP_UPDATE);

		r[0] = row[2];
		r[1] = row[3];
		s = sync + 2;
		f = 0;
		j = 0;

		do {
			if ((tables[t].fields[f].flags & ZBX_SYNC) == 0)
				f++;

			if (strcmp(tables[t].recid, tables[t].fields[f].name) == 0)
				f++;

			d[0] = NULL;
			d[1] = NULL;
			if (NULL != r[0] && NULL != (d[0] = strchr(r[0], ZBX_CKSUM_DELIMITER)))
				*d[0] = '\0';
			if (NULL != r[1] && NULL != (d[1] = strchr(r[1], ZBX_CKSUM_DELIMITER)))
				*d[1] = '\0';

			if (r[0] == NULL || r[1] == NULL || (dest_nodetype == ZBX_NODE_SLAVE && *s != c) ||
				(dest_nodetype == ZBX_NODE_MASTER && *(s+1) != c) || strcmp(r[0], r[1]) != 0) {

				zbx_snprintf_alloc(&data, &data_allocated, &data_offset, 128, "%c%s%c%d%c",
					ZBX_DM_DELIMITER,
					tables[t].fields[f].name,
					ZBX_DM_DELIMITER,
					tables[t].fields[f].type,
					ZBX_DM_DELIMITER);

				/* Fieldname, type, value */
				if (DBis_null(row2[j*2]) == SUCCEED) {
					zbx_snprintf_alloc(&data, &data_allocated, &data_offset, 128, "NULL");
				} else if(tables[t].fields[f].type == ZBX_TYPE_INT ||
					tables[t].fields[f].type == ZBX_TYPE_UINT ||
					tables[t].fields[f].type == ZBX_TYPE_ID ||
					tables[t].fields[f].type == ZBX_TYPE_FLOAT) {

					zbx_snprintf_alloc(&data, &data_allocated, &data_offset, 128, "%s", row2[j*2]);
				} else {
					rowlen = atoi(row2[j*2+1]);
					zbx_binary2hex((u_char *)row2[j*2], rowlen, &hex, &hex_allocated);
					zbx_snprintf_alloc(&data, &data_allocated, &data_offset, strlen(hex)+128, "%s", hex);
/*zabbix_log(LOG_LEVEL_CRIT, "----- [field:%s][type:%d][row:%s][hex:%s]",tables[t].fields[f].name,tables[t].fields[f].type,row2[j*2],hex);*/
				}
				found = 1;
				j++;
			}
			s += 2;

			if (d[0] != NULL) {
				*d[0] = ZBX_CKSUM_DELIMITER;
				r[0] = d[0] + 1;
			} 
			if (d[1] != NULL) {
				*d[1] = ZBX_CKSUM_DELIMITER;
				r[1] = d[1] + 1;
			} 

			if (d[0] == NULL && d[1] == NULL)
				break;
			f++;
		} while (1);
		zbx_snprintf_alloc(&data, &data_allocated, &data_offset, 128, "\n");
out:
		DBfree_result(result2);
	}
	DBfree_result(result);

	zbx_free(hex);
	zbx_free(sql);

	if (0 == found) {
		zbx_free(data);
		data = NULL;
	}

	return data;
}

/******************************************************************************
 *                                                                            *
 * Function: main_nodesender                                                  *
 *                                                                            *
 * Purpose: periodically sends config changes and history to related nodes    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              * 
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void main_nodesender(int synked_nodeid, int *synked_slave, int *synked_master)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		nodeid, slave_nodeid, master_nodeid;
	char		*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_nodesender()");

	*synked_slave = FAIL;
	*synked_master = FAIL;

	result = DBselect("select nodeid from nodes where nodetype=%d",
		ZBX_NODE_TYPE_LOCAL);

	if (NULL != (row = DBfetch(result))) {
		nodeid = atoi(row[0]);
		if (CONFIG_NODEID != nodeid) {
			zabbix_log(LOG_LEVEL_WARNING, "NodeID does not match configuration settings."
				" Processing of the node is disabled.");
		} else {
			slave_nodeid = get_slave_node(nodeid, synked_nodeid);
			master_nodeid = CONFIG_MASTER_NODEID;
			if (0 != slave_nodeid && NULL != (data = get_config_data(synked_nodeid, ZBX_NODE_SLAVE))) {
/*zabbix_log(LOG_LEVEL_CRIT, "-----> [%s]", data);*/
				*synked_slave = send_to_node("configuration changes", slave_nodeid, synked_nodeid, data);
				zbx_free(data);
			}
			if (0 != master_nodeid && NULL != (data = get_config_data(synked_nodeid, ZBX_NODE_MASTER))) {
/*zabbix_log(LOG_LEVEL_CRIT, "-----> [%s]", data);*/
				*synked_master = send_to_node("configuration changes", master_nodeid, synked_nodeid, data);
				zbx_free(data);
			}
		}
	}
	DBfree_result(result);
}
