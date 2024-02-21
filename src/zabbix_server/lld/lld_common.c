/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "lld.h"

#include "zbxdbhigh.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"
#include "audit/zbxaudit_graph.h"
#include "audit/zbxaudit_trigger.h"

ZBX_VECTOR_DECL(id_name_pair, zbx_id_name_pair_t)
ZBX_VECTOR_IMPL(id_name_pair, zbx_id_name_pair_t)

int	lld_ids_names_compare_func(const void *d1, const void *d2)
{
	const zbx_id_name_pair_t	*id_name_pair_entry_1 = (const zbx_id_name_pair_t *)d1;
	const zbx_id_name_pair_t	*id_name_pair_entry_2 = (const zbx_id_name_pair_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(id_name_pair_entry_1->id, id_name_pair_entry_2->id);

	return 0;
}

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
 * Purpose: removes or disable lost resources                                 *
 *                                                                            *
 ******************************************************************************/
void	lld_process_lost_objects(const char *table_obj, const char *table, const char *id_name,
		const zbx_vector_ptr_t *objects, zbx_lld_lifetime_t *lifetime, zbx_lld_lifetime_t *enabled_lifetime,
		int lastcheck, delete_ids_f cb, get_object_info_f cb_info, object_audit_entry_create_f cb_audit_create,
		object_audit_entry_update_status_f cb_audit_update_status, get_object_status_val cb_status)
{
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		del_ids, lc_ids, ts_ids, dis_ts_ids, discovered_ids, lost_ids, upd_ids, dis_ids;
	zbx_vector_uint64_pair_t	discovery_ts, discovery_dis_ts;
	zbx_vector_id_name_pair_t	dis_objs, en_objs;
	int				i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == objects->values_num)
		goto out;

	zbx_vector_uint64_create(&del_ids);
	zbx_vector_uint64_create(&lc_ids);
	zbx_vector_uint64_create(&ts_ids);
	zbx_vector_uint64_create(&dis_ts_ids);
	zbx_vector_uint64_create(&discovered_ids);
	zbx_vector_uint64_create(&lost_ids);
	zbx_vector_uint64_create(&upd_ids);
	zbx_vector_uint64_pair_create(&discovery_ts);
	zbx_vector_uint64_pair_create(&discovery_dis_ts);
	zbx_vector_id_name_pair_create(&dis_objs);
	zbx_vector_id_name_pair_create(&en_objs);
	zbx_vector_uint64_create(&dis_ids);

	for (i = 0; i < objects->values_num; i++)
	{
		zbx_uint64_t	id;
		int		discovery_flag, object_lastcheck, object_ts_delete, object_ts_disable;
		unsigned char	discovery_status;
		const char	*name;

		cb_info(objects->values[i], &id, &discovery_flag, &object_lastcheck, &discovery_status,
				&object_ts_delete, &object_ts_disable, &name);

		if (0 == id)
			continue;

		if (0 == discovery_flag)
		{
			int	ts_delete, ts_disable;

			if (ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY == lifetime->type ||
					(ZBX_LLD_LIFETIME_TYPE_AFTER == lifetime->type && lastcheck > (ts_delete =
					lld_end_of_life(object_lastcheck, lifetime->duration))))
			{
				zbx_vector_uint64_append(&del_ids, id);
				cb_audit_create(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_DELETE, id, name,
						(int)ZBX_FLAG_DISCOVERY_CREATED);
				continue;
			}

			if (ZBX_LLD_DISCOVERY_STATUS_LOST != discovery_status)
				zbx_vector_uint64_append(&lost_ids, id);

			if (ZBX_LLD_LIFETIME_TYPE_NEVER == lifetime->type && 0 != object_ts_delete)
			{
				zbx_vector_uint64_append(&ts_ids, id);
			}
			else if (ZBX_LLD_LIFETIME_TYPE_AFTER == lifetime->type && ts_delete != object_ts_delete)
			{
				zbx_uint64_pair_t	pair = {.first = id, .second = (zbx_uint64_t)ts_delete};

				zbx_vector_uint64_pair_append(&discovery_ts, pair);
			}

			if (NULL == enabled_lifetime)
				continue;

			if (ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY == enabled_lifetime->type ||
					(ZBX_LLD_LIFETIME_TYPE_AFTER == enabled_lifetime->type && lastcheck >
					(ts_disable = lld_end_of_life(object_lastcheck, enabled_lifetime->duration))))
			{
				zbx_id_name_pair_t	id_name_pair = {.id = id, .name = name};

				zbx_vector_id_name_pair_append(&dis_objs, id_name_pair);
			}
			else if (ZBX_LLD_LIFETIME_TYPE_NEVER == enabled_lifetime->type && 0 != object_ts_disable)
			{
				zbx_vector_uint64_append(&dis_ts_ids, id);
			}
			else if (ZBX_LLD_LIFETIME_TYPE_AFTER == enabled_lifetime->type &&
					ts_disable != object_ts_disable)
			{
				zbx_uint64_pair_t	pair = {.first = id, .second = (zbx_uint64_t)ts_disable};

				zbx_vector_uint64_pair_append(&discovery_dis_ts, pair);
			}
		}
		else
		{
			zbx_vector_uint64_append(&lc_ids, id);

			if (ZBX_LLD_DISCOVERY_STATUS_NORMAL != discovery_status)
				zbx_vector_uint64_append(&discovered_ids, id);

			if (NULL == enabled_lifetime)
			{
				zbx_id_name_pair_t	id_name_pair = {.id = id, .name = name};

				zbx_vector_id_name_pair_append(&en_objs, id_name_pair);
			}
		}
	}

	if (0 == discovery_ts.values_num && 0 == lc_ids.values_num && 0 == ts_ids.values_num &&
			0 == del_ids.values_num && 0 == discovery_dis_ts.values_num && 0 == dis_objs.values_num &&
			0 == en_objs.values_num && 0 == dis_ts_ids.values_num && 0 == discovery_dis_ts.values_num &&
			0 == lost_ids.values_num && 0 == discovered_ids.values_num)
	{
		goto clean;
	}

	zbx_db_begin();

	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* skip objects whose status or discovery data should not be changed */
	if (NULL != enabled_lifetime)
	{
		char			*s = NULL;
		size_t			s_alloc = 0, s_offset = 0;
		zbx_vector_uint64_t	en_ids;
		zbx_db_result_t		result;
		zbx_db_row_t		row;

		zbx_vector_uint64_create(&en_ids);

		zbx_snprintf_alloc(&s, &s_alloc, &s_offset,
				"select o.%s,o.status,d.disable_source"
				" from %s o"
				" join %s d on o.%s=d.%s"
				" where",
				id_name, table_obj, table, id_name, id_name);
		zbx_db_add_condition_alloc(&s, &s_alloc, &s_offset, id_name, upd_ids.values, upd_ids.values_num);

		result = zbx_db_select("%s", s);

		zbx_free(s);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_id_name_pair_t	id_name_pair;

			ZBX_STR2UINT64(id_name_pair.id, row[0]);

			if (atoi(row[1]) == cb_status(ZBX_LLD_OBJECT_STATUS_ENABLED))
			{
				if (FAIL != (i = zbx_vector_id_name_pair_bsearch(&dis_objs, id_name_pair,
						lld_ids_names_compare_func)))
				{
					cb_audit_create(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE, dis_objs.values[i].id,
							dis_objs.values[i].name, (int)ZBX_FLAG_DISCOVERY_CREATED);
					cb_audit_update_status(ZBX_AUDIT_LLD_CONTEXT, dis_objs.values[i].id,
							(int)ZBX_FLAG_DISCOVERY_CREATED,
							cb_status(ZBX_LLD_OBJECT_STATUS_ENABLED),
							cb_status(ZBX_LLD_OBJECT_STATUS_DISABLED));
					zbx_vector_uint64_append(&dis_ids, dis_objs.values[i].id);
				}

				continue;
			}

			if (FAIL != (i = zbx_vector_id_name_pair_bsearch(&en_objs, id_name_pair,
					lld_ids_names_compare_func)))
			{
				unsigned char	disable_source;

				ZBX_STR2UCHAR(disable_source, row[2]);

				if (ZBX_LLD_DISABLE_SOURCE_LLD_LOST == disable_source)
				{
					cb_audit_create(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE,
							en_objs.values[i].id, en_objs.values[i].name,
							(int)ZBX_FLAG_DISCOVERY_CREATED);
					cb_audit_update_status(ZBX_AUDIT_LLD_CONTEXT, en_objs.values[i].id,
							(int)ZBX_FLAG_DISCOVERY_CREATED,
							cb_status(ZBX_LLD_OBJECT_STATUS_DISABLED),
							cb_status(ZBX_LLD_OBJECT_STATUS_ENABLED));
					zbx_vector_uint64_append(&en_ids, en_objs.values[i].id);
				}
			}
			else if (FAIL != (i = zbx_vector_uint64_bsearch(&dis_ts_ids, id_name_pair.id,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_vector_uint64_remove(&dis_ts_ids, i);
			}
			else if (FAIL != (i = zbx_vector_uint64_pair_search(&discovery_dis_ts,
					*(zbx_uint64_pair_t*)&id_name_pair, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				zbx_vector_uint64_pair_remove_noorder(&discovery_dis_ts, i);
			}

		}
		zbx_db_free_result(result);

		/* udpate object table */

		if (0 != dis_ids.values_num)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set status=%d where",
					table_obj, cb_status(ZBX_LLD_OBJECT_STATUS_DISABLED));
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
					dis_ids.values, dis_ids.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		if (0 != en_ids.values_num)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set status=%d where",
					table_obj, cb_status(ZBX_LLD_OBJECT_STATUS_ENABLED));
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
					en_ids.values, en_ids.values_num);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		zbx_vector_uint64_destroy(&en_ids);
	}

	/* update discovery table */

	for (i = 0; i < discovery_ts.values_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update %s"
				" set ts_delete=%d"
				" where %s=" ZBX_FS_UI64 ";\n",
				table, (int)discovery_ts.values[i].second, id_name, discovery_ts.values[i].first);

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != lc_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set lastcheck=%d where",
				table, lastcheck);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				lc_ids.values, lc_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != lost_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set status=%d where",
				table, ZBX_LLD_DISCOVERY_STATUS_LOST);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				lost_ids.values, lost_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != discovered_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set status=%d where",
				table, ZBX_LLD_DISCOVERY_STATUS_NORMAL);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				discovered_ids.values, discovered_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != ts_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ts_delete=0 where",
				table);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				ts_ids.values, ts_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < discovery_dis_ts.values_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update %s"
				" set ts_disable=%d"
				" where %s=" ZBX_FS_UI64 ";\n",
				table, (int)discovery_dis_ts.values[i].second, id_name,
				discovery_dis_ts.values[i].first);

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != dis_ts_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ts_disable=0 where",
				table);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				dis_ts_ids.values, dis_ts_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != dis_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set disable_source=%d where",
				table, ZBX_LLD_DISABLE_SOURCE_LLD_LOST);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name, dis_ids.values, dis_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
		zbx_db_execute("%s", sql);

	zbx_free(sql);

	/* remove 'lost' objects */
	if (0 != del_ids.values_num)
	{
		zbx_vector_uint64_sort(&del_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		cb(&del_ids, ZBX_AUDIT_LLD_CONTEXT);
	}

	zbx_db_commit();
clean:
	zbx_vector_uint64_destroy(&del_ids);
	zbx_vector_uint64_destroy(&lc_ids);
	zbx_vector_uint64_destroy(&ts_ids);
	zbx_vector_uint64_destroy(&dis_ts_ids);
	zbx_vector_uint64_destroy(&discovered_ids);
	zbx_vector_uint64_destroy(&lost_ids);
	zbx_vector_uint64_destroy(&upd_ids);
	zbx_vector_uint64_pair_destroy(&discovery_ts);
	zbx_vector_uint64_pair_destroy(&discovery_dis_ts);
	zbx_vector_id_name_pair_destroy(&dis_objs);
	zbx_vector_id_name_pair_destroy(&en_objs);
	zbx_vector_uint64_destroy(&dis_ids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
