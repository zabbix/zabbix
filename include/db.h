/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxalgo.h"
#include "zbxdb.h"
#include "dbschema.h"

extern char	*CONFIG_DBHOST;
extern char	*CONFIG_DBNAME;
extern char	*CONFIG_DBSCHEMA;
extern char	*CONFIG_DBUSER;
extern char	*CONFIG_DBPASSWORD;
extern char	*CONFIG_DBSOCKET;
extern char	*CONFIG_DB_TLS_CONNECT;
extern char	*CONFIG_DB_TLS_CERT_FILE;
extern char	*CONFIG_DB_TLS_KEY_FILE;
extern char	*CONFIG_DB_TLS_CA_FILE;
extern char	*CONFIG_DB_TLS_CIPHER;
extern char	*CONFIG_DB_TLS_CIPHER_13;
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

#define ZBX_DB_CONNECT_NORMAL	0
#define ZBX_DB_CONNECT_EXIT	1
#define ZBX_DB_CONNECT_ONCE	2

/* type of database */
#define ZBX_DB_UNKNOWN	0
#define ZBX_DB_SERVER	1
#define ZBX_DB_PROXY	2

#define TRIGGER_OPDATA_LEN		255
#define TRIGGER_URL_LEN			255
#define TRIGGER_DESCRIPTION_LEN		255
#define TRIGGER_EXPRESSION_LEN		2048
#define TRIGGER_EXPRESSION_LEN_MAX	(TRIGGER_EXPRESSION_LEN + 1)
#if defined(HAVE_ORACLE)
#	define TRIGGER_COMMENTS_LEN	2048
#else
#	define TRIGGER_COMMENTS_LEN	65535
#endif
#define TRIGGER_EVENT_NAME_LEN		2048

#define GROUP_NAME_LEN			255

#define HOST_HOST_LEN			MAX_ZBX_HOSTNAME_LEN
#define HOST_HOST_LEN_MAX		(HOST_HOST_LEN + 1)
#define HOST_NAME_LEN			128
#define HOST_IPMI_USERNAME_LEN		16
#define HOST_IPMI_USERNAME_LEN_MAX	(HOST_IPMI_USERNAME_LEN + 1)
#define HOST_IPMI_PASSWORD_LEN		20
#define HOST_IPMI_PASSWORD_LEN_MAX	(HOST_IPMI_PASSWORD_LEN + 1)
#define HOST_PROXY_ADDRESS_LEN		255
#define HOST_PROXY_ADDRESS_LEN_MAX	(HOST_PROXY_ADDRESS_LEN + 1)

#define INTERFACE_DNS_LEN		255
#define INTERFACE_DNS_LEN_MAX		(INTERFACE_DNS_LEN + 1)
#define INTERFACE_IP_LEN		64
#define INTERFACE_IP_LEN_MAX		(INTERFACE_IP_LEN + 1)
#define INTERFACE_ADDR_LEN		255	/* MAX(INTERFACE_DNS_LEN,INTERFACE_IP_LEN) */
#define INTERFACE_ADDR_LEN_MAX		(INTERFACE_ADDR_LEN + 1)
#define INTERFACE_PORT_LEN		64
#define INTERFACE_PORT_LEN_MAX		(INTERFACE_PORT_LEN + 1)
#define INTERFACE_ERROR_LEN		2048
#define INTERFACE_ERROR_LEN_MAX		(INTERFACE_ERROR_LEN + 1)

#define ITEM_NAME_LEN			255
#define ITEM_KEY_LEN			2048
#define ITEM_DELAY_LEN			1024
#define ITEM_HISTORY_LEN		255
#define ITEM_TRENDS_LEN			255
#define ITEM_UNITS_LEN			255
#define ITEM_SNMP_COMMUNITY_LEN		64
#define ITEM_SNMP_COMMUNITY_LEN_MAX	(ITEM_SNMP_COMMUNITY_LEN + 1)
#define ITEM_SNMP_OID_LEN		512
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
#define ITEM_JMX_ENDPOINT_LEN		255
#define ITEM_JMX_ENDPOINT_LEN_MAX	(ITEM_JMX_ENDPOINT_LEN + 1)
#define ITEM_TIMEOUT_LEN		255
#define ITEM_TIMEOUT_LEN_MAX		(ITEM_TIMEOUT_LEN + 1)
#define ITEM_URL_LEN			2048
#define ITEM_URL_LEN_MAX		(ITEM_URL_LEN + 1)
#define ITEM_QUERY_FIELDS_LEN		2048
#define ITEM_QUERY_FIELDS_LEN_MAX	(ITEM_QUERY_FIELDS_LEN + 1)
#define ITEM_STATUS_CODES_LEN		255
#define ITEM_STATUS_CODES_LEN_MAX	(ITEM_STATUS_CODES_LEN + 1)
#define ITEM_HTTP_PROXY_LEN		255
#define ITEM_HTTP_PROXY_LEN_MAX		(ITEM_HTTP_PROXY_LEN + 1)
#define ITEM_SSL_KEY_PASSWORD_LEN	64
#define ITEM_SSL_KEY_PASSWORD_LEN_MAX	(ITEM_SSL_KEY_PASSWORD_LEN + 1)
#define ITEM_SSL_CERT_FILE_LEN		255
#define ITEM_SSL_CERT_FILE_LEN_MAX	(ITEM_SSL_CERT_FILE_LEN + 1)
#define ITEM_SSL_KEY_FILE_LEN		255
#define ITEM_SSL_KEY_FILE_LEN_MAX	(ITEM_SSL_KEY_FILE_LEN + 1)
#if defined(HAVE_ORACLE)
#	define ITEM_PARAM_LEN		2048
#	define ITEM_DESCRIPTION_LEN	2048
#	define ITEM_POSTS_LEN		2048
#	define ITEM_HEADERS_LEN		2048
#else
#	define ITEM_PARAM_LEN		65535
#	define ITEM_DESCRIPTION_LEN	65535
#	define ITEM_POSTS_LEN		65535
#	define ITEM_HEADERS_LEN		65535
#endif

#define ITEM_PARAMETER_NAME_LEN		255
#define ITEM_PARAMETER_VALUE_LEN	2048

/* common tag/value field lengths for all tags */
#define TAG_NAME_LEN			255
#define TAG_VALUE_LEN			255

#define HISTORY_STR_VALUE_LEN		255
#define HISTORY_TEXT_VALUE_LEN		65535
#define HISTORY_LOG_VALUE_LEN		65535

#define HISTORY_LOG_SOURCE_LEN		64
#define HISTORY_LOG_SOURCE_LEN_MAX	(HISTORY_LOG_SOURCE_LEN + 1)

#define ALERT_ERROR_LEN			2048
#define ALERT_ERROR_LEN_MAX		(ALERT_ERROR_LEN + 1)

#define GRAPH_NAME_LEN			128

#define GRAPH_ITEM_COLOR_LEN		6
#define GRAPH_ITEM_COLOR_LEN_MAX	(GRAPH_ITEM_COLOR_LEN + 1)

#define DSERVICE_VALUE_LEN		255
#define MAX_DISCOVERED_VALUE_SIZE	(DSERVICE_VALUE_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1)

#define HTTPTEST_HTTP_USER_LEN		64
#define HTTPTEST_HTTP_PASSWORD_LEN	64

#define PROXY_DHISTORY_VALUE_LEN	255

#define ITEM_PREPROC_PARAMS_LEN		65535

#define EVENT_NAME_LEN			2048

#define FUNCTION_NAME_LEN		12
#define FUNCTION_PARAM_LEN		255

#define REPORT_ERROR_LEN		2048

#define ZBX_SQL_ITEM_FIELDS	"i.itemid,i.key_,h.host,i.type,i.history,i.hostid,i.value_type,i.delta,"	\
				"i.units,i.multiplier,i.formula,i.state,i.valuemapid,i.trends,i.data_type"
#define ZBX_SQL_ITEM_TABLES	"hosts h,items i"
#define ZBX_SQL_TIME_FUNCTIONS	"'nodata','date','dayofmonth','dayofweek','time','now'"
#define ZBX_SQL_ITEM_FIELDS_NUM	15
#define ZBX_SQL_ITEM_SELECT	ZBX_SQL_ITEM_FIELDS " from " ZBX_SQL_ITEM_TABLES

#ifdef HAVE_ORACLE
#	define ZBX_PLSQL_BEGIN	"begin\n"
#	define ZBX_PLSQL_END	"end;"
#	define	DBbegin_multiple_update(sql, sql_alloc, sql_offset)			\
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_PLSQL_BEGIN)
#	define	DBend_multiple_update(sql, sql_alloc, sql_offset)			\
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_PLSQL_END)
#	if 0 == ZBX_MAX_OVERFLOW_SQL_SIZE
#		define	ZBX_SQL_EXEC_FROM	ZBX_CONST_STRLEN(ZBX_PLSQL_BEGIN)
#	else
#		define	ZBX_SQL_EXEC_FROM	0
#	endif

#	define	ZBX_SQL_STRCMP		"%s%s%s"
#	define	ZBX_SQL_STRVAL_EQ(str)				\
			'\0' != *str ? "='"  : "",		\
			'\0' != *str ? str   : " is null",	\
			'\0' != *str ? "'"   : ""
#	define	ZBX_SQL_STRVAL_NE(str)				\
			'\0' != *str ? "<>'" : "",		\
			'\0' != *str ? str   : " is not null",	\
			'\0' != *str ? "'"   : ""
#else
#	define	DBbegin_multiple_update(sql, sql_alloc, sql_offset)	do {} while (0)
#	define	DBend_multiple_update(sql, sql_alloc, sql_offset)	do {} while (0)

#	define	ZBX_SQL_EXEC_FROM	0
#	ifdef HAVE_MYSQL
#		define	ZBX_SQL_STRCMP		"%s binary '%s'"
#	else
#		define	ZBX_SQL_STRCMP		"%s'%s'"
#	endif
#	define	ZBX_SQL_STRVAL_EQ(str)	"=", str
#	define	ZBX_SQL_STRVAL_NE(str)	"<>", str
#endif

#define ZBX_SQL_NULLCMP(f1, f2)	"((" f1 " is null and " f2 " is null) or " f1 "=" f2 ")"

#define ZBX_DBROW2UINT64(uint, row)	if (SUCCEED == DBis_null(row))		\
						uint = 0;			\
					else					\
						is_uint64(row, &uint)

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

#define ZBX_DB_TRIGGER_EVAL_NONE			0x0000
#define ZBX_DB_TRIGGER_EVAL_EXPRESSION			0x0001
#define ZBX_DB_TRIGGER_EVAL_EXPRESSION_USERMACRO	0x0002
#define ZBX_DB_TRIGGER_EVAL_RECOVERY_EXPRESSION		0x0004

typedef struct
{
	zbx_uint64_t	triggerid;
	char		*description;
	char		*expression;
	char		*recovery_expression;
	char		*url;
	char		*comments;
	char		*correlation_tag;
	char		*opdata;
	char		*event_name;
	unsigned char	value;
	unsigned char	priority;
	unsigned char	type;
	unsigned char	recovery_mode;
	unsigned char	correlation_mode;

	/* temporary trigger cache for related data */
	void		*cache;
}
DB_TRIGGER;

typedef struct
{
	zbx_uint64_t		serviceid;
	char			*name;
	char			*description;
	zbx_vector_uint64_t	eventids;
	zbx_vector_ptr_t	events;
	zbx_vector_tags_t	service_tags;
}
DB_SERVICE;

typedef struct
{
	zbx_uint64_t		eventid;
	DB_TRIGGER		trigger;
	zbx_uint64_t		objectid;
	char			*name;
	int			source;
	int			object;
	int			clock;
	int			value;
	int			acknowledged;
	int			ns;
	int			severity;
	unsigned char		suppressed;

	zbx_vector_ptr_t	tags;	/* used for both zbx_tag_t and zbx_host_tag_t */

#define ZBX_FLAGS_DB_EVENT_UNSET		0x0000
#define ZBX_FLAGS_DB_EVENT_CREATE		0x0001
#define ZBX_FLAGS_DB_EVENT_NO_ACTION		0x0002
#define ZBX_FLAGS_DB_EVENT_RECOVER		0x0004
	zbx_uint64_t		flags;
}
DB_EVENT;

typedef struct DB_MEDIATYPE
{
	zbx_uint64_t		mediatypeid;
	zbx_media_type_t	type;
	char			*smtp_server;
	char			*smtp_helo;
	char			*smtp_email;
	char			*exec_path;
	char			*exec_params;
	char			*gsm_modem;
	char			*username;
	char			*passwd;
	char			*script;
	char			*attempt_interval;
	char			*timeout;
	unsigned short		smtp_port;
	unsigned char		smtp_security;
	unsigned char		smtp_verify_peer;
	unsigned char		smtp_verify_host;
	unsigned char		smtp_authentication;
	unsigned char		content_type;
	int			maxsessions;
	int			maxattempts;
}
DB_MEDIATYPE;

void 	zbx_db_mediatype_clean(DB_MEDIATYPE *mt);
void	zbx_serialize_mediatype(unsigned char **data, zbx_uint32_t *data_alloc, zbx_uint32_t *data_offset,
		const DB_MEDIATYPE *mt);
zbx_uint32_t	zbx_deserialize_mediatype(const unsigned char *data, DB_MEDIATYPE *mt);

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
	char		*agent;
	char		*http_user;
	char		*http_password;
	char		*http_proxy;
	char		*ssl_cert_file;
	char		*ssl_key_file;
	char		*ssl_key_password;
	char		*delay;
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
	int		follow_redirects;
	int		retrieve_mode;
	int		post_type;
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
	zbx_uint64_t		acknowledgeid;
	zbx_uint64_t		servicealarmid;
	zbx_uint64_t		serviceid;
	int			nextcheck;
	int			esc_step;
	zbx_escalation_status_t	status;
}
DB_ESCALATION;

typedef struct
{
	zbx_uint64_t	actionid;
	char		*name;
	int		esc_period;
	unsigned char	eventsource;
	unsigned char	pause_suppressed;
	unsigned char	recovery;
	unsigned char	status;
	unsigned char	notify_if_canceled;
}
DB_ACTION;

typedef struct
{
	zbx_uint64_t	acknowledgeid;
	zbx_uint64_t	userid;
	char		*message;
	int		clock;
	int		action;
	int		old_severity;
	int		new_severity;
}
DB_ACKNOWLEDGE;

typedef struct
{
	zbx_uint64_t	service_alarmid;
	int		value;
	int		clock;
}
zbx_service_alarm_t;

int	DBinit(char **error);
void	DBdeinit(void);

void	DBinit_autoincrement_options(void);

int	DBconnect(int flag);
void	DBclose(void);

int	zbx_db_validate_config_features(void);
#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
void	zbx_db_validate_config(void);
#endif

#ifdef HAVE_ORACLE
void	DBstatement_prepare(const char *sql);
#endif
int		DBexecute(const char *fmt, ...) __zbx_attr_format_printf(1, 2);
int		DBexecute_once(const char *fmt, ...) __zbx_attr_format_printf(1, 2);
DB_RESULT	DBselect_once(const char *fmt, ...) __zbx_attr_format_printf(1, 2);
DB_RESULT	DBselect(const char *fmt, ...) __zbx_attr_format_printf(1, 2);
DB_RESULT	DBselectN(const char *query, int n);
DB_ROW		DBfetch(DB_RESULT result);
int		DBis_null(const char *field);
void		DBbegin(void);
int		DBcommit(void);
void		DBrollback(void);
int		DBend(int ret);

const ZBX_TABLE	*DBget_table(const char *tablename);
const ZBX_FIELD	*DBget_field(const ZBX_TABLE *table, const char *fieldname);
#define DBget_maxid(table)	DBget_maxid_num(table, 1)
zbx_uint64_t	DBget_maxid_num(const char *tablename, int num);

void	zbx_db_extract_version_info(struct zbx_db_version_info_t *version_info);
void	zbx_db_extract_dbextension_info(struct zbx_db_version_info_t *version_info);
void	zbx_db_flush_version_requirements(const char *version);
#ifdef HAVE_POSTGRESQL
int	zbx_db_check_tsdb_capabilities(struct zbx_db_version_info_t *db_version_info, int allow_unsupported_ver);
char	*zbx_db_get_schema_esc(void);
#endif

/******************************************************************************
 *                                                                            *
 * Type: ZBX_GRAPH_ITEMS                                                      *
 *                                                                            *
 * Purpose: represent graph item data                                         *
 *                                                                            *
 ******************************************************************************/
typedef struct
{
	zbx_uint64_t	itemid;	/* itemid should come first for correct sorting */
	zbx_uint64_t	gitemid;
	char		key[ITEM_KEY_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	int		drawtype;
	int		sortorder;
	char		color[GRAPH_ITEM_COLOR_LEN_MAX];
	int		yaxisside;
	int		calc_fnc;
	int		type;
	unsigned char	flags;
}
ZBX_GRAPH_ITEMS;

typedef struct
{
	zbx_uint64_t	triggerid;
	unsigned char	value;
	unsigned char	state;
	unsigned char	priority;
	int		lastchange;
	int		problem_count;
	char		*error;

#define ZBX_FLAGS_TRIGGER_DIFF_UNSET				0x0000
#define ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE			0x0001
#define ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE		0x0002
#define ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE			0x0004
#define ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR			0x0008
#define ZBX_FLAGS_TRIGGER_DIFF_UPDATE										\
		(ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE | ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE | 		\
		ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE | ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR)
#define ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT		0x1000
#define ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT	0x2000
	zbx_uint64_t			flags;
}
zbx_trigger_diff_t;

void	zbx_process_triggers(zbx_vector_ptr_t *triggers, zbx_vector_ptr_t *trigger_diff);
void	zbx_db_save_trigger_changes(const zbx_vector_ptr_t *trigger_diff);
void	zbx_trigger_diff_free(zbx_trigger_diff_t *diff);
void	zbx_append_trigger_diff(zbx_vector_ptr_t *trigger_diff, zbx_uint64_t triggerid, unsigned char priority,
		zbx_uint64_t flags, unsigned char value, unsigned char state, int lastchange, const char *error);

int	DBget_row_count(const char *table_name);
int	DBget_proxy_lastaccess(const char *hostname, int *lastaccess, char **error);

char	*DBdyn_escape_field(const char *table_name, const char *field_name, const char *src);
char	*DBdyn_escape_string(const char *src);
char	*DBdyn_escape_string_len(const char *src, size_t length);
char	*DBdyn_escape_like_pattern(const char *src);

int	DBadd_templates_to_host(int hostid, int host_templateid);

int	DBadd_template_linkage(int hostid, int templateid, int items, int triggers, int graphs);

int	DBadd_trigger_to_linked_hosts(int triggerid, int hostid);
void	DBdelete_sysmaps_hosts_by_hostid(zbx_uint64_t hostid);

int	DBadd_graph_item_to_linked_hosts(int gitemid, int hostid);

int	DBcopy_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids, char **error);
int	DBdelete_template_elements(zbx_uint64_t hostid, const char *hostname, zbx_vector_uint64_t *del_templateids,
		char **error);

void	DBdelete_items(zbx_vector_uint64_t *itemids);
void	DBdelete_graphs(zbx_vector_uint64_t *graphids);
void	DBdelete_triggers(zbx_vector_uint64_t *triggerids);

void	DBdelete_hosts(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames);
void	DBdelete_hosts_with_prototypes(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames);

void	DBadd_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const zbx_uint64_t *values, const int num);
void	DBadd_str_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const char **values, const int num);

int	zbx_check_user_permissions(const zbx_uint64_t *userid, const zbx_uint64_t *recipient_userid);

const char	*zbx_host_string(zbx_uint64_t hostid);
const char	*zbx_host_key_string(zbx_uint64_t itemid);
const char	*zbx_user_string(zbx_uint64_t userid);
int	DBget_user_names(zbx_uint64_t userid, char **username, char **name, char **surname);

void	DBregister_host(zbx_uint64_t proxy_hostid, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flag,
		int now);
void	DBregister_host_prepare(zbx_vector_ptr_t *autoreg_hosts, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flag,
		int now);
void	DBregister_host_flush(zbx_vector_ptr_t *autoreg_hosts, zbx_uint64_t proxy_hostid);
void	DBregister_host_clean(zbx_vector_ptr_t *autoreg_hosts);

void	DBproxy_register_host(const char *host, const char *ip, const char *dns, unsigned short port,
		unsigned int connection_type, const char *host_metadata, unsigned short flag);
int	DBexecute_overflowed_sql(char **sql, size_t *sql_alloc, size_t *sql_offset);
char	*DBget_unique_hostname_by_sample(const char *host_name_sample, const char *field_name);

const char	*DBsql_id_ins(zbx_uint64_t id);
const char	*DBsql_id_cmp(zbx_uint64_t id);

typedef enum
{
	ZBX_CONN_DEFAULT = 0,
	ZBX_CONN_IP,
	ZBX_CONN_DNS,
}
zbx_conn_flags_t;

zbx_uint64_t	DBadd_interface(zbx_uint64_t hostid, unsigned char type, unsigned char useip,
		const char *ip, const char *dns, unsigned short port, zbx_conn_flags_t flags);
void	DBadd_interface_snmp(const zbx_uint64_t interfaceid, const unsigned char version,
		const unsigned char bulk, const char *community, const char *securityname,
		const unsigned char securitylevel, const char *authpassphrase, const char *privpassphrase,
		const unsigned char authprotocol, const unsigned char privprotocol, const char *contextname,
		const zbx_uint64_t hostid);

const char	*DBget_inventory_field(unsigned char inventory_link);

void	DBset_host_inventory(zbx_uint64_t hostid, int inventory_mode);
void	DBadd_host_inventory(zbx_uint64_t hostid, int inventory_mode);

int	DBtxn_status(void);
int	DBtxn_ongoing(void);

int	DBtable_exists(const char *table_name);
int	DBfield_exists(const char *table_name, const char *field_name);
#ifndef HAVE_SQLITE3
int	DBtrigger_exists(const char *table_name, const char *trigger_name);
int	DBindex_exists(const char *table_name, const char *index_name);
int	DBpk_exists(const char *table_name);
#endif

int	DBprepare_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids, char **sql,
		size_t	*sql_alloc, size_t *sql_offset);
int	DBexecute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids);
int	DBlock_record(const char *table, zbx_uint64_t id, const char *add_field, zbx_uint64_t add_id);
int	DBlock_records(const char *table, const zbx_vector_uint64_t *ids);
int	DBlock_ids(const char *table_name, const char *field_name, zbx_vector_uint64_t *ids);

#define DBlock_hostid(id)			DBlock_record("hosts", id, NULL, 0)
#define DBlock_druleid(id)			DBlock_record("drules", id, NULL, 0)
#define DBlock_dcheckid(dcheckid, druleid)	DBlock_record("dchecks", dcheckid, "druleid", druleid)
#define DBlock_graphid(id)			DBlock_record("graphs", id, NULL, 0)
#define DBlock_hostids(ids)			DBlock_records("hosts", ids)
#define DBlock_triggerids(ids)			DBlock_records("triggers", ids)
#define DBlock_itemids(ids)			DBlock_records("items", ids)
#define DBlock_group_prototypeids(ids)		DBlock_records("group_prototype", ids)

void	DBdelete_groups(zbx_vector_uint64_t *groupids);

void	DBselect_uint64(const char *sql, zbx_vector_uint64_t *ids);

void	DBcheck_character_set(void);

/* bulk insert support */

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
	zbx_uint64_t			interfaceid;
	zbx_agent_availability_t	agent;
	/* ensure chronological order in case of flapping interface availability */
	int				id;
}
zbx_interface_availability_t;

ZBX_PTR_VECTOR_DECL(availability_ptr, zbx_interface_availability_t *)

typedef struct
{
	zbx_uint64_t		eventid;
	int			clock;
	int			ns;
	int			value;
	int			severity;

	zbx_vector_ptr_t	tags;
}
zbx_event_t;

void	zbx_db_update_interface_availabilities(const zbx_vector_availability_ptr_t *interface_availabilities);
int	DBget_user_by_active_session(const char *sessionid, zbx_user_t *user);
int	DBget_user_by_auth_token(const char *formatted_auth_token_hash, zbx_user_t *user);
void	zbx_user_init(zbx_user_t *user);
void	zbx_user_free(zbx_user_t *user);

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	lastlogsize;
	unsigned char	state;
	int		mtime;
	const char	*error;

	zbx_uint64_t	flags;
#define ZBX_FLAGS_ITEM_DIFF_UNSET			__UINT64_C(0x0000)
#define ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE		__UINT64_C(0x0001)
#define ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR		__UINT64_C(0x0002)
#define ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME		__UINT64_C(0x0004)
#define ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE		__UINT64_C(0x0008)
#define ZBX_FLAGS_ITEM_DIFF_UPDATE_DB			\
	(ZBX_FLAGS_ITEM_DIFF_UPDATE_STATE | ZBX_FLAGS_ITEM_DIFF_UPDATE_ERROR |\
	ZBX_FLAGS_ITEM_DIFF_UPDATE_MTIME | ZBX_FLAGS_ITEM_DIFF_UPDATE_LASTLOGSIZE)
}
zbx_item_diff_t;

/* event support */
void	zbx_db_get_events_by_eventids(zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *events);
void	zbx_db_free_event(DB_EVENT *event);
void	zbx_db_get_eventid_r_eventid_pairs(zbx_vector_uint64_t *eventids, zbx_vector_uint64_pair_t *event_pairs,
		zbx_vector_uint64_t *r_eventids);
void	zbx_db_trigger_get_expression(const DB_TRIGGER *trigger, char **expression);
void	zbx_db_trigger_get_recovery_expression(const DB_TRIGGER *trigger, char **expression);
void	zbx_db_trigger_clean(DB_TRIGGER *trigger);

typedef struct
{
	zbx_uint64_t		hostid;
	unsigned char		compress;
	int			version;
	int			lastaccess;
	int			last_version_error_time;
	int			proxy_delay;
	int			more_data;
	zbx_proxy_suppress_t	nodata_win;

#define ZBX_FLAGS_PROXY_DIFF_UNSET				__UINT64_C(0x0000)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_COMPRESS			__UINT64_C(0x0001)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION			__UINT64_C(0x0002)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTACCESS			__UINT64_C(0x0004)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTERROR			__UINT64_C(0x0008)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_PROXYDELAY			__UINT64_C(0x0010)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_SUPPRESS_WIN		__UINT64_C(0x0020)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_HEARTBEAT			__UINT64_C(0x0040)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_CONFIG			__UINT64_C(0x0080)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE (			\
		ZBX_FLAGS_PROXY_DIFF_UPDATE_COMPRESS |	\
		ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION |	\
		ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTACCESS)
	zbx_uint64_t	flags;
}
zbx_proxy_diff_t;

int	zbx_db_lock_maintenanceids(zbx_vector_uint64_t *maintenanceids);

void	zbx_db_save_item_changes(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_vector_ptr_t *item_diff,
		zbx_uint64_t mask);

/* mock field to estimate how much data can be stored in characters, bytes or both, */
/* depending on database backend                                                    */

typedef struct
{
	int	bytes_num;
	int	chars_num;
}
zbx_db_mock_field_t;

void	zbx_db_mock_field_init(zbx_db_mock_field_t *field, int field_type, int field_len);
int	zbx_db_mock_field_append(zbx_db_mock_field_t *field, const char *text);

int	zbx_db_check_instanceid(void);

/* tags */
typedef struct
{
	zbx_uint64_t	tagid;
	char		*tag_orig;
	char		*tag;
	char		*value_orig;
	char		*value;
#define ZBX_FLAG_DB_TAG_UNSET			__UINT64_C(0x00000000)
#define ZBX_FLAG_DB_TAG_UPDATE_TAG		__UINT64_C(0x00000001)
#define ZBX_FLAG_DB_TAG_UPDATE_VALUE		__UINT64_C(0x00000002)
#define ZBX_FLAG_DB_TAG_REMOVE			__UINT64_C(0x80000000)
#define ZBX_FLAG_DB_TAG_UPDATE			(ZBX_FLAG_DB_TAG_UPDATE_TAG | ZBX_FLAG_DB_TAG_UPDATE_VALUE)
	zbx_uint64_t	flags;
}
zbx_db_tag_t;

ZBX_PTR_VECTOR_DECL(db_tag_ptr, zbx_db_tag_t *)

zbx_db_tag_t	*zbx_db_tag_create(const char *tag_tag, const char *tag_value);
void		zbx_db_tag_free(zbx_db_tag_t *tag);

typedef struct _zbx_item_param_t zbx_item_param_t;
struct _zbx_item_param_t
{
	zbx_uint64_t	item_parameterid;
#define ZBX_FLAG_ITEM_PARAM_UPDATE_RESET	__UINT64_C(0x000000000000)
#define ZBX_FLAG_ITEM_PARAM_UPDATE_NAME		__UINT64_C(0x000000000001)
#define ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE	__UINT64_C(0x000000000002)
#define ZBX_FLAG_ITEM_PARAM_UPDATE			\
		(ZBX_FLAG_ITEM_PARAM_UPDATE_NAME |	\
		ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE	\
		)

#define ZBX_FLAG_ITEM_PARAM_DELETE		__UINT64_C(0x000000010000)

	zbx_uint64_t	flags;
	char		*name_orig;
	char		*name;
	char		*value_orig;
	char		*value;
};

ZBX_PTR_VECTOR_DECL(item_param_ptr, zbx_item_param_t *)

zbx_item_param_t	*zbx_item_param_create(const char *item_param_name,
		const char *item_param_value);
void	zbx_item_param_free(zbx_item_param_t *param);


int	zbx_merge_tags(zbx_vector_db_tag_ptr_t *dst, zbx_vector_db_tag_ptr_t *src, const char *owner, char **error);
int	zbx_merge_item_params(zbx_vector_item_param_ptr_t *dst, zbx_vector_item_param_ptr_t *src, char **error);

typedef enum
{
	ZBX_LLD_OVERRIDE_OP_OBJECT_ITEM = 0,
	ZBX_LLD_OVERRIDE_OP_OBJECT_TRIGGER,
	ZBX_LLD_OVERRIDE_OP_OBJECT_GRAPH,
	ZBX_LLD_OVERRIDE_OP_OBJECT_HOST
}
zbx_lld_override_op_object_t;

typedef struct
{
	zbx_uint64_t		override_operationid;
	zbx_uint64_t		overrideid;
	char			*value;
	char			*delay;
	char			*history;
	char			*trends;
	zbx_vector_db_tag_ptr_t	tags;
	zbx_vector_uint64_t	templateids;
	unsigned char		operationtype;
	unsigned char		operator;
	unsigned char		status;
	unsigned char		severity;
	signed char		inventory_mode;
	unsigned char		discover;
}
zbx_lld_override_operation_t;

void	zbx_lld_override_operation_free(zbx_lld_override_operation_t *override_operation);

void	zbx_load_lld_override_operations(const zbx_vector_uint64_t *overrideids, char **sql, size_t *sql_alloc,
		zbx_vector_ptr_t *ops);

#define ZBX_TIMEZONE_DEFAULT_VALUE	"default"

void	zbx_db_trigger_get_all_functionids(const DB_TRIGGER *trigger, zbx_vector_uint64_t *functionids);
void	zbx_db_trigger_get_functionids(const DB_TRIGGER *trigger, zbx_vector_uint64_t *functionids);
int	zbx_db_trigger_get_all_hostids(const DB_TRIGGER *trigger, const zbx_vector_uint64_t **hostids);
int	zbx_db_trigger_get_constant(const DB_TRIGGER *trigger, int index, char **out);
int	zbx_db_trigger_get_itemid(const DB_TRIGGER *trigger, int index, zbx_uint64_t *itemid);
void	zbx_db_trigger_get_itemids(const DB_TRIGGER *trigger, zbx_vector_uint64_t *itemids);

int	DBselect_ids_names(const char *sql, zbx_vector_uint64_t *ids, zbx_vector_str_t *names);

int	zbx_db_check_version_info(struct zbx_db_version_info_t *info, int allow_unsupported);

#endif
