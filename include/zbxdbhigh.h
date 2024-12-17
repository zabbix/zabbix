/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_DBHIGH_H
#define ZABBIX_DBHIGH_H

#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxversion.h"
#include "zbxtime.h"

#include "zbxtagfilter.h"

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
#define ZBX_ITEM_USERNAME_LEN			255
#define ZBX_ITEM_USERNAME_LEN_MAX		(ZBX_ITEM_USERNAME_LEN + 1)
#define ZBX_ITEM_PASSWORD_LEN			255
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
#define ZBX_ITEM_PARAM_LEN			65535
#define ZBX_ITEM_DESCRIPTION_LEN		65535
#define ZBX_ITEM_POSTS_LEN			65535
#define ZBX_ITEM_HEADERS_LEN			65535
#define ZBX_ITEM_PARAMETER_NAME_LEN		255
#define ZBX_ITEM_PARAMETER_VALUE_LEN		2048
#define ZBX_ITEM_TAG_FIELD_LEN			255

/* common tag/value field lengths for all tags */
#define ZBX_DB_TAG_NAME_LEN			255
#define ZBX_DB_TAG_VALUE_LEN			255

#define ZBX_HISTORY_STR_VALUE_LEN		255
#define ZBX_HISTORY_TEXT_VALUE_LEN		65535
#define ZBX_HISTORY_LOG_VALUE_LEN		65535

/* Binary item type can only be as a dependent item. */
#define ZBX_HISTORY_BIN_VALUE_LEN		(ZBX_MEBIBYTE * 16)

#define ZBX_HISTORY_LOG_SOURCE_LEN		64
#define ZBX_HISTORY_LOG_SOURCE_LEN_MAX		(ZBX_HISTORY_LOG_SOURCE_LEN + 1)

#define ZBX_GRAPH_NAME_LEN			128
#define ZBX_GRAPH_ITEM_COLOR_LEN		6
#define ZBX_GRAPH_ITEM_COLOR_LEN_MAX		(ZBX_GRAPH_ITEM_COLOR_LEN + 1)

#define ZBX_DSERVICE_VALUE_LEN			255
#define ZBX_MAX_DISCOVERED_VALUE_SIZE	(ZBX_DSERVICE_VALUE_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1)

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

	zbx_vector_uint64_t	*maintenanceids;

	zbx_vector_tags_ptr_t	tags;

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

ZBX_PTR_VECTOR_DECL(db_event, zbx_db_event *)

/* data structures used to create new and recover existing escalations */
typedef struct
{
	zbx_uint64_t	actionid;
	zbx_uint64_t	escalationid;
	zbx_db_event	*event;
}
zbx_escalation_new_t;

ZBX_PTR_VECTOR_DECL(escalation_new_ptr, zbx_escalation_new_t *)

/* temporary cache of trigger related data */
typedef struct
{
	zbx_uint64_t		serviceid;
	char			*name;
	char			*description;
	zbx_vector_uint64_t	eventids;
	zbx_vector_db_event_t	events;
	zbx_vector_tags_ptr_t	service_tags;
}
zbx_db_service;

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
	unsigned char		message_format;
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
	char		*sendto;
	char		*subject;
	char		*message;
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

ZBX_PTR_VECTOR_DECL(trigger_diff_ptr, zbx_trigger_diff_t *)

int	zbx_trigger_diff_compare_func(const void *d1, const void *d2);

void	zbx_db_save_trigger_changes(const zbx_vector_trigger_diff_ptr_t *trigger_diff);
void	zbx_trigger_diff_free(zbx_trigger_diff_t *diff);
void	zbx_append_trigger_diff(zbx_vector_trigger_diff_ptr_t *trigger_diff, zbx_uint64_t triggerid,
		unsigned char priority, zbx_uint64_t flags, unsigned char value, unsigned char state, int lastchange,
		const char *error);

int	zbx_check_user_permissions(const zbx_uint64_t *userid, const zbx_uint64_t *recipient_userid);

const char	*zbx_host_string(zbx_uint64_t hostid);
const char	*zbx_host_key_string(zbx_uint64_t itemid);
const char	*zbx_user_string(zbx_uint64_t userid);

typedef struct
{
	zbx_uint64_t			connectorid;
	int				tags_evaltype;
	zbx_vector_match_tags_ptr_t	connector_tags;
	int				item_value_type;
}
zbx_connector_filter_t;

ZBX_PTR_VECTOR_DECL(connector_filter, zbx_connector_filter_t)

/* events callbacks */
typedef zbx_db_event	*(*zbx_add_event_func_t)(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_tags_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag,
		unsigned char trigger_value, const char *trigger_opdata, const char *event_name, const char *error);

typedef int	(*zbx_process_events_func_t)(zbx_vector_trigger_diff_ptr_t *trigger_diff,
		zbx_vector_uint64_t *triggerids_lock, zbx_vector_escalation_new_ptr_t *escalations);
typedef void	(*zbx_clean_events_func_t)(void);
typedef void	(*zbx_reset_event_recovery_func_t)(void);
typedef void	(*zbx_export_events_func_t)(int events_export_enabled, zbx_vector_connector_filter_t *connector_filters,
		unsigned char **data, size_t *data_alloc, size_t *data_offset);
typedef void	(*zbx_events_update_itservices_func_t)(void);

typedef struct
{
	zbx_add_event_func_t			add_event_cb;
	zbx_process_events_func_t		process_events_cb;
	zbx_clean_events_func_t			clean_events_cb;
	zbx_reset_event_recovery_func_t		reset_event_recovery_cb;
	zbx_export_events_func_t		export_events_cb;
	zbx_events_update_itservices_func_t	events_update_itservices_cb;
} zbx_events_funcs_t;

/* events callbacks end */

int	zbx_db_get_user_names(zbx_uint64_t userid, char **username, char **name, char **surname);
char	*zbx_db_get_unique_hostname_by_sample(const char *host_name_sample, const char *field_name);

typedef enum
{
	ZBX_CONN_DEFAULT = 0,
	ZBX_CONN_IP,
	ZBX_CONN_DNS
}
zbx_conn_flags_t;

const char	*zbx_db_get_inventory_field(unsigned char inventory_link);

#define zbx_db_lock_hostid(id)			zbx_db_lock_record("hosts", id, NULL, 0)
#define zbx_db_lock_triggerid(id)		zbx_db_lock_record("triggers", id, NULL, 0)
#define zbx_db_lock_druleid(id)			zbx_db_lock_record("drules", id, NULL, 0)
#define zbx_db_lock_dcheckid(dcheckid, druleid)	zbx_db_lock_record("dchecks", dcheckid, "druleid", druleid)
#define zbx_db_lock_graphid(id)			zbx_db_lock_record("graphs", id, NULL, 0)
#define zbx_db_lock_hostids(ids)		zbx_db_lock_records("hosts", ids)
#define zbx_db_lock_triggerids(ids)		zbx_db_lock_records("triggers", ids)
#define zbx_db_lock_itemids(ids)		zbx_db_lock_records("items", ids)
#define zbx_db_lock_group_prototypeids(ids)	zbx_db_lock_records("group_prototype", ids)
#define zbx_db_lock_hgsetids(ids)		zbx_db_lock_records("hgset", ids)

int	zbx_db_get_database_type(void);

typedef struct
{
	zbx_uint64_t		eventid;
	int			clock;
	int			ns;
	int			value;
	int			severity;
	int			mtime;
	zbx_vector_tags_ptr_t	tags;

	zbx_vector_uint64_t	*maintenanceids;
}
zbx_event_t;

ZBX_PTR_VECTOR_DECL(events_ptr, zbx_event_t *)

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

ZBX_PTR_VECTOR_DECL(item_diff_ptr, zbx_item_diff_t *)

void	zbx_item_diff_free(zbx_item_diff_t *item_diff);

int	zbx_item_diff_compare_func(const void *d1, const void *d2);

typedef struct
{
	zbx_uint64_t			hostid;
	unsigned char			compress;
	char				*version_str;
	int				version_int;
	zbx_proxy_compatibility_t	compatibility;
	time_t				lastaccess;
	time_t				last_version_error_time;
	int				proxy_delay;
	int				more_data;
	zbx_proxy_suppress_t		nodata_win;

#define ZBX_FLAGS_PROXY_DIFF_UNSET				__UINT64_C(0x0000)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION			__UINT64_C(0x0002)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTACCESS			__UINT64_C(0x0004)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTERROR			__UINT64_C(0x0008)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_PROXYDELAY			__UINT64_C(0x0010)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_SUPPRESS_WIN		__UINT64_C(0x0020)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE_CONFIG			__UINT64_C(0x0080)
#define ZBX_FLAGS_PROXY_DIFF_UPDATE (			\
		ZBX_FLAGS_PROXY_DIFF_UPDATE_VERSION |	\
		ZBX_FLAGS_PROXY_DIFF_UPDATE_LASTACCESS)
	zbx_uint64_t			flags;
}
zbx_proxy_diff_t;

int	zbx_db_lock_maintenanceids(zbx_vector_uint64_t *maintenanceids);

void	zbx_db_save_item_changes(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const zbx_vector_item_diff_ptr_t *item_diff, zbx_uint64_t mask);

int	zbx_db_check_instanceid(void);
int	zbx_db_update_software_update_checkid(void);

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
void	zbx_add_tags(zbx_vector_db_tag_ptr_t *hosttags, zbx_vector_db_tag_ptr_t *addtags);
void	zbx_del_tags(zbx_vector_db_tag_ptr_t *hosttags, zbx_vector_db_tag_ptr_t *deltags);

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

ZBX_PTR_VECTOR_DECL(lld_override_operation, zbx_lld_override_operation_t*)

void	zbx_lld_override_operation_free(zbx_lld_override_operation_t *override_operation);

void	zbx_load_lld_override_operations(const zbx_vector_uint64_t *overrideids, char **sql, size_t *sql_alloc,
		zbx_vector_lld_override_operation_t *ops);

#define ZBX_TIMEZONE_DEFAULT_VALUE	"default"

int	zbx_db_check_version_info(struct zbx_db_version_info_t *info, int allow_unsupported,
		unsigned char program_type);
void	zbx_db_version_info_clear(struct zbx_db_version_info_t *version_info);
void	zbx_db_flush_version_requirements(const char *version);

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
#define ZBX_CONDITION_TYPE_EVENT_NAME			3
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

#define PROXY_OPERATING_MODE_ACTIVE	0
#define PROXY_OPERATING_MODE_PASSIVE	1

#endif
