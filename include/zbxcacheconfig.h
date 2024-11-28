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

#ifndef ZABBIX_CACHECONFIG_H
#define ZABBIX_CACHECONFIG_H

#include "zbxdbhigh.h"
#include "zbxcomms.h"
#include "zbxeval.h"
#include "zbxavailability.h"
#include "zbxversion.h"
#include "zbxvault.h"
#include "zbxregexp.h"
#include "zbxtagfilter.h"
#include "zbxautoreg.h"
#include "zbxpgservice.h"
#include "zbxalgo.h"

#define	ZBX_NO_POLLER			255
#define	ZBX_POLLER_TYPE_NORMAL		0
#define	ZBX_POLLER_TYPE_UNREACHABLE	1
#define	ZBX_POLLER_TYPE_IPMI		2
#define	ZBX_POLLER_TYPE_PINGER		3
#define	ZBX_POLLER_TYPE_JAVA		4
#define	ZBX_POLLER_TYPE_HISTORY		5
#define	ZBX_POLLER_TYPE_ODBC		6
#define	ZBX_POLLER_TYPE_HTTPAGENT	7
#define	ZBX_POLLER_TYPE_AGENT		8
#define	ZBX_POLLER_TYPE_SNMP		9
#define ZBX_POLLER_TYPE_INTERNAL	10
#define ZBX_POLLER_TYPE_BROWSER		11
#define	ZBX_POLLER_TYPE_COUNT		12	/* number of poller types */

typedef enum
{
	ZBX_SESSION_TYPE_DATA = 0,
	ZBX_SESSION_TYPE_CONFIG,
	ZBX_SESSION_TYPE_COUNT,
}
zbx_session_type_t;

#define ZBX_MAX_JAVA_ITEMS		32
#define ZBX_MAX_SNMP_ITEMS		128
#define ZBX_MAX_POLLER_ITEMS		128	/* MAX(ZBX_MAX_JAVA_ITEMS, ZBX_MAX_SNMP_ITEMS) */
#define ZBX_MAX_PINGER_ITEMS		128
#define ZBX_MAX_HTTPAGENT_ITEMS		1000
#define ZBX_MAX_AGENT_ITEMS		1000
#define ZBX_MAX_ITEMS			1000

#define ZBX_SNMPTRAP_LOGGING_ENABLED	1

typedef struct
{
	zbx_uint64_t	interfaceid;
	char		ip_orig[ZBX_INTERFACE_IP_LEN_MAX];
	char		dns_orig[ZBX_INTERFACE_DNS_LEN_MAX];
	char		port_orig[ZBX_INTERFACE_PORT_LEN_MAX];
	char		*addr;
	unsigned short	port;
	unsigned char	useip;
	unsigned char	type;
	unsigned char	main;
	unsigned char	available;
	int		disable_until;
	char		error[ZBX_INTERFACE_ERROR_LEN_MAX];
	int		errors_from;
	int		version;
}
zbx_dc_interface_t;

typedef struct
{
	zbx_uint64_t	interfaceid;
	char		*addr;
	unsigned char	type;
	unsigned char	main;
	unsigned char	bulk;
	unsigned char	snmp_version;
	unsigned char	useip;
	char		ip_orig[ZBX_INTERFACE_IP_LEN_MAX];
	char		dns_orig[ZBX_INTERFACE_DNS_LEN_MAX];
	char		port_orig[ZBX_INTERFACE_PORT_LEN_MAX];
}
zbx_dc_interface2_t;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		hostid;
	unsigned char		value_type;
	unsigned char		flags;
	char			*key;
	char			*key_orig;
	char			host[ZBX_HOSTNAME_BUF_LEN];
	zbx_dc_interface_t	interface;
	int			ret;
	int			version;
	AGENT_RESULT		result;
}
zbx_dc_item_context_t;

#define ZBX_HOST_IPMI_USERNAME_LEN	16
#define ZBX_HOST_IPMI_USERNAME_LEN_MAX	(ZBX_HOST_IPMI_USERNAME_LEN + 1)
#define ZBX_HOST_IPMI_PASSWORD_LEN	20
#define ZBX_HOST_IPMI_PASSWORD_LEN_MAX	(ZBX_HOST_IPMI_PASSWORD_LEN + 1)
#define ZBX_HOST_PROXY_ADDRESS_LEN	255
#define ZBX_HOST_PROXY_ADDRESS_LEN_MAX	(ZBX_HOST_PROXY_ADDRESS_LEN + 1)

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxyid;
	zbx_uint64_t	proxy_groupid;
	char		host[ZBX_HOSTNAME_BUF_LEN];
	char		name[ZBX_MAX_HOSTNAME_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	unsigned char	maintenance_status;
	unsigned char	maintenance_type;
	int		maintenance_from;
	signed char	ipmi_authtype;
	unsigned char	ipmi_privilege;
	char		ipmi_username[ZBX_HOST_IPMI_USERNAME_LEN_MAX];
	char		ipmi_password[ZBX_HOST_IPMI_PASSWORD_LEN_MAX];
	unsigned char	monitored_by;
	unsigned char	status;
	unsigned char	tls_connect;
	unsigned char	tls_accept;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	char		tls_issuer[HOST_TLS_ISSUER_LEN_MAX];
	char		tls_subject[HOST_TLS_SUBJECT_LEN_MAX];
	char		tls_psk_identity[HOST_TLS_PSK_IDENTITY_LEN_MAX];
	char		tls_psk[HOST_TLS_PSK_LEN_MAX];
#endif
}
zbx_dc_host_t;

typedef struct
{
	zbx_dc_host_t		host;
	zbx_dc_interface_t	interface;
	zbx_uint64_t		itemid;
	zbx_uint64_t		lastlogsize;
	unsigned char		type;
	unsigned char		snmp_version;
	unsigned char		value_type;
	unsigned char		state;
	unsigned char		snmpv3_securitylevel;
	unsigned char		authtype;
	unsigned char		flags;
	unsigned char		snmpv3_authprotocol;
	unsigned char		snmpv3_privprotocol;
	unsigned char		status;
	unsigned char		follow_redirects;
	unsigned char		post_type;
	unsigned char		retrieve_mode;
	unsigned char		request_method;
	unsigned char		output_format;
	unsigned char		verify_peer;
	unsigned char		verify_host;
	unsigned char		allow_traps;
	char			key_orig[ZBX_ITEM_KEY_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1], *key;
	char			*delay;
	int			mtime;
	char			trapper_hosts[ZBX_ITEM_TRAPPER_HOSTS_LEN_MAX];
	char			logtimefmt[ZBX_ITEM_LOGTIMEFMT_LEN_MAX];
	char			snmp_community_orig[ZBX_ITEM_SNMP_COMMUNITY_LEN_MAX], *snmp_community;
	char			snmp_oid_orig[ZBX_ITEM_SNMP_OID_LEN_MAX], *snmp_oid;
	char			snmpv3_securityname_orig[ZBX_ITEM_SNMPV3_SECURITYNAME_LEN_MAX], *snmpv3_securityname;
	char			snmpv3_authpassphrase_orig[ZBX_ITEM_SNMPV3_AUTHPASSPHRASE_LEN_MAX],
				*snmpv3_authpassphrase;
	char			snmpv3_privpassphrase_orig[ZBX_ITEM_SNMPV3_PRIVPASSPHRASE_LEN_MAX],
				*snmpv3_privpassphrase;
	char			ipmi_sensor[ZBX_ITEM_IPMI_SENSOR_LEN_MAX];
	char			*params;
	char			username_orig[ZBX_ITEM_USERNAME_LEN_MAX], *username;
	char			publickey_orig[ZBX_ITEM_PUBLICKEY_LEN_MAX], *publickey;
	char			privatekey_orig[ZBX_ITEM_PRIVATEKEY_LEN_MAX], *privatekey;
	char			password_orig[ZBX_ITEM_PASSWORD_LEN_MAX], *password;
	char			snmpv3_contextname_orig[ZBX_ITEM_SNMPV3_CONTEXTNAME_LEN_MAX], *snmpv3_contextname;
	char			jmx_endpoint_orig[ZBX_ITEM_JMX_ENDPOINT_LEN_MAX], *jmx_endpoint;
	char			timeout_orig[ZBX_ITEM_TIMEOUT_LEN_MAX];
	int			timeout;
	char			url_orig[ZBX_ITEM_URL_LEN_MAX], *url;
	char			query_fields_orig[ZBX_ITEM_QUERY_FIELDS_LEN_MAX], *query_fields;
	char			*posts;
	char			status_codes_orig[ZBX_ITEM_STATUS_CODES_LEN_MAX], *status_codes;
	char			http_proxy_orig[ZBX_ITEM_HTTP_PROXY_LEN_MAX], *http_proxy;
	char			*headers;
	char			ssl_cert_file_orig[ZBX_ITEM_SSL_CERT_FILE_LEN_MAX], *ssl_cert_file;
	char			ssl_key_file_orig[ZBX_ITEM_SSL_KEY_FILE_LEN_MAX], *ssl_key_file;
	char			ssl_key_password_orig[ZBX_ITEM_SSL_KEY_PASSWORD_LEN_MAX], *ssl_key_password;
	zbx_vector_ptr_pair_t 	script_params;
	char			*error;
	unsigned char		*formula_bin;
	int			snmp_max_repetitions;
}
zbx_dc_item_t;

ZBX_PTR_VECTOR_DECL(dc_item, zbx_dc_item_t *)

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxyid;
	char		host[ZBX_HOSTNAME_BUF_LEN];
	char		name[ZBX_MAX_HOSTNAME_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	signed char	inventory_mode;
	unsigned char	status;
	unsigned char	monitored_by;
}
zbx_history_sync_host_t;

typedef struct
{
	zbx_history_sync_host_t	host;
	zbx_uint64_t		itemid;
	zbx_uint64_t		lastlogsize;
	zbx_uint64_t		valuemapid;
	char			key_orig[ZBX_ITEM_KEY_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	char			*units;
	char			*error;
	char			*history_period, *trends_period;
	int			mtime;
	int			history_sec;
	int			trends_sec;
	int			flags;
	unsigned char		type;
	unsigned char		value_type;
	unsigned char		state;
	unsigned char		inventory_link;
	unsigned char		status;
	unsigned char		history;
	unsigned char		trends;
	unsigned char		has_trigger;
}
zbx_history_sync_item_t;

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxyid;
	char		host[ZBX_HOSTNAME_BUF_LEN];
	char		name[ZBX_MAX_HOSTNAME_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	unsigned char	maintenance_status;
	unsigned char	maintenance_type;
	int		maintenance_from;
	unsigned char	monitored_by;
	unsigned char	status;
	unsigned char	tls_accept;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	char		tls_issuer[HOST_TLS_ISSUER_LEN_MAX];
	char		tls_subject[HOST_TLS_SUBJECT_LEN_MAX];
	char		tls_psk_identity[HOST_TLS_PSK_IDENTITY_LEN_MAX];
	char		tls_psk[HOST_TLS_PSK_LEN_MAX];
#endif
}
zbx_history_recv_host_t;

typedef struct
{
	zbx_history_recv_host_t	host;
	zbx_dc_interface_t		interface;
	zbx_uint64_t		itemid;
	unsigned char		value_type;
	unsigned char		state;
	unsigned char		flags;
	unsigned char		type;
	unsigned char		status;
	unsigned char		allow_traps;
	char			key_orig[ZBX_ITEM_KEY_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1], *key;
	char			trapper_hosts[ZBX_ITEM_TRAPPER_HOSTS_LEN_MAX];
	char			logtimefmt[ZBX_ITEM_LOGTIMEFMT_LEN_MAX];
}
zbx_history_recv_item_t;

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	proxyid;
	const char	*host;
	const char	*key_orig;
	unsigned char	value_type;
}
zbx_dc_evaluate_item_t;

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	triggerid;
	zbx_uint64_t	itemid;
	char		*function;
	char		*parameter;
	unsigned char	type;
}
zbx_dc_function_t;

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	itemid;
	zbx_tag_t	tag;
}
zbx_item_tag_t;

ZBX_PTR_VECTOR_DECL(item_tag, zbx_item_tag_t *)

typedef struct _DC_TRIGGER
{
	zbx_uint64_t		triggerid;
	char			*description;
	char			*expression;
	char			*recovery_expression;

	char			*error;
	char			*new_error;
	char			*correlation_tag;
	char			*opdata;
	char			*event_name;
	unsigned char		*expression_bin;
	unsigned char		*recovery_expression_bin;
	zbx_timespec_t		timespec;
	int			lastchange;
	unsigned char		topoindex;
	unsigned char		priority;
	unsigned char		type;
	unsigned char		value;
	unsigned char		state;
	unsigned char		new_value;
	unsigned char		status;
	unsigned char		recovery_mode;
	unsigned char		correlation_mode;

	unsigned char		flags;

	zbx_vector_tags_ptr_t	tags;
	zbx_vector_uint64_t	itemids;

	zbx_eval_context_t	*eval_ctx;
	zbx_eval_context_t	*eval_ctx_r;
}
zbx_dc_trigger_t;

ZBX_PTR_VECTOR_DECL(dc_trigger, zbx_dc_trigger_t *)

typedef struct
{
	zbx_uint64_t			proxyid;
	zbx_uint64_t			proxy_groupid;
	char				name[ZBX_HOSTNAME_BUF_LEN];
	time_t				proxy_config_nextcheck;
	time_t				proxy_data_nextcheck;
	time_t				proxy_tasks_nextcheck;
	time_t				last_cfg_error_time;	/* time when passive proxy misconfiguration error was */
								/* seen or 0 if no error */
	char				version_str[ZBX_VERSION_BUF_LEN];
	int				version_int;
	zbx_proxy_compatibility_t	compatibility;
	time_t				lastaccess;
	char				addr_orig[ZBX_INTERFACE_ADDR_LEN_MAX];
	char				port_orig[ZBX_INTERFACE_PORT_LEN_MAX];
	char				*addr;
	unsigned short			port;

	unsigned char			tls_connect;
	unsigned char			tls_accept;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	char				tls_issuer[HOST_TLS_ISSUER_LEN_MAX];
	char				tls_subject[HOST_TLS_SUBJECT_LEN_MAX];
	char				tls_psk_identity[HOST_TLS_PSK_IDENTITY_LEN_MAX];
	char				tls_psk[HOST_TLS_PSK_LEN_MAX];
#endif
	zbx_uint64_t			revision;
	zbx_uint64_t			macro_revision;

	char				allowed_addresses[ZBX_HOST_PROXY_ADDRESS_LEN_MAX];
	time_t				last_version_error_time;
}
zbx_dc_proxy_t;

#define ZBX_ACTION_OPCLASS_NONE			0
#define ZBX_ACTION_OPCLASS_NORMAL		1
#define ZBX_ACTION_OPCLASS_RECOVERY		2
#define ZBX_ACTION_OPCLASS_ACKNOWLEDGE		4

typedef struct
{
	zbx_uint64_t			conditionid;
	zbx_uint64_t			actionid;
	char				*value;
	char				*value2;
	unsigned char			conditiontype;
	unsigned char			op;
	zbx_vector_uint64_t		eventids;
}
zbx_condition_t;
ZBX_PTR_VECTOR_DECL(condition_ptr, zbx_condition_t *)

typedef struct
{
	zbx_uint64_t			actionid;
	char				*formula;
	unsigned char			eventsource;
	unsigned char			evaltype;
	unsigned char			opflags;
	zbx_vector_condition_ptr_t	conditions;
}
zbx_action_eval_t;

ZBX_PTR_VECTOR_DECL(action_eval_ptr, zbx_action_eval_t *)

typedef struct
{
	char	*host;
	char	*key;
}
zbx_host_key_t;

ZBX_VECTOR_DECL(host_key, zbx_host_key_t)

/* housekeeping related configuration data */
typedef struct
{
	int		events_trigger;
	int		events_internal;
	int		events_discovery;
	int		events_autoreg;
	int		events_service;
	int		services;
	int		audit;
	int		sessions;
	int		trends;
	int		history;

	unsigned char	services_mode;
	unsigned char	audit_mode;
	unsigned char	sessions_mode;
	unsigned char	events_mode;
	unsigned char	trends_mode;
	unsigned char	trends_global;
	unsigned char	history_mode;
	unsigned char	history_global;
}
zbx_config_hk_t;

typedef struct
{
	char		*extension;
	unsigned char	history_compression_status;
	int		history_compress_older;
}
zbx_config_db_t;

/* global configuration data (loaded from config table) */
typedef struct
{
	/* the fields set by zbx_config_get() function, see ZBX_CONFIG_FLAGS_ defines */
	zbx_uint64_t	flags;

	char		**severity_name;
	zbx_uint64_t	discovery_groupid;
	int		default_inventory_mode;
	unsigned char	snmptrap_logging;
	unsigned char	autoreg_tls_accept;
	char		*default_timezone;
	int		auditlog_enabled;
	int		auditlog_mode;

	/* database configuration data for ZBX_CONFIG_DB_EXTENSION_* extensions */
	zbx_config_db_t	db;

	/* housekeeping related configuration data */
	zbx_config_hk_t	hk;
}
zbx_config_t;

#define ZBX_CONFIG_FLAGS_SEVERITY_NAME			__UINT64_C(0x0000000000000001)
#define ZBX_CONFIG_FLAGS_DISCOVERY_GROUPID		__UINT64_C(0x0000000000000002)
#define ZBX_CONFIG_FLAGS_DEFAULT_INVENTORY_MODE		__UINT64_C(0x0000000000000004)
#define ZBX_CONFIG_FLAGS_SNMPTRAP_LOGGING		__UINT64_C(0x0000000000000010)
#define ZBX_CONFIG_FLAGS_HOUSEKEEPER			__UINT64_C(0x0000000000000020)
#define ZBX_CONFIG_FLAGS_DB_EXTENSION			__UINT64_C(0x0000000000000040)
#define ZBX_CONFIG_FLAGS_AUTOREG_TLS_ACCEPT		__UINT64_C(0x0000000000000080)
#define ZBX_CONFIG_FLAGS_DEFAULT_TIMEZONE		__UINT64_C(0x0000000000000100)
#define ZBX_CONFIG_FLAGS_AUDITLOG_ENABLED		__UINT64_C(0x0000000000000200)
#define ZBX_CONFIG_FLAGS_AUDITLOG_MODE			__UINT64_C(0x0000000000000400)

typedef struct
{
	zbx_uint64_t	hostid;
	unsigned char	idx;
	const char	*field_name;
	char		*value;
}
zbx_inventory_value_t;

ZBX_PTR_VECTOR_DECL(inventory_value_ptr, zbx_inventory_value_t *)

typedef struct
{
	char	*tag;
}
zbx_corr_condition_tag_t;

typedef struct
{
	char		*tag;
	char		*value;
	unsigned char	op;
}
zbx_corr_condition_tag_value_t;

typedef struct
{
	zbx_uint64_t	groupid;
	unsigned char	op;
}
zbx_corr_condition_group_t;

typedef struct
{
	char	*oldtag;
	char	*newtag;
}
zbx_corr_condition_tag_pair_t;

typedef union
{
	zbx_corr_condition_tag_t	tag;
	zbx_corr_condition_tag_value_t	tag_value;
	zbx_corr_condition_group_t	group;
	zbx_corr_condition_tag_pair_t	tag_pair;
}
zbx_corr_condition_data_t;

typedef struct
{
	zbx_uint64_t			corr_conditionid;
	int				type;
	zbx_corr_condition_data_t	data;
}
zbx_corr_condition_t;

ZBX_PTR_VECTOR_DECL(corr_condition_ptr, zbx_corr_condition_t *)

typedef struct
{
	unsigned char	type;
}
zbx_corr_operation_t;

ZBX_PTR_VECTOR_DECL(corr_operation_ptr, zbx_corr_operation_t *)

void	zbx_corr_operation_free(zbx_corr_operation_t *corr_operation);

typedef struct
{
	zbx_uint64_t			correlationid;
	char				*name;
	char				*formula;
	unsigned char			evaltype;

	zbx_vector_corr_condition_ptr_t	conditions;
	zbx_vector_corr_operation_ptr_t	operations;
}
zbx_correlation_t;

ZBX_PTR_VECTOR_DECL(correlation_ptr, zbx_correlation_t *)

int	zbx_correlation_compare_func(const void *d1, const void *d2);

typedef struct
{
	zbx_vector_correlation_ptr_t	correlations;
	zbx_hashset_t			conditions;

	/* Configuration synchronization timestamp of the rules. */
	/* Update the cache if this timestamp is less than the   */
	/* current configuration synchronization timestamp.      */
	int			sync_ts;
}
zbx_correlation_rules_t;

/* item queue data */
typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	proxyid;
	int		type;
	int		nextcheck;
}
zbx_queue_item_t;

ZBX_PTR_VECTOR_DECL(queue_item_ptr, zbx_queue_item_t *)

typedef union
{
	zbx_uint64_t	ui64;
	double		dbl;
}
zbx_counter_value_t;

typedef struct
{
	zbx_uint64_t		proxyid;
	zbx_counter_value_t	counter_value;
}
zbx_proxy_counter_t;

ZBX_PTR_VECTOR_DECL(proxy_counter_ptr, zbx_proxy_counter_t *)

void     zbx_proxy_counter_ptr_free(zbx_proxy_counter_t *proxy_counter);

typedef struct
{
	unsigned char	type;
	unsigned char	error_handler;
	char		*params;
	char		*error_handler_params;
}
zbx_preproc_op_t;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		hostid;
	unsigned char		type;
	unsigned char		value_type;

	int			dep_itemids_num;
	int			preproc_ops_num;
	zbx_uint64_t		revision;
	zbx_uint64_t		preproc_revision;

	zbx_uint64_pair_t	*dep_itemids;
	zbx_preproc_op_t	*preproc_ops;
}
zbx_preproc_item_t;

typedef struct
{
	zbx_uint64_t		connectorid;
	zbx_uint64_t		revision;
	unsigned char		protocol;
	unsigned char		data_type;
	char			*url_orig, *url;
	int			max_records;
	int			max_senders;
	char			*timeout_orig, *timeout;
	unsigned char		max_attempts;
	char			*token_orig, *token;
	char			*http_proxy_orig, *http_proxy;
	unsigned char		authtype;
	char			*username_orig, *username;
	char			*password_orig, *password;
	unsigned char		verify_peer;
	unsigned char		verify_host;
	char			*ssl_cert_file_orig, *ssl_cert_file;
	char			*ssl_key_file_orig, *ssl_key_file;
	char			*ssl_key_password_orig, *ssl_key_password;

	zbx_hashset_t		data_point_links;
	zbx_list_t		data_point_link_queue;
	int			time_flush;
	int			senders;

	int			item_value_type;
	char			*attempt_interval;
}
zbx_connector_t;


/* the configuration cache statistics */
typedef struct
{
	zbx_uint64_t	hosts;
	zbx_uint64_t	items;
	zbx_uint64_t	items_unsupported;
	double		requiredperformance;
}
zbx_config_cache_info_t;

typedef struct
{
	zbx_uint64_t	dcheckid;
	zbx_uint64_t	druleid;
	unsigned char	type;
	char		*key_;
	char		*snmp_community;
	char		*ports;
	char		*snmpv3_securityname;
	unsigned char	snmpv3_securitylevel;
	char		*snmpv3_authpassphrase;
	char		*snmpv3_privpassphrase;
	unsigned char	uniq;
	unsigned char	snmpv3_authprotocol;
	unsigned char	snmpv3_privprotocol;
	char		*snmpv3_contextname;
	unsigned char	allow_redirect;
	int		timeout;
}
zbx_dc_dcheck_t;

ZBX_PTR_VECTOR_DECL(dc_dcheck_ptr, zbx_dc_dcheck_t *)

typedef struct
{
	zbx_uint64_t			druleid;
	zbx_uint64_t			proxyid;
	time_t				nextcheck;
	int				delay;
	char				*delay_str;
	char				*iprange;
	unsigned char			status;
	unsigned char			location;
	zbx_uint64_t			revision;
	char				*name;
	zbx_uint64_t			unique_dcheckid;
	zbx_vector_dc_dcheck_ptr_t	dchecks;
	int				concurrency_max;
}
zbx_dc_drule_t;

ZBX_PTR_VECTOR_DECL(dc_drule_ptr, zbx_dc_drule_t *)

int	zbx_is_item_processed_by_server(unsigned char type, const char *key);
int	zbx_is_counted_in_item_queue(unsigned char type, const char *key);
int	zbx_in_maintenance_without_data_collection(unsigned char maintenance_status, unsigned char maintenance_type,
		unsigned char type);

#define ZBX_SYNC_NONE	0
#define ZBX_SYNC_ALL	1

/* initial sync, get all data */
#define ZBX_DBSYNC_INIT		0
/* update sync, get changed data */
#define ZBX_DBSYNC_UPDATE	1

#define ZBX_DBSYNC_STATUS_INITIALIZED	0
#define ZBX_DBSYNC_STATUS_UNKNOWN	-1

typedef enum
{
	ZBX_SYNCED_NEW_CONFIG_NO,
	ZBX_SYNCED_NEW_CONFIG_YES
}
zbx_synced_new_config_t;

#define ZBX_ITEM_GET_INTERFACE		0x0001
#define ZBX_ITEM_GET_HOST		0x0002
#define ZBX_ITEM_GET_HOSTNAME		0x0004
#define ZBX_ITEM_GET_HOSTINFO		0x0008
#define ZBX_ITEM_GET_TRAPPER		0x0010

#define ZBX_ITEM_GET_DEFAULT		((unsigned int)~0)

#define ZBX_ITEM_GET_SYNC		0
#define ZBX_ITEM_GET_SYNC_EXPORT	(ZBX_ITEM_GET_HOSTNAME)

#define ZBX_ITEM_GET_PROCESS		0

typedef struct zbx_dc_um_shared_handle zbx_dc_um_shared_handle_t;
typedef struct zbx_um_cache zbx_um_cache_t;

zbx_uint64_t	zbx_dc_sync_configuration(unsigned char mode, zbx_synced_new_config_t synced,
		zbx_vector_uint64_t *deleted_itemids, const zbx_config_vault_t *config_vault,
		int proxyconfig_frequency);
void	zbx_dc_sync_kvs_paths(const struct zbx_json_parse *jp_kvs_paths, const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location);
void	zbx_dc_config_get_hostids_by_revision(zbx_uint64_t new_revision, zbx_vector_uint64_t *hostids);
int	zbx_init_configuration_cache(zbx_get_program_type_f get_program_type, zbx_get_config_forks_f get_config_forks,
		zbx_uint64_t conf_cache_size, const char *hostname, char **error);
void	zbx_free_configuration_cache(void);

void	zbx_dc_config_get_triggers_by_triggerids(zbx_dc_trigger_t *triggers, const zbx_uint64_t *triggerids,
		int *errcode, size_t num);
void	zbx_dc_config_clean_items(zbx_dc_item_t *items, int *errcodes, size_t num);
int	zbx_dc_get_host_by_hostid(zbx_dc_host_t *host, zbx_uint64_t hostid);

#define ZBX_REQUEST_HOST_ID			101
#define ZBX_REQUEST_HOST_HOST			102
#define ZBX_REQUEST_HOST_NAME			103

int	zbx_dc_get_host_value(zbx_uint64_t itemid, char **replace_to, int request);
void	zbx_dc_config_get_hosts_by_itemids(zbx_dc_host_t *hosts, const zbx_uint64_t *itemids, int *errcodes, size_t num);
void	zbx_dc_config_get_hosts_by_hostids(zbx_dc_host_t *hosts, const zbx_uint64_t *hostids, int *errcodes, int num);
void	zbx_dc_config_get_items_by_keys(zbx_dc_item_t *items, zbx_host_key_t *keys, int *errcodes, size_t num);
void	zbx_dc_config_get_items_by_itemids(zbx_dc_item_t *items, const zbx_uint64_t *itemids, int *errcodes, size_t num);

void	zbx_dc_config_history_sync_get_items_by_itemids(zbx_history_sync_item_t *items, const zbx_uint64_t *itemids,
		int *errcodes, size_t num, unsigned int mode);
void	zbx_dc_config_history_sync_get_functions_by_functionids(zbx_dc_function_t *functions, zbx_uint64_t *functionids,
		int *errcodes, size_t num);
void	zbx_dc_config_history_sync_get_triggers_by_itemids(zbx_hashset_t *trigger_info,
		zbx_vector_dc_trigger_t *trigger_order, const zbx_uint64_t *itemids, const zbx_timespec_t *timespecs,
		int itemids_num);
void	zbx_dc_config_clean_history_sync_items(zbx_history_sync_item_t *items, int *errcodes, size_t num);
void	zbx_dc_config_history_sync_unset_existing_itemids(zbx_vector_uint64_t *itemids);
int	zbx_dc_config_history_get_trends_sec(const char *trends_period, int trends_global, int hk_trends);

void	zbx_dc_config_history_recv_get_items_by_keys(zbx_history_recv_item_t *items, const zbx_host_key_t *keys,
		int *errcodes, size_t num);
void	zbx_dc_config_history_recv_get_items_by_itemids(zbx_history_recv_item_t *items, const zbx_uint64_t *itemids,
		int *errcodes, size_t num, unsigned int mode);
void	zbx_dc_config_history_sync_get_connector_filters(zbx_vector_connector_filter_t *connector_filters_history,
		zbx_vector_connector_filter_t *connector_filters_events);
void	zbx_dc_config_history_sync_get_connectors(zbx_hashset_t *connectors, zbx_hashset_iter_t *connector_iter,
		zbx_uint64_t *config_revision, zbx_uint64_t *connector_revision,
		zbx_clean_func_t data_point_link_clean);
void	zbx_connector_filter_free(zbx_connector_filter_t connector_filter);

int	zbx_dc_config_get_active_items_count_by_hostid(zbx_uint64_t hostid);
void	zbx_dc_config_get_active_items_by_hostid(zbx_dc_item_t *items, zbx_uint64_t hostid, int *errcodes, size_t num);
void	zbx_dc_config_get_preprocessable_items(zbx_hashset_t *items, zbx_dc_um_shared_handle_t **um_handle,
		zbx_uint64_t *revision);
void	zbx_dc_config_get_functions_by_functionids(zbx_dc_function_t *functions,
		zbx_uint64_t *functionids, int *errcodes, size_t num);
void	zbx_dc_config_clean_functions(zbx_dc_function_t *functions, int *errcodes, size_t num);
void	zbx_dc_config_clean_triggers(zbx_dc_trigger_t *triggers, int *errcodes, size_t num);

typedef struct zbx_hc_data
{
	zbx_history_value_t	value;
	zbx_uint64_t		lastlogsize;
	zbx_timespec_t		ts;
	int			mtime;
	unsigned char		value_type;
	unsigned char		flags;
	unsigned char		state;

	struct zbx_hc_data	*next;
}
zbx_hc_data_t;

typedef struct
{
	zbx_uint64_t	itemid;
	unsigned char	status;
	int		values_num;

	zbx_hc_data_t	*tail;
	zbx_hc_data_t	*head;
}
zbx_hc_item_t;

ZBX_PTR_VECTOR_DECL(hc_item_ptr, zbx_hc_item_t *)

int	zbx_dc_config_lock_triggers_by_history_items(zbx_vector_hc_item_ptr_t *history_items,
		zbx_vector_uint64_t *triggerids);
void	zbx_dc_config_lock_triggers_by_triggerids(zbx_vector_uint64_t *triggerids_in,
		zbx_vector_uint64_t *triggerids_out);
void	zbx_dc_config_unlock_triggers(const zbx_vector_uint64_t *triggerids);
void	zbx_dc_config_unlock_all_triggers(void);
int	zbx_dc_config_trigger_exists(zbx_uint64_t triggerid);
int	zbx_config_get_trigger_severity_name(int priority, char **replace_to);

void	zbx_dc_free_triggers(zbx_vector_dc_trigger_t *triggers);
void	zbx_dc_config_update_interface_snmp_stats(zbx_uint64_t interfaceid, int max_snmp_succeed, int min_snmp_fail);
int	zbx_dc_config_get_suggested_snmp_vars(zbx_uint64_t interfaceid, int *bulk);
int	zbx_dc_config_get_interface_by_type(zbx_dc_interface_t *interface, zbx_uint64_t hostid, unsigned char type);
int	zbx_dc_config_get_interface(zbx_dc_interface_t *interface, zbx_uint64_t hostid, zbx_uint64_t itemid);

#define ZBX_REQUEST_HOST_IP			1
#define ZBX_REQUEST_HOST_DNS			2
#define ZBX_REQUEST_HOST_CONN			3
#define ZBX_REQUEST_HOST_PORT			4

int	zbx_dc_get_interface_value(zbx_uint64_t hostid, zbx_uint64_t itemid, char **replace_to, int request);

int	zbx_dc_config_get_poller_nextcheck(unsigned char poller_type);
int	zbx_dc_config_get_poller_items(unsigned char poller_type, int config_timeout, int processing,
		int config_max_concurrent_checks, zbx_dc_item_t **items);
#ifdef HAVE_OPENIPMI
int	zbx_dc_config_get_ipmi_poller_items(int now, int items_num, int config_timeout, zbx_dc_item_t *items,
		int *nextcheck);
#endif
int	zbx_dc_config_get_snmp_interfaceids_by_addr(const char *addr, zbx_uint64_t **interfaceids);
size_t	zbx_dc_config_get_snmp_items_by_interfaceid(zbx_uint64_t interfaceid, zbx_dc_item_t **items);

void	zbx_dc_config_update_autoreg_host(const char *host, const char *listen_ip, const char *listen_dns,
		unsigned short listen_port, const char *host_metadata, zbx_conn_flags_t flags, int now);
void	zbx_dc_config_delete_autoreg_host(const zbx_vector_autoreg_host_ptr_t *autoreg_hosts);

#define ZBX_HK_OPTION_DISABLED		0
#define ZBX_HK_OPTION_ENABLED		1

/* options for hk.history_mode, trends_mode, audit_mode */
#define ZBX_HK_MODE_DISABLED		ZBX_HK_OPTION_DISABLED
#define ZBX_HK_MODE_REGULAR		ZBX_HK_OPTION_ENABLED
#define ZBX_HK_MODE_PARTITION		2

#define ZBX_HK_HISTORY_MIN	SEC_PER_HOUR
#define ZBX_HK_TRENDS_MIN	SEC_PER_DAY
#define ZBX_HK_PERIOD_MAX	(25 * SEC_PER_YEAR)

void	zbx_dc_requeue_items(const zbx_uint64_t *itemids, const int *lastclocks, const int *errcodes, size_t num);
void	zbx_dc_poller_requeue_items(const zbx_uint64_t *itemids, const int *lastclocks,
		const int *errcodes, size_t num, unsigned char poller_type, int *nextcheck);
#ifdef HAVE_OPENIPMI
void	zbx_dc_requeue_unreachable_items(zbx_uint64_t *itemids, size_t itemids_num);
#endif

int	zbx_dc_config_check_trigger_dependencies(zbx_uint64_t triggerid);

void	zbx_dc_config_triggers_apply_changes(zbx_vector_trigger_diff_ptr_t *trigger_diff);
void	zbx_dc_config_items_apply_changes(const zbx_vector_item_diff_ptr_t *item_diff);

void	zbx_dc_config_update_inventory_values(const zbx_vector_inventory_value_ptr_t *inventory_values);
int	zbx_dc_get_host_inventory_value_by_itemid(zbx_uint64_t itemid, char **replace_to, int value_idx);
int	zbx_dc_get_host_inventory_by_itemid(const char *macro, zbx_uint64_t itemid, char **replace_to);
int	zbx_dc_get_host_inventory(const char *macro, const zbx_db_trigger *trigger, char **replace_to,
		int N_functionid);
int	zbx_dc_get_host_inventory_by_hostid(const char *macro, zbx_uint64_t hostid, char **replace_to);
int	zbx_dc_get_host_inventory_value_by_hostid(zbx_uint64_t hostid, char **replace_to, int value_idx);

#define ZBX_CONFSTATS_BUFFER_TOTAL	1
#define ZBX_CONFSTATS_BUFFER_USED	2
#define ZBX_CONFSTATS_BUFFER_FREE	3
#define ZBX_CONFSTATS_BUFFER_PUSED	4
#define ZBX_CONFSTATS_BUFFER_PFREE	5
void	*zbx_dc_config_get_stats(int request);

int	zbx_dc_config_get_last_sync_time(void);
int	zbx_dc_config_get_proxypoller_hosts(zbx_dc_proxy_t *proxies, int max_hosts);
int	zbx_dc_config_get_proxypoller_nextcheck(void);

#define ZBX_PROXY_CONFIG_NEXTCHECK	0x01
#define ZBX_PROXY_DATA_NEXTCHECK	0x02
#define ZBX_PROXY_TASKS_NEXTCHECK	0x04
void	zbx_dc_requeue_proxy(zbx_uint64_t proxyid, unsigned char update_nextcheck, int proxy_conn_err,
		int proxyconfig_frequency, int proxydata_frequency);
int	zbx_dc_check_host_permissions(const char *host, const zbx_socket_t *sock, zbx_uint64_t *hostid,
		zbx_uint64_t *revision, zbx_comms_redirect_t *redirect, char **error);
int	zbx_dc_is_autoreg_host_changed(const char *host, unsigned short port, const char *host_metadata,
		zbx_conn_flags_t flag, const char *interface, int now, int heartbeat);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
size_t	zbx_dc_get_psk_by_identity(const unsigned char *psk_identity, unsigned char *psk_buf, unsigned int *psk_usage);
#endif
void	zbx_dc_get_autoregistration_psk(char *psk_identity_buf, size_t psk_identity_buf_len,
		unsigned char *psk_buf, size_t psk_buf_len);

#define ZBX_MACRO_ENV_SECURE	0
#define ZBX_MACRO_ENV_NONSECURE	1
#define ZBX_MACRO_ENV_DEFAULT	2

#define ZBX_MACRO_VALUE_TEXT	0
#define ZBX_MACRO_VALUE_SECRET	1
#define ZBX_MACRO_VALUE_VAULT	2

#define ZBX_MACRO_SECRET_MASK	"******"

int	zbx_dc_interface_activate(zbx_uint64_t interfaceid, const zbx_timespec_t *ts, zbx_agent_availability_t *in,
		zbx_agent_availability_t *out);

int	zbx_dc_interface_deactivate(zbx_uint64_t interfaceid, const zbx_timespec_t *ts, int unavailable_delay,
		int unreachable_period, int unreachable_delay, zbx_agent_availability_t *in,
		zbx_agent_availability_t *out, const char *error_msg);
void	zbx_dc_set_interface_version(zbx_uint64_t interfaceid, int version);

#define ZBX_QUEUE_FROM_DEFAULT	6	/* default lower limit for delay (in seconds) */
#define ZBX_QUEUE_TO_INFINITY	-1	/* no upper limit for delay */

void	zbx_dc_free_item_queue(zbx_vector_queue_item_ptr_t *queue);
int	zbx_dc_get_item_queue(zbx_vector_queue_item_ptr_t *queue, int from, int to);

zbx_uint64_t	zbx_dc_get_item_count(zbx_uint64_t hostid);
zbx_uint64_t	zbx_dc_get_item_unsupported_count(zbx_uint64_t hostid);
zbx_uint64_t	zbx_dc_get_trigger_count(void);
double		zbx_dc_get_required_performance(void);
zbx_uint64_t	zbx_dc_get_host_count(void);
void		zbx_dc_get_count_stats_all(zbx_config_cache_info_t *stats);

void	zbx_dc_get_status(zbx_vector_proxy_counter_ptr_t *hosts_monitored,
		zbx_vector_proxy_counter_ptr_t *hosts_not_monitored,
		zbx_vector_proxy_counter_ptr_t *items_active_normal,
		zbx_vector_proxy_counter_ptr_t *items_active_notsupported,
		zbx_vector_proxy_counter_ptr_t *items_disabled, uint64_t *triggers_enabled_ok,
		zbx_uint64_t *triggers_enabled_problem, zbx_uint64_t *triggers_disabled,
		zbx_vector_proxy_counter_ptr_t *required_performance);

void	zbx_dc_get_expressions_by_names(zbx_vector_expression_t *expressions, const char * const *names, int names_num);
void	zbx_dc_get_expressions_by_name(zbx_vector_expression_t *expressions, const char *name);

int	zbx_dc_get_data_expected_from(zbx_uint64_t itemid, int *seconds);

void	zbx_dc_get_hostids_by_functionids(zbx_vector_uint64_t *functionids, zbx_vector_uint64_t *hostids);
void	zbx_dc_get_hosts_by_functionids(const zbx_vector_uint64_t *functionids, zbx_hashset_t *hosts);

int	zbx_dc_get_proxy_nodata_win(zbx_uint64_t hostid, zbx_proxy_suppress_t *nodata_win, int *lastaccess);
int	zbx_dc_get_proxy_delay_by_name(const char *name, int *delay, char **error);
int	zbx_dc_get_proxy_lastaccess_by_name(const char *name, time_t *lastaccess, char **error);
void	zbx_proxy_discovery_get(char **data);
void	zbx_proxy_group_discovery_get(char **data);
int	zbx_proxy_proxy_list_discovery_get(const zbx_vector_uint64_t *proxyids, char **data, char **error);

unsigned int	zbx_dc_get_internal_action_count(void);
unsigned int	zbx_dc_get_auto_registration_action_count(void);

/* global configuration support */
#define ZBX_DISCOVERY_GROUPID_UNDEFINED	0
void	zbx_config_get(zbx_config_t *cfg, zbx_uint64_t flags);
void	zbx_config_clean(zbx_config_t *cfg);
void	zbx_config_get_hk_mode(unsigned char *history_mode, unsigned char *trends_mode);

int	zbx_dc_set_interfaces_availability(zbx_vector_availability_ptr_t *availabilities);

int	zbx_dc_reset_interfaces_availability(zbx_vector_availability_ptr_t *interfaces);

void	zbx_dc_config_history_sync_get_actions_eval(zbx_vector_action_eval_ptr_t *actions, unsigned char opflags);

int	zbx_dc_get_interfaces_availability(zbx_vector_availability_ptr_t *interfaces, int *ts);
void	zbx_dc_touch_interfaces_availability(const zbx_vector_uint64_t *interfaceids);

void	zbx_set_availability_diff_ts(int ts);

void	zbx_dc_correlation_rules_init(zbx_correlation_rules_t *rules);
void	zbx_dc_correlation_rules_clean(zbx_correlation_rules_t *rules);
void	zbx_dc_correlation_rules_free(zbx_correlation_rules_t *rules);
void	zbx_dc_correlation_rules_get(zbx_correlation_rules_t *rules);

void	zbx_dc_get_nested_hostgroupids(zbx_uint64_t *groupids, int groupids_num, zbx_vector_uint64_t *nested_groupids);
void	zbx_dc_get_hostids_by_group_name(const char *name, zbx_vector_uint64_t *hostids);

void	zbx_free_item_tag(zbx_item_tag_t *item_tag);

int	zbx_dc_get_active_proxy_by_name(const char *name, zbx_dc_proxy_t *proxy, char **error);

typedef struct
{
	zbx_timespec_t	ts;
	char		*value;	/* NULL in case of meta record (see "meta" field below) */
	char		*source;
	zbx_uint64_t	lastlogsize;
	zbx_uint64_t	id;
	int		mtime;
	int		timestamp;
	int		severity;
	int		logeventid;
	unsigned char	state;
	unsigned char	meta;	/* non-zero if contains meta information (lastlogsize and mtime) */
}
zbx_agent_value_t;

void	zbx_dc_items_update_nextcheck(zbx_history_recv_item_t *items, zbx_agent_value_t *values, int *errcodes,
		size_t values_num);
int	zbx_dc_get_host_interfaces(zbx_uint64_t hostid, zbx_dc_interface2_t **interfaces, int *n);

void	zbx_dc_update_proxy(zbx_proxy_diff_t *diff);
void	zbx_dc_get_proxy_lastaccess(zbx_vector_uint64_pair_t *lastaccess);
void	zbx_dc_proxy_update_nodata(zbx_vector_uint64_pair_t *subscriptions);

typedef struct
{
	zbx_uint64_t		triggerid;
	unsigned char		status;
	zbx_vector_uint64_t	masterids;
}
zbx_trigger_dep_t;

ZBX_PTR_VECTOR_DECL(trigger_dep_ptr, zbx_trigger_dep_t *)

int	zbx_trigger_dep_compare_func(const void *d1, const void *d2);

void	zbx_dc_get_trigger_dependencies(const zbx_vector_uint64_t *triggerids, zbx_vector_trigger_dep_ptr_t *deps);

void	zbx_dc_reschedule_items(const zbx_vector_uint64_t *itemids, time_t nextcheck, zbx_uint64_t *proxyids);

/* data session support */

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	last_id;
	const char	*token;
	time_t		lastaccess;
}
zbx_session_t;

typedef struct
{
	zbx_uint64_t	config;			/* configuration cache revision, increased every sync */
	zbx_uint64_t	expression;		/* global expression revision */
	zbx_uint64_t	autoreg_tls;		/* autoregistration tls revision */
	zbx_uint64_t	drules;			/* drules revision */
	zbx_uint64_t	upstream;		/* configuration revision received from server */
	zbx_uint64_t	upstream_hostmap;	/* host mapping configuration revision received from server */
	zbx_uint64_t	config_table;		/* the global configuration revision (config table) */
	zbx_uint64_t	connector;
	zbx_uint64_t	proxy_group;		/* summary revision of all proxy groups */
	zbx_uint64_t	proxy;			/* summary revision of all proxies */
}
zbx_dc_revision_t;

const char	*zbx_dc_get_session_token(void);
zbx_session_t	*zbx_dc_get_or_create_session(zbx_uint64_t hostid, const char *token, zbx_session_type_t session_type);

int	zbx_dc_register_config_session(zbx_uint64_t hostid, const char *token, zbx_uint64_t session_config_revision,
		zbx_dc_revision_t *dc_revision);

void		zbx_dc_cleanup_sessions(void);

void		zbx_dc_cleanup_autoreg_host(void);

/* maintenance support */
typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	maintenanceid;
	int		maintenance_from;
	unsigned char	maintenance_type;
	unsigned char	maintenance_status;

	unsigned int	flags;
#define ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCEID		0x0001
#define ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_FROM	0x0002
#define ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_TYPE	0x0003
#define ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_STATUS	0x0004
}
zbx_host_maintenance_diff_t;
/* NOTE: Do not forget to sync changes with zbx_host_maintenance_diff_free(). */

ZBX_PTR_VECTOR_DECL(host_maintenance_diff_ptr, zbx_host_maintenance_diff_t*)
void	zbx_host_maintenance_diff_free(zbx_host_maintenance_diff_t *hmd);

/* event maintenance query data, used to get event maintenances from cache */
typedef struct
{
	zbx_uint64_t			eventid;		/* [IN] eventid */
	zbx_uint64_t			r_eventid;		/* [-] recovery eventid */
	zbx_uint64_t			triggerid;		/* [-] triggerid */
	zbx_vector_uint64_t		hostids;		/* [-] associated hostids */
	zbx_vector_uint64_t		functionids;		/* [IN] associated functionids */
	zbx_vector_tags_ptr_t		tags;			/* [IN] event tags */
	zbx_vector_uint64_pair_t	maintenances;		/* [OUT] actual maintenance data for the event in */
								/* (maintenanceid, suppress_until) pairs */
}
zbx_event_suppress_query_t;

ZBX_PTR_VECTOR_DECL(event_suppress_query_ptr, zbx_event_suppress_query_t*)

#define ZBX_FLAG_MAINTENANCE_UPDATE_NONE	0x00
#define ZBX_FLAG_MAINTENANCE_UPDATE_MAINTENANCE	0x01
#define ZBX_FLAG_MAINTENANCE_UPDATE_PERIOD	0x02

typedef enum
{
	MAINTENANCE_TIMER_INITIALIZED = 0,
	MAINTENANCE_TIMER_PENDING
}
zbx_maintenance_timer_t;

void	zbx_event_suppress_query_free(zbx_event_suppress_query_t *query);
int	zbx_dc_update_maintenances(zbx_maintenance_timer_t maintenance_timer);
void	zbx_dc_get_host_maintenance_updates(const zbx_vector_uint64_t *maintenanceids,
		zbx_vector_host_maintenance_diff_ptr_t *updates);
void	zbx_dc_flush_host_maintenance_updates(const zbx_vector_host_maintenance_diff_ptr_t *updates);
int	zbx_dc_get_event_maintenances(zbx_vector_event_suppress_query_ptr_t *event_queries,
		const zbx_vector_uint64_t *maintenanceids);
int	zbx_dc_get_running_maintenanceids(zbx_vector_uint64_t *maintenanceids);

void	zbx_dc_maintenance_set_update_flags(void);
void	zbx_dc_maintenance_reset_update_flag(int timer);
int	zbx_dc_maintenance_check_update_flag(int timer);
int	zbx_dc_maintenance_check_update_flags(void);
int	zbx_dc_maintenance_check_immediate_update(void);

int	zbx_dc_maintenance_has_tags(void);

typedef struct
{
	char	*lld_macro;
	char	*path;
}
zbx_lld_macro_path_t;

ZBX_PTR_VECTOR_DECL(lld_macro_path_ptr, zbx_lld_macro_path_t *)

int	zbx_lld_macro_paths_get(zbx_uint64_t lld_ruleid, zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		char **error);
void	zbx_lld_macro_path_free(zbx_lld_macro_path_t *lld_macro_path);
int	zbx_lld_macro_value_by_name(const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, const char *macro, char **value);
int	zbx_lld_macro_paths_compare(const void *d1, const void *d2);

void	zbx_dc_get_item_tags(zbx_uint64_t itemid, zbx_vector_item_tag_t *item_tags);
void	zbx_get_item_tags(zbx_uint64_t itemid, zbx_vector_item_tag_t *item_tags);

void	zbx_dc_config_history_sync_get_item_tags_by_functionids(const zbx_uint64_t *functionids,
		size_t functionids_num, zbx_vector_item_tag_t *item_tags);

const char	*zbx_dc_get_instanceid(void);

typedef struct
{
	zbx_uint64_t		objectid;
	zbx_uint64_t		triggerid;
	zbx_uint64_t		hostid;
	zbx_uint32_t		type;
	unsigned char		lock;		/* 1 if the timer has locked trigger, 0 otherwise */
	zbx_uint64_t		revision;	/* revision */
	time_t			lastcheck;
	zbx_timespec_t		eval_ts;	/* the history time for which trigger must be recalculated */
	zbx_timespec_t		check_ts;	/* time when timer must be checked */
	zbx_timespec_t		exec_ts;	/* real time when the timer must be executed */
	const char		*parameter;	/* function parameters (for trend functions) */
}
zbx_trigger_timer_t;

ZBX_PTR_VECTOR_DECL(trigger_timer_ptr, zbx_trigger_timer_t *)

void	zbx_dc_reschedule_trigger_timers(zbx_vector_trigger_timer_ptr_t *timers, int now);
void	zbx_dc_get_trigger_timers(zbx_vector_trigger_timer_ptr_t *timers, int now, int soft_limit, int hard_limit);
void	zbx_dc_clear_timer_queue(zbx_vector_trigger_timer_ptr_t *timers);
void	zbx_dc_get_triggers_by_timers(zbx_hashset_t *trigger_info, zbx_vector_dc_trigger_t *trigger_order,
		const zbx_vector_trigger_timer_ptr_t *timers);
void	zbx_dc_free_timers(zbx_vector_trigger_timer_ptr_t *timers);

void	zbx_get_host_interfaces_availability(zbx_uint64_t	hostid, zbx_agent_availability_t *agents);

/* external user macro cache API */

typedef struct zbx_dc_um_handle_t zbx_dc_um_handle_t;

zbx_dc_um_handle_t	*zbx_dc_open_user_macros(void);
zbx_dc_um_handle_t	*zbx_dc_open_user_macros_secure(void);
zbx_dc_um_handle_t	*zbx_dc_open_user_macros_masked(void);

void	zbx_dc_close_user_macros(zbx_dc_um_handle_t *um_handle);

void	zbx_dc_get_user_macro(const zbx_dc_um_handle_t *um_handle, const char *macro, const zbx_uint64_t *hostids,
		int hostids_num, char **value);

int	zbx_dc_expand_user_and_func_macros(const zbx_dc_um_handle_t *um_handle, char **text,
		const zbx_uint64_t *hostids, int hostids_num, char **error);
int	zbx_dc_expand_user_and_func_macros_from_cache(zbx_um_cache_t *um_cache, char **text,
		const zbx_uint64_t *hostids, int hostids_num, unsigned char env, char **error);

char	*zbx_dc_expand_user_macros_in_func_params(const char *params, zbx_uint64_t hostid);

/* shared user macro handle can be used to share user macro handle between threads   */
/* without locking configuration cache to update user macro handle reference counter */
struct zbx_dc_um_shared_handle
{
	zbx_um_cache_t		*um_cache;
	zbx_uint64_t		refcount;
};

zbx_dc_um_shared_handle_t	*zbx_dc_um_shared_handle_update(zbx_dc_um_shared_handle_t *handle);
int	zbx_dc_um_shared_handle_reacquire(zbx_dc_um_shared_handle_t *old_handle, zbx_dc_um_shared_handle_t *new_handle);
zbx_dc_um_shared_handle_t	*zbx_dc_um_shared_handle_copy(zbx_dc_um_shared_handle_t *handle);
void	zbx_dc_um_shared_handle_release(zbx_dc_um_shared_handle_t *handle);

int	zbx_dc_get_proxyid_by_name(const char *name, zbx_uint64_t *proxyid, unsigned char *type);
int	zbx_dc_update_passive_proxy_nextcheck(zbx_uint64_t proxyid);

typedef struct
{
	zbx_uint64_t	proxyid;
	unsigned char	mode;
	char		*name;
}
zbx_cached_proxy_t;

ZBX_PTR_VECTOR_DECL(cached_proxy_ptr, zbx_cached_proxy_t *)

void	zbx_dc_get_all_proxies(zbx_vector_cached_proxy_ptr_t *proxies);
void	zbx_cached_proxy_free(zbx_cached_proxy_t *proxy);

int	zbx_dc_get_proxy_name_type_by_id(zbx_uint64_t proxyid, int *status, char **name);

/* item snmpv3 security levels */
#define ZBX_ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV	0
#define ZBX_ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV	1
#define ZBX_ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV	2

/* maintenance tag operators */
#define ZBX_MAINTENANCE_TAG_OPERATOR_EQUAL	0
#define ZBX_MAINTENANCE_TAG_OPERATOR_LIKE	2

/* maintenance tag evaluation types */
/* SYNC WITH PHP!                   */
#define ZBX_MAINTENANCE_TAG_EVAL_TYPE_AND_OR	0
#define ZBX_MAINTENANCE_TAG_EVAL_TYPE_OR	2

/* special item key used for ICMP pings */
#define ZBX_SERVER_ICMPPING_KEY	"icmpping"
/* special item key used for ICMP ping latency */
#define ZBX_SERVER_ICMPPINGSEC_KEY	"icmppingsec"
/* special item key used for ICMP ping loss packages */
#define ZBX_SERVER_ICMPPINGLOSS_KEY	"icmppingloss"

void	zbx_dc_drules_get(time_t now, zbx_vector_dc_drule_ptr_t *drules, time_t *nextcheck);
void	zbx_dc_drule_queue(time_t now, zbx_uint64_t druleid, int delay);
int	zbx_dc_drule_revisions_get(zbx_uint64_t *rev_last, zbx_vector_uint64_pair_t *revisions);

int	zbx_dc_httptest_next(time_t now, zbx_uint64_t *httptestid, time_t *nextcheck);
void	zbx_dc_httptest_queue(time_t now, zbx_uint64_t httptestid, int delay);

void	zbx_dc_get_upstream_revision(zbx_uint64_t *config_revision, zbx_uint64_t *hostmap_revision);
void	zbx_dc_set_upstream_revision(zbx_uint64_t config_revision, zbx_uint64_t hostmap_revision);

void	zbx_dc_get_proxy_config_updates(zbx_uint64_t proxyid, zbx_uint64_t revision, zbx_vector_uint64_t *hostids,
		zbx_vector_uint64_t *updated_hostids, zbx_vector_uint64_t *removed_hostids,
		zbx_vector_uint64_t *httptestids, zbx_uint64_t *proxy_group_revision);

void	zbx_dc_get_macro_updates(const zbx_vector_uint64_t *hostids, const zbx_vector_uint64_t *updated_hostids,
		zbx_uint64_t revision, zbx_vector_uint64_t *macro_hostids, int *global,
		zbx_vector_uint64_t *del_macro_hostids);
void	zbx_dc_get_unused_macro_templates(zbx_hashset_t *templates, const zbx_vector_uint64_t *hostids,
		zbx_vector_uint64_t *templateids);

/* maintenance */

typedef enum
{
	MAINTENANCE_TYPE_NORMAL = 0,
	MAINTENANCE_TYPE_NODATA
}
zbx_maintenance_type_t;

/* action statuses */
#define ZBX_ACTION_STATUS_ACTIVE	0
#define ZBX_ACTION_STATUS_DISABLED	1

/* operation types */
#define ZBX_OPERATION_TYPE_MESSAGE		0
#define ZBX_OPERATION_TYPE_COMMAND		1
#define ZBX_OPERATION_TYPE_HOST_ADD		2
#define ZBX_OPERATION_TYPE_HOST_REMOVE		3
#define ZBX_OPERATION_TYPE_GROUP_ADD		4
#define ZBX_OPERATION_TYPE_GROUP_REMOVE		5
#define ZBX_OPERATION_TYPE_TEMPLATE_ADD		6
#define ZBX_OPERATION_TYPE_TEMPLATE_REMOVE	7
#define ZBX_OPERATION_TYPE_HOST_ENABLE		8
#define ZBX_OPERATION_TYPE_HOST_DISABLE		9
#define ZBX_OPERATION_TYPE_HOST_INVENTORY	10
#define ZBX_OPERATION_TYPE_RECOVERY_MESSAGE	11
#define ZBX_OPERATION_TYPE_UPDATE_MESSAGE	12 /* OPERATION_TYPE_ACK_MESSAGE */
#define ZBX_OPERATION_TYPE_HOST_TAGS_ADD	13
#define ZBX_OPERATION_TYPE_HOST_TAGS_REMOVE	14

/* proxy_history flags */
#define ZBX_PROXY_HISTORY_FLAG_META		0x01
#define ZBX_PROXY_HISTORY_FLAG_NOVALUE		0x02
#define ZBX_PROXY_HISTORY_MASK_NOVALUE		(ZBX_PROXY_HISTORY_FLAG_META | ZBX_PROXY_HISTORY_FLAG_NOVALUE)

#define ZBX_CORR_CONDITION_OLD_EVENT_TAG		0
#define ZBX_CORR_CONDITION_NEW_EVENT_TAG		1
#define ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP		2
#define ZBX_CORR_CONDITION_EVENT_TAG_PAIR		3
#define ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE		4
#define ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE		5

#define ZBX_RECALC_TIME_PERIOD_HISTORY	1
#define ZBX_RECALC_TIME_PERIOD_TRENDS	2
void	zbx_recalc_time_period(time_t *ts_from, int table_group);

/* vps tracker */
typedef struct
{
	zbx_uint64_t	overcommit_limit;
	zbx_uint64_t	overcommit;
	zbx_uint64_t	values_limit;
	zbx_uint64_t	written_num;
}
zbx_vps_monitor_stats_t;

void	zbx_vps_monitor_init(zbx_uint64_t vps_limit, zbx_uint64_t overcommit_limit);
void	zbx_vps_monitor_add_collected(zbx_uint64_t values_num);
void	zbx_vps_monitor_add_written(zbx_uint64_t values_num);
int	zbx_vps_monitor_capped(void);
void	zbx_vps_monitor_get_stats(zbx_vps_monitor_stats_t *stats);
const char	*zbx_vps_monitor_status(void);

typedef struct
{
	const char	*agent;
	const char	*simple;
	const char	*snmp;
	const char	*external;
	const char	*odbc;
	const char	*http;
	const char	*ssh;
	const char	*telnet;
	const char	*script;
	const char	*browser;
}
zbx_config_item_type_timeouts_t;

#define ZBX_ITEM_TYPE_TIMEOUT_LEN	255
#define ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX	(ZBX_ITEM_TYPE_TIMEOUT_LEN + 1)

typedef struct
{
	char	agent[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
	char	simple[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
	char	snmp[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
	char	external[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
	char	odbc[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
	char	http[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
	char	ssh[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
	char	telnet[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
	char	script[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
	char	browser[ZBX_ITEM_TYPE_TIMEOUT_LEN_MAX];
}
zbx_dc_item_type_timeouts_t;

void	zbx_dc_get_proxy_timeouts(zbx_uint64_t proxy_hostid, zbx_dc_item_type_timeouts_t *timeouts);
char	*zbx_dc_get_global_item_type_timeout(unsigned char item_type);

/* proxy group manager local cache support */

/* host-proxy mapping record */
typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxyid;
	zbx_uint64_t	revision;
	zbx_uint64_t	hostproxyid;
}
zbx_pg_host_t;

ZBX_VECTOR_DECL(pg_host, zbx_pg_host_t)

/* wrap reference to zbx_pg_host_t structure for hashset storage */
typedef struct
{
	zbx_pg_host_t	*host;
}
zbx_pg_host_ref_t;

ZBX_VECTOR_DECL(pg_host_ref_ptr, zbx_pg_host_ref_t *)

typedef struct zbx_pg_group zbx_pg_group_t;

#define ZBX_PG_PROXY_FLAGS_NONE		0x0000
#define ZBX_PG_PROXY_SYNC_ADDED		0x0100
#define ZBX_PG_PROXY_SYNC_MODIFIED	0x0200

/* proxy */

#define ZBX_PG_PROXY_FLAGS_NONE		0x0000
#define ZBX_PG_PROXY_UPDATE_STATE	0x0001

typedef struct
{
	zbx_uint64_t			proxyid;
	zbx_uint64_t			revision;
	char				*name;
	int				state;
	int				version;
	int				lastaccess;
	int				firstaccess;
	time_t				sync_time;	/* sync_time is used to stop collecting potentially infinite */
							/* host changes into deleted_group_hosts if the proxy was    */
							/* offline for day+. In this case full proxy group data      */
							/* resync will be forced                                     */
	zbx_uint32_t			flags;
	struct zbx_pg_group		*group;
	zbx_hashset_t			hosts;		/* references to proxy group manager hostmap entries */
	zbx_vector_pg_host_t		deleted_group_hosts;
}
zbx_pg_proxy_t;

ZBX_PTR_VECTOR_DECL(pg_proxy_ptr, zbx_pg_proxy_t *)

#define ZBX_PG_GROUP_FLAGS_NONE		0x0000
#define ZBX_PG_GROUP_UPDATE_STATE	0x0001
#define ZBX_PG_GROUP_UPDATE_HP_MAP	0x0002
#define ZBX_PG_GROUP_SYNC_ADDED		0x0100
#define ZBX_PG_GROUP_SYNC_MODIFIED	0x0200

/* proxy group */
struct zbx_pg_group
{
	zbx_uint64_t			proxy_groupid;
	char				*name;
	char				*failover_delay;
#define ZBX_PG_PROXY_MIN_ONLINE_MIN	1
#define ZBX_PG_PROXY_MIN_ONLINE_MAX	1000
	char				*min_online;
	zbx_uint64_t			revision;
	zbx_uint64_t			hostmap_revision;
	zbx_uint64_t			sync_revision;
	int				state;
	int				state_time;
	int				unbalanced;
	time_t				balance_time;
	zbx_uint32_t			flags;
	zbx_vector_pg_proxy_ptr_t	proxies;		/* proxies assigned to host group */
	zbx_hashset_t			hostids;		/* hostids assigned to proxy group */
	zbx_vector_uint64_t		unassigned_hostids;	/* hostids to be assigned to proxies */
};

ZBX_PTR_VECTOR_DECL(pg_group_ptr, zbx_pg_group_t *)

#define ZBX_PG_PROXY_FETCH_REVISION	0
#define ZBX_PG_PROXY_FETCH_FORCE	1

int	zbx_dc_fetch_proxy_groups(zbx_hashset_t *groups, zbx_uint64_t *revision);
int	zbx_dc_fetch_proxies(zbx_hashset_t *groups, zbx_hashset_t *proxies, zbx_uint64_t *revision, int flags,
		zbx_vector_objmove_t *proxy_reloc);

int	zbx_dc_config_get_hostid_by_name(const char *host, const zbx_socket_t *sock, zbx_uint64_t *hostid,
		zbx_comms_redirect_t *redirect);
int	zbx_dc_config_get_host_by_name(const char *host, const zbx_socket_t *sock, zbx_history_recv_host_t *recv_host,
		zbx_comms_redirect_t *redirect);

int	zbx_dc_get_proxy_group_hostmap_revision(zbx_uint64_t proxy_groupid, zbx_uint64_t *hostmap_revision);
void	zbx_dc_set_proxy_failover_delay(const char *failover_delay);
void	zbx_dc_set_proxy_lastonline(int lastonline);
zbx_uint64_t	zbx_dc_get_proxy_group_revision(zbx_uint64_t proxy_groupid);
zbx_uint64_t	zbx_dc_get_proxy_groupid(zbx_uint64_t proxyid);

void	zbx_dc_set_itservices_num(int num);
int	zbx_dc_get_itservices_num(void);

int	zbx_dc_get_proxy_version(zbx_uint64_t proxyid);

#endif
