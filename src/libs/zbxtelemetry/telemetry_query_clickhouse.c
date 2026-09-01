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
#include "zbxexpr.h"
#include "zbxjson.h"
#include "zbxstr.h"
#include "zbxcommon.h"
#include "zbxtypes.h"
#include "zbxnum.h"

static char	*tq_clickhouse_parse_row(const zbx_tq_query_t *query, struct zbx_json_parse *jp, int row_id)
{
	struct zbx_json	j;
	char		*str = NULL;
	const char	*p = NULL;
	char		*buf = NULL;
	size_t		buf_alloc = 0;
	zbx_uint64_t	timestamp;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	/* row id */
	zbx_json_addint64(&j, "id", row_id);

	/* timestamp */
	if (NULL == (p = zbx_json_next_value_dyn(jp, p, &buf, &buf_alloc, NULL)) ||
			SUCCEED != zbx_is_uint64(buf, &timestamp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse timestamp from row \"%s\"", jp->start);
		goto out;
	}

	zbx_json_adduint64(&j, "timestamp", timestamp);

	zbx_json_addobject(&j, "columns");

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];
		char			*field_name = tq_get_result_field_name_dyn(col);
		zbx_json_type_t		type;
		const char		*string;

		if (NULL == (p = zbx_json_next_value_dyn(jp, p, &buf, &buf_alloc, &type)) ||
				SUCCEED != tq_validate_result_column_type(type))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from row \"%s\"", field_name,
					jp->start);
			zbx_free(field_name);
			goto out;
		}

		string = (ZBX_JSON_TYPE_NULL == type ? NULL : buf);
		zbx_json_addstring(&j, field_name, string, type);

		zbx_free(field_name);
	}

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		const char	*field_name = query->aggregated_columns.values[i].alias;
		zbx_json_type_t	type;
		const char	*string;

		if (NULL == (p = zbx_json_next_value_dyn(jp, p, &buf, &buf_alloc, &type)) ||
				SUCCEED != tq_validate_result_aggr_column_type(type))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from row \"%s\"", field_name,
					jp->start);
			goto out;
		}

		string = (ZBX_JSON_TYPE_NULL == type ? NULL : buf);
		zbx_json_addstring(&j, field_name, string, type);
	}

	zbx_json_close(&j);
	zbx_json_close(&j);
	str = zbx_strdup(NULL, j.buffer);
out:
	zbx_json_free(&j);
	zbx_free(buf);

	return str;
}

/******************************************************************************
 *                                                                            *
 * Comments: modifies resp during parsing but returns it to initial state     *
 * Return value: SUCCEED         - parsed response successfully                *
 *               SUCCEED_PARTIAL - row count exceeded ZBX_TQ_MAX_RESULT_ROWS  *
 *               FAIL            - failed to parse response                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_tq_clickhouse_parse_resp(const zbx_tq_query_t *query, char *resp, zbx_vector_str_t *values)
{
	int	ret = SUCCEED;
	char	*start = resp;
	int	row_count = 0;

	zbx_vector_str_create(values);

	while (1)
	{
		char			*end;
		struct zbx_json_parse	jp;
		char			*str = NULL;

		/* handle empty resp */
		if ('\0' == *start)
			break;

		if (NULL != (end = strchr(start, '\n')))
			*end = '\0';

		row_count++;

		if (SUCCEED != zbx_json_open(start, &jp) ||
				NULL == (str = tq_clickhouse_parse_row(query, &jp, row_count)))
		{
			ret = FAIL;
		}
		else if (ZBX_TQ_MAX_RESULT_ROWS < row_count)
		{
			ret = SUCCEED_PARTIAL;
			zbx_free(str);
		}

		if (NULL != str)
			zbx_vector_str_append(values, str);

		if (NULL == end)
			break;

		*end = '\n';
		start = end + 1;

		if (SUCCEED != ret)
			break;
	}

	if (FAIL == ret)
	{
		zbx_vector_str_clear_ext(values, zbx_str_free);
		zbx_vector_str_destroy(values);
	}

	return ret;
}

void	zbx_tq_clickhouse_get_query_url(const char *base_url, const char *db, char **url)
{
	char	*db_enc = NULL;

	*url = zbx_strdup(NULL, base_url);

	zbx_rtrim(*url, "/");

	zbx_url_encode(db, &db_enc);

	*url = zbx_dsprintf(*url, "%s?database=%s", *url, db_enc);

	zbx_free(db_enc);
}
