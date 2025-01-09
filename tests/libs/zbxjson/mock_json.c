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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"

#include "zbxcommon.h"
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
