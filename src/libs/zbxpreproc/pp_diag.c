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

#include "zbxpreproc.h"
#include "zbxdiag.h"
#include "zbxtime.h"
#include "zbxjson.h"

/******************************************************************************
 *                                                                            *
 * Purpose: add item top list to output json                                  *
 *                                                                            *
 * Parameters: json  - [OUT] the output json                                  *
 *             field - [IN] the field name                                    *
 *             name  - [IN] stat name                                         *
 *             items - [IN] a top item list                                   *
 *                                                                            *
 ******************************************************************************/
static void	diag_add_preproc_sequences(struct zbx_json *json, const char *field, const char *name,
		const zbx_vector_pp_top_stats_ptr_t *stats)
{
	int	i;

	zbx_json_addarray(json, field);

	for (i = 0; i < stats->values_num; i++)
	{
		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, "itemid", stats->values[i]->itemid);
		zbx_json_addint64(json, name, stats->values[i]->num);
		zbx_json_close(json);
	}

	zbx_json_close(json);
}

void	zbx_pp_top_stats_free(zbx_pp_top_stats_t *pts)
{
	zbx_free(pts);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add requested preprocessing diagnostic information to json data   *
 *                                                                            *
 * Parameters: jp    - [IN] the request                                       *
 *             json  - [IN/OUT] the json to update                            *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - the information was added successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_diag_add_preproc_info(const struct zbx_json_parse *jp, struct zbx_json *json, char **error)
{
	zbx_vector_diag_map_ptr_t	tops;
	int				ret = FAIL;
	double				time1, time2, time_total = 0;
	zbx_uint64_t			fields;
	zbx_diag_map_t			field_map[] = {
							{"", ZBX_DIAG_PREPROC_INFO},
							{NULL, 0}
						};

	zbx_vector_diag_map_ptr_create(&tops);

	if (SUCCEED == (ret = zbx_diag_parse_request(jp, field_map, &fields, &tops, error)))
	{
		zbx_json_addobject(json, ZBX_DIAG_PREPROCESSING);

		if (0 != (fields & ZBX_DIAG_PREPROC_SIMPLE))
		{
			zbx_uint64_t	preproc_num, pending_num, finished_num, sequences_num, queued_num, queued_sz,
					direct_num, direct_sz, history_sz, finished_peak_num, pending_peak_num,
					processed_num;

			time1 = zbx_time();
			if (FAIL == (ret = zbx_preprocessor_get_diag_stats(&preproc_num, &pending_num, &finished_num,
					&sequences_num, &queued_num, &queued_sz, &direct_num, &direct_sz,
					&history_sz, &finished_peak_num, &pending_peak_num, &processed_num, error)))
			{
				goto out;
			}

			time2 = zbx_time();
			time_total += time2 - time1;

			if (0 != (fields & ZBX_DIAG_PREPROC_INFO))
			{
				zbx_json_adduint64(json, "cached items", preproc_num);
				zbx_json_adduint64(json, "pending tasks", pending_num);
				zbx_json_adduint64(json, "finished tasks", finished_num);
				zbx_json_adduint64(json, "task sequences", sequences_num);
				zbx_json_adduint64(json, "finished count", processed_num);
				zbx_json_adduint64(json, "queued count", queued_num);
				zbx_json_adduint64(json, "queued size", queued_sz);
				zbx_json_adduint64(json, "direct count", direct_num);
				zbx_json_adduint64(json, "direct size", direct_sz);
				zbx_json_adduint64(json, "history size", history_sz);
				zbx_json_adduint64(json, "pending tasks peak", pending_peak_num);
				zbx_json_adduint64(json, "finished tasks peak", finished_peak_num);
			}
		}

		if (0 != tops.values_num)
		{
			int	i;

			zbx_json_addobject(json, "top");

			for (i = 0; i < tops.values_num; i++)
			{
				char		*name;
				zbx_diag_map_t	*map = tops.values[i];
				int (*zbx_get_top_cb)(int limit, zbx_vector_pp_top_stats_ptr_t *stats, char **error);

				if (0 == strcmp(map->name, "sequences"))
				{
					zbx_get_top_cb = zbx_preprocessor_get_top_sequences;
					name = "tasks";
				}
				else if (0 == strcmp(map->name, "peak"))
				{
					zbx_get_top_cb = zbx_preprocessor_get_top_peak;
					name = "tasks";
				}
				else if (0 == strcmp(map->name, "values_num"))
				{
					zbx_get_top_cb = zbx_preprocessor_get_top_values_num;
					name = "values_num";
				}
				else if (0 == strcmp(map->name, "values_sz"))
				{
					zbx_get_top_cb = zbx_preprocessor_get_top_values_size;
					name = "values_sz";
				}
				else if (0 == strcmp(map->name, "time_ms"))
				{
					zbx_get_top_cb = zbx_preprocessor_get_top_time_ms;
					name = "time_ms";
				}
				else if (0 == strcmp(map->name, "total_ms"))
				{
					zbx_get_top_cb = zbx_preprocessor_get_top_total_ms;
					name = "total_ms";
				}
				else
				{
					*error = zbx_dsprintf(*error, "Unsupported top field: %s", map->name);
					ret = FAIL;
					goto out;
				}

				zbx_vector_pp_top_stats_ptr_t	stats;

				zbx_vector_pp_top_stats_ptr_create(&stats);
				time1 = zbx_time();

				if (SUCCEED != (ret = zbx_get_top_cb((int)map->value, &stats, error)))
				{
					zbx_vector_pp_top_stats_ptr_destroy(&stats);
					goto out;
				}

				time2 = zbx_time();
				time_total += time2 - time1;

				diag_add_preproc_sequences(json, map->name, name, &stats);

				zbx_vector_pp_top_stats_ptr_clear_ext(&stats, zbx_pp_top_stats_free);
				zbx_vector_pp_top_stats_ptr_destroy(&stats);
			}

			zbx_json_close(json);
		}

		zbx_json_addfloat(json, "time", time_total);
		zbx_json_close(json);
	}
out:
	zbx_vector_diag_map_ptr_clear_ext(&tops, zbx_diag_map_free);
	zbx_vector_diag_map_ptr_destroy(&tops);

	return ret;
}
