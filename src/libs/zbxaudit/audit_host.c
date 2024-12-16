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

#include "audit/zbxaudit_host.h"

#include "audit/zbxaudit.h"
#include "audit.h"

#include "zbxalgo.h"

#define PREPARE_UPDATE_JSON_SNMP_INTERFACE_OP(auditentry)							\
	char	audit_key_version[AUDIT_DETAILS_KEY_LEN], audit_key_bulk[AUDIT_DETAILS_KEY_LEN],		\
		audit_key_community[AUDIT_DETAILS_KEY_LEN], audit_key_securityname[AUDIT_DETAILS_KEY_LEN],	\
		audit_key_securitylevel[AUDIT_DETAILS_KEY_LEN],							\
		audit_key_authpassphrase[AUDIT_DETAILS_KEY_LEN],						\
		audit_key_privpassphrase[AUDIT_DETAILS_KEY_LEN], audit_key_authprotocol[AUDIT_DETAILS_KEY_LEN],	\
		audit_key_privprotocol[AUDIT_DETAILS_KEY_LEN], audit_key_contextname[AUDIT_DETAILS_KEY_LEN],	\
		audit_key[AUDIT_DETAILS_KEY_LEN];								\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
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
void	zbx_audit_##funcname##_update_json_add_snmp_interface(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t version, zbx_uint64_t bulk, const char *community, const char *securityname,	\
		zbx_uint64_t securitylevel, const char *authpassphrase, const char *privpassphrase,		\
		zbx_uint64_t authprotocol, zbx_uint64_t privprotocol, const char *contextname,			\
		zbx_uint64_t interfaceid)									\
{														\
PREPARE_UPDATE_JSON_SNMP_INTERFACE_OP(auditentry)								\
	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);	\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_version,	\
			version, "interface_snmp", "version");							\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_bulk,	\
			bulk, "interface_snmp", "bulk");							\
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_community, community, "interface_snmp", "community");				\
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_securityname, securityname, "interface_snmp", "securityname");		\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_securitylevel, securitylevel, "interface_snmp", "securitylevel");		\
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_authpassphrase, authpassphrase, "interface_snmp", "authpassphrase");		\
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_privpassphrase, privpassphrase, "interface_snmp", "privpassphrase");		\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_authprotocol, authprotocol, "interface_snmp", "authprotocol");		\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_privprotocol, privprotocol, "interface_snmp", "privprotocol");		\
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_contextname, contextname, "interface_snmp", "contextname");			\
}														\
														\
void	zbx_audit_##funcname##_update_json_update_snmp_interface(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t version_old, zbx_uint64_t version_new, zbx_uint64_t bulk_old,			\
		zbx_uint64_t bulk_new, const char *community_old, const char *community_new,			\
		const char *securityname_old, const char *securityname_new, zbx_uint64_t securitylevel_old,	\
		zbx_uint64_t securitylevel_new, const char *authpassphrase_old, const char *authpassphrase_new,	\
		const char *privpassphrase_old, const char *privpassphrase_new, zbx_uint64_t authprotocol_old,	\
		zbx_uint64_t authprotocol_new, zbx_uint64_t privprotocol_old, zbx_uint64_t privprotocol_new,	\
		const char *contextname_old, const char *contextname_new, zbx_uint64_t interfaceid)		\
{														\
PREPARE_UPDATE_JSON_SNMP_INTERFACE_OP(funcname)									\
	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key);	\
	zbx_audit_update_json_update_uint64(hostid, AUDIT_HOST_ID, audit_key_version, version_old, version_new);\
	zbx_audit_update_json_update_uint64(hostid, AUDIT_HOST_ID, audit_key_bulk, bulk_old, bulk_new);		\
	zbx_audit_update_json_update_string(hostid, AUDIT_HOST_ID, audit_key_community, community_old,		\
			community_new);										\
	zbx_audit_update_json_update_string(hostid, AUDIT_HOST_ID, audit_key_securityname, securityname_old,	\
			securityname_new);									\
	zbx_audit_update_json_update_uint64(hostid, AUDIT_HOST_ID, audit_key_securitylevel, securitylevel_old,	\
			securitylevel_new);									\
	zbx_audit_update_json_update_string(hostid, AUDIT_HOST_ID, audit_key_authpassphrase, authpassphrase_old,\
			authpassphrase_new);									\
	zbx_audit_update_json_update_string(hostid, AUDIT_HOST_ID, audit_key_privpassphrase, privpassphrase_old,\
			privpassphrase_new);									\
	zbx_audit_update_json_update_uint64(hostid, AUDIT_HOST_ID, audit_key_authprotocol, authprotocol_old,	\
			authprotocol_new);									\
	zbx_audit_update_json_update_uint64(hostid, AUDIT_HOST_ID, audit_key_privprotocol, privprotocol_old,	\
			privprotocol_new);									\
	zbx_audit_update_json_update_string(hostid, AUDIT_HOST_ID, audit_key_contextname, contextname_old,	\
			contextname_new);									\
}														\

PREPARE_AUDIT_SNMP_INTERFACE(host, host)
PREPARE_AUDIT_SNMP_INTERFACE(host_prototype, hostprototype)

void	zbx_audit_host_update_json_add_monitoring_and_hostname_and_inventory_mode(int audit_context_mode,
		zbx_uint64_t hostid, unsigned char monitored_by, zbx_uint64_t proxyid, zbx_uint64_t proxy_groupid,
		const char *hostname, int inventory_mode)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

#define	AUDIT_TABLE_NAME	"hosts"

	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.monitored_by",
			(int)monitored_by, AUDIT_TABLE_NAME, "monitored_by");
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.proxyid",
			proxyid, AUDIT_TABLE_NAME, "proxyid");
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.proxy_groupid",
			proxy_groupid, AUDIT_TABLE_NAME, "proxy_groupid");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.host", hostname,
			AUDIT_TABLE_NAME, "host");

	/*
	 * Currently there are 3 valid values for inventory_mode: HOST_INVENTORY_DISABLED (-1), the default value
	 * HOST_INVENTORY_MANUAL (0), and HOST_INVENTORY_AUTOMATIC (1). In database we write only 2 of them:
	 * HOST_INVENTORY_MANUAL and HOST_INVENTORY_AUTOMATIC. From the other side in auditlog we must write all of
	 * them except the default, i.e. HOST_INVENTORY_DISABLED and HOST_INVENTORY_AUTOMATIC.
	 */
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.inventory_mode",
			inventory_mode, "host_inventory", "inventory_mode");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_host_update_json_add_tls_and_psk(int audit_context_mode, zbx_uint64_t hostid, int tls_connect,
		int tls_accept)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

#define AUDIT_TABLE_NAME	"hosts"
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.tls_connect",
			tls_connect, AUDIT_TABLE_NAME, "tls_connect");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.tls_accept", tls_accept,
			AUDIT_TABLE_NAME, "tls_accept");
	zbx_audit_update_json_append_string_secret(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,
			"host.tls_psk_identity");
	zbx_audit_update_json_append_string_secret(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.tls_psk");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_host_update_json_add_inventory_mode(int audit_context_mode, zbx_uint64_t hostid, int inventory_mode)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.inventory_mode",
			inventory_mode, "host_inventory", "inventory_mode");
}

void	zbx_audit_host_update_json_update_inventory_mode(int audit_context_mode, zbx_uint64_t hostid,
		int inventory_mode_old, int inventory_mode_new)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_update_json_update_int(hostid, AUDIT_HOST_ID, "host.inventory_mode", inventory_mode_old,
			inventory_mode_new);
}

void	zbx_audit_host_update_json_update_host_status(int audit_context_mode, zbx_uint64_t hostid, int host_status_old,
		int host_status_new)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_update_json_update_int(hostid, AUDIT_HOST_ID, "host.status", host_status_old, host_status_new);
}

#define PREPARE_AUDIT_HOST_INTERFACE(funcname, auditentry, interface_resource, type1, type2)			\
void	zbx_audit_##funcname##_update_json_update_interface_##interface_resource(int audit_context_mode,	\
		zbx_uint64_t hostid, zbx_uint64_t interfaceid, type1 interface_resource##_old,			\
		type1 interface_resource##_new)									\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_snprintf(buf, sizeof(buf), #auditentry".interfaces[" ZBX_FS_UI64 "].details."#interface_resource,	\
			interfaceid);										\
	zbx_audit_update_json_update_##type2(hostid, AUDIT_HOST_ID, buf, interface_resource##_old,		\
			interface_resource##_new);								\
}														\

#define	PREPARE_AUDIT_HOST(funcname, auditentry, audit_resource_flag)						\
void	zbx_audit_##funcname##_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t hostid,	\
		const char *name)										\
{														\
	zbx_audit_entry_t	local_audit_host_entry, **found_audit_host_entry;				\
	zbx_audit_entry_t	*local_audit_host_entry_x = &local_audit_host_entry;				\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	local_audit_host_entry.id = hostid;									\
	local_audit_host_entry.cuid = NULL;									\
	local_audit_host_entry.id_table = AUDIT_HOST_ID;							\
	found_audit_host_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),		\
			&(local_audit_host_entry_x));								\
	if (NULL == found_audit_host_entry)									\
	{													\
		zbx_audit_entry_t	*local_audit_host_entry_insert;						\
														\
		local_audit_host_entry_insert = zbx_audit_entry_init(hostid, AUDIT_HOST_ID, name, audit_action, \
				audit_resource_flag);								\
		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_host_entry_insert,			\
				sizeof(local_audit_host_entry_insert));						\
														\
		if (ZBX_AUDIT_ACTION_ADD == audit_action)							\
		{												\
			zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,	\
					#auditentry".hostid", hostid, "hosts", "hostid");			\
		}												\
	}													\
}														\
														\
void	zbx_audit_##funcname##_update_json_add_interfaces(int audit_context_mode, zbx_uint64_t hostid,		\
		zbx_uint64_t interfaceid, zbx_uint64_t main_, zbx_uint64_t type, zbx_uint64_t useip,		\
		const char *ip, const char *dns, int port)							\
{														\
	char	audit_key_main[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],			\
		audit_key_useip[AUDIT_DETAILS_KEY_LEN], audit_key_ip[AUDIT_DETAILS_KEY_LEN],			\
		audit_key_dns[AUDIT_DETAILS_KEY_LEN], audit_key_port[AUDIT_DETAILS_KEY_LEN],			\
		audit_key[AUDIT_DETAILS_KEY_LEN];								\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
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
	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);	\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_main,	\
			main_, "interface", "main");								\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_type,	\
			type, "interface", "type");								\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_useip,	\
			useip, "interface", "useip");								\
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_ip, ip,	\
			"interface", "ip");									\
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_dns, dns,\
			"interface", "dns");									\
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_port, port,	\
			"interface", "port");									\
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

PREPARE_AUDIT_HOST(host, host, ZBX_AUDIT_RESOURCE_HOST)
PREPARE_AUDIT_HOST(host_prototype, hostprototype, ZBX_AUDIT_RESOURCE_HOST_PROTOTYPE)
#undef PREPARE_AUDIT_HOST
#undef PREPARE_AUDIT_HOST_INTERFACE

#define PREPARE_AUDIT_HOST_UPDATE(resource, type1, type2)							\
void	zbx_audit_host_update_json_update_##resource(int audit_context_mode, zbx_uint64_t hostid,		\
		type1 old_##resource, type1 new_##resource)							\
{														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_audit_update_json_update_##type2(hostid, AUDIT_HOST_ID, "host."#resource, old_##resource,		\
			new_##resource);									\
}

PREPARE_AUDIT_HOST_UPDATE(host, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(name, const char*, string)
PREPARE_AUDIT_HOST_UPDATE(proxyid, zbx_uint64_t, uint64)
PREPARE_AUDIT_HOST_UPDATE(proxy_groupid, zbx_uint64_t, uint64)
PREPARE_AUDIT_HOST_UPDATE(monitored_by, int, int)
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

void	zbx_audit_host_update_json_delete_interface(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t interfaceid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "host.interfaces[" ZBX_FS_UI64 "]", interfaceid);

	zbx_audit_update_json_delete(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_host_update_json_add_hostmacro(int audit_context_mode, zbx_uint64_t hostid, int audit_resource,
		zbx_uint64_t macroid, const char *macro, const char *value, const char *description, int type,
		int automatic)
{
#define	AUDIT_TABLE_NAME	"hostmacro"
	char		audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_hostmacroid[AUDIT_DETAILS_KEY_LEN],
			audit_key_name[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN],
			audit_key_description[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],
			audit_key_automatic[AUDIT_DETAILS_KEY_LEN];
	const char	*key;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	key = (ZBX_AUDIT_RESOURCE_HOST == audit_resource) ? "host.macros" : "hostprototype.macros";

	zbx_snprintf(audit_key, sizeof(audit_key), "%s[" ZBX_FS_UI64 "]", key, macroid);
	zbx_snprintf(audit_key_hostmacroid, sizeof(audit_key_hostmacroid), "%s[" ZBX_FS_UI64 "].hostmacroid", key,
			macroid);
	zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s[" ZBX_FS_UI64 "].macro", key, macroid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64 "].value", key, macroid);
	zbx_snprintf(audit_key_description, sizeof(audit_key_description),
			"%s[" ZBX_FS_UI64 "].description", key, macroid);
	zbx_snprintf(audit_key_type, sizeof(audit_key_type), "%s[" ZBX_FS_UI64 "].type", key, macroid);
	zbx_snprintf(audit_key_automatic, sizeof(audit_key_automatic), "%s[" ZBX_FS_UI64 "].automatic", key,
			macroid);

	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_hostmacroid, macroid, AUDIT_TABLE_NAME, "hostmacroid");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name,
			macro, AUDIT_TABLE_NAME, "macro");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value,
			value, AUDIT_TABLE_NAME, "value");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_description, description, AUDIT_TABLE_NAME, "description");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_type, type,
			AUDIT_TABLE_NAME, "type");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_automatic,
			automatic, AUDIT_TABLE_NAME, "automatic");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_host_update_json_update_hostmacro_create_entry(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "host.macros[" ZBX_FS_UI64 "]", hostmacroid);

	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

#define PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO(resource, type1, type2)						\
void	zbx_audit_host_update_json_update_hostmacro_##resource(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t hostmacroid, type1 old_##resource, type1 new_##resource)				\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_snprintf(buf, sizeof(buf), "host.macros[" ZBX_FS_UI64 "]."#resource, hostmacroid);			\
														\
	zbx_audit_update_json_update_##type2(hostid, AUDIT_HOST_ID, buf, old_##resource, new_##resource);	\
}														\

PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO(value, const char*, string)
PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO(description, const char*, string)
PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO(type, int, int)
#undef PREPARE_AUDIT_HOST_UPDATE_HOSTMACRO

void	zbx_audit_host_update_json_delete_hostmacro(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "host.macros[" ZBX_FS_UI64 "]", hostmacroid);

	zbx_audit_update_json_delete(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_hostgroup_update_json_add_group(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t hostgroupid,
		zbx_uint64_t groupid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_hostid[AUDIT_DETAILS_KEY_LEN],
		audit_key_groupid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "host.groups[" ZBX_FS_UI64 "]", hostgroupid);
	zbx_snprintf(audit_key_hostid, sizeof(audit_key_hostid), "host.groups[" ZBX_FS_UI64 "].hostid", hostgroupid);
	zbx_snprintf(audit_key_groupid, sizeof(audit_key_groupid), "host.groups[" ZBX_FS_UI64 "].groupid", hostgroupid);

#define	AUDIT_TABLE_NAME	"hosts_groups"
	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_hostid, hostid,
			AUDIT_TABLE_NAME, "hostid");
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_groupid, groupid,
			AUDIT_TABLE_NAME, "groupid");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_hostgroup_update_json_delete_group(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostgroupid, zbx_uint64_t groupid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "host.groups[" ZBX_FS_UI64 "]", hostgroupid);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key, groupid,
			NULL, NULL);
}

void	zbx_audit_host_hostgroup_delete(int audit_context_mode, zbx_uint64_t hostid, const char* hostname,
		zbx_vector_uint64_t *hostgroupids, zbx_vector_uint64_t *groupids)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_host_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_UPDATE, hostid, hostname);

	for (int i = 0; i < groupids->values_num; i++)
	{
		zbx_snprintf(buf, sizeof(buf), "host.groups[" ZBX_FS_UI64 "].groupid", hostgroupids->values[i]);
		zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_DELETE, buf,
				groupids->values[i], NULL, NULL);
	}
}

void	zbx_audit_host_del(int audit_context_mode, zbx_uint64_t hostid, const char *hostname)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_host_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_DELETE, hostid, hostname);
}

void	zbx_audit_host_update_json_add_details(int audit_context_mode, zbx_uint64_t hostid, const char *host,
		unsigned char monitored_by, zbx_uint64_t proxyid, zbx_uint64_t proxy_groupid, int ipmi_authtype,
		int ipmi_privilege, const char *ipmi_username, int status, int flags, int tls_connect, int tls_accept,
		const char *tls_issuer, const char *tls_subject, int custom_interfaces, int inventory_mode)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

#define	AUDIT_TABLE_NAME	"hosts"
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.host", host,
			AUDIT_TABLE_NAME, "host");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.monitored_by",
			monitored_by, AUDIT_TABLE_NAME, "monitored_by");
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.proxyid",
			proxyid, AUDIT_TABLE_NAME, "proxyid");
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.proxy_groupid",
			proxy_groupid, AUDIT_TABLE_NAME, "proxy_groupid");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.ipmi_authtype",
			ipmi_authtype, AUDIT_TABLE_NAME, "ipmi_authtype");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.ipmi_privilege",
			ipmi_privilege, AUDIT_TABLE_NAME, "ipmi_privilege");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.ipmi_username",
			ipmi_username, AUDIT_TABLE_NAME, "ipmi_username");
	zbx_audit_update_json_append_string_secret(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,
			"host.ipmi_password");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.status", status,
			AUDIT_TABLE_NAME, "status");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.flags", flags,
			AUDIT_TABLE_NAME, "flags");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.tls_issuer",
			tls_issuer, AUDIT_TABLE_NAME, "tls_issuer");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.tls_subject",
			tls_subject, AUDIT_TABLE_NAME, "tls_subject");

	zbx_audit_host_update_json_add_tls_and_psk(audit_context_mode, hostid, tls_connect, tls_accept);

	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.custom_interfaces",
			custom_interfaces, AUDIT_TABLE_NAME, "custom_interfaces");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.inventory_mode",
			inventory_mode, "host_inventory", "inventory_mode");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_host_update_json_add_tag(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* tag, const char* value, int automatic)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN], audit_key_automatic[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "host.tags[" ZBX_FS_UI64 "]", tagid);
	zbx_snprintf(audit_key_tag, sizeof(audit_key_tag), "host.tags[" ZBX_FS_UI64 "].tag", tagid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "host.tags[" ZBX_FS_UI64 "].value", tagid);
	zbx_snprintf(audit_key_automatic, sizeof(audit_key_automatic), "host.tags[" ZBX_FS_UI64 "].automatic", tagid);

#define	AUDIT_TABLE_NAME	"host_tag"
	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_tag, tag,
			AUDIT_TABLE_NAME, "tag");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_automatic,
			automatic, AUDIT_TABLE_NAME, "automatic");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_host_update_json_update_tag_create_entry(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t tagid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "host.tags[" ZBX_FS_UI64 "]", tagid);

	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

void	zbx_audit_host_update_json_update_tag_tag(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char *tag_old, const char *tag_new)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, AUDIT_DETAILS_KEY_LEN, "host.tags[" ZBX_FS_UI64 "].tag", tagid);

	zbx_audit_update_json_update_string(hostid, AUDIT_HOST_ID, buf, tag_old, tag_new);
}

void	zbx_audit_host_update_json_update_tag_value(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char *value_old, const char *value_new)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, AUDIT_DETAILS_KEY_LEN, "host.tags[" ZBX_FS_UI64 "].value", tagid);

	zbx_audit_update_json_update_string(hostid, AUDIT_HOST_ID, buf, value_old, value_new);
}

void	zbx_audit_host_update_json_update_tag_type(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		int automatic_old, int automatic_new)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, AUDIT_DETAILS_KEY_LEN, "host.tags[" ZBX_FS_UI64 "].automatic", tagid);

	zbx_audit_update_json_update_int(hostid, AUDIT_HOST_ID, buf, automatic_old, automatic_new);
}

void	zbx_audit_host_update_json_delete_tag(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, AUDIT_DETAILS_KEY_LEN, "host.tags[" ZBX_FS_UI64 "]", tagid);

	zbx_audit_update_json_delete(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_host_prototype_del(int audit_context_mode, zbx_uint64_t hostid, const char *hostname)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_host_prototype_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_DELETE, hostid, hostname);
}

void	zbx_audit_host_prototype_update_json_add_details(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t templateid, const char *name, int status, int discover, int custom_interfaces,
		int inventory_mode, const char *host)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

#define	AUDIT_TABLE_NAME	"hosts"
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "hostprototype.templateid",
			templateid, AUDIT_TABLE_NAME, "templateid");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "hostprototype.name", name,
			AUDIT_TABLE_NAME, "name");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "hostprototype.status",
			status, AUDIT_TABLE_NAME, "status");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "hostprototype.discover",
			discover, AUDIT_TABLE_NAME, "discover");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,
			"hostprototype.custom_interfaces", custom_interfaces, AUDIT_TABLE_NAME, "custom_interfaces");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,
			"hostprototype.inventory_mode", inventory_mode, "host_inventory", "inventory_mode");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,
			"hostprototype.host", host, AUDIT_TABLE_NAME, "host");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_host_prototype_update_json_update_templateid(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t templateid_orig, zbx_uint64_t templateid)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_update_json_update_uint64(hostid, AUDIT_HOST_ID, "hostprototype.templateid", templateid_orig,
			templateid);
}

#define PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(resource, type1, type2)						\
void	zbx_audit_host_prototype_update_json_update_##resource(int audit_context_mode, zbx_uint64_t hostid,	\
		type1 old_##resource, type1 new_##resource)							\
{														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_audit_update_json_update_##type2(hostid, AUDIT_HOST_ID, "hostprototype."#resource, old_##resource,	\
			new_##resource);									\
}														\

PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(name, const char*, string)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(status, int, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(discover, int, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(custom_interfaces, int, int)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE(inventory_mode, int, int)
#undef PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE

void	zbx_audit_host_prototype_update_json_add_group_details(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t group_prototypeid, const char *name, zbx_uint64_t groupid, zbx_uint64_t templateid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_hostid[AUDIT_DETAILS_KEY_LEN],
		audit_key_name[AUDIT_DETAILS_KEY_LEN], audit_key_groupid[AUDIT_DETAILS_KEY_LEN],
		audit_key_templateid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

#define	AUDIT_TABLE_NAME	"group_prototype"
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
		zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name,
				name, AUDIT_TABLE_NAME, "name");
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
		zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_groupid,
				groupid, AUDIT_TABLE_NAME, "groupid");
	}

	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_hostid, hostid,
			AUDIT_TABLE_NAME, "hostid");
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_templateid,
			templateid, AUDIT_TABLE_NAME, "templateid");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_host_prototype_update_json_update_group_details(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t group_prototypeid, const char* name, zbx_uint64_t groupid, zbx_uint64_t templateid_old,
		zbx_uint64_t templateid_new)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_templateid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	if (0 != strlen(name))
	{
		zbx_snprintf(audit_key, sizeof(audit_key), "hostprototype.groupPrototypes[" ZBX_FS_UI64 "]", groupid);
		zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), "hostprototype.groupPrototypes["
				ZBX_FS_UI64 "].templateid", group_prototypeid);
	}
	else if (0 != groupid)
	{
		zbx_snprintf(audit_key, sizeof(audit_key), "hostprototype.groupLinks[" ZBX_FS_UI64 "]", groupid);
		zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), "hostprototype.groupLinks[" ZBX_FS_UI64
				"].templateid", group_prototypeid);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;

		return;
	}

	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key);
	zbx_audit_update_json_update_uint64(hostid, AUDIT_HOST_ID, audit_key_templateid, templateid_old,
			templateid_new);
}

#define PREPARE_AUDIT_TEMPLATE_ADD(funcname, auditentry)							\
void	zbx_audit_##funcname##_update_json_add_parent_template(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t hosttemplateid, zbx_uint64_t templateid, int link_type)				\
{														\
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_templateid[AUDIT_DETAILS_KEY_LEN],			\
		audit_key_link_type[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_snprintf(audit_key, sizeof(audit_key), #auditentry".templates[" ZBX_FS_UI64 "]", hosttemplateid);	\
	zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), #auditentry".templates[" ZBX_FS_UI64	\
			"].templateid", hosttemplateid);							\
	zbx_snprintf(audit_key_link_type, sizeof(audit_key_link_type), #auditentry".templates[" ZBX_FS_UI64	\
			"].link_type", hosttemplateid);								\
														\
	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);	\
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_templateid, templateid, "hosts_templates", "templateid");			\
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD,			\
			audit_key_link_type, link_type, "hosts_templates", "link_type");			\
}														\

#define PREPARE_AUDIT_TEMPLATE_DELETE(funcname, auditentry)							\
void	zbx_audit_##funcname##_update_json_delete_parent_template(int audit_context_mode, zbx_uint64_t hostid,	\
		zbx_uint64_t hosttemplateid)									\
{														\
	char	audit_key_templateid[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), #auditentry".templates[" ZBX_FS_UI64	\
			"]", hosttemplateid);									\
														\
	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_DELETE,		\
			audit_key_templateid);									\
}														\

PREPARE_AUDIT_TEMPLATE_ADD(host, host)
PREPARE_AUDIT_TEMPLATE_DELETE(host, host)
PREPARE_AUDIT_TEMPLATE_ADD(host_prototype, hostprototype)
PREPARE_AUDIT_TEMPLATE_DELETE(host_prototype, hostprototype)

void	zbx_audit_host_prototype_update_json_delete_interface(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t interfaceid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "hostprototype.interfaces[" ZBX_FS_UI64 "]", interfaceid);

	zbx_audit_update_json_delete(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_host_prototype_update_json_update_hostmacro_create_entry(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "hostprototype.macros[" ZBX_FS_UI64 "]", hostmacroid);

	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

#define PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO(resource, type1, type2)					\
void	zbx_audit_host_prototype_update_json_update_hostmacro_##resource(int audit_context_mode,		\
		zbx_uint64_t hostid, zbx_uint64_t hostmacroid, type1 old_##resource, type1 new_##resource)	\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_snprintf(buf, sizeof(buf), "hostprototype.macros[" ZBX_FS_UI64 "]."#resource, hostmacroid);		\
														\
	zbx_audit_update_json_update_##type2(hostid, AUDIT_HOST_ID, buf, old_##resource, new_##resource);	\
}														\

PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO(value, const char*, string)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO(description, const char*, string)
PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO(type, int, int)
#undef PREPARE_AUDIT_HOST_PROTOTYPE_UPDATE_HOSTMACRO

void	zbx_audit_host_prototype_update_json_delete_hostmacro(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t hostmacroid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "hostprototype.macros[" ZBX_FS_UI64 "]", hostmacroid);

	zbx_audit_update_json_delete(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_host_prototype_update_json_add_tag(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid,
		const char* tag, const char* value, int automatic)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN], audit_key_automatic[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "hostprototype.tags[" ZBX_FS_UI64 "]", tagid);
	zbx_snprintf(audit_key_tag, sizeof(audit_key_tag), "hostprototype.tags[" ZBX_FS_UI64 "].tag", tagid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "hostprototype.tags[" ZBX_FS_UI64 "].value", tagid);
	zbx_snprintf(audit_key_automatic, sizeof(audit_key_automatic), "hostprototype.tags[" ZBX_FS_UI64 "].automatic",
			tagid);

#define	AUDIT_TABLE_NAME	"host_tag"
	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_tag, tag,
			AUDIT_TABLE_NAME, "tag");
	zbx_audit_update_json_append_string(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
	zbx_audit_update_json_append_int(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_automatic,
			automatic, AUDIT_TABLE_NAME, "automatic");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_host_prototype_update_json_update_tag_create_entry(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t tagid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "hostprototype.tags[" ZBX_FS_UI64 "]", tagid);

	zbx_audit_update_json_append_no_value(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

void	zbx_audit_host_prototype_update_json_update_tag_tag(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t tagid, const char* tag_old, const char *tag_new)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "hostprototype.tags[" ZBX_FS_UI64 "].tag", tagid);

	zbx_audit_update_json_update_string(hostid, AUDIT_HOST_ID, buf, tag_old, tag_new);
}

void	zbx_audit_host_prototype_update_json_update_tag_value(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t tagid, const char* value_old, const char *value_new)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "hostprototype.tags[" ZBX_FS_UI64 "].value", tagid);

	zbx_audit_update_json_update_string(hostid, AUDIT_HOST_ID, buf, value_old, value_new);
}

void	zbx_audit_host_prototype_update_json_delete_tag(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t tagid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "hostprototype.tags[" ZBX_FS_UI64 "]", tagid);

	zbx_audit_update_json_delete(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_host_group_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t groupid,
		const char *name)
{
	zbx_audit_entry_t	local_audit_group_entry, **found_audit_group_entry;
	zbx_audit_entry_t	*local_audit_group_entry_x = &local_audit_group_entry;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	local_audit_group_entry.id = groupid;
	local_audit_group_entry.cuid = NULL;
	local_audit_group_entry.id_table = AUDIT_HOSTGRP_ID;

	found_audit_group_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),
			&(local_audit_group_entry_x));
	if (NULL == found_audit_group_entry)
	{
		zbx_audit_entry_t	*local_audit_group_entry_insert;

		local_audit_group_entry_insert = zbx_audit_entry_init(groupid, AUDIT_HOSTGRP_ID, name, audit_action,
				ZBX_AUDIT_RESOURCE_HOST_GROUP);
		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_group_entry_insert,
				sizeof(local_audit_group_entry_insert));
	}
}

void	zbx_audit_host_group_del(int audit_context_mode, zbx_uint64_t groupid, const char *name)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_host_group_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_DELETE, groupid, name);
}

void	zbx_audit_host_group_update_json_add_details(int audit_context_mode, zbx_uint64_t groupid, const char *name,
		int flags)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

#define	AUDIT_TABLE_NAME	"hstgrp"
	zbx_audit_update_json_append_string(groupid, AUDIT_HOSTGRP_ID, AUDIT_DETAILS_ACTION_ADD, "hostgroup.name", name,
			AUDIT_TABLE_NAME, "name");
	zbx_audit_update_json_append_int(groupid, AUDIT_HOSTGRP_ID, AUDIT_DETAILS_ACTION_ADD, "hostgroup.flags", flags,
			AUDIT_TABLE_NAME, "flags");
	zbx_audit_update_json_append_uint64(groupid, AUDIT_HOSTGRP_ID, AUDIT_DETAILS_ACTION_ADD, "hostgroup.groupid",
			groupid, AUDIT_TABLE_NAME, "groupid");

#undef AUDIT_TABLE_NAME
}

#define PREPARE_AUDIT_HOST_GROUP_UPDATE(resource, type1, type2)							\
void	zbx_audit_host_group_update_json_update_##resource(int audit_context_mode, zbx_uint64_t groupid,	\
		type1 old_##resource, type1 new_##resource)							\
{														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_audit_update_json_update_##type2(groupid, AUDIT_HOSTGRP_ID, "hostgroup."#resource, old_##resource,	\
			new_##resource);									\
}														\

PREPARE_AUDIT_HOST_GROUP_UPDATE(name, const char*, string)

#undef PREPARE_AUDIT_HOST_GROUP_UPDATE

void	zbx_audit_host_update_json_add_proxyid(int audit_context_mode, zbx_uint64_t hostid, zbx_uint64_t proxyid)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "host.proxyid",
			proxyid, "hosts", "proxyid");
}

void	zbx_audit_host_prototype_update_json_add_lldruleid(int audit_context_mode, zbx_uint64_t hostid,
		zbx_uint64_t lldrule_id)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	/* 'ruleid' column does not exist for hosts table, but there is no default value, so it is fine */
	zbx_audit_update_json_append_uint64(hostid, AUDIT_HOST_ID, AUDIT_DETAILS_ACTION_ADD, "hostprototype.ruleid",
			lldrule_id, NULL, NULL);
}
