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

#ifndef ZABBIX_DBCACHE_H
#define ZABBIX_DBCACHE_H

#include "sysinfo.h" //included for convenience
#include "zbxdbhigh.h"
#include "zbxcomms.h"
#include "zbxshmem.h"
#include "zbxeval.h"
#include "zbxavailability.h"

#define ZBX_SYNC_DONE		0
#define	ZBX_SYNC_MORE		1

#define	ZBX_NO_POLLER			255
#define	ZBX_POLLER_TYPE_NORMAL		0
#define	ZBX_POLLER_TYPE_UNREACHABLE	1
#define	ZBX_POLLER_TYPE_IPMI		2
#define	ZBX_POLLER_TYPE_PINGER		3
#define	ZBX_POLLER_TYPE_JAVA		4
#define	ZBX_POLLER_TYPE_HISTORY		5
#define	ZBX_POLLER_TYPE_ODBC		6
#define	ZBX_POLLER_TYPE_COUNT		7	/* number of poller types */

#define MAX_JAVA_ITEMS		32
#define MAX_SNMP_ITEMS		128
#define MAX_POLLER_ITEMS	128	/* MAX(MAX_JAVA_ITEMS, MAX_SNMP_ITEMS) */
#define MAX_PINGER_ITEMS	128

#define ZBX_TRIGGER_DEPENDENCY_LEVELS_MAX	32

#define ZBX_TRIGGER_DEPENDENCY_FAIL		1
#define ZBX_TRIGGER_DEPENDENCY_UNRESOLVED	2

#define ZBX_SNMPTRAP_LOGGING_ENABLED	1

#define ZBX_IPC_SERVICE_CONFIG		"config"
#define ZBX_IPC_CONFIG_RELOAD_REQUEST	1
#define ZBX_IPC_CONFIG_RELOAD_RESPONSE	2

#define ZBX_AGENT_ZABBIX	(INTERFACE_TYPE_AGENT - 1)
#define ZBX_AGENT_SNMP		(INTERFACE_TYPE_SNMP - 1)
#define ZBX_AGENT_IPMI		(INTERFACE_TYPE_IPMI - 1)
#define ZBX_AGENT_JMX		(INTERFACE_TYPE_JMX - 1)
#define ZBX_AGENT_UNKNOWN	255
#define ZBX_AGENT_MAX		INTERFACE_TYPE_COUNT

extern int	CONFIG_TIMEOUT;

extern zbx_uint64_t	CONFIG_CONF_CACHE_SIZE;
extern zbx_uint64_t	CONFIG_HISTORY_CACHE_SIZE;
extern zbx_uint64_t	CONFIG_HISTORY_INDEX_CACHE_SIZE;
extern zbx_uint64_t	CONFIG_TRENDS_CACHE_SIZE;

extern int	CONFIG_POLLER_FORKS;
extern int	CONFIG_UNREACHABLE_POLLER_FORKS;
extern int	CONFIG_IPMIPOLLER_FORKS;
extern int	CONFIG_JAVAPOLLER_FORKS;
extern int	CONFIG_PINGER_FORKS;
extern int	CONFIG_UNREACHABLE_PERIOD;
extern int	CONFIG_UNREACHABLE_DELAY;
extern int	CONFIG_PROXYCONFIG_FREQUENCY;
extern int	CONFIG_PROXYDATA_FREQUENCY;
extern int	CONFIG_HISTORYPOLLER_FORKS;
extern int	CONFIG_ODBCPOLLER_FORKS;

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
}
DC_INTERFACE;

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
DC_INTERFACE2;

#define HOST_IPMI_USERNAME_LEN		16
#define HOST_IPMI_USERNAME_LEN_MAX	(HOST_IPMI_USERNAME_LEN + 1)
#define HOST_IPMI_PASSWORD_LEN		20
#define HOST_IPMI_PASSWORD_LEN_MAX	(HOST_IPMI_PASSWORD_LEN + 1)
#define HOST_PROXY_ADDRESS_LEN		255
#define HOST_PROXY_ADDRESS_LEN_MAX	(HOST_PROXY_ADDRESS_LEN + 1)

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxy_hostid;
	char		host[ZBX_HOSTNAME_BUF_LEN];
	char		name[ZBX_MAX_HOSTNAME_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1];
	unsigned char	maintenance_status;
	unsigned char	maintenance_type;
	int		maintenance_from;
	signed char	ipmi_authtype;
	unsigned char	ipmi_privilege;
	char		ipmi_username[HOST_IPMI_USERNAME_LEN_MAX];
	char		ipmi_password[HOST_IPMI_PASSWORD_LEN_MAX];
	signed char	inventory_mode;
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
DC_HOST;

typedef struct
{
	DC_HOST			host;
	DC_INTERFACE		interface;
	zbx_uint64_t		itemid;
	zbx_uint64_t		lastlogsize;
	zbx_uint64_t		valuemapid;
	unsigned char		type;
	unsigned char		snmp_version;
	unsigned char		value_type;
	unsigned char		state;
	unsigned char		snmpv3_securitylevel;
	unsigned char		authtype;
	unsigned char		flags;
	unsigned char		snmpv3_authprotocol;
	unsigned char		snmpv3_privprotocol;
	unsigned char		inventory_link;
	unsigned char		status;
	unsigned char		history;
	unsigned char		trends;
	unsigned char		follow_redirects;
	unsigned char		post_type;
	unsigned char		retrieve_mode;
	unsigned char		request_method;
	unsigned char		output_format;
	unsigned char		verify_peer;
	unsigned char		verify_host;
	unsigned char		allow_traps;
	char			key_orig[ZBX_ITEM_KEY_LEN * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1], *key;
	char			*units;
	char			*delay;
	int			history_sec;
	int			trends_sec;
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
	char			timeout_orig[ZBX_ITEM_TIMEOUT_LEN_MAX], *timeout;
	char			url_orig[ZBX_ITEM_URL_LEN_MAX], *url;
	char			query_fields_orig[ZBX_ITEM_QUERY_FIELDS_LEN_MAX], *query_fields;
	char			*posts;
	char			status_codes_orig[ZBX_ITEM_STATUS_CODES_LEN_MAX], *status_codes;
	char			http_proxy_orig[ZBX_ITEM_HTTP_PROXY_LEN_MAX], *http_proxy;
	char			*headers;
	char			ssl_cert_file_orig[ZBX_ITEM_SSL_CERT_FILE_LEN_MAX], *ssl_cert_file;
	char			ssl_key_file_orig[ZBX_ITEM_SSL_KEY_FILE_LEN_MAX], *ssl_key_file;
	char			ssl_key_password_orig[ZBX_ITEM_SSL_KEY_PASSWORD_LEN_MAX], *ssl_key_password;
	char			*script_params;
	char			*error;
	unsigned char		*formula_bin;
	char			*history_period, *trends_period;
}
DC_ITEM;

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	triggerid;
	zbx_uint64_t	itemid;
	char		*function;
	char		*parameter;
	unsigned char	type;
}
DC_FUNCTION;

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	itemid;
	zbx_tag_t	tag;
}
zbx_item_tag_t;

#define ZBX_DC_TRIGGER_PROBLEM_EXPRESSION	0x1	/* this flag shows that trigger value recalculation is  */
							/* initiated by a time-based function or a new value of */
							/* an item in problem expression */

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

	zbx_vector_ptr_t	tags;
	zbx_vector_uint64_t	itemids;

	zbx_eval_context_t	*eval_ctx;
	zbx_eval_context_t	*eval_ctx_r;
}
DC_TRIGGER;

/* needed to collect and pass data about items that are involved in generating problem events */
typedef struct
{
	zbx_uint64_t		triggerid;
	zbx_vector_uint64_t	itemids;
}
zbx_trigger_items_t;

typedef struct
{
	zbx_uint64_t	hostid;
	char		host[ZBX_HOSTNAME_BUF_LEN];
	int		proxy_config_nextcheck;
	int		proxy_data_nextcheck;
	int		proxy_tasks_nextcheck;
	int		last_cfg_error_time;	/* time when passive proxy misconfiguration error was seen */
						/* or 0 if no error */
	int		version;
	int		lastaccess;
	char		addr_orig[ZBX_INTERFACE_ADDR_LEN_MAX];
	char		port_orig[ZBX_INTERFACE_PORT_LEN_MAX];
	char		*addr;
	unsigned short	port;

	unsigned char	auto_compress;
	unsigned char	tls_connect;
	unsigned char	tls_accept;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	char		tls_issuer[HOST_TLS_ISSUER_LEN_MAX];
	char		tls_subject[HOST_TLS_SUBJECT_LEN_MAX];
	char		tls_psk_identity[HOST_TLS_PSK_IDENTITY_LEN_MAX];
	char		tls_psk[HOST_TLS_PSK_LEN_MAX];
#endif
	char		proxy_address[HOST_PROXY_ADDRESS_LEN_MAX];
	int		last_version_error_time;
}
DC_PROXY;

#define ZBX_ACTION_OPCLASS_NONE			0
#define ZBX_ACTION_OPCLASS_NORMAL		1
#define ZBX_ACTION_OPCLASS_RECOVERY		2
#define ZBX_ACTION_OPCLASS_ACKNOWLEDGE		4

typedef struct
{
	zbx_uint64_t		actionid;
	char			*formula;
	unsigned char		eventsource;
	unsigned char		evaltype;
	unsigned char		opflags;
	zbx_vector_ptr_t	conditions;
}
zbx_action_eval_t;

typedef struct
{
	char	*host;
	char	*key;
}
zbx_host_key_t;

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

typedef struct
{
	zbx_uint64_t	hostid;
	unsigned char	idx;
	const char	*field_name;
	char		*value;
}
zbx_inventory_value_t;

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

typedef struct
{
	unsigned char	type;
}
zbx_corr_operation_t;

typedef struct
{
	zbx_uint64_t		correlationid;
	char			*name;
	char			*formula;
	unsigned char		evaltype;

	zbx_vector_ptr_t	conditions;
	zbx_vector_ptr_t	operations;
}
zbx_correlation_t;

typedef struct
{
	zbx_vector_ptr_t	correlations;
	zbx_hashset_t		conditions;

	/* Configuration synchronization timestamp of the rules. */
	/* Update the cache if this timesamp is less than the    */
	/* current configuration synchronization timestamp.      */
	int			sync_ts;
}
zbx_correlation_rules_t;

/* value_avg_t structure is used for item average value trend calculations. */
/*                                                                          */
/* For double values the average value is calculated on the fly with the    */
/* following formula: avg = (dbl * count + value) / (count + 1) and stored  */
/* into dbl member.                                                         */
/* For uint64 values the item values are summed into ui64 member and the    */
/* average value is calculated before flushing trends to database:          */
/* avg = ui64 / count                                                       */
typedef union
{
	double		dbl;
	zbx_uint128_t	ui64;
}
value_avg_t;

typedef struct
{
	zbx_uint64_t	itemid;
	history_value_t	value_min;
	value_avg_t	value_avg;
	history_value_t	value_max;
	int		clock;
	int		num;
	int		disable_from;
	unsigned char	value_type;
}
ZBX_DC_TREND;

typedef struct
{
	zbx_uint64_t	itemid;
	history_value_t	value;
	zbx_uint64_t	lastlogsize;
	zbx_timespec_t	ts;
	int		mtime;
	unsigned char	value_type;
	unsigned char	flags;		/* see ZBX_DC_FLAG_* */
	unsigned char	state;
	int		ttl;		/* time-to-live of the history value */
}
ZBX_DC_HISTORY;

/* item queue data */
typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	proxy_hostid;
	int		type;
	int		nextcheck;
}
zbx_queue_item_t;

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

typedef enum
{
	ZBX_COUNTER_TYPE_UI64,
	ZBX_COUNTER_TYPE_DBL
}
zbx_counter_type_t;

typedef struct
{
	unsigned char	type;
	unsigned char	error_handler;
	char		*params;
	char		*params_orig;
	char		*error_handler_params;
}
zbx_preproc_op_t;

#define ZBX_PREPROC_MACRO_UPDATE_TRUE	1
#define ZBX_PREPROC_MACRO_UPDATE_FALSE	0

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		hostid;
	unsigned char		type;
	unsigned char		value_type;

	int			dep_itemids_num;
	int			preproc_ops_num;
	int			update_time;
	int			macro_update;

	zbx_uint64_pair_t	*dep_itemids;
	zbx_preproc_op_t	*preproc_ops;
}
zbx_preproc_item_t;

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
	zbx_uint64_t	history_counter;	/* the total number of processed values */
	zbx_uint64_t	history_float_counter;	/* the number of processed float values */
	zbx_uint64_t	history_uint_counter;	/* the number of processed uint values */
	zbx_uint64_t	history_str_counter;	/* the number of processed str values */
	zbx_uint64_t	history_log_counter;	/* the number of processed log values */
	zbx_uint64_t	history_text_counter;	/* the number of processed text values */
	zbx_uint64_t	notsupported_counter;	/* the number of processed not supported items */
}
ZBX_DC_STATS;

/* the write cache statistics */
typedef struct
{
	ZBX_DC_STATS	stats;
	zbx_uint64_t	history_free;
	zbx_uint64_t	history_total;
	zbx_uint64_t	index_free;
	zbx_uint64_t	index_total;
	zbx_uint64_t	trend_free;
	zbx_uint64_t	trend_total;
}
zbx_wcache_info_t;

int	is_item_processed_by_server(unsigned char type, const char *key);
int	zbx_is_counted_in_item_queue(unsigned char type, const char *key);
int	in_maintenance_without_data_collection(unsigned char maintenance_status, unsigned char maintenance_type,
		unsigned char type);
void	dc_add_history(zbx_uint64_t itemid, unsigned char item_value_type, unsigned char item_flags,
		AGENT_RESULT *result, const zbx_timespec_t *ts, unsigned char state, const char *error);
void	dc_flush_history(void);
void	zbx_sync_history_cache(int *values_num, int *triggers_num, int *more);
void	zbx_log_sync_history_cache_progress(void);

#define ZBX_SYNC_NONE	0
#define ZBX_SYNC_ALL	1

int	init_database_cache(char **error);
void	free_database_cache(int);

void	change_proxy_history_count(int change_count);
void	reset_proxy_history_count(int reset);
int	get_proxy_history_count(void);

#define ZBX_STATS_HISTORY_COUNTER	0
#define ZBX_STATS_HISTORY_FLOAT_COUNTER	1
#define ZBX_STATS_HISTORY_UINT_COUNTER	2
#define ZBX_STATS_HISTORY_STR_COUNTER	3
#define ZBX_STATS_HISTORY_LOG_COUNTER	4
#define ZBX_STATS_HISTORY_TEXT_COUNTER	5
#define ZBX_STATS_NOTSUPPORTED_COUNTER	6
#define ZBX_STATS_HISTORY_TOTAL		7
#define ZBX_STATS_HISTORY_USED		8
#define ZBX_STATS_HISTORY_FREE		9
#define ZBX_STATS_HISTORY_PUSED		10
#define ZBX_STATS_HISTORY_PFREE		11
#define ZBX_STATS_TREND_TOTAL		12
#define ZBX_STATS_TREND_USED		13
#define ZBX_STATS_TREND_FREE		14
#define ZBX_STATS_TREND_PUSED		15
#define ZBX_STATS_TREND_PFREE		16
#define ZBX_STATS_HISTORY_INDEX_TOTAL	17
#define ZBX_STATS_HISTORY_INDEX_USED	18
#define ZBX_STATS_HISTORY_INDEX_FREE	19
#define ZBX_STATS_HISTORY_INDEX_PUSED	20
#define ZBX_STATS_HISTORY_INDEX_PFREE	21
void	*DCget_stats(int request);
void	DCget_stats_all(zbx_wcache_info_t *wcache_info);

zbx_uint64_t	DCget_nextid(const char *table_name, int num);

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

#define ZBX_ITEM_GET_MISC		0x0001
#define ZBX_ITEM_GET_DELAY		0x0002
#define ZBX_ITEM_GET_EMPTY_ERROR	0x0004
#define ZBX_ITEM_GET_NUM		0x0008
#define ZBX_ITEM_GET_EMPTY_UNITS	0x0010
#define ZBX_ITEM_GET_LOGTIMEFMT		0x0020
#define ZBX_ITEM_GET_POLLINFO		0x0040
#define ZBX_ITEM_GET_INTERFACE		0x0080
#define ZBX_ITEM_GET_HOSTNAME		0x0100
#define ZBX_ITEM_GET_HOSTINFO		0x0200
#define ZBX_ITEM_GET_MAINTENANCE	0x0400
#define ZBX_ITEM_GET_INVENTORY		0x0800
#define ZBX_ITEM_GET_HOUSEKEEPING	0x1000

#define ZBX_ITEM_GET_DEFAULT		(~(unsigned int)ZBX_ITEM_GET_HOUSEKEEPING)
#define ZBX_ITEM_GET_ALL		(~(unsigned int)0)

#define ZBX_ITEM_GET_SYNC		(ZBX_ITEM_GET_INVENTORY | ZBX_ITEM_GET_NUM | ZBX_ITEM_GET_HOUSEKEEPING)
#define ZBX_ITEM_GET_SYNC_EXPORT	(ZBX_ITEM_GET_INVENTORY | ZBX_ITEM_GET_NUM | ZBX_ITEM_GET_HOSTNAME | \
					ZBX_ITEM_GET_HOUSEKEEPING)

#define ZBX_ITEM_GET_PROCESS		(ZBX_ITEM_GET_MAINTENANCE|ZBX_ITEM_GET_MISC|ZBX_ITEM_GET_LOGTIMEFMT)

#define ZBX_TRIGGER_GET_ITEMIDS		0x0001

#define ZBX_TRIGGER_GET_DEFAULT		(~(unsigned int)ZBX_TRIGGER_GET_ITEMIDS)
#define ZBX_TRIGGER_GET_ALL		(~(unsigned int)0)

void	DCsync_configuration(unsigned char mode, zbx_synced_new_config_t synced);
void	DCsync_kvs_paths(const struct zbx_json_parse *jp_kvs_paths);
int	init_configuration_cache(char **error);
void	free_configuration_cache(void);

void	DCconfig_get_triggers_by_triggerids(DC_TRIGGER *triggers, const zbx_uint64_t *triggerids, int *errcode,
		size_t num);
void	DCconfig_clean_items(DC_ITEM *items, int *errcodes, size_t num);
int	DCget_host_by_hostid(DC_HOST *host, zbx_uint64_t hostid);
int	DCconfig_get_hostid_by_name(const char *host, zbx_uint64_t *hostid);
void	DCconfig_get_hosts_by_itemids(DC_HOST *hosts, const zbx_uint64_t *itemids, int *errcodes, size_t num);
void	DCconfig_get_hosts_by_hostids(DC_HOST *hosts, const zbx_uint64_t *hostids, int *errcodes, int num);
void	DCconfig_get_items_by_keys(DC_ITEM *items, zbx_host_key_t *keys, int *errcodes, size_t num);
void	DCconfig_get_items_by_itemids(DC_ITEM *items, const zbx_uint64_t *itemids, int *errcodes, size_t num);
void	DCconfig_get_items_by_itemids_partial(DC_ITEM *items, const zbx_uint64_t *itemids, int *errcodes, int num,
		unsigned int mode);
void	DCconfig_get_preprocessable_items(zbx_hashset_t *items, int *timestamp);
void	DCconfig_get_functions_by_functionids(DC_FUNCTION *functions,
		zbx_uint64_t *functionids, int *errcodes, size_t num);
void	DCconfig_clean_functions(DC_FUNCTION *functions, int *errcodes, size_t num);
void	DCconfig_clean_triggers(DC_TRIGGER *triggers, int *errcodes, size_t num);
int	DCconfig_lock_triggers_by_history_items(zbx_vector_ptr_t *history_items, zbx_vector_uint64_t *triggerids);
void	DCconfig_lock_triggers_by_triggerids(zbx_vector_uint64_t *triggerids_in, zbx_vector_uint64_t *triggerids_out);
void	DCconfig_unlock_triggers(const zbx_vector_uint64_t *triggerids);
void	DCconfig_unlock_all_triggers(void);
void	DCconfig_get_triggers_by_itemids(zbx_hashset_t *trigger_info, zbx_vector_ptr_t *trigger_order,
		const zbx_uint64_t *itemids, const zbx_timespec_t *timespecs, int itemids_num);
int	DCconfig_trigger_exists(zbx_uint64_t triggerid);
void	DCfree_triggers(zbx_vector_ptr_t *triggers);
void	DCconfig_update_interface_snmp_stats(zbx_uint64_t interfaceid, int max_snmp_succeed, int min_snmp_fail);
int	DCconfig_get_suggested_snmp_vars(zbx_uint64_t interfaceid, int *bulk);
int	DCconfig_get_interface_by_type(DC_INTERFACE *interface, zbx_uint64_t hostid, unsigned char type);
int	DCconfig_get_interface(DC_INTERFACE *interface, zbx_uint64_t hostid, zbx_uint64_t itemid);
int	DCconfig_get_poller_nextcheck(unsigned char poller_type);
int	DCconfig_get_poller_items(unsigned char poller_type, DC_ITEM **items);
int	DCconfig_get_ipmi_poller_items(int now, DC_ITEM *items, int items_num, int *nextcheck);
int	DCconfig_get_snmp_interfaceids_by_addr(const char *addr, zbx_uint64_t **interfaceids);
size_t	DCconfig_get_snmp_items_by_interfaceid(zbx_uint64_t interfaceid, DC_ITEM **items);

#define ZBX_HK_OPTION_DISABLED		0
#define ZBX_HK_OPTION_ENABLED		1

/* options for hk.history_mode, trends_mode */
#define ZBX_HK_MODE_DISABLED		ZBX_HK_OPTION_DISABLED
#define ZBX_HK_MODE_REGULAR		ZBX_HK_OPTION_ENABLED
#define ZBX_HK_MODE_PARTITION		2

#define ZBX_HK_HISTORY_MIN	SEC_PER_HOUR
#define ZBX_HK_TRENDS_MIN	SEC_PER_DAY
#define ZBX_HK_PERIOD_MAX	(25 * SEC_PER_YEAR)

void	DCrequeue_items(const zbx_uint64_t *itemids, const int *lastclocks,
		const int *errcodes, size_t num);
void	DCpoller_requeue_items(const zbx_uint64_t *itemids, const int *lastclocks,
		const int *errcodes, size_t num, unsigned char poller_type, int *nextcheck);
void	zbx_dc_requeue_unreachable_items(zbx_uint64_t *itemids, size_t itemids_num);
int	DCconfig_activate_host(DC_ITEM *item);
int	DCconfig_deactivate_host(DC_ITEM *item, int now);

int	DCconfig_check_trigger_dependencies(zbx_uint64_t triggerid);

void	DCconfig_triggers_apply_changes(zbx_vector_ptr_t *trigger_diff);
void	DCconfig_items_apply_changes(const zbx_vector_ptr_t *item_diff);

void	DCconfig_update_inventory_values(const zbx_vector_ptr_t *inventory_values);
int	DCget_host_inventory_value_by_itemid(zbx_uint64_t itemid, char **replace_to, int value_idx);
int	DCget_host_inventory_value_by_hostid(zbx_uint64_t hostid, char **replace_to, int value_idx);

#define ZBX_CONFSTATS_BUFFER_TOTAL	1
#define ZBX_CONFSTATS_BUFFER_USED	2
#define ZBX_CONFSTATS_BUFFER_FREE	3
#define ZBX_CONFSTATS_BUFFER_PUSED	4
#define ZBX_CONFSTATS_BUFFER_PFREE	5
void	*DCconfig_get_stats(int request);

int	DCconfig_get_last_sync_time(void);
int	DCconfig_get_proxypoller_hosts(DC_PROXY *proxies, int max_hosts);
int	DCconfig_get_proxypoller_nextcheck(void);

#define ZBX_PROXY_CONFIG_NEXTCHECK	0x01
#define ZBX_PROXY_DATA_NEXTCHECK	0x02
#define ZBX_PROXY_TASKS_NEXTCHECK	0x04
void	DCrequeue_proxy(zbx_uint64_t hostid, unsigned char update_nextcheck, int proxy_conn_err);
int	DCcheck_proxy_permissions(const char *host, const zbx_socket_t *sock, zbx_uint64_t *hostid, char **error);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
size_t	DCget_psk_by_identity(const unsigned char *psk_identity, unsigned char *psk_buf, unsigned int *psk_usage);
#endif
void	DCget_autoregistration_psk(char *psk_identity_buf, size_t psk_identity_buf_len,
		unsigned char *psk_buf, size_t psk_buf_len);

#define ZBX_MACRO_ENV_SECURE	0
#define ZBX_MACRO_ENV_NONSECURE	1
#define ZBX_MACRO_ENV_DEFAULT	2

#define ZBX_MACRO_VALUE_TEXT	0
#define ZBX_MACRO_VALUE_SECRET	1
#define ZBX_MACRO_VALUE_VAULT	2

#define ZBX_MACRO_SECRET_MASK	"******"

int	DCinterface_activate(zbx_uint64_t interfaceid, const zbx_timespec_t *ts, zbx_agent_availability_t *in,
		zbx_agent_availability_t *out);

int	DCinterface_deactivate(zbx_uint64_t interfaceid, const zbx_timespec_t *ts, zbx_agent_availability_t *in,
		zbx_agent_availability_t *out, const char *error_msg);

#define ZBX_QUEUE_FROM_DEFAULT	6	/* default lower limit for delay (in seconds) */
#define ZBX_QUEUE_TO_INFINITY	-1	/* no upper limit for delay */
void	DCfree_item_queue(zbx_vector_ptr_t *queue);
int	DCget_item_queue(zbx_vector_ptr_t *queue, int from, int to);

zbx_uint64_t	DCget_item_count(zbx_uint64_t hostid);
zbx_uint64_t	DCget_item_unsupported_count(zbx_uint64_t hostid);
zbx_uint64_t	DCget_trigger_count(void);
double		DCget_required_performance(void);
zbx_uint64_t	DCget_host_count(void);
void		DCget_count_stats_all(zbx_config_cache_info_t *stats);

void	DCget_status(zbx_vector_ptr_t *hosts_monitored, zbx_vector_ptr_t *hosts_not_monitored,
		zbx_vector_ptr_t *items_active_normal, zbx_vector_ptr_t *items_active_notsupported,
		zbx_vector_ptr_t *items_disabled, zbx_uint64_t *triggers_enabled_ok,
		zbx_uint64_t *triggers_enabled_problem, zbx_uint64_t *triggers_disabled,
		zbx_vector_ptr_t *required_performance);

void	DCget_expressions_by_names(zbx_vector_ptr_t *expressions, const char * const *names, int names_num);
void	DCget_expressions_by_name(zbx_vector_ptr_t *expressions, const char *name);

int	DCget_data_expected_from(zbx_uint64_t itemid, int *seconds);

void	DCget_hostids_by_functionids(zbx_vector_uint64_t *functionids, zbx_vector_uint64_t *hostids);
void	DCget_hosts_by_functionids(const zbx_vector_uint64_t *functionids, zbx_hashset_t *hosts);

int	DCget_proxy_nodata_win(zbx_uint64_t hostid, zbx_proxy_suppress_t *nodata_win, int *lastaccess);
int	DCget_proxy_delay_by_name(const char *name, int *delay, char **error);
int	DCget_proxy_lastaccess_by_name(const char *name, int *lastaccess, char **error);

unsigned int	DCget_internal_action_count(void);

/* global configuration support */
#define ZBX_DISCOVERY_GROUPID_UNDEFINED	0
void	zbx_config_get(zbx_config_t *cfg, zbx_uint64_t flags);
void	zbx_config_clean(zbx_config_t *cfg);
void	zbx_config_get_hk_mode(unsigned char *history_mode, unsigned char *trends_mode);

int	DCset_interfaces_availability(zbx_vector_availability_ptr_t *availabilities);

int	DCreset_interfaces_availability(zbx_vector_availability_ptr_t *interfaces);
void	DCupdate_interfaces_availability(void);

void	zbx_dc_get_actions_eval(zbx_vector_ptr_t *actions, unsigned char opflags);

int	DCget_interfaces_availability(zbx_vector_ptr_t *interfaces, int *ts);
void	DCtouch_interfaces_availability(const zbx_vector_uint64_t *interfaceids);

void	zbx_set_availability_diff_ts(int ts);

void	zbx_dc_correlation_rules_init(zbx_correlation_rules_t *rules);
void	zbx_dc_correlation_rules_clean(zbx_correlation_rules_t *rules);
void	zbx_dc_correlation_rules_free(zbx_correlation_rules_t *rules);
void	zbx_dc_correlation_rules_get(zbx_correlation_rules_t *rules);

void	zbx_dc_get_nested_hostgroupids(zbx_uint64_t *groupids, int groupids_num, zbx_vector_uint64_t *nested_groupids);
void	zbx_dc_get_hostids_by_group_name(const char *name, zbx_vector_uint64_t *hostids);

#define ZBX_HC_ITEM_STATUS_NORMAL	0
#define ZBX_HC_ITEM_STATUS_BUSY		1

#define ZBX_DC_FLAG_META	0x01	/* contains meta information (lastlogsize and mtime) */
#define ZBX_DC_FLAG_NOVALUE	0x02	/* entry contains no value */
#define ZBX_DC_FLAG_LLD		0x04	/* low-level discovery value */
#define ZBX_DC_FLAG_UNDEF	0x08	/* unsupported or undefined (delta calculation failed) value */
#define ZBX_DC_FLAG_NOHISTORY	0x10	/* values should not be kept in history */
#define ZBX_DC_FLAG_NOTRENDS	0x20	/* values should not be kept in trends */

typedef struct zbx_hc_data
{
	history_value_t	value;
	zbx_uint64_t	lastlogsize;
	zbx_timespec_t	ts;
	int		mtime;
	unsigned char	value_type;
	unsigned char	flags;
	unsigned char	state;

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

void	zbx_free_item_tag(zbx_item_tag_t *item_tag);

int	zbx_dc_get_active_proxy_by_name(const char *name, DC_PROXY *proxy, char **error);
void	zbx_dc_update_proxy_version(zbx_uint64_t hostid, int version);

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

void	zbx_dc_items_update_nextcheck(DC_ITEM *items, zbx_agent_value_t *values, int *errcodes, size_t values_num);
int	zbx_dc_get_host_interfaces(zbx_uint64_t hostid, DC_INTERFACE2 **interfaces, int *n);

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

void	zbx_dc_get_trigger_dependencies(const zbx_vector_uint64_t *triggerids, zbx_vector_ptr_t *deps);

void	zbx_dc_reschedule_items(const zbx_vector_uint64_t *itemids, int nextcheck, zbx_uint64_t *proxy_hostids);

/* data session support */

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	last_valueid;
	const char	*token;
	time_t		lastaccess;
}
zbx_data_session_t;

const char	*zbx_dc_get_session_token(void);
zbx_data_session_t	*zbx_dc_get_or_create_data_session(zbx_uint64_t hostid, const char *token);
void	zbx_dc_cleanup_data_sessions(void);

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

/* event maintenance query data, used to get event maintenances from cache */
typedef struct
{
	zbx_uint64_t			eventid;		/* [IN] eventid */
	zbx_uint64_t			r_eventid;		/* [-] recovery eventid */
	zbx_uint64_t			triggerid;		/* [-] triggerid */
	zbx_vector_uint64_t		functionids;		/* [IN] associated functionids */
	zbx_vector_ptr_t		tags;			/* [IN] event tags */
	zbx_vector_uint64_pair_t	maintenances;		/* [OUT] actual maintenance data for the event in */
								/* (maintenanceid, suppress_until) pairs */
}
zbx_event_suppress_query_t;

#define ZBX_MAINTENANCE_UPDATE_TRUE	1
#define ZBX_MAINTENANCE_UPDATE_FALSE	0

void	zbx_event_suppress_query_free(zbx_event_suppress_query_t *query);
int	zbx_dc_update_maintenances(void);
void	zbx_dc_get_host_maintenance_updates(const zbx_vector_uint64_t *maintenanceids, zbx_vector_ptr_t *updates);
void	zbx_dc_flush_host_maintenance_updates(const zbx_vector_ptr_t *updates);
int	zbx_dc_get_event_maintenances(zbx_vector_ptr_t *event_queries, const zbx_vector_uint64_t *maintenanceids);
int	zbx_dc_get_running_maintenanceids(zbx_vector_uint64_t *maintenanceids);

void	zbx_dc_maintenance_set_update_flags(void);
void	zbx_dc_maintenance_reset_update_flag(int timer);
int	zbx_dc_maintenance_check_update_flag(int timer);
int	zbx_dc_maintenance_check_update_flags(void);

int	zbx_dc_maintenance_has_tags(void);

typedef struct
{
	char	*lld_macro;
	char	*path;
}
zbx_lld_macro_path_t;

int	zbx_lld_macro_paths_get(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *lld_macro_paths, char **error);
void	zbx_lld_macro_path_free(zbx_lld_macro_path_t *lld_macro_path);
int	zbx_lld_macro_value_by_name(const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths,
		const char *macro, char **value);
int	zbx_lld_macro_paths_compare(const void *d1, const void *d2);

void	zbx_dc_get_item_tags(zbx_uint64_t itemid, zbx_vector_ptr_t *item_tags);
void	zbx_dc_get_item_tags_by_functionids(const zbx_uint64_t *functionids, size_t functionids_num,
		zbx_vector_ptr_t *item_tags);

const char	*zbx_dc_get_instanceid(void);

/* diagnostic data */
void	zbx_hc_get_diag_stats(zbx_uint64_t *items_num, zbx_uint64_t *values_num);
void	zbx_hc_get_mem_stats(zbx_shmem_stats_t *data, zbx_shmem_stats_t *index);
void	zbx_hc_get_items(zbx_vector_uint64_pair_t *items);

typedef struct
{
	zbx_uint64_t		objectid;
	zbx_uint64_t		triggerid;
	zbx_uint64_t		hostid;
	zbx_uint32_t		type;
	unsigned char		lock;		/* 1 if the timer has locked trigger, 0 otherwise */
	int			revision;	/* revision */
	time_t			lastcheck;
	zbx_timespec_t		eval_ts;	/* the history time for which trigger must be recalculated */
	zbx_timespec_t		check_ts;	/* time when timer must be checked */
	zbx_timespec_t		exec_ts;	/* real time when the timer must be executed */
	const char		*parameter;	/* function parameters (for trend functions) */
}
zbx_trigger_timer_t;

void	zbx_dc_reschedule_trigger_timers(zbx_vector_ptr_t *timers, int now);
void	zbx_dc_get_trigger_timers(zbx_vector_ptr_t *timers, int now, int soft_limit, int hard_limit);
void	zbx_dc_clear_timer_queue(zbx_vector_ptr_t *timers);
void	zbx_dc_get_triggers_by_timers(zbx_hashset_t *trigger_info, zbx_vector_ptr_t *trigger_order,
		const zbx_vector_ptr_t *timers);
void	zbx_dc_free_timers(zbx_vector_ptr_t *timers);

int	zbx_db_trigger_queue_locked(void);
void	zbx_db_trigger_queue_unlock(void);

void	zbx_get_host_interfaces_availability(zbx_uint64_t	hostid, zbx_agent_availability_t *agents);

int	zbx_hc_check_proxy(zbx_uint64_t proxyid);

typedef int (*zbx_trigger_func_t)(zbx_variant_t *, const DC_ITEM *, const char *, const char *,
		const zbx_timespec_t *, char **);

void	zbx_db_trigger_explain_expression(const ZBX_DB_TRIGGER *trigger, char **expression,
		zbx_trigger_func_t eval_func_cb, int recovery);
void	zbx_db_trigger_get_function_value(const ZBX_DB_TRIGGER *trigger, int index, char **value,
		zbx_trigger_func_t eval_func_cb, int recovery);

/* external user macro cache API */

typedef struct zbx_dc_um_handle_t zbx_dc_um_handle_t;

zbx_dc_um_handle_t	*zbx_dc_open_user_macros(void);
zbx_dc_um_handle_t	*zbx_dc_open_user_macros_secure(void);
zbx_dc_um_handle_t	*zbx_dc_open_user_macros_masked(void);

void	zbx_dc_close_user_macros(zbx_dc_um_handle_t *um_handle);

void	zbx_dc_get_user_macro(const zbx_dc_um_handle_t *um_handle, const char *macro, const zbx_uint64_t *hostids,
		int hostids_num, char **value);

int	zbx_dc_expand_user_macros(const zbx_dc_um_handle_t *um_handle, char **text, const zbx_uint64_t *hostids,
		int hostids_num, char **error);

char	*zbx_dc_expand_user_macros_in_func_params(const char *params, zbx_uint64_t hostid);

int	zbx_dc_get_proxyid_by_name(const char *name, zbx_uint64_t *proxyid, unsigned char *type);
int	zbx_dc_update_passive_proxy_nextcheck(zbx_uint64_t proxyid);

typedef struct
{
	zbx_uint64_t	hostid;
	unsigned char	status;
	char		*name;
}
zbx_cached_proxy_t;

ZBX_PTR_VECTOR_DECL(cached_proxy, zbx_cached_proxy_t *)

void	zbx_dc_get_all_proxies(zbx_vector_cached_proxy_t *proxies);

int	zbx_dc_get_proxy_name_type_by_id(zbx_uint64_t proxyid, int *status, char **name);

#endif
