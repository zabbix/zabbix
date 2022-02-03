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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"

#include "common.h"
#include "zbxjson.h"
#include "mock_json.h"

const char	*zbx_mock_json_type_to_str(int type)
{
	static const char *json_types[] = {
			"ZBX_JSON_TYPE_UNKNOWN", "ZBX_JSON_TYPE_STRING", "ZBX_JSON_TYPE_INT",
			"ZBX_JSON_TYPE_ARRAY", "ZBX_JSON_TYPE_OBJECT", "ZBX_JSON_TYPE_NULL",
			"ZBX_JSON_TYPE_TRUE", "ZBX_JSON_TYPE_FALSE"};

	if (0 > type || ZBX_JSON_TYPE_FALSE < type)
		fail_msg("Unknown json type: %d", type);

	return json_types[type];
}
