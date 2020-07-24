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
#include "memalloc.h"
#include "../../libs/zbxdbcache/valuecache.h"

#include "diag.h"

/******************************************************************************
 *                                                                            *
 * Function: diag_historycache_item_compare_values                            *
 *                                                                            *
 * Purpose: sort itemid,values_num pair by values_num in descending order     *
 *                                                                            *
 ******************************************************************************/
static int	diag_valuecache_item_compare_values(const void *d1, const void *d2)
{
	zbx_vc_item_diag_t	*i1 = *(zbx_vc_item_diag_t **)d1;
	zbx_vc_item_diag_t	*i2 = *(zbx_vc_item_diag_t **)d2;

	return i2->values_num - i1->values_num;
}

/******************************************************************************
 *                                                                            *
 * Function: diag_valuecache_item_compare_hourly                              *
 *                                                                            *
 * Purpose: sort itemid,values_num pair by hourly_num in descending order     *
 *                                                                            *
 ******************************************************************************/
static int	diag_valuecache_item_compare_hourly(const void *d1, const void *d2)
{
	zbx_vc_item_diag_t	*i1 = *(zbx_vc_item_diag_t **)d1;
	zbx_vc_item_diag_t	*i2 = *(zbx_vc_item_diag_t **)d2;

	return i2->hourly_num - i1->hourly_num;
}

/******************************************************************************
 *                                                                            *
 * Function: diag_valuecache_add_item                                         *
 *                                                                            *
 * Purpose: add valuecache item diagnostic statistics to json                 *
 *                                                                            *
 ******************************************************************************/
static void	diag_valuecache_add_item(struct zbx_json *json, zbx_vc_item_diag_t *item)
{
	zbx_json_addobject(json, NULL);
	zbx_json_addint64(json, "itemid", item->itemid);
	zbx_json_addint64(json, "values", item->values_num);
	zbx_json_addint64(json, "request.values", item->hourly_num);
	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Function: diag_add_valuecache_info                                         *
 *                                                                            *
 * Purpose: add requested value cache diagnostic information to json data     *
 *                                                                            *
 * Parameters: jp        - [IN] the request                                   *
 *             field_map - [IN] a map of supported statistic field names to   *
 *                               bitmasks                                     *
 *             json      - [IN/OUT] the json to update                        *
 *             error     - [OUT] error message                                *
 *                                                                            *
 * Return value: SUCCEED - the request was parsed successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	diag_add_valuecache_info(const struct zbx_json_parse *jp, struct zbx_json *json, char **error)
{
	zbx_vector_ptr_t	tops;
	int			ret;
	double			time1, time2, time_total = 0;
	zbx_uint64_t		fields;
	zbx_diag_map_t		field_map[] = {
					{"all", ZBX_DIAG_VALUECACHE_SIMPLE | ZBX_DIAG_VALUECACHE_MEMORY},
					{"items", ZBX_DIAG_VALUECACHE_ITEMS},
					{"values", ZBX_DIAG_VALUECACHE_VALUES},
					{"mode", ZBX_DIAG_VALUECACHE_MODE},
					{"memory", ZBX_DIAG_VALUECACHE_MEMORY},
					{NULL, 0}
					};

	zbx_vector_ptr_create(&tops);

	if (SUCCEED == (ret = diag_parse_request(jp, field_map, &fields, &tops, error)))
	{
		zbx_json_addobject(json, "valuecache");

		if (0 != (fields & ZBX_DIAG_VALUECACHE_SIMPLE))
		{
			zbx_uint64_t	values_num, items_num;
			int		mode;

			time1 = zbx_time();
			zbx_vc_get_diag_stats(&items_num, &values_num, &mode);
			time2 = zbx_time();
			time_total += time2 - time1;

			if (0 != (fields & ZBX_DIAG_VALUECACHE_ITEMS))
				zbx_json_addint64(json, "items", items_num);
			if (0 != (fields & ZBX_DIAG_VALUECACHE_VALUES))
				zbx_json_addint64(json, "values", values_num);
			if (0 != (fields & ZBX_DIAG_VALUECACHE_MODE))
				zbx_json_addint64(json, "mode", mode);
		}

		if (0 != (fields & ZBX_DIAG_VALUECACHE_MEMORY))
		{
			zbx_mem_stats_t	mem;

			time1 = zbx_time();
			zbx_vc_get_mem_stats(&mem);
			time2 = zbx_time();
			time_total += time2 - time1;

			diag_add_mem_stats(json, "memory", &mem);
		}

		if (0 != tops.values_num)
		{
			zbx_vector_ptr_t	items;
			int			i;

			zbx_vector_ptr_create(&items);

			time1 = zbx_time();
			zbx_vc_get_items_diag(&items);
			time2 = zbx_time();
			time_total += time2 - time1;

			zbx_json_addobject(json, "top");

			for (i = 0; i < tops.values_num; i++)
			{
				zbx_diag_map_t			*map = (zbx_diag_map_t *)tops.values[i];
				int				j, limit;

				if (0 == strcmp(map->name, "values"))
				{
					zbx_vector_ptr_sort(&items, diag_valuecache_item_compare_values);
				}
				else if (0 == strcmp(map->name, "request.values"))
				{
					zbx_vector_ptr_sort(&items, diag_valuecache_item_compare_hourly);
				}
				else
				{
					*error = zbx_dsprintf(*error, "Unsupported top field: %s", map->name);
					ret = FAIL;
					break;
				}

				limit = MIN((int)map->value, items.values_num);

				zbx_json_addarray(json, map->name);

				for (j = 0; j < limit; j++)
					diag_valuecache_add_item(json, items.values[j]);

				zbx_json_close(json);

			}
			zbx_json_close(json);

			zbx_vector_ptr_clear_ext(&items, zbx_ptr_free);
			zbx_vector_ptr_destroy(&items);
		}

		zbx_json_addfloat(json, "time", time_total);

		zbx_json_close(json);
	}

	zbx_vector_ptr_clear_ext(&tops, (zbx_ptr_free_func_t)diag_map_free);
	zbx_vector_ptr_destroy(&tops);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: diag_add_section_info                                            *
 *                                                                            *
 * Purpose: add requested section diagnostic information                      *
 *                                                                            *
 * Parameters: section - [IN] the section name                                *
 *             jp      - [IN] the request                                     *
 *             j       - [IN/OUT] the json to update                          *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the information was retrieved successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	diag_add_section_info(const char *section, const struct zbx_json_parse *jp, struct zbx_json *j,
		char **error)
{
	int	ret = FAIL;

	if (0 == strcmp(section, "historycache"))
		ret = diag_add_historycache_info(jp, j, error);
	if (0 == strcmp(section, "valuecache"))
		ret = diag_add_valuecache_info(jp, j, error);
	else
		*error = zbx_dsprintf(*error, "Unsupported diagnostics section: %s", section);


	return ret;
}
