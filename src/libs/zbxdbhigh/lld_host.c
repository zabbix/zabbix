/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
#include "db.h"
#include "log.h"
#include "zbxalgo.h"
#include "zbxserver.h"

typedef struct
{
	zbx_uint64_t	hostid;
	char		*host;
	char		*name;
}
zbx_lld_host_t;

static void	DBlld_clean_hosts(zbx_vector_ptr_t *hosts)
{
	zbx_lld_host_t	*host;

	while (0 != hosts->values_num)
	{
		host = (zbx_lld_host_t *)hosts->values[--hosts->values_num];

		zbx_free(host->host);
		zbx_free(host->name);
		zbx_free(host);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_host_exists                                                *
 *                                                                            *
 * Parameters: hostid - [IN] host identificator from database                 *
 *             host   - [IN] new host name with substituted macros            *
 *             name   - [IN] new host visible name with substituted macros    *
 *             hosts  - [IN] list of already checked hosts                    *
 *                                                                            *
 * Return value: SUCCEED if host exists otherwise FAIL                        *
 *                                                                            *
 ******************************************************************************/
int	DBlld_host_exists(zbx_uint64_t hostid, const char *host, const char *name, zbx_vector_ptr_t *hosts)
{
	char		*host_esc, *name_esc, *sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	DB_RESULT	result;
	int		i, ret = FAIL;

	for (i = 0; i < hosts->values_num; i++)
	{
		if (0 == strcmp(name, ((zbx_lld_host_t *)hosts->values[i])->name))
			return SUCCEED;

		if (0 == strcmp(host, ((zbx_lld_host_t *)hosts->values[i])->host))
			return SUCCEED;
	}

	sql = zbx_malloc(sql, sql_alloc);
	host_esc = DBdyn_escape_string_len(host, HOST_HOST_LEN);
	name_esc = DBdyn_escape_string_len(name, HOST_NAME_LEN);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select null from hosts where (host='%s' or name='%s')", host_esc, name_esc);
	if (0 != hostid)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and hostid<>" ZBX_FS_UI64, hostid);

	result = DBselectN(sql, 1);

	if (NULL != DBfetch(result))
		ret = SUCCEED;
	DBfree_result(result);

	zbx_free(name_esc);
	zbx_free(host_esc);
	zbx_free(sql);

	return ret;
}

static int	DBlld_make_host(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts, const char *host_proto,
		const char *name_proto, struct zbx_json_parse *jp_row, char **error)
{
	const char	*__function_name = "DBlld_make_host";

	DB_RESULT	result;
	DB_ROW		row;
	char		*host_esc;
	int		ret = SUCCEED;
	zbx_lld_host_t	*host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	host = zbx_calloc(NULL, 1, sizeof(zbx_lld_host_t));

	host->host = zbx_strdup(NULL, host_proto);
	substitute_discovery_macros(&host->host, jp_row, ZBX_MACRO_ANY, NULL, 0);

	host->name = zbx_strdup(NULL, name_proto);
	substitute_discovery_macros(&host->name, jp_row, ZBX_MACRO_ANY, NULL, 0);

	host_esc = DBdyn_escape_string_len(host->host, HOST_HOST_LEN);

	result = DBselect(
			"select h.hostid"
			" from hosts h,host_discovery hd"
			" where h.hostid=hd.hostid"
				" and hd.parent_hostid=" ZBX_FS_UI64
				" and h.host='%s'",
			parent_hostid, host_esc);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(host->hostid, row[0]);
	DBfree_result(result);

/*	if (0 == host->hostid)
	{
		result = DBselect(
				"select distinct i.itemid,id.key_,i.key_"
				" from items i,item_discovery id"
				" where i.itemid=id.itemid"
					" and id.parent_itemid=" ZBX_FS_UI64,
				parent_itemid);

		while (NULL != (row = DBfetch(result)))
		{
			char	*old_key = NULL;

			old_key = zbx_strdup(old_key, row[1]);
			substitute_key_macros(&old_key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);

			if (0 == strcmp(old_key, row[2]))
				ZBX_STR2UINT64(item->itemid, row[0]);

			zbx_free(old_key);

			if (0 != item->itemid)
				break;
		}
		DBfree_result(result);
	}
*/
	if (SUCCEED == DBlld_host_exists(host->hostid, host->host, host->name, hosts))
	{
		*error = zbx_strdcatf(*error, "Cannot %s host \"%s\": host already exists\n",
				0 != host->hostid ? "update" : "create", host->host);
		ret = FAIL;
		goto out;
	}

	zbx_vector_ptr_append(hosts, host);
out:
	if (FAIL == ret)
	{
		zbx_free(host->name);
		zbx_free(host->host);
		zbx_free(host);
	}

	zbx_free(host_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	DBlld_save_hosts(zbx_vector_ptr_t *hosts, unsigned char status, zbx_uint64_t parent_hostid)
{
	int		i, new_hosts = 0;
	zbx_lld_host_t	*host;
	zbx_uint64_t	hostid = 0, hostdiscoveryid = 0;
	char		*sql1 = NULL, *sql2 = NULL, *sql3 = NULL, *host_esc, *name_esc;
	size_t		sql1_alloc = 8 * ZBX_KIBIBYTE, sql1_offset = 0,
			sql2_alloc = 2 * ZBX_KIBIBYTE, sql2_offset = 0,
			sql3_alloc = 8 * ZBX_KIBIBYTE, sql3_offset = 0;
	const char	*ins_hosts_sql = "insert into hosts (hostid,host,name,status,flags) values ";
	const char	*ins_host_discovery_sql =
			"insert into host_discovery (hostdiscoveryid,hostid,parent_hostid) values ";

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == host->hostid)
			new_hosts++;
	}

	if (0 != new_hosts)
	{
		hostid = DBget_maxid_num("hosts", new_hosts);
		hostdiscoveryid = DBget_maxid_num("host_discovery", new_hosts);

		sql1 = zbx_malloc(sql1, sql1_alloc);
		sql2 = zbx_malloc(sql2, sql2_alloc);
		DBbegin_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBbegin_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_hosts_sql);
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_host_discovery_sql);
#endif
	}

	if (new_hosts < hosts->values_num)
	{
		sql3 = zbx_malloc(sql3, sql3_alloc);
		DBbegin_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
	}

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		host_esc = DBdyn_escape_string_len(host->host, HOST_HOST_LEN);
		name_esc = DBdyn_escape_string_len(host->name, HOST_NAME_LEN);

		if (0 == host->hostid)
		{
			host->hostid = hostid++;
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ins_hosts_sql);
#endif
			zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
					"(" ZBX_FS_UI64 ",'%s','%s',%d,%d)" ZBX_ROW_DL,
					host->hostid, host_esc, name_esc, (int)status, ZBX_FLAG_DISCOVERY_CREATED);

#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ins_host_discovery_sql);
#endif
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")" ZBX_ROW_DL,
					hostdiscoveryid, host->hostid, parent_hostid);

			hostdiscoveryid++;
		}
		else
		{
			zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
					"update hosts"
					" set host='%s',"
						"name='%s'"
					" where hostid=" ZBX_FS_UI64 ";\n",
					host_esc, name_esc, host->hostid);

/*			zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
					"update host_discovery"
					" set key_='%s',"
						"lastcheck=%d,ts_delete=0"
					" where hostid=" ZBX_FS_UI64
						" and parent_hostid=" ZBX_FS_UI64 ";\n",
					key_proto_esc, lastcheck, host->hostid, parent_hostid);*/
		}

		zbx_free(name_esc);
		zbx_free(host_esc);
	}

	if (0 != new_hosts)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql1_offset--;
		sql2_offset--;
		zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, ";\n");
		zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
#endif
		DBend_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBend_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
		DBexecute("%s", sql1);
		DBexecute("%s", sql2);
		zbx_free(sql1);
		zbx_free(sql2);
	}

	if (new_hosts < hosts->values_num)
	{
		DBend_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
		DBexecute("%s", sql3);
		zbx_free(sql3);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_update_hosts                                               *
 *                                                                            *
 * Purpose: add or update low-level discovered hosts                          *
 *                                                                            *
 ******************************************************************************/
void	DBlld_update_hosts(zbx_uint64_t lld_ruleid, struct zbx_json_parse *jp_data, char **error,
		const char *f_macro, const char *f_regexp, ZBX_REGEXP *regexps, int regexps_num)
{
	const char		*__function_name = "DBlld_update_hosts";

	struct zbx_json_parse	jp_row;
	const char		*p;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	hosts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&hosts);

	result = DBselect(
			"select h.hostid,h.host,h.name,h.status"
			" from hosts h,host_discovery hd"
			" where h.hostid=hd.hostid"
				" and hd.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	parent_hostid;
		const char	*host_proto, *name_proto;
		unsigned char	status;

		ZBX_STR2UINT64(parent_hostid, row[0]);
		host_proto = row[1];
		name_proto = row[2];
		status = (unsigned char)atoi(row[3]);

		p = NULL;
		/* {"data":[{"{#VMNAME}":"vm_001"},{"{#VMNAME}":"vm_002"},...]} */
		/*          ^                                                   */
		while (NULL != (p = zbx_json_next(jp_data, p)))
		{
			/* {"data":[{"{#VMNAME}":"vm_001"},{"{#VMNAME}":"vm_002"},...]} */
			/*          ^--------------------^                              */
			if (FAIL == zbx_json_brackets_open(p, &jp_row))
				continue;

			if (SUCCEED != lld_check_record(&jp_row, f_macro, f_regexp, regexps, regexps_num))
				continue;

			DBlld_make_host(parent_hostid, &hosts, host_proto, name_proto, &jp_row, error);
		}

		zbx_vector_ptr_sort(&hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		DBlld_save_hosts(&hosts, status, parent_hostid);

		DBlld_clean_hosts(&hosts);
	}
	DBfree_result(result);

	zbx_vector_ptr_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
