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

#ifndef ZABBIX_AUDIT_HOST_H
#define ZABBIX_AUDIT_HOST_H

#include "zbxtypes.h"
#include "zbxalgo.h"

#define PREPARE_AUDIT_SNMP_INTERFACE_H(funcname)								\
void	zbx_audit_##funcname##_update_json_add_snmp_interface(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t version, zbx_uint64_t bulk, const char *community, const char *securityname,	\
		zbx_uint64_t securitylevel, const char *authpassphrase, const char *privpassphrase,		\
		zbx_uint64_t authprotocol, zbx_uint64_t privprotocol, const char *contextname,			\
		zbx_uint64_t interfaceid);									\
void	zbx_audit_##funcname##_update_json_update_snmp_interface(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t version_old, zbx_uint64_t version_new, zbx_uint64_t bulk_old,			\
		zbx_uint64_t bulk_new, const char *community_old, const char *community_new,			\
		const char *securityname_old, const char *securityname_new, zbx_uint64_t securitylevel_old,	\
		zbx_uint64_t securitylevel_new, const char *authpassphrase_old,	const char *authpassphrase_new,	\
		const char *privpassphrase_old, const char *privpassphrase_new,	zbx_uint64_t authprotocol_old,	\
		zbx_uint64_t authprotocol_new, zbx_uint64_t privprotocol_old, zbx_uint64_t privprotocol_new,	\
		const char *contextname_old, const char *contextname_new, zbx_uint64_t interfaceid);		\

PREPARE_AUDIT_SNMP_INTERFACE_H(host)
PREPARE_AUDIT_SNMP_INTERFACE_H(host_prototype)

void	zbx_audit_host_update_json_add_monitoring_and_hostname_and_inventory_mode(int audit_context_mode,
		zbx_uint64_t hostid, unsigned char monitored_by, zbx_uint64_t proxyid, zbx_uint64_t proxy_groupid,
		const char *hostname, int inventory_mode);
void	zbx_audit_host_update_json_add_tls_and_psk(int audit_context_mode, zbx_uint64_t hostid, int tls_connect,
		int tls_accept);
void	zbx_audit_host_update_json_add_inventory_mode(int audit_context_mode, zbx_uint64_t hostid, int inventory_mode);
void	zbx_audit_host_update_json_update_inventory_mode(int audit_context_mode, zbx_uint64_t hostid,
		int inventory_mode_old, int inventory_mode_new);
void	zbx_audit_host_update_json_update_host_status(int audit_context_mode, zbx_uint64_t hostid, int host_status_old,
		int host_status_new);

#define PREPARE_AUDIT_HOST_INTERFACE_H(funcname, interface_resource, type1)					\
void	zbx_audit_##funcname##_update_json_update_interface_##interface_resource(int audit_context_mode,	\
		zbx_uint64_t hostid, zbx_uint64_t interfaceid, type1 interface_resource##_old,			\
		type1 interface_resource##_new);								\

#define	PREPARE_AUDIT_HOST_H(funcname, audit_resource_flag)							\
void	zbx_audit_##funcname##_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t hostid,	\
		const char *name);										\
void	zbx_audit_##funcname##_update_json_add_interfaces(int audit_context_mode, zbx_uint64_t hostid,		\
		zbx_uint64_t interfaceid, zbx_uint64_t main_, zbx_uint64_t type, zbx_uint64_t useip,		\
		const char *ip, const char *dns, int port);							\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, useip, zbx_uint64_t)							\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, main, zbx_uint64_t)							\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, type, zbx_uint64_t)							\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, ip, const char*)							\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, dns, const char*)							\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, port, int)								\
/* snmp */													\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, version, zbx_uint64_t)							\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, bulk, zbx_uint64_t)							\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, community, const char*)						\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, securityname, const char*)						\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, securitylevel, int)							\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, authpassphrase, const char*)						\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, privpassphrase, const char*)						\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, authprotocol, zbx_uint64_t)						\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, privprotocol, zbx_uint64_t)						\
PREPARE_AUDIT_HOST_INTERFACE_H(funcname, contextname, const char*)						\

PREPARE_AUDIT_HOST_H(host, ZBX_AUDIT_RESOURCE_HOST)
PREPARE_AUDIT_HOST_H(host_prototype, ZBX_AUDIT_RESOURCE_HOST_PROTOTYPE)

#define PREPARE_AUDIT_HOST_UPDATE_H(resource, type1)							\
void	zbx_audit_host_update_json_update_##resource(int audit_context_mode, zbx_uint64_t hostid,	\
		type1 old_##resource, type1 new_##resource);						\

PREPARE_AUDIT_HOST_UPDATE_H(host, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(name, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(proxyid, zbx_uint64_t)
PREPARE_AUDIT_HOST_UPDATE_H(proxy_groupid, zbx_uint64_t)
PREPARE_AUDIT_HOST_UPDATE_H(monitored_by, int)
PREPARE_AUDIT_HOST_UPDATE_H(ipmi_authtype, int)
PREPARE_AUDIT_HOST_UPDATE_H(ipmi_privilege, int)
PREPARE_AUDIT_HOST_UPDATE_H(ipmi_username, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(ipmi_password, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(tls_connect, int)
PREPARE_AUDIT_HOST_UPDATE_H(tls_accept, int)
PREPARE_AUDIT_HOST_UPDATE_H(tls_issuer, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(tls_subject, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(tls_psk_identity, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(tls_psk, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(custom_interfaces, int)
#undef PREPARE_AUDIT_HOST_UPDATE_H

void	zbx_audit_host_update_json_delete_interface(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t interfaceid);
void	zbx_audit_host_update_json_add_hostmacro(int audit_context_mode, zbx_uint64_t hostid, int audit_resource,
		zbx_uint64_t macroid, const char *macro, const char *value, const char *description, int type,
		int automatic);

#define PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO_H(resource, type1)							\
void	zbx_audit_host_update_json_update_hostmacro_##resource(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t hostmacroid, type1 old_##resource, type1 new_##resource);
PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO_H(value, const char*)
PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO_H(description, const char*)
PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO_H(type, int)

void	zbx_audit_host_update_json_delete_hostmacro(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid);

void	zbx_audit_host_update_json_add_tag(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* tag, const char* value, int automatic);

void	zbx_audit_host_update_json_update_tag_tag(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* tag_old, const char *tag_new);
void	zbx_audit_host_update_json_update_tag_value(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* value_old, const char *value_new);
void	zbx_audit_host_update_json_update_tag_type(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		int automatic_old, int automatic_new);

void	zbx_audit_host_update_json_delete_tag(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid);

void	zbx_audit_hostgroup_update_json_add_group(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t hostgroupid,
		zbx_uint64_t groupid);
void	zbx_audit_hostgroup_update_json_delete_group(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostgroupid, zbx_uint64_t groupid);
void	zbx_audit_host_hostgroup_delete(int audit_context_mode, zbx_uint64_t hostid, const char* hostname,
		zbx_vector_uint64_t *hostgroupids, zbx_vector_uint64_t *groupids);
void	zbx_audit_host_del(int audit_context_mode, zbx_uint64_t hostid, const char *hostname);
void	zbx_audit_host_update_json_add_details(int audit_context_mode, zbx_uint64_t hostid, const char *host,
		unsigned char monitored_by, zbx_uint64_t proxyid, zbx_uint64_t proxy_groupid, int ipmi_authtype,
		int ipmi_privilege, const char *ipmi_username, int status, int flags, int tls_connect, int tls_accept,
		const char *tls_issuer, const char *tls_subject, int custom_interfaces, int inventory_mode);
void	zbx_audit_host_prototype_del(int audit_context_mode, zbx_uint64_t hostid, const char *hostname);
void	zbx_audit_host_prototype_update_json_add_details(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t templateid, const char *name, int status, int discover, int custom_interfaces,
		int inventory_mode, const char *host);
void	zbx_audit_host_prototype_update_json_update_templateid(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t templateid_orig, zbx_uint64_t templateid);

#define PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(resource, type1)							\
void	zbx_audit_host_prototype_update_json_update_##resource(int audit_context_mode, zbx_uint64_t hostid,	\
		type1 old_##resource, type1 new_##resource);							\

PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(name, const char*)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(status, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(discover, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(custom_interfaces, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(inventory_mode, int)

void	zbx_audit_host_prototype_update_json_add_group_details(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t group_prototypeid, const char* name, zbx_uint64_t groupid, zbx_uint64_t templateid);

void	zbx_audit_host_prototype_update_json_update_group_details(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t group_prototypeid, const char* name, zbx_uint64_t groupid, zbx_uint64_t templateid_old,
		zbx_uint64_t templateid_new);

#define PREPARE_AUDIT_TEMPLATE_ADD_H(funcname)									\
void	zbx_audit_##funcname##_update_json_add_parent_template(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t hosttemplateid, zbx_uint64_t templateid, int link_type);
#define PREPARE_AUDIT_TEMPLATE_DELETE_H(funcname)								\
void	zbx_audit_##funcname##_update_json_delete_parent_template(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t hosttemplateid);
PREPARE_AUDIT_TEMPLATE_ADD_H(host)
PREPARE_AUDIT_TEMPLATE_DELETE_H(host)
PREPARE_AUDIT_TEMPLATE_ADD_H(host_prototype)
PREPARE_AUDIT_TEMPLATE_DELETE_H(host_prototype)

void	zbx_audit_host_prototype_update_json_delete_interface(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t interfaceid);

void	zbx_audit_host_update_json_update_hostmacro_create_entry(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid);
void	zbx_audit_host_prototype_update_json_update_hostmacro_create_entry(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid);
#define PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO_H(resource, type1)					\
void	zbx_audit_host_prototype_update_json_update_hostmacro_##resource(int audit_context_mode,		\
		zbx_uint64_t hostid, zbx_uint64_t hostmacroid, type1 old_##resource, type1 new_##resource);
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO_H(value, const char*)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO_H(description, const char*)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO_H(type, int)

void	zbx_audit_host_prototype_update_json_delete_hostmacro(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid);

void	zbx_audit_host_prototype_update_json_add_tag(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* tag, const char* value, int automatic);
void	zbx_audit_host_prototype_update_json_update_tag_create_entry(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t tagid);
void	zbx_audit_host_update_json_update_tag_create_entry(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t tagid);
void	zbx_audit_host_prototype_update_json_update_tag_tag(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t tagid, const char* tag_old, const char *tag_new);
void	zbx_audit_host_prototype_update_json_update_tag_value(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t tagid, const char* value_old, const char *value_new);

void	zbx_audit_host_prototype_update_json_delete_tag(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t tagid);

void	zbx_audit_host_group_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t groupid,
		const char *name);
void	zbx_audit_host_group_del(int audit_context_mode, zbx_uint64_t groupid, const char *name);
void	zbx_audit_host_group_update_json_add_details(int audit_context_mode, zbx_uint64_t groupid, const char *name,
		int flags);

#define PREPARE_AUDIT_HOST_GROUP_UPDATE_H(resource, type1)							\
void	zbx_audit_host_group_update_json_update_##resource(int audit_context_mode, zbx_uint64_t groupid,	\
		type1 old_##resource, type1 new_##resource);							\

PREPARE_AUDIT_HOST_GROUP_UPDATE_H(name, const char*)
#undef PREPARE_AUDIT_HOST_UPDATE_H

void	zbx_audit_host_update_json_add_proxyid(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t proxyid);

void	zbx_audit_host_prototype_update_json_add_lldruleid(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t lldrule_id);
#endif	/* ZABBIX_AUDIT_HOST_H */
