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

#include "trigger_dep_linking.h"

#include "zbxdbhigh.h"
#include "log.h"
#include "audit/zbxaudit_trigger.h"

typedef struct
{
	zbx_uint64_t	triggerid;
	int		flags;
}
resolve_dependencies_triggers_flags_t;

static zbx_hash_t	triggers_flags_hash_func(const void *data)
{
	const resolve_dependencies_triggers_flags_t	*trigger_entry =
			(const resolve_dependencies_triggers_flags_t *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&(trigger_entry->triggerid), sizeof(trigger_entry->triggerid),
			ZBX_DEFAULT_HASH_SEED);
}

static int	triggers_flags_compare_func(const void *d1, const void *d2)
{
	const resolve_dependencies_triggers_flags_t	*trigger_entry_1 =
			(const resolve_dependencies_triggers_flags_t *)d1;
	const resolve_dependencies_triggers_flags_t	*trigger_entry_2 =
			(const resolve_dependencies_triggers_flags_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(trigger_entry_1->triggerid, trigger_entry_2->triggerid);

	return 0;
}

typedef struct
{
	zbx_uint64_t	trigger_dep_id;
	zbx_uint64_t	trigger_down_id;
	zbx_uint64_t	trigger_up_id;
	int		status;
	int		flags;
}
zbx_trigger_dep_vec_entry_t;

ZBX_PTR_VECTOR_DECL(trigger_up_entries, zbx_trigger_dep_vec_entry_t *)
ZBX_PTR_VECTOR_IMPL(trigger_up_entries, zbx_trigger_dep_vec_entry_t *)

typedef struct
{
	zbx_uint64_t			trigger_down_id;
	zbx_vector_trigger_up_entries_t	v;
}
zbx_trigger_dep_entry_t;

static zbx_hash_t	zbx_trigger_dep_entries_hash_func(const void *data)
{
	const zbx_trigger_dep_entry_t	*trigger_dep_entry = (const zbx_trigger_dep_entry_t *)data;

	return ZBX_DEFAULT_UINT64_HASH_ALGO(&((trigger_dep_entry)->trigger_down_id),
			sizeof(trigger_dep_entry->trigger_down_id), ZBX_DEFAULT_HASH_SEED);
}

static int	zbx_trigger_dep_entries_compare_func(const void *d1, const void *d2)
{
	const zbx_trigger_dep_entry_t	*trigger_dep_entry_1 = (const zbx_trigger_dep_entry_t *)d1;
	const zbx_trigger_dep_entry_t	*trigger_dep_entry_2 = (const zbx_trigger_dep_entry_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(trigger_dep_entry_1->trigger_down_id, trigger_dep_entry_2->trigger_down_id);

	return 0;
}

static void	zbx_trigger_dep_vec_entry_clean(zbx_trigger_dep_vec_entry_t *trigger_dep_entry)
{
	zbx_free(trigger_dep_entry);
}

static void	zbx_triggers_dep_entries_clean(zbx_hashset_t *h)
{
	zbx_hashset_iter_t	iter;
	zbx_trigger_dep_entry_t	*trigger_dep_entry;

	zbx_hashset_iter_reset(h, &iter);

	while (NULL != (trigger_dep_entry = (zbx_trigger_dep_entry_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_trigger_up_entries_clear_ext(&(trigger_dep_entry->v), zbx_trigger_dep_vec_entry_clean);
		zbx_vector_trigger_up_entries_destroy(&(trigger_dep_entry->v));
	}

	zbx_hashset_destroy(h);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: resolves trigger dependencies for the specified triggers based on    *
 *          host and linked templates                                            *
 *                                                                               *
 * Parameters: hostid        - [IN] host identifier from database                *
 *             trids         - [IN] vector of trigger identifiers from database  *
 *             links         - [OUT] pairs of trigger dependencies (down,up)     *
 *             trigger_flags - [OUT] map that lets audit to know if trigger is   *
 *                                   a prototype or just trigger                 *
 *                                                                               *
 * Return value: upon successful completion return SUCCEED, or FAIL on DB error  *
 *                                                                               *
 *********************************************************************************/
static int	DBresolve_template_trigger_dependencies(zbx_uint64_t hostid, const zbx_vector_uint64_t *trids,
		zbx_vector_uint64_pair_t *links, zbx_hashset_t *triggers_flags)
{
	char				*sql = NULL;
	int				i, res = SUCCEED;
	size_t				sql_alloc = 512, sql_offset;
	DB_RESULT			result;
	DB_ROW				row;
	zbx_vector_uint64_pair_t	dep_list_ids, map_ids;
	zbx_vector_uint64_t		all_templ_ids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&all_templ_ids);
	zbx_vector_uint64_pair_create(&dep_list_ids);
	zbx_vector_uint64_pair_create(&map_ids);
	sql = (char *)zbx_malloc(sql, sql_alloc);

	sql_offset = 0;

	/* get triggerids on which the 'parent template trigger of the new trigger' depends on */
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct td.triggerid_down,td.triggerid_up"
			" from triggers t,trigger_depends td"
			" where t.templateid in (td.triggerid_up,td.triggerid_down) and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.triggerid", trids->values, trids->values_num);

	if (NULL == (result = DBselect("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_pair_t	dep_list_id;

		ZBX_STR2UINT64(dep_list_id.first, row[0]);
		ZBX_STR2UINT64(dep_list_id.second, row[1]);
		zbx_vector_uint64_pair_append(&dep_list_ids, dep_list_id);
		zbx_vector_uint64_append(&all_templ_ids, dep_list_id.first);
		zbx_vector_uint64_append(&all_templ_ids, dep_list_id.second);
	}

	DBfree_result(result);

	if (0 == dep_list_ids.values_num)	/* not all trigger templates have a dependency trigger */
		goto clean;

	zbx_vector_uint64_sort(&all_templ_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&all_templ_ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	sql_offset = 0;

	/* find triggers which have a parent template trigger that we have dependency on, */
	/* those are the dependency triggers of interest on our host */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select t.triggerid,t.templateid,t.flags"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and i.hostid=" ZBX_FS_UI64
				" and",
				hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.templateid", all_templ_ids.values,
			all_templ_ids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "t.triggerid", trids->values,
			trids->values_num);

	if (NULL == (result = DBselect("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_pair_t			map_id;
		resolve_dependencies_triggers_flags_t	temp_t;

		ZBX_STR2UINT64(temp_t.triggerid, row[0]);

		if (NULL == zbx_hashset_search(triggers_flags, &temp_t))
		{
			resolve_dependencies_triggers_flags_t	local_temp_t;

			ZBX_STR2UINT64(local_temp_t.triggerid, row[0]);
			local_temp_t.flags = atoi(row[2]);
			zbx_hashset_insert(triggers_flags, &local_temp_t, sizeof(local_temp_t));
		}

		ZBX_STR2UINT64(map_id.first, row[0]);
		ZBX_DBROW2UINT64(map_id.second, row[1]);

		zbx_vector_uint64_pair_append(&map_ids, map_id);
	}

	DBfree_result(result);

	for (i = 0; i < dep_list_ids.values_num; i++)
	{
		zbx_uint64_t	templateid_down = dep_list_ids.values[i].first;
		zbx_uint64_t	templateid_up = dep_list_ids.values[i].second;

		/* Convert template ids to corresponding trigger ids.         */
		/* If template trigger depends on host trigger rather than    */
		/* template trigger then up id conversion will fail and the   */
		/* original value (host trigger id) will be used as intended. */
		zbx_uint64_t	triggerid_down = 0;
		zbx_uint64_t	triggerid_up = templateid_up;
		int		j;

		for (j = 0; j < map_ids.values_num; j++)
		{
			zbx_uint64_t	hst_triggerid = map_ids.values[j].first;
			zbx_uint64_t	tpl_triggerid = map_ids.values[j].second;

			if (tpl_triggerid == templateid_down)
				triggerid_down = hst_triggerid;

			if (tpl_triggerid == templateid_up)
				triggerid_up = hst_triggerid;
		}

		if (0 != triggerid_down)
		{
			zbx_uint64_pair_t	link = {triggerid_down, triggerid_up};

			zbx_vector_uint64_pair_append(links, link);
		}
	}
clean:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&all_templ_ids);

	zbx_vector_uint64_pair_destroy(&map_ids);
	zbx_vector_uint64_pair_destroy(&dep_list_ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

/**********************************************************************************************************
 *                                                                                                        *
 * Purpose: takes a list of pending trigger dependencies (links) and excludes entries that are            *
 *          already present on the target host to generate a new list (links_processed). Also, prepare    *
 *          the list of the trigger dependencies (trigger_dep_ids_del) that need to be deleted on the     *
 *          target host, since they are not present on the template trigger.                              *
 *                                                                                                        *
 * Parameters: trids               - [IN] vector of trigger identifiers from database                     *
 *             links               - [OUT] pairs of trigger dependencies, list of links_up and links_down *
 *                                         links that we want to be present on the target host            *
 *             links_processed     - [OUT] processed links with entries that are already present excluded *
 *             trigger_dep_ids_del - [OUT] list of triggers dependencies that need to be deleted          *
 *                                                                                                        *
 * Return value: upon successful completion return SUCCEED, or FAIL on DB error                           *
 *                                                                                                        *
 *********************************************************************************************************/
static int	prepare_trigger_dependencies_updates_and_deletes(const zbx_vector_uint64_t *trids,
		zbx_vector_uint64_pair_t *links, zbx_vector_uint64_pair_t *links_processed,
		zbx_vector_uint64_t *trigger_dep_ids_del)
{
	char			*sql = NULL;
	int			i, res = SUCCEED;
	size_t			sql_alloc = 256, sql_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_hashset_t		h;
	zbx_hashset_iter_t	iter;
	zbx_trigger_dep_entry_t	*found;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
#define	TRIGGER_FUNCS_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&h, TRIGGER_FUNCS_HASHSET_DEF_SIZE, zbx_trigger_dep_entries_hash_func,
			zbx_trigger_dep_entries_compare_func);
#undef TRIGGER_FUNCS_HASHSET_DEF_SIZE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select td.triggerdepid,td.triggerid_down,td.triggerid_up,t.flags"
			" from trigger_depends td,triggers t "
			" where t.triggerid=td.triggerid_down"
			" and");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "td.triggerid_down", trids->values, trids->values_num);

	if (NULL == (result = DBselect("%s", sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (row = DBfetch(result)))
	{
		int				flags;
		zbx_uint64_t			trigger_dep_id, trigger_id_down, trigger_id_up;
		zbx_trigger_dep_entry_t		temp_t;
		zbx_trigger_dep_vec_entry_t	*s;

		ZBX_STR2UINT64(trigger_dep_id, row[0]);
		ZBX_STR2UINT64(trigger_id_down, row[1]);
		ZBX_STR2UINT64(trigger_id_up, row[2]);
		flags = atoi(row[3]);

		temp_t.trigger_down_id = trigger_id_down;

		s = (zbx_trigger_dep_vec_entry_t *)zbx_malloc(NULL, sizeof(zbx_trigger_dep_vec_entry_t));
		s->trigger_dep_id = trigger_dep_id;
		s->trigger_down_id = trigger_id_down;
		s->trigger_up_id = trigger_id_up;
		s->status = 0;
		s->flags = flags;

		if (NULL != (found = (zbx_trigger_dep_entry_t *)zbx_hashset_search(&h, &temp_t)))
		{
			zbx_vector_trigger_up_entries_append(&(found->v), s);
		}
		else
		{
			zbx_trigger_dep_entry_t local_temp_t;

			zbx_vector_trigger_up_entries_create(&(local_temp_t.v));
			zbx_vector_trigger_up_entries_append(&(local_temp_t.v), s);
			local_temp_t.trigger_down_id = trigger_id_down;
			zbx_hashset_insert(&h, &local_temp_t, sizeof(local_temp_t));
		}
	}

	DBfree_result(result);

	for (i = 0; i < links->values_num; i++)
	{
		zbx_trigger_dep_entry_t	temp_t;

		temp_t.trigger_down_id = links->values[i].first;

		if (NULL != (found = (zbx_trigger_dep_entry_t *) zbx_hashset_search(&h, &temp_t)))
		{
			int	j;
			int	found_trigger_up = 0;

			for (j = 0; j < found->v.values_num; j++)
			{
				/* trigger_up are equal */
				if (links->values[i].second == found->v.values[j]->trigger_up_id)
				{
					found_trigger_up = 1;
					/* mark it as 'need to preserve' */
					found->v.values[j]->status = 1;
					break;
				}
			}

			if (0 == found_trigger_up)
			{
				zbx_uint64_pair_t	x;

				x.first = links->values[i].first;
				x.second = links->values[i].second;
				zbx_vector_uint64_pair_append(links_processed, x);
			}
		}
		else
		{
			zbx_uint64_pair_t	x;

			x.first = links->values[i].first;
			x.second = links->values[i].second;
			zbx_vector_uint64_pair_append(links_processed, x);
		}
	}

	zbx_hashset_iter_reset(&h, &iter);

	while (NULL != (found = (zbx_trigger_dep_entry_t *)zbx_hashset_iter_next(&iter)))
	{
		for (i = 0; i < found->v.values_num; i++)
		{
			if (0 == found->v.values[i]->status)
			{
				zbx_vector_uint64_append(trigger_dep_ids_del,
						found->v.values[i]->trigger_dep_id);
				zbx_audit_trigger_update_json_remove_dependency(found->v.values[i]->flags,
						found->v.values[i]->trigger_dep_id,
						found->v.values[i]->trigger_down_id);
			}
		}
	}
clean:
	zbx_triggers_dep_entries_clean(&h);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	DBadd_trigger_dependencies(zbx_vector_uint64_pair_t *links, zbx_hashset_t *triggers_flags)
{
	int	res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 < links->values_num)
	{
		int		i;
		zbx_uint64_t	triggerdepid;
		zbx_db_insert_t	db_insert;

		triggerdepid = DBget_maxid_num("trigger_depends", links->values_num);

		zbx_db_insert_prepare(&db_insert, "trigger_depends", "triggerdepid", "triggerid_down", "triggerid_up",
				NULL);

		for (i = 0; i < links->values_num; i++)
		{
			resolve_dependencies_triggers_flags_t	*found, temp_t;

			zbx_db_insert_add_values(&db_insert, triggerdepid, links->values[i].first,
					links->values[i].second);

			temp_t.triggerid = links->values[i].first;

			if (NULL != (found = (resolve_dependencies_triggers_flags_t *)zbx_hashset_search(
					triggers_flags, &temp_t)))
			{
				zbx_audit_trigger_update_json_add_dependency(found->flags, triggerdepid,
						links->values[i].first, links->values[i].second);
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				res = FAIL;

				break;
			}

			triggerdepid++;
		}

		if (SUCCEED == res)
			zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

static int	DBadd_and_remove_trigger_dependencies(zbx_vector_uint64_pair_t *links,
		const zbx_vector_uint64_t *trids, zbx_hashset_t *triggers_flags)
{
	int				res;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_pair_t	links_processed;
	zbx_vector_uint64_t		trigger_dep_ids_del;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&trigger_dep_ids_del);
	zbx_vector_uint64_pair_create(&links_processed);

	if (FAIL == (res = prepare_trigger_dependencies_updates_and_deletes(trids, links, &links_processed,
			&trigger_dep_ids_del)))
	{
		goto clean;
	}

	if (0 < trigger_dep_ids_del.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from trigger_depends where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerdepid",
				trigger_dep_ids_del.values, trigger_dep_ids_del.values_num);

		if (ZBX_DB_OK > DBexecute("%s", sql))
		{
			res = FAIL;
			goto clean;
		}
	}

	res = DBadd_trigger_dependencies(&links_processed, triggers_flags);
clean:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&trigger_dep_ids_del);
	zbx_vector_uint64_pair_destroy(&links_processed);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

/********************************************************************************
 *                                                                              *
 * Purpose: update trigger dependencies for specified host                      *
 *                                                                              *
 * Parameters: hostid    - [IN] host identifier from database                   *
 *             trids     - [IN] vector of trigger identifiers from database     *
 *             is_update - [IN] flag. Values:                                   *
 *                              TRIGGER_DEP_SYNC_INSERT_OP - 'trids' contains   *
 *                               identifiers of new triggers,                   *
 *                              TRIGGER_DEP_SYNC_UPDATE_OP - 'trids' contains   *
 *                               identifiers of already present triggers which  *
 *                               need to be updated                             *
 *                                                                              *
 * Return value: upon successful completion return SUCCEED, or FAIL on DB error *
 *                                                                              *
 * Comments: !!! Don't forget to sync the code with PHP !!!                     *
 *                                                                              *
 ********************************************************************************/
int	DBsync_template_dependencies_for_triggers(zbx_uint64_t hostid, const zbx_vector_uint64_t *trids, int is_update)
{
	int				res = SUCCEED;
	zbx_vector_uint64_pair_t	links;
	zbx_hashset_t			triggers_flags;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == trids->values_num)
		goto out;

	zbx_vector_uint64_pair_create(&links);
#define	TRIGGER_FUNCS_HASHSET_DEF_SIZE	100
	zbx_hashset_create(&triggers_flags, TRIGGER_FUNCS_HASHSET_DEF_SIZE, triggers_flags_hash_func,
			triggers_flags_compare_func);
#undef TRIGGER_FUNCS_HASHSET_DEF_SIZE
	if (FAIL == (res = DBresolve_template_trigger_dependencies(hostid, trids, &links, &triggers_flags)))
		goto clean;

	if (TRIGGER_DEP_SYNC_INSERT_OP == is_update)
	{
		if (FAIL == (res = DBadd_trigger_dependencies(&links, &triggers_flags)))
			goto clean;
	}
	else if (TRIGGER_DEP_SYNC_UPDATE_OP == is_update)
	{
		res = DBadd_and_remove_trigger_dependencies(&links, trids, &triggers_flags);
	}
clean:
	zbx_vector_uint64_pair_destroy(&links);
	zbx_hashset_destroy(&triggers_flags);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}
