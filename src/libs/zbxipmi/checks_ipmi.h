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

#ifndef ZABBIX_CHECKS_IPMI_H
#define ZABBIX_CHECKS_IPMI_H

#include "zbxcommon.h"

#ifdef HAVE_OPENIPMI

int	zbx_init_ipmi_handler(void);
void	zbx_free_ipmi_handler(void);

int	get_value_ipmi(zbx_uint64_t itemid, const char *addr, unsigned short port, signed char authtype,
		unsigned char privilege, const char *username, const char *password, const char *sensor, char **value);

int	get_discovery_ipmi(zbx_uint64_t itemid, const char *addr, unsigned short port, signed char authtype,
		unsigned char privilege, const char *username, const char *password, char **value);

int	zbx_parse_ipmi_command(const char *command, char *c_name, int *val, char *error, size_t max_error_len);

int	zbx_set_ipmi_control_value(zbx_uint64_t hostid, const char *addr, unsigned short port, signed char authtype,
		unsigned char privilege, const char *username, const char *password, const char *sensor,
		int value, char **error);

void	zbx_delete_inactive_ipmi_hosts(time_t last_check);

void	zbx_perform_all_openipmi_ops(int timeout);

#endif	/* HAVE_OPENIPMI */

#endif	/* ZABBIX_CHECKS_IPMI_H */
