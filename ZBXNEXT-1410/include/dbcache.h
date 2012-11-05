/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_DBCACHE_H
#define ZABBIX_DBCACHE_H

#include "db.h"
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

extern char	*CONFIG_FILE;
extern int	CONFIG_TIMEOUT;
extern int	CONFIG_CONF_CACHE_SIZE;
extern int	CONFIG_HISTORY_CACHE_SIZE;
extern int	CONFIG_TRENDS_CACHE_SIZE;
extern int	CONFIG_TEXT_CACHE_SIZE;
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
}
DC_HOST;

typedef struct
{
	DC_HOST		host;
	DC_INTERFACE	interface;
	zbx_uint64_t	itemid;
	unsigned char 	type;
	unsigned char	data_type;
	unsigned char	value_type;
	char		key_orig[ITEM_KEY_LEN * 4 + 1], *key;
	int		delay;
	int		nextcheck;
	unsigned char	status;
	char		trapper_hosts[ITEM_TRAPPER_HOSTS_LEN_MAX];
	char		logtimefmt[ITEM_LOGTIMEFMT_LEN_MAX];
	char		snmp_community_orig[ITEM_SNMP_COMMUNITY_LEN_MAX], *snmp_community;
	char		snmp_oid_orig[ITEM_SNMP_OID_LEN_MAX], *snmp_oid;
	char		snmpv3_securityname_orig[ITEM_SNMPV3_SECURITYNAME_LEN_MAX], *snmpv3_securityname;
	unsigned char	snmpv3_securitylevel;
	char		snmpv3_authpassphrase_orig[ITEM_SNMPV3_AUTHPASSPHRASE_LEN_MAX], *snmpv3_authpassphrase;
	char		snmpv3_privpassphrase_orig[ITEM_SNMPV3_PRIVPASSPHRASE_LEN_MAX], *snmpv3_privpassphrase;
	char		ipmi_sensor[ITEM_IPMI_SENSOR_LEN_MAX];
	char		*params;
	char		delay_flex[ITEM_DELAY_FLEX_LEN_MAX];
	unsigned char	authtype;
	char		username_orig[ITEM_USERNAME_LEN_MAX], *username;
	char		publickey_orig[ITEM_PUBLICKEY_LEN_MAX], *publickey;
	char		privatekey_orig[ITEM_PRIVATEKEY_LEN_MAX], *privatekey;
	char		password_orig[ITEM_PASSWORD_LEN_MAX], *password;
	unsigned char	flags;
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
	zbx_uint64_t	triggerid;
	char		*expression;
	char		error[TRIGGER_ERROR_LEN_MAX];
	char		*new_error;
	zbx_timespec_t	timespec;
	unsigned char	type;
	unsigned char	value;
	unsigned char	value_flags;
	unsigned char	new_value;
	unsigned char	add_event;
	unsigned char	value_changed;
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
	char	*host;
	char	*key;
}
zbx_host_key_t;

void	dc_add_history(zbx_uint64_t itemid, unsigned char value_type, unsigned char flags, AGENT_RESULT *value,
		zbx_timespec_t *ts, unsigned char status, const char *error, int timestamp, const char *source,
		int severity, int logeventid, zbx_uint64_t lastlogsize, int mtime);
void	dc_flush_history();
int	DCsync_history(int sync_type);
void	init_database_cache();
void	free_database_cache();

void	DCinit_nextchecks();
void	DCadd_nextcheck(zbx_uint64_t itemid, time_t now, const char *error_msg);
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

int	DCget_item_lastclock(zbx_uint64_t itemid);

void	DCsync_configuration();
void	init_configuration_cache();
void	free_configuration_cache();
void	DCload_config();

void	DCconfig_clean_items(DC_ITEM *items, int *errcodes, size_t num);
int	DCget_host_by_hostid(DC_HOST *host, zbx_uint64_t hostid);
void	DCconfig_get_items_by_keys(DC_ITEM *items, zbx_uint64_t proxy_hostid,
		zbx_host_key_t *keys, int *errcodes, size_t num);
void	DCconfig_get_items_by_itemids(DC_ITEM *items, zbx_uint64_t *itemids, int *errcodes, size_t num);
void	DCconfig_get_functions_by_functionids(DC_FUNCTION *functions,
		zbx_uint64_t *functionids, int *errcodes, size_t num);
void	DCconfig_clean_functions(DC_FUNCTION *functions, int *errcodes, size_t num);
void	DCconfig_get_triggers_by_itemids(zbx_hashset_t *trigger_info, zbx_vector_ptr_t *trigger_order,
		const zbx_uint64_t *itemids, const zbx_timespec_t *timespecs, char **errors, int item_num);
int	DCconfig_get_trigger_for_event(DB_TRIGGER *trigger, zbx_uint64_t triggerid);
void	DCconfig_get_time_based_triggers(DC_TRIGGER **trigger_info, zbx_vector_ptr_t *trigger_order, int process_num);
int	DCconfig_get_interface_by_type(DC_INTERFACE *interface, zbx_uint64_t hostid, unsigned char type);
int	DCconfig_get_poller_nextcheck(unsigned char poller_type);
int	DCconfig_get_poller_items(unsigned char poller_type, DC_ITEM *items, int max_items);
int	DCconfig_get_snmp_interfaceids_by_addr(const char *addr, zbx_uint64_t **interfaceids);
size_t	DCconfig_get_snmp_items_by_interfaceid(zbx_uint64_t interfaceid, DC_ITEM **items);

#define	CONFIG_ALERT_HISTORY		1
#define	CONFIG_EVENT_HISTORY		2
#define	CONFIG_REFRESH_UNSUPPORTED	3
#define	CONFIG_DISCOVERY_GROUPID	4
#define	CONFIG_SNMPTRAP_LOGGING		5
void	*DCconfig_get_config_data(void *data, int type);
int	DCget_trigger_severity_name(unsigned char priority, char **replace_to);

void	DCrequeue_reachable_item(zbx_uint64_t itemid, unsigned char status, int now);
void	DCrequeue_unreachable_item(zbx_uint64_t itemid);
int	DCconfig_activate_host(DC_ITEM *item);
int	DCconfig_deactivate_host(DC_ITEM *item, int now);

int	DCconfig_check_trigger_dependencies(zbx_uint64_t triggerid);

void	DCconfig_set_trigger_value(zbx_uint64_t triggerid, unsigned char value,
		unsigned char value_flags, const char *error);
void	DCconfig_set_maintenance(zbx_uint64_t hostid, int maintenance_status,
		int maintenance_type, int maintenance_from);

#define ZBX_CONFSTATS_BUFFER_TOTAL	1
#define ZBX_CONFSTATS_BUFFER_USED	2
#define ZBX_CONFSTATS_BUFFER_FREE	3
#define ZBX_CONFSTATS_BUFFER_PFREE	4
void	*DCconfig_get_stats(int request);

int	DCconfig_get_proxypoller_hosts(DC_PROXY *proxies, int max_hosts);
int	DCconfig_get_proxypoller_nextcheck();
void	DCrequeue_proxy(zbx_uint64_t hostid, unsigned char update_nextcheck);

void	DCget_user_macro(zbx_uint64_t *hostids, int host_num, const char *macro, char **replace_to);

#endif
