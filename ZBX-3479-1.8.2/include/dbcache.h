/*
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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


#ifndef ZABBIX_DBCACHE_H
#define ZABBIX_DBCACHE_H

#include "db.h"

#define ZBX_DC_CACHE struct zbx_dc_cache_type
#define ZBX_DC_STATS struct zbx_dc_stats_type
#define ZBX_DC_HISTORY struct zbx_dc_history_type
#define ZBX_DC_TREND struct zbx_dc_trend_type
#define ZBX_DC_NEXTCHECK struct zbx_dc_nextcheck_type
#define ZBX_DC_ID struct zbx_dc_id_type
#define ZBX_DC_IDS struct zbx_dc_ids_type

#define ZBX_IDS_SIZE		7

#define ZBX_SYNC_PARTIAL	0
#define	ZBX_SYNC_FULL		1

#define DC_ITEM struct dc_item
#define DC_HOST struct dc_host

extern char	*CONFIG_FILE;
extern int	CONFIG_DBCONFIG_SIZE;
extern int	CONFIG_HISTORY_CACHE_SIZE;
extern int	CONFIG_TRENDS_CACHE_SIZE;
extern int	CONFIG_TEXT_CACHE_SIZE;
extern int	CONFIG_POLLER_FORKS;
extern int	CONFIG_IPMIPOLLER_FORKS;
extern int	CONFIG_UNREACHABLE_POLLER_FORKS;
extern int	CONFIG_PINGER_FORKS;
extern int	CONFIG_REFRESH_UNSUPPORTED;
extern int	CONFIG_UNAVAILABLE_DELAY;
extern int	CONFIG_UNREACHABLE_PERIOD;
extern int	CONFIG_UNREACHABLE_DELAY;

typedef union{
	double		value_float;
	zbx_uint64_t	value_uint64;
	char		*value_str;
} history_value_t;

ZBX_DC_ID
{
	char		table_name[64], field_name[64];
	zbx_uint64_t	lastid;
};

ZBX_DC_IDS
{
	ZBX_DC_ID	id[ZBX_IDS_SIZE];
};

ZBX_DC_HISTORY
{
	zbx_uint64_t	itemid;
	int		clock;
	unsigned char	value_type;
	history_value_t	value_orig;
	history_value_t	value;
	unsigned char	value_null;
	int		timestamp;
	char		*source;
	int		severity;
	int		logeventid;
	int		lastlogsize;
	int		mtime;
	unsigned char	keep_history;
	unsigned char	keep_trends;
	int		functions;
};

ZBX_DC_TREND
{
	zbx_uint64_t	itemid;
	int		clock;
	int		num;
	unsigned char	value_type;
	history_value_t	value_min;
	history_value_t	value_avg;
	history_value_t	value_max;
};

ZBX_DC_STATS
{
	zbx_uint64_t	history_counter;	/* Total number of saved values in th DB */
	zbx_uint64_t	history_float_counter;	/* Number of saved float values in th DB */
	zbx_uint64_t	history_uint_counter;	/* Number of saved uint values in the DB */
	zbx_uint64_t	history_str_counter;	/* Number of saved str values in the DB */
	zbx_uint64_t	history_log_counter;	/* Number of saved log values in the DB */
	zbx_uint64_t	history_text_counter;	/* Number of saved text values in the DB */
};

ZBX_DC_CACHE
{
	int		history_first;
	int		history_num;
	int		trends_num;
	ZBX_DC_STATS	stats;
	ZBX_DC_HISTORY	*history;	/* [ZBX_HISTORY_SIZE] */
	ZBX_DC_TREND	*trends;	/* [ZBX_TREND_SIZE] */
	char		*text;		/* [ZBX_TEXTBUFFER_SIZE] */
	char		*last_text;
};

ZBX_DC_NEXTCHECK
{
	zbx_uint64_t	itemid;
	time_t		now;/*, nextcheck;*/
	/* for not supported items */
	char		*error_msg;
};

DC_HOST
{
	zbx_uint64_t	hostid;
	zbx_uint64_t	proxy_hostid;
	char		host[HOST_HOST_LEN_MAX];
	unsigned char	useip;
	char		ip[HOST_IP_LEN_MAX];
	char		dns[HOST_DNS_LEN_MAX];
	unsigned short	port;
	unsigned char	status;
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
	char		ipmi_ip_orig[HOST_ADDR_LEN_MAX];
	char		*ipmi_ip;
	unsigned short	ipmi_port;
	signed char	ipmi_authtype;
	unsigned char	ipmi_privilege;
	char		ipmi_username[HOST_IPMI_USERNAME_LEN_MAX];
	char		ipmi_password[HOST_IPMI_PASSWORD_LEN_MAX];
};

DC_ITEM
{
	DC_HOST		host;
	zbx_uint64_t	itemid;
	unsigned char 	type;
	unsigned char	data_type;
	unsigned char	value_type;
	char		key_orig[ITEM_KEY_LEN_MAX], *key;
	int		delay;
	int		nextcheck;
	unsigned char	status;
	char		trapper_hosts[ITEM_TRAPPER_HOSTS_LEN_MAX];
	char		logtimefmt[ITEM_LOGTIMEFMT_LEN_MAX];
	char		snmp_community[ITEM_SNMP_COMMUNITY_LEN_MAX];
	char		snmp_oid[ITEM_SNMP_OID_LEN_MAX];
	unsigned short	snmp_port;
	char		snmpv3_securityname[ITEM_SNMPV3_SECURITYNAME_LEN_MAX];
	unsigned char	snmpv3_securitylevel;
	char		snmpv3_authpassphrase[ITEM_SNMPV3_AUTHPASSPHRASE_LEN_MAX];
	char		snmpv3_privpassphrase[ITEM_SNMPV3_PRIVPASSPHRASE_LEN_MAX];
	char		ipmi_sensor[ITEM_IPMI_SENSOR_LEN_MAX];
	char		params_orig[ITEM_PARAMS_LEN_MAX], *params;
	char		delay_flex[ITEM_DELAY_FLEX_LEN_MAX];
	unsigned char	authtype;
	char		username_orig[ITEM_USERNAME_LEN_MAX], *username;
	char		publickey_orig[ITEM_PUBLICKEY_LEN_MAX], *publickey;
	char		privatekey_orig[ITEM_PRIVATEKEY_LEN_MAX], *privatekey;
	char		password_orig[ITEM_PASSWORD_LEN_MAX], *password;
};

void	DCadd_history(zbx_uint64_t itemid, double value_orig, int clock);
void	DCadd_history_uint(zbx_uint64_t itemid, zbx_uint64_t value_orig, int clock);
void	DCadd_history_str(zbx_uint64_t itemid, char *value_orig, int clock);
void	DCadd_history_text(zbx_uint64_t itemid, char *value_orig, int clock);
void	DCadd_history_log(zbx_uint64_t itemid, char *value_orig, int clock, int timestamp, char *source, int severity,
		int logeventid, int lastlogsize, int mtime);
int	DCsync_history(int sync_type);
void	init_database_cache(zbx_process_t p);
void	free_database_cache(void);

void	DCinit_nextchecks();
void	DCadd_nextcheck(DC_ITEM *item, time_t now, const char *error_msg);
void	DCflush_nextchecks();

#define ZBX_STATS_HISTORY_COUNTER	0
#define ZBX_STATS_HISTORY_FLOAT_COUNTER	1
#define ZBX_STATS_HISTORY_UINT_COUNTER	2
#define ZBX_STATS_HISTORY_STR_COUNTER	3
#define ZBX_STATS_HISTORY_LOG_COUNTER	4
#define ZBX_STATS_HISTORY_TEXT_COUNTER	5
#define ZBX_STATS_HISTORY_TOTAL		6
#define ZBX_STATS_HISTORY_USED		7
#define ZBX_STATS_HISTORY_FREE		8
#define ZBX_STATS_HISTORY_PFREE		9
#define ZBX_STATS_TREND_TOTAL		10
#define ZBX_STATS_TREND_USED		11
#define ZBX_STATS_TREND_FREE		12
#define ZBX_STATS_TREND_PFREE		13
#define ZBX_STATS_TEXT_TOTAL		14
#define ZBX_STATS_TEXT_USED		15
#define ZBX_STATS_TEXT_FREE		16
#define ZBX_STATS_TEXT_PFREE		17
void	*DCget_stats(int request);

zbx_uint64_t	DCget_nextid(const char *table_name, const char *field_name, int num);

int	DCget_item_lastclock(zbx_uint64_t itemid);

void	DCsync_configuration();
void	init_configuration_cache();
void	free_configuration_cache();

int	DCget_host_by_hostid(DC_HOST *host, zbx_uint64_t hostid);
int	DCconfig_get_item_by_key(DC_ITEM *item, zbx_uint64_t proxy_hostid, const char *hostname, const char *key);
int	DCconfig_get_item_by_itemid(DC_ITEM *item, zbx_uint64_t itemid);
int	DCconfig_get_poller_items(unsigned char poller_type, unsigned char poller_num, int now,
		DC_ITEM *items, int max_items);
int	DCconfig_get_poller_nextcheck(unsigned char poller_type, unsigned char poller_num, int now);
int	DCconfig_get_items(zbx_uint64_t hostid, const char *key, DC_ITEM **items);

void	DCconfig_update_item(zbx_uint64_t itemid, unsigned char status, int now);
int	DCconfig_activate_host(DC_ITEM *item);
int	DCconfig_deactivate_host(DC_ITEM *item, int now);

void	DCreset_item_nextcheck(zbx_uint64_t itemid);

void	DCconfig_set_maintenance(zbx_uint64_t hostid, int maintenance_status,
		int maintenance_type, int maintenance_from);

#define ZBX_CONFSTATS_BUFFER_TOTAL	1
#define ZBX_CONFSTATS_BUFFER_USED	2
#define ZBX_CONFSTATS_BUFFER_FREE	3
#define ZBX_CONFSTATS_BUFFER_PFREE	4
void	*DCconfig_get_stats(int request);

#endif
