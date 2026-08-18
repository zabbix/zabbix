/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "permissions.h"
#include "zbxhash.h"
#include "zbxdb.h"
#include "zbxdbwrap.h"
#include "zbxnix.h"
#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxtime.h"

#define PERM_REBUILD_IDLE	0
#define PERM_REBUILD_RUNNING	1

static pthread_mutex_t	rebuild_lock = PTHREAD_MUTEX_INITIALIZER;
static int		rebuild_state = PERM_REBUILD_IDLE;

typedef struct
{
	char			hash_str[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	zbx_vector_uint64_t	groupids;
	zbx_vector_uint64_t	ids;
} zbx_group_set_t;

static int	permission_groupsets_make(zbx_dbconn_t *db, zbx_vector_uint64_t *ids, const char *fld_name_id,
		const char *fld_name_groupid, const char *tbl_name_groups, zbx_hashset_t *group_sets,
		int allow_empty_groups)
{
	int			ret = SUCCEED;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	groupids;
	zbx_group_set_t		*gset_ptr;

	zbx_vector_uint64_create(&groupids);

	for (int i = 0; i < ids->values_num; i++)
	{
		zbx_group_set_t	gset;

		result = zbx_dbconn_select(db, "select %s from %s where %s=" ZBX_FS_UI64 " order by %s",
				fld_name_groupid, tbl_name_groups, fld_name_id, ids->values[i], fld_name_groupid);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	groupid;

			ZBX_STR2UINT64(groupid, row[0]);
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

		zbx_hgset_hash_calculate(&groupids, gset.hash_str, sizeof(gset.hash_str));

		if (NULL == (gset_ptr = zbx_hashset_search(group_sets, &gset)))
		{
			zbx_vector_uint64_create(&gset.ids);
			zbx_vector_uint64_create(&gset.groupids);
			zbx_vector_uint64_append_array(&gset.groupids, groupids.values, groupids.values_num);

			if (NULL == (gset_ptr = zbx_hashset_insert(group_sets, &gset, sizeof(zbx_group_set_t))))
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

static int	permission_groupsets_insert(zbx_dbconn_t *db, const char *tbl_name, zbx_hashset_t *group_sets,
		zbx_db_insert_t *db_gset, zbx_db_insert_t *db_gset_groups, zbx_db_insert_t *db_gset_parents)
{
	zbx_uint64_t		gsetid;
	zbx_hashset_iter_t	iter;
	zbx_group_set_t		*gset_ptr;

	if (0 == group_sets->num_data)
		return SUCCEED;

	gsetid = zbx_dbconn_get_maxid_num(db, tbl_name, group_sets->num_data);

	zbx_hashset_iter_reset(group_sets, &iter);

	while (NULL != (gset_ptr = (zbx_group_set_t *)zbx_hashset_iter_next(&iter)))
	{
		int	i;

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

static zbx_hash_t	permission_group_set_hash(const void *data)
{
	const zbx_group_set_t	*group_set = (const zbx_group_set_t *)data;

	return ZBX_DEFAULT_STRING_HASH_FUNC(group_set->hash_str);
}

static int	permission_group_set_compare(const void *d1, const void *d2)
{
	const zbx_group_set_t	*group_set1 = (const zbx_group_set_t *)d1;
	const zbx_group_set_t	*group_set2 = (const zbx_group_set_t *)d2;

	return strcmp(group_set1->hash_str, group_set2->hash_str);
}

static void	permission_groupsets_destroy(zbx_hashset_t *group_sets)
{
	zbx_hashset_iter_t	iter;
	zbx_group_set_t	 *gset_ptr;

	zbx_hashset_iter_reset(group_sets, &iter);

	while (NULL != (gset_ptr = (zbx_group_set_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_uint64_destroy(&gset_ptr->groupids);
		zbx_vector_uint64_destroy(&gset_ptr->ids);
	}

	zbx_hashset_destroy(group_sets);
}

static int	permission_hgsets_add(zbx_dbconn_t *db, zbx_vector_uint64_t *ids)
{
	int		ret;
	zbx_hashset_t	hgsets;
	zbx_db_insert_t	db_insert, db_insert_groups, db_insert_hosts;

	zbx_hashset_create(&hgsets, 1, permission_group_set_hash, permission_group_set_compare);

	zbx_dbconn_prepare_insert(db, &db_insert, "hgset", "hgsetid", "hash", (char*)NULL);
	zbx_dbconn_prepare_insert(db, &db_insert_groups, "hgset_group", "hgsetid", "groupid", (char*)NULL);
	zbx_dbconn_prepare_insert(db, &db_insert_hosts, "host_hgset", "hostid", "hgsetid", (char*)NULL);

	if (SUCCEED == (ret = permission_groupsets_make(db, ids, "hostid", "groupid", "hosts_groups", &hgsets, 0)))
	{
		ret = permission_groupsets_insert(db, "hgset", &hgsets, &db_insert, &db_insert_groups,
				&db_insert_hosts);
	}

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_clean(&db_insert_groups);
	zbx_db_insert_clean(&db_insert_hosts);

	permission_groupsets_destroy(&hgsets);

	return ret;
}

static int	permission_ugsets_add(zbx_dbconn_t *db, zbx_vector_uint64_t *ids)
{
	int			ret;
	zbx_hashset_t		ugsets;
	zbx_db_insert_t		db_insert, db_insert_groups, db_insert_users;

	zbx_hashset_create(&ugsets, 1, permission_group_set_hash, permission_group_set_compare);
	zbx_dbconn_prepare_insert(db, &db_insert, "ugset", "ugsetid", "hash", (char*)NULL);
	zbx_dbconn_prepare_insert(db, &db_insert_groups, "ugset_group", "ugsetid", "usrgrpid", (char*)NULL);
	zbx_dbconn_prepare_insert(db, &db_insert_users, "user_ugset", "userid", "ugsetid", (char*)NULL);

	if (SUCCEED == (ret = permission_groupsets_make(db, ids, "userid", "usrgrpid", "users_groups", &ugsets, 1)))
	{
		ret = permission_groupsets_insert(db, "ugset", &ugsets, &db_insert, &db_insert_groups,
				&db_insert_users);
	}

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_clean(&db_insert_groups);
	zbx_db_insert_clean(&db_insert_users);

	permission_groupsets_destroy(&ugsets);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: rebuilds permission cache tables from scratch                     *
 *                                                                            *
 * Comments: It is a long running task and it should be executed in a         *
 *           separate thread. The permission cache tables are cleared and     *
 *           repopulated within a single transaction, which is rolled back    *
 *           if the rebuilding fails.                                         *
 *                                                                            *
 ******************************************************************************/
static int	permissions_rebuild_task(void)
{
	int			ret = SUCCEED;
	zbx_vector_uint64_t	ids;
	static const char	*tables[] = {"user_ugset", "host_hgset", "permission", "ugset_group", "ugset",
			"hgset_group", "hgset"};


	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&ids);

	zbx_dbconn_t	*db = zbx_dbconn_create();
	(void)zbx_dbconn_set_connect_options(db, ZBX_DB_CONNECT_ONCE);

	if (ZBX_DB_OK != zbx_dbconn_open(db))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot connect to database for rebuilding the permissions cache");

		ret = FAIL;
		goto skip;
	}

	if (ZBX_DB_OK != zbx_dbconn_begin(db))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot begin database transaction for rebuilding the permissions cache");

		ret = FAIL;
		goto skip;
	}

	for (int i = 0; i < (int)ARRSIZE(tables); i++)
	{
		if (ZBX_DB_OK > zbx_dbconn_execute(db, "delete from %s", tables[i]))
		{
			ret = FAIL;
			zabbix_log(LOG_LEVEL_WARNING, "cannot delete from permission cache tables");
			goto out;
		}
	}

	zbx_dbconn_select_uint64(db, "select hostid from hosts where (flags & 2) = 0", &ids);

	ret = permission_hgsets_add(db, &ids);

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add permission hgsets");
		goto out;
	}

	zbx_vector_uint64_clear(&ids);

	zbx_dbconn_select_uint64(db,
			"select u.userid"
			" from users u"
				" join role r on u.roleid=r.roleid"
			" where r.type<>3"
				" and u.userid in ("
					"select userid"
					" from users_groups"
				")", &ids);

	ret = permission_ugsets_add(db, &ids);

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add permission ugsets");
		goto out;
	}

	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert;

	zbx_dbconn_prepare_insert(db, &db_insert, "permission", "ugsetid", "hgsetid", "permission", (char*)NULL);

	result = zbx_dbconn_select(db,
			"select u.ugsetid,h.hgsetid,max(r.permission)"
			" from hgset h"
			" join hgset_group hg"
				" on h.hgsetid=hg.hgsetid"
			" join rights r on hg.groupid=r.id"
			" join ugset_group ug"
				" on r.groupid=ug.usrgrpid"
			" join ugset u"
				" on ug.ugsetid=u.ugsetid"
			" group by u.ugsetid,h.hgsetid"
			" having min(r.permission)>0"
			" order by u.ugsetid,h.hgsetid");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hgsetid, ugsetid;
		int		permission;

		ZBX_STR2UINT64(ugsetid, row[0]);
		ZBX_STR2UINT64(hgsetid, row[1]);
		permission = atoi(row[2]);

		zbx_db_insert_add_values(&db_insert, ugsetid, hgsetid, permission);
	}
	zbx_db_free_result(result);

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_WARNING, "cannot insert permissions");
out:
	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK != zbx_dbconn_commit(db))
			ret = FAIL;
	}
	else
		zbx_dbconn_rollback(db);

skip:
	zbx_vector_uint64_destroy(&ids);
	zbx_dbconn_free(db);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	*permissions_rebuild_entry(void *args)
{
	int	err, res = SUCCEED;
	double	start;

	ZBX_UNUSED(args);

	zabbix_log(LOG_LEVEL_WARNING, "thread started [permissions rebuild]");

	if (0 != (err = zbx_init_thread_signal_handler()))
		zabbix_log(LOG_LEVEL_WARNING, "cannot block signals: %s", zbx_strerror(err));

	start = zbx_time();

	res = permissions_rebuild_task();

	zabbix_log(LOG_LEVEL_WARNING, "thread finished [permissions rebuild] %s in " ZBX_FS_DBL " sec",
			zbx_result_string(res), zbx_time() - start);

	pthread_mutex_lock(&rebuild_lock);
	rebuild_state = PERM_REBUILD_IDLE;
	pthread_mutex_unlock(&rebuild_lock);

	return NULL;
}


/******************************************************************************
 *                                                                            *
 * Purpose: starts forced rebuilding of the permissions cache                 *
 *                                                                            *
 * Comments: Starts the rebuild in a new detached thread, so the main loop    *
 *           continues processing other tasks.                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_permissions_rebuild_start(void)
{
	int		err;
	pthread_t	thread;
	pthread_attr_t	attr;

	pthread_mutex_lock(&rebuild_lock);

	if (PERM_REBUILD_IDLE != rebuild_state)
	{
		pthread_mutex_unlock(&rebuild_lock);
		zabbix_log(LOG_LEVEL_WARNING, "forced rebuilding of the permissions cache is already in progress");
		return;
	}

	rebuild_state = PERM_REBUILD_RUNNING;

	pthread_mutex_unlock(&rebuild_lock);

	if (0 == (err = pthread_attr_init(&attr)))
	{
		if (0 == (err = pthread_attr_setdetachstate(&attr, PTHREAD_CREATE_DETACHED)))
			err = pthread_create(&thread, &attr, permissions_rebuild_entry, NULL);

		pthread_attr_destroy(&attr);
	}

	if (0 != err)
	{
		pthread_mutex_lock(&rebuild_lock);
		rebuild_state = PERM_REBUILD_IDLE;
		pthread_mutex_unlock(&rebuild_lock);

		zabbix_log(LOG_LEVEL_WARNING, "cannot create thread for forced rebuilding of the permissions"
				" cache: %s", zbx_strerror(err));
	}
}
