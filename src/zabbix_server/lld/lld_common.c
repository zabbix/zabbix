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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "log.h"
#include "zbxdbhigh.h"

#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"
#include "audit/zbxaudit_graph.h"
#include "audit/zbxaudit_trigger.h"

void	lld_field_str_rollback(char **field, char **field_orig, zbx_uint64_t *flags, zbx_uint64_t flag)
{
	if (0 == (*flags & flag))
		return;

	zbx_free(*field);
	*field = *field_orig;
	*field_orig = NULL;
	*flags &= ~flag;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate when to delete lost resources in an overflow-safe way   *
 *                                                                            *
 ******************************************************************************/
int	lld_end_of_life(int lastcheck, int lifetime)
{
	return ZBX_JAN_2038 - lastcheck > lifetime ? lastcheck + lifetime : ZBX_JAN_2038;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates lastcheck and ts_delete fields; removes lost resources    *
 *                                                                            *
 ******************************************************************************/
void	lld_remove_lost_objects(const char *table, const char *id_name, const zbx_vector_ptr_t *objects,
		int lifetime, int lastcheck, delete_ids_f cb, get_object_info_f cb_info)
{
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		del_ids, lc_ids, ts_ids;
	zbx_vector_uint64_pair_t	discovery_ts;
	int				i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == objects->values_num)
		goto out;

	zbx_vector_uint64_create(&del_ids);
	zbx_vector_uint64_create(&lc_ids);
	zbx_vector_uint64_create(&ts_ids);
	zbx_vector_uint64_pair_create(&discovery_ts);

	for (i = 0; i < objects->values_num; i++)
	{
		zbx_uint64_t	id;
		int		discovery_flag, object_lastcheck, object_ts_delete;
		const char	*name;

		cb_info(objects->values[i], &id, &discovery_flag, &object_lastcheck, &object_ts_delete, &name);

		if (0 == id)
			continue;

		if (0 == discovery_flag)
		{
			int	ts_delete = lld_end_of_life(object_lastcheck, lifetime);

			if (lastcheck > ts_delete)
			{
				zbx_vector_uint64_append(&del_ids, id);

				if (0 == strcmp(table, "item_discovery"))
				{
					zbx_audit_item_create_entry_for_delete(id, name,
							(int)ZBX_FLAG_DISCOVERY_CREATED);
				}
				else if (0 == strcmp(table, "graph_discovery"))
				{
					zbx_audit_graph_create_entry(ZBX_AUDIT_ACTION_DELETE, id, name,
							(int)ZBX_FLAG_DISCOVERY_CREATED);
				}
				else if (0 == strcmp(table, "trigger_discovery"))
				{
					zbx_audit_trigger_create_entry(ZBX_AUDIT_ACTION_DELETE, id, name,
							ZBX_FLAG_DISCOVERY_CREATED);
				}
			}
			else if (object_ts_delete != ts_delete)
			{
				zbx_uint64_pair_t	pair;

				pair.first = id;
				pair.second = ts_delete;
				zbx_vector_uint64_pair_append(&discovery_ts, pair);
			}
		}
		else
		{
			zbx_vector_uint64_append(&lc_ids, id);
			if (0 != object_ts_delete)
				zbx_vector_uint64_append(&ts_ids, id);
		}
	}

	if (0 == discovery_ts.values_num && 0 == lc_ids.values_num && 0 == ts_ids.values_num &&
			0 == del_ids.values_num)
	{
		goto clean;
	}

	/* update discovery table */

	DBbegin();

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < discovery_ts.values_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update %s"
				" set ts_delete=%d"
				" where %s=" ZBX_FS_UI64 ";\n",
				table, (int)discovery_ts.values[i].second, id_name, discovery_ts.values[i].first);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != lc_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set lastcheck=%d where",
				table, lastcheck);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				lc_ids.values, lc_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != ts_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ts_delete=0 where",
				table);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				ts_ids.values, ts_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	/* remove 'lost' objects */
	if (0 != del_ids.values_num)
	{
		zbx_vector_uint64_sort(&del_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		cb(&del_ids);
	}

	DBcommit();
clean:
	zbx_vector_uint64_pair_destroy(&discovery_ts);
	zbx_vector_uint64_destroy(&ts_ids);
	zbx_vector_uint64_destroy(&lc_ids);
	zbx_vector_uint64_destroy(&del_ids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
