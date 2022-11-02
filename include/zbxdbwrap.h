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

#ifndef ZABBIX_DBWRAP_H
#define ZABBIX_DBWRAP_H

#include "zbxdbhigh.h"
#include "dbcache.h"

#define ZBX_PROXYMODE_ACTIVE	0
#define ZBX_PROXYMODE_PASSIVE	1

#define ZBX_PROXY_UPLOAD_UNDEFINED	0
#define ZBX_PROXY_UPLOAD_DISABLED	1
#define ZBX_PROXY_UPLOAD_ENABLED	2

#define ZBX_PROXY_ACTIVE_CHECK_AVAIL_TIMEOUT		30

typedef enum
{
	ZBX_TEMPLATE_LINK_MANUAL = 0,
	ZBX_TEMPLATE_LINK_LLD = 1
}
zbx_host_template_link_type;

int	check_access_passive_proxy(zbx_socket_t *sock, int send_response, const char *req,
		const zbx_config_tls_t *zbx_config_tls);

int	get_active_proxy_from_request(const struct zbx_json_parse *jp, DC_PROXY *proxy, char **error);
int	zbx_proxy_check_permissions(const DC_PROXY *proxy, const zbx_socket_t *sock, char **error);

int	get_interface_availability_data(struct zbx_json *json, int *ts);

int	proxy_get_hist_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more);
int	proxy_get_dhis_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more);
int	proxy_get_areg_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more);
void	proxy_set_hist_lastid(const zbx_uint64_t lastid);
void	proxy_set_dhis_lastid(const zbx_uint64_t lastid);
void	proxy_set_areg_lastid(const zbx_uint64_t lastid);
int	proxy_get_host_active_availability(struct zbx_json *j);

int	proxy_get_history_count(void);
int	proxy_get_delay(zbx_uint64_t lastid);

int	process_history_data(DC_ITEM *items, zbx_agent_value_t *values, int *errcodes, size_t values_num,
		zbx_proxy_suppress_t *nodata_win);

void	zbx_update_proxy_data(DC_PROXY *proxy, char *version_str, int version_int, int lastaccess, int compress,
		zbx_uint64_t flags_add);

int	process_agent_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info);
int	process_sender_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info);
int	process_proxy_data(const DC_PROXY *proxy, struct zbx_json_parse *jp, zbx_timespec_t *ts,
		unsigned char proxy_status, int *more, char **error);
int	zbx_check_protocol_version(DC_PROXY *proxy, int version);

int	DBcopy_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids,
		zbx_host_template_link_type link_type, char **error);
int	DBdelete_template_elements(zbx_uint64_t hostid, const char *hostname, zbx_vector_uint64_t *del_templateids,
		char **error);

void	DBdelete_items(zbx_vector_uint64_t *itemids);
void	DBdelete_graphs(zbx_vector_uint64_t *graphids);
void	DBdelete_triggers(zbx_vector_uint64_t *triggerids);

void	DBdelete_hosts(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames);
void	DBdelete_hosts_with_prototypes(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames);

void	DBset_host_inventory(zbx_uint64_t hostid, int inventory_mode);
void	DBadd_host_inventory(zbx_uint64_t hostid, int inventory_mode);

void	DBdelete_groups(zbx_vector_uint64_t *groupids);

zbx_uint64_t	DBadd_interface(zbx_uint64_t hostid, unsigned char type, unsigned char useip,
		const char *ip, const char *dns, unsigned short port, zbx_conn_flags_t flags);
void	DBadd_interface_snmp(const zbx_uint64_t interfaceid, const unsigned char version,
		const unsigned char bulk, const char *community, const char *securityname,
		const unsigned char securitylevel, const char *authpassphrase, const char *privpassphrase,
		const unsigned char authprotocol, const unsigned char privprotocol, const char *contextname,
		const zbx_uint64_t hostid);

/* event support */
void	zbx_db_get_events_by_eventids(zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *events);
void	zbx_db_free_event(ZBX_DB_EVENT *event);
void	zbx_db_get_eventid_r_eventid_pairs(zbx_vector_uint64_t *eventids, zbx_vector_uint64_pair_t *event_pairs,
		zbx_vector_uint64_t *r_eventids);

#endif /* ZABBIX_DBWRAP_H */
