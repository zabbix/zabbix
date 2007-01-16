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


#include <stdlib.h>
#include <stdio.h>

/* for setproctitle() */
#include <sys/types.h>
#include <unistd.h>

#include <string.h>
#include <strings.h>

#include "db.h"
#include "log.h"
#include "zlog.h"
#include "common.h"

#ifdef	HAVE_MYSQL
	MYSQL	mysql;
#endif

#ifdef	HAVE_PGSQL
	PGconn	*conn;
#endif

#ifdef	HAVE_ORACLE
	sqlo_db_handle_t oracle;
#endif

extern void    apply_actions(DB_TRIGGER *trigger,int alarmid,int trigger_value);
extern void    update_services(int triggerid, int status);

void	DBclose(void)
{
#ifdef	HAVE_MYSQL
	mysql_close(&mysql);
#endif
#ifdef	HAVE_PGSQL
	PQfinish(conn);
#endif
#ifdef	HAVE_ORACLE
	sqlo_finish(oracle);
#endif
}

/*
 * Connect to the database.
 * If fails, program terminates.
 */ 
void    DBconnect(void)
{
	/*	zabbix_log(LOG_LEVEL_ERR, "[%s] [%s] [%s]\n",dbname, dbuser, dbpassword ); */
#ifdef	HAVE_MYSQL
	/* For MySQL >3.22.00 */
	/*	if( ! mysql_connect( &mysql, NULL, dbuser, dbpassword ) )*/

	mysql_init(&mysql);

    if( ! mysql_real_connect( &mysql, CONFIG_DBHOST, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBNAME, CONFIG_DBPORT, CONFIG_DBSOCKET,0 ) )
	{
		zabbix_log(LOG_LEVEL_ERR, "Failed to connect to database: Error: %s",mysql_error(&mysql) );
		exit(FAIL);
	}
	else
	{
		if( mysql_select_db( &mysql, CONFIG_DBNAME ) != 0 )
		{
			zabbix_log(LOG_LEVEL_ERR, "Failed to select database: Error: %s",mysql_error(&mysql) );
			exit(FAIL);
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
		zabbix_log(LOG_LEVEL_ERR, "Connection to database '%s' failed.\n", CONFIG_DBNAME);
		exit(FAIL);
	}
#endif
#ifdef	HAVE_ORACLE
	char    connect[MAX_STRING_LEN];

	if (SQLO_SUCCESS != sqlo_init(SQLO_OFF, 1, 100))
	{
		zabbix_log(LOG_LEVEL_ERR, "Failed to init libsqlora8");
		exit(FAIL);
	}
			        /* login */
	snprintf(connect,MAX_STRING_LEN-1,"%s/%s@%s", CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBNAME);
	if (SQLO_SUCCESS != sqlo_connect(&oracle, connect))
	{
		printf("Cannot login with %s\n", connect);
		zabbix_log(LOG_LEVEL_ERR, "Cannot login with %s\n", connect);
		exit(FAIL);
	}
	sqlo_autocommit_on(oracle);
#endif
}

/*
 * Execute SQL statement. For non-select statements only.
 * If fails, program terminates.
 */ 
int	DBexecute(char *query)
{
/* Do not include any code here. Will break HAVE_PGSQL section */
#ifdef	HAVE_MYSQL
/*	if(strstr(query, "17828") != NULL)*/
	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s",query);

	if(mysql_query(&mysql,query) != 0)
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s [%d]", mysql_error(&mysql), mysql_errno(&mysql) );
		return FAIL;
	}
	return (long)mysql_affected_rows(&mysql);
#endif
#ifdef	HAVE_PGSQL
	PGresult        *result;

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
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s:%s",
			PQresStatus(PQresultStatus(result)),
			PQresultErrorMessage(result));
			PQclear(result);
		return FAIL;
	}
	PQclear(result);
	return SUCCEED;
#endif
#ifdef	HAVE_ORACLE
	int ret;
	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s",query);
	if ( (ret = sqlo_exec(oracle, query))<0 )
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s", sqlo_geterror(oracle) );
		fprintf(stderr, "Query::%s\n",query);
		fprintf(stderr, "Query failed:%s\n", sqlo_geterror(oracle) );
		ret = FAIL;
	}
	return ret;
#endif
}

int	DBis_null(char *field)
{
	int ret = FAIL;

	if(field == NULL)	ret = SUCCEED;
#ifdef	HAVE_ORACLE
	else if(field[0] == 0)	ret = SUCCEED;
#endif
	return ret;
}

#ifdef  HAVE_PGSQL
void	PG_DBfree_result(DB_RESULT result)
{
	if(!result) return;

	/* free old data */
	if(result->values)
	{
		result->fld_num = 0;
		free(result->values);
		result->values = NULL;
	}

	PQclear(result->pg_result);
	free(result);
}
#endif

DB_ROW	DBfetch(DB_RESULT result)
{
#ifdef	HAVE_MYSQL
	return mysql_fetch_row(result);
#endif
#ifdef	HAVE_PGSQL
	int     i;

	/* EOF */
	if(!result)     return NULL;

	/* free old data */
	if(result->values)
	{
		free(result->values);
		result->values = NULL;
	}

	/* EOF */
	if(result->cursor == result->row_num) return NULL;

	/* init result */
	result->fld_num = PQnfields(result->pg_result);

	if(result->fld_num > 0)
	{
		result->values = malloc(sizeof(char*) * result->fld_num);
		for(i = 0; i < result->fld_num; i++)
		{
			result->values[i] = PQgetvalue(result->pg_result, result->cursor, i);
		}
	}

	result->cursor++;

	return result->values;
#endif
#ifdef	HAVE_ORACLE
	int res;

	res = sqlo_fetch(result, 1);

	if(SQLO_SUCCESS == res)
	{
		return sqlo_values(result, NULL, 1);
	}
	else if(SQLO_NO_DATA == res)
	{
		return 0;
	}
	else
	{
		zabbix_log( LOG_LEVEL_ERR, "Fetch failed:%s\n", sqlo_geterror(oracle) );
		exit(FAIL);
	}
#endif
}

/*
 * Execute SQL statement. For select statements only.
 * If fails, program terminates.
 */ 
DB_RESULT DBselect(char *query)
{
/* Do not include any code here. Will break HAVE_PGSQL section */
#ifdef	HAVE_MYSQL
	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s",query);

	if(mysql_query(&mysql,query) != 0)
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s [%d]", mysql_error(&mysql), mysql_errno(&mysql) );

		exit(FAIL);
	}
	return	mysql_store_result(&mysql);
#endif
#ifdef	HAVE_PGSQL
	DB_RESULT result;

	result = malloc(sizeof(ZBX_PG_DB_RESULT));
	result->pg_result = PQexec(conn,query);
	result->values = NULL;
	result->cursor = 0;

	if(result->pg_result==NULL)
	{
		zabbix_log(LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s", "Result is NULL" );
		exit( FAIL );
	}
	if( PQresultStatus(result->pg_result) != PGRES_TUPLES_OK)
	{
		zabbix_log(LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s:%s",
		PQresStatus(PQresultStatus(result->pg_result)),
		PQresultErrorMessage(result->pg_result));
		exit( FAIL );
	}

	/* init rownum */
	result->row_num = PQntuples(result->pg_result);

	return result;
#endif
#ifdef	HAVE_ORACLE
	sqlo_stmt_handle_t sth;

	zabbix_log( LOG_LEVEL_DEBUG, "Executing query:%s",query);
	if(0 > (sth = (sqlo_open(oracle, query,0,NULL))))
	{
		zabbix_log( LOG_LEVEL_ERR, "Query::%s",query);
		zabbix_log(LOG_LEVEL_ERR, "Query failed:%s", sqlo_geterror(oracle));
		exit(FAIL);
	}
	return sth;
#endif
}

/*
 * Execute SQL statement. For select statements only.
 * If fails, program terminates.
 */ 
DB_RESULT DBselectN(char *query, int n)
{
	char sql[MAX_STRING_LEN];
#ifdef	HAVE_MYSQL
	snprintf(sql,MAX_STRING_LEN-1,"%s limit %d", query, n);
	return DBselect(sql);
#endif
#ifdef	HAVE_PGSQL
	snprintf(sql,MAX_STRING_LEN-1,"%s limit %d", query, n);
	return DBselect(sql);
#endif
#ifdef	HAVE_ORACLE
	snprintf(sql,MAX_STRING_LEN-1,"select * from (%s) where rownum<=%d", query, n);
	return DBselect(sql);
#endif
}

/*
 * Get value for given row and field. Must be called after DBselect.
 */ 
/*
char	*DBget_field(DB_RESULT result, int rownum, int fieldnum)
{
#ifdef	HAVE_MYSQL
	MYSQL_ROW	row;

	mysql_data_seek(result, rownum);
	row=mysql_fetch_row(result);
	if(row == NULL)
	{
		zabbix_log(LOG_LEVEL_ERR, "Error while mysql_fetch_row():Error [%s] Rownum [%d] Fieldnum [%d]", mysql_error(&mysql), rownum, fieldnum );
		zabbix_syslog("MYSQL: Error while mysql_fetch_row():Error [%s] Rownum [%d] Fieldnum [%d]", mysql_error(&mysql), rownum, fieldnum );
		exit(FAIL);
	}
	return row[fieldnum];
#endif
#ifdef	HAVE_PGSQL
	return PQgetvalue(result, rownum, fieldnum);
#endif
#ifdef	HAVE_ORACLE
	return FAIL;
#endif
}
*/

/*
 * Get value of autoincrement field for last insert or update statement
 */ 
int	DBinsert_id(int exec_result, const char *table, const char *field)
{
#ifdef	HAVE_MYSQL
	zabbix_log(LOG_LEVEL_DEBUG, "In DBinsert_id()" );
	
	if(exec_result == FAIL) return 0;
	
	return mysql_insert_id(&mysql);
#endif
#ifdef	HAVE_PGSQL
	char		sql[MAX_STRING_LEN];
	DB_RESULT	tmp_res;
	int		id_res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In DBinsert_id()" );
	
	if(exec_result < 0) return 0;
	if(exec_result == FAIL) return 0;
	if((Oid)exec_result == InvalidOid) return 0;
	
	snprintf(sql, sizeof(sql), "select %s from %s where oid=%i", field, table, exec_result);
	tmp_res = DBselect(sql);
	
	id_res = atoi(PQgetvalue(tmp_res->pg_result, 0, 0));
	
	DBfree_result(tmp_res);
	
	return id_res;
#endif
#ifdef	HAVE_ORACLE
	DB_ROW	row;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	int	id;
	
	zabbix_log(LOG_LEVEL_DEBUG, "In DBinsert_id()" );

	if(exec_result == FAIL) return 0;
	
	snprintf(sql, sizeof(sql), "select %s_%s.currval from dual", table, field);
	result = DBselect(sql);

	row = DBfetch(result);
	id = atoi(row[0]);
	DBfree_result(result);

	return id;
#endif
}

/*
 * Returs number of affected rows of last select, update, delete or replace
 */
/*
long    DBaffected_rows()
{
#ifdef  HAVE_MYSQL
	return (long)mysql_affected_rows(&mysql);
#endif
#ifdef  HAVE_PGSQL
	NOT IMPLEMENTED YET
#endif
#ifdef	HAVE_ORACLE
	return FAIL;
#endif
}*/


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
/*
int	DBnum_rows(DB_RESULT result)
{
#ifdef	HAVE_MYSQL
	int rows;

	zabbix_log(LOG_LEVEL_DEBUG, "In DBnum_rows");
	if(result == NULL)
	{
		return	0;
	}
// Order is important !
	rows = mysql_num_rows(result);
	if(rows == 0)
	{
		return	0;
	}
	
// This is necessary to exclude situations like
// atoi(DBget_field(result,0,0). This leads to coredump.
//
// This is required for empty results for count(*), etc 
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
#ifdef	HAVE_ORACLE
	return sqlo_prows(result);
#endif
}
*/

/*
 * Get function value.
 */ 
int     DBget_function_result(double *result,char *functionid)
{
	DB_RESULT dbresult;
	DB_ROW	row;
	int		res = SUCCEED;

        char	sql[MAX_STRING_LEN];

/* 0 is added to distinguish between lastvalue==NULL and empty result */
	snprintf( sql, sizeof(sql)-1, "select 0,lastvalue from functions where functionid=%s", functionid );
	dbresult = DBselect(sql);

	row = DBfetch(dbresult);

	if(!row)
	{
		zabbix_log(LOG_LEVEL_WARNING, "No function for functionid:[%s]", functionid );
		zabbix_syslog("No function for functionid:[%s]", functionid );
		res = FAIL;
	}
	else if(DBis_null(row[1]) == SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "function.lastvalue==NULL [%s]", functionid );
		res = FAIL;
	}
	else
	{
        	*result=atof(row[1]);
	}
        DBfree_result(dbresult);

        return res;
}

/* Returns previous trigger value. If not value found, return TRIGGER_VALUE_FALSE */
int	DBget_prev_trigger_value(int triggerid)
{
	char	sql[MAX_STRING_LEN];
	int	clock;
	int	value;

	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_prev_trigger_value[%d]", triggerid);

	snprintf(sql,sizeof(sql)-1,"select max(clock) from alarms where triggerid=%d",triggerid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
		DBfree_result(result);
		return TRIGGER_VALUE_UNKNOWN;
	}
	clock=atoi(row[0]);
	DBfree_result(result);

	snprintf(sql,sizeof(sql),"select max(clock) from alarms where triggerid=%d and clock<%d",triggerid,clock);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);
	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
		DBfree_result(result);
/* Assume that initially Trigger value is False. Otherwise alarms will not be generated when
status changes to TRUE for te first time */
		return TRIGGER_VALUE_FALSE;
/*		return TRIGGER_VALUE_UNKNOWN;*/
	}
	clock=atoi(row[0]);
	DBfree_result(result);

	snprintf(sql,sizeof(sql)-1,"select value from alarms where triggerid=%d and clock=%d",triggerid,clock);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);
	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Result of [%s] is empty", sql );
		DBfree_result(result);
		return TRIGGER_VALUE_UNKNOWN;
	}
	value=atoi(row[0]);
	DBfree_result(result);

	return value;
}

/* SUCCEED if latest alarm with triggerid has this status */
/* Rewrite required to simplify logic ?*/
static int	latest_alarm(int triggerid, int status)
{
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	int 		ret = FAIL;
	int		alarmid_max=0;
	int		value_max;

	zabbix_log(LOG_LEVEL_DEBUG,"In latest_alarm()");

	/* There could be more than 1 record with the same clock. We are interested in one with max alarmid */
	snprintf(sql,sizeof(sql)-1,"select alarmid,value,clock from alarms where triggerid=%d order by clock desc",triggerid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselectN(sql,20);

	while((row=DBfetch(result)))
	{
		if(atoi(row[0])>=alarmid_max)
		{
			alarmid_max=atoi(row[0]);
			value_max=atoi(row[1]);
		}
	}

	if(alarmid_max!=0 && value_max==status)
	{
		ret = SUCCEED;
	}
	else
	{
                zabbix_log(LOG_LEVEL_DEBUG, "No alarms");
	}
	DBfree_result(result);
	zabbix_log(LOG_LEVEL_DEBUG,"Value of latest alarm is [%d] max alarmid [%d]", value_max, alarmid_max);

	return ret;
}

/* SUCCEED if latest service alarm has this status */
/* Rewrite required to simplify logic ?*/
int	latest_service_alarm(int serviceid, int status)
{
	char	sql[MAX_STRING_LEN];
	int	clock;
	DB_RESULT	result;
	DB_ROW		row;
	int ret = FAIL;


	zabbix_log(LOG_LEVEL_DEBUG,"In latest_service_alarm()");

	snprintf(sql,sizeof(sql)-1,"select max(clock) from service_alarms where serviceid=%d",serviceid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);
	row = DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
        {
                zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
                ret = FAIL;
        }
	else
	{
		clock=atoi(row[0]);
		DBfree_result(result);

		snprintf(sql,sizeof(sql)-1,"select value from service_alarms where serviceid=%d and clock=%d",serviceid,clock);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		result = DBselect(sql);
		row = DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(atoi(row[0]) == status)
			{
				ret = SUCCEED;
			}
		}
	}

	DBfree_result(result);

	return ret;
}

/* Returns alarmid or 0 */
int	add_alarm(int triggerid,int status,int clock,int *alarmid)
{
	char	sql[MAX_STRING_LEN];

	*alarmid=0;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_alarm(%d,%d,%d)",triggerid, status, *alarmid);

	/* Latest alarm has the same status? */
	if(latest_alarm(triggerid,status) == SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Alarm for triggerid [%d] status [%d] already exists",triggerid,status);
		return FAIL;
	}

	snprintf(sql,sizeof(sql)-1,"insert into alarms(triggerid,clock,value) values(%d,%d,%d)", triggerid, clock, status);

	*alarmid = DBinsert_id(DBexecute(sql), "alarms", "alarmid");

	/* Cancel currently active alerts */
	if(status == TRIGGER_VALUE_FALSE || status == TRIGGER_VALUE_TRUE)
	{
		snprintf(sql,sizeof(sql)-1,"update alerts set retries=3,error='Trigger changed its status. WIll not send repeats.' where triggerid=%d and repeats>0 and status=%d", triggerid, ALERT_STATUS_NOT_SENT);
		DBexecute(sql);
	}

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

int	DBupdate_trigger_value(DB_TRIGGER *trigger, int new_value, int now, char *reason)
{
	char	sql[MAX_STRING_LEN];
	int	alarmid;
	int	ret = SUCCEED;

	if(reason==NULL)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"In update_trigger_value[%d,%d,%d]", trigger->triggerid, new_value, now);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG,"In update_trigger_value[%d,%d,%d,%s]", trigger->triggerid, new_value, now, reason);
	}

	if(trigger->value != new_value)
	{
		trigger->prevvalue=DBget_prev_trigger_value(trigger->triggerid);
		if(add_alarm(trigger->triggerid,new_value,now,&alarmid) == SUCCEED)
		{
			if(reason==NULL)
			{
				snprintf(sql,sizeof(sql)-1,"update triggers set value=%d,lastchange=%d,error='' where triggerid=%d",new_value,now,trigger->triggerid);
			}
			else
			{
				snprintf(sql,sizeof(sql)-1,"update triggers set value=%d,lastchange=%d,error='%s' where triggerid=%d",new_value,now,reason, trigger->triggerid);
			}
			DBexecute(sql);
/* It is not required and is wrong! */
/*			if(TRIGGER_VALUE_UNKNOWN == new_value)
			{
				snprintf(sql,sizeof(sql)-1,"update functions set lastvalue=NULL where triggerid=%d",trigger->triggerid);
				DBexecute(sql);
			}*/
			if(	((trigger->value == TRIGGER_VALUE_TRUE) && (new_value == TRIGGER_VALUE_FALSE)) ||
				((trigger->value == TRIGGER_VALUE_FALSE) && (new_value == TRIGGER_VALUE_TRUE)) ||
				((trigger->prevvalue == TRIGGER_VALUE_FALSE) && (trigger->value == TRIGGER_VALUE_UNKNOWN) && (new_value == TRIGGER_VALUE_TRUE)) ||
				((trigger->prevvalue == TRIGGER_VALUE_TRUE) && (trigger->value == TRIGGER_VALUE_UNKNOWN) && (new_value == TRIGGER_VALUE_FALSE)))
			{
				zabbix_log(LOG_LEVEL_DEBUG,"In update_trigger_value. Before apply_actions. Triggerid [%d] prev [%d] curr [%d] new [%d]", trigger->triggerid, trigger->prevvalue, trigger->value, new_value);
				apply_actions(trigger,alarmid,new_value);
				if(new_value == TRIGGER_VALUE_TRUE)
				{
					update_services(trigger->triggerid, trigger->priority);
				}
				else
				{
					update_services(trigger->triggerid, 0);
				}
			}
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG,"Alarm not added for triggerid [%d]", trigger->triggerid);
			ret = FAIL;
		}
	}
	return ret;
}

/*
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

	zabbix_log(LOG_LEVEL_DEBUG,"End of update_trigger_value()");
	return SUCCEED;
}
*/

void update_triggers_status_to_unknown(int hostid,int clock,char *reason)
{
	char	sql[MAX_STRING_LEN];

	DB_RESULT	result;
	DB_ROW		row;
	DB_TRIGGER	trigger;

	zabbix_log(LOG_LEVEL_DEBUG,"In update_triggers_status_to_unknown()");

	memset(&trigger, 0, sizeof(trigger));

/*	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and h.hostid=%d and i.key_<>'%s'",hostid,SERVER_STATUS_KEY);*/
	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid,t.value,t.url,t.comments from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and h.hostid=%d and i.key_ not in ('%s','%s','%s')",hostid,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		trigger.triggerid=atoi(row[0]);
		trigger.value=atoi(row[1]);
		strscpy(trigger.url, row[2]);
		strscpy(trigger.comments, row[3]);
		DBupdate_trigger_value(&trigger,TRIGGER_VALUE_UNKNOWN,clock,reason);
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
	int	serviceid;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_services_by_triggerid(%d)", triggerid);
	snprintf(sql,sizeof(sql)-1,"select serviceid from services where triggerid=%d", triggerid);
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		serviceid=atoi(row[0]);
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
/*	snprintf(sql,sizeof(sql)-1,"delete from actions where triggerid=%d and scope=%d", triggerid, ACTION_SCOPE_TRIGGER);
	DBexecute(sql);*/

	DBdelete_services_by_triggerid(triggerid);

	snprintf(sql,sizeof(sql)-1,"update sysmaps_links set triggerid=NULL where triggerid=%d", triggerid);
	DBexecute(sql);
	snprintf(sql,sizeof(sql)-1,"delete from triggers where triggerid=%d", triggerid);
	DBexecute(sql);
}

void  DBdelete_triggers_by_itemid(int itemid)
{
	int	triggerid;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_triggers_by_itemid(%d)", itemid);
	snprintf(sql,sizeof(sql)-1,"select triggerid from functions where itemid=%d", itemid);
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		triggerid=atoi(row[0]);
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

void DBdelete_sysmaps_links_by_shostid(int shostid)
{
	char	sql[MAX_STRING_LEN];

	snprintf(sql,sizeof(sql)-1,"delete from sysmaps_links where shostid1=%d or shostid2=%d", shostid, shostid);
	DBexecute(sql);
}

void DBdelete_sysmaps_hosts_by_hostid(int hostid)
{
	int	shostid;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_sysmaps_hosts(%d)", hostid);
	snprintf(sql,sizeof(sql)-1,"select shostid from sysmaps_elements where hostid=%d", hostid);
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		shostid=atoi(row[0]);
		DBdelete_sysmaps_links_by_shostid(shostid);
	}
	DBfree_result(result);

	snprintf(sql,sizeof(sql)-1,"delete from sysmaps_elements where hostid=%d", hostid);
	DBexecute(sql);
}

/*
int DBdelete_history_pertial(int itemid)
{
	char	sql[MAX_STRING_LEN];

#ifdef	HAVE_ORACLE
	snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d and rownum<500", itemid);
#else
	snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d limit 500", itemid);
#endif
	DBexecute(sql);

	return DBaffected_rows();
}
*/

void DBupdate_triggers_status_after_restart(void)
{
	char	sql[MAX_STRING_LEN];
	int	lastchange;
	int	now;

	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW	row;
	DB_ROW	row2;
	DB_TRIGGER	trigger;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBupdate_triggers_after_restart()");

	now=time(NULL);

	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid,t.value from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and i.nextcheck+i.delay<%d and i.key_<>'%s' and h.status not in (%d,%d)",now,SERVER_STATUS_KEY, HOST_STATUS_DELETED, HOST_STATUS_TEMPLATE);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		trigger.triggerid=atoi(row[0]);
		trigger.value=atoi(row[1]);

		snprintf(sql,sizeof(sql)-1,"select min(i.nextcheck+i.delay) from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and i.nextcheck<>0 and t.triggerid=%d and i.type<>%d",trigger.triggerid,ITEM_TYPE_TRAPPER);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		result2 = DBselect(sql);
		row2=DBfetch(result2);
		if(!row2 || DBis_null(row2[0])==SUCCEED)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "No triggers to update (2)");
			DBfree_result(result2);
			continue;
		}

		lastchange=atoi(row2[0]);
		DBfree_result(result2);

		DBupdate_trigger_value(&trigger,TRIGGER_VALUE_UNKNOWN,lastchange,"ZABBIX was down.");
	}

	DBfree_result(result);
	zabbix_log(LOG_LEVEL_DEBUG,"End of DBupdate_triggers_after_restart()");

	return; 
}

void DBupdate_host_availability(int hostid,int available,int clock, char *error)
{
	DB_RESULT	result;
	DB_ROW		row;
	char	sql[MAX_STRING_LEN];
	char	error_esc[MAX_STRING_LEN];
	int	disable_until;

	zabbix_log(LOG_LEVEL_DEBUG,"In update_host_availability()");

	if(error!=NULL)
	{
		DBescape_string(error,error_esc,MAX_STRING_LEN);
	}
	else
	{
		strscpy(error_esc,"");
	}

	snprintf(sql,sizeof(sql)-1,"select available,disable_until from hosts where hostid=%d",hostid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);
	row=DBfetch(result);

	if(!row)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot select host with hostid [%d]",hostid);
		zabbix_syslog("Cannot select host with hostid [%d]",hostid);
		DBfree_result(result);
		return;
	}

	disable_until = atoi(row[1]);

	if(available == atoi(row[0]))
	{
/*		if((available==HOST_AVAILABLE_FALSE) 
		&&(clock+CONFIG_UNREACHABLE_PERIOD>disable_until) )
		{
		}
		else
		{*/
			zabbix_log(LOG_LEVEL_DEBUG, "Host already has availability [%d]",available);
			DBfree_result(result);
			return;
/*		}*/
	}

	DBfree_result(result);

	if(available==HOST_AVAILABLE_TRUE)
	{
		snprintf(sql,sizeof(sql)-1,"update hosts set available=%d,error=' ',errors_from=0 where hostid=%d",HOST_AVAILABLE_TRUE,hostid);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		DBexecute(sql);
	}
	else if(available==HOST_AVAILABLE_FALSE)
	{
/*		if(disable_until+CONFIG_UNREACHABLE_PERIOD>clock)
		{
			snprintf(sql,sizeof(sql)-1,"update hosts set available=%d,disable_until=disable_until+%d,error='%s' where hostid=%d",HOST_AVAILABLE_FALSE,CONFIG_UNREACHABLE_DELAY,error_esc,hostid);
		}
		else
		{
			snprintf(sql,sizeof(sql)-1,"update hosts set available=%d,disable_until=%d,error='%s' where hostid=%d",HOST_AVAILABLE_FALSE,clock+CONFIG_UNREACHABLE_DELAY,error_esc,hostid);
		}*/
		/* '%s ' - space to make Oracle happy */
		snprintf(sql,sizeof(sql)-1,"update hosts set available=%d,error='%s ' where hostid=%d",HOST_AVAILABLE_FALSE,error_esc,hostid);
		zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
		DBexecute(sql);
	}
	else
	{
		zabbix_log( LOG_LEVEL_ERR, "Unknown host availability [%d] for hostid [%d]", available, hostid);
		zabbix_syslog("Unknown host availability [%d] for hostid [%d]", available, hostid);
		return;
	}

	update_triggers_status_to_unknown(hostid,clock,"Host is unavailable.");
	zabbix_log(LOG_LEVEL_DEBUG,"End of update_host_availability()");

	return;
}

int	DBupdate_item_status_to_notsupported(int itemid, char *error)
{
	char	sql[MAX_STRING_LEN];
	char	error_esc[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In DBupdate_item_status_to_notsupported()");

	if(error!=NULL)
	{
		DBescape_string(error,error_esc,MAX_STRING_LEN);
	}
	else
	{
		strscpy(error_esc,"");
	}

	/* '&s ' to make Oracle happy */
	snprintf(sql,sizeof(sql)-1,"update items set status=%d,error='%s ' where itemid=%d",ITEM_STATUS_NOTSUPPORTED,error_esc,itemid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	DBexecute(sql);

	return SUCCEED;
}

int	DBadd_trend(int itemid, double value, int clock)
{
	DB_RESULT	result;
	DB_ROW		row;
	char	sql[MAX_STRING_LEN];
	int	hour;
	int	num;
	double	value_min, value_avg, value_max;	

	zabbix_log(LOG_LEVEL_DEBUG,"In add_trend()");

	hour=clock-clock%3600;

	snprintf(sql,sizeof(sql)-1,"select num,value_min,value_avg,value_max from trends where itemid=%d and clock=%d", itemid, hour);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselect(sql);

	row=DBfetch(result);

	if(row)
	{
		num=atoi(row[0]);
		value_min=atof(row[1]);
		value_avg=atof(row[2]);
		value_max=atof(row[3]);
		if(value<value_min)	value_min=value;
/* Unfortunate mistake... */
/*		if(value>value_avg)	value_max=value;*/
		if(value>value_max)	value_max=value;
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

int	DBadd_history_uint(int itemid, zbx_uint64_t value, int clock)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_uint()");

	snprintf(sql,sizeof(sql)-1,"insert into history_uint (clock,itemid,value) values (%d,%d," ZBX_FS_UI64 ")",clock,itemid,value);
	DBexecute(sql);

	DBadd_trend(itemid, (double)value, clock);

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

int	DBadd_history_text(int itemid, char *value, int clock)
{
#ifdef HAVE_ORACLE
	char	sql[MAX_STRING_LEN];
	char	*value_esc;
	int	value_esc_max_len = 0;
	int	ret = FAIL;

	sqlo_lob_desc_t		loblp;		/* the lob locator */
	sqlo_stmt_handle_t	sth;

	sqlo_autocommit_off(oracle);

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_text()");

	value_esc_max_len = strlen(value)+1024;
	value_esc = malloc(value_esc_max_len);
	if(value_esc == NULL)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Can't allocate required memory");
		goto lbl_exit;
	}

	DBescape_string(value, value_esc, value_esc_max_len-1);
	value_esc_max_len = strlen(value_esc);

	/* alloate the lob descriptor */
	if(sqlo_alloc_lob_desc(oracle, &loblp) < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"CLOB allocating failed:%s", sqlo_geterror(oracle));
		goto lbl_exit;
	}

	snprintf(sql, sizeof(sql)-1, "insert into history_text (clock,itemid,value)"
		" values (%d,%d, EMPTY_CLOB()) returning value into :1", clock, itemid);

	zabbix_log(LOG_LEVEL_DEBUG,"Query:%s", sql);

	/* parse the statement */
	sth = sqlo_prepare(oracle, sql);
	if(sth < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Query prepearing failed:%s", sqlo_geterror(oracle));
		goto lbl_exit;
	}

	/* bind input variables. Note: we bind the lob descriptor here */
	if(SQLO_SUCCESS != sqlo_bind_by_pos(sth, 1, SQLOT_CLOB, &loblp, 0, NULL, 0))
	{
		zabbix_log(LOG_LEVEL_DEBUG,"CLOB binding failed:%s", sqlo_geterror(oracle));
		goto lbl_exit_loblp;
	}

	/* execute the statement */
	if(sqlo_execute(sth, 1) != SQLO_SUCCESS)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Query failed:%s", sqlo_geterror(oracle));
		goto lbl_exit_loblp;
	}

	/* write the lob */
	ret = sqlo_lob_write_buffer(oracle, loblp, value_esc_max_len, value_esc, value_esc_max_len, SQLO_ONE_PIECE);
	if(ret < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"CLOB writing failed:%s", sqlo_geterror(oracle) );
		goto lbl_exit_loblp;
	}

	/* commiting */
	if(sqlo_commit(oracle) < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Commiting failed:%s", sqlo_geterror(oracle) );
	}

	ret = SUCCEED;

lbl_exit_loblp:
	sqlo_free_lob_desc(oracle, &loblp);

lbl_exit:
	if(sth >= 0)	sqlo_close(sth);
	if(value_esc)	free(value_esc);

	sqlo_autocommit_on(oracle);

	return ret;

#else /* HAVE_ORACLE */

	char    *sql;
	char    *value_esc;
	int	value_esc_max_len = 0;
	int	sql_max_len = 0;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_str()");

	value_esc_max_len = strlen(value)+1024;
	value_esc = malloc(value_esc_max_len);
	if(value_esc == NULL)
	{
		return FAIL;
	}

	sql_max_len = value_esc_max_len+100;
	sql = malloc(sql_max_len);
	if(sql == NULL)
	{
		free(value_esc);
		return FAIL;
	}

	DBescape_string(value,value_esc,value_esc_max_len);
	snprintf(sql,sql_max_len, "insert into history_text (clock,itemid,value) values (%d,%d,'%s')",clock,itemid,value_esc);
	DBexecute(sql);

	free(value_esc);
	free(sql);

	return SUCCEED;

#endif
}


int	DBadd_history_log(int itemid, char *value, int clock, int timestamp,char *source, int severity)
{
	char	sql[MAX_STRING_LEN];
	char	value_esc[MAX_STRING_LEN];
	char	source_esc[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_log()");

	DBescape_string(value,value_esc,MAX_STRING_LEN);
	DBescape_string(source,source_esc,MAX_STRING_LEN);
	snprintf(sql,sizeof(sql)-1,"insert into history_log (clock,itemid,timestamp,value,source,severity) values (%d,%d,%d,'%s','%s',%d)",clock,itemid,timestamp,value_esc,source_esc,severity);
	DBexecute(sql);

	return SUCCEED;
}


int	DBget_items_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_items_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from items");

	result=DBselect(sql);
	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		zabbix_syslog("Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_triggers_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_triggers_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from triggers");

	result=DBselect(sql);

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		zabbix_syslog("Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_items_unsupported_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_items_unsupported_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from items where status=%d", ITEM_STATUS_NOTSUPPORTED);

	result=DBselect(sql);
	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		zabbix_syslog("Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_history_str_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_history_str_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from history_str");

	result=DBselect(sql);
	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		zabbix_syslog("Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_history_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_history_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from history");

	result=DBselect(sql);
	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		zabbix_syslog("Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_trends_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_trends_count()");

	snprintf(sql,sizeof(sql)-1,"select count(*) from trends");

	result=DBselect(sql);
	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		zabbix_syslog("Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_queue_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	int	now;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_queue_count()");

	now=time(NULL);
/*	snprintf(sql,sizeof(sql)-1,"select count(*) from items i,hosts h where i.status=%d and i.type not in (%d) and h.status=%d and i.hostid=h.hostid and i.nextcheck<%d and i.key_<>'status'", ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, HOST_STATUS_MONITORED, now);*/
	snprintf(sql,sizeof(sql)-1,"select count(*) from items i,hosts h where i.status=%d and i.type not in (%d) and ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<=%d)) and i.hostid=h.hostid and i.nextcheck<%d and i.key_ not in ('%s','%s','%s','%s')", ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, now, now, SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY, SERVER_ZABBIXLOG_KEY);

	result=DBselect(sql);
	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		zabbix_syslog("Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBadd_alert(int actionid, int userid, int triggerid,  int mediatypeid, char *sendto, char *subject, char *message, int maxrepeats, int repeatdelay)
{
	int	now;
	char	*sql		= NULL;
	char	*sendto_esc	= NULL;
	char	*subject_esc	= NULL;
	char	*message_esc	= NULL;

	int	size;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_alert(triggerid[%d])",triggerid);

	now = time(NULL);

	size = strlen(sendto) * 3 / 2 + 1;
	sendto_esc = zbx_malloc(size);
	DBescape_string(sendto	, sendto_esc	, size);

	size = strlen(subject) * 3 / 2 + 1;
	subject_esc = zbx_malloc(size);
	DBescape_string(subject	, subject_esc	, size);

	size = strlen(message) * 3 / 2 + 1;
	message_esc = zbx_malloc(size);
	DBescape_string(message	, message_esc	, size);

/* Does not work on PostgreSQL */
/*	snprintf(sql,sizeof(sql)-1,"insert into alerts (alertid,actionid,clock,mediatypeid,sendto,subject,message,status,retries) values (NULL,%d,%d,%d,'%s','%s','%s',0,0)",actionid,now,mediatypeid,sendto,subject,message);*/
	sql = zbx_dsprintf("insert into alerts (actionid,triggerid,userid,clock,mediatypeid,sendto,subject,message,status,retries,maxrepeats,delay) values (%d,%d,%d,%d,%d,'%s','%s','%s',0,0,%d,%d)",actionid,triggerid,userid,now,mediatypeid,sendto_esc,subject_esc,message_esc, maxrepeats, repeatdelay);

	zbx_free(sendto_esc);
	zbx_free(subject_esc);
	zbx_free(message_esc);
	
	DBexecute(sql);
	
	zbx_free(sql);

	return SUCCEED;
}

void	DBvacuum(void)
{
#ifdef	HAVE_PGSQL
	char *table_for_housekeeping[]={"services", "services_links", "graphs_items", "graphs", "sysmaps_links",
			"sysmaps_elements", "sysmaps", "config", "groups", "hosts_groups", "alerts",
			"actions", "alarms", "functions", "history", "history_str", "hosts", "trends",
			"items", "media", "media_type", "triggers", "trigger_depends", "users",
			"sessions", "rights", "service_alarms", "profiles", "screens", "screens_items",
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

/* Broken:
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
*/

void    DBescape_string(char *from, char *to, int maxlen)
{
	int     i,ptr;

	assert(to);

	maxlen--;
	for(i=0, ptr=0; from && from[i] && ptr < maxlen; i++)
	{
#ifdef	HAVE_ORACLE
		if( (from[i] == '\''))
		{
			to[ptr++] = '\'';
#else /* HAVE_ORACLE */
		if( (from[i] == '\'') || (from[i] == '\\'))
		{
			to[ptr++] = '\\';
#endif
			if(ptr >= maxlen)       break;
		}
		to[ptr++] = from[i];
	}
	to[ptr]=0;
}

void	DBget_item_from_db(DB_ITEM *item,DB_ROW row)
{
	char	*s;

	item->itemid=atoi(row[0]);
	strscpy(item->key,row[1]);
	item->host=row[2];
	item->port=atoi(row[3]);
	item->delay=atoi(row[4]);
	item->description=row[5];
	item->nextcheck=atoi(row[6]);
	item->type=atoi(row[7]);
	item->snmp_community=row[8];
	item->snmp_oid=row[9];
	item->useip=atoi(row[10]);
	item->ip=row[11];
	item->history=atoi(row[12]);
	s=row[13];
	if(DBis_null(s)==SUCCEED)
	{
		item->lastvalue_null=1;
	}
	else
	{
		item->lastvalue_null=0;
		item->lastvalue_str=s;
		item->lastvalue=atof(s);
	}
	s=row[14];
	if(DBis_null(s)==SUCCEED)
	{
		item->prevvalue_null=1;
	}
	else
	{
		item->prevvalue_null=0;
		item->prevvalue_str=s;
		item->prevvalue=atof(s);
	}
	item->hostid=atoi(row[15]);
	item->host_status=atoi(row[16]);
	item->value_type=atoi(row[17]);

	item->host_errors_from=atoi(row[18]);
	item->snmp_port=atoi(row[19]);
	item->delta=atoi(row[20]);

	s=row[21];
	if(DBis_null(s)==SUCCEED)
	{
		item->prevorgvalue_null=1;
	}
	else
	{
		item->prevorgvalue_null=0;
		item->prevorgvalue=atof(s);
	}
	s=row[22];
	if(DBis_null(s)==SUCCEED)
	{
		item->lastclock=0;
	}
	else
	{
		item->lastclock=atoi(s);
	}

	item->units=row[23];
	item->multiplier=atoi(row[24]);

	item->snmpv3_securityname = row[25];
	item->snmpv3_securitylevel = atoi(row[26]);
	item->snmpv3_authpassphrase = row[27];
	item->snmpv3_privpassphrase = row[28];
	item->formula = row[29];
	item->host_available=atoi(row[30]);
	item->status=atoi(row[31]);
	item->trapper_hosts=row[32];
	item->logtimefmt=row[33];
	item->valuemapid=atoi(row[34]);
}
