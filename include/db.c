#include <stdlib.h>
#include <stdio.h>
#include <syslog.h>

#include "db.h"
#include "common.h"

#ifdef	USE_MYSQL
	MYSQL	mysql;
#endif

#ifdef	USE_POSTGRESQL
	PGconn	*conn;
#endif

/*
 * Connect to database.
 * If fails, program terminates.
 */ 
void    DBconnect( void )
{
#ifdef	USE_MYSQL
	if( ! mysql_connect( &mysql, NULL, DB_USER, DB_PASSWD ) )
	{
		syslog(LOG_ERR, "Failed to connect to database: Error: %s\n",mysql_error(&mysql) );
		exit( FAIL );
	}
	if( mysql_select_db( &mysql, DB_NAME ) != 0 )
	{
		syslog(LOG_ERR, "Failed to select database: Error: %s\n",mysql_error(&mysql) );
		exit( FAIL );
	}
#endif
#ifdef	USE_POSTGRESQL
/*	conn = PQsetdb(pghost, pgport, pgoptions, pgtty, dbName); */
	conn = PQsetdb(NULL, NULL, NULL, NULL, DB_NAME);

/* check to see that the backend connection was successfully made */
	if (PQstatus(conn) == CONNECTION_BAD)
	{
		syslog(LOG_ERR, "Connection to database '%s' failed.\n", DB_NAME);
		syslog(LOG_ERR, "%s", PQerrorMessage(conn));
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

#ifdef	USE_MYSQL
	syslog( LOG_DEBUG, "Executing query:%s\n",query);

	if( mysql_query(&mysql,query) != 0 )
	{
		syslog(LOG_ERR, "Query failed:%s", mysql_error(&mysql) );
		return FAIL;
	}
#endif
#ifdef	USE_POSTGRESQL
	PGresult	*result;

	syslog( LOG_DEBUG, "Executing query:%s\n",query);

	result = PQexec(conn,query);

	if( result==NULL)
	{
		syslog(LOG_ERR, "Query failed:%s", "Result is NULL" );
		PQclear(result);
		return FAIL;
	}
	if( PQresultStatus(result) != PGRES_COMMAND_OK)
	{
		syslog(LOG_ERR, "Query failed:%s", PQresStatus(PQresultStatus(result)) );
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
#ifdef	USE_MYSQL
	syslog( LOG_DEBUG, "Executing query:%s\n",query);

	if( mysql_query(&mysql,query) != 0 )
	{
		syslog(LOG_ERR, "Query failed:%s", mysql_error(&mysql) );
		exit( FAIL );
	}
	return	mysql_store_result(&mysql);
#endif
#ifdef	USE_POSTGRESQL
	PGresult	*result;

	syslog( LOG_DEBUG, "Executing query:%s\n",query);
	result = PQexec(conn,query);

	if( result==NULL)
	{
		syslog(LOG_ERR, "Query failed:%s", "Result is NULL" );
		exit( FAIL );
	}
	if( PQresultStatus(result) != PGRES_TUPLES_OK)
	{
		syslog(LOG_ERR, "Query failed:%s", PQresStatus(PQresultStatus(result)) );
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
#ifdef	USE_MYSQL
	MYSQL_ROW	row;

	mysql_data_seek(result, rownum);
	row=mysql_fetch_row(result);
	syslog(LOG_DEBUG, "Got field:%s", row[fieldnum] );
	return row[fieldnum];
#endif
#ifdef	USE_POSTGRESQL
	return PQgetvalue(result, rownum, fieldnum);
#endif
}

/*
 * Get number of selected records.
 */ 
int	DBnum_rows(DB_RESULT *result)
{
#ifdef	USE_MYSQL
	return mysql_num_rows(result);
#endif
#ifdef	USE_POSTGRESQL
	return PQntuples(result);
#endif
}

/*
 * Get function value.
 */ 
int     DBget_function_result(float *Result,char *FunctionID)
{
	DB_RESULT *result;

        char	c[128];
        int	rows;

	sprintf( c, "select lastvalue from functions where functionid=%s", FunctionID );
	result = DBselect(c);

	if(result == NULL)
	{
        	DBfree_result(result);
		syslog(LOG_WARNING, "Query failed for functionid:[%s]", FunctionID );
		return FAIL;	
	}
        rows = DBnum_rows(result);
	if(rows == 0)
	{
        	DBfree_result(result);
		syslog(LOG_WARNING, "Query failed for functionid:[%s]", FunctionID );
		return FAIL;	
	}
        *Result=atof(DBget_field(result,0,0));
        DBfree_result(result);

        return SUCCEED;
}


int	DBget_lastvalue(float *Result,char *host,char *key,char *function,char *parameter)
{
	DB_RESULT *result;

        char	c[128];
        int	rows;

	sprintf( c, "select i.lastvalue from functions f,items i,hosts h where i.itemid=f.itemid and h.host='%s' and h.hostid=i.hostid and i.key_='%s' and f.function='%s' and f.parameter=%s", host, key, function, parameter );
	syslog(LOG_WARNING, "Query:%s",c );
	result = DBselect(c);

	if(result == NULL)
	{
        	DBfree_result(result);
		syslog(LOG_WARNING, "Query failed" );
		return FAIL;	
	}
        rows = DBnum_rows(result);
	if(rows == 0)
	{
        	DBfree_result(result);
		syslog(LOG_WARNING, "Query failed" );
		return FAIL;	
	}
        *Result=atof(DBget_field(result,0,0));
        DBfree_result(result);

        return SUCCEED;
}
