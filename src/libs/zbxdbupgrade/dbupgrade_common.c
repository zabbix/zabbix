/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "dbupgrade_common.h"
#include "dbupgrade.h"

#include "zbxdb.h"
#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxhash.h"
#include "zbxcrypto.h"

typedef struct
{
	char			hash_str[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	zbx_vector_uint64_t	groupids;
	zbx_vector_uint64_t	ids;
} zbx_dbu_group_set_t;

int	delete_problems_with_nonexistent_object(void)
{
	zbx_db_result_t		result;
	zbx_vector_uint64_t	eventids;
	zbx_db_row_t		row;
	zbx_uint64_t		eventid;
	int			sources[] = {EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL};
	int			objects[] = {EVENT_OBJECT_ITEM, EVENT_OBJECT_LLDRULE}, i;

	zbx_vector_uint64_create(&eventids);

	for (i = 0; i < (int)ARRSIZE(sources); i++)
	{
		result = zbx_db_select(
				"select p.eventid"
				" from problem p"
				" where p.source=%d and p.object=%d and not exists ("
					"select null"
					" from triggers t"
					" where t.triggerid=p.objectid"
				")",
				sources[i], EVENT_OBJECT_TRIGGER);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);
			zbx_vector_uint64_append(&eventids, eventid);
		}
		zbx_db_free_result(result);
	}

	for (i = 0; i < (int)ARRSIZE(objects); i++)
	{
		result = zbx_db_select(
				"select p.eventid"
				" from problem p"
				" where p.source=%d and p.object=%d and not exists ("
					"select null"
					" from items i"
					" where i.itemid=p.objectid"
				")",
				EVENT_SOURCE_INTERNAL, objects[i]);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);
			zbx_vector_uint64_append(&eventids, eventid);
		}
		zbx_db_free_result(result);
	}

	zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 != eventids.values_num)
		zbx_db_execute_multiple_query("delete from problem where", "eventid", &eventids);

	zbx_vector_uint64_destroy(&eventids);

	return SUCCEED;
}
#ifndef HAVE_SQLITE3
int	create_problem_3_index(void)
{
	if (FAIL == zbx_db_index_exists("problem", "problem_3"))
		return DBcreate_index("problem", "problem_3", "r_eventid", 0);

	return SUCCEED;
}

int	drop_c_problem_2_index(void)
{
#ifdef HAVE_MYSQL	/* MySQL automatically creates index and might not remove it on some conditions */
	if (SUCCEED == zbx_db_index_exists("problem", "c_problem_2"))
		return DBdrop_index("problem", "c_problem_2");
#endif
	return SUCCEED;
}
#endif

static zbx_hash_t	permission_group_set_hash(const void *data)
{
	const zbx_dbu_group_set_t	*group_set = (const zbx_dbu_group_set_t *)data;

	return ZBX_DEFAULT_STRING_HASH_FUNC(group_set->hash_str);
}

static int	permission_group_set_compare(const void *d1, const void *d2)
{
	const zbx_dbu_group_set_t	*group_set1 = (const zbx_dbu_group_set_t *)d1;
	const zbx_dbu_group_set_t	*group_set2 = (const zbx_dbu_group_set_t *)d2;

	return strcmp(group_set1->hash_str, group_set2->hash_str);
}

static int	permission_groupsets_make(zbx_vector_uint64_t *ids, const char *fld_name_id,
		const char *fld_name_groupid, const char *tbl_name_groups, zbx_hashset_t *group_sets,
		int allow_empty_groups)
{
	int			ret = SUCCEED;
	char			id_str[MAX_ID_LEN + 2];
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	groupids;
	zbx_dbu_group_set_t	*gset_ptr;

	id_str[0] = '|';
	zbx_vector_uint64_create(&groupids);

	for (int i = 0; i < ids->values_num; i++)
	{
		unsigned char		hash[ZBX_SHA256_DIGEST_SIZE];
		char			*id_str_p = id_str + 1;
		sha256_ctx		ctx;
		zbx_dbu_group_set_t	gset;

		zbx_sha256_init(&ctx);

		result = zbx_db_select("select %s from %s where %s=" ZBX_FS_UI64 " order by %s",
				fld_name_groupid, tbl_name_groups, fld_name_id, ids->values[i], fld_name_groupid);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	groupid;

			ZBX_STR2UINT64(groupid, row[0]);

			if (1 == groupids.values_num)
				id_str_p = id_str;

			zbx_snprintf(id_str + 1, MAX_ID_LEN + 1, "%s", row[0]);
			zbx_sha256_process_bytes(id_str_p, strlen(id_str_p), &ctx);
			zbx_vector_uint64_append(&groupids, groupid);
		}
		zbx_db_free_result(result);

		if (0 == groupids.values_num)
		{
			if (0 == allow_empty_groups)
			{
				zabbix_log(LOG_LEVEL_WARNING, "host or template [hostid=" ZBX_FS_UI64 "] is not"
						" assigned to any group, permissions not granted", ids->values[i]);
			}

			continue;
		}

		zbx_sha256_finish(&ctx, hash);
		(void)zbx_bin2hex(hash, ZBX_SHA256_DIGEST_SIZE, gset.hash_str,
				ZBX_SHA256_DIGEST_SIZE * 2 + 1);

		if (NULL == (gset_ptr = zbx_hashset_search(group_sets, &gset)))
		{
			zbx_vector_uint64_create(&gset.ids);
			zbx_vector_uint64_create(&gset.groupids);
			zbx_vector_uint64_append_array(&gset.groupids, groupids.values, groupids.values_num);

			if (NULL == (gset_ptr = zbx_hashset_insert(group_sets, &gset, sizeof(zbx_dbu_group_set_t))))
			{
				ret = FAIL;
				break;
			}
		}

		zbx_vector_uint64_append(&gset_ptr->ids, ids->values[i]);
		zbx_vector_uint64_clear(&groupids);
	}

	zbx_vector_uint64_destroy(&groupids);

	return ret;
}

static int	permission_groupsets_insert(const char *tbl_name, zbx_hashset_t *group_sets, zbx_db_insert_t *db_gset,
		zbx_db_insert_t *db_gset_groups, zbx_db_insert_t *db_gset_parents, zbx_vector_uint64_t *hgsetids)
{
	zbx_uint64_t		gsetid;
	zbx_hashset_iter_t	iter;
	zbx_dbu_group_set_t	*gset_ptr;

	if (0 == group_sets->num_data)
		return SUCCEED;

	gsetid = zbx_db_get_maxid_num(tbl_name, group_sets->num_data);

	zbx_hashset_iter_reset(group_sets, &iter);

	while (NULL != (gset_ptr = (zbx_dbu_group_set_t *)zbx_hashset_iter_next(&iter)))
	{
		int	i;

		if (NULL != hgsetids)
		{
			char		*sql;
			zbx_db_result_t	result;
			zbx_db_row_t	row;

			sql = zbx_dsprintf(NULL, "select hgsetid from hgset where hash='%s'", gset_ptr->hash_str);
			result = zbx_db_select_n(sql, 1);
			zbx_free(sql);

			if (NULL != (row = zbx_db_fetch(result)))
			{
				zbx_uint64_t	hgsetid;

				ZBX_STR2UINT64(hgsetid, row[0]);
				zbx_db_free_result(result);

				for (i = 0; i < gset_ptr->ids.values_num; i++)
					zbx_db_insert_add_values(db_gset_parents, gset_ptr->ids.values[i], hgsetid);

				continue;
			}

			zbx_db_free_result(result);
			zbx_vector_uint64_append(hgsetids, gsetid);
		}

		zbx_db_insert_add_values(db_gset, gsetid, gset_ptr->hash_str);

		for (i = 0; i < gset_ptr->groupids.values_num; i++)
			zbx_db_insert_add_values(db_gset_groups, gsetid, gset_ptr->groupids.values[i]);

		for (i = 0; i < gset_ptr->ids.values_num; i++)
			zbx_db_insert_add_values(db_gset_parents, gset_ptr->ids.values[i], gsetid);

		gsetid++;
	}

	if (FAIL == zbx_db_insert_execute(db_gset) ||
			FAIL == zbx_db_insert_execute(db_gset_groups) ||
			FAIL == zbx_db_insert_execute(db_gset_parents))
	{
		return FAIL;
	}

	return SUCCEED;
}

static void	permission_groupsets_destroy(zbx_hashset_t *group_sets)
{
	zbx_hashset_iter_t	iter;
	zbx_dbu_group_set_t	*gset_ptr;

	zbx_hashset_iter_reset(group_sets, &iter);

	while (NULL != (gset_ptr = (zbx_dbu_group_set_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_uint64_destroy(&gset_ptr->groupids);
		zbx_vector_uint64_destroy(&gset_ptr->ids);
	}

	zbx_hashset_destroy(group_sets);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates hashes and adds new group sets for hosts               *
 *                                                                            *
 * Parameters: ids      - [IN] host IDs                                       *
 *             hgsetids - [IN/OUT] added host group sets IDs, optional        *
 *                                                                            *
 * Return value: SUCCEED - new host group sets added successfully,            *
 *                           or not needed                                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: If hgsetids parameter was specified, then function checks if     *
 *           hgset already exists before adding new one.                      *
 *                                                                            *
******************************************************************************/
int	permission_hgsets_add(zbx_vector_uint64_t *ids, zbx_vector_uint64_t *hgsetids)
{
	int		ret;
	zbx_hashset_t	hgsets;
	zbx_db_insert_t	db_insert, db_insert_groups, db_insert_hosts;

	zbx_hashset_create(&hgsets, 1, permission_group_set_hash, permission_group_set_compare);
	zbx_db_insert_prepare(&db_insert, "hgset", "hgsetid", "hash", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_groups, "hgset_group", "hgsetid", "groupid", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_hosts, "host_hgset", "hostid", "hgsetid", (char*)NULL);

	if (SUCCEED == (ret = permission_groupsets_make(ids, "hostid", "groupid", "hosts_groups", &hgsets, 0)))
	{
		ret = permission_groupsets_insert("hgset", &hgsets, &db_insert, &db_insert_groups, &db_insert_hosts,
				hgsetids);
	}

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_clean(&db_insert_groups);
	zbx_db_insert_clean(&db_insert_hosts);

	permission_groupsets_destroy(&hgsets);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates hashes and adds new group sets for users               *
 *                                                                            *
 * Parameters: ids  - [IN] user IDs                                           *
 *                                                                            *
 * Return value: SUCCEED - new user group sets added successfully,            *
 *                           or not needed                                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
******************************************************************************/
int	permission_ugsets_add(zbx_vector_uint64_t *ids)
{
	int			ret;
	zbx_hashset_t		ugsets;
	zbx_db_insert_t		db_insert, db_insert_groups, db_insert_users;

	zbx_hashset_create(&ugsets, 1, permission_group_set_hash, permission_group_set_compare);
	zbx_db_insert_prepare(&db_insert, "ugset", "ugsetid", "hash", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_groups, "ugset_group", "ugsetid", "usrgrpid", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_users, "user_ugset", "userid", "ugsetid", (char*)NULL);

	if (SUCCEED == (ret = permission_groupsets_make(ids, "userid", "usrgrpid", "users_groups", &ugsets, 1)))
	{
		ret = permission_groupsets_insert("ugset", &ugsets, &db_insert, &db_insert_groups, &db_insert_users,
				NULL);
	}

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_clean(&db_insert_groups);
	zbx_db_insert_clean(&db_insert_users);

	permission_groupsets_destroy(&ugsets);

	return ret;
}
