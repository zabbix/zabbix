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

int	zbx_auditlog_global_script(unsigned char script_type, unsigned char script_execute_on,
		char *script_command_orig, zbx_uint64_t hostid, char *hostname, zbx_uint64_t eventid,
		zbx_uint64_t proxy_hostid, zbx_uint64_t userid, const char *clientip, const char *output,
		const char *error);

void	zbx_audit_init(void);
void	zbx_audit_flush(void);
void	zbx_audit_update_json_add_string(const zbx_uint64_t itemid, const char *key, const char *value);
void	zbx_audit_update_json_add_uint64(const zbx_uint64_t itemid, const char *key, const uint64_t value);

void	zbx_audit_host_add_interfaces(zbx_uint64_t hostid, zbx_uint64_t interfaceid, zbx_uint64_t main_,
		zbx_uint64_t type, zbx_uint64_t useip, const char *ip, const char *dns, zbx_uint64_t port);
void	zbx_audit_host_update_snmp_interfaces(zbx_uint64_t hostid, zbx_uint64_t version, zbx_uint64_t bulk,
		const char *community, const char *securityname, zbx_uint64_t securitylevel, const char *authpassphrase,
		const char *privpassphrase, zbx_uint64_t authprotocol, zbx_uint64_t privprotocol,
		const char *contextname, zbx_uint64_t interfaceid);

void	zbx_audit_host_update_json_add_tls_and_psk(zbx_uint64_t hostid, int tls_connect, int tls_accept,
		const char *psk_identity, const char *psk);

void	zbx_audit_host_create_entry(int audit_action, zbx_uint64_t hostid, const char *name);
void	zbx_audit_host_add_groups(const char *audit_details_action, zbx_uint64_t hostid, zbx_uint64_t groupid);
void	zbx_audit_host_del(zbx_uint64_t hostid, const char *hostname);
#endif	/* ZABBIX_AUDIT_H */
