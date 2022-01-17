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

#ifndef ZABBIX_AUDIT_HOST_H
#define ZABBIX_AUDIT_HOST_H

#include "zbxtypes.h"
#include "zbxalgo.h"

#define PREPARE_AUDIT_SNMP_INTERFACE_H(funcname)								\
void	zbx_audit_##funcname##_update_json_add_snmp_interface(zbx_uint64_t hostid, zbx_uint64_t version,	\
		zbx_uint64_t bulk, const char *community, const char *securityname, zbx_uint64_t securitylevel,	\
		const char *authpassphrase, const char *privpassphrase, zbx_uint64_t authprotocol,		\
		zbx_uint64_t privprotocol, const char *contextname, zbx_uint64_t interfaceid);			\
void	zbx_audit_##funcname##_update_json_update_snmp_interface(zbx_uint64_t hostid, zbx_uint64_t version_old,	\
		zbx_uint64_t version_new, zbx_uint64_t bulk_old,  zbx_uint64_t bulk_new,			\
		const char *community_old, const char *community_new, const char *securityname_old,		\
		const char *securityname_new, zbx_uint64_t securitylevel_old, zbx_uint64_t securitylevel_new,	\
		const char *authpassphrase_old,	const char *authpassphrase_new, const char *privpassphrase_old,	\
		const char *privpassphrase_new,	zbx_uint64_t authprotocol_old, zbx_uint64_t authprotocol_new,	\
		zbx_uint64_t privprotocol_old, zbx_uint64_t privprotocol_new, const char *contextname_old,	\
		const char *contextname_new, zbx_uint64_t interfaceid);						\

PREPARE_AUDIT_SNMP_INTERFACE_H(host)
PREPARE_AUDIT_SNMP_INTERFACE_H(host_prototype)

void	zbx_audit_host_update_json_add_proxy_hostid_and_hostname_and_inventory_mode(zbx_uint64_t hostid,
		zbx_uint64_t proxy_hostid, const char *hostname, int inventory_mode);
void	zbx_audit_host_update_json_add_tls_and_psk(zbx_uint64_t hostid, int tls_connect, int tls_accept,
		const char *tls_psk_identity, const char *tls_psk);
void	zbx_audit_host_update_json_add_inventory_mode(zbx_uint64_t hostid, int inventory_mode);
void	zbx_audit_host_update_json_update_inventory_mode(zbx_uint64_t hostid, int inventory_mode_old,
		int inventory_mode_new);
void	zbx_audit_host_update_json_update_host_status(zbx_uint64_t hostid, int host_status_old,
		int host_status_new);
void	zbx_audit_host_prototype_update_json_update_interface_details_create_entry(zbx_uint64_t hostid,
		zbx_uint64_t interfaceid);

#define PREPARE_AUDIT_HOST_INTERFACE_H(funcname, interface_resource, type1)					\
void	zbx_audit_##funcname##_update_json_update_interface_##interface_resource(zbx_uint64_t hostid,		\
		zbx_uint64_t interfaceid, type1 interface_resource##_old, type1 interface_resource##_new);	\

#define	PREPARE_AUDIT_HOST_H(funcname, audit_resource_flag)							\
void	zbx_audit_##funcname##_create_entry(int audit_action, zbx_uint64_t hostid, const char *name);		\
void	zbx_audit_##funcname##_update_json_add_interfaces(zbx_uint64_t hostid, zbx_uint64_t interfaceid,	\
		zbx_uint64_t main_, zbx_uint64_t type, zbx_uint64_t useip, const char *ip, const char *dns,	\
		int port);											\
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

PREPARE_AUDIT_HOST_H(host, AUDIT_RESOURCE_HOST)
PREPARE_AUDIT_HOST_H(host_prototype, AUDIT_RESOURCE_HOST_PROTOTYPE)

#define PREPARE_AUDIT_HOST_UPDATE_H(resource, type1)							\
void	zbx_audit_host_update_json_update_##resource(zbx_uint64_t hostid, type1 old_##resource,		\
		type1 new_##resource);									\

PREPARE_AUDIT_HOST_UPDATE_H(host, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(name, const char*)
PREPARE_AUDIT_HOST_UPDATE_H(proxy_hostid, zbx_uint64_t)
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

void	zbx_audit_host_update_json_delete_interface(zbx_uint64_t hostid, zbx_uint64_t interfaceid);
void	zbx_audit_host_update_json_add_hostmacro(zbx_uint64_t hostid, zbx_uint64_t macroid,
		const char *macro, const char *value, const char *description, int type);

#define PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO_H(resource, type1)					\
void	zbx_audit_host_update_json_update_hostmacro_##resource(zbx_uint64_t hostid,		\
		zbx_uint64_t hostmacroid, type1 old_##resource, type1 new_##resource);
PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO_H(value, const char*)
PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO_H(description, const char*)
PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO_H(type, int)

void	zbx_audit_host_update_json_delete_hostmacro(zbx_uint64_t hostid, zbx_uint64_t hostmacroid);

void	zbx_audit_host_update_json_add_tag(zbx_uint64_t hostid, zbx_uint64_t tagid, const char* tag,
		const char* value);

void	zbx_audit_host_update_json_update_tag_tag(zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* tag_old, const char *tag_new);

void	zbx_audit_host_update_json_update_tag_value(zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* value_old, const char *value_new);

void	zbx_audit_host_update_json_delete_tag(zbx_uint64_t hostid, zbx_uint64_t tagid);

void	zbx_audit_hostgroup_update_json_add_group(zbx_uint64_t hostid, zbx_uint64_t hostgroupid, zbx_uint64_t groupid);
void	zbx_audit_hostgroup_update_json_delete_group(zbx_uint64_t hostid, zbx_uint64_t hostgroupid,
		zbx_uint64_t groupid);
void	zbx_audit_host_hostgroup_delete(zbx_uint64_t hostid, const char* hostname, zbx_vector_uint64_t *hostgroupids,
		zbx_vector_uint64_t *groupids);
void	zbx_audit_host_del(zbx_uint64_t hostid, const char *hostname);
void	zbx_audit_host_update_json_add_details(zbx_uint64_t hostid, const char *host, zbx_uint64_t proxy_hostid,
		int ipmi_authtype, int ipmi_privilege, const char *ipmi_username, const char *ipmi_password,
		int status, int flags, int tls_connect, int tls_accept, const char *tls_issuer, const char *tls_subject,
		const char *tls_psk_identity, const char *tls_psk, int custom_interfaces, int inventory_mode);
void	zbx_audit_host_prototype_del(zbx_uint64_t hostid, const char *hostname);
void	zbx_audit_host_prototype_update_json_add_details(zbx_uint64_t hostid, zbx_uint64_t templateid, const char *name,
		int status, int discover, int custom_interfaces, int inventory_mode);
void	zbx_audit_host_prototype_update_json_update_templateid(zbx_uint64_t hostid, zbx_uint64_t templateid_orig,
		zbx_uint64_t templateid);

#define PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(resource, type1)							\
void	zbx_audit_host_prototype_update_json_update_##resource(zbx_uint64_t hostid, type1 old_##resource,	\
		type1 new_##resource);										\

PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(name, const char*)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(status, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(discover, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(custom_interfaces, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_H(inventory_mode, int)

void	zbx_audit_host_prototype_update_json_add_group_details(zbx_uint64_t hostid, zbx_uint64_t group_prototypeid,
		const char* name, zbx_uint64_t groupid, zbx_uint64_t templateid);

void	zbx_audit_host_prototype_update_json_update_group_details(zbx_uint64_t hostid, zbx_uint64_t group_prototypeid,
		const char* name, zbx_uint64_t groupid, zbx_uint64_t templateid_old, zbx_uint64_t templateid_new);

#define PREPARE_AUDIT_TEMPLATE_ADD_H(funcname)									\
void	zbx_audit_##funcname##_update_json_add_parent_template(zbx_uint64_t hostid,				\
		zbx_uint64_t hosttemplateid, zbx_uint64_t templateid);
#define PREPARE_AUDIT_TEMPLATE_DELETE_H(funcname)								\
void	zbx_audit_##funcname##_update_json_delete_parent_template(zbx_uint64_t hostid,				\
		zbx_uint64_t hosttemplateid);
PREPARE_AUDIT_TEMPLATE_ADD_H(host)
PREPARE_AUDIT_TEMPLATE_DELETE_H(host)
PREPARE_AUDIT_TEMPLATE_ADD_H(host_prototype)
PREPARE_AUDIT_TEMPLATE_DELETE_H(host_prototype)

void	zbx_audit_host_prototype_update_json_delete_interface(zbx_uint64_t hostid, zbx_uint64_t interfaceid);

void	zbx_audit_host_prototype_update_json_add_hostmacro(zbx_uint64_t hostid, zbx_uint64_t macroid,
		const char *macro, const char *value, const char *description, int type);
void	zbx_audit_host_update_json_update_hostmacro_create_entry(zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid);
void	zbx_audit_host_prototype_update_json_update_hostmacro_create_entry(zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid);
#define PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO_H(resource, type1)					\
void	zbx_audit_host_prototype_update_json_update_hostmacro_##resource(zbx_uint64_t hostid,			\
		zbx_uint64_t hostmacroid, type1 old_##resource, type1 new_##resource);
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO_H(value, const char*)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO_H(description, const char*)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO_H(type, int)

void	zbx_audit_host_prototype_update_json_delete_hostmacro(zbx_uint64_t hostid, zbx_uint64_t hostmacroid);

void	zbx_audit_host_prototype_update_json_add_tag(zbx_uint64_t hostid, zbx_uint64_t tagid, const char* tag,
		const char* value);
void	zbx_audit_host_prototype_update_json_update_tag_create_entry(zbx_uint64_t hostid, zbx_uint64_t tagid);
void	zbx_audit_host_update_json_update_tag_create_entry(zbx_uint64_t hostid, zbx_uint64_t tagid);
void	zbx_audit_host_prototype_update_json_update_tag_tag(zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* tag_old, const char *tag_new);
void	zbx_audit_host_prototype_update_json_update_tag_value(zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* value_old, const char *value_new);

void	zbx_audit_host_prototype_update_json_delete_tag(zbx_uint64_t hostid, zbx_uint64_t tagid);

void	zbx_audit_host_group_create_entry(int audit_action, zbx_uint64_t groupid, const char *name);
void	zbx_audit_host_group_del(zbx_uint64_t groupid, const char *name);
void	zbx_audit_host_group_update_json_add_details(zbx_uint64_t groupid, const char *name, int flags);

#define PREPARE_AUDIT_HOST_GROUP_UPDATE_H(resource, type1)							\
void	zbx_audit_host_group_update_json_update_##resource(zbx_uint64_t groupid, type1 old_##resource,		\
		type1 new_##resource);										\

PREPARE_AUDIT_HOST_GROUP_UPDATE_H(name, const char*)
#undef PREPARE_AUDIT_HOST_UPDATE_H

#endif	/* ZABBIX_AUDIT_HOST_H */
