/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_DBHIGH_H
#define ZABBIX_DBHIGH_H

#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxversion.h"

#define ZBX_DB_CONNECT_NORMAL	0
#define ZBX_DB_CONNECT_EXIT	1
#define ZBX_DB_CONNECT_ONCE	2

/* type of database */
#define ZBX_DB_UNKNOWN	0
#define ZBX_DB_SERVER	1
#define ZBX_DB_PROXY	2

#define ZBX_INTERFACE_DNS_LEN		255
#define ZBX_INTERFACE_DNS_LEN_MAX	(ZBX_INTERFACE_DNS_LEN + 1)
#define ZBX_INTERFACE_IP_LEN		64
#define ZBX_INTERFACE_IP_LEN_MAX	(ZBX_INTERFACE_IP_LEN + 1)
#define ZBX_INTERFACE_ADDR_LEN		255	/* MAX(ZBX_INTERFACE_DNS_LEN,ZBX_INTERFACE_IP_LEN) */
#define ZBX_INTERFACE_ADDR_LEN_MAX	(ZBX_INTERFACE_ADDR_LEN + 1)
#define ZBX_INTERFACE_PORT_LEN		64
#define ZBX_INTERFACE_PORT_LEN_MAX	(ZBX_INTERFACE_PORT_LEN + 1)
#define ZBX_INTERFACE_ERROR_LEN		2048
#define ZBX_INTERFACE_ERROR_LEN_MAX	(ZBX_INTERFACE_ERROR_LEN + 1)

#define ZBX_ITEM_NAME_LEN			255
#define ZBX_ITEM_KEY_LEN			2048
#define ZBX_ITEM_DELAY_LEN			1024
#define ZBX_ITEM_HISTORY_LEN			255
#define ZBX_ITEM_TRENDS_LEN			255
#define ZBX_ITEM_UNITS_LEN			255
#define ZBX_ITEM_SNMP_COMMUNITY_LEN		64
#define ZBX_ITEM_SNMP_COMMUNITY_LEN_MAX		(ZBX_ITEM_SNMP_COMMUNITY_LEN + 1)
#define ZBX_ITEM_SNMP_OID_LEN			512
#define ZBX_ITEM_SNMP_OID_LEN_MAX		(ZBX_ITEM_SNMP_OID_LEN + 1)
#define ZBX_ITEM_ERROR_LEN			2048
#define ZBX_ITEM_ERROR_LEN_MAX			(ZBX_ITEM_ERROR_LEN + 1)
#define ZBX_ITEM_TRAPPER_HOSTS_LEN		255
#define ZBX_ITEM_TRAPPER_HOSTS_LEN_MAX		(ZBX_ITEM_TRAPPER_HOSTS_LEN + 1)
#define ZBX_ITEM_SNMPV3_SECURITYNAME_LEN	64
#define ZBX_ITEM_SNMPV3_SECURITYNAME_LEN_MAX	(ZBX_ITEM_SNMPV3_SECURITYNAME_LEN + 1)
#define ZBX_ITEM_SNMPV3_AUTHPASSPHRASE_LEN	64
#define ZBX_ITEM_SNMPV3_AUTHPASSPHRASE_LEN_MAX	(ZBX_ITEM_SNMPV3_AUTHPASSPHRASE_LEN + 1)
#define ZBX_ITEM_SNMPV3_PRIVPASSPHRASE_LEN	64
#define ZBX_ITEM_SNMPV3_PRIVPASSPHRASE_LEN_MAX	(ZBX_ITEM_SNMPV3_PRIVPASSPHRASE_LEN + 1)
#define ZBX_ITEM_SNMPV3_CONTEXTNAME_LEN		255
#define ZBX_ITEM_SNMPV3_CONTEXTNAME_LEN_MAX	(ZBX_ITEM_SNMPV3_CONTEXTNAME_LEN + 1)
#define ZBX_ITEM_LOGTIMEFMT_LEN			64
#define ZBX_ITEM_LOGTIMEFMT_LEN_MAX		(ZBX_ITEM_LOGTIMEFMT_LEN + 1)
#define ZBX_ITEM_IPMI_SENSOR_LEN		128
#define ZBX_ITEM_IPMI_SENSOR_LEN_MAX		(ZBX_ITEM_IPMI_SENSOR_LEN + 1)
#define ZBX_ITEM_USERNAME_LEN			64
#define ZBX_ITEM_USERNAME_LEN_MAX		(ZBX_ITEM_USERNAME_LEN + 1)
#define ZBX_ITEM_PASSWORD_LEN			64
#define ZBX_ITEM_PASSWORD_LEN_MAX		(ZBX_ITEM_PASSWORD_LEN + 1)
#define ZBX_ITEM_PUBLICKEY_LEN			64
#define ZBX_ITEM_PUBLICKEY_LEN_MAX		(ZBX_ITEM_PUBLICKEY_LEN + 1)
#define ZBX_ITEM_PRIVATEKEY_LEN			64
#define ZBX_ITEM_PRIVATEKEY_LEN_MAX		(ZBX_ITEM_PRIVATEKEY_LEN + 1)
#define ZBX_ITEM_JMX_ENDPOINT_LEN		255
#define ZBX_ITEM_JMX_ENDPOINT_LEN_MAX		(ZBX_ITEM_JMX_ENDPOINT_LEN + 1)
#define ZBX_ITEM_TIMEOUT_LEN			255
#define ZBX_ITEM_TIMEOUT_LEN_MAX		(ZBX_ITEM_TIMEOUT_LEN + 1)
#define ZBX_ITEM_URL_LEN			2048
#define ZBX_ITEM_URL_LEN_MAX			(ZBX_ITEM_URL_LEN + 1)
#define ZBX_ITEM_QUERY_FIELDS_LEN		2048
#define ZBX_ITEM_QUERY_FIELDS_LEN_MAX		(ZBX_ITEM_QUERY_FIELDS_LEN + 1)
#define ZBX_ITEM_STATUS_CODES_LEN		255
#define ZBX_ITEM_STATUS_CODES_LEN_MAX		(ZBX_ITEM_STATUS_CODES_LEN + 1)
#define ZBX_ITEM_HTTP_PROXY_LEN			255
#define ZBX_ITEM_HTTP_PROXY_LEN_MAX		(ZBX_ITEM_HTTP_PROXY_LEN + 1)
#define ZBX_ITEM_SSL_KEY_PASSWORD_LEN		64
#define ZBX_ITEM_SSL_KEY_PASSWORD_LEN_MAX	(ZBX_ITEM_SSL_KEY_PASSWORD_LEN + 1)
#define ZBX_ITEM_SSL_CERT_FILE_LEN		255
#define ZBX_ITEM_SSL_CERT_FILE_LEN_MAX		(ZBX_ITEM_SSL_CERT_FILE_LEN + 1)
#define ZBX_ITEM_SSL_KEY_FILE_LEN		255
#define ZBX_ITEM_SSL_KEY_FILE_LEN_MAX		(ZBX_ITEM_SSL_KEY_FILE_LEN + 1)
#define ZBX_ITEM_PREPROC_PARAMS_LEN		65535

#if defined(HAVE_ORACLE)
#	define ZBX_ITEM_PARAM_LEN		2048
#	define ZBX_ITEM_DESCRIPTION_LEN		2048
#	define ZBX_ITEM_POSTS_LEN		2048
#	define ZBX_ITEM_HEADERS_LEN		2048
#else
#	define ZBX_ITEM_PARAM_LEN		65535
#	define ZBX_ITEM_DESCRIPTION_LEN		65535
#	define ZBX_ITEM_POSTS_LEN		65535
#	define ZBX_ITEM_HEADERS_LEN		65535
#endif

#define ZBX_ITEM_PARAMETER_NAME_LEN		255
#define ZBX_ITEM_PARAMETER_VALUE_LEN		2048
#define ZBX_ITEM_TAG_FIELD_LEN			255

/* common tag/value field lengths for all tags */
#define ZBX_DB_TAG_NAME_LEN			255
#define ZBX_DB_TAG_VALUE_LEN			255

#define ZBX_HISTORY_STR_VALUE_LEN		255
#define ZBX_HISTORY_TEXT_VALUE_LEN		65535
#define ZBX_HISTORY_LOG_VALUE_LEN		65535

#define ZBX_HISTORY_LOG_SOURCE_LEN		64
#define ZBX_HISTORY_LOG_SOURCE_LEN_MAX		(ZBX_HISTORY_LOG_SOURCE_LEN + 1)

#define ZBX_GRAPH_NAME_LEN			128
#define ZBX_GRAPH_ITEM_COLOR_LEN		6
#define ZBX_GRAPH_ITEM_COLOR_LEN_MAX		(ZBX_GRAPH_ITEM_COLOR_LEN + 1)

#define ZBX_DSERVICE_VALUE_LEN		255
#define ZBX_MAX_DISCOVERED_VALUE_SIZE	(ZBX_DSERVICE_VALUE_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1)

typedef zbx_uint64_t	(*zbx_dc_get_nextid_func_t)(const char *table_name, int num);

#ifdef HAVE_ORACLE
#	define ZBX_PLSQL_BEGIN	"begin\n"
#	define ZBX_PLSQL_END	"end;"
#	define	zbx_db_begin_multiple_update(sql, sql_alloc, sql_offset)			\
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_PLSQL_BEGIN)
#	define	zbx_db_end_multiple_update(sql, sql_alloc, sql_offset)			\
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ZBX_PLSQL_END)
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
#	define	zbx_db_begin_multiple_update(sql, sql_alloc, sql_offset)	do {} while (0)
#	define	zbx_db_end_multiple_update(sql, sql_alloc, sql_offset)	do {} while (0)
#	ifdef HAVE_MYSQL
#		define	ZBX_SQL_STRCMP		"%s binary '%s'"
#	else
#		define	ZBX_SQL_STRCMP		"%s'%s'"
#	endif
#	define	ZBX_SQL_STRVAL_EQ(str)	"=", str
#	define	ZBX_SQL_STRVAL_NE(str)	"<>", str
#endif

#ifdef HAVE_MYSQL
#	define ZBX_SQL_CONCAT()		"concat(%s,%s)"
#else
#	define ZBX_SQL_CONCAT()		"%s||%s"
#endif

#define ZBX_SQL_NULLCMP(f1, f2)	"((" f1 " is null and " f2 " is null) or " f1 "=" f2 ")"

#define ZBX_DBROW2UINT64(uint, row)			\
	do {						\
		if (SUCCEED == zbx_db_is_null(row))	\
			uint = 0;			\
		else					\
			zbx_is_uint64(row, &uint);	\
	}						\
	while (0)

#define ZBX_DB_MAX_ID	(zbx_uint64_t)__UINT64_C(0x7fffffffffffffff)

#ifdef HAVE_MYSQL
#	define ZBX_SQL_SORT_ASC(field)	field " asc"
#	define ZBX_SQL_SORT_DESC(field)	field " desc"
#else
#	define ZBX_SQL_SORT_ASC(field)	field " asc nulls first"
#	define ZBX_SQL_SORT_DESC(field)	field " desc nulls last"
#endif

typedef struct
{
	zbx_uint64_t	druleid;
	zbx_uint64_t	unique_dcheckid;
	char		*iprange;
	char		*name;
}
zbx_db_drule;

typedef struct
{
	zbx_uint64_t	dhostid;
	int		status;
	int		lastup;
	int		lastdown;
}
zbx_db_dhost;

typedef struct
{
	zbx_uint64_t	triggerid;
	char		*description;
	char		*expression;
	char		*recovery_expression;
	char		*url;
	char		*url_name;
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
zbx_db_trigger;

/* temporary cache of trigger related data */
typedef struct
{
	zbx_uint64_t		serviceid;
	char			*name;
	char			*description;
	zbx_vector_uint64_t	eventids;
	zbx_vector_ptr_t	events;
	zbx_vector_tags_t	service_tags;
}
zbx_db_service;

typedef struct
{
	zbx_uint64_t		eventid;
	zbx_db_trigger		trigger;
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

	zbx_vector_tags_t	tags;

#define ZBX_FLAGS_DB_EVENT_UNSET		0x0000
#define ZBX_FLAGS_DB_EVENT_CREATE		0x0001
#define ZBX_FLAGS_DB_EVENT_NO_ACTION		0x0002
#define ZBX_FLAGS_DB_EVENT_RECOVER		0x0004
/* flags to indicate data retrieved from DB, used for cause event macros */
#define ZBX_FLAGS_DB_EVENT_RETRIEVED_CORE	0x0008
#define ZBX_FLAGS_DB_EVENT_RETRIEVED_TAGS	0x0010
#define ZBX_FLAGS_DB_EVENT_RETRIEVED_TRIGGERS	0x0020
	zbx_uint64_t		flags;
}
zbx_db_event;

/* media types */
typedef enum
{
	MEDIA_TYPE_EMAIL = 0,
	MEDIA_TYPE_EXEC,
	MEDIA_TYPE_SMS,
	MEDIA_TYPE_WEBHOOK = 4
}
zbx_media_type_t;

/* alert statuses */
typedef enum
{
	ALERT_STATUS_NOT_SENT = 0,
	ALERT_STATUS_SENT,
	ALERT_STATUS_FAILED,
	ALERT_STATUS_NEW
}
zbx_alert_status_t;

/* escalation statuses */
typedef enum
{
	ESCALATION_STATUS_ACTIVE = 0,
	ESCALATION_STATUS_RECOVERY,	/* only in server code, never in DB, deprecated */
	ESCALATION_STATUS_SLEEP,
	ESCALATION_STATUS_COMPLETED	/* only in server code, never in DB */
}
zbx_escalation_status_t;

/* alert types */
typedef enum
{
	ALERT_TYPE_MESSAGE = 0,
	ALERT_TYPE_COMMAND
}
zbx_alert_type_t;

typedef enum
{
	ZBX_PROTOTYPE_STATUS_ENABLED,
	ZBX_PROTOTYPE_STATUS_DISABLED,
	ZBX_PROTOTYPE_STATUS_COUNT
}
zbx_prototype_status_t;

typedef enum
{
	ZBX_PROTOTYPE_DISCOVER,
	ZBX_PROTOTYPE_NO_DISCOVER,
	ZBX_PROTOTYPE_DISCOVER_COUNT
}
zbx_prototype_discover_t;

typedef struct ZBX_DB_MEDIATYPE
{
	zbx_uint64_t		mediatypeid;
	zbx_media_type_t	type;
	char			*smtp_server;
	char			*smtp_helo;
	char			*smtp_email;
	char			*exec_path;
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
zbx_db_mediatype;

void	zbx_db_mediatype_clean(zbx_db_mediatype *mt);
void	zbx_serialize_mediatype(unsigned char **data, zbx_uint32_t *data_alloc, zbx_uint32_t *data_offset,
		const zbx_db_mediatype *mt);
zbx_uint32_t	zbx_deserialize_mediatype(const unsigned char *data, zbx_db_mediatype *mt);

typedef struct
{
	zbx_uint64_t	alertid;
	zbx_uint64_t	actionid;
	int		clock;
	zbx_uint64_t	mediatypeid;
	char		*sendto;
	char		*subject;
	char		*message;
	zbx_alert_status_t	status;
	int		retries;
}
zbx_db_alert;

typedef struct
{
	zbx_uint64_t	housekeeperid;
	char		*tablename;
	char		*field;
	zbx_uint64_t	value;
}
zbx_db_housekeeper;

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
zbx_db_httptest;

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
zbx_db_httpstep;

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
zbx_db_escalation;

typedef struct
{
	zbx_uint64_t	actionid;
	char		*name;
	int		esc_period;
	unsigned char	eventsource;
	unsigned char	pause_suppressed;
	unsigned char	pause_symptoms;
	unsigned char	recovery;
	unsigned char	status;
	unsigned char	notify_if_canceled;
}
zbx_db_action;

typedef struct
{
	zbx_uint64_t	acknowledgeid;
	zbx_uint64_t	userid;
	char		*message;
	int		clock;
	int		action;
	int		old_severity;
	int		new_severity;
	int		suppress_until;
}
zbx_db_acknowledge;

typedef struct
{
	zbx_uint64_t	service_alarmid;
	int		value;
	int		clock;
}
zbx_service_alarm_t;

zbx_config_dbhigh_t	*zbx_config_dbhigh_new(void);
void			zbx_config_dbhigh_free(zbx_config_dbhigh_t *config_dbhigh);

void	zbx_init_library_dbhigh(const zbx_config_dbhigh_t *config_dbhigh);
int	zbx_db_init(zbx_dc_get_nextid_func_t cb_nextid, unsigned char program, char **error);
void	zbx_db_deinit(void);

void	zbx_db_init_autoincrement_options(void);

int	zbx_db_connect(int flag);
void	zbx_db_close(void);

int	zbx_db_validate_config_features(unsigned char program_type, const zbx_config_dbhigh_t *config_dbhig);
#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
void	zbx_db_validate_config(const zbx_config_dbhigh_t *config_dbhigh);
#endif

#ifdef HAVE_ORACLE
void	zbx_db_statement_prepare(const char *sql);
void	zbx_db_table_prepare(const char *tablename, struct zbx_json *json);
#endif
int		zbx_db_execute(const char *fmt, ...) __zbx_attr_format_printf(1, 2);
int		zbx_db_execute_once(const char *fmt, ...) __zbx_attr_format_printf(1, 2);
DB_RESULT	zbx_db_select_once(const char *fmt, ...) __zbx_attr_format_printf(1, 2);
DB_RESULT	zbx_db_select(const char *fmt, ...) __zbx_attr_format_printf(1, 2);
DB_RESULT	zbx_db_select_n(const char *query, int n);
DB_ROW		zbx_db_fetch(DB_RESULT result);
int		zbx_db_is_null(const char *field);
void		zbx_db_begin(void);
int		zbx_db_commit(void);
void		zbx_db_rollback(void);
int		zbx_db_end(int ret);

const ZBX_TABLE	*zbx_db_get_table(const char *tablename);
const ZBX_FIELD	*zbx_db_get_field(const ZBX_TABLE *table, const char *fieldname);
int		zbx_db_validate_field_size(const char *tablename, const char *fieldname, const char *str);
#define zbx_db_get_maxid(table)	zbx_db_get_maxid_num(table, 1)
zbx_uint64_t	zbx_db_get_maxid_num(const char *tablename, int num);

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
	char		key[ZBX_ITEM_KEY_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	int		drawtype;
	int		sortorder;
	char		color[ZBX_GRAPH_ITEM_COLOR_LEN_MAX];
	int		yaxisside;
	int		calc_fnc;
	int		type;
	unsigned char	flags;
}
zbx_graph_items;

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
		(ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE | ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE |		\
		ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE | ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR)
#define ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT		0x1000
#define ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT	0x2000
	zbx_uint64_t			flags;
}
zbx_trigger_diff_t;

void	zbx_db_save_trigger_changes(const zbx_vector_ptr_t *trigger_diff);
void	zbx_trigger_diff_free(zbx_trigger_diff_t *diff);
void	zbx_append_trigger_diff(zbx_vector_ptr_t *trigger_diff, zbx_uint64_t triggerid, unsigned char priority,
		zbx_uint64_t flags, unsigned char value, unsigned char state, int lastchange, const char *error);

char	*zbx_db_dyn_escape_field(const char *table_name, const char *field_name, const char *src);
char	*zbx_db_dyn_escape_string(const char *src);
char	*zbx_db_dyn_escape_string_len(const char *src, size_t length);
char	*zbx_db_dyn_escape_like_pattern(const char *src);

void	zbx_db_add_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const zbx_uint64_t *values, const int num);
void	zbx_db_add_str_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const char **values, const int num);

int	zbx_check_user_permissions(const zbx_uint64_t *userid, const zbx_uint64_t *recipient_userid);

const char	*zbx_host_string(zbx_uint64_t hostid);
const char	*zbx_host_key_string(zbx_uint64_t itemid);
const char	*zbx_user_string(zbx_uint64_t userid);
int	zbx_db_get_user_names(zbx_uint64_t userid, char **username, char **name, char **surname);

void	zbx_db_register_host(zbx_uint64_t proxy_hostid, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flag,
		int now);
void	zbx_db_register_host_prepare(zbx_vector_ptr_t *autoreg_hosts, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flag,
		int now);
void	zbx_db_register_host_flush(zbx_vector_ptr_t *autoreg_hosts, zbx_uint64_t proxy_hostid);
void	zbx_db_register_host_clean(zbx_vector_ptr_t *autoreg_hosts);

void	zbx_db_proxy_register_host(const char *host, const char *ip, const char *dns, unsigned short port,
		unsigned int connection_type, const char *host_metadata, unsigned short flag, int now);
int	zbx_db_execute_overflowed_sql(char **sql, size_t *sql_alloc, size_t *sql_offset);
char	*zbx_db_get_unique_hostname_by_sample(const char *host_name_sample, const char *field_name);

const char	*zbx_db_sql_id_ins(zbx_uint64_t id);
const char	*zbx_db_sql_id_cmp(zbx_uint64_t id);

typedef enum
{
	ZBX_CONN_DEFAULT = 0,
	ZBX_CONN_IP,
	ZBX_CONN_DNS,
}
zbx_conn_flags_t;

const char	*zbx_db_get_inventory_field(unsigned char inventory_link);

int	zbx_db_table_exists(const char *table_name);
int	zbx_db_field_exists(const char *table_name, const char *field_name);
#ifndef HAVE_SQLITE3
int	zbx_db_trigger_exists(const char *table_name, const char *trigger_name);
int	zbx_db_index_exists(const char *table_name, const char *index_name);
int	zbx_db_pk_exists(const char *table_name);
#endif

int	zbx_db_prepare_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids, char **sql,
		size_t	*sql_alloc, size_t *sql_offset);
int	zbx_db_execute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids);
int	zbx_db_lock_record(const char *table, zbx_uint64_t id, const char *add_field, zbx_uint64_t add_id);
int	zbx_db_lock_records(const char *table, const zbx_vector_uint64_t *ids);
int	zbx_db_lock_ids(const char *table_name, const char *field_name, zbx_vector_uint64_t *ids);

#define zbx_db_lock_hostid(id)			zbx_db_lock_record("hosts", id, NULL, 0)
#define zbx_db_lock_triggerid(id)		zbx_db_lock_record("triggers", id, NULL, 0)
#define zbx_db_lock_druleid(id)			zbx_db_lock_record("drules", id, NULL, 0)
#define zbx_db_lock_dcheckid(dcheckid, druleid)	zbx_db_lock_record("dchecks", dcheckid, "druleid", druleid)
#define zbx_db_lock_graphid(id)			zbx_db_lock_record("graphs", id, NULL, 0)
#define zbx_db_lock_hostids(ids)		zbx_db_lock_records("hosts", ids)
#define zbx_db_lock_triggerids(ids)		zbx_db_lock_records("triggers", ids)
#define zbx_db_lock_itemids(ids)		zbx_db_lock_records("items", ids)
#define zbx_db_lock_group_prototypeids(ids)	zbx_db_lock_records("group_prototype", ids)

void	zbx_db_select_uint64(const char *sql, zbx_vector_uint64_t *ids);

void	zbx_db_check_character_set(void);

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

int	zbx_db_get_user_by_active_session(const char *sessionid, zbx_user_t *user);
int	zbx_db_get_user_by_auth_token(const char *formatted_auth_token_hash, zbx_user_t *user);
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

typedef struct
{
	zbx_uint64_t			hostid;
	unsigned char			compress;
	char				*version_str;
	int				version_int;
	zbx_proxy_compatibility_t	compatibility;
	int				lastaccess;
	int				last_version_error_time;
	int				proxy_delay;
	int				more_data;
	zbx_proxy_suppress_t		nodata_win;

#define ZBX_FLAGS_PROXY_DIFF_UNSET				__UINT64_C(0x0000)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_COMPRESS			__UINT64_C(0x0001)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION			__UINT64_C(0x0002)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTACCESS			__UINT64_C(0x0004)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTERROR			__UINT64_C(0x0008)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_PROXYDELAY			__UINT64_C(0x0010)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_SUPPRESS_WIN		__UINT64_C(0x0020)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_CONFIG			__UINT64_C(0x0080)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE (			\
		ZBX_FLAGS_PROXY_DIFF_UPDATE_COMPRESS |	\
		ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION |	\
		ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTACCESS)
	zbx_uint64_t			flags;
}
zbx_proxy_diff_t;

int	zbx_db_lock_maintenanceids(zbx_vector_uint64_t *maintenanceids);

void	zbx_db_save_item_changes(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_vector_ptr_t *item_diff,
		zbx_uint64_t mask);

int	zbx_db_check_instanceid(void);

/* tags */
typedef struct
{
	zbx_uint64_t	tagid;
	char		*tag_orig;
	char		*tag;
	char		*value_orig;
	char		*value;
	int		automatic;
	int		automatic_orig;
#define ZBX_FLAG_DB_TAG_UNSET			__UINT64_C(0x00000000)
#define ZBX_FLAG_DB_TAG_UPDATE_AUTOMATIC	__UINT64_C(0x00000001)
#define ZBX_FLAG_DB_TAG_UPDATE_VALUE		__UINT64_C(0x00000002)
#define ZBX_FLAG_DB_TAG_UPDATE_TAG		__UINT64_C(0x00000004)
#define ZBX_FLAG_DB_TAG_REMOVE			__UINT64_C(0x80000000)
#define ZBX_FLAG_DB_TAG_UPDATE	(ZBX_FLAG_DB_TAG_UPDATE_TAG | ZBX_FLAG_DB_TAG_UPDATE_VALUE|	\
		ZBX_FLAG_DB_TAG_UPDATE_AUTOMATIC)
	zbx_uint64_t	flags;
}
zbx_db_tag_t;

ZBX_PTR_VECTOR_DECL(db_tag_ptr, zbx_db_tag_t *)

#define ZBX_DB_TAG_NORMAL	0
#define ZBX_DB_TAG_AUTOMATIC	1

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

int	zbx_db_check_version_info(struct zbx_db_version_info_t *info, int allow_unsupported,
		unsigned char program_type);
void	zbx_db_version_info_clear(struct zbx_db_version_info_t *version_info);

#define ZBX_MAX_HRECORDS	1000
#define ZBX_MAX_HRECORDS_TOTAL	10000

#define ZBX_PROXY_DATA_DONE	0
#define ZBX_PROXY_DATA_MORE	1

void	zbx_calc_timestamp(const char *line, int *timestamp, const char *format);

char	*zbx_get_proxy_protocol_version_str(const struct zbx_json_parse *jp);
int	zbx_get_proxy_protocol_version_int(const char *version_str);

/* condition evaluation types */
#define ZBX_CONDITION_EVAL_TYPE_AND_OR			0
#define ZBX_CONDITION_EVAL_TYPE_AND			1
#define ZBX_CONDITION_EVAL_TYPE_OR			2
#define ZBX_CONDITION_EVAL_TYPE_EXPRESSION		3

/* condition types */
#define ZBX_CONDITION_TYPE_HOST_GROUP			0
#define ZBX_CONDITION_TYPE_HOST				1
#define ZBX_CONDITION_TYPE_TRIGGER			2
#define ZBX_CONDITION_TYPE_TRIGGER_NAME			3
#define ZBX_CONDITION_TYPE_TRIGGER_SEVERITY		4
/* #define ZBX_CONDITION_TYPE_TRIGGER_VALUE		5	deprecated */
#define ZBX_CONDITION_TYPE_TIME_PERIOD			6
#define ZBX_CONDITION_TYPE_DHOST_IP			7
#define ZBX_CONDITION_TYPE_DSERVICE_TYPE		8
#define ZBX_CONDITION_TYPE_DSERVICE_PORT		9
#define ZBX_CONDITION_TYPE_DSTATUS			10
#define ZBX_CONDITION_TYPE_DUPTIME			11
#define ZBX_CONDITION_TYPE_DVALUE			12
#define ZBX_CONDITION_TYPE_HOST_TEMPLATE		13
#define ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED		14
/* #define ZBX_CONDITION_TYPE_APPLICATION		15	deprecated */
#define ZBX_CONDITION_TYPE_SUPPRESSED			16
#define ZBX_CONDITION_TYPE_DRULE			18
#define ZBX_CONDITION_TYPE_DCHECK			19
#define ZBX_CONDITION_TYPE_PROXY			20
#define ZBX_CONDITION_TYPE_DOBJECT			21
#define ZBX_CONDITION_TYPE_HOST_NAME			22
#define ZBX_CONDITION_TYPE_EVENT_TYPE			23
#define ZBX_CONDITION_TYPE_HOST_METADATA		24
#define ZBX_CONDITION_TYPE_EVENT_TAG			25
#define ZBX_CONDITION_TYPE_EVENT_TAG_VALUE		26
#define ZBX_CONDITION_TYPE_SERVICE			27
#define ZBX_CONDITION_TYPE_SERVICE_NAME			28

typedef struct
{
	zbx_uint64_t	autoreg_hostid;
	zbx_uint64_t	hostid;
	char		*host;
	char		*ip;
	char		*dns;
	char		*host_metadata;
	int		now;
	unsigned short	port;
	unsigned short	flag;
	unsigned int	connection_type;
}
zbx_autoreg_host_t;

#endif
