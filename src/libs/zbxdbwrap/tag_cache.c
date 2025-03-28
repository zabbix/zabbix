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

#include "zbxdbwrap.h"

void	zbx_db_save_item_tag_cache(zbx_uint64_t hostid, zbx_vector_uint64_t *new_itemids)
{
	zbx_db_insert_t	db_insert_item_tag_cache_host_itself;

	zbx_db_execute_multiple_query("insert into item_tag_cache with recursive cte as "
			"( select i0.templateid, i0.itemid, i0.hostid from items i0 "
			"union all select i1.templateid, c.itemid, c.hostid from cte c "
			"join items i1 on c.templateid=i1.itemid where i1.templateid is not NULL) "
			"select cte.itemid,ii.hostid from cte,items ii "
			"where cte.templateid= ii.itemid and ", "cte.itemid", new_itemids);

	zbx_db_insert_prepare(&db_insert_item_tag_cache_host_itself, "item_tag_cache",
			"itemid", "tag_hostid",  (char *)NULL);

	for (int i = 0; i < new_itemids->values_num; i++)
		zbx_db_insert_add_values(&db_insert_item_tag_cache_host_itself, new_itemids->values[i], hostid);

	zbx_db_insert_execute(&db_insert_item_tag_cache_host_itself);
	zbx_db_insert_clean(&db_insert_item_tag_cache_host_itself);
}

int	zbx_db_delete_host_tag_cache(zbx_uint64_t hostid, zbx_vector_uint64_t *del_templateids)
{
	zbx_vector_uint64_t	t_templateids;
	zbx_db_result_t		t_result;
	zbx_db_row_t		t_row;
	size_t			t_sql_alloc = 256, t_sql_offset = 0;
	char			*t_sql = (char *)zbx_malloc(NULL, t_sql_alloc);
	int			res = SUCCEED;

	zbx_vector_uint64_create(&t_templateids);

	zbx_strcpy_alloc(&t_sql, &t_sql_alloc, &t_sql_offset,
			"with recursive cte as ( select h0.templateid, h0.hostid from hosts_templates h0 "
				"union all select h1.templateid, c.hostid from cte c "
				"join hosts_templates h1 on c.templateid=h1.hostid) "
				"select templateid from cte where ");

	zbx_db_add_condition_alloc(&t_sql, &t_sql_alloc, &t_sql_offset, "hostid", del_templateids->values,
			del_templateids->values_num);

	if (NULL == (t_result = zbx_db_select("%s", t_sql)))
	{
		res = FAIL;
		goto clean;
	}

	while (NULL != (t_row = zbx_db_fetch(t_result)))
	{
		zbx_uint64_t	t_templateid;

		ZBX_STR2UINT64(t_templateid, t_row[0]);
		zbx_vector_uint64_append(&t_templateids, t_templateid);
	}

	zbx_db_free_result(t_result);

	for (int i = 0; i < del_templateids->values_num; i++)
		zbx_vector_uint64_append(&t_templateids, del_templateids->values[i]);

	zbx_free(t_sql);
	t_sql_offset = 0;
	t_sql = (char *)zbx_malloc(NULL, t_sql_alloc);

	zbx_snprintf_alloc(&t_sql, &t_sql_alloc, &t_sql_offset,
			"delete from host_tag_cache"
				" where hostid=" ZBX_FS_UI64
				" and",
				hostid);

	zbx_db_add_condition_alloc(&t_sql, &t_sql_alloc, &t_sql_offset, "tag_hostid",
			t_templateids.values, t_templateids.values_num);
	zbx_db_execute("%s", t_sql);
clean:
	zbx_free(t_sql);

	return res;
}

int	zbx_db_copy_item_tag_cache(zbx_uint64_t hostid, zbx_vector_uint64_t *lnk_templateids)
{
	zbx_vector_uint64_t	templateids, hostids;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_insert_t		db_insert_host_tag_cache;

	size_t			sql_alloc = 256, sql_offset = 0;
	char			*sql = (char *)zbx_malloc(NULL, sql_alloc);

	zbx_db_insert_prepare(&db_insert_host_tag_cache, "host_tag_cache", "hostid", "tag_hostid", (char *)NULL);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&templateids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"with recursive cte as ( select h0.templateid, h0.hostid from hosts_templates h0 "
				"union all select h1.templateid, c.hostid from cte c "
				"join hosts_templates h1 on c.templateid=h1.hostid) "
				"select hostid,templateid from cte where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", lnk_templateids->values,
			lnk_templateids->values_num);

	if (NULL == (result = zbx_db_select("%s", sql)))
		return FAIL;


	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	hostid, templateid;

		ZBX_STR2UINT64(hostid, row[0]);
		ZBX_STR2UINT64(templateid, row[1]);
		zbx_vector_uint64_append(&hostids, hostid);
		zbx_vector_uint64_append(&templateids, templateid);
	}

	zbx_db_free_result(result);

	for (int i = 0; i < templateids.values_num; i++)
		zbx_db_insert_add_values(&db_insert_host_tag_cache, hostid, templateids.values[i]);

	for (int i = 0; i < lnk_templateids->values_num; i++)
		zbx_db_insert_add_values(&db_insert_host_tag_cache, hostid,  lnk_templateids->values[i]);

	zbx_db_insert_execute(&db_insert_host_tag_cache);
	zbx_db_insert_clean(&db_insert_host_tag_cache);

	zbx_vector_uint64_destroy(&hostids);
	zbx_vector_uint64_destroy(&templateids);
	zbx_free(sql);

	return SUCCEED;
}

void	zbx_db_save_httptest_tag_cache(zbx_uint64_t hostid, zbx_vector_uint64_t *new_httptestids)
{
	zbx_db_insert_t	db_insert_httptest_tag_cache_host_itself;

	zbx_db_execute_multiple_query(
			"insert into httptest_tag_cache  with recursive cte as ( "
			"select i0.templateid, i0.httptestid, i0.hostid from httptest i0 "
			"union all "
			"select i1.templateid, c.httptestid, c.hostid from cte c "
			"join httptest i1 on c.templateid=i1.httptestid where i1.templateid is not NULL) "
			"select cte.httptestid,ii.hostid from cte,httptest ii where "
			"cte.templateid= ii.httptestid and ",
			"cte.httptestid", new_httptestids);


	zbx_db_insert_prepare(&db_insert_httptest_tag_cache_host_itself, "httptest_tag_cache",
			"httptestid", "tag_hostid",  (char *)NULL);

	for (int i = 0; i < new_httptestids->values_num; i++)
		zbx_db_insert_add_values(&db_insert_httptest_tag_cache_host_itself, new_httptestids->values[i], hostid);

	zbx_db_insert_execute(&db_insert_httptest_tag_cache_host_itself);
	zbx_db_insert_clean(&db_insert_httptest_tag_cache_host_itself);
}
