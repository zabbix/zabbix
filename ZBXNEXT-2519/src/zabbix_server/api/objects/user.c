/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "common.h"
#include "zbxalgo.h"
#include "zbxjson.h"
#include "log.h"
#include "dbschema.h"
#include "db.h"

#include "../api.h"
#include "user.h"

static zbx_api_property_t zbx_api_class_properties[] = {
		{"userid", "userid", NULL, ZBX_API_PROPERTY_SORTABLE},
		{"alias", "alias", NULL, ZBX_API_PROPERTY_REQUIRED | ZBX_API_PROPERTY_SORTABLE},
		{"name", "name", NULL, 0},
		{"surname", "surname", NULL, 0},
		{"url", "url", NULL, 0},
		{"autologin", "autologin", NULL, 0},
		{"autologout", "autologout", NULL, 0},
		{"lang", "lang", NULL, 0},
		{"refresh", "refresh", NULL,  0},
		{"type", "type", NULL, 0},
		{"theme", "theme", NULL,  0},
		{"attempt_failed", "attempt_failed", NULL, 0},
		{"attempt_ip", "attempt_ip", NULL, 0},
		{"attempt_clock", "attempt_clock", NULL, 0},
		{"rows_per_page", "rows_per_page", NULL, 0},
		{NULL}
};

zbx_api_class_t	zbx_api_class_user = {"user", "users", zbx_api_class_properties};
