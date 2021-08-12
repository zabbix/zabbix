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

#define PREPARE_UPDATE_JSON_SNMP_INTERFACE_OP(resource)								\
	char	audit_key_version[AUDIT_DETAILS_KEY_LEN], audit_key_bulk[AUDIT_DETAILS_KEY_LEN],		\
		audit_key_community[AUDIT_DETAILS_KEY_LEN], audit_key_securityname[AUDIT_DETAILS_KEY_LEN],	\
		audit_key_securitylevel[AUDIT_DETAILS_KEY_LEN],							\
		audit_key_authpassphrase[AUDIT_DETAILS_KEY_LEN],						\
		audit_key_privpassphrase[AUDIT_DETAILS_KEY_LEN], audit_key_authprotocol[AUDIT_DETAILS_KEY_LEN],	\
		audit_key_privprotocol[AUDIT_DETAILS_KEY_LEN], audit_key_contextname[AUDIT_DETAILS_KEY_LEN];	\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(audit_key_version, AUDIT_DETAILS_KEY_LEN, #resource".interfaces[%lu].details.version",	\
			interfaceid);										\
	zbx_snprintf(audit_key_bulk, AUDIT_DETAILS_KEY_LEN, #resource".interfaces[%lu].details.bulk",		\
			interfaceid);										\
	zbx_snprintf(audit_key_community, AUDIT_DETAILS_KEY_LEN, #resource".interfaces[%lu].details.community",	\
			interfaceid);										\
	zbx_snprintf(audit_key_securityname, AUDIT_DETAILS_KEY_LEN,						\
			#resource".interfaces[%lu].details.securityname", interfaceid);				\
	zbx_snprintf(audit_key_securitylevel, AUDIT_DETAILS_KEY_LEN,						\
			#resource".interfaces[%lu].details.securitylevel", interfaceid);			\
	zbx_snprintf(audit_key_authpassphrase, AUDIT_DETAILS_KEY_LEN,						\
			#resource".interfaces[%lu].details.authpassphrase", interfaceid);			\
	zbx_snprintf(audit_key_privpassphrase, AUDIT_DETAILS_KEY_LEN,						\
			#resource".interfaces[%lu].details.privpassphrase", interfaceid);			\
	zbx_snprintf(audit_key_authprotocol, AUDIT_DETAILS_KEY_LEN,						\
			#resource".interfaces[%lu].details.authprotocol", interfaceid);				\
	zbx_snprintf(audit_key_privprotocol, AUDIT_DETAILS_KEY_LEN,						\
			#resource".interfaces[%lu].details.privprotocol", interfaceid);				\
	zbx_snprintf(audit_key_contextname, AUDIT_DETAILS_KEY_LEN,						\
			#resource".interfaces[%lu].details.contextname", interfaceid);				\

#define PREPARE_AUDIT_SNMP_INTERFACE(resource)									\
void	zbx_audit_##resource##_update_json_add_snmp_interface(zbx_uint64_t hostid, zbx_uint64_t version,	\
		zbx_uint64_t bulk, const char *community, const char *securityname, zbx_uint64_t securitylevel,	\
		const char *authpassphrase, const char *privpassphrase, zbx_uint64_t authprotocol,		\
		zbx_uint64_t privprotocol, const char *contextname, zbx_uint64_t interfaceid)			\
{														\
PREPARE_UPDATE_JSON_SNMP_INTERFACE_OP(resource)									\
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
void	zbx_audit_##resource##_update_json_update_snmp_interface(zbx_uint64_t hostid, zbx_uint64_t version_old,	\
		zbx_uint64_t version_new, zbx_uint64_t bulk_old,  zbx_uint64_t bulk_new,			\
		const char *community_old, const char *community_new, const char *securityname_old,		\
		const char *securityname_new, zbx_uint64_t securitylevel_old, zbx_uint64_t securitylevel_new,	\
		const char *authpassphrase_old, const char *authpassphrase_new, const char *privpassphrase_old,	\
		const char *privpassphrase_new, zbx_uint64_t authprotocol_old, zbx_uint64_t authprotocol_new,	\
		zbx_uint64_t privprotocol_old, zbx_uint64_t privprotocol_new, const char *contextname_old,	\
		const char *contextname_new, zbx_uint64_t interfaceid)						\
{														\
PREPARE_UPDATE_JSON_SNMP_INTERFACE_OP(resource)									\
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

PREPARE_AUDIT_SNMP_INTERFACE(host)
PREPARE_AUDIT_SNMP_INTERFACE(host_prototype)

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

#define PREPARE_AUDIT_HOST_INTERFACE(resource, interface_resource, type1, type2)				\
void	zbx_audit_##resource##_update_json_update_interface_##interface_resource(zbx_uint64_t hostid,		\
		zbx_uint64_t interfaceid, type1 interface_resource##_old, type1 interface_resource##_new)	\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(buf, sizeof(buf), #resource".interfaces[%lu]."#interface_resource, interfaceid);		\
	zbx_audit_update_json_update_##type2(hostid, buf, interface_resource##_old, interface_resource##_new);	\
}														\

#define	PREPARE_AUDIT_HOST(resource, audit_resource_flag)							\
void	zbx_audit_##resource##_create_entry(int audit_action, zbx_uint64_t hostid, const char *name)		\
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
	}													\
}														\
														\
void	zbx_audit_##resource##_update_json_add_interfaces(zbx_uint64_t hostid, zbx_uint64_t interfaceid,	\
		zbx_uint64_t main_, zbx_uint64_t type, zbx_uint64_t useip, const char *ip, const char *dns,	\
		int port)											\
{														\
	char	audit_key_main[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],			\
		audit_key_useip[AUDIT_DETAILS_KEY_LEN], audit_key_ip[AUDIT_DETAILS_KEY_LEN],			\
		audit_key_dns[AUDIT_DETAILS_KEY_LEN], audit_key_port[AUDIT_DETAILS_KEY_LEN];			\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(audit_key_main,  AUDIT_DETAILS_KEY_LEN, #resource".interfaces[%lu].main", interfaceid);	\
	zbx_snprintf(audit_key_type,  AUDIT_DETAILS_KEY_LEN, #resource".interfaces[%lu].type", interfaceid);	\
	zbx_snprintf(audit_key_useip, AUDIT_DETAILS_KEY_LEN, #resource".interfaces[%lu].useip", interfaceid);	\
	zbx_snprintf(audit_key_ip,    AUDIT_DETAILS_KEY_LEN, #resource".interfaces[%lu].ip", interfaceid);	\
	zbx_snprintf(audit_key_dns,   AUDIT_DETAILS_KEY_LEN, #resource".interfaces[%lu].dns", interfaceid);	\
	zbx_snprintf(audit_key_port,  AUDIT_DETAILS_KEY_LEN, #resource".interfaces[%lu].port", interfaceid);	\
														\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_main, main_);		\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_type, type);		\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_useip, useip);		\
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_ip, ip);		\
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_dns, dns);		\
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_port, port);		\
}														\
														\
PREPARE_AUDIT_HOST_INTERFACE(resource, useip, zbx_uint64_t, uint64)						\
PREPARE_AUDIT_HOST_INTERFACE(resource, main, zbx_uint64_t, uint64)						\
PREPARE_AUDIT_HOST_INTERFACE(resource, type, zbx_uint64_t, uint64)						\
PREPARE_AUDIT_HOST_INTERFACE(resource, ip, const char*, string)							\
PREPARE_AUDIT_HOST_INTERFACE(resource, dns, const char*, string)						\
PREPARE_AUDIT_HOST_INTERFACE(resource, port, int, int)								\
/* snmp */													\
PREPARE_AUDIT_HOST_INTERFACE(resource, version, zbx_uint64_t, uint64)						\
PREPARE_AUDIT_HOST_INTERFACE(resource, bulk, zbx_uint64_t, uint64)						\
PREPARE_AUDIT_HOST_INTERFACE(resource, community, const char*, string)						\
PREPARE_AUDIT_HOST_INTERFACE(resource, securityname, const char*, string)					\
PREPARE_AUDIT_HOST_INTERFACE(resource, securitylevel, int, int)							\
PREPARE_AUDIT_HOST_INTERFACE(resource, authpassphrase, const char*, string)					\
PREPARE_AUDIT_HOST_INTERFACE(resource, privpassphrase, const char*, string)					\
PREPARE_AUDIT_HOST_INTERFACE(resource, authprotocol, zbx_uint64_t, uint64)					\
PREPARE_AUDIT_HOST_INTERFACE(resource, privprotocol, zbx_uint64_t, uint64)					\
PREPARE_AUDIT_HOST_INTERFACE(resource, contextname, const char*, string)					\

PREPARE_AUDIT_HOST(host, AUDIT_RESOURCE_HOST)
PREPARE_AUDIT_HOST(host_prototype, AUDIT_RESOURCE_HOST_PROTOTYPE)
#undef PREPARE_AUDIT_HOST
#undef PREPARE_AUDIT_HOST_INTERFACE

#define PREPARE_AUDIT_HOST_UPDATE(resource, type1, type2)							\
void	zbx_audit_host_update_json_update_##resource(zbx_uint64_t hostid, type1 old_##resource,			\
		type1 new_##resource)										\
{														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_audit_update_json_update_##type2(hostid, "host."#resource, old_##resource, new_##resource);		\
}

PREPARE_AUDIT_HOST_UPDATE(host, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(name, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(proxy_hostid, zbx_uint64_t, uint64)
PREPARE_AUDIT_HOST_UPDATE(ipmi_authtype, int, int)
PREPARE_AUDIT_HOST_UPDATE(ipmi_privilege, int, int)
PREPARE_AUDIT_HOST_UPDATE(ipmi_username, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(ipmi_password, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(tls_connect, int, int)
PREPARE_AUDIT_HOST_UPDATE(tls_accept, int, int)
PREPARE_AUDIT_HOST_UPDATE(tls_issuer, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(tls_subject, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(tls_psk_identity, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(tls_psk, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(custom_interfaces, int, int)
#undef PREPARE_AUDIT_HOST_UPDATE

void	zbx_audit_hostgroup_update_json_attach(zbx_uint64_t hostid, zbx_uint64_t hostgroupid, zbx_uint64_t groupid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "host.groups[%lu]", hostgroupid);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ATTACH, buf, groupid);
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
		zbx_snprintf(buf, sizeof(buf), "host.groups[%lu]", hostgroupids->values[i]);
		zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_DETACH, buf,
				groupids->values[i]);
	}
}

void	zbx_audit_host_del(zbx_uint64_t hostid, const char *hostname)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_host_create_entry(AUDIT_ACTION_DELETE, hostid, hostname);
}

void	zbx_audit_host_update_json_add_details(zbx_uint64_t hostid, const char *host, zbx_uint64_t proxy_hostid,
		int ipmi_authtype, int ipmi_privilege, const char *ipmi_username, const char *ipmi_password,
		int status, int flags, int tls_connect, int tls_accept, const char *tls_issuer, const char *tls_subject,
		const char *tls_psk_identity, const char *tls_psk, int custom_interfaces)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.host", host);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, "host.proxy_hostid", proxy_hostid);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.ipmi_authtype", ipmi_authtype);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.ipmi_privilege", ipmi_privilege);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.ipmi_username", ipmi_username);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.ipmi_password", ipmi_password);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.status", status);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.flags", flags);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.tls_connect", tls_connect);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.tls_accept", tls_accept);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.tls_issuer", tls_issuer);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.tls_subject", tls_subject);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.tls_psk_identity",
			tls_psk_identity);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "host.tls_psk", tls_psk);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "host.custom_interfaces", custom_interfaces);
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

	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ATTACH, "hostprototype.templateid",
			templateid);
	zbx_audit_update_json_append_string(hostid, AUDIT_DETAILS_ACTION_ADD, "hostprototype.name", name);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "hostprototype.status", status);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "hostprototype.discover", discover);
	zbx_audit_update_json_append_int(hostid, AUDIT_DETAILS_ACTION_ADD, "hostprototype.custom_interfaces",
			custom_interfaces);
}


void	zbx_audit_host_prototype_update_json_add_templateid(zbx_uint64_t hostid, zbx_uint64_t templateid)
{
	RETURN_IF_AUDIT_OFF();

	zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ATTACH, "hostprototype.templateid",
			templateid);
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

void	zbx_audit_host_prototype_update_json_add_group_details(zbx_uint64_t hostid, const char* name,
		zbx_uint64_t groupid, zbx_uint64_t templateid)
{
	char	audit_key_operator[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	if (0 != strlen(name))
	{
		zbx_snprintf(audit_key_operator, AUDIT_DETAILS_KEY_LEN, "hostprototype.groupPrototypes[%s]", name);
		zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ADD, audit_key_operator,
				templateid);
	}
	else if (0 != groupid)
	{
		zbx_snprintf(audit_key_operator, AUDIT_DETAILS_KEY_LEN, "hostprototype.groupLinks[%lu]", groupid);
		zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ATTACH, audit_key_operator,
				templateid);
	}
}

void	zbx_audit_host_prototype_update_json_update_group_links(zbx_uint64_t hostid, zbx_uint64_t groupid,
		zbx_uint64_t templateid_old, zbx_uint64_t templateid_new)
{
	char	audit_key_operator[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();
	zbx_snprintf(audit_key_operator, AUDIT_DETAILS_KEY_LEN, "hostprototype.groupLinks[%lu]", groupid);

	zbx_audit_update_json_update_uint64(hostid, audit_key_operator, templateid_old, templateid_new);
}

#define PREPARE_AUDIT_TEMPLATE_OP(op1, op2)									\
void	zbx_audit_host_update_json_##op1##_parent_template(zbx_uint64_t hostid, zbx_uint64_t templateid)	\
{														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_audit_update_json_append_uint64(hostid, op2, "host.templates", templateid);				\
}														\

PREPARE_AUDIT_TEMPLATE_OP(attach, AUDIT_DETAILS_ACTION_ATTACH)
PREPARE_AUDIT_TEMPLATE_OP(detach, AUDIT_DETAILS_ACTION_DETACH)

void	zbx_audit_host_prototype_update_json_add_templates(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids)
{
	int	i;

	RETURN_IF_AUDIT_OFF();

	for (i = 0; i < templateids->values_num; i++)
	{
		zbx_audit_update_json_append_uint64(hostid, AUDIT_DETAILS_ACTION_ATTACH, "hostprototype.templates",
				templateids->values[i]);
	}
}
