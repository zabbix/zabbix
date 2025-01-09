/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#ifndef ZABBIX_DBWRAP_H
#define ZABBIX_DBWRAP_H

#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxdiscovery.h"
#include "zbxautoreg.h"

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

typedef int (*zbx_trigger_func_t)(zbx_variant_t *, const zbx_dc_evaluate_item_t *, const char *, const char *,
		const zbx_timespec_t *, char **);
typedef void (*zbx_lld_process_agent_result_func_t)(zbx_uint64_t itemid, zbx_uint64_t hostid, AGENT_RESULT *result,
		zbx_timespec_t *ts, char *error);
typedef void (*zbx_preprocess_item_value_func_t)(zbx_uint64_t itemid, zbx_uint64_t hostid,
		unsigned char item_value_type, unsigned char item_flags, AGENT_RESULT *result, zbx_timespec_t *ts,
		unsigned char state, char *error);
typedef void (*zbx_preprocessor_flush_func_t)(void);

void	zbx_init_library_dbwrap(zbx_lld_process_agent_result_func_t lld_process_agent_result_func,
		zbx_preprocess_item_value_func_t preprocess_item_value_func,
		zbx_preprocessor_flush_func_t preprocessor_flush_func);

int	zbx_check_access_passive_proxy(zbx_socket_t *sock, int send_response, const char *req,
		const zbx_config_tls_t *config_tls, int config_timeout, const char *server);

int	zbx_get_active_proxy_from_request(const struct zbx_json_parse *jp, zbx_dc_proxy_t *proxy, char **error);
int	zbx_proxy_check_permissions(const zbx_dc_proxy_t *proxy, const zbx_socket_t *sock, char **error);

int	zbx_get_interface_availability_data(struct zbx_json *json, int *ts);

int	zbx_proxy_get_host_active_availability(struct zbx_json *j);

int	zbx_proxy_get_delay(zbx_uint64_t lastid);

int	zbx_process_history_data(zbx_history_recv_item_t *items, zbx_agent_value_t *values, int *errcodes,
		size_t values_num, zbx_proxy_suppress_t *nodata_win);

void	zbx_update_proxy_data(zbx_dc_proxy_t *proxy, char *version_str, int version_int, time_t lastaccess,
		zbx_uint64_t flags_add);

int	zbx_process_agent_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info);
int	zbx_process_sender_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info);
int	zbx_process_proxy_data(const zbx_dc_proxy_t *proxy, const struct zbx_json_parse *jp, const zbx_timespec_t *ts,
		unsigned char proxy_status, const zbx_events_funcs_t *events_cbs, int proxydata_frequency,
		zbx_discovery_update_host_func_t discovery_update_host_cb,
		zbx_discovery_update_service_func_t discovery_update_service_cb,
		zbx_discovery_update_service_down_func_t discovery_update_service_down_cb,
		zbx_discovery_find_host_func_t discovery_find_host_cb,
		zbx_discovery_update_drule_func_t discovery_update_drule_cb,
		zbx_autoreg_host_free_func_t autoreg_host_free_cb,
		zbx_autoreg_flush_hosts_func_t autoreg_flush_hosts_cb,
		zbx_autoreg_prepare_host_func_t autoreg_prepare_host_cb, int *more, char **error);
int	zbx_check_protocol_version(zbx_dc_proxy_t *proxy, int version);

int	zbx_db_copy_template_elements(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids,
		zbx_host_template_link_type link_type, int audit_context_mode, char **error);
int	zbx_db_delete_template_elements(zbx_uint64_t hostid, const char *hostname, zbx_vector_uint64_t *del_templateids,
		int audit_context_mode, char **error);

void	zbx_db_delete_items(zbx_vector_uint64_t *itemids, int audit_context_mode);
void	zbx_db_delete_graphs(zbx_vector_uint64_t *graphids, int audit_context_mode);
void	zbx_db_delete_triggers(zbx_vector_uint64_t *triggerids, int audit_context_mode);

void	zbx_db_delete_hosts(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames,
		int audit_context_mode);
void	zbx_db_delete_hosts_with_prototypes(const zbx_vector_uint64_t *hostids, const zbx_vector_str_t *hostnames,
		int audit_context_mode);

void	zbx_db_set_host_inventory(zbx_uint64_t hostid, int inventory_mode, int audit_context_mode);
void	zbx_db_add_host_inventory(zbx_uint64_t hostid, int inventory_mode, int audit_context_mode);

void	zbx_db_delete_groups(zbx_vector_uint64_t *groupids);

void	zbx_host_groups_add(zbx_uint64_t hostid, zbx_vector_uint64_t *groupids, int audit_context_mode);
void	zbx_host_groups_remove(zbx_uint64_t hostid, zbx_vector_uint64_t *groupids);

void	zbx_hgset_hash_calculate(zbx_vector_uint64_t *groupids, char *hash_str, size_t hash_len);

zbx_uint64_t	zbx_db_add_interface(zbx_uint64_t hostid, unsigned char type, unsigned char useip,
		const char *ip, const char *dns, unsigned short port, zbx_conn_flags_t flags, int audit_context_mode);
void	zbx_db_add_interface_snmp(const zbx_uint64_t interfaceid, const unsigned char version,
		const unsigned char bulk, const char *community, const char *securityname,
		const unsigned char securitylevel, const char *authpassphrase, const char *privpassphrase,
		const unsigned char authprotocol, const unsigned char privprotocol, const char *contextname,
		const zbx_uint64_t hostid, int audit_context_mode);

/* event support */
void		zbx_db_get_events_by_eventids(zbx_vector_uint64_t *eventids, zbx_vector_db_event_t *events);
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
void	zbx_db_event_add_maintenanceid(zbx_db_event *event, zbx_uint64_t maintenanceid);

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

int	zbx_db_check_user_perm2system(zbx_uint64_t userid);
char	*zbx_db_get_user_timezone(zbx_uint64_t userid);

#define ZBX_PROBLEM_SUPPRESSED_FALSE	0
#define ZBX_PROBLEM_SUPPRESSED_TRUE	1

const char	*zbx_permission_string(int perm);
int	zbx_get_user_info(zbx_uint64_t userid, zbx_uint64_t *roleid, char **user_timezone);
int	zbx_get_item_permission(zbx_uint64_t userid, zbx_uint64_t itemid, char **user_timezone);
int	zbx_get_host_permission(const zbx_user_t *user, zbx_uint64_t hostid);

/* data resolvers */

int	zbx_macro_event_trigger_expr_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to,
		char **data, char *error, size_t maxerrlen);

int	zbx_db_trigger_recovery_user_and_func_macro_eval_resolv(zbx_token_type_t token_type, char **value,
		char **error, va_list args);
int	zbx_db_trigger_supplement_eval_resolv(zbx_token_type_t token_type, char **value, char **error, va_list args);

#endif /* ZABBIX_DBWRAP_H */
