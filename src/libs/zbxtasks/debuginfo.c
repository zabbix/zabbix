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
#include "log.h"
#include "zbxalgo.h"
#include "zbxjson.h"
#include "memalloc.h"
#include "dbcache.h"

#define ZBX_DI_SECTION_MAX	64
#define ZBX_DI_FIELD_MAX	64

#define ZBX_DI_STATS_ALL			0xFFFFFFFF

#define ZBX_DI_HISTORYCACHE_ITEMS	0x00000001
#define ZBX_DI_HISTORYCACHE_VALUES	0x00000002
#define ZBX_DI_HISTORYCACHE_MEMDATA	0x00000004
#define ZBX_DI_HISTORYCACHE_MEMINDEX	0x00000008
#define ZBX_DI_HISTORYCACHE_MEMTRENDS	0x00000010

#define ZBX_DI_HISTORYCACHE_SIMPLE	(ZBX_DI_HISTORYCACHE_ITEMS | \
					ZBX_DI_HISTORYCACHE_VALUES)

#define ZBX_DI_HISTORYCACHE_MEM		(ZBX_DI_HISTORYCACHE_MEMDATA | \
					ZBX_DI_HISTORYCACHE_MEMINDEX | \
					ZBX_DI_HISTORYCACHE_MEMTRENDS)

typedef struct
{
	char		*name;
	zbx_uint64_t	value;
}
zbx_di_map_t;

static void	di_map_free(zbx_di_map_t *map)
{
	zbx_free(map->name);
	zbx_free(map);
}

static int	tm_parse_debuginfo_request(struct zbx_json_parse *jp, const zbx_di_map_t *stats,
		zbx_uint64_t *fields, zbx_vector_ptr_t *tops, char **error)
{
	struct zbx_json_parse	jp_stats;
	int			ret = FAIL;
	const char		*pnext = NULL;
	char			name[ZBX_DI_FIELD_MAX + 1], value[MAX_ID_LEN + 1];
	zbx_uint64_t		value_ui64;

	*fields = 0;

	/* parse stats fields */
	if (SUCCEED == zbx_json_brackets_by_name(jp, "stats", &jp_stats))
	{
		while (NULL != (pnext = zbx_json_next(&jp_stats, pnext)))
		{
			const zbx_di_map_t	*stat;

			zbx_json_decodevalue(pnext, name, sizeof(name), NULL);

			if (0 == strcmp(name, "all"))
			{
				*fields |= ZBX_DI_STATS_ALL;
				continue;
			}

			for (stat = stats;; stat++)
			{
				if (NULL == stat->name)
				{
					*error = zbx_dsprintf(*error, "Unknown statistics field: %s", name);
					goto out;
				}

				if (0 == strcmp(name, stat->name))
					break;
			}

			*fields |= stat->value;
		}
	}

	/* parse top requests */
	if (SUCCEED == zbx_json_brackets_by_name(jp, "top", &jp_stats))
	{
		while (NULL != (pnext = zbx_json_pair_next(&jp_stats, pnext, name, sizeof(name))))
		{
			zbx_di_map_t	*top;

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

			top = (zbx_di_map_t *)zbx_malloc(NULL, sizeof(zbx_di_map_t));
			top->name = zbx_strdup(NULL, name);
			top->value = value_ui64;
			zbx_vector_ptr_append(tops, top);
		}
	}
	ret = SUCCEED;
out:
	if (FAIL == ret)
		zbx_vector_ptr_clear_ext(tops, (zbx_ptr_free_func_t)di_map_free);

	return ret;
}

static void	tm_add_mem_stats(struct zbx_json *j, const char *section, const zbx_mem_stats_t *stats)
{
	int	i;

	if (NULL == stats)
		return;

	zbx_json_addobject(j, section);
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

static int	tm_get_debuginfo_historycache(struct zbx_json_parse *jp, struct zbx_json *j, char **error)
{
	zbx_vector_ptr_t	tops;
	int			ret;
	double			time1, time2, time_total;
	zbx_uint64_t		fields;
	zbx_di_map_t		statmap[] = {
			{"items", ZBX_DI_HISTORYCACHE_ITEMS},
			{"values", ZBX_DI_HISTORYCACHE_VALUES},
			{"memdata", ZBX_DI_HISTORYCACHE_MEMDATA},
			{"memindex", ZBX_DI_HISTORYCACHE_MEMINDEX},
			{"memtrends", ZBX_DI_HISTORYCACHE_MEMTRENDS},
			{NULL, 0}
	};

	zbx_vector_ptr_create(&tops);

	if (SUCCEED == (ret = tm_parse_debuginfo_request(jp, statmap, &fields, &tops, error)))
	{
		int	i;

		zbx_json_addobject(j, "historycache");

		if (0 != (fields & ZBX_DI_HISTORYCACHE_SIMPLE))
		{
			zbx_uint64_t	values_num, items_num;
			time1 = zbx_time();
			zbx_hc_get_simple_stats(&items_num, &values_num);
			time2 = zbx_time();
			time_total += time2 - time1;

			if (0 != (fields & ZBX_DI_HISTORYCACHE_ITEMS))
				zbx_json_addint64(j, "items", items_num);
			if (0 != (fields & ZBX_DI_HISTORYCACHE_VALUES))
				zbx_json_addint64(j, "values", values_num);
		}

		if (0 != (fields & ZBX_DI_HISTORYCACHE_MEM))
		{
			zbx_mem_stats_t	data_mem, index_mem, trends_mem, *pdata_mem, *pindex_mem, *ptrends_mem;

			pdata_mem = (0 != (fields & ZBX_DI_HISTORYCACHE_MEMDATA) ? &data_mem : NULL);
			pindex_mem = (0 != (fields & ZBX_DI_HISTORYCACHE_MEMINDEX) ? &index_mem : NULL);
			ptrends_mem = (0 != (fields & ZBX_DI_HISTORYCACHE_MEMTRENDS) ? &trends_mem : NULL);

			time1 = zbx_time();
			zbx_hc_get_mem_stats(pdata_mem, pindex_mem, ptrends_mem);
			time2 = zbx_time();
			time_total += time2 - time1;

			tm_add_mem_stats(j, "memdata", pdata_mem);
			tm_add_mem_stats(j, "memindex", pindex_mem);
			tm_add_mem_stats(j, "memtrends", ptrends_mem);
		}

		for (i = 0; i < tops.values_num; i++)
		{
			zbx_di_map_t	*map = (zbx_di_map_t *)tops.values[i];

			/* TODO: process top requests */
		}

		zbx_json_close(j);
	}

	zbx_vector_ptr_clear_ext(&tops, (zbx_ptr_free_func_t)di_map_free);
	zbx_vector_ptr_destroy(&tops);

	return ret;
}

int	zbx_tm_get_debuginfo(const char *request, char **info)
{
	struct zbx_json_parse	jp, jp_section;
	char			section[ZBX_DI_SECTION_MAX + 1];
	const char		*pnext = NULL;
	struct zbx_json		j;
	int			ret = SUCCEED;

	if (SUCCEED != zbx_json_open(request, &jp))
	{
		*info = zbx_strdup(*info, zbx_json_strerror());
		return FAIL;
	}

	zbx_json_init(&j, 1024);

	while (NULL != (pnext = zbx_json_pair_next(&jp, pnext, section, sizeof(section))))
	{
		if (FAIL == (ret = zbx_json_brackets_open(pnext, &jp_section)))
		{
			*info = zbx_strdup(*info, zbx_json_strerror());
			goto out;
		}

		if (0 == strcmp(section, "historycache"))
		{
			if (FAIL == (ret = tm_get_debuginfo_historycache(&jp_section, &j, info)))
				goto out;
		}
		else
		{
			*info = zbx_dsprintf(*info, "Unknown debuginfo section: %s", section);
			ret = FAIL;
			goto out;
		}
	}
out:
	if (SUCCEED == ret)
		*info = zbx_strdup(*info, j.buffer);

	zbx_json_free(&j);

	return ret;
}
