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

#include "zbxmacros.h"
#include "common.h"
#include "zbxdb.h"
#include "dbschema.h"

extern char	*CONFIG_DBHOST;
extern char	*CONFIG_DBNAME;
extern char	*CONFIG_DBSCHEMA;
extern char	*CONFIG_DBUSER;
extern char	*CONFIG_DBPASSWORD;
extern char	*CONFIG_DBSOCKET;
extern int	CONFIG_DBPORT;
extern int	CONFIG_NODEID;
extern int	CONFIG_MASTER_NODEID;
extern int	CONFIG_HISTSYNCER_FORKS;
extern int	CONFIG_REFRESH_UNSUPPORTED;
extern int	CONFIG_UNAVAILABLE_DELAY;
extern int	CONFIG_LOG_SLOW_QUERIES;

typedef enum {
	GRAPH_TYPE_NORMAL = 0,
	GRAPH_TYPE_STACKED = 1
} zbx_graph_types;

typedef enum {
	SCREEN_RESOURCE_GRAPH = 0,
	SCREEN_RESOURCE_SIMPLE_GRAPH,
	SCREEN_RESOURCE_MAP,
	SCREEN_RESOURCE_PLAIN_TEXT,
	SCREEN_RESOURCE_HOSTS_INFO,
	SCREEN_RESOURCE_TRIGGERS_INFO,
	SCREEN_RESOURCE_SERVER_INFO,
	SCREEN_RESOURCE_CLOCK,
	SCREEN_RESOURCE_SCREEN,
	SCREEN_RESOURCE_TRIGGERS_OVERVIEW,
	SCREEN_RESOURCE_DATA_OVERVIEW,
	SCREEN_RESOURCE_URL,
	SCREEN_RESOURCE_ACTIONS,
	SCREEN_RESOURCE_EVENTS,
	SCREEN_RESOURCE_HOSTGROUP_TRIGGERS,
	SCREEN_RESOURCE_SYSTEM_STATUS,
	SCREEN_RESOURCE_HOST_TRIGGERS
} zbx_screen_resources;

typedef enum {
	CALC_FNC_MIN = 1,
	CALC_FNC_AVG = 2,
	CALC_FNC_MAX = 4,
	CALC_FNC_ALL = 7
} zbx_graph_item_calc_function;

typedef enum {
	GRAPH_ITEM_SIMPLE = 0,
	GRAPH_ITEM_AGGREGATED = 1
} zbx_graph_item_type;

#define ZBX_DB_CONNECT_NORMAL	0
#define ZBX_DB_CONNECT_EXIT	1
#define ZBX_DB_CONNECT_ONCE	2

/* Trigger related defines */
#define TRIGGER_DESCRIPTION_LEN		1020
#define TRIGGER_DESCRIPTION_LEN_MAX	TRIGGER_DESCRIPTION_LEN+1
#define TRIGGER_EXPRESSION_LEN		255
#define TRIGGER_EXPRESSION_LEN_MAX	TRIGGER_EXPRESSION_LEN+1
#define TRIGGER_ERROR_LEN		128
#define TRIGGER_ERROR_LEN_MAX		TRIGGER_ERROR_LEN+1

#define HOST_HOST_LEN			MAX_ZBX_HOSTNAME_LEN
#define HOST_HOST_LEN_MAX		HOST_HOST_LEN+1
#define HOST_DNS_LEN			64
#define HOST_DNS_LEN_MAX		HOST_DNS_LEN+1
#define HOST_IP_LEN			39
#define HOST_IP_LEN_MAX			HOST_IP_LEN+1
#define HOST_ADDR_LEN			64	/* MAX(HOST_DNS_LEN,HOST_IP_LEN) */
#define HOST_ADDR_LEN_MAX		HOST_ADDR_LEN+1
#define HOST_ERROR_LEN			128
#define HOST_ERROR_LEN_MAX		HOST_ERROR_LEN+1
#define HOST_IPMI_USERNAME_LEN		16
#define HOST_IPMI_USERNAME_LEN_MAX	HOST_IPMI_USERNAME_LEN+1
#define HOST_IPMI_PASSWORD_LEN		20
#define HOST_IPMI_PASSWORD_LEN_MAX	HOST_IPMI_PASSWORD_LEN+1

#define ITEM_KEY_LEN			1020
#define ITEM_KEY_LEN_MAX		ITEM_KEY_LEN+1
#define ITEM_SNMP_COMMUNITY_LEN		64
#define ITEM_SNMP_COMMUNITY_LEN_MAX	ITEM_SNMP_COMMUNITY_LEN+1
#define ITEM_SNMP_OID_LEN		255
#define ITEM_SNMP_OID_LEN_MAX		ITEM_SNMP_OID_LEN+1
#define ITEM_LASTVALUE_LEN		255
#define ITEM_LASTVALUE_LEN_MAX		ITEM_LASTVALUE_LEN+1
#define ITEM_ERROR_LEN			128
#define ITEM_ERROR_LEN_MAX		ITEM_ERROR_LEN+1
#define ITEM_TRAPPER_HOSTS_LEN		255
#define ITEM_TRAPPER_HOSTS_LEN_MAX	ITEM_TRAPPER_HOSTS_LEN+1
#define ITEM_UNITS_LEN			10
#define ITEM_UNITS_LEN_MAX		ITEM_UNITS_LEN+1
#define ITEM_SNMPV3_SECURITYNAME_LEN		64
#define ITEM_SNMPV3_SECURITYNAME_LEN_MAX	ITEM_SNMPV3_SECURITYNAME_LEN+1
#define ITEM_SNMPV3_AUTHPASSPHRASE_LEN		64
#define ITEM_SNMPV3_AUTHPASSPHRASE_LEN_MAX	ITEM_SNMPV3_AUTHPASSPHRASE_LEN+1
#define ITEM_SNMPV3_PRIVPASSPHRASE_LEN		64
#define ITEM_SNMPV3_PRIVPASSPHRASE_LEN_MAX	ITEM_SNMPV3_PRIVPASSPHRASE_LEN+1
#define ITEM_FORMULA_LEN		255
#define ITEM_FORMULA_LEN_MAX		ITEM_FORMULA_LEN+1
#define ITEM_LOGTIMEFMT_LEN		64
#define ITEM_LOGTIMEFMT_LEN_MAX		ITEM_LOGTIMEFMT_LEN+1
#define ITEM_DELAY_FLEX_LEN		255
#define ITEM_DELAY_FLEX_LEN_MAX		ITEM_DELAY_FLEX_LEN+1
#define ITEM_IPMI_SENSOR_LEN		128
#define ITEM_IPMI_SENSOR_LEN_MAX	ITEM_IPMI_SENSOR_LEN+1
#define ITEM_PARAMS_LEN			2048
#define ITEM_PARAMS_LEN_MAX		ITEM_PARAMS_LEN+1
#define ITEM_USERNAME_LEN		64
#define ITEM_USERNAME_LEN_MAX		ITEM_USERNAME_LEN+1
#define ITEM_PASSWORD_LEN		64
#define ITEM_PASSWORD_LEN_MAX		ITEM_PASSWORD_LEN+1
#define ITEM_PUBLICKEY_LEN		64
#define ITEM_PUBLICKEY_LEN_MAX		ITEM_PUBLICKEY_LEN+1
#define ITEM_PRIVATEKEY_LEN		64
#define ITEM_PRIVATEKEY_LEN_MAX		ITEM_PRIVATEKEY_LEN+1

#define FUNCTION_FUNCTION_LEN		12
#define FUNCTION_FUNCTION_LEN_MAX	FUNCTION_FUNCTION_LEN+1
#define FUNCTION_PARAMETER_LEN		255
#define FUNCTION_PARAMETER_LEN_MAX	FUNCTION_PARAMETER_LEN+1

#define HISTORY_STR_VALUE_LEN		255
#ifdef HAVE_IBM_DB2
#	define HISTORY_TEXT_VALUE_LEN	2048
#	define HISTORY_LOG_VALUE_LEN	2048
#else
#	define HISTORY_TEXT_VALUE_LEN	65535
#	define HISTORY_LOG_VALUE_LEN	65535
#endif

#define HISTORY_LOG_SOURCE_LEN		64
#define HISTORY_LOG_SOURCE_LEN_MAX	HISTORY_LOG_SOURCE_LEN+1

#define ALERT_SENDTO_LEN		100
#define ALERT_SENDTO_LEN_MAX		ALERT_SENDTO_LEN+1
#define ALERT_SUBJECT_LEN		255
#define ALERT_SUBJECT_LEN_MAX		ALERT_SUBJECT_LEN+1
#define ALERT_ERROR_LEN			128
#define ALERT_ERROR_LEN_MAX		ALERT_ERROR_LEN+1

#define GRAPH_ITEM_COLOR_LEN		6
#define GRAPH_ITEM_COLOR_LEN_MAX	GRAPH_ITEM_COLOR_LEN+1

#define DHOST_IP_LEN			39
#define DHOST_IP_LEN_MAX		DHOST_IP_LEN+1

#define DSERVICE_KEY_LEN		255
#define DSERVICE_KEY_LEN_MAX		DSERVICE_KEY_LEN+1
#define DSERVICE_VALUE_LEN		255
#define DSERVICE_VALUE_LEN_MAX		DSERVICE_VALUE_LEN+1

#define HTTPTEST_HTTP_USER_LEN		64
#define HTTPTEST_HTTP_USER_LEN_MAX	HTTPTEST_HTTP_USER_LEN+1
#define HTTPTEST_HTTP_PASSWORD_LEN	64
#define HTTPTEST_HTTP_PASSWORD_LEN_MAX	HTTPTEST_HTTP_PASSWORD_LEN+1

#define PROXY_DHISTORY_IP_LEN		39
#define PROXY_DHISTORY_IP_LEN_MAX	PROXY_DHISTORY_IP_LEN+1
#define PROXY_DHISTORY_KEY_LEN		255
#define PROXY_DHISTORY_KEY_LEN_MAX	PROXY_DHISTORY_KEY_LEN+1
#define PROXY_DHISTORY_VALUE_LEN	255
#define PROXY_DHISTORY_VALUE_LEN_MAX	PROXY_DHISTORY_VALUE_LEN+1

#define HTTPTEST_ERROR_LEN		255
#define HTTPTEST_ERROR_LEN_MAX		HTTPTEST_ERROR_LEN+1

#define HTTPSTEP_STATUS_LEN		255
#define HTTPSTEP_STATUS_LEN_MAX		HTTPSTEP_STATUS_LEN+1

#define HTTPSTEP_REQUIRED_LEN		255
#define HTTPSTEP_REQUIRED_LEN_MAX	HTTPSTEP_REQUIRED_LEN+1

#define ZBX_SQL_ITEM_FIELDS	"i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.type,h.useip,"	\
				"h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,i.value_type,i.delta,"	\
				"i.prevorgvalue,i.lastclock,i.units,i.multiplier,i.formula,i.status,"	\
				"i.valuemapid,h.dns,i.trends,i.lastlogsize,i.data_type,i.mtime"
#define ZBX_SQL_ITEM_TABLES	"hosts h,items i"
#define ZBX_SQL_TIME_FUNCTIONS	"'nodata','date','dayofmonth','dayofweek','time','now'"
#define ZBX_SQL_ITEM_FIELDS_NUM	27
#define ZBX_SQL_ITEM_SELECT	ZBX_SQL_ITEM_FIELDS " from " ZBX_SQL_ITEM_TABLES

#ifdef HAVE_ORACLE
#define ZBX_SQL_STRCMP		"%s%s%s"
#define ZBX_SQL_STRVAL_EQ(str)	str[0] != '\0' ? "='"  : "",			\
				str[0] != '\0' ? str   : " is null",		\
				str[0] != '\0' ? "'"   : ""
#define	ZBX_SQL_STRVAL_NE(str)	str[0] != '\0' ? "<>'" : "",			\
				str[0] != '\0' ? str   : " is not null",	\
				str[0] != '\0' ? "'"   : ""
#else
#define ZBX_SQL_STRCMP		"%s'%s'"
#define ZBX_SQL_STRVAL_EQ(str)	"=", str
#define ZBX_SQL_STRVAL_NE(str)	"<>", str
#endif

#define ZBX_MAX_SQL_LEN		65535

typedef struct
{
	zbx_uint64_t	druleid;
	char		*iprange;
	char		*name;
	zbx_uint64_t	unique_dcheckid;
}
DB_DRULE;

typedef struct
{
	zbx_uint64_t	dcheckid;
	int		type;
	char		*ports;
	char		*key_;
	char		*snmp_community;
	char		*snmpv3_securityname;
	int		snmpv3_securitylevel;
	char		*snmpv3_authpassphrase;
	char		*snmpv3_privpassphrase;
}
DB_DCHECK;

typedef struct
{
	zbx_uint64_t	dhostid;
	int		status;
	int		lastup;
	int		lastdown;
}
DB_DHOST;

typedef struct
{
	zbx_uint64_t	dserviceid;
	int		status;
	int		lastup;
	int		lastdown;
	char		value[DSERVICE_VALUE_LEN_MAX];
}
DB_DSERVICE;

typedef struct
{
	zbx_uint64_t	triggerid;
	char		description[TRIGGER_DESCRIPTION_LEN_MAX];
	char		expression[TRIGGER_EXPRESSION_LEN_MAX];
	char		*url;
	char		*comments;
	unsigned char	priority;
	unsigned char	type;
}
DB_TRIGGER;

typedef struct
{
	zbx_uint64_t	triggerid;
	char		*expression;
	char		*error;
	char		*new_error;
	int		lastchange;
	int		value;
	int		new_value;
	unsigned char	type;
	unsigned char	add_event;
}
DB_TRIGGER_UPDATE;

typedef struct
{
	DB_TRIGGER	trigger;
	zbx_uint64_t	eventid;
	zbx_uint64_t	objectid;
	zbx_uint64_t	ack_eventid;
	int		source;
	int		object;
	int		clock;
	int		value;
	int		acknowledged;
	int		skip_actions;
}
DB_EVENT;

typedef union
{
	double		dbl;
	zbx_uint64_t	ui64;
	char		*str;
	char		*err;
}
history_value_t;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		hostid;
	zbx_item_type_t		type;
	zbx_item_data_type_t	data_type;
	zbx_item_status_t	status;
	char			*description;
	char			*key;
	char			*key_orig;
	char			*host_name;
	char			*host_ip;
	char			*host_dns;
	int			useip;
	int			port;
	int			delay;
	int			history;
	int			trends;
	int			lastclock;
	char			*lastvalue[2];
	int			prevorgvalue_null;
	history_value_t		prevorgvalue;
	time_t 			lastcheck;
	zbx_item_value_type_t	value_type;
	int			delta;
	int			multiplier;
	char			*units;

	char			*formula;
	int			lastlogsize;
	int			mtime;

	zbx_uint64_t		valuemapid;

	char			*error;

	char			*h_lastvalue[2];
	char			*h_lasteventid;
	char			*h_lastsource;
	char			*h_lastseverity;
}
DB_ITEM;

typedef struct
{
	zbx_uint64_t		mediatypeid;
	zbx_media_type_t	type;
	char	*description;
	char	*smtp_server;
	char	*smtp_helo;
	char	*smtp_email;
	char	*exec_path;
	char	*gsm_modem;
	char	*username;
	char	*passwd;
}
DB_MEDIATYPE;

typedef struct
{
	zbx_uint64_t	actionid;
	char		*shortdata;
	char		*longdata;
	int		esc_period;
	unsigned char	eventsource;
	unsigned char	recovery_msg;
}
DB_ACTION;

typedef struct
{
	zbx_uint64_t	operationid;
	zbx_uint64_t	actionid;
	int		operationtype;
	int		object;
	zbx_uint64_t	objectid;
	char		*shortdata;
	char		*longdata;
	int		esc_period;
	int		default_msg;
	int		evaltype;
}
DB_OPERATION;

typedef struct
{
	zbx_uint64_t	conditionid;
	zbx_uint64_t	actionid;
	zbx_condition_type_t	conditiontype;
	zbx_condition_op_t	operator;
	char		*value;
}
DB_CONDITION;

typedef struct
{
	zbx_uint64_t	alertid;
	zbx_uint64_t 	actionid;
	int		clock;
	zbx_uint64_t	mediatypeid;
	char		*sendto;
	char		*subject;
	char		*message;
	zbx_alert_status_t	status;
	int		retries;
}
DB_ALERT;

typedef struct
{
	zbx_uint64_t	housekeeperid;
	char		*tablename;
	char		*field;
	zbx_uint64_t	value;
}
DB_HOUSEKEEPER;

typedef struct
{
	zbx_uint64_t	httptestid;
	char		*name;
	zbx_uint64_t	applicationid;
	int		nextcheck;
	int		status;
	char		*macros;
	char		*agent;
	double		time;
	int		authentication;
	char		*http_user;
	char		*http_password;
}
DB_HTTPTEST;

typedef struct
{
	zbx_uint64_t	httpstepid;
	zbx_uint64_t	httptestid;
	int		no;
	char		*name;
	char		url[MAX_STRING_LEN];	/* excessive length is required to support macros */
	int		timeout;
	char		posts[MAX_STRING_LEN];
	char		required[HTTPSTEP_REQUIRED_LEN_MAX];
	char		status_codes[HTTPSTEP_STATUS_LEN_MAX];
}
DB_HTTPSTEP;

typedef struct
{
	zbx_uint64_t	httpstepitemid;
	zbx_uint64_t	httpstepid;
	zbx_uint64_t	itemid;
	zbx_httpitem_type_t	type;
}
DB_HTTPSTEPITEM;

typedef struct
{
	zbx_uint64_t	httptestitemid;
	zbx_uint64_t	httptestid;
	zbx_uint64_t	itemid;
	zbx_httpitem_type_t	type;
}
DB_HTTPTESTITEM;

typedef struct
{
	zbx_uint64_t		escalationid;
	zbx_uint64_t		actionid;
	zbx_uint64_t		triggerid;
	zbx_uint64_t		eventid;
	zbx_uint64_t		r_eventid;
	int			esc_step;
	zbx_escalation_status_t	status;
	int			nextcheck;
}
DB_ESCALATION;

#define DB_NODE			"%s"
#define DBnode_local(fieldid)	DBnode(fieldid, CONFIG_NODEID)
const char	*DBnode(const char *fieldid, int nodeid);

int	DBconnect(int flag);
void	DBinit();

void	DBclose();
void	DBvacuum();

#ifdef HAVE___VA_ARGS__
#	define DBexecute(fmt, ...) __zbx_DBexecute(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define DBexecute __zbx_DBexecute
#endif
int	__zbx_DBexecute(const char *fmt, ...);

#ifdef HAVE___VA_ARGS__
#	define DBselect_once(fmt, ...)	__zbx_DBselect_once(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#	define DBselect(fmt, ...)	__zbx_DBselect(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define DBselect_once	__zbx_DBselect_once
#	define DBselect		__zbx_DBselect
#endif
DB_RESULT	__zbx_DBselect_once(const char *fmt, ...);
DB_RESULT	__zbx_DBselect(const char *fmt, ...);

DB_RESULT	DBselectN(const char *query, int n);
DB_ROW		DBfetch(DB_RESULT result);
int		DBis_null(const char *field);
void		DBbegin();
void		DBcommit();
void		DBrollback();

const ZBX_TABLE	*DBget_table(const char *tablename);
const ZBX_FIELD	*DBget_field(const ZBX_TABLE *table, const char *fieldname);
#define DBget_maxid(table)	DBget_maxid_num(table, 1)
zbx_uint64_t	DBget_maxid_num(const char *tablename, int num);

int	DBupdate_item_status_to_notsupported(DB_ITEM *item, int clock, const char *error);
int	DBadd_service_alarm(zbx_uint64_t serviceid, int status, int clock);
int	DBstart_escalation(zbx_uint64_t actionid, zbx_uint64_t triggerid, zbx_uint64_t eventid);
int	DBstop_escalation(zbx_uint64_t actionid, zbx_uint64_t triggerid, zbx_uint64_t eventid);
int	DBremove_escalation(zbx_uint64_t escalationid);
void	DBupdate_triggers_status_after_restart();
int	DBget_trigger_update_sql(char **sql, int *sql_alloc, int *sql_offset, zbx_uint64_t triggerid,
		unsigned char type, int value, const char *error, int new_value, const char *new_error, int lastchange,
		unsigned char *add_event);

int	DBget_row_count(const char *table_name);
int	DBget_items_unsupported_count();
int	DBget_queue_count(int from, int to);
double	DBget_requiredperformance();
int	DBget_proxy_lastaccess(const char *hostname, int *lastaccess, char **error);

char	*DBdyn_escape_string(const char *src);
char	*DBdyn_escape_string_len(const char *src, size_t max_src_len);

#define ZBX_SQL_LIKE_ESCAPE_CHAR '!'
char	*DBdyn_escape_like_pattern(const char *src);

void    DBget_item_from_db(DB_ITEM *item, DB_ROW row);
void	DBfree_item_from_db(DB_ITEM *item);

zbx_uint64_t	DBadd_host(char *server, int port, int status, int useip, char *ip, int disable_until, int available);
int	DBhost_exists(char *server);
int	DBadd_templates_to_host(int hostid,int host_templateid);

int	DBadd_template_linkage(int hostid,int templateid,int items,int triggers,int graphs);

int	DBget_item_by_itemid(int itemid,DB_ITEM *item);

int	DBadd_trigger_to_linked_hosts(int triggerid,int hostid);
void	DBdelete_sysmaps_hosts_by_hostid(zbx_uint64_t hostid);

int	DBadd_graph_item_to_linked_hosts(int gitemid,int hostid);

int	DBdelete_template_elements(zbx_uint64_t hostid, zbx_uint64_t templateid);
int	DBcopy_template_elements(zbx_uint64_t hostid, zbx_uint64_t templateid);
int	DBdelete_host(zbx_uint64_t hostid);
void	DBupdate_services(zbx_uint64_t triggerid, int status, int clock);

/* History related functions */
int	DBadd_trend(zbx_uint64_t itemid, double value, int clock);
int	DBadd_trend_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock);

void	DBadd_condition_alloc(char **sql, int *sql_alloc, int *sql_offset, const char *fieldname, const zbx_uint64_t *values, const int num);

const char	*zbx_host_string(zbx_uint64_t hostid);
const char	*zbx_host_key_string(zbx_uint64_t itemid);
const char	*zbx_host_key_string_by_item(DB_ITEM *item);
const char	*zbx_user_string(zbx_uint64_t userid);

double	DBmultiply_value_float(DB_ITEM *item, double value);
zbx_uint64_t	DBmultiply_value_uint64(DB_ITEM *item, zbx_uint64_t value);

void	DBregister_host(zbx_uint64_t proxy_hostid, const char *host, int now);
void	DBproxy_register_host(const char *host);
void	DBexecute_overflowed_sql(char **sql, int *sql_allocated, int *sql_offset);
char	*DBget_unique_hostname_by_sample(char *host_name_sample);

#define ZBX_DB_GET_HIST_MIN	0
#define ZBX_DB_GET_HIST_AVG	1
#define ZBX_DB_GET_HIST_MAX	2
#define ZBX_DB_GET_HIST_SUM	3
#define ZBX_DB_GET_HIST_COUNT	4
#define ZBX_DB_GET_HIST_DELTA	5
#define ZBX_DB_GET_HIST_VALUE	6
char	**DBget_history(zbx_uint64_t itemid, unsigned char value_type, int function, int clock_from, int clock_to, const char *field_name, int last_n);
void	DBfree_history(char **value);

int	DBtxn_status();
int	DBtxn_ongoing();

#endif
