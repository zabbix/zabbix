/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "sysinfo.h"
#include "zbxalgo.h"

#define ZBX_SYNC_PARTIAL	0
#define	ZBX_SYNC_FULL		1

#define ZBX_SYNC_MAX	1000

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

extern char	*CONFIG_FILE;
extern int	CONFIG_TIMEOUT;

extern zbx_uint64_t	CONFIG_CONF_CACHE_SIZE;
extern zbx_uint64_t	CONFIG_HISTORY_CACHE_SIZE;
extern zbx_uint64_t	CONFIG_TRENDS_CACHE_SIZE;
extern zbx_uint64_t	CONFIG_TEXT_CACHE_SIZE;

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
}
DC_HOST;

typedef struct
{
	DC_HOST		host;
	DC_INTERFACE	interface;
	zbx_uint64_t	itemid;
	zbx_uint64_t	lastlogsize;
	zbx_uint64_t	valuemapid;
	unsigned char 	type;
	unsigned char	data_type;
	unsigned char	value_type;
	unsigned char	delta;
	unsigned char	multiplier;
	unsigned char	state;
	unsigned char	db_state;
	unsigned char	snmpv3_securitylevel;
	unsigned char	authtype;
	unsigned char	flags;
	unsigned char	snmpv3_authprotocol;
	unsigned char	snmpv3_privprotocol;
	unsigned char	inventory_link;
	unsigned char	status;
	char		key_orig[ITEM_KEY_LEN * 4 + 1], *key;
	char		*formula;
	char		*units;
	int		delay;
	int		nextcheck;
	int		lastclock;
	int		mtime;
	int		history;
	int		trends;
	char		trapper_hosts[ITEM_TRAPPER_HOSTS_LEN_MAX];
	char		logtimefmt[ITEM_LOGTIMEFMT_LEN_MAX];
	char		snmp_community_orig[ITEM_SNMP_COMMUNITY_LEN_MAX], *snmp_community;
	char		snmp_oid_orig[ITEM_SNMP_OID_LEN_MAX], *snmp_oid;
	char		snmpv3_securityname_orig[ITEM_SNMPV3_SECURITYNAME_LEN_MAX], *snmpv3_securityname;
	char		snmpv3_authpassphrase_orig[ITEM_SNMPV3_AUTHPASSPHRASE_LEN_MAX], *snmpv3_authpassphrase;
	char		snmpv3_privpassphrase_orig[ITEM_SNMPV3_PRIVPASSPHRASE_LEN_MAX], *snmpv3_privpassphrase;
	char		ipmi_sensor[ITEM_IPMI_SENSOR_LEN_MAX];
	char		*params;
	char		delay_flex[ITEM_DELAY_FLEX_LEN_MAX];
	char		username_orig[ITEM_USERNAME_LEN_MAX], *username;
	char		publickey_orig[ITEM_PUBLICKEY_LEN_MAX], *publickey;
	char		privatekey_orig[ITEM_PRIVATEKEY_LEN_MAX], *privatekey;
	char		password_orig[ITEM_PASSWORD_LEN_MAX], *password;
	char		snmpv3_contextname_orig[ITEM_SNMPV3_CONTEXTNAME_LEN_MAX], *snmpv3_contextname;
	char		*db_error;
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

typedef struct _DC_TRIGGER
{
	zbx_uint64_t	triggerid;
	char		*description;
	char		*expression_orig;
	/* temporary value, allocated during processing and freed right after */
	char		*expression;

	char		*error;
	char		*new_error;
	zbx_timespec_t	timespec;
	int		lastchange;
	unsigned char	topoindex;
	unsigned char	priority;
	unsigned char	type;
	unsigned char	value;
	unsigned char	state;
	unsigned char	new_value;
	unsigned char	status;
}
DC_TRIGGER;

typedef struct
{
	zbx_uint64_t	hostid;
	char            host[HOST_HOST_LEN_MAX];
	int		proxy_config_nextcheck;
	int		proxy_data_nextcheck;
	char		addr_orig[INTERFACE_ADDR_LEN_MAX];
	char		port_orig[INTERFACE_PORT_LEN_MAX];
	char		*addr;
	unsigned short	port;
}
DC_PROXY;

typedef struct
{
	const char	*host;
	const char	*key;
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

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_timespec_t		timestamp;
	history_value_t		value;
}
zbx_item_history_value_t;

/* item queue data */
typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	proxy_hostid;
	int		type;
	int		nextcheck;
}
zbx_queue_item_t;

typedef struct
{
	zbx_uint64_t	hostid;
	int		errors_from;
	int		disable_until;
	unsigned char	type;
	unsigned char	available;
}
zbx_host_availability_t;

int	is_item_processed_by_server(unsigned char type, const char *key);
int	in_maintenance_without_data_collection(unsigned char maintenance_status, unsigned char maintenance_type,
		unsigned char type);
void	dc_add_history(zbx_uint64_t itemid, unsigned char value_type, unsigned char flags, AGENT_RESULT *value,
		zbx_timespec_t *ts, unsigned char state, const char *error);
void	dc_flush_history();
int	DCsync_history(int sync_type);
void	init_database_cache();
void	free_database_cache();

void	DCadd_nextcheck(zbx_uint64_t itemid, const zbx_timespec_t *ts, const char *error_msg);
void	DCflush_nextchecks();

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
#define ZBX_STATS_TEXT_TOTAL		15
#define ZBX_STATS_TEXT_USED		16
#define ZBX_STATS_TEXT_FREE		17
#define ZBX_STATS_TEXT_PFREE		18
void	*DCget_stats(int request);

zbx_uint64_t	DCget_nextid(const char *table_name, int num);

void	DCsync_configuration(void);
void	init_configuration_cache();
void	free_configuration_cache();
void	DCload_config();

void	DCconfig_get_triggers_by_triggerids(DC_TRIGGER *triggers, const zbx_uint64_t *triggerids, int *errcode,
		size_t num);
void	DCconfig_clean_items(DC_ITEM *items, int *errcodes, size_t num);
int	DCget_host_by_hostid(DC_HOST *host, zbx_uint64_t hostid);
void	DCconfig_get_items_by_keys(DC_ITEM *items, zbx_host_key_t *keys, int *errcodes, size_t num);
void	DCconfig_get_items_by_itemids(DC_ITEM *items, const zbx_uint64_t *itemids, int *errcodes, size_t num);
void	DCconfig_set_item_db_state(zbx_uint64_t itemid, unsigned char state, const char *error);
void	DCconfig_get_functions_by_functionids(DC_FUNCTION *functions,
		zbx_uint64_t *functionids, int *errcodes, size_t num);
void	DCconfig_clean_functions(DC_FUNCTION *functions, int *errcodes, size_t num);
void	DCconfig_clean_triggers(DC_TRIGGER *triggers, int *errcodes, size_t num);
void	DCconfig_lock_triggers_by_itemids(zbx_uint64_t *itemids, int itemids_num, zbx_vector_uint64_t *triggerids);
void	DCconfig_unlock_triggers(const zbx_vector_uint64_t *triggerids);
void	DCconfig_get_triggers_by_itemids(zbx_hashset_t *trigger_info, zbx_vector_ptr_t *trigger_order,
		const zbx_uint64_t *itemids, const zbx_timespec_t *timespecs, char **errors, int itemids_num);
void	DCconfig_get_time_based_triggers(DC_TRIGGER **trigger_info, zbx_vector_ptr_t *trigger_order, int max_triggers,
		int process_num);
void	DCfree_triggers(zbx_vector_ptr_t *triggers);
void	DCconfig_update_interface_snmp_stats(zbx_uint64_t interfaceid, int max_snmp_succeed, int min_snmp_fail);
int	DCconfig_get_suggested_snmp_vars(zbx_uint64_t interfaceid, int *bulk);
int	DCconfig_get_interface_by_type(DC_INTERFACE *interface, zbx_uint64_t hostid, unsigned char type);
int	DCconfig_get_poller_nextcheck(unsigned char poller_type);
int	DCconfig_get_poller_items(unsigned char poller_type, DC_ITEM *items);
int	DCconfig_get_snmp_interfaceids_by_addr(const char *addr, zbx_uint64_t **interfaceids);
size_t	DCconfig_get_snmp_items_by_interfaceid(zbx_uint64_t interfaceid, DC_ITEM **items);

#define	CONFIG_REFRESH_UNSUPPORTED	1
#define	CONFIG_DISCOVERY_GROUPID	2
#define	CONFIG_SNMPTRAP_LOGGING		3

#define ZBX_HK_OPTION_DISABLED		0
#define ZBX_HK_OPTION_ENABLED		1

void	*DCconfig_get_config_data(void *data, int type);
void	DCconfig_get_config_hk(zbx_config_hk_t *data);
int	DCget_trigger_severity_name(unsigned char priority, char **replace_to);

void	DCrequeue_items(zbx_uint64_t *itemids, unsigned char *states, int *lastclocks, zbx_uint64_t *lastlogsizes,
		int *mtimes, int *errcodes, size_t num);
int	DCconfig_activate_host(DC_ITEM *item);
int	DCconfig_deactivate_host(DC_ITEM *item, int now);

int	DCconfig_check_trigger_dependencies(zbx_uint64_t triggerid);

void	DCconfig_set_trigger_value(zbx_uint64_t triggerid, unsigned char value,
		unsigned char state, const char *error, int *lastchange);
void	DCconfig_set_maintenance(const zbx_uint64_t *hostids, int hostids_num, int maintenance_status,
		int maintenance_type, int maintenance_from);

#define ZBX_CONFSTATS_BUFFER_TOTAL	1
#define ZBX_CONFSTATS_BUFFER_USED	2
#define ZBX_CONFSTATS_BUFFER_FREE	3
#define ZBX_CONFSTATS_BUFFER_PFREE	4
void	*DCconfig_get_stats(int request);

int	DCconfig_get_proxypoller_hosts(DC_PROXY *proxies, int max_hosts);
int	DCconfig_get_proxypoller_nextcheck();
void	DCrequeue_proxy(zbx_uint64_t hostid, unsigned char update_nextcheck);
void	DCconfig_set_proxy_timediff(zbx_uint64_t hostid, const zbx_timespec_t *timediff);

void	DCget_user_macro(zbx_uint64_t *hostids, int host_num, const char *macro, char **replace_to);

int	DChost_activate(zbx_host_availability_t *in, zbx_host_availability_t *out);

int	DChost_deactivate(const zbx_timespec_t *ts, zbx_host_availability_t *in, zbx_host_availability_t *out);

void	DChost_update_availability(const zbx_host_availability_t *availability, int availability_num);

void	DCget_delta_items(zbx_hashset_t *items, const zbx_vector_uint64_t *ids);
void	DCset_delta_items(zbx_hashset_t *items);

void	DCfree_item_queue(zbx_vector_ptr_t *queue);
int	DCget_item_queue(zbx_vector_ptr_t *queue, int from, int to);

int	DCget_item_count();
int	DCget_item_unsupported_count();
int	DCget_trigger_count();
double	DCget_required_performance();
int	DCget_host_count();

void	DCget_expressions_by_names(zbx_vector_ptr_t *expressions, const char * const *names, int names_num);
void	DCget_expressions_by_name(zbx_vector_ptr_t *expressions, const char *name);

int	DCget_data_expected_from(zbx_uint64_t itemid, int *seconds);

/* a set of identifiers assigned to another identifier */
typedef struct
{
	zbx_uint64_t		id;
	zbx_vector_uint64_t	ids;
}
zbx_idset_t;

/* local user macro cache support */
void	zbx_umc_init(zbx_hashset_t *cache);
void	zbx_umc_destroy(zbx_hashset_t *cache);
void	zbx_umc_add_expression(zbx_hashset_t *cache, zbx_uint64_t objectid, const char *expression);
void	zbx_umc_add_hostids(zbx_hashset_t *cache, zbx_uint64_t objectid, const zbx_uint64_t *hostids, int hostids_num);
void	zbx_umc_resolve(zbx_hashset_t *cache);
const char	*zbx_umc_get_macro_value(zbx_hashset_t *cache, zbx_uint64_t objectid, const char *macro);

void	DCget_bulk_hostids_by_functionids(zbx_vector_ptr_t *functionids, zbx_vector_ptr_t *hostids);
void	DCget_hostids_by_functionids(zbx_vector_uint64_t *functionids, zbx_vector_uint64_t *hostids);
void	zbx_idset_free(zbx_idset_t *idset);

#endif
