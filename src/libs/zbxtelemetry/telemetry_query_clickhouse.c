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

#include "zbxjson.h"
#include "zbxtelemetry.h"
#include "zbxcommon.h"
#include "zbxtypes.h"

static int	tq_clickhouse_parse_row(const zbx_tq_query_t *query, struct zbx_json_parse *jp, struct zbx_json *j)
{
	int		ret = FAIL;
	const char	*p = NULL;
	char		buf[MAX_STRING_LEN];
	zbx_uint32_t	row_idx;
	zbx_uint64_t	timestamp;

	zbx_json_addobject(j, NULL);

	/* row index */
	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)) || SUCCEED != zbx_is_uint32(buf, &row_idx))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse row index from row \"%s\"", jp->start);
		goto out;
	}

	zbx_json_adduint64(j, "id", row_idx);

	/* skip rounded time */
	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse rounded time from row \"%s\"", jp->start);
		goto out;
	}

	/* timestamp */
	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)) ||
			SUCCEED != zbx_is_uint64(buf, &timestamp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse timestamp from row \"%s\"", jp->start);
		goto out;
	}

	zbx_json_adduint64(j, "timestamp", timestamp);

	zbx_json_addobject(j, "columns");

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];
		char		*field_name;
		zbx_json_type_t	type;

		if (NULL != col->key)
			field_name = zbx_dsprintf(NULL, "%s.%s", col->name, col->key);
		else
			field_name = zbx_strdup(NULL, col->name);

		if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), &type)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from row \"%s\"", field_name,
					jp->start);
			zbx_free(field_name);
			goto out_columns;
		}

		zbx_json_addstring(j, field_name, buf, type);

		zbx_free(field_name);
	}

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		const char	*field_name = query->aggregated_columns.values[i].alias;
		zbx_json_type_t	type;

		if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), &type)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from row \"%s\"", field_name,
					jp->start);
			goto out_columns;
		}

		zbx_json_addstring(j, field_name, buf, type);
	}

	ret = SUCCEED;
out_columns:
	zbx_json_close(j);
out:
	zbx_json_close(j);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: modifies resp during parsing but returns it to initial state     *
 *                                                                            *
 ******************************************************************************/
int	zbx_tq_clickhouse_resp_to_json(const zbx_tq_query_t *query, char *resp, char **out_json)
{
	int		ret = SUCCEED;
	struct zbx_json	j;
	char		*start = resp;

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	while (1)
	{
		char			*end;
		struct zbx_json_parse	jp;

		/* handle empty resp */
		if ('\0' == *start)
			break;

		if (NULL != (end = strchr(start, '\n')))
			*end = '\0';

		if (SUCCEED != zbx_json_open(start, &jp) || SUCCEED != tq_clickhouse_parse_row(query, &jp, &j))
			ret = FAIL;

		if (NULL == end) {
			break;
		}

		*end = '\n';
		start = end + 1;

		if (SUCCEED != ret)
			break;
	}

	if (SUCCEED == ret)
		*out_json = zbx_strdup(NULL, j.buffer);

	zbx_json_close(&j);

	return ret;
}
