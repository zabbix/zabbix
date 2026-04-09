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
	/* FIXME: placeholder */

	int		ret = NOTSUPPORTED;
	zbx_tq_query_t	query;
	time_t		now = time(NULL);
	time_t		lasttimestamp;
	char		*sql = NULL;
	char 		*out_str;

	lasttimestamp = 0; /* FIXME: placeholder */

	if (FAIL == zbx_tq_query_from_json(item->query_fields, &query))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid query format."));
		goto out;
	}

	zbx_tq_sql_generate_clickhouse(&query, now, lasttimestamp, &sql);

	zabbix_log(LOG_LEVEL_INFORMATION, "MYTEST %s(): '%s'", __func__, sql);

	out_str = strdup("Parsed:\n");
	out_str = zbx_strdcatf(out_str, "category: %d,\n", (int)query.category);
	out_str = zbx_strdcatf(out_str, "metric_type: %d,\n", (int)query.metric_type);
	out_str = zbx_strdcatf(out_str, "columns:\n");
	for (int i = 0; i < query.columns.values_num; i++) {
		out_str = zbx_strdcatf(out_str, "-- name: '%s', key: '%s'\n",
				ZBX_NULL2STR(query.columns.values[i].name),
				ZBX_NULL2STR(query.columns.values[i].key));
	}
	out_str = zbx_strdcatf(out_str, "aggregated_columns:\n");
	for (int i = 0; i < query.aggregated_columns.values_num; i++) {
		const zbx_tq_aggr_column_t	*aggr_col = &query.aggregated_columns.values[i];
		out_str = zbx_strdcatf(out_str, "-- column_name: '%s', function: %d, args: {",
				ZBX_NULL2STR(aggr_col->column_name), (int)aggr_col->function);
		for (int j = 0; j < aggr_col->args.values_num; j++) {
			out_str = zbx_strdcatf(out_str, "'%s'%s", aggr_col->args.values[j],
					(j == aggr_col->args.values_num - 1) ? "" : ", ");
		}
		out_str = zbx_strdcatf(out_str, "}, alias: '%s'\n", aggr_col->alias);
	}
	out_str = zbx_strdcatf(out_str, "evaltype: %d\n", (int)query.evaltype);
	out_str = zbx_strdcatf(out_str, "formula: %s\n", query.formula);
	out_str = zbx_strdcatf(out_str, "conditions:\n");
	for (int i = 0; i < query.conditions.values_num; i++) {
		out_str = zbx_strdcatf(out_str, "-- column_name: '%s', json_path: '%s', value: '%s', operator: %d\n",
				ZBX_NULL2STR(query.conditions.values[i].column_name),
				ZBX_NULL2STR(query.conditions.values[i].json_path),
				ZBX_NULL2STR(query.conditions.values[i].value),
				(int)query.conditions.values[i].operator);
	}
	out_str = zbx_strdcatf(out_str, "time_shift: %d\n", query.time_shift);
	out_str = zbx_strdcatf(out_str, "loopback_limit: %d\n", query.loopback_limit);
	out_str = zbx_strdcatf(out_str, "aggregation_size: %d\n", query.aggregation_size);

	out_str = zbx_strdcatf(out_str, "\nlasttimestamp: " ZBX_FS_TIME_T "\n", lasttimestamp);

	out_str = zbx_strdcatf(out_str, "\nSQL: '%s'\n", sql);

	SET_TEXT_RESULT(result, out_str);

	ret = SUCCEED;

	zbx_tq_query_clean(&query);
	zbx_free(sql);
out:
	return ret;
}
