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
	int     ItemId;
	char    *Description;
	char    *Key;
	char    *Host;
	char    *ShortName;
	int     Port;
	int     Delay;
	int     History;
	time_t  LastDelete;
	time_t  LastCheck;
};
 
FUNCTION
{
	int     FunctionId;
	int     ItemId;
	int     TriggerId;
	double  LastValue;
	char    *Function;
	int     Parameter;
};

MEDIA
{
	int	MediaId;
	char	*Type;
	char	*SendTo;
	int	Active;
};

ACTION
{
	int	ActionId;
	int	TriggerId;
	int	UserId;
	int	Good;
	int	Delay;
	int	LastCheck;
	char	*Subject;
	char	*Message;
};

TRIGGER
{
	int	TriggerId;
	char	*Expression;
	char	*Description;
	int	IsTrue;
};

void    DBconnect( void );

void	DBexecute( char *query );

DB_RESULT *DBget_result( );
DB_ROW	DBfetch_row(DB_RESULT *result);
int     DBnum_rows(DB_RESULT *result);
int	DBget_function_result(float *Result,char *FunctionID);

#endif
