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

/* move into user class file */
const zbx_api_field_t zbx_api_object_user[] = {
		{"userid", ZBX_TYPE_ID, ZBX_API_FIELD_FLAG_SORTABLE},
		{"alias", ZBX_TYPE_CHAR, ZBX_API_FIELD_FLAG_REQUIRED | ZBX_API_FIELD_FLAG_SORTABLE},
		{"name", ZBX_TYPE_CHAR, 0},
		{"surname", ZBX_TYPE_CHAR, 0},
		{"url", ZBX_TYPE_CHAR, 0},
		{"autologin", ZBX_TYPE_INT, 0},
		{"autologout", ZBX_TYPE_INT, 0},
		{"lang", ZBX_TYPE_CHAR, 0},
		{"refresh", ZBX_TYPE_INT,  0},
		{"type", ZBX_TYPE_INT, 0},
		{"theme", ZBX_TYPE_CHAR,  0},
		{"attempt_failed", ZBX_TYPE_INT, 0},
		{"attempt_ip", ZBX_TYPE_CHAR, 0},
		{"attempt_clock", ZBX_TYPE_INT, 0},
		{"rows_per_page", ZBX_TYPE_INT, 0},
		{NULL}
};

