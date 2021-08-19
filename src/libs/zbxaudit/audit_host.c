/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "dbcache.h"
#include "audit.h"
#include "audit_host.h"

#define PREPARE_UPDATE_JSON_SNMP_INTERFACE_OP(auditentry)							\
	char	audit_key_version[AUDIT_DETAILS_KEY_LEN], audit_key_bulk[AUDIT_DETAILS_KEY_LEN],		\
		audit_key_community[AUDIT_DETAILS_KEY_LEN], audit_key_securityname[AUDIT_DETAILS_KEY_LEN],	\
		audit_key_securitylevel[AUDIT_DETAILS_KEY_LEN],							\
		audit_key_authpassphrase[AUDIT_DETAILS_KEY_LEN],						\
		audit_key_privpassphrase[AUDIT_DETAILS_KEY_LEN], audit_key_authprotocol[AUDIT_DETAILS_KEY_LEN],	\
		audit_key_privprotocol[AUDIT_DETAILS_KEY_LEN], audit_key_contextname[AUDIT_DETAILS_KEY_LEN],	\
		audit_key[AUDIT_DETAILS_KEY_LEN];								\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(audit_key, sizeof(audit_key), #auditentry".interfaces[" ZBX_FS_UI64			\
			"].details", interfaceid);								\
	zbx_snprintf(audit_key_version, sizeof(audit_key_version), #auditentry".interfaces[" ZBX_FS_UI64	\
			"].details.version", interfaceid);							\
	zbx_snprintf(audit_key_bulk, sizeof(audit_key_bulk), #auditentry".interfaces[" ZBX_FS_UI64		\
			"].details.bulk", interfaceid);								\
	zbx_snprintf(audit_key_community, sizeof(audit_key_community),						\
			#auditentry".interfaces[" ZBX_FS_UI64 "].details.community", interfaceid);		\
	zbx_snprintf(audit_key_securityname, sizeof(audit_key_securityname),					\
			#auditentry".interfaces[" ZBX_FS_UI64 "].details.securityname", interfaceid);		\
	zbx_snprintf(audit_key_securitylevel, sizeof(audit_key_securitylevel),					\
			#auditentry".interfaces[" ZBX_FS_UI64 "].details.securitylevel", interfaceid);		\
	zbx_snprintf(audit_key_authpassphrase, sizeof(audit_key_authpassphrase),				\
			#auditentry".interfaces[" ZBX_FS_UI64 "].details.authpassphrase", interfaceid);		\
	zbx_snprintf(audit_key_privpassphrase, sizeof(audit_key_privpassphrase),				\
			#auditentry".interfaces[" ZBX_FS_UI64 "].details.privpassphrase", interfaceid);		\
	zbx_snprintf(audit_key_authprotocol, sizeof(audit_key_authprotocol),					\
			#auditentry".interfaces[" ZBX_FS_UI64 "].details.authprotocol", interfaceid);		\
	zbx_snprintf(audit_key_privprotocol, sizeof(audit_key_privprotocol),					\
			#auditentry".interfaces[" ZBX_FS_UI64 "].details.privprotocol", interfaceid);		\
	zbx_snprintf(audit_key_contextname, sizeof(audit_key_contextname),					\
			#auditentry".interfaces[" ZBX_FS_UI64 "].details.contextname", interfaceid);		\

#define PREPARE_AUDIT_SNMP_INTERFACE(funcname, auditentry)							\
void	zbx_audit_##funcname##_update_json_add_snmp_interface(zbx_uint64_t hostid, zbx_uint64_t version,	\
		zbx_uint64_t bulk, const char *community, const char *securityname, zbx_uint64_t securitylevel,	\
		const char *authpassphrase, const char *privpassphrase, zbx_uint64_t authprotocol,		\
		zbx_uint64_t privprotocol, const char *contextname, zbx_uint64_t interfaceid)			\
{														\
PREPARE_UPDATE_JSON_SNMP_INTERFACE_OP(auditentry)								\
	zbx_audit_update_json_append(hostid,        AUDIT_DETAILS_ACTION_ADD, audit_key);			\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_version, version);	\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_bulk, bulk);		\
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_community, community);	\
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_securityname,		\
			securityname);										\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_securitylevel,		\
			securitylevel);										\
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_authpassphrase,		\
			authpassphrase);									\
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_privpassphrase,		\
			privpassphrase);									\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_authprotocol,		\
			authprotocol);										\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_privprotocol,		\
			privprotocol);										\
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_contextname,		\
			contextname);										\
}														\
														\
void	zbx_audit_##funcname##_update_json_update_snmp_interface(zbx_uint64_t hostid, zbx_uint64_t version_old,	\
		zbx_uint64_t version_new, zbx_uint64_t bulk_old,  zbx_uint64_t bulk_new,			\
		const char *community_old, const char *community_new, const char *securityname_old,		\
		const char *securityname_new, zbx_uint64_t securitylevel_old, zbx_uint64_t securitylevel_new,	\
		const char *authpassphrase_old, const char *authpassphrase_new, const char *privpassphrase_old,	\
		const char *privpassphrase_new, zbx_uint64_t authprotocol_old, zbx_uint64_t authprotocol_new,	\
		zbx_uint64_t privprotocol_old, zbx_uint64_t privprotocol_new, const char *contextname_old,	\
		const char *contextname_new, zbx_uint64_t interfaceid)						\
{														\
PREPARE_UPDATE_JSON_SNMP_INTERFACE_OP(funcname)									\
	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_UPDATE, audit_key);				\
	zbx_audit_update_json_update_uint64(hostid, audit_key_version, version_old, version_new);		\
	zbx_audit_update_json_update_uint64(hostid, audit_key_bulk, bulk_old, bulk_new);			\
	zbx_audit_update_json_update_string(hostid, audit_key_community, community_old, community_new);		\
	zbx_audit_update_json_update_string(hostid, audit_key_securityname, securityname_old, securityname_new);\
	zbx_audit_update_json_update_uint64(hostid, audit_key_securitylevel, securitylevel_old,			\
			securitylevel_new);									\
	zbx_audit_update_json_update_string(hostid, audit_key_authpassphrase, authpassphrase_old,		\
			authpassphrase_new);									\
	zbx_audit_update_json_update_string(hostid, audit_key_privpassphrase, privpassphrase_old,		\
			privpassphrase_new);									\
	zbx_audit_update_json_update_uint64(hostid, audit_key_authprotocol, authprotocol_old, authprotocol_new);\
	zbx_audit_update_json_update_uint64(hostid, audit_key_privprotocol, privprotocol_old, privprotocol_new);\
	zbx_audit_update_json_update_string(hostid, audit_key_contextname, contextname_old, contextname_new);	\
}														\

PREPARE_AUDIT_SNMP_INTERFACE(host, host)
PREPARE_AUDIT_SNMP_INTERFACE(host_prototype, hostprototype)

void	zbx_audit_host_update_json_add_proxy_hostid_and_hostname(zbx_uint64_t hostid, zbx_uint64_t proxy_hostid,
		const char *hostname)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, "host.proxy_hostid", proxy_hostid);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.host", hostname);
}

void	zbx_audit_host_update_json_add_tls_and_psk(zbx_uint64_t hostid, int tls_connect, int tls_accept,
		const char *psk_identity, const char *psk)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.tls_connect", tls_connect);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.tls_accept", tls_accept);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.psk_identity", psk_identity);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.psk", psk);
}

void	zbx_audit_host_update_json_add_inventory_mode(zbx_uint64_t hostid, int inventory_mode)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.inventory_mode", inventory_mode);
}

void	zbx_audit_host_update_json_update_inventory_mode(zbx_uint64_t hostid, int inventory_mode_old,
		int inventory_mode_new)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_update_json_update_int(hostid, "host.inventory_mode", inventory_mode_old, inventory_mode_new);
}

void	zbx_audit_host_update_json_update_host_status(zbx_uint64_t hostid, int host_status_old,
		int host_status_new)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_update_json_update_int(hostid, "host.status", host_status_old, host_status_new);
}

#define PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, interface_resource, type1, type2)			\
void	zbx_audit_##funcname##_update_json_update_interface_##interface_resource(zbx_uint64_t hostid,		\
		zbx_uint64_t interfaceid, type1 interface_resource##_old, type1 interface_resource##_new)	\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(buf, sizeof(buf), #auditentry".interfaces[" ZBX_FS_UI64 "].details."#interface_resource,	\
			interfaceid);										\
	zbx_audit_update_json_update_##type2(hostid, buf, interface_resource##_old, interface_resource##_new);	\
}														\

#define	PREPARE_AUDIT_HOST(funcname, auditentry, audit_resource_flag)						\
void	zbx_audit_##funcname##_create_entry(int audit_action, zbx_uint64_t hostid, const char *name)		\
{														\
	zbx_audit_entry_t	local_audit_host_entry, **found_audit_host_entry;				\
	zbx_audit_entry_t	*local_audit_host_entry_x = &local_audit_host_entry;				\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	local_audit_host_entry.id = hostid;									\
														\
	found_audit_host_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),		\
			&(local_audit_host_entry_x));								\
	if (NULL == found_audit_host_entry)									\
	{													\
		zbx_audit_entry_t	*local_audit_host_entry_insert;						\
														\
		local_audit_host_entry_insert = (zbx_audit_entry_t*)zbx_malloc(NULL, sizeof(zbx_audit_entry_t));\
		local_audit_host_entry_insert->id = hostid;							\
		local_audit_host_entry_insert->name = zbx_strdup(NULL, name);					\
		local_audit_host_entry_insert->audit_action = audit_action;					\
		local_audit_host_entry_insert->resource_type = audit_resource_flag;				\
		zbx_json_init(&(local_audit_host_entry_insert->details_json), ZBX_JSON_STAT_BUF_LEN);		\
		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_host_entry_insert,			\
				sizeof(local_audit_host_entry_insert));						\
														\
		if (AUDIT_ACTION_ADD == audit_action)								\
		{												\
			zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD,			\
					#auditentry".hostid", hostid);						\
		}												\
	}													\
}														\
														\
void	zbx_audit_##funcname##_update_json_add_interfaces(zbx_uint64_t hostid, zbx_uint64_t interfaceid,	\
		zbx_uint64_t main_, zbx_uint64_t type, zbx_uint64_t useip, const char *ip, const char *dns,	\
		int port)											\
{														\
	char	audit_key_main[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],			\
		audit_key_useip[AUDIT_DETAILS_KEY_LEN], audit_key_ip[AUDIT_DETAILS_KEY_LEN],			\
		audit_key_dns[AUDIT_DETAILS_KEY_LEN], audit_key_port[AUDIT_DETAILS_KEY_LEN],			\
		audit_key[AUDIT_DETAILS_KEY_LEN];								\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(audit_key, sizeof(audit_key), #auditentry".interfaces[" ZBX_FS_UI64 "]", interfaceid);	\
	zbx_snprintf(audit_key_main,  sizeof(audit_key_main),  #auditentry".interfaces[" ZBX_FS_UI64		\
			"].main", interfaceid);									\
	zbx_snprintf(audit_key_type,  sizeof(audit_key_type),  #auditentry".interfaces[" ZBX_FS_UI64		\
			"].type", interfaceid);									\
	zbx_snprintf(audit_key_useip, sizeof(audit_key_useip), #auditentry".interfaces[" ZBX_FS_UI64		\
			"].useip", interfaceid);								\
	zbx_snprintf(audit_key_ip,    sizeof(audit_key_ip),    #auditentry".interfaces[" ZBX_FS_UI64		\
			"].ip", interfaceid);									\
	zbx_snprintf(audit_key_dns,   sizeof(audit_key_dns),   #auditentry".interfaces[" ZBX_FS_UI64		\
			"].dns", interfaceid);									\
	zbx_snprintf(audit_key_port,  sizeof(audit_key_port),  #auditentry".interfaces[" ZBX_FS_UI64		\
			"].port", interfaceid);									\
														\
	zbx_audit_update_json_append(hostid,        AUDIT_DETAILS_ACTION_ADD, audit_key);			\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_main, main_);		\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_type, type);		\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_useip, useip);		\
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_ip, ip);		\
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_dns, dns);		\
	zbx_audit_update_json_append_int(hostid,    AUDIT_DETAILS_ACTION_ADD, audit_key_port, port);		\
}														\
														\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, useip, zbx_uint64_t, uint64)					\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, main, zbx_uint64_t, uint64)					\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, type, zbx_uint64_t, uint64)					\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, ip, const char*, string)					\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, dns, const char*, string)					\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, port, int, int)						\
/* snmp */													\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, version, zbx_uint64_t, uint64)				\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, bulk, zbx_uint64_t, uint64)					\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, community, const char*, string)				\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, securityname, const char*, string)				\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, securitylevel, int, int)					\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, authpassphrase, const char*, string)				\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, privpassphrase, const char*, string)				\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, authprotocol, zbx_uint64_t, uint64)				\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, privprotocol, zbx_uint64_t, uint64)				\
PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, contextname, const char*, string)				\

PREPARE_AUDIT_HOST(host, host, AUDIT_RESOURCE_HOST)
PREPARE_AUDIT_HOST(host_prototype, hostprototype, AUDIT_RESOURCE_HOST_PROTOTYPE)
#undef PREPARE_AUDIT_HOST
#undef PREPARE_AUDIT_HOST_INTERFACE

void	zbx_audit_hostgroup_update_json_add_group(zbx_uint64_t hostid, zbx_uint64_t hostgroupid, zbx_uint64_t groupid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_hostid[AUDIT_DETAILS_KEY_LEN],
		audit_key_groupid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "host.groups[" ZBX_FS_UI64 "]", hostgroupid);
	zbx_snprintf(audit_key_hostid, sizeof(audit_key_hostid), "host.groups[" ZBX_FS_UI64 "].hostid", hostgroupid);
	zbx_snprintf(audit_key_groupid, sizeof(audit_key_groupid), "host.groups[" ZBX_FS_UI64 "].groupid", hostgroupid);

	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_hostid, hostid);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_groupid, groupid);
}

void	zbx_audit_host_hostgroup_delete(zbx_uint64_t hostid, const char* hostname, zbx_vector_uint64_t *hostgroupids,
		zbx_vector_uint64_t *groupids)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];
	int	i;

	RETURN_IF_AUDIT_OFF();

	zbx_audit_host_create_entry(AUDIT_ACTION_UPDATE, hostid, hostname);

	for (i = 0; i < groupids->values_num; i++)
	{
		zbx_snprintf(buf, sizeof(buf), "host.groups[" ZBX_FS_UI64 "].groupid", hostgroupids->values[i]);
		zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_DELETE, buf,
				groupids->values[i]);
	}
}

void	zbx_audit_host_del(zbx_uint64_t hostid, const char *hostname)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_host_create_entry(AUDIT_ACTION_DELETE, hostid, hostname);
}

void	zbx_audit_host_prototype_del(zbx_uint64_t hostid, const char *hostname)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_host_prototype_create_entry(AUDIT_ACTION_DELETE, hostid, hostname);
}

void	zbx_audit_host_prototype_update_json_add_details(zbx_uint64_t hostid, zbx_uint64_t templateid,
		const char *name, int status, int discover, int custom_interfaces)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, "hostprototype.templateid",
			templateid);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "hostprototype.name", name);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "hostprototype.status", status);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "hostprototype.discover", discover);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "hostprototype.custom_interfaces",
			custom_interfaces);
}

void	zbx_audit_host_prototype_update_json_update_templateid(zbx_uint64_t hostid, zbx_uint64_t templateid_orig,
		zbx_uint64_t templateid)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_update_json_update_uint64(hostid, "hostprototype.templateid", templateid_orig, templateid);
}

#define PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(resource, type1, type2)						\
void	zbx_audit_host_prototype_update_json_update_##resource(zbx_uint64_t hostid, type1 old_##resource,	\
		type1 new_##resource)										\
{														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_audit_update_json_update_##type2(hostid, "hostprototype."#resource, old_##resource, new_##resource);\
}														\

PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(name, const char*, string)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(status, int, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(discover, int, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(custom_interfaces, int, int)
#undef PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE

void	zbx_audit_host_prototype_update_json_add_group_details(zbx_uint64_t hostid, zbx_uint64_t group_prototypeid,
		const char* name, zbx_uint64_t groupid, zbx_uint64_t templateid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_hostid[AUDIT_DETAILS_KEY_LEN],
		audit_key_name[AUDIT_DETAILS_KEY_LEN], audit_key_groupid[AUDIT_DETAILS_KEY_LEN],
		audit_key_templateid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	if (0 != strlen(name))
	{
		zbx_snprintf(audit_key, sizeof(audit_key), "hostprototype.groupPrototypes[" ZBX_FS_UI64 "]",
				group_prototypeid);
		zbx_snprintf(audit_key_hostid, sizeof(audit_key_hostid), "hostprototype.groupPrototypes["
				ZBX_FS_UI64 "].hostid", group_prototypeid);
		zbx_snprintf(audit_key_name, sizeof(audit_key_name), "hostprototype.groupPrototypes[" ZBX_FS_UI64
				"].name", group_prototypeid);
		zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), "hostprototype.groupPrototypes["
				ZBX_FS_UI64 "].templateid", group_prototypeid);
		zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_name, name);
	}
	else if (0 != groupid)
	{
		zbx_snprintf(audit_key, sizeof(audit_key), "hostprototype.groupLinks[" ZBX_FS_UI64 "]",
				group_prototypeid);
		zbx_snprintf(audit_key_hostid, sizeof(audit_key_hostid), "hostprototype.groupLinks[" ZBX_FS_UI64
				"].hostid", group_prototypeid);
		zbx_snprintf(audit_key_groupid, sizeof(audit_key_groupid), "hostprototype.groupLinks[" ZBX_FS_UI64
				"].groupid", group_prototypeid);
		zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), "hostprototype.groupLinks[" ZBX_FS_UI64
				"].templateid", group_prototypeid);
		zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_groupid, groupid);
	}

	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_hostid, hostid);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_templateid, templateid);
}

void	zbx_audit_host_prototype_update_json_update_group_links(zbx_uint64_t hostid, zbx_uint64_t groupid,
		zbx_uint64_t templateid_old, zbx_uint64_t templateid_new)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_groupid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key_groupid), "hostprototype.groupLinks[" ZBX_FS_UI64 "]", groupid);
	zbx_snprintf(audit_key_groupid, sizeof(audit_key_groupid), "hostprototype.groupLinks[" ZBX_FS_UI64 "].groupid",
			groupid);

	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_UPDATE, audit_key);
	zbx_audit_update_json_update_uint64(hostid, audit_key_groupid, templateid_old, templateid_new);
}

#define PREPARE_AUDIT_TEMPLATE_ADD(funcname, auditentry)							\
void	zbx_audit_##funcname##_update_json_add_parent_template(zbx_uint64_t hostid,				\
		zbx_uint64_t hosttemplateid, zbx_uint64_t templateid)						\
{														\
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_hostid[AUDIT_DETAILS_KEY_LEN],			\
		audit_key_templateid[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(audit_key, sizeof(audit_key), #auditentry".templates[" ZBX_FS_UI64 "]", hosttemplateid);	\
	zbx_snprintf(audit_key_hostid, sizeof(audit_key), #auditentry".templates[" ZBX_FS_UI64 "].hostid",	\
			hosttemplateid);									\
	zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), #auditentry".templates[" ZBX_FS_UI64	\
			"].templateid", hosttemplateid);							\
														\
	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key);				\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_hostid, hostid);	\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_templateid,		\
			templateid);										\
}														\

#define PREPARE_AUDIT_TEMPLATE_DELETE(funcname, auditentry)							\
void	zbx_audit_##funcname##_update_json_delete_parent_template(zbx_uint64_t hostid,				\
		zbx_uint64_t hosttemplateid)									\
{														\
	char	audit_key_templateid[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), #auditentry".templates[" ZBX_FS_UI64	\
			"]", hosttemplateid);									\
														\
	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_DELETE, audit_key_templateid);		\
}														\

PREPARE_AUDIT_TEMPLATE_ADD(host, host)
PREPARE_AUDIT_TEMPLATE_DELETE(host, host)
PREPARE_AUDIT_TEMPLATE_ADD(host_prototype, hostprototype)
PREPARE_AUDIT_TEMPLATE_DELETE(host_prototype, hostprototype)

void	zbx_audit_host_prototype_update_json_delete_interface(zbx_uint64_t hostid, zbx_uint64_t interfaceid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "hostprototype.interfaces[" ZBX_FS_UI64 "]", interfaceid);

	zbx_audit_update_json_delete(hostid, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_host_prototype_update_json_add_hostmacro(zbx_uint64_t hostid, zbx_uint64_t macroid,
		const char *macro, const char *value, const char *description, int type)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN], audit_key_description[AUDIT_DETAILS_KEY_LEN],
		audit_key_type[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "hostprototype.macros[" ZBX_FS_UI64 "]", macroid);
	zbx_snprintf(audit_key_name, sizeof(audit_key_name), "hostprototype.macros[" ZBX_FS_UI64 "].name", macroid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "hostprototype.macros[" ZBX_FS_UI64 "].value", macroid);
	zbx_snprintf(audit_key_description, sizeof(audit_key_value), "hostprototype.macros[" ZBX_FS_UI64
			"].description", macroid);
	zbx_snprintf(audit_key_type, sizeof(audit_key_type), "hostprototype.macros[" ZBX_FS_UI64 "].type", macroid);

	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_name, macro);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_description, description);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_type, type);
}

void	zbx_audit_host_prototype_update_json_update_hostmacro_create_entry(zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "hostprototype.macros[" ZBX_FS_UI64 "]", hostmacroid);

	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

#define PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO(resource, type1, type2)					\
void	zbx_audit_host_prototype_update_json_update_hostmacro_##resource(zbx_uint64_t hostid,			\
		zbx_uint64_t hostmacroid, type1 old_##resource, type1 new_##resource)				\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(buf, sizeof(buf), "hostprototype.macros[" ZBX_FS_UI64 "]."#resource, hostmacroid);		\
														\
	zbx_audit_update_json_update_##type2(hostid, buf, old_##resource, new_##resource);			\
}														\

PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO(value, const char*, string)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO(description, const char*, string)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO(type, int, int)
#undef PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO

void	zbx_audit_host_prototype_update_json_delete_hostmacro(zbx_uint64_t hostid, zbx_uint64_t hostmacroid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "hostprototype.macros[" ZBX_FS_UI64 "]", hostmacroid);

	zbx_audit_update_json_delete(hostid, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_host_prototype_update_json_add_tag(zbx_uint64_t hostid, zbx_uint64_t tagid, const char* tag,
		const char* value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "hostprototype.tags[" ZBX_FS_UI64 "]", tagid);
	zbx_snprintf(audit_key_tag, sizeof(audit_key_tag), "hostprototype.tags[" ZBX_FS_UI64 "].tag", tagid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "hostprototype.tags[" ZBX_FS_UI64 "].value", tagid);

	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_tag, tag);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value);
}

void	zbx_audit_host_prototype_update_json_update_tag_create_entry(zbx_uint64_t hostid, zbx_uint64_t tagid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "hostprototype.tags[" ZBX_FS_UI64 "]", tagid);

	zbx_audit_update_json_append(hostid, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

void	zbx_audit_host_prototype_update_json_update_tag_tag(zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* tag_old, const char *tag_new)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "hostprototype.tags[" ZBX_FS_UI64 "].tag", tagid);

	zbx_audit_update_json_update_string(hostid, buf, tag_old, tag_new);
}

void	zbx_audit_host_prototype_update_json_update_tag_value(zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* value_old, const char *value_new)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "hostprototype.tags[" ZBX_FS_UI64 "].value", tagid);

	zbx_audit_update_json_update_string(hostid, buf, value_old, value_new);
}

void	zbx_audit_host_prototype_update_json_delete_tag(zbx_uint64_t hostid, zbx_uint64_t tagid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "hostprototype.tags[" ZBX_FS_UI64 "]", tagid);

	zbx_audit_update_json_delete(hostid, AUDIT_DETAILS_ACTION_DELETE, buf);
}
