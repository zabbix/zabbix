/*
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "zbxalgo.h"
#include "zbxtime.h"
#include "zbxjson.h"

/******************************************************************************
 *                                                                            *
 * Purpose: add item top list to output json                                  *
 *                                                                            *
 * Parameters: json  - [OUT] the output json                                  *
 *             field - [IN] the field name                                    *
 *             items - [IN] a top item list                                   *
 *                                                                            *
 ******************************************************************************/
static void	diag_add_preproc_sequences(struct zbx_json *json, const char *field,
		const zbx_vector_pp_top_stats_ptr_t *stats)
{
	int	i;

	zbx_json_addarray(json, field);

	for (i = 0; i < stats->values_num; i++)
	{
		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, "itemid", stats->values[i]->itemid);
		zbx_json_addint64(json, "tasks", stats->values[i]->tasks_num);
		zbx_json_close(json);
	}

	zbx_json_close(json);
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
			zbx_uint64_t	preproc_num, pending_num, finished_num, sequences_num;

			time1 = zbx_time();
			if (FAIL == (ret = zbx_preprocessor_get_diag_stats(&preproc_num, &pending_num, &finished_num,
					&sequences_num, error)))
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
			}
		}

		if (0 != tops.values_num)
		{
			int	i;

			zbx_json_addobject(json, "top");

			for (i = 0; i < tops.values_num; i++)
			{
				zbx_diag_map_t	*map = tops.values[i];
				int (*zbx_get_top_cb)(int limit, zbx_vector_pp_top_stats_ptr_t *stats, char **error);

				if (0 == strcmp(map->name, "sequences"))
				{
					zbx_get_top_cb = zbx_preprocessor_get_top_sequences;
				}
				else if (0 == strcmp(map->name, "peak"))
				{
					zbx_get_top_cb = zbx_preprocessor_get_top_peak;
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

				diag_add_preproc_sequences(json, map->name, &stats);

				zbx_vector_pp_top_stats_ptr_clear_ext(&stats,
						(zbx_pp_top_stats_ptr_free_func_t)(zbx_ptr_free));
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
