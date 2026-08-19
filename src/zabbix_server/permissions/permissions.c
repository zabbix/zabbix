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
#include "zbxdbhigh.h"
#include "zbxthreads.h"
#include "zbxdbwrap.h"

static pid_t	rebuild_pid = ZBX_THREAD_HANDLE_NULL;

typedef struct
{
	char			hash_str[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	zbx_vector_uint64_t	groupids;
	zbx_vector_uint64_t	ids;
} zbx_group_set_t;

static int	permission_groupsets_make(zbx_vector_uint64_t *ids, const char *fld_name_id,
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

		result = zbx_db_select("select %s from %s where %s=" ZBX_FS_UI64 " order by %s",
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

static int	permission_groupsets_insert(const char *tbl_name, zbx_hashset_t *group_sets, zbx_db_insert_t *db_gset,
		zbx_db_insert_t *db_gset_groups, zbx_db_insert_t *db_gset_parents)
{
	zbx_uint64_t		gsetid;
	zbx_hashset_iter_t	iter;
	zbx_group_set_t		*gset_ptr;

	if (0 == group_sets->num_data)
		return SUCCEED;

	gsetid = zbx_db_get_maxid_num(tbl_name, group_sets->num_data);

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

static int	permission_hgsets_add(zbx_vector_uint64_t *ids)
{
	int		ret;
	zbx_hashset_t	hgsets;
	zbx_db_insert_t	db_insert, db_insert_groups, db_insert_hosts;

	zbx_hashset_create(&hgsets, 1, permission_group_set_hash, permission_group_set_compare);
	zbx_db_insert_prepare(&db_insert, "hgset", "hgsetid", "hash", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_groups, "hgset_group", "hgsetid", "groupid", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_hosts, "host_hgset", "hostid", "hgsetid", (char*)NULL);

	if (SUCCEED == (ret = permission_groupsets_make(ids, "hostid", "groupid", "hosts_groups", &hgsets, 0)))
		ret = permission_groupsets_insert("hgset", &hgsets, &db_insert, &db_insert_groups, &db_insert_hosts);

	zbx_db_insert_clean(&db_insert);
	zbx_db_insert_clean(&db_insert_groups);
	zbx_db_insert_clean(&db_insert_hosts);

	permission_groupsets_destroy(&hgsets);

	return ret;
}

static int	permission_ugsets_add(zbx_vector_uint64_t *ids)
{
	int			ret;
	zbx_hashset_t		ugsets;
	zbx_db_insert_t		db_insert, db_insert_groups, db_insert_users;

	zbx_hashset_create(&ugsets, 1, permission_group_set_hash, permission_group_set_compare);
	zbx_db_insert_prepare(&db_insert, "ugset", "ugsetid", "hash", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_groups, "ugset_group", "ugsetid", "usrgrpid", (char*)NULL);
	zbx_db_insert_prepare(&db_insert_users, "user_ugset", "userid", "ugsetid", (char*)NULL);

	if (SUCCEED == (ret = permission_groupsets_make(ids, "userid", "usrgrpid", "users_groups", &ugsets, 1)))
		ret = permission_groupsets_insert("ugset", &ugsets, &db_insert, &db_insert_groups, &db_insert_users);

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
 *           separate process. The permission cache tables are cleared and    *
 *           repopulated within a single transaction, which is rolled back    *
 *           if the rebuilding fails.                                         *
 *                                                                            *
 ******************************************************************************/
static void	permissions_rebuild_task(void)
{
	int			res = SUCCEED;
	zbx_vector_uint64_t	ids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&ids);

	(void)zbx_db_connect(ZBX_DB_CONNECT_NORMAL);
	zbx_db_begin();

	static const char	*tables[] = {"user_ugset", "host_hgset", "permission", "ugset_group", "ugset",
			"hgset_group", "hgset"};

	for (int i = 0; i < (int)ARRSIZE(tables); i++)
	{
		if (ZBX_DB_OK > zbx_db_execute("delete from %s", tables[i]))
		{
			res = FAIL;
			zabbix_log(LOG_LEVEL_WARNING, "cannot delete from permission cache tables");
			goto out;
		}
	}

	zbx_db_select_uint64("select hostid from hosts where flags<>2", &ids);

	res = permission_hgsets_add(&ids);

	if (SUCCEED != res)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add permission hgsets");
		goto out;
	}

	zbx_vector_uint64_clear(&ids);

	zbx_db_select_uint64(
			"select u.userid"
			" from users u"
				" join role r on u.roleid=r.roleid"
			" where r.type<>3"
				" and u.userid in ("
					"select userid"
					" from users_groups"
				")", &ids);

	res = permission_ugsets_add(&ids);

	if (SUCCEED != res)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add permission ugsets");
		goto out;
	}

	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "permission", "ugsetid", "hgsetid", "permission", (char*)NULL);

	result = zbx_db_select("select u.ugsetid,h.hgsetid,max(r.permission)"
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

	res = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (SUCCEED != res)
		zabbix_log(LOG_LEVEL_WARNING, "cannot insert permissions");
out:
	if (SUCCEED == res)
	{
		if (ZBX_DB_OK != zbx_db_commit())
			res = FAIL;
	}
	else
		zbx_db_rollback();

	zbx_db_close();

	zbx_vector_uint64_destroy(&ids);

	zabbix_log(LOG_LEVEL_WARNING, "permissions rebuild: %s", zbx_result_string(res));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: starts forced permission cache rebuilding in a separate process   *
 *                                                                            *
 ******************************************************************************/
void	zbx_permissions_rebuild_start(void)
{
	if (ZBX_THREAD_HANDLE_NULL != rebuild_pid)
	{
		zabbix_log(LOG_LEVEL_WARNING, "forced rebuilding of the permissions cache is already in progress");
		return;
	}

	/* so the child cannot inherit the connection */
	(void)zbx_db_close();

	pid_t	pid;

	switch (pid = zbx_fork())
	{
		case -1:
			zabbix_log(LOG_LEVEL_WARNING, "cannot fork a process for forced rebuilding of the permissions"
					" cache: %s", zbx_strerror(errno));
			break;
		case 0: /* child */
			zbx_setproctitle("permissions rebuild");
			permissions_rebuild_task();
			exit(EXIT_SUCCESS);
		default: /* parent */
			rebuild_pid = pid;
			zabbix_log(LOG_LEVEL_WARNING, "process started [permissions rebuild]");
	}

	(void)zbx_db_connect(ZBX_DB_CONNECT_NORMAL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reaps the finished permission cache rebuilding process            *
 *                                                                            *
 * Comments: Resets rebuild_pid to ZBX_THREAD_HANDLE_NULL when the process    *
 *           started by zbx_permissions_rebuild_start() has exited, allowing  *
 *           the next rebuilding to be started. Must be called periodically.  *
 *                                                                            *
 ******************************************************************************/
void	zbx_permissions_rebuild_poll(void)
{
	if (ZBX_THREAD_HANDLE_NULL == rebuild_pid)
		return;

	if (0 == (waitpid(rebuild_pid, NULL, WNOHANG)))
		return;

	rebuild_pid = ZBX_THREAD_HANDLE_NULL;

	zabbix_log(LOG_LEVEL_WARNING, "process finished [permissions rebuild]");
}

/******************************************************************************
 *                                                                            *
 * Purpose: terminates the permission cache rebuilding process                *
 *                                                                            *
 * Comments: Must be called before the process exits to avoid leaving an      *
 *           orphaned child.                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_permissions_rebuild_stop(void)
{
	if (ZBX_THREAD_HANDLE_NULL == rebuild_pid)
		return;

	if (-1 == kill(rebuild_pid, SIGKILL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot kill permissions cache rebuild process: %s", zbx_strerror(errno));
	}

	pid_t	pid;

	do
	{
		pid = waitpid(rebuild_pid, NULL, 0);
	}
	while (-1 == pid && EINTR == errno);

	if (0 > pid)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot wait for permissions cache rebuild process: %s",
				zbx_strerror(errno));
	}

	zabbix_log(LOG_LEVEL_WARNING, "process terminated [permissions rebuild]");
}
