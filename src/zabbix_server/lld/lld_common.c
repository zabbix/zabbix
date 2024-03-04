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
#include "zbxdb.h"
#include "zbxnum.h"
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

static int	lld_get_lifetime_ts(int obj_lastcheck, zbx_lld_lifetime_t *lifetime)
{
	int	ts;

	if (ZBX_LLD_LIFETIME_TYPE_AFTER == lifetime->type)
		ts = lld_end_of_life(obj_lastcheck, lifetime->duration);
	else if (ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY == lifetime->type)
		ts = 1;
	else
		ts = 0;

	return ts;
}

static int	lld_check_lifetime_elapsed(int lastcheck, int ts)
{
	if (0 == ts || lastcheck <= ts)
		return FAIL;

	return SUCCEED;
}

static void	lld_prepare_ts_update(zbx_uint64_t id, int ts, int obj_ts, zbx_vector_uint64_t *reset_ts_ids,
		zbx_vector_uint64_t *imm_ts_ids, zbx_vector_uint64_pair_t *discovery_ts)
{
	if (ts == obj_ts)
		return;

	if (0 == ts)
	{
		zbx_vector_uint64_append(reset_ts_ids, id);
	}
	else if (1 == ts)
	{
		zbx_vector_uint64_append(imm_ts_ids, id);
	}
	else
	{
		zbx_uint64_pair_t	pair = {.first = id, .second = (zbx_uint64_t)ts};

		zbx_vector_uint64_pair_append(discovery_ts, pair);
	}
}

static void	lld_prepare_object_delete(zbx_uint64_t id, const char *name, zbx_vector_uint64_t *del_ids,
		zbx_vector_uint64_t *ts_ids, zbx_vector_uint64_pair_t *discovery_ts, zbx_vector_uint64_t *imm_ids,
		object_audit_entry_create_f cb_audit_create)
{
	int			i;
	zbx_uint64_pair_t	dts = {.first = id};

	zbx_vector_uint64_append(del_ids, id);

	if (FAIL != (i = zbx_vector_uint64_bsearch(ts_ids, id, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove(ts_ids, i);
	else if (FAIL != (i = zbx_vector_uint64_pair_bsearch(discovery_ts, dts, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_pair_remove(discovery_ts, i);
	else if (FAIL != (i = zbx_vector_uint64_bsearch(imm_ids, id, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		zbx_vector_uint64_remove(imm_ids, i);

	cb_audit_create(ZBX_AUDIT_LLD_CONTEXT, ZBX_AUDIT_ACTION_DELETE, id, name, (int)ZBX_FLAG_DISCOVERY_CREATED);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process lost resources                                            *
 *                                                                            *
 ******************************************************************************/
void	lld_process_lost_objects(const char *table, const char *table_obj, const char *id_name,
		zbx_vector_ptr_t *objects, zbx_lld_lifetime_t *lifetime, zbx_lld_lifetime_t *enabled_lifetime,
		int lastcheck, delete_ids_f cb, get_object_info_f cb_info, get_object_status_val cb_status,
		object_audit_entry_create_f cb_audit_create, object_audit_entry_update_status_f cb_audit_update_status)
{
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t		del_ids, en_ids, dis_ids, lc_ids, ts_ids, dis_ts_ids, discovered_ids, lost_ids,
					upd_ids, lock_ids, imm_ids, dis_imm_ids;
	zbx_vector_uint64_pair_t	discovery_ts, dis_discovery_ts;
	zbx_vector_id_name_pair_t	del_objs, dis_objs, en_objs;
	int				i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == objects->values_num)
		goto out;

	zbx_vector_uint64_create(&del_ids);
	zbx_vector_uint64_create(&en_ids);
	zbx_vector_uint64_create(&dis_ids);
	zbx_vector_uint64_create(&lc_ids);
	zbx_vector_uint64_create(&ts_ids);
	zbx_vector_uint64_create(&dis_ts_ids);
	zbx_vector_uint64_create(&imm_ids);
	zbx_vector_uint64_create(&dis_imm_ids);
	zbx_vector_uint64_create(&discovered_ids);
	zbx_vector_uint64_create(&lost_ids);
	zbx_vector_uint64_pair_create(&discovery_ts);
	zbx_vector_uint64_pair_create(&dis_discovery_ts);
	zbx_vector_uint64_create(&upd_ids);
	zbx_vector_uint64_create(&lock_ids);
	zbx_vector_id_name_pair_create(&dis_objs);
	zbx_vector_id_name_pair_create(&en_objs);
	zbx_vector_id_name_pair_create(&del_objs);

	for (i = 0; i < objects->values_num; i++)
	{
		zbx_uint64_t	id;
		int		discovery_flag, object_lastcheck, object_ts_delete, object_ts_disable, ts;
		unsigned char	discovery_status, object_status, disable_source, pending_del = 0;
		char		*name;

		cb_info(objects->values[i], &id, &discovery_flag, &object_lastcheck, &discovery_status,
				&object_ts_delete, &object_ts_disable, &object_status, &disable_source, &name);

		if (0 == id)
			continue;

		if (0 != discovery_flag)
		{
			zbx_vector_uint64_append(&lc_ids, id);

			if (ZBX_LLD_DISCOVERY_STATUS_NORMAL != discovery_status)
				zbx_vector_uint64_append(&discovered_ids, id);

			if (NULL != enabled_lifetime && ZBX_LLD_OBJECT_STATUS_DISABLED == object_status &&
					ZBX_DISABLE_SOURCE_LLD_LOST == disable_source)
			{
				zbx_id_name_pair_t	id_name_pair = {.id = id, .name = name};

				zbx_vector_id_name_pair_append(&en_objs, id_name_pair);
				zbx_vector_uint64_append(&upd_ids, id);
				zbx_vector_uint64_append(&lock_ids, id);
			}

			continue;
		}

		ts = lld_get_lifetime_ts(object_lastcheck, lifetime);

		if (SUCCEED == lld_check_lifetime_elapsed(lastcheck, ts))
		{
			if (NULL == enabled_lifetime)
			{
				zbx_vector_uint64_append(&del_ids, id);
				continue;
			}

			if (ZBX_LLD_OBJECT_STATUS_ENABLED == object_status ||
					ZBX_DISABLE_SOURCE_LLD_LOST == disable_source)
			{
				zbx_id_name_pair_t	id_name_pair = {.id = id, .name = name};

				zbx_vector_id_name_pair_append(&del_objs, id_name_pair);
				zbx_vector_uint64_append(&upd_ids, id);
				pending_del = 1;
			}
		}

		if (ZBX_LLD_DISCOVERY_STATUS_LOST != discovery_status)
			zbx_vector_uint64_append(&lost_ids, id);

		lld_prepare_ts_update(id, ts, object_ts_delete, &ts_ids, &imm_ids, &discovery_ts);

		if (NULL == enabled_lifetime)
			continue;

		ts = lld_get_lifetime_ts(object_lastcheck, enabled_lifetime);

		lld_prepare_ts_update(id, ts, object_ts_disable, &dis_ts_ids, &dis_imm_ids, &dis_discovery_ts);

		if (0 == pending_del && ZBX_LLD_OBJECT_STATUS_ENABLED == object_status &&
				SUCCEED == lld_check_lifetime_elapsed(lastcheck, ts))
		{
			zbx_id_name_pair_t	id_name_pair = {.id = id, .name = name};

			zbx_vector_id_name_pair_append(&dis_objs, id_name_pair);
			zbx_vector_uint64_append(&upd_ids, id);
			zbx_vector_uint64_append(&lock_ids, id);
		}
	}

	if (0 == lc_ids.values_num && 0 == discovery_ts.values_num && 0 == dis_discovery_ts.values_num &&
			0 == ts_ids.values_num && 0 == dis_ts_ids.values_num && 0 == upd_ids.values_num &&
			0 == lost_ids.values_num && 0 == discovered_ids.values_num && 0 == del_ids.values_num &&
			0 == imm_ids.values_num && 0 == dis_imm_ids.values_num)
	{
		goto clean;
	}

	zbx_db_begin();

	if ((0 != lock_ids.values_num && SUCCEED == zbx_db_lock_records(table_obj, &lock_ids)) ||
			(0 != upd_ids.values_num && upd_ids.values_num != lock_ids.values_num))
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
			unsigned char		disable_source;
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
				else if (FAIL != (i = zbx_vector_id_name_pair_bsearch(&del_objs, id_name_pair,
						lld_ids_names_compare_func)))
				{
					lld_prepare_object_delete(del_objs.values[i].id, del_objs.values[i].name,
							&del_ids, &ts_ids, &discovery_ts, &imm_ids, cb_audit_create);
				}

				continue;
			}

			ZBX_STR2UCHAR(disable_source, row[2]);

			if (ZBX_DISABLE_SOURCE_LLD_LOST == disable_source)
			{
				if (FAIL != (i = zbx_vector_id_name_pair_bsearch(&en_objs, id_name_pair,
						lld_ids_names_compare_func)))
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
				else if (FAIL != (i = zbx_vector_id_name_pair_bsearch(&del_objs, id_name_pair,
						lld_ids_names_compare_func)))
				{
					lld_prepare_object_delete(del_objs.values[i].id, del_objs.values[i].name,
							&del_ids, &ts_ids, &discovery_ts, &imm_ids, cb_audit_create);
				}
			}
		}
		zbx_db_free_result(result);
	}

	zbx_db_begin_multiple_update(&sql, &sql_alloc, &sql_offset);

	/* update object table */

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

	for (i = 0; i < discovery_ts.values_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update %s"
				" set ts_delete=%d"
				" where %s=" ZBX_FS_UI64 ";\n",
				table, (int)discovery_ts.values[i].second, id_name, discovery_ts.values[i].first);

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < dis_discovery_ts.values_num; i++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update %s"
				" set ts_disable=%d"
				" where %s=" ZBX_FS_UI64 ";\n",
				table, (int)dis_discovery_ts.values[i].second, id_name,
				dis_discovery_ts.values[i].first);

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

	if (0 != dis_ts_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ts_disable=0 where",
				table);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				dis_ts_ids.values, dis_ts_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != imm_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ts_delete=1 where",
				table);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				imm_ids.values, imm_ids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (0 != dis_imm_ids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update %s set ts_disable=1 where",
				table);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, id_name,
				dis_imm_ids.values, dis_imm_ids.values_num);
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
	zbx_vector_uint64_destroy(&en_ids);
	zbx_vector_uint64_destroy(&dis_ids);
	zbx_vector_uint64_destroy(&lc_ids);
	zbx_vector_uint64_destroy(&ts_ids);
	zbx_vector_uint64_destroy(&dis_ts_ids);
	zbx_vector_uint64_destroy(&imm_ids);
	zbx_vector_uint64_destroy(&dis_imm_ids);
	zbx_vector_uint64_destroy(&discovered_ids);
	zbx_vector_uint64_destroy(&lost_ids);
	zbx_vector_uint64_pair_destroy(&discovery_ts);
	zbx_vector_uint64_pair_destroy(&dis_discovery_ts);
	zbx_vector_uint64_destroy(&upd_ids);
	zbx_vector_uint64_destroy(&lock_ids);
	zbx_vector_id_name_pair_destroy(&dis_objs);
	zbx_vector_id_name_pair_destroy(&en_objs);
	zbx_vector_id_name_pair_destroy(&del_objs);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
