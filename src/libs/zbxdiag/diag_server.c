/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "diag.h"

#include "common.h"
#include "../../libs/zbxdbcache/valuecache.h"
#include "zbxlld.h"
#include "zbxalert.h"
#include "zbxdiag.h"

/******************************************************************************
 *                                                                            *
 * Purpose: sort itemid,values_num pair by values_num in descending order     *
 *                                                                            *
 ******************************************************************************/
static int	diag_valuecache_item_compare_values(const void *d1, const void *d2)
{
	zbx_vc_item_stats_t	*i1 = *(zbx_vc_item_stats_t **)d1;
	zbx_vc_item_stats_t	*i2 = *(zbx_vc_item_stats_t **)d2;

	return i2->values_num - i1->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sort itemid,values_num pair by hourly_num in descending order     *
 *                                                                            *
 ******************************************************************************/
static int	diag_valuecache_item_compare_hourly(const void *d1, const void *d2)
{
	zbx_vc_item_stats_t	*i1 = *(zbx_vc_item_stats_t **)d1;
	zbx_vc_item_stats_t	*i2 = *(zbx_vc_item_stats_t **)d2;

	return i2->hourly_num - i1->hourly_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add valuecache items diagnostic statistics to json                *
 *                                                                            *
 ******************************************************************************/
static void	diag_valuecache_add_items(struct zbx_json *json, const char *field, zbx_vc_item_stats_t **items,
		int items_num)
{
	int	i;

	zbx_json_addarray(json, field);

	for (i = 0; i < items_num; i++)
	{
		zbx_json_addobject(json, NULL);
		zbx_json_addint64(json, "itemid", items[i]->itemid);
		zbx_json_addint64(json, "values", items[i]->values_num);
		zbx_json_addint64(json, "request.values", items[i]->hourly_num);
		zbx_json_close(json);
	}
	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add requested value cache diagnostic information to json data     *
 *                                                                            *
 * Parameters: jp    - [IN] the request                                       *
 *             json  - [IN/OUT] the json to update                            *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - the information was added successfully             *
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
					{"", ZBX_DIAG_VALUECACHE_SIMPLE | ZBX_DIAG_VALUECACHE_MEMORY},
					{"items", ZBX_DIAG_VALUECACHE_ITEMS},
					{"values", ZBX_DIAG_VALUECACHE_VALUES},
					{"mode", ZBX_DIAG_VALUECACHE_MODE},
					{"memory", ZBX_DIAG_VALUECACHE_MEMORY},
					{NULL, 0}
					};

	zbx_vector_ptr_create(&tops);

	if (SUCCEED == (ret = diag_parse_request(jp, field_map, &fields, &tops, error)))
	{
		zbx_json_addobject(json, ZBX_DIAG_VALUECACHE);

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
			zbx_vc_get_item_stats(&items);
			time2 = zbx_time();
			time_total += time2 - time1;

			zbx_json_addobject(json, "top");

			for (i = 0; i < tops.values_num; i++)
			{
				zbx_diag_map_t	*map = (zbx_diag_map_t *)tops.values[i];
				int		limit;

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
					goto out;
				}

				limit = MIN((int)map->value, items.values_num);
				diag_valuecache_add_items(json, map->name, (zbx_vc_item_stats_t **)items.values, limit);
			}
			zbx_json_close(json);

			zbx_vector_ptr_clear_ext(&items, zbx_ptr_free);
			zbx_vector_ptr_destroy(&items);
		}

		zbx_json_addfloat(json, "time", time_total);

		zbx_json_close(json);
	}
out:
	zbx_vector_ptr_clear_ext(&tops, (zbx_ptr_free_func_t)diag_map_free);
	zbx_vector_ptr_destroy(&tops);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add lld item top list to output json                              *
 *                                                                            *
 ******************************************************************************/
static void	diag_add_lld_items(struct zbx_json *json, const char *field, const zbx_vector_uint64_pair_t *items)
{
	int	i;

	zbx_json_addarray(json, field);

	for (i = 0; i < items->values_num; i++)
	{
		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, "itemid", items->values[i].first);
		zbx_json_adduint64(json, "values", items->values[i].second);
		zbx_json_close(json);
	}

	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add requested lld manager diagnostic information to json data     *
 *                                                                            *
 * Parameters: jp    - [IN] the request                                       *
 *             json  - [IN/OUT] the json to update                            *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - the information was added successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	diag_add_lld_info(const struct zbx_json_parse *jp, struct zbx_json *json, char **error)
{
	zbx_vector_ptr_t	tops;
	int			ret;
	double			time1, time2, time_total = 0;
	zbx_uint64_t		fields;
	zbx_diag_map_t		field_map[] = {
					{"", ZBX_DIAG_LLD_SIMPLE},
					{"rules", ZBX_DIAG_LLD_RULES},
					{"values", ZBX_DIAG_LLD_VALUES},
					{NULL, 0}
					};

	zbx_vector_ptr_create(&tops);

	if (SUCCEED == (ret = diag_parse_request(jp, field_map, &fields, &tops, error)))
	{
		zbx_json_addobject(json, ZBX_DIAG_LLD);

		if (0 != (fields & ZBX_DIAG_LLD_SIMPLE))
		{
			zbx_uint64_t	values_num, items_num;

			time1 = zbx_time();
			if (FAIL == (ret = zbx_lld_get_diag_stats(&items_num, &values_num, error)))
				goto out;
			time2 = zbx_time();
			time_total += time2 - time1;

			if (0 != (fields & ZBX_DIAG_LLD_RULES))
				zbx_json_addint64(json, "rules", items_num);
			if (0 != (fields & ZBX_DIAG_LLD_VALUES))
				zbx_json_addint64(json, "values", values_num);
		}

		if (0 != tops.values_num)
		{
			int	i;

			zbx_json_addobject(json, "top");

			for (i = 0; i < tops.values_num; i++)
			{
				zbx_diag_map_t	*map = (zbx_diag_map_t *)tops.values[i];

				if (0 == strcmp(map->name, "values"))
				{
					zbx_vector_uint64_pair_t	items;

					zbx_vector_uint64_pair_create(&items);

					time1 = zbx_time();
					if (FAIL == (ret = zbx_lld_get_top_items(map->value, &items, error)))
					{
						zbx_vector_uint64_pair_destroy(&items);
						goto out;
					}
					time2 = zbx_time();
					time_total += time2 - time1;

					diag_add_lld_items(json, map->name, &items);
					zbx_vector_uint64_pair_destroy(&items);
				}
				else
				{
					*error = zbx_dsprintf(*error, "Unsupported top field: %s", map->name);
					ret = FAIL;
					goto out;
				}
			}

			zbx_json_close(json);
		}

		zbx_json_addfloat(json, "time", time_total);
		zbx_json_close(json);
	}
out:
	zbx_vector_ptr_clear_ext(&tops, (zbx_ptr_free_func_t)diag_map_free);
	zbx_vector_ptr_destroy(&tops);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add mediatype top list to output json                             *
 *                                                                            *
 * Parameters: json  - [OUT] the output json                                  *
 *             field - [IN] the field name                                    *
 *             items - [IN] a top mediatype list consisting of                *
 *                          mediatype, alerts_num pairs                       *
 *                                                                            *
 ******************************************************************************/
static void	diag_add_alerting_mediatypes(struct zbx_json *json, const char *field,
		const zbx_vector_uint64_pair_t *mediatypes)
{
	int	i;

	zbx_json_addarray(json, field);

	for (i = 0; i < mediatypes->values_num; i++)
	{
		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, "mediatypeid", mediatypes->values[i].first);
		zbx_json_adduint64(json, "alerts", mediatypes->values[i].second);
		zbx_json_close(json);
	}

	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add alert source top list to output json                          *
 *                                                                            *
 * Parameters: json  - [OUT] the output json                                  *
 *             field - [IN] the field name                                    *
 *             items - [IN] a top alert source list consisting of             *
 *                          zbx_am_source_stats_t structures                  *
 *                                                                            *
 ******************************************************************************/
static void	diag_add_alerting_sources(struct zbx_json *json, const char *field, const zbx_vector_ptr_t *sources)
{
	int	i;

	zbx_json_addarray(json, field);

	for (i = 0; i < sources->values_num; i++)
	{
		const zbx_am_source_stats_t	*source = (const zbx_am_source_stats_t *)sources->values[i];

		zbx_json_addobject(json, NULL);
		zbx_json_adduint64(json, "source", source->source);
		zbx_json_adduint64(json, "object", source->object);
		zbx_json_adduint64(json, "objectid", source->objectid);
		zbx_json_adduint64(json, "alerts", source->alerts_num);
		zbx_json_close(json);
	}

	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add requested alert manager diagnostic information to json data   *
 *                                                                            *
 * Parameters: jp    - [IN] the request                                       *
 *             json  - [IN/OUT] the json to update                            *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - the information was added successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	diag_add_alerting_info(const struct zbx_json_parse *jp, struct zbx_json *json, char **error)
{
	zbx_vector_ptr_t	tops;
	int			ret;
	double			time1, time2, time_total = 0;
	zbx_uint64_t		fields;
	zbx_diag_map_t		field_map[] = {
					{"", ZBX_DIAG_ALERTING_SIMPLE},
					{"alerts", ZBX_DIAG_ALERTING_ALERTS},
					{NULL, 0}
					};

	zbx_vector_ptr_create(&tops);

	if (SUCCEED == (ret = diag_parse_request(jp, field_map, &fields, &tops, error)))
	{
		zbx_json_addobject(json, ZBX_DIAG_ALERTING);

		if (0 != (fields & ZBX_DIAG_ALERTING_SIMPLE))
		{
			zbx_uint64_t	alerts_num;

			time1 = zbx_time();
			if (FAIL == (ret = zbx_alerter_get_diag_stats(&alerts_num, error)))
				goto out;
			time2 = zbx_time();
			time_total += time2 - time1;

			if (0 != (fields & ZBX_DIAG_ALERTING_ALERTS))
				zbx_json_addint64(json, "alerts", alerts_num);
		}

		if (0 != tops.values_num)
		{
			int	i;

			zbx_json_addobject(json, "top");

			for (i = 0; i < tops.values_num; i++)
			{
				zbx_diag_map_t	*map = (zbx_diag_map_t *)tops.values[i];

				if (0 == strcmp(map->name, "media.alerts"))
				{
					zbx_vector_uint64_pair_t	mediatypes;

					zbx_vector_uint64_pair_create(&mediatypes);

					time1 = zbx_time();
					if (FAIL == (ret = zbx_alerter_get_top_mediatypes(map->value, &mediatypes,
							error)))
					{
						zbx_vector_uint64_pair_destroy(&mediatypes);
						goto out;
					}
					time2 = zbx_time();
					time_total += time2 - time1;

					diag_add_alerting_mediatypes(json, map->name, &mediatypes);
					zbx_vector_uint64_pair_destroy(&mediatypes);
				}
				else if (0 == strcmp(map->name, "source.alerts"))
				{
					zbx_vector_ptr_t	sources;

					zbx_vector_ptr_create(&sources);

					time1 = zbx_time();
					if (FAIL == (ret = zbx_alerter_get_top_sources(map->value, &sources, error)))
					{
						zbx_vector_ptr_clear_ext(&sources, zbx_ptr_free);
						zbx_vector_ptr_destroy(&sources);
						goto out;
					}
					time2 = zbx_time();
					time_total += time2 - time1;

					diag_add_alerting_sources(json, map->name, &sources);
					zbx_vector_ptr_clear_ext(&sources, zbx_ptr_free);
					zbx_vector_ptr_destroy(&sources);
				}
				else
				{
					*error = zbx_dsprintf(*error, "Unsupported top field: %s", map->name);
					ret = FAIL;
					goto out;
				}
			}

			zbx_json_close(json);
		}

		zbx_json_addfloat(json, "time", time_total);
		zbx_json_close(json);
	}
out:
	zbx_vector_ptr_clear_ext(&tops, (zbx_ptr_free_func_t)diag_map_free);
	zbx_vector_ptr_destroy(&tops);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add requested section diagnostic information                      *
 *                                                                            *
 * Parameters: section - [IN] the section name                                *
 *             jp      - [IN] the request                                     *
 *             json    - [IN/OUT] the json to update                          *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the information was retrieved successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	diag_add_section_info(const char *section, const struct zbx_json_parse *jp, struct zbx_json *json,
		char **error)
{
	int	ret = FAIL;

	if (0 == strcmp(section, ZBX_DIAG_HISTORYCACHE))
		ret = diag_add_historycache_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_VALUECACHE))
		ret = diag_add_valuecache_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_PREPROCESSING))
		ret = diag_add_preproc_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_LLD))
		ret = diag_add_lld_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_ALERTING))
		ret = diag_add_alerting_info(jp, json, error);
	else if (0 == strcmp(section, ZBX_DIAG_LOCKS))
	{
		diag_add_locks_info(json);
		ret = SUCCEED;
	}
	else
		*error = zbx_dsprintf(*error, "Unsupported diagnostics section: %s", section);

	return ret;
}
