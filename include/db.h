#ifndef ZABBIX_DB_H
#define ZABBIX_DB_H

/* time_t */
#include <time.h>
#include "config.h"

#ifdef HAVE_MYSQL
	#include "mysql.h"
#endif

#ifdef HAVE_PGSQL
	#include "libpq-fe.h"
#endif

#define DB_ITEM struct item_type
#define DB_TRIGGER struct trigger_type
#define DB_ACTION struct action_type
#define DB_FUNCTION struct function_type
#define DB_MEDIA struct media_type

#ifdef HAVE_MYSQL
	#define	DB_RESULT	MYSQL_RES
	#define	DBfree_result	mysql_free_result
#endif

#ifdef HAVE_PGSQL
	#define	DB_RESULT	PGresult
	#define	DBfree_result	PQclear
#endif


DB_ITEM
{
	int     itemid;
	int     hostid;
	int     type;
	char    *description;
	char    *key;
	char    *host;
	int     useip;
	char    *ip;
	char    *shortname;
	char    *snmp_community;
	char    *snmp_oid;
	int     port;
	int     delay;
	int     history;
	double	lastvalue;
	int     lastvalue_null;
	double	prevvalue;
	int     prevvalue_null;
	time_t  lastdelete;
	time_t  lastcheck;
	time_t	nextcheck;
	int     value_type;
};
 
DB_FUNCTION
{
	int     functionid;
	int     itemid;
	int     triggerid;
	double  lastvalue;
	int	lastvalue_null;
	char    *function;
	int     parameter;
};

DB_MEDIA
{
	int	mediaid;
	char	*type;
	char	*sendto;
	int	active;
};

DB_TRIGGER
{
	int	triggerid;
	char	*expression;
	char	*description;
	int	istrue;
};

DB_ACTION
{
	int     actionid;
	int     triggerid;
	int     userid;
	int     good;
	int     delay;
	int     lastcheck;
	char    *subject;
	char    *message;
};


void    DBconnect( char *dbname, char *dbuser, char *dbpassword, char *dbsocket);

int	DBexecute( char *query );

DB_RESULT	*DBselect(char *query);
char		*DBget_field(DB_RESULT *result, int rownum, int fieldnum);
int		DBnum_rows(DB_RESULT *result);

int	DBget_function_result(float *Result,char *functionID);

#endif
