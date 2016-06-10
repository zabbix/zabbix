/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_DB_H
#define ZABBIX_DB_H

#include "common.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "dbschema.h"

extern char	*CONFIG_DBHOST;
extern char	*CONFIG_DBNAME;
extern char	*CONFIG_DBSCHEMA;
extern char	*CONFIG_DBUSER;
extern char	*CONFIG_DBPASSWORD;
extern char	*CONFIG_DBSOCKET;
extern int	CONFIG_DBPORT;
extern int	CONFIG_HISTSYNCER_FORKS;
extern int	CONFIG_UNAVAILABLE_DELAY;

typedef enum
{
	GRAPH_TYPE_NORMAL = 0,
	GRAPH_TYPE_STACKED = 1
}
zbx_graph_types;

typedef enum
{
	CALC_FNC_MIN = 1,
	CALC_FNC_AVG = 2,
	CALC_FNC_MAX = 4,
	CALC_FNC_ALL = 7
}
zbx_graph_item_calc_function;

typedef enum
{
	GRAPH_ITEM_SIMPLE = 0,
	GRAPH_ITEM_AGGREGATED = 1
}
zbx_graph_item_type;

struct	_DC_TRIGGER;

#define ZBX_DB_CONNECT_NORMAL	0
#define ZBX_DB_CONNECT_EXIT	1
#define ZBX_DB_CONNECT_ONCE	2

/* type of database */
#define ZBX_DB_UNKNOWN	0
#define ZBX_DB_SERVER	1
#define ZBX_DB_PROXY	2

#define TRIGGER_URL_LEN			255
#define TRIGGER_DESCRIPTION_LEN		255
#define TRIGGER_EXPRESSION_LEN		2048
#define TRIGGER_EXPRESSION_LEN_MAX	(TRIGGER_EXPRESSION_LEN + 1)
#define TRIGGER_ERROR_LEN		128
#if defined(HAVE_IBM_DB2) || defined(HAVE_ORACLE)
#	define TRIGGER_COMMENTS_LEN	2048
#else
#	define TRIGGER_COMMENTS_LEN	65535
#endif

#define TRIGGER_TAG_LEN			255
#define TRIGGER_TAG_VALUE_LEN		255

#define GROUP_NAME_LEN			64

#define HOST_HOST_LEN			MAX_ZBX_HOSTNAME_LEN
#define HOST_HOST_LEN_MAX		(HOST_HOST_LEN + 1)
#define HOST_NAME_LEN			128
#define HOST_ERROR_LEN			2048
#define HOST_ERROR_LEN_MAX		(HOST_ERROR_LEN + 1)
#define HOST_IPMI_USERNAME_LEN		16
#define HOST_IPMI_USERNAME_LEN_MAX	(HOST_IPMI_USERNAME_LEN + 1)
#define HOST_IPMI_PASSWORD_LEN		20
#define HOST_IPMI_PASSWORD_LEN_MAX	(HOST_IPMI_PASSWORD_LEN + 1)

#define INTERFACE_DNS_LEN		64
#define INTERFACE_DNS_LEN_MAX		(INTERFACE_DNS_LEN + 1)
#define INTERFACE_IP_LEN		64
#define INTERFACE_IP_LEN_MAX		(INTERFACE_IP_LEN + 1)
#define INTERFACE_ADDR_LEN		64	/* MAX(INTERFACE_DNS_LEN,INTERFACE_IP_LEN) */
#define INTERFACE_ADDR_LEN_MAX		(INTERFACE_ADDR_LEN + 1)
#define INTERFACE_PORT_LEN		64
#define INTERFACE_PORT_LEN_MAX		(INTERFACE_PORT_LEN + 1)

#define ITEM_NAME_LEN			255
#define ITEM_KEY_LEN			255
#define ITEM_UNITS_LEN			255
#define ITEM_SNMP_COMMUNITY_LEN		64
#define ITEM_SNMP_COMMUNITY_LEN_MAX	(ITEM_SNMP_COMMUNITY_LEN + 1)
#define ITEM_SNMP_OID_LEN		255
#define ITEM_SNMP_OID_LEN_MAX		(ITEM_SNMP_OID_LEN + 1)
#define ITEM_ERROR_LEN			2048
#define ITEM_ERROR_LEN_MAX		(ITEM_ERROR_LEN + 1)
#define ITEM_TRAPPER_HOSTS_LEN		255
#define ITEM_TRAPPER_HOSTS_LEN_MAX	(ITEM_TRAPPER_HOSTS_LEN + 1)
#define ITEM_SNMPV3_SECURITYNAME_LEN		64
#define ITEM_SNMPV3_SECURITYNAME_LEN_MAX	(ITEM_SNMPV3_SECURITYNAME_LEN + 1)
#define ITEM_SNMPV3_AUTHPASSPHRASE_LEN		64
#define ITEM_SNMPV3_AUTHPASSPHRASE_LEN_MAX	(ITEM_SNMPV3_AUTHPASSPHRASE_LEN + 1)
#define ITEM_SNMPV3_PRIVPASSPHRASE_LEN		64
#define ITEM_SNMPV3_PRIVPASSPHRASE_LEN_MAX	(ITEM_SNMPV3_PRIVPASSPHRASE_LEN + 1)
#define ITEM_SNMPV3_CONTEXTNAME_LEN		255
#define ITEM_SNMPV3_CONTEXTNAME_LEN_MAX		(ITEM_SNMPV3_CONTEXTNAME_LEN + 1)
#define ITEM_LOGTIMEFMT_LEN		64
#define ITEM_LOGTIMEFMT_LEN_MAX		(ITEM_LOGTIMEFMT_LEN + 1)
#define ITEM_DELAY_FLEX_LEN		255
#define ITEM_DELAY_FLEX_LEN_MAX		(ITEM_DELAY_FLEX_LEN + 1)
#define ITEM_IPMI_SENSOR_LEN		128
#define ITEM_IPMI_SENSOR_LEN_MAX	(ITEM_IPMI_SENSOR_LEN + 1)
#define ITEM_USERNAME_LEN		64
#define ITEM_USERNAME_LEN_MAX		(ITEM_USERNAME_LEN + 1)
#define ITEM_PASSWORD_LEN		64
#define ITEM_PASSWORD_LEN_MAX		(ITEM_PASSWORD_LEN + 1)
#define ITEM_PUBLICKEY_LEN		64
#define ITEM_PUBLICKEY_LEN_MAX		(ITEM_PUBLICKEY_LEN + 1)
#define ITEM_PRIVATEKEY_LEN		64
#define ITEM_PRIVATEKEY_LEN_MAX		(ITEM_PRIVATEKEY_LEN + 1)
#if defined(HAVE_IBM_DB2) || defined(HAVE_ORACLE)
#	define ITEM_PARAM_LEN		2048
#	define ITEM_DESCRIPTION_LEN	2048
#else
#	define ITEM_PARAM_LEN		65535
#	define ITEM_DESCRIPTION_LEN	65535
#endif

#define HISTORY_STR_VALUE_LEN		255
#ifdef HAVE_IBM_DB2
#	define HISTORY_TEXT_VALUE_LEN	2048
#	define HISTORY_LOG_VALUE_LEN	2048
#else
#	define HISTORY_TEXT_VALUE_LEN	65535
#	define HISTORY_LOG_VALUE_LEN	65535
#endif

#define HISTORY_LOG_SOURCE_LEN		64
#define HISTORY_LOG_SOURCE_LEN_MAX	(HISTORY_LOG_SOURCE_LEN + 1)

#define ALERT_ERROR_LEN			128
#define ALERT_ERROR_LEN_MAX		(ALERT_ERROR_LEN + 1)

#define GRAPH_NAME_LEN			128

#define GRAPH_ITEM_COLOR_LEN		6
#define GRAPH_ITEM_COLOR_LEN_MAX	(GRAPH_ITEM_COLOR_LEN + 1)

#define DSERVICE_KEY_LEN		255
#define DSERVICE_VALUE_LEN		255

#define HTTPTEST_HTTP_USER_LEN		64
#define HTTPTEST_HTTP_PASSWORD_LEN	64

#define PROXY_DHISTORY_KEY_LEN		255
#define PROXY_DHISTORY_VALUE_LEN	255

#define ZBX_SQL_ITEM_FIELDS	"i.itemid,i.key_,h.host,i.type,i.history,i.hostid,i.value_type,i.delta,"	\
				"i.units,i.multiplier,i.formula,i.state,i.valuemapid,i.trends,i.data_type"
#define ZBX_SQL_ITEM_TABLES	"hosts h,items i"
#define ZBX_SQL_TIME_FUNCTIONS	"'nodata','date','dayofmonth','dayofweek','time','now'"
#define ZBX_SQL_ITEM_FIELDS_NUM	15
#define ZBX_SQL_ITEM_SELECT	ZBX_SQL_ITEM_FIELDS " from " ZBX_SQL_ITEM_TABLES

#ifdef HAVE_ORACLE
#define	DBbegin_multiple_update(sql, sql_alloc, sql_offset)	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "begin\n")
#define	DBend_multiple_update(sql, sql_alloc, sql_offset)	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "end;")

#define	ZBX_SQL_STRCMP		"%s%s%s"
#define	ZBX_SQL_STRVAL_EQ(str)	'\0' != *str ? "='"  : "",		\
				'\0' != *str ? str   : " is null",	\
				'\0' != *str ? "'"   : ""
#define	ZBX_SQL_STRVAL_NE(str)	'\0' != *str ? "<>'" : "",		\
				'\0' != *str ? str   : " is not null",	\
				'\0' != *str ? "'"   : ""
#else
#define	DBbegin_multiple_update(sql, sql_alloc, sql_offset)
#define	DBend_multiple_update(sql, sql_alloc, sql_offset)

#define	ZBX_SQL_STRCMP		"%s'%s'"
#define	ZBX_SQL_STRVAL_EQ(str)	"=", str
#define	ZBX_SQL_STRVAL_NE(str)	"<>", str
#endif

#define ZBX_SQL_NULLCMP(f1, f2)	"((" f1 " is null and " f2 " is null) or " f1 "=" f2 ")"

#define ZBX_DBROW2UINT64(uint, row)	if (SUCCEED == DBis_null(row))		\
						uint = 0;			\
					else					\
						is_uint64(row, &uint)

#define ZBX_MAX_SQL_LEN		65535

#define ZBX_DB_MAX_ID	(zbx_uint64_t)__UINT64_C(0x7fffffffffffffff)

typedef struct
{
	zbx_uint64_t	druleid;
	zbx_uint64_t	unique_dcheckid;
	char		*iprange;
	char		*name;
}
DB_DRULE;

typedef struct
{
	zbx_uint64_t	dcheckid;
	char		*ports;
	char		*key_;
	char		*snmp_community;
	char		*snmpv3_securityname;
	char		*snmpv3_authpassphrase;
	char		*snmpv3_privpassphrase;
	char		*snmpv3_contextname;
	int		type;
	unsigned char	snmpv3_securitylevel;
	unsigned char	snmpv3_authprotocol;
	unsigned char	snmpv3_privprotocol;
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
	char		*value;
}
DB_DSERVICE;

typedef struct
{
	zbx_uint64_t	triggerid;
	char		*description;
	char		*expression;
	char		*recovery_expression;
	char		*url;
	char		*comments;
	unsigned char	priority;
	unsigned char	type;
	unsigned char	recovery_mode;
}
DB_TRIGGER;

typedef struct
{
	DB_TRIGGER		trigger;
	zbx_uint64_t		eventid;
	zbx_uint64_t		objectid;
	int			source;
	int			object;
	int			clock;
	int			value;
	int			acknowledged;
	int			ns;

	zbx_vector_ptr_t	tags;
}
DB_EVENT;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		hostid;
	zbx_item_type_t		type;
	zbx_item_data_type_t	data_type;
	char			*key;
	char			*host_name;
	int			history;
	int			trends;
	zbx_item_value_type_t	value_type;
	int			delta;
	int			multiplier;
	char			*units;
	char			*formula;
	zbx_uint64_t		valuemapid;
	char			*error;

	unsigned char		state;
	unsigned char		flags;
}
DB_ITEM;

typedef struct
{
	zbx_uint64_t		mediatypeid;
	zbx_media_type_t	type;
	char			*description;
	char			*smtp_server;
	char			*smtp_helo;
	char			*smtp_email;
	char			*exec_path;
	char			*exec_params;
	char			*gsm_modem;
	char			*username;
	char			*passwd;
	unsigned short		smtp_port;
	unsigned char		smtp_security;
	unsigned char		smtp_verify_peer;
	unsigned char		smtp_verify_host;
	unsigned char		smtp_authentication;
}
DB_MEDIATYPE;

typedef struct
{
	zbx_uint64_t	conditionid;
	zbx_uint64_t	actionid;
	char		*value;
	char		*value2;
	unsigned char	conditiontype;
	unsigned char	operator;
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
	char		*variables;
	char		*headers;
	char		*agent;
	char		*http_user;
	char		*http_password;
	char		*http_proxy;
	char		*ssl_cert_file;
	char		*ssl_key_file;
	char		*ssl_key_password;
	int		authentication;
	int		retries;
	int		verify_peer;
	int		verify_host;
}
DB_HTTPTEST;

typedef struct
{
	zbx_uint64_t	httpstepid;
	zbx_uint64_t	httptestid;
	char		*name;
	char		*url;
	char		*posts;
	char		*required;
	char		*status_codes;
	int		no;
	int		timeout;
	char		*variables;
	int		follow_redirects;
	int		retrieve_mode;
	char		*headers;
}
DB_HTTPSTEP;

typedef struct
{
	zbx_uint64_t		escalationid;
	zbx_uint64_t		actionid;
	zbx_uint64_t		triggerid;
	zbx_uint64_t		itemid;
	zbx_uint64_t		eventid;
	zbx_uint64_t		r_eventid;
	int			nextcheck;
	int			esc_step;
	zbx_escalation_status_t	status;
}
DB_ESCALATION;

int	DBconnect(int flag);
void	DBinit(void);
void	DBclose(void);

#ifdef HAVE_ORACLE
void	DBstatement_prepare(const char *sql);
void	DBbind_parameter(int position, void *buffer, unsigned char type);
int	DBstatement_execute();
#endif
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
void		DBbegin(void);
void		DBcommit(void);
void		DBrollback(void);
void		DBend(int ret);

const ZBX_TABLE	*DBget_table(const char *tablename);
const ZBX_FIELD	*DBget_field(const ZBX_TABLE *table, const char *fieldname);
#define DBget_maxid(table)	DBget_maxid_num(table, 1)
zbx_uint64_t	DBget_maxid_num(const char *tablename, int num);

/******************************************************************************
 *                                                                            *
 * Type: ZBX_GRAPH_ITEMS                                                      *
 *                                                                            *
 * Purpose: represent graph item data                                         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
typedef struct
{
	zbx_uint64_t	itemid;	/* itemid should come first for correct sorting */
	zbx_uint64_t	gitemid;
	char		key[ITEM_KEY_LEN * 4 + 1];
	int		drawtype;
	int		sortorder;
	char		color[GRAPH_ITEM_COLOR_LEN_MAX];
	int		yaxisside;
	int		calc_fnc;
	int		type;
	unsigned char	flags;
}
ZBX_GRAPH_ITEMS;

void	process_triggers(zbx_vector_ptr_t *triggers);
int	process_trigger(char **sql, size_t *sql_alloc, size_t *sql_offset, const struct _DC_TRIGGER *trigger);

int	DBupdate_item_status_to_notsupported(DB_ITEM *item, int clock, const char *error);
int	DBget_row_count(const char *table_name);
int	DBget_proxy_lastaccess(const char *hostname, int *lastaccess, char **error);

char	*DBdyn_escape_string(const char *src);
char	*DBdyn_escape_string_len(const char *src, size_t max_src_len);
char	*DBdyn_escape_like_pattern(const char *src);

zbx_uint64_t	DBadd_host(char *server, int port, int status, int useip, char *ip, int disable_until, int available);
int	DBhost_exists(char *server);
int	DBadd_templates_to_host(int hostid,int host_templateid);

int	DBadd_template_linkage(int hostid,int templateid,int items,int triggers,int graphs);

int	DBget_item_by_itemid(int itemid,DB_ITEM *item);

int	DBadd_trigger_to_linked_hosts(int triggerid,int hostid);
void	DBdelete_sysmaps_hosts_by_hostid(zbx_uint64_t hostid);

int	DBadd_graph_item_to_linked_hosts(int gitemid,int hostid);

int	DBcopy_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids);
int	DBdelete_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *del_templateids);

void	DBdelete_items(zbx_vector_uint64_t *itemids);
void	DBdelete_graphs(zbx_vector_uint64_t *graphids);
void	DBdelete_hosts(zbx_vector_uint64_t *hostids);
void	DBdelete_hosts_with_prototypes(zbx_vector_uint64_t *hostids);

int	DBupdate_itservices(const DB_EVENT *events, size_t events_num);
int	DBremove_triggers_from_itservices(zbx_uint64_t *triggerids, int triggerids_num);

void	zbx_create_itservices_lock();
void	zbx_destroy_itservices_lock();

void	DBadd_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const zbx_uint64_t *values, const int num);
void	DBadd_str_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const char **values, const int num);

const char	*zbx_host_string(zbx_uint64_t hostid);
const char	*zbx_host_key_string(zbx_uint64_t itemid);
const char	*zbx_user_string(zbx_uint64_t userid);

void	DBregister_host(zbx_uint64_t proxy_hostid, const char *host, const char *ip, const char *dns,
		unsigned short port, const char *host_metadata, int now);
void	DBproxy_register_host(const char *host, const char *ip, const char *dns, unsigned short port,
		const char *host_metadata);
int	DBexecute_overflowed_sql(char **sql, size_t *sql_alloc, size_t *sql_offset);
char	*DBget_unique_hostname_by_sample(const char *host_name_sample);

const char	*DBsql_id_ins(zbx_uint64_t id);
const char	*DBsql_id_cmp(zbx_uint64_t id);

zbx_uint64_t	DBadd_interface(zbx_uint64_t hostid, unsigned char type,
		unsigned char useip, const char *ip, const char *dns, unsigned short port);

const char	*DBget_inventory_field(unsigned char inventory_link);
unsigned short	DBget_inventory_field_len(unsigned char inventory_link);

void	DBset_host_inventory(zbx_uint64_t hostid, int inventory_mode);
void	DBadd_host_inventory(zbx_uint64_t hostid, int inventory_mode);

int	DBtxn_status(void);
int	DBtxn_ongoing(void);

int	DBtable_exists(const char *table_name);
int	DBfield_exists(const char *table_name, const char *field_name);

void	DBexecute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids);
int	DBlock_record(const char *table, zbx_uint64_t id, const char *add_field, zbx_uint64_t add_id);
int	DBlock_records(const char *table, const zbx_vector_uint64_t *ids);

#define DBlock_hostid(id)			DBlock_record("hosts", id, NULL, 0)
#define DBlock_druleid(id)			DBlock_record("drules", id, NULL, 0)
#define DBlock_dcheckid(dcheckid, druleid)	DBlock_record("dchecks", dcheckid, "druleid", druleid)
#define DBlock_hostids(ids)			DBlock_records("hosts", ids)

void	DBdelete_groups(zbx_vector_uint64_t *groupids);

void	DBselect_uint64(const char *sql, zbx_vector_uint64_t *ids);

/* bulk insert support */

/* database field value */
typedef union
{
	int		i32;
	zbx_uint64_t	ui64;
	double		dbl;
	char		*str;
}
zbx_db_value_t;

/* database bulk insert data */
typedef struct
{
	/* the target table */
	const ZBX_TABLE		*table;
	/* the fields to insert (pointers to the ZBX_FIELD structures from database schema) */
	zbx_vector_ptr_t	fields;
	/* the values rows to insert (pointers to arrays of zbx_db_value_t structures) */
	zbx_vector_ptr_t	rows;
	/* index of autoincrement field */
	int			autoincrement;
}
zbx_db_insert_t;

void	zbx_db_insert_prepare_dyn(zbx_db_insert_t *self, const ZBX_TABLE *table, const ZBX_FIELD **fields,
		int fields_num);
void	zbx_db_insert_prepare(zbx_db_insert_t *self, const char *table, ...);
void	zbx_db_insert_add_values_dyn(zbx_db_insert_t *self, const zbx_db_value_t **values, int values_num);
void	zbx_db_insert_add_values(zbx_db_insert_t *self, ...);
int	zbx_db_insert_execute(zbx_db_insert_t *self);
void	zbx_db_insert_clean(zbx_db_insert_t *self);
void	zbx_db_insert_autoincrement(zbx_db_insert_t *self, const char *field_name);
int	zbx_db_get_database_type(void);

/* agent (ZABBIX, SNMP, IPMI, JMX) availability data */
typedef struct
{
	/* flags specifying which fields are set, see ZBX_FLAGS_AGENT_STATUS_* defines */
	unsigned char	flags;

	/* agent availability fields */
	unsigned char	available;
	char		*error;
	int		errors_from;
	int		disable_until;
}
zbx_agent_availability_t;

#define ZBX_FLAGS_AGENT_STATUS_NONE		0x00000000
#define ZBX_FLAGS_AGENT_STATUS_AVAILABLE	0x00000001
#define ZBX_FLAGS_AGENT_STATUS_ERROR		0x00000002
#define ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM	0x00000004
#define ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL	0x00000008

#define ZBX_FLAGS_AGENT_STATUS		(ZBX_FLAGS_AGENT_STATUS_AVAILABLE |	\
					ZBX_FLAGS_AGENT_STATUS_ERROR |		\
					ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM |	\
					ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL)

#define ZBX_AGENT_ZABBIX	(INTERFACE_TYPE_AGENT - 1)
#define ZBX_AGENT_SNMP		(INTERFACE_TYPE_SNMP - 1)
#define ZBX_AGENT_IPMI		(INTERFACE_TYPE_IPMI - 1)
#define ZBX_AGENT_JMX		(INTERFACE_TYPE_JMX - 1)
#define ZBX_AGENT_UNKNOWN 	255
#define ZBX_AGENT_MAX		INTERFACE_TYPE_COUNT

typedef struct
{
	zbx_uint64_t			hostid;

	zbx_agent_availability_t	agents[ZBX_AGENT_MAX];
}
zbx_host_availability_t;


int	zbx_sql_add_host_availability(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const zbx_host_availability_t *ha);

#endif
