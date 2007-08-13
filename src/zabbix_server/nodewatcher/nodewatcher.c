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
static int calculate_checksums()
{

	char	*sql = NULL;
	int	sql_allocated, sql_offset;

	int	i = 0;
	int	j;
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	DB_ROW		row2;
	int		nodeid;

	int	now;

	zabbix_log( LOG_LEVEL_DEBUG, "In calculate_checksums");

	DBexecute("delete from node_cksum where cksumtype=%d",
		NODE_CKSUM_TYPE_NEW);

	/* Select all nodes */
	result =DBselect("select nodeid from nodes");
	while((row=DBfetch(result)))
	{
		sql_allocated=64*1024;
		sql_offset=0;
		sql=zbx_malloc(sql, sql_allocated);

		now  = time(NULL);
		nodeid = atoi(row[0]);

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
				"select 'table                  ','field                ',itemid, '012345678901234' from items where 1=0\n");

		for(i=0;tables[i].table!=0;i++)
		{
/*			zabbix_log( LOG_LEVEL_WARNING, "In calculate_checksums2 [%s]", tables[i].table ); */
			/* Do not sync some of tables */
			if( (tables[i].flags & ZBX_SYNC) ==0)	continue;

			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
#ifdef	HAVE_MYSQL
					"union all select '%s','%s',%s,md5(concat(",
#else
					"union all select '%s','%s',%s,md5(",
#endif
					tables[i].table,
					tables[i].recid,
					tables[i].recid);

			j=0;
			while(tables[i].fields[j].name != 0)
			{
				if( (tables[i].fields[j].flags & ZBX_SYNC) ==0)
				{
					j++;
					continue;
				}
#ifdef	HAVE_MYSQL
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "coalesce(%s,'1234567890'),",
					tables[i].fields[j].name);
#else
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "coalesce(%s,'1234567890')||",
					tables[i].fields[j].name);
#endif
				j++;
			}
#ifdef	HAVE_MYSQL
			if(j>0)			sql_offset--; /* Remove last */
#else
			if(j>0)			sql_offset-=2; /* Remove last */
#endif

			/* select table,recid,md5(fields) from table union all ... */
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
#ifdef	HAVE_MYSQL
					")) from %s where %s>=" ZBX_FS_UI64 " and %s<=" ZBX_FS_UI64 "\n",
#else
					") from %s where %s>=" ZBX_FS_UI64 " and %s<=" ZBX_FS_UI64 "\n",
#endif
					tables[i].table,
					tables[i].recid,
					(zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid,
					tables[i].recid,
					(zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid+__UINT64_C(99999999999999));

		}
/*		zabbix_log( LOG_LEVEL_WARNING, "SQL DUMP [%s]", sql); */

		result2 =DBselect("%s",sql);

/*		zabbix_log( LOG_LEVEL_WARNING, "Selected records in %d seconds", time(NULL)-now);*/
		now = time(NULL);
		i=0;
		while((row2=DBfetch(result2)))
		{
			DBexecute("insert into node_cksum (cksumid,nodeid,tablename,fieldname,recordid,cksumtype,cksum) "\
				"values (" ZBX_FS_UI64 ",%d,'%s','%s',%s,%d,'%s')",
/*				DBget_nextid("node_cksum","cksumid"),*/
				DBget_maxid("node_cksum","cksumid"),
				nodeid,
				row2[0],
				row2[1],
				row2[2],
				NODE_CKSUM_TYPE_NEW,
				row2[3]);
			i++;
		}
		DBfree_result(result2);
		zbx_free(sql);
	}
	DBfree_result(result);

	return SUCCEED;
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
static int update_checksums()
{
	DBexecute("delete from node_cksum where cksumtype=%d",
		NODE_CKSUM_TYPE_OLD);
	DBexecute("update node_cksum set cksumtype=%d where cksumtype=%d",
		NODE_CKSUM_TYPE_OLD,
		NODE_CKSUM_TYPE_NEW);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: compare_checksums                                                *
 *                                                                            *
 * Purpose: compare new checksums with old ones. Write difference to          *
 *          table 'node_config'                                               *
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
static int compare_checksums()
{
	DB_RESULT	result;
	DB_ROW		row;

	/* Find updated records */
	result = DBselect("select curr.nodeid,curr.tablename,curr.recordid from node_cksum prev, node_cksum curr where curr.tablename=prev.tablename and curr.recordid=prev.recordid and curr.fieldname=prev.fieldname and curr.nodeid=prev.nodeid and curr.cksum<>prev.cksum and curr.cksumtype=%d and prev.cksumtype=%d",
		NODE_CKSUM_TYPE_NEW,
		NODE_CKSUM_TYPE_OLD);
	while((row=DBfetch(result)))
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Adding record to node_configlog NODE_CONFIGLOG_OP_UPDATE");
		DBexecute("insert into node_configlog (conflogid,nodeid,tablename,recordid,operation)" \
				"values (" ZBX_FS_UI64 ",%s,'%s',%s,%d)",
/*				DBget_nextid("node_configlog","conflogid"),*/
				DBget_maxid("node_configlog","conflogid"),
				row[0],
				row[1],
				row[2],
				NODE_CONFIGLOG_OP_UPDATE);
	}
	DBfree_result(result);

	/* Find new records */
	result = DBselect("select curr.nodeid,curr.tablename,curr.recordid from node_cksum curr" \
			  " left join node_cksum prev" \
			  " on curr.tablename=prev.tablename and curr.recordid=prev.recordid and curr.fieldname=prev.fieldname and curr.nodeid=prev.nodeid and curr.cksumtype<>prev.cksumtype" \
			  " where prev.cksumid is null and curr.cksumtype=%d",
			NODE_CKSUM_TYPE_NEW);

	while((row=DBfetch(result)))
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Adding record to node_configlog NODE_CONFIGLOG_OP_ADD");
		DBexecute("insert into node_configlog (conflogid,nodeid,tablename,recordid,operation)" \
			"values (" ZBX_FS_UI64 ",%s,'%s',%s,%d)",
/*			DBget_nextid("node_configlog","conflogid"),*/
			DBget_maxid("node_configlog","conflogid"),
			row[0],
			row[1],
			row[2],
			NODE_CONFIGLOG_OP_ADD);
	}
	DBfree_result(result);

	/* Find deleted records */
	result = DBselect("select curr.nodeid,curr.tablename,curr.recordid from node_cksum curr" \
			  " left join node_cksum prev" \
			  " on curr.tablename=prev.tablename and curr.recordid=prev.recordid and curr.fieldname=prev.fieldname and curr.nodeid=prev.nodeid and curr.cksumtype<>prev.cksumtype" \
			  " where prev.cksumid is null and curr.cksumtype=%d",
			NODE_CKSUM_TYPE_OLD);

	while((row=DBfetch(result)))
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Adding record to node_configlog NODE_CONFIGLOG_OP_DELETE");
		DBexecute("insert into node_configlog (conflogid,nodeid,tablename,recordid,operation)" \
				"values (" ZBX_FS_UI64 ",%s,'%s',%s,%d)",
/*				DBget_nextid("node_configlog","conflogid"),*/
				DBget_maxid("node_configlog","conflogid"),
				row[0],
				row[1],
				row[2],
				NODE_CONFIGLOG_OP_DELETE);
	}
	DBfree_result(result);

	return SUCCEED;
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

		if(lastrun + 120 < start)
		{

			DBbegin();
			calculate_checksums();
			compare_checksums();
			update_checksums();

			/* Send configuration changes to required nodes */
			main_nodesender();
			DBcommit();

			lastrun = start;
		}

		/* Send new events to master node */
		main_eventsender();

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
