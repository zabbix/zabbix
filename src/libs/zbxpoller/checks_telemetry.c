/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "checks_telemetry.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxtelemetry.h"

int	get_value_telemetry(const zbx_dc_item_t *item, AGENT_RESULT *result)
{
	// FIXME: placeholder

	zbx_tq_query_t query;
	zbx_tq_query_from_json(item->query_fields, &query);

	char *out_str = zbx_strdup(NULL, item->query_fields);
	SET_TEXT_RESULT(result, out_str);

	zbx_tq_query_clean(&query);
	return SUCCEED;
}
