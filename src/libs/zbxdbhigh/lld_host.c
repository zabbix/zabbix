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
	zbx_uint64_t		hostid;
	zbx_vector_uint64_t	groupids;	/* host groups which should be added */
	char			*host_proto;
	char			*host;
	char			*name;
#define ZBX_FLAG_LLD_HOST_DISCOVERED	0x01	/* hosts which should be updated or added */
#define ZBX_FLAG_LLD_HOST_UPDATE_HOST	0x02	/* hosts.host and host_discovery.host fields should be updated  */
#define ZBX_FLAG_LLD_HOST_UPDATE_NAME	0x04	/* hosts.name field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE	(ZBX_FLAG_LLD_HOST_UPDATE_HOST | ZBX_FLAG_LLD_HOST_UPDATE_NAME)
	unsigned char		flags;
}
zbx_lld_host_t;

static void	DBlld_host_free(zbx_lld_host_t *host)
{
	zbx_vector_uint64_destroy(&host->groupids);
	zbx_free(host->host);
	zbx_free(host->name);
	zbx_free(host);
}

static void	DBlld_hosts_free(zbx_vector_ptr_t *hosts)
{
	while (0 != hosts->values_num)
		DBlld_host_free((zbx_lld_host_t *)hosts->values[--hosts->values_num]);
}

typedef struct
{
	char		*ip_esc;
	char		*dns_esc;
	char		*port_esc;
	unsigned char	main;
	unsigned char	type;
	unsigned char	useip;
}
zbx_lld_interface_t;

static void	DBlld_clean_interfaces(zbx_vector_ptr_t *interfaces)
{
	zbx_lld_interface_t	*interface;

	while (0 != interfaces->values_num)
	{
		interface = (zbx_lld_interface_t *)interfaces->values[--interfaces->values_num];

		zbx_free(interface->port_esc);
		zbx_free(interface->dns_esc);
		zbx_free(interface->ip_esc);
		zbx_free(interface);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_get_hosts                                                  *
 *                                                                            *
 * Purpose: retrieves existing hosts for the specified host prototype         *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype identificator              *
 *             hosts         - [OUT] list of hosts                            *
 *                                                                            *
 ******************************************************************************/
static void	DBlld_get_hosts(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts)
{
	const char	*__function_name = "DBlld_get_hosts";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_lld_host_t	*host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select hd.hostid,hd.host,h.host,h.name"
			" from host_discovery hd"
				" join hosts h"
					" on h.hostid=hd.hostid"
			" where hd.parent_hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		host = zbx_malloc(NULL, sizeof(zbx_lld_host_t));

		ZBX_STR2UINT64(host->hostid, row[0]);
		host->host_proto = zbx_strdup(NULL, row[1]);
		host->host = zbx_strdup(NULL, row[2]);
		host->name = zbx_strdup(NULL, row[3]);
		zbx_vector_uint64_create(&host->groupids);
		host->flags = 0x00;

		zbx_vector_ptr_append(hosts, host);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_hosts_validate                                             *
 *                                                                            *
 * Parameters: hosts - [IN] list of hosts; should be sorted by hostid         *
 *                                                                            *
 ******************************************************************************/
void	DBlld_hosts_validate(zbx_vector_ptr_t *hosts, char **error)
{
	const char		*__function_name = "DBlld_hosts_validate";

	char			*tnames = NULL, *vnames = NULL, *host_esc, *name_esc;
	size_t			tnames_alloc = 256, tnames_offset = 0,
				vnames_alloc = 256, vnames_offset = 0;
	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_lld_host_t		*host_a, *host_b;
	zbx_vector_uint64_t	hostids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&hostids);

	tnames = zbx_malloc(tnames, tnames_alloc);	/* list of technical host names */
	vnames = zbx_malloc(vnames, vnames_alloc);	/* list of visible host names */

	for (i = 0; i < hosts->values_num; i++)
	{
		host_a = (zbx_lld_host_t *)hosts->values[i];

		for (j = i + 1; j < hosts->values_num; j++)
		{
			host_b = (zbx_lld_host_t *)hosts->values[j];

			if (0 == strcmp(host_a->host, host_b->host))
			{
				*error = zbx_strdcatf(*error, "Cannot %s host:"
						" host with the same name \"%s\" already exists.\n",
						(0 != host_a->hostid ? "update" : "create"), host_a->host);
				host_a->flags = 0;
				break;
			}

			if (0 == strcmp(host_a->name, host_b->name))
			{
				*error = zbx_strdcatf(*error, "Cannot %s host:"
						" host with the same visible name \"%s\" already exists.\n",
						(0 != host_a->hostid ? "update" : "create"), host_a->name);
				host_a->flags = 0;
				break;
			}
		}

		if (0 == host_a->flags)
			continue;

		if (0 != host_a->hostid)
			zbx_vector_uint64_append(&hostids, host_a->hostid);

		host_esc = DBdyn_escape_string_len(host_a->host, HOST_HOST_LEN);
		name_esc = DBdyn_escape_string_len(host_a->name, HOST_NAME_LEN);

		if (0 != tnames_offset)
			zbx_chrcpy_alloc(&tnames, &tnames_alloc, &tnames_offset, ',');
		zbx_chrcpy_alloc(&tnames, &tnames_alloc, &tnames_offset, '\'');
		zbx_strcpy_alloc(&tnames, &tnames_alloc, &tnames_offset, host_esc);
		zbx_chrcpy_alloc(&tnames, &tnames_alloc, &tnames_offset, '\'');

		if (0 != vnames_offset)
			zbx_chrcpy_alloc(&vnames, &vnames_alloc, &vnames_offset, ',');
		zbx_chrcpy_alloc(&vnames, &vnames_alloc, &vnames_offset, '\'');
		zbx_strcpy_alloc(&vnames, &vnames_alloc, &vnames_offset, name_esc);
		zbx_chrcpy_alloc(&vnames, &vnames_alloc, &vnames_offset, '\'');

		zbx_free(name_esc);
		zbx_free(host_esc);
	}

	if (0 != tnames_offset)	/* we have the discovered hosts */
	{
		char	*sql = NULL;
		size_t	sql_alloc = 256, sql_offset = 0;

		sql = zbx_malloc(sql, sql_alloc);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select host,name"
				" from hosts"
				" where status in (%d,%d,%d)"
					" and flags<>%d"
					" and ("
						"host in (%s) or name in (%s)"
					")",
				HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE,
				ZBX_FLAG_DISCOVERY_CHILD,
				tnames, vnames);
		if (0 != hostids.values_num)
		{
			zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
					hostids.values, hostids.values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			for (i = 0; i < hosts->values_num; i++)
			{
				host_a = (zbx_lld_host_t *)hosts->values[i];

				if (0 == host_a->flags)
					continue;

				if (0 == strcmp(host_a->host, row[0]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s host:"
							" host with the same name \"%s\" already exists.\n",
							(0 != host_a->hostid ? "update" : "create"), host_a->host);
					host_a->flags = 0;
					continue;
				}

				if (0 == strcmp(host_a->name, row[1]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s host:"
							" host with the same visible name \"%s\" already exists.\n",
							(0 != host_a->hostid ? "update" : "create"), host_a->name);
					host_a->flags = 0;
				}
			}
		}
		DBfree_result(result);

		zbx_free(sql);
	}

	zbx_free(vnames);
	zbx_free(tnames);

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_hosts_drop_garbage                                         *
 *                                                                            *
 * Purpose: removes host records without ZBX_FLAG_LLD_HOST_DISCOVERED flag    *
 *                                                                            *
 * Parameters: hosts - [IN] list of hosts                                     *
 *                                                                            *
 ******************************************************************************/
static void	DBlld_hosts_drop_garbage(zbx_vector_ptr_t *hosts)
{
	const char	*__function_name = "DBlld_hosts_drop_garbage";

	int		i;
	zbx_lld_host_t	*host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 != (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_ptr_remove_noorder(hosts, i--);
		DBlld_host_free(host);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	DBlld_host_make(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts, const char *host_proto,
		const char *name_proto, struct zbx_json_parse *jp_row)
{
	const char	*__function_name = "DBlld_host_make";

	char		*buffer = NULL;
	int		i;
	zbx_lld_host_t	*host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 != (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		buffer = zbx_strdup(buffer, host->host_proto);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);

		if (0 == strcmp(host->host, buffer))
			break;
	}

	if (i == hosts->values_num)	/* no host found */
	{
		host = zbx_malloc(NULL, sizeof(zbx_lld_host_t));

		host->hostid = 0;
		host->host_proto = NULL;
		host->host = zbx_strdup(NULL, host_proto);
		substitute_discovery_macros(&host->host, jp_row, ZBX_MACRO_ANY, NULL, 0);
		host->name = zbx_strdup(NULL, name_proto);
		substitute_discovery_macros(&host->name, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_vector_uint64_create(&host->groupids);

		zbx_vector_ptr_append(hosts, host);
	}
	else
	{
		/* host technical name */
		if (0 != strcmp(host->host_proto, host_proto))	/* the new host prototype differs */
		{
			host->host = zbx_strdup(host->host, host_proto);
			substitute_discovery_macros(&host->host, jp_row, ZBX_MACRO_ANY, NULL, 0);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_HOST;
		}

		/* host visible name */
		buffer = zbx_strdup(buffer, name_proto);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
		if (0 != strcmp(host->name, buffer))
		{
			zbx_free(host->name);
			host->name = buffer;
			buffer = NULL;
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_NAME;
		}
	}

	host->flags |= ZBX_FLAG_LLD_HOST_DISCOVERED;

	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_hostgroups_make                                            *
 *                                                                            *
 * Parameters: hosts        - [IN/OUT] list of hosts                          *
 *                            should be sorted by hostid                      *
 *             hostgroupids - [OUT] list of host groups which should be       *
 *                            deleted                                         *
 *                                                                            *
 ******************************************************************************/
static void	DBlld_hostgroups_make(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *hosts,
		zbx_vector_uint64_t *hostgroupids)
{
	const char		*__function_name = "DBlld_hostgroups_make";

	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	groupids, hostids;
	zbx_uint64_t		hostgroupid, hostid, groupid;
	zbx_lld_host_t		*host;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_vector_uint64_create(&groupids);
	zbx_vector_uint64_create(&hostids);

	/* list of host groups which should be added */

	result = DBselect(
			"select hg.groupid"
			" from hosts_groups hg,items i"
			" where hg.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64, lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(&groupids, groupid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		for (j = 0; j < groupids.values_num; j++)
			zbx_vector_uint64_append(&host->groupids, groupids.values[j]);

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	if (0 != hostids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostid,groupid,hostgroupid"
				" from hosts_groups"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_STR2UINT64(groupid, row[1]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(hosts, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = (zbx_lld_host_t *)hosts->values[i];

			if (FAIL == (i = zbx_vector_uint64_bsearch(&host->groupids, groupid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				/* host groups which should be deleted */
				ZBX_STR2UINT64(hostgroupid, row[2]);
				zbx_vector_uint64_append(hostgroupids, hostgroupid);
			}
			else
			{
				/* host groups which are already added */
				zbx_vector_uint64_remove(&host->groupids, i);
			}
		}
		DBfree_result(result);

		zbx_vector_uint64_sort(hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_vector_uint64_destroy(&groupids);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_hosts_save                                                 *
 *                                                                            *
 * Parameters: hosts        - [IN] list of hosts; should be sorted by hostid  *
 *             status       - [IN] initial host satatus                       *
 *             hostgroupids - [IN] host groups which should be deleted        *
 *                                                                            *
 ******************************************************************************/
static void	DBlld_hosts_save(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts, const char *host_proto,
		unsigned char status, zbx_vector_uint64_t *hostgroupids, zbx_vector_ptr_t *interfaces)
{
	const char		*__function_name = "DBlld_hosts_save";

	int			i, j, new_hosts = 0, upd_hosts = 0, new_hostgroups = 0, new_interfaces;
	zbx_lld_host_t		*host;
	zbx_lld_interface_t	*interface;
	zbx_uint64_t		hostid = 0, hostdiscoveryid = 0, hostgroupid = 0, interfaceid = 0;
	char			*sql1 = NULL, *sql2 = NULL, *sql3 = NULL, *sql4 = NULL, *sql5 = NULL, *sql6 = NULL,
				*host_esc, *name_esc, *host_proto_esc;
	size_t			sql1_alloc = 8 * ZBX_KIBIBYTE, sql1_offset = 0,
				sql2_alloc = 2 * ZBX_KIBIBYTE, sql2_offset = 0,
				sql3_alloc = 8 * ZBX_KIBIBYTE, sql3_offset = 0,
				sql4_alloc = 2 * ZBX_KIBIBYTE, sql4_offset = 0,
				sql5_alloc = 256, sql5_offset = 0,
				sql6_alloc = 2 * ZBX_KIBIBYTE, sql6_offset = 0;
	const char		*ins_hosts_sql = "insert into hosts (hostid,host,name,status,flags) values ";
	const char		*ins_host_discovery_sql =
				"insert into host_discovery (hostdiscoveryid,hostid,parent_hostid,host) values ";
	const char		*ins_hosts_groups_sql = "insert into hosts_groups (hostgroupid,hostid,groupid) values ";
	const char		*ins_interface_sql =
				"insert into interface (interfaceid,hostid,type,main,useip,ip,dns,port) values ";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == host->hostid)
			new_hosts++;
		else if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE))
			upd_hosts++;

		new_hostgroups += host->groupids.values_num;
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

	if (0 != upd_hosts)
	{
		sql3 = zbx_malloc(sql3, sql3_alloc);
		DBbegin_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
	}

	if (0 != new_hostgroups)
	{
		hostgroupid = DBget_maxid_num("hosts_groups", new_hostgroups);

		sql4 = zbx_malloc(sql4, sql4_alloc);
		DBbegin_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, ins_hosts_groups_sql);
#endif
	}

	if (0 != hostgroupids->values_num)
	{
		sql5 = zbx_malloc(sql5, sql5_alloc);

		zbx_strcpy_alloc(&sql5, &sql5_alloc, &sql5_offset, "delete from hosts_groups where");
		DBadd_condition_alloc(&sql5, &sql5_alloc, &sql5_offset, "hostgroupid",
				hostgroupids->values, hostgroupids->values_num);
	}

	if (0 != (new_interfaces = new_hosts * interfaces->values_num))
	{
		interfaceid = DBget_maxid_num("interface", new_interfaces);

		sql6 = zbx_malloc(sql6, sql6_alloc);
		DBbegin_multiple_update(&sql6, &sql6_alloc, &sql6_offset);
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql6, &sql6_alloc, &sql6_offset, ins_interface_sql);
#endif
	}

	host_proto_esc = DBdyn_escape_string(host_proto);

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
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s')" ZBX_ROW_DL,
					hostdiscoveryid, host->hostid, parent_hostid, host_proto_esc);

			hostdiscoveryid++;

			for (j = 0; j < interfaces->values_num; j++)
			{
				interface = (zbx_lld_interface_t *)interfaces->values[j];
#ifndef HAVE_MULTIROW_INSERT
				zbx_strcpy_alloc(&sql6, &sql6_alloc, &sql6_offset, ins_interface_sql);
#endif
				zbx_snprintf_alloc(&sql6, &sql6_alloc, &sql6_offset,
						"(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d,%d,'%s','%s','%s')" ZBX_ROW_DL,
						interfaceid++, host->hostid, (int)interface->type, (int)interface->main,
						(int)interface->useip, interface->ip_esc, interface->dns_esc,
						interface->port_esc);
			}
		}
		else
		{
			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql3, &sql3_alloc, &sql3_offset, "update hosts set ");
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
				{
					zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset, "host='%s'", host_esc);
					d = ",";
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
				{
					zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
							"%sname='%s'", d, name_esc);
				}
				zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset, " where hostid=" ZBX_FS_UI64 ";\n",
						host->hostid);
			}

			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			{
				zbx_snprintf_alloc(&sql3, &sql3_alloc, &sql3_offset,
						"update host_discovery"
						" set host='%s'"
						" where hostid=" ZBX_FS_UI64
							" and parent_hostid=" ZBX_FS_UI64 ";\n",
						host_proto_esc, host->hostid, parent_hostid);
			}
		}

		for (j = 0; j < host->groupids.values_num; j++)
		{
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, ins_hosts_groups_sql);
#endif
			zbx_snprintf_alloc(&sql4, &sql4_alloc, &sql4_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")" ZBX_ROW_DL,
					hostgroupid++, host->hostid, host->groupids.values[j]);
		}

		zbx_free(name_esc);
		zbx_free(host_esc);
	}

	zbx_free(host_proto_esc);

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

	if (0 != upd_hosts)
	{
		DBend_multiple_update(&sql3, &sql3_alloc, &sql3_offset);
		DBexecute("%s", sql3);
		zbx_free(sql3);
	}

	if (0 != new_hostgroups)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql4_offset--;
		zbx_strcpy_alloc(&sql4, &sql4_alloc, &sql4_offset, ";\n");
#endif
		DBend_multiple_update(&sql4, &sql4_alloc, &sql4_offset);
		DBexecute("%s", sql4);
		zbx_free(sql4);
	}

	if (0 != hostgroupids->values_num)
	{
		DBexecute("%s", sql5);
		zbx_free(sql5);
	}

	if (0 != new_interfaces)
	{
#ifdef HAVE_MULTIROW_INSERT
		sql6_offset--;
		zbx_strcpy_alloc(&sql6, &sql6_alloc, &sql6_offset, ";\n");
#endif
		DBend_multiple_update(&sql6, &sql6_alloc, &sql6_offset);
		DBexecute("%s", sql6);
		zbx_free(sql6);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
	zbx_vector_ptr_t	hosts, interfaces;
	zbx_vector_uint64_t	hostgroupids;
	zbx_lld_interface_t	*interface;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&hosts);
	zbx_vector_uint64_create(&hostgroupids);
	zbx_vector_ptr_create(&interfaces);

	result = DBselect(
			"select hi.type,hi.main,hi.useip,hi.ip,hi.dns,hi.port"
			" from interface hi,items i"
			" where hi.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64, lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		interface = zbx_malloc(NULL, sizeof(zbx_lld_interface_t));

		interface->type = (unsigned char)atoi(row[0]);
		interface->main = (unsigned char)atoi(row[1]);
		interface->useip = (unsigned char)atoi(row[2]);
		interface->ip_esc = DBdyn_escape_string(row[3]);
		interface->dns_esc = DBdyn_escape_string(row[4]);
		interface->port_esc = DBdyn_escape_string(row[5]);

		zbx_vector_ptr_append(&interfaces, interface);
	}
	DBfree_result(result);

	result = DBselect(
			"select h.hostid,h.host,h.name,h.status"
			" from hosts h,host_discovery hd"
			" where h.hostid=hd.hostid"
				" and hd.parent_itemid=" ZBX_FS_UI64, lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	parent_hostid;
		const char	*host_proto, *name_proto;
		unsigned char	status;

		ZBX_STR2UINT64(parent_hostid, row[0]);
		host_proto = row[1];
		name_proto = row[2];
		status = (unsigned char)atoi(row[3]);

		DBlld_get_hosts(parent_hostid, &hosts);

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

			DBlld_host_make(parent_hostid, &hosts, host_proto, name_proto, &jp_row);
		}

		DBlld_hosts_drop_garbage(&hosts);
		zbx_vector_ptr_sort(&hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		DBlld_hosts_validate(&hosts, error);

		DBlld_hosts_drop_garbage(&hosts);
		zbx_vector_ptr_sort(&hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		DBlld_hostgroups_make(lld_ruleid, &hosts, &hostgroupids);

		DBlld_hosts_save(parent_hostid, &hosts, host_proto, status, &hostgroupids, &interfaces);

		DBlld_hosts_free(&hosts);
	}
	DBfree_result(result);

	DBlld_clean_interfaces(&interfaces);
	zbx_vector_ptr_destroy(&interfaces);
	zbx_vector_uint64_destroy(&hostgroupids);
	zbx_vector_ptr_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
