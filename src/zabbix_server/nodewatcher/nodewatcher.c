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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include <sys/wait.h>

#include <string.h>

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

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

	char	*sql;
	int	sql_allocated=64*1024, sql_offset=0;
//	char	tmp[MAX_STRING_LEN];
//	char	fields[MAX_STRING_LEN];

	int	i = 0;
	int	j;
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	DB_ROW		row2;
	int		nodeid;

	int	now;

	zabbix_log( LOG_LEVEL_DEBUG, "In calculate_checksums");

	sql=malloc(sql_allocated);

	DBbegin();

	DBexecute("delete from node_cksum where cksumtype=%d", NODE_CKSUM_TYPE_NEW);
	// insert into node_cksum (select NULL,0,'items','itemid',itemid,0,md5(concat(key_)) as md5 from items);

	/* Select all nodes */
	result =DBselect("select nodeid from nodes");
	while((row=DBfetch(result)))
	{
		now  = time(NULL);
		nodeid = atoi(row[0]);

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128,
				"select 'table                  ','field                ',itemid, '012345678901234' from items where 1=0\n");

//		zbx_snprintf(sql,sizeof(sql),"select 'table                  ','field                ',itemid, '012345678901234' from items where 1=0\n");

		for(i=0;tables[i].table!=0;i++)
		{
//			zabbix_log( LOG_LEVEL_WARNING, "In calculate_checksums2 [%s]", tables[i].table );
			/* Do not sync some of tables */
			if( (tables[i].flags & ZBX_SYNC) ==0)	continue;

			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
#ifdef	HAVE_MYSQL
					"union all select '%s','%s',%s,md5(concat(",
#else
					"union all select '%s','%s',%s,md5(",
#endif
					tables[i].table, tables[i].recid, tables[i].recid);

			j=0;
//			fields[0]=0;
			while(tables[i].fields[j].name != 0)
			{
//				zabbix_log( LOG_LEVEL_WARNING, "In calculate_checksums2 [%s,%s]", tables[i].table,tables[i].fields[j].name );
				if( (tables[i].fields[j].flags & ZBX_SYNC) ==0)
				{
//					zabbix_log( LOG_LEVEL_WARNING, "Skip %s.%s", tables[i].table,tables[i].fields[j].name );
					j++;
					continue;
				}
#ifdef	HAVE_MYSQL
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "coalesce(%s,'1234567890'),", tables[i].fields[j].name);
#else
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, "coalesce(%s,'1234567890')||", tables[i].fields[j].name);
#endif
//				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096, "quote(%s),", tables[i].fields[j].name);
//				zbx_strlcat(fields,"quote(",sizeof(fields));
//				zbx_strlcat(fields,tables[i].fields[j].name,sizeof(fields));
//				zbx_strlcat(fields,"),",sizeof(fields));
				j++;
			}
#ifdef	HAVE_MYSQL
			if(j>0)			sql_offset--; // Remove last ,
#else
			if(j>0)			sql_offset-=2; // Remove last ||
#endif

//			if(fields[0]!=0)	fields[strlen(fields)-1] = 0;

			// select table,recid,md5(fields) from table union all ...
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4096,
#ifdef	HAVE_MYSQL
					")) from %s where %s>=" ZBX_FS_UI64 " and %s<=" ZBX_FS_UI64 "\n",
#else
					") from %s where %s>=" ZBX_FS_UI64 " and %s<=" ZBX_FS_UI64 "\n",
#endif
					tables[i].table,
					tables[i].recid, (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid,
					tables[i].recid, (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid+__UINT64_C(99999999999999));

//			zbx_snprintf(tmp,sizeof(tmp),"union all select '%s','%s',%s,md5(concat(%s)) from %s where %s>=" ZBX_FS_UI64 " and %s<=" ZBX_FS_UI64 "\n",
//					tables[i].table, tables[i].recid, tables[i].recid, fields, tables[i].table,
//					tables[i].recid, (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid,
//					tables[i].recid, (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid+__UINT64_C(99999999999999));
//		zabbix_log( LOG_LEVEL_WARNING, "TMP [%s]", tmp);
//			zbx_strlcat(sql,tmp,sizeof(sql));
		}
//		zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]", sql);

		result2 =DBselect(sql);

//		zabbix_log( LOG_LEVEL_WARNING, "Selected records in %d seconds", time(NULL)-now);
		now = time(NULL);
		i=0;
		while((row2=DBfetch(result2)))
		{
//			zabbix_log( LOG_LEVEL_WARNING, "Cksum [%s]", row2[3]);
			DBexecute("insert into node_cksum (cksumid,nodeid,tablename,fieldname,recordid,cksumtype,cksum) "\
				"values (" ZBX_FS_UI64 ",%d,'%s','%s',%s,%d,'%s')",
				DBget_nextid("node_cksum","cksumid"),
				nodeid,row2[0],row2[1],row2[2],NODE_CKSUM_TYPE_NEW,row2[3]);
			i++;
		}
		DBfree_result(result2);
//		zabbix_log( LOG_LEVEL_WARNING, "Added %d records in %d seconds", i, time(NULL)-now);
	}
	DBfree_result(result);

	DBcommit();

	free(sql);

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
	DBbegin();

	DBexecute("delete from node_cksum where cksumtype=%d", NODE_CKSUM_TYPE_OLD);
	DBexecute("update node_cksum set cksumtype=%d where cksumtype=%d", NODE_CKSUM_TYPE_OLD, NODE_CKSUM_TYPE_NEW);

	DBcommit();

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

	/* Begin work */
	DBbegin();

	/* Find updated records */
	result = DBselect("select curr.nodeid,curr.tablename,curr.recordid from node_cksum prev, node_cksum curr where curr.tablename=prev.tablename and curr.recordid=prev.recordid and curr.fieldname=prev.fieldname and curr.nodeid=prev.nodeid and curr.cksum<>prev.cksum and curr.cksumtype=%d and prev.cksumtype=%d", NODE_CKSUM_TYPE_NEW, NODE_CKSUM_TYPE_OLD);
	while((row=DBfetch(result)))
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Adding record to node_configlog");
		DBexecute("insert into node_configlog (conflogid,nodeid,tablename,recordid,operation)" \
				"values (" ZBX_FS_UI64 ",%s,'%s',%s,%d)",
				DBget_nextid("node_configlog","conflogid"),
				row[0],row[1],row[2],NODE_CONFIGLOG_OP_UPDATE);
	}
	DBfree_result(result);

	/* Find new records */
	result = DBselect("select curr.nodeid,curr.tablename,curr.recordid from node_cksum curr" \
			  " left join node_cksum prev" \
			  " on curr.tablename=prev.tablename and curr.recordid=prev.recordid and curr.fieldname=prev.fieldname and curr.nodeid=prev.nodeid and curr.cksumtype<>prev.cksumtype" \
			  " where prev.cksumid is null and curr.cksumtype=%d", NODE_CKSUM_TYPE_NEW);

	while((row=DBfetch(result)))
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Adding record to node_configlog");
		DBexecute("insert into node_configlog (conflogid,nodeid,tablename,recordid,operation)" \
			"values (" ZBX_FS_UI64 ",%s,'%s',%s,%d)",
			DBget_nextid("node_configlog","conflogid"),
			row[0],row[1],row[2],NODE_CONFIGLOG_OP_ADD);
	}
	DBfree_result(result);

	/* Find deleted records */
	result = DBselect("select curr.nodeid,curr.tablename,curr.recordid from node_cksum curr" \
			  " left join node_cksum prev" \
			  " on curr.tablename=prev.tablename and curr.recordid=prev.recordid and curr.fieldname=prev.fieldname and curr.nodeid=prev.nodeid and curr.cksumtype<>prev.cksumtype" \
			  " where prev.cksumid is null and curr.cksumtype=%d", NODE_CKSUM_TYPE_OLD);

	while((row=DBfetch(result)))
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Adding record to node_configlog");
		DBexecute("insert into node_configlog (conflogid,nodeid,tablename,recordid,operation)" \
				"values (" ZBX_FS_UI64 ",%s,'%s',%s,%d)",
				DBget_nextid("node_configlog","conflogid"),
				row[0],row[1],row[2],NODE_CONFIGLOG_OP_DELETE);
	}
	DBfree_result(result);

	/* Commit */
	DBcommit();

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

//	zabbix_log( LOG_LEVEL_WARNING, "In main_nodeupdater_loop()");
	for(;;)
	{
		start = time(NULL);

		zbx_setproctitle("connecting to the database");
		zabbix_log( LOG_LEVEL_DEBUG, "Starting sync with nodes");

		DBconnect();

		if(lastrun + 120 < start)
		{
			calculate_checksums();
			compare_checksums();
			update_checksums();

			/* Send configuration changes to required nodes */
			main_nodesender();

			lastrun = start;
		}

		/* Send new events to master node */
		main_eventsender();

		/* Send new history data to master node */
		main_historysender();

		DBclose();

		end = time(NULL);

		if(end-start<5)
		{
			zbx_setproctitle("sender [sleeping for %d seconds]", 5-(end-start));
			zabbix_log( LOG_LEVEL_DEBUG, "Sleeping %d seconds", 5-(end-start));
			sleep(10-(end-start));
		}
	}
}
