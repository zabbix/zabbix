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


#ifndef ZABBIX_DB_H
#define ZABBIX_DB_H

/* time_t */
#include <time.h>

#include "config.h"
#include "common.h"

#ifdef HAVE_MYSQL
#	include "mysql.h"
#	include "errmsg.h"
#	include "mysqld_error.h"
#	define	DB_HANDLE	MYSQL
#endif /* HAVE_MYSQL */

#ifdef HAVE_ORACLE
#	include "sqlora.h"
#endif /* HAVE_ORACLE */

#ifdef HAVE_PGSQL
#	include <libpq-fe.h>
#endif /* HAVE_PGSQL */

extern	char	*CONFIG_DBHOST;
extern	char	*CONFIG_DBNAME;
extern	char	*CONFIG_DBUSER;
extern	char	*CONFIG_DBPASSWORD;
extern	char	*CONFIG_DBSOCKET;
extern	int	CONFIG_DBPORT;

#define DB_FULL_DELETE	0
#define DB_PART_DELETE	1

#define DB_HOST		struct host_type
#define DB_ITEM		struct item_type
#define DB_TRIGGER	struct trigger_type
#define DB_ACTION	struct action_type
#define DB_CONDITION	struct condition_type
#define DB_ALERT	struct alert_type
#define DB_FUNCTION	struct function_type
#define DB_MEDIA	struct media_type
#define DB_MEDIATYPE	struct mediatype_type
#define DB_GRAPH	struct graph_type
#define DB_GRAPH_ITEM	struct graph_item_type
#define DB_HOUSEKEEPER	struct housekeeper_type

#ifdef HAVE_MYSQL
	#define	DB_RESULT	MYSQL_RES *
	#define	DBfree_result	mysql_free_result
	#define DB_ROW		MYSQL_ROW
#endif

#ifdef HAVE_PGSQL
	#define DB_ROW		char **
	#define	DB_RESULT	ZBX_PG_DB_RESULT*
	#define	DBfree_result	PG_DBfree_result

	typedef struct zbx_pg_db_result_s
	{
		PGresult	*pg_result;
		int		row_num;
		int		fld_num;
		int		cursor;
		DB_ROW		values;
	} ZBX_PG_DB_RESULT;

void	PG_DBfree_result(DB_RESULT result);

#endif

#ifdef HAVE_ORACLE
	#define	DB_RESULT	sqlo_stmt_handle_t
	#define	DBfree_result	sqlo_close
	#define DB_ROW		char **
#endif

#define	MAX_HOST_HOST_LEN	64

#define	MAX_ITEM_KEY_LEN	64
#define	MAX_ITEM_IP_LEN		15
#define	MAX_ITEM_SNMP_COMMUNITY_LEN	64
#define	MAX_ITEM_SNMP_OID_LEN	255

/* Trigger related defines */
#define TRIGGER_DESCRIPTION_LEN		255
#define TRIGGER_DESCRIPTION_LEN_MAX	TRIGGER_DESCRIPTION_LEN+1
#define TRIGGER_EXPRESSION_LEN		255
#define TRIGGER_EXPRESSION_LEN_MAX	TRIGGER_EXPRESSION_LEN+1
#define TRIGGER_URL_LEN			255
#define TRIGGER_URL_LEN_MAX		TRIGGER_URL_LEN+1
#define TRIGGER_COMMENTS_LEN		4096
#define TRIGGER_COMMENTS_LEN_MAX	TRIGGER_URL_LEN+1

#define CONDITION_VALUE_LEN		255
#define CONDITION_VALUE_LEN_MAX		CONDITION_VALUE_LEN+1

#define HOST_HOST_LEN			64
#define HOST_HOST_LEN_MAX		HOST_HOST_LEN+1
#define HOST_IP_LEN			15
#define HOST_IP_LEN_MAX			HOST_IP_LEN+1
#define HOST_ERROR_LEN			128
#define HOST_ERROR_LEN_MAX		HOST_ERROR_LEN+1
#define HOST_ERROR_LEN			128
#define HOST_ERROR_LEN_MAX		HOST_ERROR_LEN+1

#define ITEM_KEY_LEN			64
#define ITEM_KEY_LEN_MAX		ITEM_KEY_LEN+1

#define GRAPH_NAME_LEN			128
#define GRAPH_NAME_LEN_MAX		GRAPH_NAME_LEN+1

#define GRAPH_ITEM_COLOR_LEN		32
#define GRAPH_ITEM_COLOR_LEN_MAX	GRAPH_ITEM_COLOR_LEN+1

#define ACTION_SUBJECT_LEN		255
#define ACTION_SUBJECT_LEN_MAX		ACTION_SUBJECT_LEN+1

#define ZBX_SQL_ITEM_SELECT	"i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.errors_from,i.snmp_port,i.delta,i.prevorgvalue,i.lastclock,i.units,i.multiplier,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,h.available,i.status,i.trapper_hosts,i.logtimefmt,i.valuemapid from hosts h, items i"

DB_HOST
{
	int     hostid;
	char    host[HOST_HOST_LEN_MAX];
	int     useip;
	char    ip[HOST_IP_LEN_MAX];
	int	port;
	int	status;
	int	disable_until;
	int	errors_from;
	char	error[HOST_ERROR_LEN_MAX];
	int	available;
};

DB_GRAPH
{
	int	graphid;
	char	name[GRAPH_NAME_LEN_MAX];
	int	width;
	int	height;
	int	yaxistype;
	double	yaxismin;
	double	yaxismax;
};

DB_GRAPH_ITEM
{
	int	gitemid;
	int	graphid;
	int	itemid;
	int	drawtype;
	int	sortorder;
	char	color[GRAPH_ITEM_COLOR_LEN_MAX];
};

DB_ITEM
{
	int	itemid;
	int	hostid;
	int	type;
	int	status;
	char	*description;
	char	key[ITEM_KEY_LEN_MAX];
	char	*host;
	int	host_status;
	int	host_available;
	int	host_errors_from;
	int	useip;
	char	*ip;
	char	*shortname;
	char	*snmp_community;
	char	*snmp_oid;
	int	snmp_port;
	char	*trapper_hosts;
	int     port;
	int     delay;
	int     history;
	int	trends;
	double	prevorgvalue;
	int	prevorgvalue_null;
	double	lastvalue;
	int	lastclock;
	char	*lastvalue_str;
	int     lastvalue_null;
	double	prevvalue;
	char	*prevvalue_str;
	int     prevvalue_null;
	time_t  lastcheck;
	time_t	nextcheck;
	int	value_type;
	int	delta;
	int	multiplier;
	char	*units;

	char	*snmpv3_securityname;
	int	snmpv3_securitylevel;
	char	*snmpv3_authpassphrase;
	char	*snmpv3_privpassphrase;

	char	*formula;
	int	lastlogsize;
	int	timestamp;
	int	eventlog_severity;
	char	*eventlog_source;

	char	*logtimefmt;
	int	valuemapid;
};
 
DB_FUNCTION
{
	int     functionid;
	int     itemid;
	int     triggerid;
	double  lastvalue;
	int	lastvalue_null;
	char    *function;
/*	int     parameter;*/
	char	*parameter;
};

DB_MEDIA
{
	int	mediaid;
/*	char	*type;*/
	int	mediatypeid;
	char	*sendto;
	char	*period;
	int	active;
	int	severity;
};

DB_MEDIATYPE
{
	int	mediatypeid;
	int	type;
	char	*description;
	char	*smtp_server;
	char	*smtp_helo;
	char	*smtp_email;
	char	*exec_path;
	char	*gsm_modem;
};

DB_TRIGGER
{
	int	triggerid;
	char	expression[TRIGGER_EXPRESSION_LEN_MAX];
	char	description[TRIGGER_DESCRIPTION_LEN_MAX];
	char	url[TRIGGER_URL_LEN_MAX];
	char	comments[TRIGGER_COMMENTS_LEN_MAX];
	int	status;
	int	value;
	int	prevvalue;
	int	priority;
};

DB_ACTION
{
	int	actionid;
	int	actiontype;
	int	userid;
/*	int	delay;*/
	int	lastcheck;
	int	recipient;
	char	subject[ACTION_SUBJECT_LEN_MAX];	/* don't use pointer, cose sizeof is used */
	char	*message;
	int	message_len;
	int	maxrepeats;
	int	repeatdelay;
	char	scripts[MAX_STRING_LEN];
};

DB_CONDITION
{
	int	conditionid;
	int	actionid;
	int	conditiontype;
	int	operator;
	char	*value;
};

DB_ALERT
{
	int	alertid;
	int 	actionid;
	int 	clock;
	int	mediatypeid;
	char	*sendto;
	char	*subject;
	char	*message;
	int	status;
	int	retries;
	int	delay;
};

DB_HOUSEKEEPER
{
	int	housekeeperid;
	char	*tablename;
	char	*field;
	int	value;
};


void    DBconnect(void);

void    DBclose(void);
void    DBvacuum(void);

int	DBexecute( char *query );
/*long	DBaffected_rows();*/

DB_RESULT	DBselect(char *query);
DB_RESULT	DBselectN(char *query, int n);
DB_ROW	DBfetch(DB_RESULT result);
/*char	*DBget_field(DB_RESULT result, int rownum, int fieldnum);*/
/*int	DBnum_rows(DB_RESULT result);*/
int	DBinsert_id(int exec_result, const char *table, const char *field);
int	DBis_null(char *field);

int	DBget_function_result(double *result,char *functionid);
void	DBupdate_host_availability(int hostid,int available,int clock,char *error);
int	DBupdate_item_status_to_notsupported(int itemid, char *error);
int	DBadd_trend(int itemid, double value, int clock);
int	DBadd_history(int itemid, double value, int clock);
int	DBadd_history_log(int itemid, char *value, int clock, int timestamp, char *source, int severity);
int	DBadd_history_str(int itemid, char *value, int clock);
int	DBadd_history_text(int itemid, char *value, int clock);
int	DBadd_history_uint(int itemid, zbx_uint64_t value, int clock);
int	DBadd_service_alarm(int serviceid,int status,int clock);
int	DBadd_alert(int actionid, int triggerid, int userid, int mediatypeid, char *sendto, char *subject, char *message, int maxrepeats, int repeatdelay);
void	DBupdate_triggers_status_after_restart(void);
int	DBget_prev_trigger_value(int triggerid);
/*int	DBupdate_trigger_value(int triggerid,int value,int clock);*/
int     DBupdate_trigger_value(DB_TRIGGER *trigger, int new_value, int now, char *reason);

int	DBget_items_count(void);
int	DBget_items_unsupported_count(void);
int	DBget_history_count(void);
int	DBget_history_str_count(void);
int	DBget_trends_count(void);
int	DBget_triggers_count(void);
int	DBget_queue_count(void);

void    DBescape_string(char *from, char *to, int maxlen);
void    DBget_item_from_db(DB_ITEM *item,DB_ROW row);

int	DBadd_host(char *server, int port, int status, int useip, char *ip, int disable_until, int available);
int	DBhost_exists(char *server);
int	DBget_host_by_hostid(int hostid,DB_HOST *host);
int	DBsync_host_with_templates(int hostid);
int	DBsync_host_with_template(int hostid,int templateid,int items,int triggers,int graphs);
int	DBadd_templates_to_host(int hostid,int host_templateid);

int	DBadd_template_linkage(int hostid,int templateid,int items,int triggers,int graphs);

int	DBget_item_by_itemid(int itemid,DB_ITEM *item);
int	DBadd_item_to_linked_hosts(int itemid, int hostid);
int	DBadd_item(char *description, char *key, int hostid, int delay, int history, int status, int type, char *snmp_community, char *snmp_oid,int value_type,char *trapper_hosts,int snmp_port,char *units,int multiplier,int delta, char *snmpv3_securityname,int snmpv3_securitylevel,char *snmpv3_authpassphrase,char *snmpv3_privpassphrase,char *formula,int trends,char *logtimefmt);

int	DBadd_action_to_linked_hosts(int actionid,int hostid);

int	DBget_trigger_by_triggerid(int triggerid,DB_TRIGGER *trigger);
int	DBadd_trigger_to_linked_hosts(int triggerid,int hostid);
void	DBdelete_triggers_by_itemid(int itemid);
void	DBdelete_sysmaps_hosts_by_hostid(int hostid);

int	DBadd_graph(char *name, int width, int height, int yaxistype, double yaxismin, double yaxismax);
int	DBget_graph_item_by_gitemid(int gitemid, DB_GRAPH_ITEM *graph_item);
int	DBget_graph_by_graphid(int graphid, DB_GRAPH *graph);
int	DBadd_graph_item_to_linked_hosts(int gitemid,int hostid);
#endif
