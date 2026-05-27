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

#include "zbxtelemetry.h"
#include "telemetry.h"
#include "zbxalgo.h"
#include "zbxjson.h"

int	zbx_tq_parse_sql_result(const zbx_tq_query_t *query, zbx_db_result_t result, zbx_vector_str_t *values)
{
	int		ret = FAIL;
	zbx_db_row_t	row;
	struct zbx_json	j;
	int		row_id = 1;

	zbx_vector_str_create(values);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		int	col_idx = 0;
		zbx_uint64_t	timestamp;

		zbx_json_reset(&j);

		/* row id */
		zbx_json_adduint64(&j, "id", row_id);

		/* skip rounded time */
		col_idx++;

		/* timestamp */
		if (SUCCEED != zbx_is_uint64(row[col_idx], &timestamp))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse timestamp from row #%d: \"%s\"", row_id,
					row[col_idx]);
			goto out;
		}

		zbx_json_adduint64(&j, "timestamp", timestamp);
		col_idx++;

		zbx_json_addobject(&j, "columns");

		for (int i = 0; i < query->columns.values_num; i++)
		{
			const zbx_tq_column_t	*col = &query->columns.values[i];
			char			*field_name = tq_get_result_field_name_dyn(col);
			zbx_json_type_t		type = ZBX_TQ_COLUMN_TYPE_NUM == col->col_type ? ZBX_JSON_TYPE_NUMBER :
					ZBX_JSON_TYPE_STRING;

			zbx_json_addstring(&j, field_name, row[col_idx++], type);

			zbx_free(field_name);
		}

		for (int i = 0; i < query->aggregated_columns.values_num; i++)
		{
			const zbx_tq_aggr_column_t	*aggr_col = &query->aggregated_columns.values[i];
			const char			*field_name = aggr_col->alias;

			zbx_json_addstring(&j, field_name, row[col_idx++], ZBX_JSON_TYPE_NUMBER);
		}

		zbx_json_close(&j);
		zbx_json_close(&j);

		zbx_vector_str_append(values, zbx_strdup(NULL, j.buffer));

		row_id++;
	}

	ret = SUCCEED;
out:
	zbx_json_free(&j);

	if (FAIL == ret)
	{
		zbx_vector_str_clear_ext(values, zbx_str_free);
		zbx_vector_str_destroy(values);
	}

	return ret;
}
