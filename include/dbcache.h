/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "db.h"
#include "comms.h"
#include "sysinfo.h"
#include "zbxalgo.h"

#define ZBX_SYNC_PARTIAL	0
#define	ZBX_SYNC_FULL		1

#define	ZBX_NO_POLLER			255
#define	ZBX_POLLER_TYPE_NORMAL		0
#define	ZBX_POLLER_TYPE_UNREACHABLE	1
#define	ZBX_POLLER_TYPE_IPMI		2
#define	ZBX_POLLER_TYPE_PINGER		3
#define	ZBX_POLLER_TYPE_JAVA		4
#define	ZBX_POLLER_TYPE_COUNT		5	/* number of poller types */

#define MAX_JAVA_ITEMS		32
#define MAX_SNMP_ITEMS		128
#define MAX_POLLER_ITEMS	128	/* MAX(MAX_JAVA_ITEMS, MAX_SNMP_ITEMS) */
#define MAX_PINGER_ITEMS	128

#define ZBX_TRIGGER_DEPENDENCY_LEVELS_MAX	32

#define ZBX_TRIGGER_DEPENDENCY_FAIL		1
#define ZBX_TRIGGER_DEPENDENCY_UNRESOLVED	2

#define ZBX_SNMPTRAP_LOGGING_ENABLED	1

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
extern int	CONFIG_UNAVAILABLE_DELAY;
extern int	CONFIG_UNREACHABLE_PERIOD;
extern int	CONFIG_UNREACHABLE_DELAY;
extern int	CONFIG_HISTSYNCER_FORKS;
extern int	CONFIG_PROXYCONFIG_FREQUENCY;
extern int	CONFIG_PROXYDATA_FREQUENCY;

typedef struct
{
	zbx_uint64_t	interfaceid;
	char		ip_orig[INTERFACE_IP_LEN_MAX];
	char		dns_orig[INTERFACE_DNS_LEN_MAX];
	char		port_orig[INTERFACE_PORT_LEN_MAX];
	char		*addr;
	unsigned short	port;
	unsigned char	useip;
	unsigned char	type;
	unsigned char	main;
}
DC_INTERFACE;

typedef struct
{
	zbx_uint64_t	interfaceid;
	char		*addr;
	unsigned char	type;
	unsigned char	main;
	unsigned char	bulk;
	unsigned char	useip;
	char		ip_orig[INTERFACE_IP_LEN_MAX];
	char		dns_orig[INTERFACE_DNS_LEN_MAX];
	char		port_orig[INTERFACE_PORT_LEN_MAX];
}
DC_INTERFACE2;

typedef struct
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxy_hostid;
	char		host[HOST_HOST_LEN_MAX];
	char		name[HOST_HOST_LEN_MAX];
	unsigned char	maintenance_status;
	unsigned char	maintenance_type;
	int		maintenance_from;
	int		errors_from;
	unsigned char	available;
	int		disable_until;
	int		snmp_errors_from;
	unsigned char	snmp_available;
	int		snmp_disable_until;
	int		ipmi_errors_from;
	unsigned char	ipmi_available;
	int		ipmi_disable_until;
	signed char	ipmi_authtype;
	unsigned char	ipmi_privilege;
	char		ipmi_username[HOST_IPMI_USERNAME_LEN_MAX];
	char		ipmi_password[HOST_IPMI_PASSWORD_LEN_MAX];
	int		jmx_errors_from;
	unsigned char	jmx_available;
	int		jmx_disable_until;
	char		inventory_mode;
	unsigned char	status;
	unsigned char	tls_connect;
	unsigned char	tls_accept;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	char		tls_issuer[HOST_TLS_ISSUER_LEN_MAX];
	char		tls_subject[HOST_TLS_SUBJECT_LEN_MAX];
	char		tls_psk_identity[HOST_TLS_PSK_IDENTITY_LEN_MAX];
	char		tls_psk[HOST_TLS_PSK_LEN_MAX];
#endif
	char		error[HOST_ERROR_LEN_MAX];
	char		snmp_error[HOST_ERROR_LEN_MAX];
	char		ipmi_error[HOST_ERROR_LEN_MAX];
	char		jmx_error[HOST_ERROR_LEN_MAX];
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
	char			key_orig[ITEM_KEY_LEN * 4 + 1], *key;
	char			*units;
	char			*delay;
	int			history_sec;
	int			nextcheck;
	int			lastclock;
	int			mtime;
	char			trapper_hosts[ITEM_TRAPPER_HOSTS_LEN_MAX];
	char			logtimefmt[ITEM_LOGTIMEFMT_LEN_MAX];
	char			snmp_community_orig[ITEM_SNMP_COMMUNITY_LEN_MAX], *snmp_community;
	char			snmp_oid_orig[ITEM_SNMP_OID_LEN_MAX], *snmp_oid;
	char			snmpv3_securityname_orig[ITEM_SNMPV3_SECURITYNAME_LEN_MAX], *snmpv3_securityname;
	char			snmpv3_authpassphrase_orig[ITEM_SNMPV3_AUTHPASSPHRASE_LEN_MAX], *snmpv3_authpassphrase;
	char			snmpv3_privpassphrase_orig[ITEM_SNMPV3_PRIVPASSPHRASE_LEN_MAX], *snmpv3_privpassphrase;
	char			ipmi_sensor[ITEM_IPMI_SENSOR_LEN_MAX];
	char			*params;
	char			username_orig[ITEM_USERNAME_LEN_MAX], *username;
	char			publickey_orig[ITEM_PUBLICKEY_LEN_MAX], *publickey;
	char			privatekey_orig[ITEM_PRIVATEKEY_LEN_MAX], *privatekey;
	char			password_orig[ITEM_PASSWORD_LEN_MAX], *password;
	char			snmpv3_contextname_orig[ITEM_SNMPV3_CONTEXTNAME_LEN_MAX], *snmpv3_contextname;
	char			jmx_endpoint_orig[ITEM_JMX_ENDPOINT_LEN_MAX], *jmx_endpoint;
	char			*error;
}
DC_ITEM;

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_uint64_t	triggerid;
	zbx_uint64_t	itemid;
	char		*function;
	char		*parameter;
}
DC_FUNCTION;

typedef struct
{
	char	*tag;
	char	*value;
}
zbx_tag_t;

#define ZBX_DC_TRIGGER_PROBLEM_EXPRESSION	0x1	/* this flag shows that trigger value recalculation is  */
							/* initiated by a time-based function or a new value of */
							/* an item in problem expression */

typedef struct _DC_TRIGGER
{
	zbx_uint64_t		triggerid;
	char			*description;
	char			*expression_orig;
	char			*recovery_expression_orig;
	/* temporary values, allocated during processing and freed right after */
	char			*expression;
	char			*recovery_expression;

	char			*error;
	char			*new_error;
	char			*correlation_tag;
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
}
DC_TRIGGER;

typedef struct
{
	zbx_uint64_t	hostid;
	char		host[HOST_HOST_LEN_MAX];
	int		proxy_config_nextcheck;
	int		proxy_data_nextcheck;
	int		proxy_tasks_nextcheck;
	int		last_cfg_error_time;	/* time when passive proxy misconfiguration error was seen */
						/* or 0 if no error */
	int		version;
	char		addr_orig[INTERFACE_ADDR_LEN_MAX];
	char		port_orig[INTERFACE_PORT_LEN_MAX];
	char		*addr;
	unsigned short	port;

	unsigned char	tls_connect;
	unsigned char	tls_accept;

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	char		tls_issuer[HOST_TLS_ISSUER_LEN_MAX];
	char		tls_subject[HOST_TLS_SUBJECT_LEN_MAX];
	char		tls_psk_identity[HOST_TLS_PSK_IDENTITY_LEN_MAX];
	char		tls_psk[HOST_TLS_PSK_LEN_MAX];
#endif
	char		proxy_address[HOST_PROXY_ADDRESS_LEN_MAX];
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

/* global configuration data (loaded from config table) */
typedef struct
{
	/* the fields set by zbx_config_get() function, see ZBX_CONFIG_FLAGS_ defines */
	zbx_uint64_t	flags;

	char		**severity_name;
	zbx_uint64_t	discovery_groupid;
	int		default_inventory_mode;
	int		refresh_unsupported;
	unsigned char	snmptrap_logging;

	/* housekeeping related configuration data */
	zbx_config_hk_t	hk;
}
zbx_config_t;

#define ZBX_CONFIG_FLAGS_SEVERITY_NAME			0x00000001
#define ZBX_CONFIG_FLAGS_DISCOVERY_GROUPID		0x00000002
#define ZBX_CONFIG_FLAGS_DEFAULT_INVENTORY_MODE		0x00000004
#define ZBX_CONFIG_FLAGS_REFRESH_UNSUPPORTED		0x00000008
#define ZBX_CONFIG_FLAGS_SNMPTRAP_LOGGING		0x00000010
#define ZBX_CONFIG_FLAGS_HOUSEKEEPER			0x00000020

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

	/* Configuration synchonization timestamp of the rules. */
	/* Update the cache if this timesamp is less than the   */
	/* current configuration synchonization timestamp.      */
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
	zbx_uint64_t		itemid;
	zbx_timespec_t		timestamp;
	zbx_variant_t		value;
	unsigned char		value_type;
}
zbx_item_history_value_t;

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
	char		*params;
}
zbx_preproc_op_t;

typedef struct
{
	zbx_uint64_t		itemid;
	unsigned char		type;
	unsigned char		value_type;

	int			dep_itemids_num;
	int			preproc_ops_num;

	zbx_uint64_t		*dep_itemids;
	zbx_preproc_op_t	*preproc_ops;
}
zbx_preproc_item_t;

int	is_item_processed_by_server(unsigned char type, const char *key);
int	in_maintenance_without_data_collection(unsigned char maintenance_status, unsigned char maintenance_type,
		unsigned char type);
void	dc_add_history(zbx_uint64_t itemid, unsigned char item_flags, AGENT_RESULT *result, const zbx_timespec_t *ts,
		unsigned char state, const char *error);
void	dc_flush_history(void);
int	DCsync_history(int sync_type, int *sync_num);
int	init_database_cache(char **error);
void	free_database_cache(void);

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
#define ZBX_STATS_HISTORY_PFREE		10
#define ZBX_STATS_TREND_TOTAL		11
#define ZBX_STATS_TREND_USED		12
#define ZBX_STATS_TREND_FREE		13
#define ZBX_STATS_TREND_PFREE		14
#define ZBX_STATS_HISTORY_INDEX_TOTAL	15
#define ZBX_STATS_HISTORY_INDEX_USED	16
#define ZBX_STATS_HISTORY_INDEX_FREE	17
#define ZBX_STATS_HISTORY_INDEX_PFREE	18
void	*DCget_stats(int request);

zbx_uint64_t	DCget_nextid(const char *table_name, int num);

/* initial sync, get all data */
#define ZBX_DBSYNC_INIT		0
/* update sync, get changed data */
#define ZBX_DBSYNC_UPDATE	1

void	DCsync_configuration(unsigned char mode);
int	init_configuration_cache(char **error);
void	free_configuration_cache(void);

void	DCconfig_get_triggers_by_triggerids(DC_TRIGGER *triggers, const zbx_uint64_t *triggerids, int *errcode,
		size_t num);
void	DCconfig_clean_items(DC_ITEM *items, int *errcodes, size_t num);
int	DCget_host_by_hostid(DC_HOST *host, zbx_uint64_t hostid);
void	DCconfig_get_hosts_by_itemids(DC_HOST *hosts, const zbx_uint64_t *itemids, int *errcodes, size_t num);
void	DCconfig_get_items_by_keys(DC_ITEM *items, zbx_host_key_t *keys, int *errcodes, size_t num);
void	DCconfig_get_items_by_itemids(DC_ITEM *items, const zbx_uint64_t *itemids, int *errcodes, size_t num);
void	DCconfig_get_preprocessable_items(zbx_hashset_t *items, int *timestamp);
void	DCconfig_get_functions_by_functionids(DC_FUNCTION *functions,
		zbx_uint64_t *functionids, int *errcodes, size_t num);
void	DCconfig_clean_functions(DC_FUNCTION *functions, int *errcodes, size_t num);
void	DCconfig_clean_triggers(DC_TRIGGER *triggers, int *errcodes, size_t num);
int	DCconfig_lock_triggers_by_history_items(zbx_vector_ptr_t *history_items, zbx_vector_uint64_t *triggerids);
void	DCconfig_lock_triggers_by_triggerids(zbx_vector_uint64_t *triggerids_in, zbx_vector_uint64_t *triggerids_out);
void	DCconfig_unlock_triggers(const zbx_vector_uint64_t *triggerids);
void	DCconfig_unlock_all_triggers(void);
int	DCconfig_lock_lld_rule(zbx_uint64_t lld_ruleid);
void	DCconfig_unlock_lld_rule(zbx_uint64_t lld_ruleid);
void	DCconfig_get_triggers_by_itemids(zbx_hashset_t *trigger_info, zbx_vector_ptr_t *trigger_order,
		const zbx_uint64_t *itemids, const zbx_timespec_t *timespecs, int itemids_num);
int	DCconfig_get_time_based_triggers(DC_TRIGGER *trigger_info, zbx_vector_ptr_t *trigger_order, int max_triggers,
		zbx_uint64_t start_triggerid, int process_num);
void	DCfree_triggers(zbx_vector_ptr_t *triggers);
void	DCconfig_update_interface_snmp_stats(zbx_uint64_t interfaceid, int max_snmp_succeed, int min_snmp_fail);
int	DCconfig_get_suggested_snmp_vars(zbx_uint64_t interfaceid, int *bulk);
int	DCconfig_get_interface_by_type(DC_INTERFACE *interface, zbx_uint64_t hostid, unsigned char type);
int	DCconfig_get_interface(DC_INTERFACE *interface, zbx_uint64_t hostid, zbx_uint64_t itemid);
int	DCconfig_get_poller_nextcheck(unsigned char poller_type);
int	DCconfig_get_poller_items(unsigned char poller_type, DC_ITEM *items);
int	DCconfig_get_ipmi_poller_items(int now, DC_ITEM *items, int items_num, int *nextcheck);
int	DCconfig_get_snmp_interfaceids_by_addr(const char *addr, zbx_uint64_t **interfaceids);
size_t	DCconfig_get_snmp_items_by_interfaceid(zbx_uint64_t interfaceid, DC_ITEM **items);

#define ZBX_HK_OPTION_DISABLED		0
#define ZBX_HK_OPTION_ENABLED		1

#define ZBX_HK_HISTORY_MIN	SEC_PER_HOUR
#define ZBX_HK_TRENDS_MIN	SEC_PER_DAY

void	DCrequeue_items(const zbx_uint64_t *itemids, const unsigned char *states, const int *lastclocks,
		const int *errcodes, size_t num);
void	DCpoller_requeue_items(const zbx_uint64_t *itemids, const unsigned char *states, const int *lastclocks,
		const int *errcodes, size_t num, unsigned char poller_type, int *nextcheck);
void	zbx_dc_requeue_unreachable_items(zbx_uint64_t *itemids, size_t itemids_num);
int	DCconfig_activate_host(DC_ITEM *item);
int	DCconfig_deactivate_host(DC_ITEM *item, int now);

int	DCconfig_check_trigger_dependencies(zbx_uint64_t triggerid);

void	DCconfig_triggers_apply_changes(zbx_vector_ptr_t *trigger_diff);
void	DCconfig_items_apply_changes(const zbx_vector_ptr_t *item_diff);

void	DCconfig_set_maintenance(const zbx_uint64_t *hostids, int hostids_num, int maintenance_status,
		int maintenance_type, int maintenance_from);

void	DCconfig_update_inventory_values(const zbx_vector_ptr_t *inventory_values);
int	DCget_host_inventory_value_by_itemid(zbx_uint64_t itemid, char **replace_to, int value_idx);

#define ZBX_CONFSTATS_BUFFER_TOTAL	1
#define ZBX_CONFSTATS_BUFFER_USED	2
#define ZBX_CONFSTATS_BUFFER_FREE	3
#define ZBX_CONFSTATS_BUFFER_PFREE	4
void	*DCconfig_get_stats(int request);

int	DCconfig_get_last_sync_time(void);
int	DCconfig_get_proxypoller_hosts(DC_PROXY *proxies, int max_hosts);
int	DCconfig_get_proxypoller_nextcheck(void);

#define ZBX_PROXY_CONFIG_NEXTCHECK	0x01
#define ZBX_PROXY_DATA_NEXTCHECK	0x02
#define ZBX_PROXY_TASKS_NEXTCHECK	0x04
void	DCrequeue_proxy(zbx_uint64_t hostid, unsigned char update_nextcheck, int proxy_conn_err);
void	DCconfig_set_proxy_timediff(zbx_uint64_t hostid, const zbx_timespec_t *timediff);
int	DCcheck_proxy_permissions(const char *host, const zbx_socket_t *sock, zbx_uint64_t *hostid, char **error);

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
size_t	DCget_psk_by_identity(const unsigned char *psk_identity, unsigned char *psk_buf, size_t psk_buf_len);
#endif

void	DCget_user_macro(const zbx_uint64_t *hostids, int host_num, const char *macro, char **replace_to);
char	*DCexpression_expand_user_macros(const char *expression, char **error);

int	DChost_activate(zbx_uint64_t hostid, unsigned char agent_type, const zbx_timespec_t *ts,
		zbx_agent_availability_t *in, zbx_agent_availability_t *out);

int	DChost_deactivate(zbx_uint64_t hostid, unsigned char agent, const zbx_timespec_t *ts,
		zbx_agent_availability_t *in, zbx_agent_availability_t *out, const char *error);

#define ZBX_QUEUE_FROM_DEFAULT	6	/* default lower limit for delay (in seconds) */
#define ZBX_QUEUE_TO_INFINITY	-1	/* no upper limit for delay */
void	DCfree_item_queue(zbx_vector_ptr_t *queue);
int	DCget_item_queue(zbx_vector_ptr_t *queue, int from, int to);

zbx_uint64_t	DCget_item_count(zbx_uint64_t hostid);
zbx_uint64_t	DCget_item_unsupported_count(zbx_uint64_t hostid);
zbx_uint64_t	DCget_trigger_count(void);
double		DCget_required_performance(void);
zbx_uint64_t	DCget_host_count(void);

void	DCget_status(zbx_vector_ptr_t *hosts_monitored, zbx_vector_ptr_t *hosts_not_monitored,
		zbx_vector_ptr_t *items_active_normal, zbx_vector_ptr_t *items_active_notsupported,
		zbx_vector_ptr_t *items_disabled, zbx_uint64_t *triggers_enabled_ok,
		zbx_uint64_t *triggers_enabled_problem, zbx_uint64_t *triggers_disabled,
		zbx_vector_ptr_t *required_performance);

void	DCget_expressions_by_names(zbx_vector_ptr_t *expressions, const char * const *names, int names_num);
void	DCget_expressions_by_name(zbx_vector_ptr_t *expressions, const char *name);

int	DCget_data_expected_from(zbx_uint64_t itemid, int *seconds);

void	DCget_hostids_by_functionids(zbx_vector_uint64_t *functionids, zbx_vector_uint64_t *hostids);

/* global configuration support */
#define ZBX_DISCOVERY_GROUPID_UNDEFINED	0
void	zbx_config_get(zbx_config_t *cfg, zbx_uint64_t flags);
void	zbx_config_clean(zbx_config_t *cfg);

int	DCset_hosts_availability(zbx_vector_ptr_t *availabilities);

int	DCreset_hosts_availability(zbx_vector_ptr_t *hosts);
void	DCupdate_hosts_availability(void);

void	zbx_dc_get_actions_eval(zbx_vector_ptr_t *actions, zbx_hashset_t *uniq_conditions, unsigned char opflags);
void	zbx_action_eval_free(zbx_action_eval_t *action);
void	zbx_conditions_eval_clean(zbx_hashset_t *uniq_conditions);

int	DCget_hosts_availability(zbx_vector_ptr_t *hosts, int *ts);

void	zbx_host_availability_init(zbx_host_availability_t *availability, zbx_uint64_t hostid);
void	zbx_host_availability_clean(zbx_host_availability_t *availability);
void	zbx_host_availability_free(zbx_host_availability_t *availability);
int	zbx_host_availability_is_set(const zbx_host_availability_t *ha);

void	zbx_set_availability_diff_ts(int ts);

void	zbx_dc_correlation_rules_init(zbx_correlation_rules_t *rules);
void	zbx_dc_correlation_rules_clean(zbx_correlation_rules_t *rules);
void	zbx_dc_correlation_rules_free(zbx_correlation_rules_t *rules);
void	zbx_dc_correlation_rules_get(zbx_correlation_rules_t *rules);

void	zbx_dc_get_nested_hostgroupids(zbx_uint64_t *groupids, int groupids_num, zbx_vector_uint64_t *nested_groupids);
void	zbx_dc_get_nested_hostgroupids_by_names(char **names, int names_num,
		zbx_vector_uint64_t *nested_groupids);

#define ZBX_HC_ITEM_STATUS_NORMAL	0
#define ZBX_HC_ITEM_STATUS_BUSY		1

typedef struct zbx_hc_data	zbx_hc_data_t;

typedef struct
{
	zbx_uint64_t	itemid;
	unsigned char	status;

	zbx_hc_data_t	*tail;
	zbx_hc_data_t	*head;
}
zbx_hc_item_t;

void	zbx_free_tag(zbx_tag_t *tag);

int	zbx_dc_get_active_proxy_by_name(const char *name, DC_PROXY *proxy, char **error);
void	zbx_dc_update_proxy_version(zbx_uint64_t hostid, int version);

typedef struct
{
	zbx_timespec_t	ts;
	char		*value;	/* NULL in case of meta record (see "meta" field below) */
	char		*source;
	zbx_uint64_t	lastlogsize;
	int		mtime;
	int		timestamp;
	int		severity;
	int		logeventid;
	unsigned char	state;
	unsigned char	meta;	/* non-zero of contains meta information (lastlogsize and mtime) */
}
zbx_agent_value_t;

void	zbx_dc_items_update_nextcheck(DC_ITEM *items, zbx_agent_value_t *values, int *errcodes, size_t values_num);
void	zbx_dc_update_proxy_lastaccess(zbx_uint64_t hostid, int lastaccess, zbx_vector_uint64_pair_t *proxy_diff);
int	zbx_dc_get_host_interfaces(zbx_uint64_t hostid, DC_INTERFACE2 **interfaces, int *n);

typedef struct
{
	zbx_uint64_t		triggerid;
	unsigned char		status;
	zbx_vector_uint64_t	masterids;
}
zbx_trigger_dep_t;

void	zbx_dc_get_trigger_dependencies(const zbx_vector_uint64_t *triggerids, zbx_vector_ptr_t *deps);

#endif
