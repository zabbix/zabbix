/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003 Alexei Vladishev
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
 * Connect to database.
 * If fails, program terminates.
 */ 
void    DBconnect(char *dbhost, char *dbname, char *dbuser, char *dbpassword, char *dbsocket)
{
/*	zabbix_log(LOG_LEVEL_ERR, "[%s] [%s] [%s]\n",dbname, dbuser, dbpassword ); */
#ifdef	HAVE_MYSQL
/* For MySQL >3.22.00 */
/*	if( ! mysql_connect( &mysql, NULL, dbuser, dbpassword ) )*/
	mysql_init(&mysql);
	if( ! mysql_real_connect( &mysql, dbhost, dbuser, dbpassword, dbname, 3306, dbsocket,0 ) )
	{
		fprintf(stderr, "Failed to connect to database: Error: %s\n",mysql_error(&mysql) );
		zabbix_log(LOG_LEVEL_ERR, "Failed to connect to database: Error: %s",mysql_error(&mysql) );
		exit( FAIL );
	}
	if( mysql_select_db( &mysql, dbname ) != 0 )
	{
		fprintf(stderr, "Failed to select database: Error: %s\n",mysql_error(&mysql) );
		zabbix_log(LOG_LEVEL_ERR, "Failed to select database: Error: %s",mysql_error(&mysql) );
		exit( FAIL );
	}
#endif
#ifdef	HAVE_PGSQL
/*	conn = PQsetdb(pghost, pgport, pgoptions, pgtty, dbName); */
/*	conn = PQsetdb(NULL, NULL, NULL, NULL, dbname);*/
	conn = PQsetdbLogin(dbhost, NULL, NULL, NULL, dbname, dbuser, dbpassword );

/* check to see that the backend connection was successfully made */
	if (PQstatus(conn) == CONNECTION_BAD)
	{
		fprintf(stderr, "Connection to database '%s' failed.\n", dbname);
		zabbix_log(LOG_LEVEL_ERR, "Connection to database '%s' failed.\n", dbname);
		fprintf(stderr, "%s\n", PQerrorMessage(conn));
		zabbix_log(LOG_LEVEL_ERR, "%s", PQerrorMessage(conn));
		exit(FAIL);
	}
#endif
}

/*
 * Execute SQL statement. For non-select statements only.
 * If fails, program terminates.
 */ 
int	DBexecute(char *query)
{

#ifdef	HAVE_MYSQL
	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s\n",query); 
/*	zabbix_log( LOG_LEVEL_WARNING, "Executing query:%s\n",query)*/;

	if( mysql_query(&mysql,query) != 0 )
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s", mysql_error(&mysql) );
		return FAIL;
	}
#endif
#ifdef	HAVE_PGSQL
	PGresult	*result;

	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s\n",query);

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
	return	SUCCEED;
}

/*
 * Execute SQL statement. For select statements only.
 * If fails, program terminates.
 */ 
DB_RESULT *DBselect(char *query)
{
#ifdef	HAVE_MYSQL
	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s\n",query);
/*	zabbix_log( LOG_LEVEL_WARNING, "Executing query:%s\n",query);*/

	if( mysql_query(&mysql,query) != 0 )
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s", mysql_error(&mysql) );
		exit( FAIL );
	}
	return	mysql_store_result(&mysql);
#endif
#ifdef	HAVE_PGSQL
	PGresult	*result;

	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s\n",query);
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

        char	sql[MAX_STRING_LEN+1];

/* 0 is added to distinguish between lastvalue==NULL and empty result */
	sprintf( sql, "select 0,lastvalue from functions where functionid=%s", functionid );
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
	char	sql[MAX_STRING_LEN+1];
	int	clock;
	int	value;

	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_prev_trigger_value()");

	sprintf(sql,"select max(clock) from alarms where triggerid=%d",triggerid);
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

	sprintf(sql,"select max(clock) from alarms where triggerid=%d and clock<%d",triggerid,clock);
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

	sprintf(sql,"select value from alarms where triggerid=%d and clock=%d",triggerid,clock);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	if(DBnum_rows(result) == SUCCEED)
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
	char	sql[MAX_STRING_LEN+1];
	int	clock;
	DB_RESULT	*result;
	int ret = FAIL;


	zabbix_log(LOG_LEVEL_DEBUG,"In latest_alarm()");

	sprintf(sql,"select max(clock) from alarms where triggerid=%d",triggerid);
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

		sprintf(sql,"select value from alarms where triggerid=%d and clock=%d",triggerid,clock);
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
	char	sql[MAX_STRING_LEN+1];
	int	clock;
	DB_RESULT	*result;
	int ret = FAIL;


	zabbix_log(LOG_LEVEL_DEBUG,"In latest_service_alarm()");

	sprintf(sql,"select max(clock) from service_alarms where serviceid=%d",serviceid);
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

		sprintf(sql,"select value from service_alarms where serviceid=%d and clock=%d",serviceid,clock);
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
	char	sql[MAX_STRING_LEN+1];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_alarm()");

	if(latest_alarm(triggerid,status) == SUCCEED)
	{
		return SUCCEED;
	}

	sprintf(sql,"insert into alarms(triggerid,clock,value) values(%d,%d,%d)", triggerid, clock, status);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	DBexecute(sql);

	zabbix_log(LOG_LEVEL_DEBUG,"End of add_alarm()");
	
	return SUCCEED;
}

int	DBadd_service_alarm(int serviceid,int status,int clock)
{
	char	sql[MAX_STRING_LEN+1];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_service_alarm()");

	if(latest_service_alarm(serviceid,status) == SUCCEED)
	{
		return SUCCEED;
	}

	sprintf(sql,"insert into service_alarms(serviceid,clock,value) values(%d,%d,%d)", serviceid, clock, status);
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
	char	sql[MAX_STRING_LEN+1];

	zabbix_log(LOG_LEVEL_DEBUG,"In update_trigger_value()");
	add_alarm(triggerid,value,clock);

	sprintf(sql,"update triggers set value=%d,lastchange=%d where triggerid=%d",value,clock,triggerid);
	DBexecute(sql);

	if(TRIGGER_VALUE_UNKNOWN == value)
	{
		sprintf(sql,"update functions set lastvalue=NULL where triggerid=%d",triggerid);
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
	char	sql[MAX_STRING_LEN+1];
	int	triggerid;

	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In update_triggers_status_to_unknown()");

	sprintf(sql,"select distinct t.triggerid from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and h.hostid=%d and i.key_<>'%s'",hostid,SERVER_STATUS_KEY);
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

void  DBdelete_trigger(int triggerid)
{
	sprintf(sql,"delete from trigger_depends where triggerid_down=%d or triggerid_up=%d", triggerid, triggerid);
	DBexecute(sql);
	sprintf(sql,"delete from functions where triggerid=%d", triggerid);
	DBexecute(sql);
	sprintf(sql,"delete from alarms where triggerid=%d", triggerid);
	DBexecute(sql);
	sprintf(sql,"delete from actions where triggerid=%d", triggerid);
	DBexecute(sql);

	DBdelete_services_by_triggerid(triggerid);

	sprintf(sql,"update sysmaps_links set triggerid=NULL where triggerid=%d", triggerid);
	DBexecute(sql);
	sprintf(sql,"delete from triggers where triggerid=%d", triggerid);
	DBexecute(sql);
}

void  DBdelete_triggers_by_itemid(int itemid)
{
	int	i, triggerid;
	char	sql[MAX_STRING_LEN+1];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_triggers_by_itemid(%d)", itemid);
	sprintf(sql,"select triggerid from functions where itemid=%d", itemid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		triggerid=atoi(DBget_field(result,i,0));
		DBdelete_trigger(triggerid);
	}
	DBfree_result(result);

	sprintf(sql,"delete from functions where itemid=%d", itemid);
	DBexecute(sql);

	zabbix_log(LOG_LEVEL_DEBUG,"End of DBdelete_triggers_by_itemid(%d)", itemid);
}
}

void DBdelete_history_by_itemid(int itemid)
{
	sprintf(sql,"delete from history where itemid=%d", itemid);
	DBexecute(sql);
	sprintf(sql,"delete from history_str where itemid=%d", itemid);
	DBexecute(sql);
}

void DBdelete_item(int itemid)
{
	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_item(%d)", itemid);

	DBdelete_triggers_by_itemid(itemid);
	DBdelete_history_by_itemid(itemid);

	sprintf(sql,"delete from items where itemid=%d", itemid);
	DBexecute(sql);

	zabbix_log(LOG_LEVEL_DEBUG,"End of DBdelete_item(%d)", itemid);
}

void DBdelete_host(int hostid)
{
	int	i, itemid;
	char	sql[MAX_STRING_LEN+1];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_host(%d)", hostid);
	sprintf(sql,"select itemid from items where hostid=%d", hostid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		itemid=atoi(DBget_field(result,i,0));
		DBdelete_item(itemid);
	}
	DBfree_result(result);

	sprintf(sql,"delete from hosts where hostid=%d", hostid);
	DBexecute(sql);

	zabbix_log(LOG_LEVEL_DEBUG,"End of DBdelete_host(%d)", hostid);
}

void DBupdate_triggers_status_after_restart(void)
{
	int	i;
	char	sql[MAX_STRING_LEN+1];
	int	triggerid, lastchange;
	int	now;

	DB_RESULT	*result;
	DB_RESULT	*result2;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBupdate_triggers_after_restart()");

	now=time(NULL);

	sprintf(sql,"select distinct t.triggerid from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and i.nextcheck+i.delay<%d and i.key_<>'%s' and h.status not in (%d,%d)",now,SERVER_STATUS_KEY, HOST_STATUS_DELETED, HOST_STATUS_TEMPLATE);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		triggerid=atoi(DBget_field(result,i,0));

		sprintf(sql,"select min(i.nextcheck+i.delay) from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and i.nextcheck<>0 and t.triggerid=%d and i.type<>%d",triggerid,ITEM_TYPE_TRAPPER);
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
	char	sql[MAX_STRING_LEN+1];
	int	disable_until;

	zabbix_log(LOG_LEVEL_DEBUG,"In update_host_status()");

	sprintf(sql,"select status,disable_until from hosts where hostid=%d",hostid);
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
		sprintf(sql,"update hosts set status=%d where hostid=%d",HOST_STATUS_MONITORED,hostid);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		DBexecute(sql);
	}
	else if(status==HOST_STATUS_NOT_MONITORED)
	{
		sprintf(sql,"update hosts set status=%d where hostid=%d",HOST_STATUS_NOT_MONITORED,hostid);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		DBexecute(sql);
	}
	else if(status==HOST_STATUS_UNREACHABLE)
	{
		if(disable_until+DELAY_ON_NETWORK_FAILURE>clock)
		{
			sprintf(sql,"update hosts set status=%d,disable_until=disable_until+%d where hostid=%d",HOST_STATUS_UNREACHABLE,DELAY_ON_NETWORK_FAILURE,hostid);
		}
		else
		{
			sprintf(sql,"update hosts set status=%d,disable_until=%d where hostid=%d",HOST_STATUS_UNREACHABLE,clock+DELAY_ON_NETWORK_FAILURE,hostid);
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
	char	sql[MAX_STRING_LEN+1];

	zabbix_log(LOG_LEVEL_DEBUG,"In DBupdate_item_status_to_notsupported()");

	sprintf(sql,"update items set status=%d where itemid=%d",ITEM_STATUS_NOTSUPPORTED,itemid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	DBexecute(sql);

	return SUCCEED;
}

int	DBadd_history(int itemid, double value)
{
	int	now;
	char	sql[MAX_STRING_LEN+1];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history()");

	now=time(NULL);
	sprintf(sql,"insert into history (clock,itemid,value) values (%d,%d,%g)",now,itemid,value);
	DBexecute(sql);

	return SUCCEED;
}

int	DBadd_history_str(int itemid, char *value)
{
	int	now;
	char	sql[MAX_STRING_LEN+1];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_str()");

	now=time(NULL);
	sprintf(sql,"insert into history_str (clock,itemid,value) values (%d,%d,'%s')",now,itemid,value);
	DBexecute(sql);

	return SUCCEED;
}

int	DBget_items_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN+1];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_items_count()");

	sprintf(sql,"select count(*) from items");

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
	char	sql[MAX_STRING_LEN+1];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_triggers_count()");

	sprintf(sql,"select count(*) from triggers");

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
	char	sql[MAX_STRING_LEN+1];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_items_unsupported_count()");

	sprintf(sql, "select count(*) from items where status=%d", ITEM_STATUS_NOTSUPPORTED);

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
	char	sql[MAX_STRING_LEN+1];
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_history_count()");

	sprintf(sql,"select count(*) from history");

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
	char	sql[MAX_STRING_LEN+1];
	DB_RESULT	*result;
	int	now;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_queue_count()");

	now=time(NULL);
	sprintf(sql,"select count(*) from items i,hosts h where i.status=%d and i.type not in (%d) and h.status=%d and i.hostid=h.hostid and i.nextcheck<%d and i.key_<>'status'", ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, HOST_STATUS_MONITORED, now);

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
	char	sql[MAX_STRING_LEN+1];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_alert()");

	now = time(NULL);
	sprintf(sql,"insert into alerts (alertid,actionid,clock,mediatypeid,sendto,subject,message,status,retries) values (NULL,%d,%d,%d,'%s','%s','%s',0,0)",actionid,now,mediatypeid,sendto,subject,message);
	DBexecute(sql);

	return SUCCEED;
}

void	DBvacuum(void)
{

#ifdef	HAVE_PGSQL
	char *table_for_housekeeping[]={"services", "services_links", "graphs_items", "graphs", "sysmaps_links",
			"sysmaps_hosts", "sysmaps", "config", "groups", "hosts_groups", "alerts",
			"actions", "alarms", "functions", "history", "history_str", "hosts",
			"items", "media", "media_type", "triggers", "trigger_depends", "users",
			"sessions", "rights", "service_alarms", "profiles", "screens", "screens_items",
			"stats",
			NULL};

	char	sql[MAX_STRING_LEN+1];
	char	*table;
	int	i;
#ifdef HAVE_FUNCTION_SETPROCTITLE
	setproctitle("housekeeper [vacuum DB]");
#endif
	i=0;
	while (NULL != (table = table_for_housekeeping[i++]))
	{
		sprintf(sql,"vacuum analyze %s", table);
		DBexecute(sql);
	}
#endif

#ifdef	HAVE_MYSQL
	/* Nothing to do */
#endif
}
