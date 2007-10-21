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
#include "events.h"
#include "history.h"
#include "nodewatcher.h"
#include "nodesender.h"

/******************************************************************************
 *                                                                            *
 * Function: calculate_checksums                                              *
 *                                                                            *
 * Purpose: calculate check sums of configuration data                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - calculated succesfully                             * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int calculate_checksums(int nodeid, const char *tablename, const zbx_uint64_t id)
{
	char		*sql = NULL;
	int		sql_allocated = 16*1024, sql_offset = 0;
	int		t, f, res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In calculate_checksums");

	sql = zbx_malloc(sql, sql_allocated);

	for (t = 0; tables[t].table != 0; t++) {
		/* Do not sync some of tables */
		if ((tables[t].flags & ZBX_SYNC) == 0)
			continue;

		if (NULL != tablename && 0 != strcmp(tablename, tables[t].table))
			continue;

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
#ifdef	HAVE_MYSQL
			"%s select %d,'%s',%s,%d,concat(",
#else
			"%s select %d,'%s',%s,%d,",
#endif
			sql_offset > 0 ? "union all" : "insert into node_cksum (nodeid,tablename,recordid,cksumtype,cksum)",
			nodeid,
			tables[t].table,
			tables[t].recid,
			NODE_CKSUM_TYPE_NEW);

		for (f = 0; tables[t].fields[f].name != 0; f ++) {
			if ((tables[t].fields[f].flags & ZBX_SYNC) == 0)
				continue;

			if (strcmp(tables[t].recid, tables[t].fields[f].name) == 0)
				continue;

			if (tables[t].fields[f].flags & ZBX_NOTNULL) {
				switch ( tables[t].fields[f].type ) {
				case ZBX_TYPE_ID	:
				case ZBX_TYPE_INT	:
				case ZBX_TYPE_UINT	:
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
						"%s",
						tables[t].fields[f].name);
					break;
				default	:
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
						"md5(%s)",
						tables[t].fields[f].name);
					break;
				}
			} else {
				switch ( tables[t].fields[f].type ) {
				case ZBX_TYPE_ID	:
				case ZBX_TYPE_INT	:
				case ZBX_TYPE_UINT	:
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
						"case when %s is null then 'NULL' else %1$s end",
						tables[t].fields[f].name);
					break;
				default	:
					zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
						"case when %s is null then 'NULL' else md5(%1$s) end",
						tables[t].fields[f].name);
					break;
				}
			}
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 16,
#ifdef	HAVE_MYSQL
				",'%c',",
#else
				"||'%c'||",
#endif
				ZBX_CKSUM_DELIMITER);
		}

		/* remove last delimiter */
		if (f > 0) {
#ifdef	HAVE_MYSQL
			sql_offset -= 5;
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 16, ")");
#else
			sql_offset -= 7;
#endif
		}

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
			" from %s where"ZBX_COND_NODEID,
			tables[t].table,
			ZBX_NODE(tables[t].recid,nodeid));

		if (0 != id) {
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
				"and %s="ZBX_FS_UI64,
				tables[t].recid,
				id);
		}
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "\n");
	}
	if (DBexecute("%s", sql) < ZBX_DB_OK)
		res = FAIL;
	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: update_checksums                                                 *
 *                                                                            *
 * Purpose: overwrite old checksums with new ones                             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - calculated succesfully                             * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int update_checksums(int nodeid, int synked_slave, int synked_master, const char *tablename, const zbx_uint64_t id, char *fields)
{
	char		*r[2], *d[2], sync[131], *s;
	char		cs, cm, sql[2][256];
	DB_RESULT	result;
	DB_ROW		row;
	int		t, f;

	zabbix_log(LOG_LEVEL_DEBUG, "In update_checksums");

	cs = synked_slave == SUCCEED ? '1' : ' ';
	cm = synked_master == SUCCEED ? '1' : ' ';

	if (NULL != tablename) {
		zbx_snprintf(sql[0], sizeof(sql[0]), " and curr.tablename='%s' and curr.recordid="ZBX_FS_UI64,
			tablename, id);
		zbx_snprintf(sql[1], sizeof(sql[1]), " and prev.tablename='%s' and prev.recordid="ZBX_FS_UI64,
			tablename, id);
	} else {
		*sql[0] = '\0';
		*sql[1] = '\0';
	}

	/* Find updated records */
	result = DBselect("select curr.tablename,curr.recordid,prev.cksum,curr.cksum,prev.sync "
		"from node_cksum curr, node_cksum prev "
		"where curr.nodeid=%1$d and prev.nodeid=%1$d and "
		"curr.tablename=prev.tablename and curr.recordid=prev.recordid and "
		"curr.cksumtype=%3$d and prev.cksumtype=%2$d%4$s "
		"union all "
	/* Find new records */
		"select curr.tablename,curr.recordid,prev.cksum,curr.cksum,NULL "
		"from node_cksum curr left join node_cksum prev "
		"on prev.nodeid=%1$d and prev.tablename=curr.tablename and "
		"prev.recordid=curr.recordid and prev.cksumtype=%2$d "
		"where curr.nodeid=%1$d and curr.cksumtype=%3$d and prev.tablename is null%4$s "
		"union all "
	/* Find deleted records */
		"select prev.tablename,prev.recordid,prev.cksum,curr.cksum,prev.sync "
		"from node_cksum prev left join node_cksum curr "
		"on prev.nodeid=curr.nodeid and curr.nodeid=%1$d and curr.tablename=prev.tablename and "
		"curr.recordid=prev.recordid and curr.cksumtype=%3$d "
		"where prev.nodeid=%1$d and prev.cksumtype=%2$d and curr.tablename is null%5$s",
		nodeid,
		NODE_CKSUM_TYPE_OLD,	/* prev */
		NODE_CKSUM_TYPE_NEW,	/* curr */
		sql[0],
		sql[1]);

	while (NULL != (row = DBfetch(result))) {
		for (t = 0; tables[t].table != 0 && strcmp(tables[t].table, row[0]) != 0; t++)
			;

		/* Found table */
		if (tables[t].table == 0) {
			zabbix_log(LOG_LEVEL_WARNING, "Cannot find table [%s]",
				row[0]);
			continue;
		}

		if (DBis_null(row[4]) == FAIL)
			strcpy(sync, row[4]);
		else
			memset(sync, ' ', sizeof(sync));
		s = sync;

		/* Special (simpler) processing for operation DELETE */
		if (DBis_null(row[3]) == SUCCEED) {
			if (synked_slave == SUCCEED && *s != cs)
				*s = cs;
			if (synked_master == SUCCEED && *(s+1) != cm) 
				*(s+1) = cm;
			s += 2;
		} else {
			r[0] = row[2];
			r[1] = row[3];
			s += 2;
			f = 0;

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

				if (NULL == tablename || SUCCEED == str_in_list(fields, tables[t].fields[f].name, ',')) {
					if (r[0] == NULL || r[1] == NULL || strcmp(r[0], r[1]) != 0) {
						*s = cs;
						*(s+1) = cm;
					} else {
						if (synked_slave == SUCCEED && *s != cs)
							*s = cs;
						if (synked_master == SUCCEED && *(s+1) != cm)
							*(s+1) = cm;
					}
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
		}
		*s = '\0';

		if (DBis_null(row[2]) == SUCCEED || DBis_null(row[3]) == SUCCEED ||
			strcmp(row[4], sync) != 0 || strcmp(row[2], row[3]) != 0) {
			DBexecute("update node_cksum set cksumtype=%d,cksum=\'%s\',sync=\'%s\' "
				"where nodeid=%d and tablename=\'%s\' and recordid=%s and cksumtype=%d",
				NODE_CKSUM_TYPE_OLD,
				DBis_null(row[3]) == SUCCEED ? row[2] : row[3],
				sync,
				nodeid,
				row[0],
				row[1],
				DBis_null(row[2]) == SUCCEED ? NODE_CKSUM_TYPE_NEW : NODE_CKSUM_TYPE_OLD);
		}
	}
	DBfree_result(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: lock_node                                                        *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              * 
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int lock_sync_node(int nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		sync;
	int		res = FAIL;
	int		retry = 0;

retry_lock:
	if (DBexecute("update nodes set sync=sync+1 where nodeid=%d",
		nodeid) >= ZBX_DB_OK) {

		result = DBselect("select sync from nodes where nodeid=%d",
			nodeid);
		if (NULL != (row=DBfetch(result))) {
			sync = atoi(row[0]);
			if (sync == 1 || sync > 25) {
				if (DBexecute("delete from node_cksum where nodeid=%d and cksumtype=%d",
					nodeid,
					NODE_CKSUM_TYPE_NEW) >= ZBX_DB_OK) {
					res = SUCCEED;
				}
			} else if (retry++ < 3) {
				sleep(5);
				goto retry_lock;
			}
		}
		DBfree_result(result);
	}
	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: process_nodes                                                    *
 *                                                                            *
 * Purpose: calculates checks sum of config data                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              * 
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void process_nodes()
{
	DB_RESULT	result;
	DB_ROW		row;
	int		nodeid, synked_slave, synked_master;
/*	int		now = time(NULL);*/

/*	DBbegin();*/

	/* Select all nodes */
	result = DBselect("select nodeid from nodes");
	while (NULL != (row=DBfetch(result))) {
		nodeid = atoi(row[0]);

		if (FAIL == lock_sync_node(nodeid))
			continue;

		if (FAIL == calculate_checksums(nodeid, NULL, 0))
			continue;

		/* Send configuration changes to required nodes */
		main_nodesender(nodeid, &synked_slave, &synked_master);
		if (synked_slave == SUCCEED || synked_master == SUCCEED)
			update_checksums(nodeid, synked_slave, synked_master, NULL, 0, NULL);

		DBexecute("update nodes set sync=0 where nodeid=%d",
			nodeid);
	}
	DBfree_result(result);

/*	DBcommit();*/

/*	zabbix_log(LOG_LEVEL_CRIT, "----- process_nodes [Selected records in %d seconds]", time(NULL)-now);*/
}

/******************************************************************************
 *                                                                            *
 * Function: main_nodewatcher_loop                                            *
 *                                                                            *
 * Purpose: periodically calculates checks sum of config data                 *
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
int main_nodewatcher_loop()
{
	int start, end;
	int	lastrun = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In main_nodeupdater_loop()");
	for(;;)
	{
		start = time(NULL);

		zbx_setproctitle("connecting to the database");
		zabbix_log( LOG_LEVEL_DEBUG, "Starting sync with nodes");

		DBconnect(ZBX_DB_CONNECT_NORMAL);

		/* Send new events to master node */
		main_eventsender();

		if(lastrun + 120 < start)
		{
			process_nodes();

			lastrun = start;
		}
		/* Send new history data to master node */
		main_historysender();

		DBclose();

		end = time(NULL);

		if(end-start<10)
		{
			zbx_setproctitle("sender [sleeping for %d seconds]",
				10-(end-start));
			zabbix_log( LOG_LEVEL_DEBUG, "Sleeping %d seconds",
				10-(end-start));
			sleep(10-(end-start));
		}
	}
}
