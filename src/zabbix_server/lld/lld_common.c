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
#include "../server_constants.h"

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
 * Purpose: removes lost resources                                            *
 *                                                                            *
 ******************************************************************************/
void	lld_remove_lost_objects(const char *table, const char *id_name, zbx_vector_ptr_t *objects,
		zbx_lld_lifetime_t *lifetime, int lastcheck, delete_ids_f cb, get_object_info_f cb_info,
		set_object_flag_delete cb_set_flag_del, object_audit_entry_create_f cb_audit_create)
{
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		del_ids, lc_ids, ts_ids, discovered_ids, lost_ids;
	zbx_vector_uint64_pair_t	discovery_ts;
	int				i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == objects->values_num)
		goto out;

	zbx_vector_uint64_create(&del_ids);
	zbx_vector_uint64_create(&lc_ids);
	zbx_vector_uint64_create(&ts_ids);
	zbx_vector_uint64_create(&discovered_ids);
	zbx_vector_uint64_create(&lost_ids);
	zbx_vector_uint64_pair_create(&discovery_ts);

	for (i = 0; i < objects->values_num; i++)
	{
		zbx_uint64_t	id;
		int		discovery_flag, object_lastcheck, object_ts_delete, ts_delete;
		unsigned char	discovery_status;
		const char	*name;

		cb_info(objects->values[i], &id, &discovery_flag, &object_lastcheck, &discovery_status,
				&object_ts_delete, &name);

		if (0 == id)
			continue;

		if (0 != discovery_flag)
		{
			zbx_vector_uint64_append(&lc_ids, id);

			if (ZBX_LLD_DISCOVERY_STATUS_NORMAL != discovery_status)
				zbx_vector_uint64_append(&discovered_ids, id);

			continue;
		}

		if (ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY == lifetime->type ||
				(ZBX_LLD_LIFETIME_TYPE_AFTER == lifetime->type && lastcheck >
				(ts_delete = lld_end_of_life(object_lastcheck, lifetime->duration))))
		{
			zbx_vector_uint64_append(&del_ids, id);
			cb_set_flag_del(objects->values[i]);
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
	}

	if (0 == discovery_ts.values_num && 0 == lc_ids.values_num && 0 == ts_ids.values_num &&
			0 == del_ids.values_num && 0 == lost_ids.values_num && 0 == discovered_ids.values_num)
	{
		goto clean;
	}

	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

	zbx_db_begin();

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
	zbx_vector_uint64_destroy(&discovered_ids);
	zbx_vector_uint64_destroy(&lost_ids);
	zbx_vector_uint64_pair_destroy(&discovery_ts);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: disable lost resources                                            *
 *                                                                            *
 ******************************************************************************/
void	lld_disable_lost_objects(const char *table_obj, const char *table, const char *id_name,
		const zbx_vector_ptr_t *objects, zbx_lld_lifetime_t *enabled_lifetime, int lastcheck,
		get_object_info_disable_f cb_info_disable, get_object_status_val cb_status,
		object_audit_entry_create_f cb_audit_create, object_audit_entry_update_status_f cb_audit_update_status)
{
	int				i;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		upd_ids, en_ids, dis_ids, ts_ids;
	zbx_vector_uint64_pair_t	ts_upd;
	zbx_vector_id_name_pair_t	dis_objs, en_objs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == objects->values_num)
		goto out;

	zbx_vector_uint64_create(&upd_ids);
	zbx_vector_uint64_create(&en_ids);
	zbx_vector_uint64_create(&dis_ids);
	zbx_vector_uint64_create(&ts_ids);
	zbx_vector_uint64_pair_create(&ts_upd);
	zbx_vector_id_name_pair_create(&dis_objs);
	zbx_vector_id_name_pair_create(&en_objs);

	for (i = 0; i < objects->values_num; i++)
	{
		zbx_uint64_t	id;
		int		discovery_flag, del_flag, object_lastcheck, object_ts_disable, object_status,
				ts_disable, disable_source;
		char		*name;

		cb_info_disable(objects->values[i], &id, &discovery_flag, &del_flag, &object_lastcheck,
				&object_ts_disable, &object_status, &disable_source, &name);

		if (0 == id || 0 != del_flag)
			continue;

		if (0 != discovery_flag)
		{
			if (ZBX_LLD_OBJECT_STATUS_DISABLED == object_status &&
					ZBX_DISABLE_SOURCE_LLD_LOST == disable_source)
			{
				zbx_id_name_pair_t	id_name_pair = {.id = id, .name = name};

				zbx_vector_id_name_pair_append(&en_objs, id_name_pair);
				zbx_vector_uint64_append(&upd_ids, id);
			}

			continue;
		}

		if ((ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY == enabled_lifetime->type ||
				(ZBX_LLD_LIFETIME_TYPE_AFTER == enabled_lifetime->type && lastcheck >
				(ts_disable = lld_end_of_life(object_lastcheck, enabled_lifetime->duration)))) &&
				ZBX_LLD_OBJECT_STATUS_ENABLED == object_status)
		{
			zbx_id_name_pair_t	id_name_pair = {.id = id, .name = name};

			zbx_vector_id_name_pair_append(&dis_objs, id_name_pair);
			zbx_vector_uint64_append(&upd_ids, id);
		}

		if (ZBX_LLD_LIFETIME_TYPE_AFTER == enabled_lifetime->type && ts_disable != object_ts_disable)
		{
			zbx_uint64_pair_t	pair = {.first = id, .second = (zbx_uint64_t)ts_disable};

			zbx_vector_uint64_pair_append(&ts_upd, pair);
		}
		else if (ZBX_LLD_LIFETIME_TYPE_NEVER == enabled_lifetime->type && 0 != object_ts_disable)
		{
			zbx_vector_uint64_append(&ts_ids, id);
		}
	}

	if (0 == upd_ids.values_num && 0 == ts_upd.values_num && 0 == ts_ids.values_num)
		goto clean;

	zbx_db_begin();

	if (0 != upd_ids.values_num && SUCCEED == zbx_db_lock_records(table_obj, &upd_ids))
	{
		zbx_db_result_t	result;
		zbx_db_row_t	row;
		char		*idname;

		idname = zbx_dsprintf(NULL, "o.%s", id_name);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select o.%s,o.status,d.disable_source"
				" from %s o"
				" join %s d on o.%s=d.%s"
				" where",
				id_name, table_obj, table, id_name, id_name);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, idname, upd_ids.values, upd_ids.values_num);
		zbx_free(idname);

		result = zbx_db_select("%s", sql);
		sql_offset = 0;

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_id_name_pair_t	id_name_pair;

			ZBX_STR2UINT64(id_name_pair.id, row[0]);

			if (atoi(row[1]) == cb_status(ZBX_LLD_OBJECT_STATUS_ENABLED))
			{
				if (FAIL != (i = zbx_vector_id_name_pair_bsearch(&dis_objs, id_name_pair,
						lld_ids_names_compare_func)))
				{
					cb_audit_create(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_UPDATE,
							dis_objs.values[i].id, dis_objs.values[i].name,
							(int)ZBX_FLAG_DISCOVERY_CREATED);
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

				if (ZBX_DISABLE_SOURCE_LLD_LOST == disable_source)
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
		}
		zbx_db_free_result(result);
	}

	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

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

	/* update discovery table */

	for (i = 0; i < ts_upd.values_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update %s"
				" set ts_disable=%d"
				" where %s=" ZBX_FS_UI64 ";\n",
				table, (int)ts_upd.values[i].second, id_name,
				ts_upd.values[i].first);

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != ts_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ts_disable=0 where",
				table);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				ts_ids.values, ts_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != dis_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set disable_source=%d where",
				table, ZBX_DISABLE_SOURCE_LLD_LOST);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name, dis_ids.values, dis_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	zbx_db_end_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
		zbx_db_execute("%s", sql);

	zbx_db_commit();

	zbx_free(sql);
clean:
	zbx_vector_id_name_pair_destroy(&dis_objs);
	zbx_vector_id_name_pair_destroy(&en_objs);
	zbx_vector_uint64_pair_destroy(&ts_upd);
	zbx_vector_uint64_destroy(&ts_ids);
	zbx_vector_uint64_destroy(&dis_ids);
	zbx_vector_uint64_destroy(&en_ids);
	zbx_vector_uint64_destroy(&upd_ids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
