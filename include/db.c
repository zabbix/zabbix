/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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


#include <stdlib.h>
#include <stdio.h>

/* for setproctitle() */
#include <sys/types.h>
#include <unistd.h>

#include <string.h>
#include <strings.h>

#include "db.h"
#include "log.h"
#include "common.h"

#ifdef	HAVE_MYSQL
	MYSQL	mysql;
#endif

#ifdef	HAVE_PGSQL
	PGconn	*conn;
#endif

void	DBclose(void)
{
#ifdef	HAVE_MYSQL
	mysql_close(&mysql);
#endif
#ifdef	HAVE_PGSQL
	PQfinish(conn);
#endif
}

/*
 * Connect to the database.
 * If fails, program terminates.
 */ 
void    DBconnect()
{
/*extern	char	*CONFIG_DBHOST;
extern	char	*CONFIG_DBNAME;
extern	char	*CONFIG_DBUSER;
extern	char	*CONFIG_DBPASSWORD;
extern	char	*CONFIG_DBSOCKET;*/

	for(;;)
	{
		/*	zabbix_log(LOG_LEVEL_ERR, "[%s] [%s] [%s]\n",dbname, dbuser, dbpassword ); */
#ifdef	HAVE_MYSQL
	/* For MySQL >3.22.00 */
	/*	if( ! mysql_connect( &mysql, NULL, dbuser, dbpassword ) )*/
		mysql_init(&mysql);
		if( ! mysql_real_connect( &mysql, CONFIG_DBHOST, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBNAME, 3306, CONFIG_DBSOCKET,0 ) )
		{
			fprintf(stderr, "Failed to connect to database: Error: %s\n",mysql_error(&mysql) );
			zabbix_log(LOG_LEVEL_ERR, "Failed to connect to database: Error: %s",mysql_error(&mysql) );
			if( (ER_SERVER_SHUTDOWN != mysql_errno(&mysql)) && (CR_SERVER_GONE_ERROR != mysql_errno(&mysql)))
			{
				exit(FAIL);
			}
		}
		else
		{
			if( mysql_select_db( &mysql, CONFIG_DBNAME ) != 0 )
			{
				fprintf(stderr, "Failed to select database: Error: %s\n",mysql_error(&mysql) );
				zabbix_log(LOG_LEVEL_ERR, "Failed to select database: Error: %s",mysql_error(&mysql) );
				exit( FAIL );
			}
			else
			{
				break;
			}
		}
#endif
#ifdef	HAVE_PGSQL
/*	conn = PQsetdb(pghost, pgport, pgoptions, pgtty, dbName); */
/*	conn = PQsetdb(NULL, NULL, NULL, NULL, dbname);*/
		conn = PQsetdbLogin(CONFIG_DBHOST, NULL, NULL, NULL, CONFIG_DBNAME, CONFIG_DBUSER, CONFIG_DBPASSWORD );

/* check to see that the backend connection was successfully made */
		if (PQstatus(conn) != CONNECTION_OK)
		{
			fprintf(stderr, "Connection to database '%s' failed.\n", CONFIG_DBNAME);
			zabbix_log(LOG_LEVEL_ERR, "Connection to database '%s' failed.\n", CONFIG_DBNAME);
			fprintf(stderr, "%s\n", PQerrorMessage(conn));
			zabbix_log(LOG_LEVEL_ERR, "%s", PQerrorMessage(conn));
			exit(FAIL);
		}
		else
		{
			break;
		}
#endif
		fprintf(stderr, "Will retry to connect to the database after 30 seconds\n");
		zabbix_log(LOG_LEVEL_ERR, "Will retry to connect to the database after 30 seconds");
		sleep(30);
	}
}

/*
 * Execute SQL statement. For non-select statements only.
 * If fails, program terminates.
 */ 
int	DBexecute(char *query)
{
/* Do not include any code here. Will break HAVE_PGSQL section */
#ifdef	HAVE_MYSQL
	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s",query);
	while( mysql_query(&mysql,query) != 0)
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s [%d]", mysql_error(&mysql), mysql_errno(&mysql) );
		if( (ER_SERVER_SHUTDOWN != mysql_errno(&mysql)) && (CR_SERVER_GONE_ERROR != mysql_errno(&mysql)))
		{
			return FAIL;
		}
		sleep(30);
	}
#endif
#ifdef	HAVE_PGSQL
	PGresult	*result;

	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s",query);
	result = PQexec(conn,query);

	if( result==NULL)
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s", "Result is NULL" );
		PQclear(result);
		return FAIL;
	}
	if( PQresultStatus(result) != PGRES_COMMAND_OK)
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s", PQresStatus(PQresultStatus(result)) );
		PQclear(result);
		return FAIL;
	}
	PQclear(result);
#endif
/*	zabbix_set_log_level(LOG_LEVEL_WARNING);*/
	return	SUCCEED;
}

/*
 * Execute SQL statement. For select statements only.
 * If fails, program terminates.
 */ 
DB_RESULT *DBselect(char *query)
{
/* Do not include any code here. Will break HAVE_PGSQL section */
#ifdef	HAVE_MYSQL
	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s",query);
	while(mysql_query(&mysql,query) != 0)
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s [%d]", mysql_error(&mysql), mysql_errno(&mysql) );
		if( (ER_SERVER_SHUTDOWN != mysql_errno(&mysql)) && (CR_SERVER_GONE_ERROR != mysql_errno(&mysql)))
		{
			exit(FAIL);
		}
		sleep(30);
	}
/*	zabbix_set_log_level(LOG_LEVEL_WARNING);*/
	return	mysql_store_result(&mysql);
#endif
#ifdef	HAVE_PGSQL
	PGresult	*result;

	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s",query);
	result = PQexec(conn,query);

	if( result==NULL)
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s", "Result is NULL" );
		exit( FAIL );
	}
	if( PQresultStatus(result) != PGRES_TUPLES_OK)
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s", PQresStatus(PQresultStatus(result)) );
		exit( FAIL );
	}
	return result;
#endif
}

/*
 * Get value for given row and field. Must be called after DBselect.
 */ 
char	*DBget_field(DB_RESULT *result, int rownum, int fieldnum)
{
#ifdef	HAVE_MYSQL
	MYSQL_ROW	row;

	mysql_data_seek(result, rownum);
	row=mysql_fetch_row(result);
	if(row == NULL)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error while mysql_fetch_row():[%s]", mysql_error(&mysql) );
		exit(FAIL);
	}
	return row[fieldnum];
#endif
#ifdef	HAVE_PGSQL
	return PQgetvalue(result, rownum, fieldnum);
#endif
}

/*
 * Return SUCCEED if result conains no records
 */ 
/*int	DBis_empty(DB_RESULT *result)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In DBis_empty");
	if(result == NULL)
	{
		return	SUCCEED;
	}
	if(DBnum_rows(result) == 0)
	{
		return	SUCCEED;
	}
	if(DBget_field(result,0,0) == 0)
	{
		return	SUCCEED;
	}

	return FAIL;
}*/

/*
 * Get number of selected records.
 */ 
int	DBnum_rows(DB_RESULT *result)
{
#ifdef	HAVE_MYSQL
	int rows;

	zabbix_log(LOG_LEVEL_DEBUG, "In DBnum_rows");
	if(result == NULL)
	{
		return	0;
	}
/* Order is important ! */
	rows = mysql_num_rows(result);
	if(rows == 0)
	{
		return	0;
	}
	
/* This is necessary to exclude situations like
 * atoi(DBget_field(result,0,0). This leads to coredump.
 */
/* This is required for empty results for count(*), etc */
	if(DBget_field(result,0,0) == 0)
	{
		return	0;
	}
	zabbix_log(LOG_LEVEL_DEBUG, "Result of DBnum_rows [%d]", rows);
	return rows;
#endif
#ifdef	HAVE_PGSQL
	zabbix_log(LOG_LEVEL_DEBUG, "In DBnum_rows");
	return PQntuples(result);
#endif
}

/*
 * Get function value.
 */ 
int     DBget_function_result(double *result,char *functionid)
{
	DB_RESULT *dbresult;
	int		res = SUCCEED;

        char	sql[MAX_STRING_LEN];

/* 0 is added to distinguish between lastvalue==NULL and empty result */
	snprintf( sql, sizeof(sql)-1, "select 0,lastvalue from functions where functionid=%s", functionid );
	dbresult = DBselect(sql);

	if(DBnum_rows(dbresult) == 0)
	{
		zabbix_log(LOG_LEVEL_WARNING, "No function for functionid:[%s]", functionid );
		res = FAIL;
	}
	else if(DBget_field(dbresult,0,1) == NULL)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "function.lastvalue==NULL [%s]", functionid );
		res = FAIL;
	}
	else
	{
        	*result=atof(DBget_field(dbresult,0,1));
	}
        DBfree_result(dbresult);

        return res;
}

/* SUCCEED if latest alarm with triggerid has this status */
int	DBget_prev_trigger_value(int triggerid)
{
	char	sql[MAX_STRING_LEN];
	int	clock;
	int	value;

	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_prev_trigger_value[%d]", triggerid);

	snprintf(sql,sizeof(sql)-1,"select max(clock) from alarms where triggerid=%d",triggerid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
		DBfree_result(result);
		return TRIGGER_VALUE_UNKNOWN;
	}
	clock=atoi(DBget_field(result,0,0));
	DBfree_result(result);

	snprintf(sql,sizeof(sql),"select max(clock) from alarms where triggerid=%d and clock<%d",triggerid,clock);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
		DBfree_result(result);
		return TRIGGER_VALUE_UNKNOWN;
	}
	clock=atoi(DBget_field(result,0,0));
	DBfree_result(result);

	snprintf(sql,sizeof(sql)-1,"select value from alarms where triggerid=%d and clock=%d",triggerid,clock);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result of [%s] is empty", sql );
		DBfree_result(result);
		return TRIGGER_VALUE_UNKNOWN;
	}
	value=atoi(DBget_field(result,0,0));
	DBfree_result(result);

	return value;
}

/* SUCCEED if latest alarm with triggerid has this status */
/* Rewrite required to simplify logic ?*/
int	latest_alarm(int triggerid, int status)
{
	char	sql[MAX_STRING_LEN];
	int	clock;
	DB_RESULT	*result;
	int ret = FAIL;


	zabbix_log(LOG_LEVEL_DEBUG,"In latest_alarm()");

	snprintf(sql,sizeof(sql)-1,"select max(clock) from alarms where triggerid=%d",triggerid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
        {
                zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
                ret = FAIL;
        }
	else
	{
		clock=atoi(DBget_field(result,0,0));
		DBfree_result(result);

		snprintf(sql,sizeof(sql)-1,"select value from alarms where triggerid=%d and clock=%d",triggerid,clock);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		result = DBselect(sql);
		if(DBnum_rows(result)==1)
		{
			if(atoi(DBget_field(result,0,0)) == status)
			{
				ret = SUCCEED;
			}
		}
	}

	DBfree_result(result);

	return ret;
}

/* SUCCEED if latest service alarm has this status */
/* Rewrite required to simplify logic ?*/
int	latest_service_alarm(int serviceid, int status)
{
	char	sql[MAX_STRING_LEN];
	int	clock;
	DB_RESULT	*result;
	int ret = FAIL;


	zabbix_log(LOG_LEVEL_DEBUG,"In latest_service_alarm()");

	snprintf(sql,sizeof(sql)-1,"select max(clock) from service_alarms where serviceid=%d",serviceid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
        {
                zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
                ret = FAIL;
        }
	else
	{
		clock=atoi(DBget_field(result,0,0));
		DBfree_result(result);

		snprintf(sql,sizeof(sql)-1,"select value from service_alarms where serviceid=%d and clock=%d",serviceid,clock);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		result = DBselect(sql);
		if(DBnum_rows(result)==1)
		{
			if(atoi(DBget_field(result,0,0)) == status)
			{
				ret = SUCCEED;
			}
		}
	}

	DBfree_result(result);

	return ret;
}

int	add_alarm(int triggerid,int status,int clock)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_alarm()");

	if(latest_alarm(triggerid,status) == SUCCEED)
	{
		return SUCCEED;
	}

	snprintf(sql,sizeof(sql)-1,"insert into alarms(triggerid,clock,value) values(%d,%d,%d)", triggerid, clock, status);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	DBexecute(sql);

	zabbix_log(LOG_LEVEL_DEBUG,"End of add_alarm()");
	
	return SUCCEED;
}

int	DBadd_service_alarm(int serviceid,int status,int clock)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_service_alarm()");

	if(latest_service_alarm(serviceid,status) == SUCCEED)
	{
		return SUCCEED;
	}

	snprintf(sql,sizeof(sql)-1,"insert into service_alarms(serviceid,clock,value) values(%d,%d,%d)", serviceid, clock, status);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	DBexecute(sql);

	zabbix_log(LOG_LEVEL_DEBUG,"End of add_service_alarm()");
	
	return SUCCEED;
}

#ifdef	IT_HELPDESK
void	update_problems(int triggerid, int value, int clock)
{
}
#endif

int	DBupdate_trigger_value(int triggerid,int value,int clock)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In update_trigger_value[%d,%d,%d]", triggerid, value, clock);
	add_alarm(triggerid,value,clock);

	snprintf(sql,sizeof(sql)-1,"update triggers set value=%d,lastchange=%d where triggerid=%d",value,clock,triggerid);
	DBexecute(sql);

	if(TRIGGER_VALUE_UNKNOWN == value)
	{
		snprintf(sql,sizeof(sql)-1,"update functions set lastvalue=NULL where triggerid=%d",triggerid);
		DBexecute(sql);
	}

#ifdef	IT_HELPDESK
	update_problems(triggerid,value,clock);
#endif

	zabbix_log(LOG_LEVEL_DEBUG,"End of update_trigger_value()");
	return SUCCEED;
}

void update_triggers_status_to_unknown(int hostid,int clock)
{
	int	i;
	char	sql[MAX_STRING_LEN];
	int	triggerid;

	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In update_triggers_status_to_unknown()");

	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and h.hostid=%d and i.key_<>'%s'",hostid,SERVER_STATUS_KEY);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		triggerid=atoi(DBget_field(result,i,0));
		DBupdate_trigger_value(triggerid,TRIGGER_VALUE_UNKNOWN,clock);
	}

	DBfree_result(result);
	zabbix_log(LOG_LEVEL_DEBUG,"End of update_triggers_status_to_unknown()");

	return; 
}

void  DBdelete_service(int serviceid)
{
	char	sql[MAX_STRING_LEN];

	snprintf(sql,sizeof(sql)-1,"delete from services_links where servicedownid=%d or serviceupid=%d", serviceid, serviceid);
	DBexecute(sql);
	snprintf(sql,sizeof(sql)-1,"delete from services where serviceid=%d", serviceid);
	DBexecute(sql);
}

void  DBdelete_services_by_triggerid(int triggerid)
{
	int	i, serviceid;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_services_by_triggerid(%d)", triggerid);
	snprintf(sql,sizeof(sql)-1,"select serviceid from services where triggerid=%d", triggerid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		serviceid=atoi(DBget_field(result,i,0));
		DBdelete_service(serviceid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG,"End of DBdelete_services_by_triggerid(%d)", triggerid);
}

void  DBdelete_trigger(int triggerid)
{
	char	sql[MAX_STRING_LEN];

	snprintf(sql,sizeof(sql)-1,"delete from trigger_depends where triggerid_down=%d or triggerid_up=%d", triggerid, triggerid);
	DBexecute(sql);
	snprintf(sql,sizeof(sql)-1,"delete from functions where triggerid=%d", triggerid);
	DBexecute(sql);
	snprintf(sql,sizeof(sql)-1,"delete from alarms where triggerid=%d", triggerid);
	DBexecute(sql);
	snprintf(sql,sizeof(sql)-1,"delete from actions where triggerid=%d and scope=%d", triggerid, ACTION_SCOPE_TRIGGER);
	DBexecute(sql);

	DBdelete_services_by_triggerid(triggerid);

	snprintf(sql,sizeof(sql)-1,"update sysmaps_links set triggerid=NULL where triggerid=%d", triggerid);
	DBexecute(sql);
	snprintf(sql,sizeof(sql)-1,"delete from triggers where triggerid=%d", triggerid);
	DBexecute(sql);
}

void  DBdelete_triggers_by_itemid(int itemid)
{
	int	i, triggerid;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_triggers_by_itemid(%d)", itemid);
	snprintf(sql,sizeof(sql)-1,"select triggerid from functions where itemid=%d", itemid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		triggerid=atoi(DBget_field(result,i,0));
		DBdelete_trigger(triggerid);
	}
	DBfree_result(result);

	snprintf(sql,sizeof(sql)-1,"delete from functions where itemid=%d", itemid);
	DBexecute(sql);

	zabbix_log(LOG_LEVEL_DEBUG,"End of DBdelete_triggers_by_itemid(%d)", itemid);
}

void DBdelete_trends_by_itemid(int itemid)
{
	char	sql[MAX_STRING_LEN];

	snprintf(sql,sizeof(sql)-1,"delete from trends where itemid=%d", itemid);
	DBexecute(sql);
}

void DBdelete_history_by_itemid(int itemid)
{
	char	sql[MAX_STRING_LEN];

	snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d", itemid);
	DBexecute(sql);
	snprintf(sql,sizeof(sql)-1,"delete from history_str where itemid=%d", itemid);
	DBexecute(sql);
}

void DBdelete_item(int itemid)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_item(%d)", itemid);

	DBdelete_triggers_by_itemid(itemid);
	DBdelete_history_by_itemid(itemid);
	DBdelete_trends_by_itemid(itemid);

	snprintf(sql,sizeof(sql)-1,"delete from items where itemid=%d", itemid);
	DBexecute(sql);

	zabbix_log(LOG_LEVEL_DEBUG,"End of DBdelete_item(%d)", itemid);
}

void DBdelete_sysmaps_links_by_shostid(int shostid)
{
	char	sql[MAX_STRING_LEN];

	snprintf(sql,sizeof(sql)-1,"delete from sysmaps_links where shostid1=%d or shostid2=%d", shostid, shostid);
	DBexecute(sql);
}

void DBdelete_sysmaps_hosts_by_hostid(int hostid)
{
	int	i, shostid;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_sysmaps_hosts(%d)", hostid);
	snprintf(sql,sizeof(sql)-1,"select shostid from sysmaps_hosts where hostid=%d", hostid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		shostid=atoi(DBget_field(result,i,0));
		DBdelete_sysmaps_links_by_shostid(shostid);
	}
	DBfree_result(result);

	snprintf(sql,sizeof(sql)-1,"delete from sysmaps_hosts where hostid=%d", hostid);
	DBexecute(sql);
}

void DBdelete_host(int hostid)
{
	int	i, itemid;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_host(%d)", hostid);
	snprintf(sql,sizeof(sql)-1,"select itemid from items where hostid=%d", hostid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		itemid=atoi(DBget_field(result,i,0));
		DBdelete_item(itemid);
	}
	DBfree_result(result);

	DBdelete_sysmaps_hosts_by_hostid(hostid);

	snprintf(sql,sizeof(sql)-1,"delete from actions where triggerid=%d and scope=%d", hostid, ACTION_SCOPE_HOST);
	DBexecute(sql);

	snprintf(sql,sizeof(sql)-1,"delete from hosts_groups where hostid=%d", hostid);
	DBexecute(sql);

	snprintf(sql,sizeof(sql)-1,"delete from hosts where hostid=%d", hostid);
	DBexecute(sql);

	zabbix_log(LOG_LEVEL_DEBUG,"End of DBdelete_host(%d)", hostid);
}

void DBupdate_triggers_status_after_restart(void)
{
	int	i;
	char	sql[MAX_STRING_LEN];
	int	triggerid, lastchange;
	int	now;

	DB_RESULT	*result;
	DB_RESULT	*result2;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBupdate_triggers_after_restart()");

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and i.nextcheck+i.delay<%d and i.key_<>'%s' and h.status not in (%d,%d)",now,SERVER_STATUS_KEY, HOST_STATUS_DELETED, HOST_STATUS_TEMPLATE);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		triggerid=atoi(DBget_field(result,i,0));

		snprintf(sql,sizeof(sql)-1,"select min(i.nextcheck+i.delay) from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and i.nextcheck<>0 and t.triggerid=%d and i.type<>%d",triggerid,ITEM_TYPE_TRAPPER);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		result2 = DBselect(sql);
		if( DBnum_rows(result2) == 0 )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "No triggers to update (2)");
			DBfree_result(result2);
			continue;
		}

		lastchange=atoi(DBget_field(result2,0,0));
		DBfree_result(result2);

		DBupdate_trigger_value(triggerid,TRIGGER_VALUE_UNKNOWN,lastchange);
	}

	DBfree_result(result);
	zabbix_log(LOG_LEVEL_DEBUG,"End of DBupdate_triggers_after_restart()");

	return; 
}

void DBupdate_host_status(int hostid,int status,int clock)
{
	DB_RESULT	*result;
	char	sql[MAX_STRING_LEN];
	int	disable_until;

	zabbix_log(LOG_LEVEL_DEBUG,"In update_host_status()");

	snprintf(sql,sizeof(sql)-1,"select status,disable_until from hosts where hostid=%d",hostid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot select host with hostid [%d]",hostid);
		DBfree_result(result);
		return;
	}

	disable_until = atoi(DBget_field(result,0,1));

	if(status == atoi(DBget_field(result,0,0)))
	{
		if((status==HOST_STATUS_UNREACHABLE) 
		&&(clock+DELAY_ON_NETWORK_FAILURE>disable_until) )
		{
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Host already has status [%d]",status);
			DBfree_result(result);
			return;
		}
	}

	DBfree_result(result);

	if(status==HOST_STATUS_MONITORED)
	{
		snprintf(sql,sizeof(sql)-1,"update hosts set status=%d where hostid=%d",HOST_STATUS_MONITORED,hostid);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		DBexecute(sql);
	}
	else if(status==HOST_STATUS_NOT_MONITORED)
	{
		snprintf(sql,sizeof(sql)-1,"update hosts set status=%d where hostid=%d",HOST_STATUS_NOT_MONITORED,hostid);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		DBexecute(sql);
	}
	else if(status==HOST_STATUS_UNREACHABLE)
	{
		if(disable_until+DELAY_ON_NETWORK_FAILURE>clock)
		{
			snprintf(sql,sizeof(sql)-1,"update hosts set status=%d,disable_until=disable_until+%d where hostid=%d",HOST_STATUS_UNREACHABLE,DELAY_ON_NETWORK_FAILURE,hostid);
		}
		else
		{
			snprintf(sql,sizeof(sql)-1,"update hosts set status=%d,disable_until=%d where hostid=%d",HOST_STATUS_UNREACHABLE,clock+DELAY_ON_NETWORK_FAILURE,hostid);
		}
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		DBexecute(sql);
	}
	else
	{
		zabbix_log( LOG_LEVEL_ERR, "Unknown host status [%d]", status);
		return;
	}

	update_triggers_status_to_unknown(hostid,clock);
	zabbix_log(LOG_LEVEL_DEBUG,"End of update_host_status()");

	return;
}

int	DBupdate_item_status_to_notsupported(int itemid)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In DBupdate_item_status_to_notsupported()");

	snprintf(sql,sizeof(sql)-1,"update items set status=%d where itemid=%d",ITEM_STATUS_NOTSUPPORTED,itemid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	DBexecute(sql);

	return SUCCEED;
}

int	DBadd_trend(int itemid, double value, int clock)
{
	DB_RESULT	*result;
	char	sql[MAX_STRING_LEN];
	int	hour;
	int	num;
	double	value_min, value_avg, value_max;	

	zabbix_log(LOG_LEVEL_DEBUG,"In add_trend()");

	hour=clock-clock%3600;

	snprintf(sql,sizeof(sql)-1,"select num,value_min,value_avg,value_max from trends where itemid=%d and clock=%d", itemid, hour);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	if(DBnum_rows(result) == 1)
	{
		num=atoi(DBget_field(result,0,0));
		value_min=atof(DBget_field(result,0,1));
		value_avg=atof(DBget_field(result,0,2));
		value_max=atof(DBget_field(result,0,3));
		if(value<value_min)	value_min=value;
		if(value>value_avg)	value_max=value;
		value_avg=(num*value_avg+value)/(num+1);
		num++;
		snprintf(sql,sizeof(sql)-1,"update trends set num=%d, value_min=%f, value_avg=%f, value_max=%f where itemid=%d and clock=%d", num, value_min, value_avg, value_max, itemid, hour);
	}
	else
	{
		snprintf(sql,sizeof(sql)-1,"insert into trends (clock,itemid,num,value_min,value_avg,value_max) values (%d,%d,%d,%f,%f,%f)", hour, itemid, 1, value, value, value);
	}
	DBexecute(sql);

	DBfree_result(result);

	return SUCCEED;
}

int	DBadd_history(int itemid, double value, int clock)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history()");

	snprintf(sql,sizeof(sql)-1,"insert into history (clock,itemid,value) values (%d,%d,%f)",clock,itemid,value);
	DBexecute(sql);

	DBadd_trend(itemid, value, clock);

	return SUCCEED;
}

int	DBadd_history_str(int itemid, char *value, int clock)
{
	char	sql[MAX_STRING_LEN];
	char	value_esc[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_str()");

	DBescape_string(value,value_esc,MAX_STRING_LEN);
	snprintf(sql,sizeof(sql)-1,"insert into history_str (clock,itemid,value) values (%d,%d,'%s')",clock,itemid,value_esc);
	DBexecute(sql);

	return SUCCEED;
}

int	DBget_items_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_items_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from items");

	result=DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(DBget_field(result,0,0));

	DBfree_result(result);

	return res;
}

int	DBget_triggers_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_triggers_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from triggers");

	result=DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(DBget_field(result,0,0));

	DBfree_result(result);

	return res;
}

int	DBget_items_unsupported_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_items_unsupported_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from items where status=%d", ITEM_STATUS_NOTSUPPORTED);

	result=DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(DBget_field(result,0,0));

	DBfree_result(result);

	return res;
}

int	DBget_history_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_history_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from history");

	result=DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(DBget_field(result,0,0));

	DBfree_result(result);

	return res;
}

int	DBget_trends_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_trends_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from trends");

	result=DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(DBget_field(result,0,0));

	DBfree_result(result);

	return res;
}

int	DBget_queue_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;
	int	now;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_queue_count()");

	now=time(NULL);
	snprintf(sql,sizeof(sql)-1,"select count(*) from items i,hosts h where i.status=%d and i.type not in (%d) and h.status=%d and i.hostid=h.hostid and i.nextcheck<%d and i.key_<>'status'", ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, HOST_STATUS_MONITORED, now);

	result=DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(DBget_field(result,0,0));

	DBfree_result(result);

	return res;
}

int	DBadd_alert(int actionid, int mediatypeid, char *sendto, char *subject, char *message)
{
	int	now;
	char	sql[MAX_STRING_LEN];
	char	sendto_esc[MAX_STRING_LEN];
	char	subject_esc[MAX_STRING_LEN];
	char	message_esc[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_alert()");

	now = time(NULL);
/* Does not work on PostgreSQL */
/*	snprintf(sql,sizeof(sql)-1,"insert into alerts (alertid,actionid,clock,mediatypeid,sendto,subject,message,status,retries) values (NULL,%d,%d,%d,'%s','%s','%s',0,0)",actionid,now,mediatypeid,sendto,subject,message);*/
	DBescape_string(sendto,sendto_esc,MAX_STRING_LEN);
	DBescape_string(subject,subject_esc,MAX_STRING_LEN);
	DBescape_string(message,message_esc,MAX_STRING_LEN);
	snprintf(sql,sizeof(sql)-1,"insert into alerts (actionid,clock,mediatypeid,sendto,subject,message,status,retries) values (%d,%d,%d,'%s','%s','%s',0,0)",actionid,now,mediatypeid,sendto_esc,subject_esc,message_esc);
	DBexecute(sql);

	return SUCCEED;
}

void	DBvacuum(void)
{
#ifdef	HAVE_PGSQL
	char *table_for_housekeeping[]={"services", "services_links", "graphs_items", "graphs", "sysmaps_links",
			"sysmaps_hosts", "sysmaps", "config", "groups", "hosts_groups", "alerts",
			"actions", "alarms", "functions", "history", "history_str", "hosts", "trends",
			"items", "media", "media_type", "triggers", "trigger_depends", "users",
			"sessions", "rights", "service_alarms", "profiles", "screens", "screens_items",
			"stats",
			NULL};

	char	sql[MAX_STRING_LEN];
	char	*table;
	int	i;
#ifdef HAVE_FUNCTION_SETPROCTITLE
	setproctitle("housekeeper [vacuum DB]");
#endif
	i=0;
	while (NULL != (table = table_for_housekeeping[i++]))
	{
		snprintf(sql,sizeof(sql)-1,"vacuum analyze %s", table);
		DBexecute(sql);
	}
#endif

#ifdef	HAVE_MYSQL
	/* Nothing to do */
#endif
}

void    DBescape_string(char *from, char *to, int maxlen)
{
	int	i,ptr;
	char	*f;

	ptr=0;
	f=(char *)strdup(from);
	for(i=0;f[i]!=0;i++)
	{
		if( (f[i]=='\'') || (f[i]=='\\'))
		{
			if(ptr>maxlen-1)	break;
			to[ptr]='\\';
			if(ptr+1>maxlen-1)	break;
			to[ptr+1]=f[i];
			ptr+=2;
		}
		else
		{
			if(ptr>maxlen-1)	break;
			to[ptr]=f[i];
			ptr++;
		}
	}
	free(f);

	to[ptr]=0;
	to[maxlen-1]=0;
}
