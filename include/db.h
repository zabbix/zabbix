#ifndef MON_DB_H
#define MON_DB_H
 
#include "mysql.h"

#define	DB_NAME		"zabbix"
#define	DB_USER		"root"
#define	DB_PASSWD	""

#define ITEM struct item_type
#define TRIGGER struct trigger_type
#define FUNCTION struct function_type
#define ACTION struct action_type
#define MEDIA struct media_type

#define	DB_RESULT	MYSQL_RES
#define	DB_ROW		MYSQL_ROW
#define	DBfree_result	mysql_free_result

ITEM
{
	int     itemid;
	char    *description;
	char    *key;
	char    *host;
	char    *shortname;
	int     port;
	int     delay;
	int     history;
	time_t  lastdelete;
	time_t  lastcheck;
};
 
FUNCTION
{
	int     functionid;
	int     itemid;
	int     triggerid;
	double  lastvalue;
	char    *function;
	int     parameter;
};

MEDIA
{
	int	mediaid;
	char	*type;
	char	*sendto;
	int	active;
};

ACTION
{
	int	actionid;
	int	triggerid;
	int	userid;
	int	good;
	int	delay;
	int	lastcheck;
	char	*subject;
	char	*message;
};

TRIGGER
{
	int	triggerid;
	char	*expression;
	char	*description;
	int	istrue;
};

void    DBconnect( void );

void	DBexecute( char *query );

DB_RESULT *DBget_result( );
DB_ROW	DBfetch_row(DB_RESULT *result);
int     DBnum_rows(DB_RESULT *result);
int	DBget_function_result(float *Result,char *FunctionID);

#endif
