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
#define AUDIT_DETAILS_ACTION_DELETE	"delete"
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
zbx_hashset_t	*zbx_get_audit_hashset(void);

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
void	zbx_audit_update_json_delete(const zbx_uint64_t id, const char *audit_op, const char *key);
#endif	/* ZABBIX_AUDIT_H */
