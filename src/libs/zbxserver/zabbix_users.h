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

#ifndef ZABBIX_ZABBIX_USERS_H_
#define ZABBIX_ZABBIX_USERS_H_

#include "common.h"

int	check_perm2system(zbx_uint64_t userid);
char	*get_user_timezone(zbx_uint64_t userid);
int	zbx_check_user_administration_actions_permissions(const zbx_user_t *user, const char *role_rule_default,
		const char *role_rule);

#endif /* ZABBIX_ZABBIX_USERS_H_ */
