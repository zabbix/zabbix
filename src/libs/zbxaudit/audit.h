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

#ifndef ZABBIX_AUDIT_H
#define ZABBIX_AUDIT_H

#include "common.h"

#define AUDIT_ACTION_ADD		0
#define AUDIT_ACTION_UPDATE		1
#define AUDIT_ACTION_DELETE		2
#define AUDIT_ACTION_EXECUTE		7

#define AUDIT_DETAILS_ACTION_ADD	"add"
#define AUDIT_DETAILS_ACTION_ATTACH	"attach"
#define AUDIT_DETAILS_ACTION_DETACH	"detach"

#define AUDIT_SECRET_MASK		"******"

#define	AUDIT_DETAILS_KEY_LEN		100

#define AUDIT_RESOURCE_HOST		4
#define AUDIT_RESOURCE_HOST_PROTOTYPE	37
#define AUDIT_RESOURCE_SCRIPT		25

#define RETURN_IF_AUDIT_OFF()					\
	if (ZBX_AUDITLOG_ENABLED != zbx_get_audit_mode())	\
		return						\

int		zbx_get_audit_mode(void);
zbx_hashset_t*	zbx_get_audit_hashset(void);

typedef struct zbx_audit_entry
{
	zbx_uint64_t	id;
	char		*name;
	struct zbx_json	details_json;
	int		audit_action;
	int		resource_type;
} zbx_audit_entry_t;

int	zbx_auditlog_global_script(unsigned char script_type, unsigned char script_execute_on,
		const char *script_command_orig, zbx_uint64_t hostid, const char *hostname, zbx_uint64_t eventid,
		zbx_uint64_t proxy_hostid, zbx_uint64_t userid, const char *username, const char *clientip,
		const char *output, const char *error);

void	zbx_audit_init(int audit_mode_set);
void	zbx_audit_flush(void);
void	zbx_audit_update_json_append_string(const zbx_uint64_t id, const char *audit_op, const char *key,
		const char *value);
void	zbx_audit_update_json_append_uint64(const zbx_uint64_t id, const char *audit_op, const char *key,
		uint64_t value);
void	zbx_audit_update_json_append_int(const zbx_uint64_t id, const char *audit_op, const char *key, int value);
void	zbx_audit_update_json_update_string(const zbx_uint64_t id, const char *key, const char *value_old,
		const char *value_new);
void	zbx_audit_update_json_update_uint64(const zbx_uint64_t id, const char *key, uint64_t value_old,
		uint64_t value_new);
void	zbx_audit_update_json_update_int(const zbx_uint64_t id, const char *key, int value_old, int value_new);

/* host audit start */
void	zbx_audit_host_update_json_add_proxy_hostid_and_hostname(zbx_uint64_t hostid,
		zbx_uint64_t proxy_hostid, const char *hostname);
void	zbx_audit_host_update_json_add_tls_and_psk(zbx_uint64_t hostid, int tls_connect,
		int tls_accept, const char *psk_identity, const char *psk);
void	zbx_audit_host_update_json_add_inventory_mode(zbx_uint64_t hostid, int inventory_mode);
void	zbx_audit_host_update_json_update_inventory_mode(zbx_uint64_t hostid, int inventory_mode_old,
		int inventory_mode_new);
void	zbx_audit_host_update_json_update_host_status(zbx_uint64_t hostid, int host_status_old,
		int host_status_new);
void	zbx_audit_host_create_entry(int audit_action, zbx_uint64_t hostid, const char *name);
void	zbx_audit_host_prototype_create_entry(int audit_action, zbx_uint64_t hostid, const char *name);
void	zbx_audit_host_update_json_add_interfaces(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t main_, zbx_uint64_t type, zbx_uint64_t useip, const char *ip, const char *dns,
		int port);
void	zbx_audit_host_prototype_update_json_add_interfaces(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t main_, zbx_uint64_t type, zbx_uint64_t useip, const char *ip, const char *dns,
		int port);
void	zbx_audit_hostgroup_update_json_attach(zbx_uint64_t hostid, zbx_uint64_t hostgroupid, zbx_uint64_t groupid);
void	zbx_audit_host_hostgroup_delete(zbx_uint64_t hostid, const char* hostname, zbx_vector_uint64_t *hostgroupids,
		zbx_vector_uint64_t *groupids);
void	zbx_audit_host_del(zbx_uint64_t hostid, const char *hostname);
void	zbx_audit_host_prototype_del(zbx_uint64_t hostid, const char *hostname);

void	zbx_audit_host_update_json_update_interface_useip(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t useip_old, zbx_uint64_t useip_new);
void	zbx_audit_host_update_json_update_interface_ip(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		const char *ip_old, const char *ip_new);
void	zbx_audit_host_update_json_update_interface_dns(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		const char *dns_old, const char *dns_new);
void	zbx_audit_host_update_json_update_interface_port(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		int port_old, int port_new);

void	zbx_audit_host_prototype_update_json_update_interface_useip(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t useip_old, zbx_uint64_t useip_new);
void	zbx_audit_host_prototype_update_json_update_interface_ip(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		const char *ip_old, const char *ip_new);
void	zbx_audit_host_prototype_update_json_update_interface_dns(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		const char *dns_old, const char *dns_new);
void	zbx_audit_host_prototype_update_json_update_interface_port(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		int port_old, int port_new);

void	zbx_audit_host_prototype_update_json_update_interface_main(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t main_old, zbx_uint64_t main_new);
void	zbx_audit_host_prototype_update_json_update_interface_type(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t type_old, zbx_uint64_t type_new);

void	zbx_audit_host_prototype_update_json_update_interface_version(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t version_old, zbx_uint64_t version_new);
void	zbx_audit_host_prototype_update_json_update_interface_bulk(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t bulk_old, zbx_uint64_t bulk_new);
void	zbx_audit_host_prototype_update_json_update_interface_community(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		const char *community_old, const char *community_new);
void	zbx_audit_host_prototype_update_json_update_interface_securityname(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		const char *securityname_old, const char *securityname_new);
void	zbx_audit_host_prototype_update_json_update_interface_securitylevel(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		int securitylevel_old, int securitylevel_new);
void	zbx_audit_host_prototype_update_json_update_interface_authpassphrase(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		const char *authpassphrase_old, const char *authpassphrase_new);
void	zbx_audit_host_prototype_update_json_update_interface_privpassphrase(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		const char *privpassphrase_old, const char *privpassphrase_new);
void	zbx_audit_host_prototype_update_json_update_interface_authprotocol(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t authprotocol_old, zbx_uint64_t authprotocol_new);
void	zbx_audit_host_prototype_update_json_update_interface_privprotocol(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		zbx_uint64_t privprotocol_old, zbx_uint64_t privprotocol_new);
void	zbx_audit_host_prototype_update_json_update_interface_contextname(zbx_uint64_t hostid, zbx_uint64_t interfaceid,
		const char *contextname_old, const char *contextname_new);

void	zbx_audit_host_prototype_update_json_add_details(zbx_uint64_t hostid, zbx_uint64_t templateid, const char *name,
		int status, int discover, int custom_interfaces);
void	zbx_audit_host_prototype_update_json_add_templateid(zbx_uint64_t hostid, zbx_uint64_t templateid);
void	zbx_audit_host_prototype_update_json_update_name(zbx_uint64_t hostid, const char *old_name,
		const char *new_name);
void	zbx_audit_host_prototype_update_json_update_status(zbx_uint64_t hostid, int old_status, int new_status);
void	zbx_audit_host_prototype_update_json_update_discover(zbx_uint64_t hostid, int old_discover,
		int new_discover);
void	zbx_audit_host_prototype_update_json_update_custom_interfaces(zbx_uint64_t hostid, int old_custom_interfaces,
		int new_custom_interfaces);
void	zbx_audit_host_prototype_update_json_add_group_details(zbx_uint64_t hostid, const char* name,
		zbx_uint64_t groupid, zbx_uint64_t templateid);
void	zbx_audit_host_update_json_add_parent_template(zbx_uint64_t hostid, zbx_uint64_t templateid);
void	zbx_audit_host_prototype_update_json_add_templates(zbx_uint64_t hostid, zbx_vector_uint64_t *templateids);

void	zbx_audit_host_update_json_add_snmp_interface(zbx_uint64_t hostid, zbx_uint64_t version,
		zbx_uint64_t bulk, const char *community, const char *securityname, zbx_uint64_t securitylevel,
		const char *authpassphrase, const char *privpassphrase, zbx_uint64_t authprotocol,
		zbx_uint64_t privprotocol, const char *contextname, zbx_uint64_t interfaceid);
void	zbx_audit_host_update_json_update_snmp_interface(zbx_uint64_t hostid,
		zbx_uint64_t version_old, zbx_uint64_t version_new, zbx_uint64_t bulk_old,  zbx_uint64_t bulk_new,
		const char *community_old, const char *community_new, const char *securityname_old,
		const char *securityname_new, zbx_uint64_t securitylevel_old, zbx_uint64_t securitylevel_new,
		const char *authpassphrase_old, const char *authpassphrase_new, const char *privpassphrase_old,
		const char *privpassphrase_new, zbx_uint64_t authprotocol_old, zbx_uint64_t authprotocol_new,
		zbx_uint64_t privprotocol_old, zbx_uint64_t privprotocol_new, const char *contextname_old,
		const char *contextname_new, zbx_uint64_t interfaceid);

void	zbx_audit_host_prototype_update_json_add_snmp_interface(zbx_uint64_t hostid, zbx_uint64_t version,
		zbx_uint64_t bulk, const char *community, const char *securityname, zbx_uint64_t securitylevel,
		const char *authpassphrase, const char *privpassphrase, zbx_uint64_t authprotocol,
		zbx_uint64_t privprotocol, const char *contextname, zbx_uint64_t interfaceid);
void	zbx_audit_host_prototype_update_json_update_snmp_interface(zbx_uint64_t hostid,
		zbx_uint64_t version_old, zbx_uint64_t version_new, zbx_uint64_t bulk_old,  zbx_uint64_t bulk_new,
		const char *community_old, const char *community_new, const char *securityname_old,
		const char *securityname_new, zbx_uint64_t securitylevel_old, zbx_uint64_t securitylevel_new,
		const char *authpassphrase_old, const char *authpassphrase_new, const char *privpassphrase_old,
		const char *privpassphrase_new, zbx_uint64_t authprotocol_old, zbx_uint64_t authprotocol_new,
		zbx_uint64_t privprotocol_old, zbx_uint64_t privprotocol_new, const char *contextname_old,
		const char *contextname_new, zbx_uint64_t interfaceid);
/* host audit end */

#endif	/* ZABBIX_AUDIT_H */
