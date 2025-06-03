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

#ifndef ZABBIX_AUDIT_ITEM_H
#define ZABBIX_AUDIT_ITEM_H

#include "zbxaudit.h"
#include "zbxalgo.h"

int	zbx_audit_item_resource_is_only_item(int resource_type);
int	zbx_audit_item_resource_is_only_item_prototype(int resource_type);
int	zbx_audit_item_resource_is_only_item_and_item_prototype(int resource_type);
int	zbx_audit_item_resource_is_only_lld_rule_or_lld_rule_prototype(int resource_type);

int	zbx_audit_item_flag_to_resource_type(int flag);

const char	*lld_audit_item_prop(int flags, const char *field, char *buf, size_t len);

void	zbx_audit_item_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t itemid, const char *name,
		int flags);

#define PREPARE_AUDIT_ITEM_UPDATE_H(resource, type1)								\
void	zbx_audit_item_update_json_update_##resource(int audit_context_mode, zbx_uint64_t itemid, int flags,	\
		type1 resource##_old, type1 resource##_new);

PREPARE_AUDIT_ITEM_UPDATE_H(interfaceid, zbx_uint64_t)
PREPARE_AUDIT_ITEM_UPDATE_H(templateid, zbx_uint64_t)
PREPARE_AUDIT_ITEM_UPDATE_H(name, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(type, int)
PREPARE_AUDIT_ITEM_UPDATE_H(value_type, int)
PREPARE_AUDIT_ITEM_UPDATE_H(delay, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(history, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(trends, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(status, int)
PREPARE_AUDIT_ITEM_UPDATE_H(trapper_hosts, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(units, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(formula, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(logtimefmt, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(valuemapid, zbx_uint64_t)
PREPARE_AUDIT_ITEM_UPDATE_H(params, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(ipmi_sensor, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(snmp_oid, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(authtype, int)
PREPARE_AUDIT_ITEM_UPDATE_H(username, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(password, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(publickey, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(privatekey, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(flags, int)
PREPARE_AUDIT_ITEM_UPDATE_H(description, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(inventory_link, int)
PREPARE_AUDIT_ITEM_UPDATE_H(lifetime, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(lifetime_type, int)
PREPARE_AUDIT_ITEM_UPDATE_H(enabled_lifetime, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(enabled_lifetime_type, int)
PREPARE_AUDIT_ITEM_UPDATE_H(evaltype, int)
PREPARE_AUDIT_ITEM_UPDATE_H(jmx_endpoint, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(master_itemid, zbx_uint64_t)
PREPARE_AUDIT_ITEM_UPDATE_H(timeout, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(url, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(query_fields, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(posts, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(status_codes, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(follow_redirects, int)
PREPARE_AUDIT_ITEM_UPDATE_H(redirects, int)
PREPARE_AUDIT_ITEM_UPDATE_H(post_type, int)
PREPARE_AUDIT_ITEM_UPDATE_H(http_proxy, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(headers, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(retrieve_mode, int)
PREPARE_AUDIT_ITEM_UPDATE_H(request_method, int)
PREPARE_AUDIT_ITEM_UPDATE_H(output_format, int)
PREPARE_AUDIT_ITEM_UPDATE_H(ssl_cert_file, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(ssl_key_file, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(ssl_key_password, const char*)
PREPARE_AUDIT_ITEM_UPDATE_H(verify_peer, int)
PREPARE_AUDIT_ITEM_UPDATE_H(verify_host, int)
PREPARE_AUDIT_ITEM_UPDATE_H(allow_traps, int)
PREPARE_AUDIT_ITEM_UPDATE_H(discover, int)
PREPARE_AUDIT_ITEM_UPDATE_H(key_, const char*)

void	zbx_audit_item_delete(int audit_context_mode, zbx_vector_uint64_t *itemids);

void	zbx_audit_discovery_rule_update_json_add_filter_conditions(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t rule_conditionid, zbx_uint64_t op, const char *macro, const char *value);
void	zbx_audit_discovery_rule_update_json_update_filter_conditions_create_entry(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t item_conditionid);

void	zbx_audit_discovery_rule_update_json_update_filter_conditions(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t item_conditionid, const char *resource, const char *value_old,
		const char *value_new);

#define PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_H(resource, type1)							\
void	zbx_audit_discovery_rule_update_json_update_filter_conditions_##resource(int audit_context_mode,	\
		zbx_uint64_t itemid, zbx_uint64_t item_conditionid, type1 resource##_old, type1 resource##_new);
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_H(operator, int)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_H(macro, const char*)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_H(value, const char*)

void	zbx_audit_discovery_rule_update_json_delete_filter_conditions(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t item_conditionid);

void	zbx_audit_item_update_json_add_item_preproc(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t preprocid, int item_flags, int step, int type, const char *params, int error_handler,
		const char *error_handler_params);

void	zbx_audit_item_update_json_update_item_preproc_create_entry(int audit_context_mode, zbx_uint64_t itemid,
		int item_flags, zbx_uint64_t preprocid);

#define PREPARE_AUDIT_ITEM_UPDATE_PREPROC_H(resource, type1)							\
void	zbx_audit_item_update_json_update_item_preproc_##resource(int audit_context_mode, zbx_uint64_t itemid,	\
		int item_flags, zbx_uint64_t preprocid, type1 resource##_old, type1 resource##_new);

PREPARE_AUDIT_ITEM_UPDATE_PREPROC_H(type, int)
PREPARE_AUDIT_ITEM_UPDATE_PREPROC_H(params, const char*)
PREPARE_AUDIT_ITEM_UPDATE_PREPROC_H(error_handler, int)
PREPARE_AUDIT_ITEM_UPDATE_PREPROC_H(error_handler_params, const char*)

void	zbx_audit_item_delete_preproc(int audit_context_mode, zbx_uint64_t itemid, int item_flags,
		zbx_uint64_t preprocid);

void	zbx_audit_item_update_json_add_item_tag(int audit_context_mode, zbx_uint64_t itemid, zbx_uint64_t tagid,
		int item_flags, const char *tag, const char *value);

void	zbx_audit_item_update_json_update_item_tag_create_entry(int audit_context_mode, zbx_uint64_t itemid,
		int item_flags, zbx_uint64_t tagid);

#define PREPARE_AUDIT_ITEM_UPDATE_TAG_H(resource, type1)							\
void	zbx_audit_item_update_json_update_item_tag_##resource(int audit_context_mode, zbx_uint64_t itemid,	\
		int item_flags, zbx_uint64_t tagid, type1 resource##_old, type1 resource##_new);

PREPARE_AUDIT_ITEM_UPDATE_TAG_H(tag, const char*)
PREPARE_AUDIT_ITEM_UPDATE_TAG_H(value, const char*)

void	zbx_audit_item_delete_tag(int audit_context_mode, zbx_uint64_t itemid, int item_flags, zbx_uint64_t tagid);

void	zbx_audit_item_update_json_add_params(int audit_context_mode, zbx_uint64_t itemid, int item_flags,
		zbx_uint64_t item_parameter_id, const char *name, const char *value);

void	zbx_audit_item_update_json_update_params_create_entry(int audit_context_mode, zbx_uint64_t itemid,
		int item_flags, zbx_uint64_t item_parameter_id);

#define PREPARE_AUDIT_ITEM_PARAMS_UPDATE_H(resource)								\
void	zbx_audit_item_update_json_update_params_##resource(int audit_context_mode, zbx_uint64_t itemid,	\
		int item_flags, zbx_uint64_t item_parameter_id, const char *resource##_orig, const char *resource);

PREPARE_AUDIT_ITEM_PARAMS_UPDATE_H(name)
PREPARE_AUDIT_ITEM_PARAMS_UPDATE_H(value)

void	zbx_audit_item_delete_params(int audit_context_mode, zbx_uint64_t itemid, int item_flags,
		zbx_uint64_t item_parameter_id);

void   zbx_audit_discovery_rule_update_json_delete_lld_macro_path(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t lld_macro_pathid);
void	zbx_audit_discovery_rule_update_json_add_lld_macro_path(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t lld_macro_pathid, const char *lld_macro, const char *path);

void	zbx_audit_discovery_rule_update_json_lld_macro_path_create_update_entry(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t lld_macro_pathid);

void	zbx_audit_discovery_rule_update_json_update_lld_macro_path(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t lld_macro_pathid, const char *resource, const char *value_old,
		const char *value_new);
#define PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH_H(resource)						\
void	zbx_audit_discovery_rule_update_json_update_lld_macro_path_##resource(int audit_context_mode,		\
		zbx_uint64_t itemid, zbx_uint64_t lld_macro_pathid, const char *resource##_old,			\
		const char *resource##_new);
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH_H(lld_macro)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH_H(path)

void	zbx_audit_discovery_rule_update_json_add_lld_override(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, const char *name, int step, int stop);
void	zbx_audit_discovery_rule_update_json_update_lld_override_str(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, const char *resource, const char *value_old, const char *value_new);
void	zbx_audit_discovery_rule_update_json_delete_lld_override(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid);
void	zbx_audit_discovery_rule_update_json_add_lld_override_filter(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, int evaltype, const char *formula);

void	zbx_audit_discovery_rule_update_json_update_lld_override_filter_str(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, const char *resource, const char *value_old, const char  *value_new);
void	zbx_audit_discovery_rule_update_json_add_lld_override_condition(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t override_conditionid, int condition_operator, const char *macro,
		const char *value);

void	zbx_audit_discovery_rule_update_json_add_lld_override_operation(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t override_operationid, int operationobject,
		int condition_operator, const char *value);

#define PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD_H(resource, type)						\
void	zbx_audit_discovery_rule_update_json_add_lld_override_##resource(int audit_context_mode,		\
		zbx_uint64_t itemid, zbx_uint64_t overrideid, zbx_uint64_t resource##_id, type resource);

PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD_H(opstatus, int)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD_H(opdiscover, int)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD_H(opperiod, const char*)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD_H(optrends, const char*)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD_H(ophistory, const char*)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD_H(opseverity, int)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD_H(opinventory, int)

void    zbx_audit_discovery_rule_update_json_add_lld_override_optag(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t override_operationid, zbx_uint64_t lld_override_optagid,
		const char *tag, const char *value);
void	zbx_audit_discovery_rule_update_json_delete_lld_override_operation_optag(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t optagid);
void	zbx_audit_discovery_rule_update_json_update_lld_override_optag(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t optagid, const char *resource,
		const char *value_old, const char *value_new);

void	zbx_audit_discovery_rule_update_json_add_lld_override_optemplate(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t lld_override_optemplateid,
		zbx_uint64_t templateid);
void	zbx_audit_discovery_rule_update_json_update_lld_override_optemplate(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t optemplateid, const char *resource,
		const char *value_old, const char *value_new);

void	zbx_audit_item_prototype_update_json_add_lldruleid(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t lldrule_id);

void	zbx_audit_item_update_json_add_query_fields_json(int audit_context_mode, zbx_uint64_t itemid, int flags,
		const char *val);
void	zbx_audit_item_update_json_add_headers(int audit_context_mode, zbx_uint64_t itemid, int flags,
		const char *val);

zbx_audit_entry_t	*zbx_audit_item_get_entry(int audit_context_mode, zbx_uint64_t itemid);
zbx_audit_entry_t	*zbx_audit_item_get_or_create_entry(int audit_context_mode, int audit_action,
		zbx_uint64_t itemid, const char *name, int flags);

const char	*zbx_audit_lldrule_macro_path(zbx_uint64_t lld_macro_pathid, const char *field, char *key,
		size_t key_size);
const char	*zbx_audit_lldrule_filter_condition(zbx_uint64_t filterid, const char *field, char *key,
		size_t key_size);

const char	*zbx_audit_lldrule_override(zbx_uint64_t overrideid, const char *field, char *key, size_t key_size);
const char	*zbx_audit_lldrule_override_filter(zbx_uint64_t overrideid, const char *field, char *key,
		size_t key_size);
const char	*zbx_audit_lldrule_override_filter_condition(zbx_uint64_t overrideid, zbx_uint64_t filterid,
		const char *field, char *key, size_t key_size);
const char	*zbx_audit_lldrule_override_operation(zbx_uint64_t overrideid, zbx_uint64_t operationid,
		const char *field, char *key, size_t key_size);
const char	*zbx_audit_lldrule_override_operation_optag(zbx_uint64_t overrideid, zbx_uint64_t operationid,
		zbx_uint64_t optagid, const char *field, char *key, size_t key_size);
const char	*zbx_audit_lldrule_override_operation_optemplate(zbx_uint64_t overrideid, zbx_uint64_t operationid,
		zbx_uint64_t optemplateid, const char *field, char *key, size_t key_size);
const char	*zbx_audit_item_query_fields(int index, const char *field, char *key, size_t key_size);
const char	*zbx_audit_item_headers(int index, const char *field, char *key, size_t key_size);

void	zbx_audit_audit_entry_update_json_delete_lld_macro_path(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t lld_macro_pathid);
void	zbx_audit_entry_update_json_add_lld_macro_path(zbx_audit_entry_t *audit_entry, zbx_uint64_t lld_macro_pathid,
		const char *lld_macro, const char *path);

void	zbx_audit_entry_update_json_delete_filter_conditions(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t rule_conditionid);
void	zbx_audit_entry_update_json_add_filter_conditions(zbx_audit_entry_t *audit_entry, zbx_uint64_t rule_conditionid,
		zbx_uint64_t op, const char *macro, const char *value);

void	zbx_audit_entry_update_json_delete_lld_override_filter(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t conditionid);
void	zbx_audit_entry_update_json_delete_lld_override_operation(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t operationid);
void	zbx_audit_entry_update_json_delete_lld_override_operation_optag(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t optagid);
void	zbx_audit_entry_update_json_delete_lld_override_operation_optemplate(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t optemplateid);
void	zbx_audit_entry_update_json_add_lld_override_condition(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t override_conditionid, int condition_operator, const char *macro,
		const char *value);
void	zbx_audit_entry_update_json_add_lld_override_operation(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		zbx_uint64_t override_operationid, int operationobject, int condition_operator, const char *value);
void	zbx_audit_entry_update_json_add_lld_override_optag(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		zbx_uint64_t override_operationid, zbx_uint64_t lld_override_optagid, const char *tag,
		const char *value);
void	zbx_audit_entry_update_json_add_lld_override_optemplate(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		zbx_uint64_t operationid, zbx_uint64_t lld_override_optemplateid, zbx_uint64_t templateid);
void	zbx_audit_entry_update_json_delete_lld_override(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid);
void	zbx_audit_entry_update_json_add_lld_override(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		const char *name, int step, int stop);
void	zbx_audit_entry_update_json_add_lld_override_filter(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		int evaltype, const char *formula);
void	zbx_audit_entry_update_json_add_query_fields_json(zbx_audit_entry_t *audit_entry, const char *val);
void	zbx_audit_entry_update_json_add_headers(zbx_audit_entry_t* audit_entry, const char *val);

#endif	/* ZABBIX_AUDIT_ITEM_H */
