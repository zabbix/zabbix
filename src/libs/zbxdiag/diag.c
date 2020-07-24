/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "dbcache.h"

#include "diag.h"

static void	diag_map_free(zbx_diag_map_t *map)
{
	zbx_free(map->name);
	zbx_free(map);
}

/******************************************************************************
 *                                                                            *
 * Function: diag_parse_request                                               *
 *                                                                            *
 * Purpose: parse diagnostic section request having json format               *
 *          {"stats":[<field1>,<field2>,...], "top":{<field1>:<limit1>,...}}  *
 *                                                                            *
 * Parameters: jp         - [IN] the request                                  *
 *             field_map  - [IN] a map of supported statistic field names to  *
 *                               bitmasks                                     *
 *             field_mask - [OUT] the bitmask of the requested fields         *
 *             top_views  - [OUT] the requested top views                     *
 *             error      - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - the request was parsed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	diag_parse_request(const struct zbx_json_parse *jp, const zbx_diag_map_t *field_map,
		zbx_uint64_t *field_mask, zbx_vector_ptr_t *top_views, char **error)
{
	struct zbx_json_parse	jp_stats;
	int			ret = FAIL;
	const char		*pnext = NULL;
	char			name[ZBX_DIAG_FIELD_MAX + 1], value[MAX_ID_LEN + 1];
	zbx_uint64_t		value_ui64;

	*field_mask = 0;

	/* parse requested statistics fields */
	if (SUCCEED == zbx_json_brackets_by_name(jp, "stats", &jp_stats))
	{
		while (NULL != (pnext = zbx_json_next(&jp_stats, pnext)))
		{
			const zbx_diag_map_t	*stat;

			zbx_json_decodevalue(pnext, name, sizeof(name), NULL);

			for (stat = field_map;; stat++)
			{
				if (NULL == stat->name)
				{
					*error = zbx_dsprintf(*error, "Unknown statistics field: %s", name);
					goto out;
				}

				if (0 == strcmp(name, stat->name))
					break;
			}

			*field_mask |= stat->value;
		}
	}

	/* parse requested top views */
	if (SUCCEED == zbx_json_brackets_by_name(jp, "top", &jp_stats))
	{
		while (NULL != (pnext = zbx_json_pair_next(&jp_stats, pnext, name, sizeof(name))))
		{
			zbx_diag_map_t	*top;

			if (NULL == zbx_json_decodevalue(pnext, value, sizeof(value), NULL))
			{
				*error = zbx_strdup(*error, zbx_json_strerror());
				goto out;
			}
			if (FAIL == is_uint64(value, &value_ui64))
			{
				*error = zbx_dsprintf(*error, "Invalid top limit value: %s", value);
				goto out;
			}

			top = (zbx_diag_map_t *)zbx_malloc(NULL, sizeof(zbx_diag_map_t));
			top->name = zbx_strdup(NULL, name);
			top->value = value_ui64;
			zbx_vector_ptr_append(top_views, top);
		}
	}
	ret = SUCCEED;
out:
	if (FAIL == ret)
		zbx_vector_ptr_clear_ext(top_views, (zbx_ptr_free_func_t)diag_map_free);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: diag_add_mem_stats                                               *
 *                                                                            *
 * Purpose: add memory statistics to the json data                            *
 *                                                                            *
 * Parameters: j     - [IN/OUT] the json to update                            *
 *             name  - [IN] the memory object name                            *
 *             stats - [IN] the memory statistics                             *
 *                                                                            *
 ******************************************************************************/
static void	diag_add_mem_stats(struct zbx_json *j, const char *name, const zbx_mem_stats_t *stats)
{
	int	i;

	if (NULL == stats)
		return;

	zbx_json_addobject(j, name);
	zbx_json_addobject(j, "size");
	zbx_json_adduint64(j, "free", stats->free_size);
	zbx_json_adduint64(j, "used", stats->used_size);
	zbx_json_close(j);

	zbx_json_addobject(j, "chunks");
	zbx_json_adduint64(j, "free", stats->free_chunks);
	zbx_json_adduint64(j, "used", stats->used_chunks);
	zbx_json_adduint64(j, "min", stats->min_chunk_size);
	zbx_json_adduint64(j, "max", stats->max_chunk_size);

	zbx_json_addobject(j, "buckets");
	for (i = 0; i < MEM_BUCKET_COUNT; i++)
	{
		if (0 != stats->chunks_num[i])
		{
			char	buf[MAX_ID_LEN + 2];

			zbx_snprintf(buf, sizeof(buf), "%d%s", MEM_MIN_BUCKET_SIZE + 8 * i,
					(MEM_BUCKET_COUNT - 1 == i ? "+" : ""));
			zbx_json_adduint64(j, buf, stats->chunks_num[i]);
		}
	}

	zbx_json_close(j);
	zbx_json_close(j);
	zbx_json_close(j);
}

/******************************************************************************
 *                                                                            *
 * Function: diag_add_historycache_info                                       *
 *                                                                            *
 * Purpose: add requested history cache diagnostic information to json data   *
 *                                                                            *
 * Parameters: jp        - [IN] the request                                   *
 *             field_map - [IN] a map of supported statistic field names to   *
 *                               bitmasks                                     *
 *             j         - [IN/OUT] the json to update                        *
 *             error     - [OUT] error message                                *
 *                                                                            *
 * Return value: SUCCEED - the request was parsed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	diag_add_historycache_info(const struct zbx_json_parse *jp, struct zbx_json *j, char **error)
{
	zbx_vector_ptr_t	tops;
	int			ret;
	double			time1, time2, time_total;
	zbx_uint64_t		fields;
	zbx_diag_map_t		field_map[] = {
					{"all", ZBX_DIAG_HISTORYCACHE_SIMPLE | ZBX_DIAG_HISTORYCACHE_MEM},
					{"items", ZBX_DIAG_HISTORYCACHE_ITEMS},
					{"values", ZBX_DIAG_HISTORYCACHE_VALUES},
					{"memory", ZBX_DIAG_HISTORYCACHE_MEM},
					{"memory.data", ZBX_DIAG_HISTORYCACHE_MEM_DATA},
					{"memory.index", ZBX_DIAG_HISTORYCACHE_MEM_INDEX},
					{NULL, 0}
					};

	zbx_vector_ptr_create(&tops);

	if (SUCCEED == (ret = diag_parse_request(jp, field_map, &fields, &tops, error)))
	{
		int	i;

		zbx_json_addobject(j, "historycache");

		if (0 != (fields & ZBX_DIAG_HISTORYCACHE_SIMPLE))
		{
			zbx_uint64_t	values_num, items_num;
			time1 = zbx_time();
			zbx_hc_get_simple_stats(&items_num, &values_num);
			time2 = zbx_time();
			time_total += time2 - time1;

			if (0 != (fields & ZBX_DIAG_HISTORYCACHE_ITEMS))
				zbx_json_addint64(j, "items", items_num);
			if (0 != (fields & ZBX_DIAG_HISTORYCACHE_VALUES))
				zbx_json_addint64(j, "values", values_num);
		}

		if (0 != (fields & ZBX_DIAG_HISTORYCACHE_MEM))
		{
			zbx_mem_stats_t	data_mem, index_mem, *pdata_mem, *pindex_mem;

			pdata_mem = (0 != (fields & ZBX_DIAG_HISTORYCACHE_MEM_DATA) ? &data_mem : NULL);
			pindex_mem = (0 != (fields & ZBX_DIAG_HISTORYCACHE_MEM_INDEX) ? &index_mem : NULL);

			time1 = zbx_time();
			zbx_hc_get_mem_stats(pdata_mem, pindex_mem);
			time2 = zbx_time();
			time_total += time2 - time1;

			zbx_json_addobject(j, "memory");
			diag_add_mem_stats(j, "data", pdata_mem);
			diag_add_mem_stats(j, "index", pindex_mem);
			zbx_json_close(j);
		}

		for (i = 0; i < tops.values_num; i++)
		{
			zbx_diag_map_t	*map = (zbx_diag_map_t *)tops.values[i];

			/* TODO: process top requests */
		}

		zbx_json_close(j);
	}

	zbx_vector_ptr_clear_ext(&tops, (zbx_ptr_free_func_t)diag_map_free);
	zbx_vector_ptr_destroy(&tops);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_diag_get_info                                                *
 *                                                                            *
 * Purpose: get diagnostic information                                        *
 *                                                                            *
 * Parameters: jp   - [IN] the request                                        *
 *             info - [OUT] the requested information or error message        *
 *                                                                            *
 * Return value: SUCCEED - the information was retrieved successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_diag_get_info(const struct zbx_json_parse *jp, char **info)
{
	struct zbx_json_parse	jp_section;
	char			section[ZBX_DIAG_SECTION_MAX + 1];
	const char		*pnext = NULL;
	struct zbx_json		j;
	int			ret = SUCCEED;

	zbx_json_init(&j, 1024);

	while (NULL != (pnext = zbx_json_pair_next(jp, pnext, section, sizeof(section))))
	{
		if (FAIL == (ret = zbx_json_brackets_open(pnext, &jp_section)))
		{
			*info = zbx_strdup(*info, zbx_json_strerror());
			goto out;
		}

		if (FAIL == (ret = diag_add_section_info(section, &jp_section, &j, info)))
			goto out;
	}
out:
	if (SUCCEED == ret)
		*info = zbx_strdup(*info, j.buffer);

	zbx_json_free(&j);

	return ret;
}
