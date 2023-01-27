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

#ifndef ZABBIX_DBWRAP_H
#define ZABBIX_DBWRAP_H

#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"

#define ZBX_PROXYMODE_ACTIVE	0
#define ZBX_PROXYMODE_PASSIVE	1

#define ZBX_PROXY_UPLOAD_UNDEFINED	0
#define ZBX_PROXY_UPLOAD_DISABLED	1
#define ZBX_PROXY_UPLOAD_ENABLED	2

typedef enum
{
	ZBX_TEMPLATE_LINK_MANUAL = 0,
	ZBX_TEMPLATE_LINK_LLD = 1
}
zbx_host_template_link_type;

typedef int (*zbx_trigger_func_t)(zbx_variant_t *, const DC_EVALUATE_ITEM *, const char *, const char *,
		const zbx_timespec_t *, char **);

int	zbx_check_access_passive_proxy(zbx_socket_t *sock, int send_response, const char *req,
		const zbx_config_tls_t *config_tls, int config_timeout);

int	zbx_get_active_proxy_from_request(const struct zbx_json_parse *jp, DC_PROXY *proxy, char **error);
int	zbx_proxy_check_permissions(const DC_PROXY *proxy, const zbx_socket_t *sock, char **error);

int	zbx_get_interface_availability_data(struct zbx_json *json, int *ts);

int	zbx_proxy_get_hist_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more);
int	zbx_proxy_get_dhis_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more);
int	zbx_proxy_get_areg_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more);
void	zbx_proxy_set_hist_lastid(const zbx_uint64_t lastid);
void	zbx_proxy_set_dhis_lastid(const zbx_uint64_t lastid);
void	zbx_proxy_set_areg_lastid(const zbx_uint64_t lastid);
int	zbx_proxy_get_host_active_availability(struct zbx_json *j);

int	zbx_proxy_get_history_count(void);
int	zbx_proxy_get_delay(zbx_uint64_t lastid);

int	zbx_process_history_data(zbx_history_recv_item_t *items, zbx_agent_value_t *values, int *errcodes,
		size_t values_num, zbx_proxy_suppress_t *nodata_win);

void	zbx_update_proxy_data(DC_PROXY *proxy, char *version_str, int version_int, int lastaccess, int compress,
		zbx_uint64_t flags_add);

int	zbx_process_agent_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info);
int	zbx_process_sender_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info);
int	zbx_process_proxy_data(const DC_PROXY *proxy, struct zbx_json_parse *jp, zbx_timespec_t *ts,
		unsigned char proxy_status, int *more, char **error);
int	zbx_check_protocol_version(DC_PROXY *proxy, int version);

int	zbx_db_copy_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids,
		zbx_host_template_link_type link_type, char **error);
int	zbx_db_delete_template_elements(zbx_uint64_t hostid, const char *hostname, zbx_vector_uint64_t *del_templateids,
		char **error);

void	zbx_db_delete_items(zbx_vector_uint64_t *itemids);
void	zbx_db_delete_graphs(zbx_vector_uint64_t *graphids);
void	zbx_db_delete_triggers(zbx_vector_uint64_t *triggerids);

void	zbx_db_delete_hosts(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames);
void	zbx_db_delete_hosts_with_prototypes(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames);

void	zbx_db_set_host_inventory(zbx_uint64_t hostid, int inventory_mode);
void	zbx_db_add_host_inventory(zbx_uint64_t hostid, int inventory_mode);

void	zbx_db_delete_groups(zbx_vector_uint64_t *groupids);

zbx_uint64_t	zbx_db_add_interface(zbx_uint64_t hostid, unsigned char type, unsigned char useip,
		const char *ip, const char *dns, unsigned short port, zbx_conn_flags_t flags);
void	zbx_db_add_interface_snmp(const zbx_uint64_t interfaceid, const unsigned char version,
		const unsigned char bulk, const char *community, const char *securityname,
		const unsigned char securitylevel, const char *authpassphrase, const char *privpassphrase,
		const unsigned char authprotocol, const unsigned char privprotocol, const char *contextname,
		const zbx_uint64_t hostid);

/* event support */
void		zbx_db_get_events_by_eventids(zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *events);
void		zbx_db_free_event(zbx_db_event *event);
void		zbx_db_get_eventid_r_eventid_pairs(zbx_vector_uint64_t *eventids, zbx_vector_uint64_pair_t *event_pairs,
		zbx_vector_uint64_t *r_eventids);
void		zbx_db_prepare_empty_event(zbx_uint64_t eventid, zbx_db_event **event);
void		zbx_db_get_event_data_core(zbx_db_event *event);
void		zbx_db_get_event_data_tags(zbx_db_event *event);
void		zbx_db_get_event_data_triggers(zbx_db_event *event);
void		zbx_db_select_symptom_eventids(zbx_vector_uint64_t *eventids, zbx_vector_uint64_t *symptom_eventids);
zbx_uint64_t	zbx_db_get_cause_eventid(zbx_uint64_t eventid);
zbx_uint64_t	zbx_get_objectid_by_eventid(zbx_uint64_t eventid);

void	zbx_db_trigger_get_all_functionids(const zbx_db_trigger *trigger, zbx_vector_uint64_t *functionids);
void	zbx_db_trigger_get_functionids(const zbx_db_trigger *trigger, zbx_vector_uint64_t *functionids);
int	zbx_db_trigger_get_constant(const zbx_db_trigger *trigger, int index, char **out);
int	zbx_db_trigger_get_all_hostids(const zbx_db_trigger *trigger, const zbx_vector_uint64_t **hostids);
int	zbx_db_trigger_get_itemid(const zbx_db_trigger *trigger, int index, zbx_uint64_t *itemid);
void	zbx_db_trigger_get_itemids(const zbx_db_trigger *trigger, zbx_vector_uint64_t *itemids);

void	zbx_db_trigger_get_expression(const zbx_db_trigger *trigger, char **expression);
void	zbx_db_trigger_get_recovery_expression(const zbx_db_trigger *trigger, char **expression);
void	zbx_db_trigger_clean(zbx_db_trigger *trigger);

void	zbx_db_trigger_explain_expression(const zbx_db_trigger *trigger, char **expression,
		zbx_trigger_func_t eval_func_cb, int recovery);
void	zbx_db_trigger_get_function_value(const zbx_db_trigger *trigger, int index, char **value,
		zbx_trigger_func_t eval_func_cb, int recovery);

#endif /* ZABBIX_DBWRAP_H */
