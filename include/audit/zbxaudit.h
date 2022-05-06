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

#ifndef ZABBIX_ZBXAUDIT_H
#define ZABBIX_ZBXAUDIT_H

#include "zbxtypes.h"

#define ZBX_AUDIT_ACTION_ADD		0
#define ZBX_AUDIT_ACTION_UPDATE		1
#define ZBX_AUDIT_ACTION_DELETE		2
#define ZBX_AUDIT_ACTION_EXECUTE	7

int	zbx_auditlog_global_script(unsigned char script_type, unsigned char script_execute_on,
		const char *script_command_orig, zbx_uint64_t hostid, const char *hostname, zbx_uint64_t eventid,
		zbx_uint64_t proxy_hostid, zbx_uint64_t userid, const char *username, const char *clientip,
		const char *output, const char *error);

void	zbx_audit_init(int audit_mode_set);
void	zbx_audit_prepare(void);
void	zbx_audit_clean(void);
void	zbx_audit_flush(void);
int	zbx_audit_flush_once(void);

#endif	/* ZABBIX_ZBXAUDIT_H */
