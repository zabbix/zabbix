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
	#include "mysql.h"
	#include "errmsg.h"
	#include "mysqld_error.h"
#define	DB_HANDLE	MYSQL
#endif

#ifdef HAVE_PGSQL
	#include "libpq-fe.h"
#endif

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
#define DB_ALERT	struct alert_type
#define DB_FUNCTION	struct function_type
#define DB_MEDIA	struct media_type
#define DB_MEDIATYPE	struct mediatype_type
#define DB_ESCALATION_RULE	struct escalation_rule_type
#define DB_ESCALATION_LOG	struct escalation_log_type

#ifdef HAVE_MYSQL
	#define	DB_RESULT	MYSQL_RES
	#define	DBfree_result	mysql_free_result
#endif

#ifdef HAVE_PGSQL
	#define	DB_RESULT	PGresult
	#define	DBfree_result	PQclear
#endif

#define	MAX_HOST_HOST_LEN	64

DB_HOST
{
	int     hostid;
	char    *host;
	int     useip;
	char    *ip;
};

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

DB_ITEM
{
	int	itemid;
	int	hostid;
	int	type;
	int	status;
	char	*description;
	char	*key;
	char	*host;
	int	host_status;
	int	host_available;
	int	host_network_errors;
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
};

DB_TRIGGER
{
	int	triggerid;
	char	expression[TRIGGER_EXPRESSION_LEN_MAX];
	char	description[TRIGGER_DESCRIPTION_LEN_MAX];
	char	url[TRIGGER_URL_LEN_MAX];
	int	status;
	int	value;
	int	prevvalue;
	int	priority;
};

DB_ACTION
{
	int     actionid;
	int     triggerid;
	int     userid;
	int     scope;
	int     severity;
	int     good;
	int     delay;
	int     lastcheck;
	int	recipient;
	char    subject[MAX_STRING_LEN];
	char    message[MAX_STRING_LEN];
};

DB_ALERT
{
	int	alertid;
	int 	actionid;
	int 	clock;
/*	char	*type;*/
	int	mediatypeid;
	char	*sendto;
	char	*subject;
	char	*message;
	int	status;
	int	retries;
};

DB_ESCALATION_RULE
{
	int	escalationruleid;
	int	escalationid;
	int	level;
	char	*period;
	int	delay;
	int	actiontype;
};

DB_ESCALATION_LOG
{
	int 	escalationlogid;
	int 	triggerid;
	int	alarmid;
	int	escalationid;
	int	level;
	int	adminlevel;
	int	nextcheck;
	int	status;
};

void    DBconnect(void);

void    DBclose(void);
void    DBvacuum(void);

int	DBexecute( char *query );
long	DBaffected_rows();

DB_RESULT	*DBselect(char *query);
char		*DBget_field(DB_RESULT *result, int rownum, int fieldnum);
int		DBnum_rows(DB_RESULT *result);

int	DBget_function_result(double *result,char *functionid);
void	DBupdate_host_availability(int hostid,int available,int clock,char *error);
int	DBupdate_item_status_to_notsupported(int itemid, char *error);
int	DBadd_trend(int itemid, double value, int clock);
int	DBadd_history(int itemid, double value, int clock);
int	DBadd_history_log(int itemid, char *value, int clock, int timestamp, char *source, int severity);
int	DBadd_history_str(int itemid, char *value, int clock);
int	DBadd_service_alarm(int serviceid,int status,int clock);
int	DBadd_alert(int actionid, int mediatypeid, char *sendto, char *subject, char *message);
void	DBupdate_triggers_status_after_restart(void);
int	DBget_prev_trigger_value(int triggerid);
/*int	DBupdate_trigger_value(int triggerid,int value,int clock);*/
int     DBupdate_trigger_value(DB_TRIGGER *trigger, int new_value, int now, char *reason);
int     DBget_default_escalation_id();

int	DBget_items_count(void);
int	DBget_items_unsupported_count(void);
int	DBget_history_count(void);
int	DBget_history_str_count(void);
int	DBget_trends_count(void);
int	DBget_triggers_count(void);
int	DBget_queue_count(void);

void    DBescape_string(char *from, char *to, int maxlen);
void    DBget_item_from_db(DB_ITEM *item,DB_RESULT *result, int row);

int	DBget_trigger_by_triggerid(int triggerid, DB_TRIGGER *trigger);

int	DBadd_new_host(char *server, int port, int status, int useip, char *ip, int disable_until, int available);
int	DBhost_exists(char *server);
#endif
