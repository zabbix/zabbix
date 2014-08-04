/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	zbx_uint64_t	hostmacroid;
	char		*macro;
	char		*value;
}
zbx_lld_hostmacro_t;

static void	lld_hostmacro_free(zbx_lld_hostmacro_t *hostmacro)
{
	zbx_free(hostmacro->macro);
	zbx_free(hostmacro->value);
	zbx_free(hostmacro);
}

typedef struct
{
	zbx_uint64_t	interfaceid;
	zbx_uint64_t	parent_interfaceid;
	char		*ip;
	char		*dns;
	char		*port;
	unsigned char	main;
	unsigned char	main_orig;
	unsigned char	type;
	unsigned char	type_orig;
	unsigned char	useip;
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE	0x01	/* interface.type field should be updated  */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN	0x02	/* interface.main field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP	0x04	/* interface.useip field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_IP	0x08	/* interface.ip field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS	0x10	/* interface.dns field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT	0x20	/* interface.port field should be updated */
#define ZBX_FLAG_LLD_INTERFACE_UPDATE								\
		(ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE | ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN |	\
		ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP | ZBX_FLAG_LLD_INTERFACE_UPDATE_IP |	\
		ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS | ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT)
#define ZBX_FLAG_LLD_INTERFACE_REMOVE		0x40	/* interfaces which should be deleted */
	unsigned char	flags;
}
zbx_lld_interface_t;

static void	lld_interface_free(zbx_lld_interface_t *interface)
{
	zbx_free(interface->port);
	zbx_free(interface->dns);
	zbx_free(interface->ip);
	zbx_free(interface);
}

typedef struct
{
	zbx_uint64_t		hostid;
	zbx_vector_uint64_t	new_groupids;		/* host groups which should be added */
	zbx_vector_uint64_t	lnk_templateids;	/* templates which should be linked */
	zbx_vector_uint64_t	del_templateids;	/* templates which should be unlinked */
	zbx_vector_ptr_t	new_hostmacros;		/* host macros which should be added */
	zbx_vector_ptr_t	interfaces;
	char			*host_proto;
	char			*host;
	char			*host_orig;
	char			*name;
	char			*name_orig;
	int			lastcheck;
	int			ts_delete;
#define ZBX_FLAG_LLD_HOST_DISCOVERED		0x01	/* hosts which should be updated or added */
#define ZBX_FLAG_LLD_HOST_UPDATE_HOST		0x02	/* hosts.host and host_discovery.host fields should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_NAME		0x04	/* hosts.name field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_PROXY		0x08	/* hosts.proxy_hostid field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH	0x10	/* hosts.ipmi_authtype field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV	0x20	/* hosts.ipmi_privilege field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER	0x40	/* hosts.ipmi_username field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS	0x80	/* hosts.ipmi_password field should be updated */
#define ZBX_FLAG_LLD_HOST_UPDATE								\
		(ZBX_FLAG_LLD_HOST_UPDATE_HOST | ZBX_FLAG_LLD_HOST_UPDATE_NAME |		\
		ZBX_FLAG_LLD_HOST_UPDATE_PROXY | ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH |		\
		ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV | ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER |	\
		ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS)
	unsigned char		flags;
	char			inventory_mode;
}
zbx_lld_host_t;

static void	lld_host_free(zbx_lld_host_t *host)
{
	zbx_vector_uint64_destroy(&host->new_groupids);
	zbx_vector_uint64_destroy(&host->lnk_templateids);
	zbx_vector_uint64_destroy(&host->del_templateids);
	zbx_vector_ptr_clean(&host->new_hostmacros, (zbx_mem_free_func_t)lld_hostmacro_free);
	zbx_vector_ptr_destroy(&host->new_hostmacros);
	zbx_vector_ptr_clean(&host->interfaces, (zbx_mem_free_func_t)lld_interface_free);
	zbx_vector_ptr_destroy(&host->interfaces);
	zbx_free(host->host_proto);
	zbx_free(host->host);
	zbx_free(host->host_orig);
	zbx_free(host->name);
	zbx_free(host->name_orig);
	zbx_free(host);
}

typedef struct
{
	zbx_uint64_t	group_prototypeid;
	char		*name;
}
zbx_lld_group_prototype_t;

static void	lld_group_prototype_free(zbx_lld_group_prototype_t *group_prototype)
{
	zbx_free(group_prototype->name);
	zbx_free(group_prototype);
}

typedef struct
{
	zbx_uint64_t		groupid;
	zbx_uint64_t		group_prototypeid;
	zbx_vector_ptr_t	hosts;
	char			*name_proto;
	char			*name;
	char			*name_orig;
	int			lastcheck;
	int			ts_delete;
#define ZBX_FLAG_LLD_GROUP_DISCOVERED		0x01	/* groups which should be updated or added */
#define ZBX_FLAG_LLD_GROUP_UPDATE_NAME		0x02	/* groups.name field should be updated */
#define ZBX_FLAG_LLD_GROUP_UPDATE		ZBX_FLAG_LLD_GROUP_UPDATE_NAME
	unsigned char		flags;
}
zbx_lld_group_t;

static void	lld_group_free(zbx_lld_group_t *group)
{
	/* zbx_vector_ptr_clean(&group->hosts, (zbx_mem_free_func_t)lld_host_free); is not missing here */
	zbx_vector_ptr_destroy(&group->hosts);
	zbx_free(group->name_proto);
	zbx_free(group->name);
	zbx_free(group->name_orig);
	zbx_free(group);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_hosts_get                                                    *
 *                                                                            *
 * Purpose: retrieves existing hosts for the specified host prototype         *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype identificator              *
 *             hosts         - [OUT] list of hosts                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_get(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts, zbx_uint64_t proxy_hostid,
		char ipmi_authtype, unsigned char ipmi_privilege, const char *ipmi_username, const char *ipmi_password,
		char inventory_mode)
{
	const char	*__function_name = "lld_hosts_get";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_lld_host_t	*host;
	zbx_uint64_t	db_proxy_hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select hd.hostid,hd.host,hd.lastcheck,hd.ts_delete,h.host,h.name,h.proxy_hostid,"
				"h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password,hi.inventory_mode"
			" from host_discovery hd"
				" join hosts h"
					" on hd.hostid=h.hostid"
				" left join host_inventory hi"
					" on hd.hostid=hi.hostid"
			" where hd.parent_hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		host = zbx_malloc(NULL, sizeof(zbx_lld_host_t));

		ZBX_STR2UINT64(host->hostid, row[0]);
		host->host_proto = zbx_strdup(NULL, row[1]);
		host->lastcheck = atoi(row[2]);
		host->ts_delete = atoi(row[3]);
		host->host = zbx_strdup(NULL, row[4]);
		host->host_orig = NULL;
		host->name = zbx_strdup(NULL, row[5]);
		host->name_orig = NULL;
		host->flags = 0x00;

		ZBX_DBROW2UINT64(db_proxy_hostid, row[6]);
		if (db_proxy_hostid != proxy_hostid)
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_PROXY;

		if ((char)atoi(row[7]) != ipmi_authtype)
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH;

		if ((unsigned char)atoi(row[8]) != ipmi_privilege)
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV;

		if (0 != strcmp(row[9], ipmi_username))
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER;

		if (0 != strcmp(row[10], ipmi_password))
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS;

		if (SUCCEED == DBis_null(row[11]))
			host->inventory_mode = HOST_INVENTORY_DISABLED;
		else
			host->inventory_mode = (char)atoi(row[11]);

		zbx_vector_uint64_create(&host->new_groupids);
		zbx_vector_uint64_create(&host->lnk_templateids);
		zbx_vector_uint64_create(&host->del_templateids);
		zbx_vector_ptr_create(&host->new_hostmacros);
		zbx_vector_ptr_create(&host->interfaces);

		zbx_vector_ptr_append(hosts, host);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_hosts_validate                                               *
 *                                                                            *
 * Parameters: hosts - [IN] list of hosts; should be sorted by hostid         *
 *                                                                            *
 ******************************************************************************/
void	lld_hosts_validate(zbx_vector_ptr_t *hosts, char **error)
{
	const char		*__function_name = "lld_hosts_validate";

	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_lld_host_t		*host, *host_b;
	zbx_vector_uint64_t	hostids;
	zbx_vector_str_t	tnames, vnames;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&hostids);
	zbx_vector_str_create(&tnames);		/* list of technical host names */
	zbx_vector_str_create(&vnames);		/* list of visible host names */

	/* checking a host name validity */
	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with a new host name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			continue;

		/* host name is valid? */
		if (SUCCEED == zbx_check_hostname(host->host))
			continue;

		*error = zbx_strdcatf(*error, "Cannot %s host: invalid host name \"%s\".\n",
				(0 != host->hostid ? "update" : "create"), host->host);

		if (0 != host->hostid)
		{
			/* return an original host name and drop the correspond flag */
			zbx_free(host->host);
			host->host = host->host_orig;
			host->host_orig = NULL;
			host->flags &= ~ZBX_FLAG_LLD_HOST_UPDATE_HOST;
		}
		else
			host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
	}

	/* checking a visible host name validity */
	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with a new visible host name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
			continue;

		/* visible host name is valid utf8 sequence and has a valid length */
		if (SUCCEED == zbx_is_utf8(host->name) && '\0' != *host->name &&
				HOST_NAME_LEN >= zbx_strlen_utf8(host->name))
		{
			continue;
		}

		zbx_replace_invalid_utf8(host->name);
		*error = zbx_strdcatf(*error, "Cannot %s host: invalid visible host name \"%s\".\n",
				(0 != host->hostid ? "update" : "create"), host->name);

		if (0 != host->hostid)
		{
			/* return an original visible host name and drop the correspond flag */
			zbx_free(host->name);
			host->name = host->name_orig;
			host->name_orig = NULL;
			host->flags &= ~ZBX_FLAG_LLD_HOST_UPDATE_NAME;
		}
		else
			host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
	}

	/* checking duplicated host names */
	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with a new host name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			continue;

		for (j = 0; j < hosts->values_num; j++)
		{
			host_b = (zbx_lld_host_t *)hosts->values[j];

			if (0 == (host_b->flags & ZBX_FLAG_LLD_HOST_DISCOVERED) || i == j)
				continue;

			if (0 != strcmp(host->host, host_b->host))
				continue;

			*error = zbx_strdcatf(*error, "Cannot %s host:"
						" host with the same name \"%s\" already exists.\n",
						(0 != host->hostid ? "update" : "create"), host->host);

			if (0 != host->hostid)
			{
				/* return an original host name and drop the correspond flag */
				zbx_free(host->host);
				host->host = host->host_orig;
				host->host_orig = NULL;
				host->flags &= ~ZBX_FLAG_LLD_HOST_UPDATE_HOST;
			}
			else
				host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
		}
	}

	/* checking duplicated visible host names */
	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		/* only new hosts or hosts with a new visible host name will be validated */
		if (0 != host->hostid && 0 == (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
			continue;

		for (j = 0; j < hosts->values_num; j++)
		{
			host_b = (zbx_lld_host_t *)hosts->values[j];

			if (0 == (host_b->flags & ZBX_FLAG_LLD_HOST_DISCOVERED) || i == j)
				continue;

			if (0 != strcmp(host->name, host_b->name))
				continue;

			*error = zbx_strdcatf(*error, "Cannot %s host:"
					" host with the same visible name \"%s\" already exists.\n",
					(0 != host->hostid ? "update" : "create"), host->name);

			if (0 != host->hostid)
			{
				/* return an original visible host name and drop the correspond flag */
				zbx_free(host->name);
				host->name = host->name_orig;
				host->name_orig = NULL;
				host->flags &= ~ZBX_FLAG_LLD_HOST_UPDATE_NAME;
			}
			else
				host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
		}
	}

	/* checking duplicated host names and visible host names in DB */

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);

		if (0 == host->hostid || 0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			zbx_vector_str_append(&tnames, host->host);

		if (0 == host->hostid || 0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
			zbx_vector_str_append(&vnames, host->name);
	}

	if (0 != tnames.values_num || 0 != vnames.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select host,name"
				" from hosts"
				" where status in (%d,%d,%d)"
					" and flags<>%d"
					" and",
				HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE,
				ZBX_FLAG_DISCOVERY_PROTOTYPE);

		if (0 != tnames.values_num && 0 != vnames.values_num)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " (");

		if (0 != tnames.values_num)
		{
			DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "host",
					(const char **)tnames.values, tnames.values_num);
		}

		if (0 != tnames.values_num && 0 != vnames.values_num)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " or");

		if (0 != vnames.values_num)
		{
			DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "name",
					(const char **)vnames.values, vnames.values_num);
		}

		if (0 != tnames.values_num && 0 != vnames.values_num)
			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');

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
				host = (zbx_lld_host_t *)hosts->values[i];

				if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
					continue;

				if (0 == strcmp(host->host, row[0]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s host:"
							" host with the same name \"%s\" already exists.\n",
							(0 != host->hostid ? "update" : "create"), host->host);

					if (0 != host->hostid)
					{
						/* return an original host name and drop the correspond flag */
						zbx_free(host->host);
						host->host = host->host_orig;
						host->host_orig = NULL;
						host->flags &= ~ZBX_FLAG_LLD_HOST_UPDATE_HOST;
					}
					else
						host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
				}

				if (0 == strcmp(host->name, row[1]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s host:"
							" host with the same visible name \"%s\" already exists.\n",
							(0 != host->hostid ? "update" : "create"), host->name);

					if (0 != host->hostid)
					{
						/* return an original visible host name and drop the correspond flag */
						zbx_free(host->name);
						host->name = host->name_orig;
						host->name_orig = NULL;
						host->flags &= ~ZBX_FLAG_LLD_HOST_UPDATE_NAME;
					}
					else
						host->flags &= ~ZBX_FLAG_LLD_HOST_DISCOVERED;
				}
			}
		}
		DBfree_result(result);

		zbx_free(sql);
	}

	zbx_vector_str_destroy(&vnames);
	zbx_vector_str_destroy(&tnames);
	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static zbx_lld_host_t	*lld_host_make(zbx_vector_ptr_t *hosts, const char *host_proto, const char *name_proto,
		struct zbx_json_parse *jp_row)
{
	const char	*__function_name = "lld_host_make";

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
		zbx_lrtrim(buffer, ZBX_WHITESPACE);

		if (0 == strcmp(host->host, buffer))
			break;
	}

	if (i == hosts->values_num)	/* no host found */
	{
		host = zbx_malloc(NULL, sizeof(zbx_lld_host_t));

		host->hostid = 0;
		host->host_proto = NULL;
		host->lastcheck = 0;
		host->ts_delete = 0;
		host->host = zbx_strdup(NULL, host_proto);
		host->host_orig = NULL;
		substitute_discovery_macros(&host->host, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(host->host, ZBX_WHITESPACE);
		host->name = zbx_strdup(NULL, name_proto);
		substitute_discovery_macros(&host->name, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(host->name, ZBX_WHITESPACE);
		host->name_orig = NULL;
		zbx_vector_uint64_create(&host->new_groupids);
		zbx_vector_uint64_create(&host->lnk_templateids);
		zbx_vector_uint64_create(&host->del_templateids);
		zbx_vector_ptr_create(&host->new_hostmacros);
		zbx_vector_ptr_create(&host->interfaces);
		host->flags = ZBX_FLAG_LLD_HOST_DISCOVERED;

		zbx_vector_ptr_append(hosts, host);
	}
	else
	{
		/* host technical name */
		if (0 != strcmp(host->host_proto, host_proto))	/* the new host prototype differs */
		{
			host->host_orig = host->host;
			host->host = zbx_strdup(NULL, host_proto);
			substitute_discovery_macros(&host->host, jp_row, ZBX_MACRO_ANY, NULL, 0);
			zbx_lrtrim(host->host, ZBX_WHITESPACE);
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_HOST;
		}

		/* host visible name */
		buffer = zbx_strdup(buffer, name_proto);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(host->name, buffer))
		{
			host->name_orig = host->name;
			host->name = buffer;
			buffer = NULL;
			host->flags |= ZBX_FLAG_LLD_HOST_UPDATE_NAME;
		}

		host->flags |= ZBX_FLAG_LLD_HOST_DISCOVERED;
	}

	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, host);

	return host;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_simple_groups_get                                            *
 *                                                                            *
 * Purpose: retrieve list of host groups which should be present on the each  *
 *          discovered host                                                   *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype identificator              *
 *             groupids      - [OUT] sorted list of host groups               *
 *                                                                            *
 ******************************************************************************/
static void	lld_simple_groups_get(zbx_uint64_t parent_hostid, zbx_vector_uint64_t *groupids)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	groupid;

	result = DBselect(
			"select groupid"
			" from group_prototype"
			" where groupid is not null"
				" and hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);
		zbx_vector_uint64_append(groupids, groupid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_hostgroups_make                                              *
 *                                                                            *
 * Parameters: groupids         - [IN] sorted list of host groups which       *
 *                                     should be present on the each          *
 *                                     discovered host                        *
 *             hosts            - [IN/OUT] list of hosts                      *
 *                                         should be sorted by hostid         *
 *             del_hostgroupids - [OUT] list of host groups which should be   *
 *                                      deleted                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_hostgroups_make(const zbx_vector_uint64_t *groupids, zbx_vector_ptr_t *hosts,
		zbx_vector_ptr_t *groups, zbx_vector_uint64_t *del_hostgroupids)
{
	const char		*__function_name = "lld_hostgroups_make";

	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostgroupid, hostid, groupid;
	zbx_lld_host_t		*host;
	zbx_lld_group_t		*group;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_uint64_reserve(&host->new_groupids, groupids->values_num);
		for (j = 0; j < groupids->values_num; j++)
			zbx_vector_uint64_append(&host->new_groupids, groupids->values[j]);

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED) || 0 == group->groupid)
			continue;

		for (j = 0; j < group->hosts.values_num; j++)
		{
			host = (zbx_lld_host_t *)group->hosts.values[j];

			zbx_vector_uint64_append(&host->new_groupids, group->groupid);
		}
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostid,groupid,hostgroupid"
				" from hosts_groups"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

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

			if (FAIL == (i = zbx_vector_uint64_bsearch(&host->new_groupids, groupid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				/* host groups which should be unlinked */
				ZBX_STR2UINT64(hostgroupid, row[2]);
				zbx_vector_uint64_append(del_hostgroupids, hostgroupid);
			}
			else
			{
				/* host groups which are already added */
				zbx_vector_uint64_remove(&host->new_groupids, i);
			}
		}
		DBfree_result(result);

		zbx_vector_uint64_sort(del_hostgroupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_group_prototypes_get                                         *
 *                                                                            *
 * Purpose: retrieve list of group prototypes                                 *
 *                                                                            *
 * Parameters: parent_hostid    - [IN] host prototype identificator           *
 *             group_prototypes - [OUT] sorted list of group prototypes       *
 *                                                                            *
 ******************************************************************************/
static void	lld_group_prototypes_get(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *group_prototypes)
{
	const char			*__function_name = "lld_group_prototypes_get";

	DB_RESULT			result;
	DB_ROW				row;
	zbx_lld_group_prototype_t	*group_prototype;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select group_prototypeid,name"
			" from group_prototype"
			" where groupid is null"
				" and hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		group_prototype = zbx_malloc(NULL, sizeof(zbx_lld_group_prototype_t));

		ZBX_STR2UINT64(group_prototype->group_prototypeid, row[0]);
		group_prototype->name = zbx_strdup(NULL, row[1]);

		zbx_vector_ptr_append(group_prototypes, group_prototype);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(group_prototypes, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_groups_get                                                   *
 *                                                                            *
 * Purpose: retrieves existing groups for the specified host prototype        *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype identificator              *
 *             groups        - [OUT] list of groups                           *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_get(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *groups)
{
	const char	*__function_name = "lld_groups_get";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_lld_group_t	*group;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select gd.groupid,gp.group_prototypeid,gd.name,gd.lastcheck,gd.ts_delete,g.name"
			" from group_prototype gp,group_discovery gd"
				" join groups g"
					" on gd.groupid=g.groupid"
			" where gp.group_prototypeid=gd.parent_group_prototypeid"
				" and gp.hostid=" ZBX_FS_UI64,
			parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		group = zbx_malloc(NULL, sizeof(zbx_lld_group_t));

		ZBX_STR2UINT64(group->groupid, row[0]);
		ZBX_STR2UINT64(group->group_prototypeid, row[1]);
		zbx_vector_ptr_create(&group->hosts);
		group->name_proto = zbx_strdup(NULL, row[2]);
		group->lastcheck = atoi(row[3]);
		group->ts_delete = atoi(row[4]);
		group->name = zbx_strdup(NULL, row[5]);
		group->name_orig = NULL;
		group->flags = 0x00;

		zbx_vector_ptr_append(groups, group);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(groups, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_group_make                                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_lld_group_t	*lld_group_make(zbx_vector_ptr_t *groups, zbx_uint64_t group_prototypeid,
		const char *name_proto, struct zbx_json_parse *jp_row)
{
	const char	*__function_name = "lld_group_make";

	char		*buffer = NULL;
	int		i;
	zbx_lld_group_t	*group;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (group->group_prototypeid != group_prototypeid)
			continue;

		if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		buffer = zbx_strdup(buffer, group->name_proto);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);

		if (0 == strcmp(group->name, buffer))
			break;
	}

	if (i == groups->values_num)	/* no group found */
	{
		/* trying to find an already existing group */

		buffer = zbx_strdup(buffer, name_proto);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);

		for (i = 0; i < groups->values_num; i++)
		{
			group = (zbx_lld_group_t *)groups->values[i];

			if (group->group_prototypeid != group_prototypeid)
				continue;

			if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
				continue;

			if (0 == strcmp(group->name, buffer))
				return group;
		}

		/* otherwise create a new group */

		group = zbx_malloc(NULL, sizeof(zbx_lld_group_t));

		group->groupid = 0;
		group->group_prototypeid = group_prototypeid;
		zbx_vector_ptr_create(&group->hosts);
		group->name_proto = NULL;
		group->name = zbx_strdup(NULL, name_proto);
		substitute_discovery_macros(&group->name, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(group->name, ZBX_WHITESPACE);
		group->name_orig = NULL;
		group->lastcheck = 0;
		group->ts_delete = 0;
		group->flags = 0x00;
		group->flags = ZBX_FLAG_LLD_GROUP_DISCOVERED;

		zbx_vector_ptr_append(groups, group);
	}
	else
	{
		/* update an already existing group */

		/* group name */
		buffer = zbx_strdup(buffer, name_proto);
		substitute_discovery_macros(&buffer, jp_row, ZBX_MACRO_ANY, NULL, 0);
		zbx_lrtrim(buffer, ZBX_WHITESPACE);
		if (0 != strcmp(group->name, buffer))
		{
			group->name_orig = group->name;
			group->name = buffer;
			buffer = NULL;
			group->flags |= ZBX_FLAG_LLD_GROUP_UPDATE_NAME;
		}

		group->flags |= ZBX_FLAG_LLD_GROUP_DISCOVERED;
	}

	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, group);

	return group;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_groups_make                                                  *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_make(zbx_lld_host_t *host, zbx_vector_ptr_t *groups, zbx_vector_ptr_t *group_prototypes,
		struct zbx_json_parse *jp_row)
{
	const char	*__function_name = "lld_groups_make";

	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < group_prototypes->values_num; i++)
	{
		zbx_lld_group_prototype_t	*group_prototype;
		zbx_lld_group_t			*group;

		group_prototype = (zbx_lld_group_prototype_t *)group_prototypes->values[i];

		group = lld_group_make(groups, group_prototype->group_prototypeid, group_prototype->name, jp_row);

		zbx_vector_ptr_append(&group->hosts, host);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_groups_validate                                              *
 *                                                                            *
 * Parameters: groups - [IN] list of groups; should be sorted by groupid      *
 *                                                                            *
 ******************************************************************************/
void	lld_groups_validate(zbx_vector_ptr_t *groups, char **error)
{
	const char		*__function_name = "lld_groups_validate";

	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_lld_group_t		*group, *group_b;
	zbx_vector_uint64_t	groupids;
	zbx_vector_str_t	names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&groupids);
	zbx_vector_str_create(&names);		/* list of group names */

	/* checking a group name validity */
	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		/* only new groups or groups with a new group name will be validated */
		if (0 != group->groupid && 0 == (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
			continue;

		/* group name is valid utf8 sequence and has a valid length */
		if (SUCCEED == zbx_is_utf8(group->name) && '\0' != *group->name &&
				GROUP_NAME_LEN >= zbx_strlen_utf8(group->name))
		{
			continue;
		}

		zbx_replace_invalid_utf8(group->name);
		*error = zbx_strdcatf(*error, "Cannot %s group: invalid group name \"%s\".\n",
				(0 != group->groupid ? "update" : "create"), group->name);

		if (0 != group->groupid)
		{
			/* return an original group name and drop the correspond flag */
			zbx_free(group->name);
			group->name = group->name_orig;
			group->name_orig = NULL;
			group->flags &= ~ZBX_FLAG_LLD_GROUP_UPDATE_NAME;
		}
		else
			group->flags &= ~ZBX_FLAG_LLD_GROUP_DISCOVERED;
	}

	/* checking duplicated group names */
	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		/* only new groups or groups with a new group name will be validated */
		if (0 != group->groupid && 0 == (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
			continue;

		for (j = 0; j < groups->values_num; j++)
		{
			group_b = (zbx_lld_group_t *)groups->values[j];

			if (0 == (group_b->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED) || i == j)
				continue;

			if (0 != strcmp(group->name, group_b->name))
				continue;

			*error = zbx_strdcatf(*error, "Cannot %s group:"
					" group with the same name \"%s\" already exists.\n",
					(0 != group->groupid ? "update" : "create"), group->name);

			if (0 != group->groupid)
			{
				/* return an original group name and drop the correspond flag */
				zbx_free(group->name);
				group->name = group->name_orig;
				group->name_orig = NULL;
				group->flags &= ~ZBX_FLAG_LLD_GROUP_UPDATE_NAME;
			}
			else
				group->flags &= ~ZBX_FLAG_LLD_GROUP_DISCOVERED;
		}
	}

	/* checking duplicated group names and group names in DB */

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		if (0 != group->groupid)
			zbx_vector_uint64_append(&groupids, group->groupid);

		if (0 == group->groupid || 0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
			zbx_vector_str_append(&names, group->name);
	}

	if (0 != names.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select name from groups where");
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "name",
				(const char **)names.values, names.values_num);

		if (0 != groupids.values_num)
		{
			zbx_vector_uint64_sort(&groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid",
					groupids.values, groupids.values_num);
		}

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			for (i = 0; i < groups->values_num; i++)
			{
				group = (zbx_lld_group_t *)groups->values[i];

				if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
					continue;

				if (0 == strcmp(group->name, row[0]))
				{
					*error = zbx_strdcatf(*error, "Cannot %s group:"
							" group with the same name \"%s\" already exists.\n",
							(0 != group->groupid ? "update" : "create"), group->name);

					if (0 != group->groupid)
					{
						/* return an original group name and drop the correspond flag */
						zbx_free(group->name);
						group->name = group->name_orig;
						group->name_orig = NULL;
						group->flags &= ~ZBX_FLAG_LLD_GROUP_UPDATE_NAME;
					}
					else
						group->flags &= ~ZBX_FLAG_LLD_GROUP_DISCOVERED;
				}
			}
		}
		DBfree_result(result);

		zbx_free(sql);
	}

	zbx_vector_str_destroy(&names);
	zbx_vector_uint64_destroy(&groupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_groups_save                                                  *
 *                                                                            *
 * Parameters: groups           - [IN/OUT] list of groups; should be sorted   *
 *                                         by groupid                         *
 *             group_prototypes - [IN] list of group prototypes; should be    *
 *                                     sorted by group_prototypeid            *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_save(zbx_vector_ptr_t *groups, zbx_vector_ptr_t *group_prototypes)
{
	const char			*__function_name = "lld_groups_save";

	int				i, j, new_groups = 0, upd_groups = 0;
	zbx_lld_group_t			*group;
	zbx_lld_group_prototype_t	*group_prototype;
	zbx_lld_host_t			*host;
	zbx_uint64_t			groupid = 0;
	char				*sql = NULL, *name_esc, *name_proto_esc;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_db_insert_t			db_insert, db_insert_gdiscovery;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		if (0 == group->groupid)
			new_groups++;
		else if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE))
			upd_groups++;
	}

	if (0 == new_groups && 0 == upd_groups)
		goto out;

	DBbegin();

	if (0 != new_groups)
	{
		groupid = DBget_maxid_num("groups", new_groups);

		zbx_db_insert_prepare(&db_insert, "groups", "groupid", "name", "flags", NULL);

		zbx_db_insert_prepare(&db_insert_gdiscovery, "group_discovery", "groupid", "parent_group_prototypeid",
				"name", NULL);
	}

	if (0 != upd_groups)
	{
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
			continue;

		if (0 == group->groupid)
		{
			group->groupid = groupid++;

			zbx_db_insert_add_values(&db_insert, group->groupid, group->name,
					(int)ZBX_FLAG_DISCOVERY_CREATED);

			if (FAIL != (j = zbx_vector_ptr_bsearch(group_prototypes, &group->group_prototypeid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				group_prototype = (zbx_lld_group_prototype_t *)group_prototypes->values[j];

				zbx_db_insert_add_values(&db_insert_gdiscovery, group->groupid,
						group->group_prototypeid, group_prototype->name);
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;

			for (j = 0; j < group->hosts.values_num; j++)
			{
				host = (zbx_lld_host_t *)group->hosts.values[j];

				/* hosts will be linked to a new host groups */
				zbx_vector_uint64_append(&host->new_groupids, group->groupid);
			}
		}
		else
		{
			if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE))
			{
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update groups set ");
				if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
				{
					name_esc = DBdyn_escape_string(group->name);

					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "name='%s'", name_esc);

					zbx_free(name_esc);
				}
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						" where groupid=" ZBX_FS_UI64 ";\n", group->groupid);
			}

			if (0 != (group->flags & ZBX_FLAG_LLD_GROUP_UPDATE_NAME))
			{
				if (FAIL != (j = zbx_vector_ptr_bsearch(group_prototypes, &group->group_prototypeid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					group_prototype = (zbx_lld_group_prototype_t *)group_prototypes->values[j];

					name_proto_esc = DBdyn_escape_string(group_prototype->name);

					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							"update group_discovery"
							" set name='%s'"
							" where groupid=" ZBX_FS_UI64 ";\n",
							name_proto_esc, group->groupid);

					zbx_free(name_proto_esc);
				}
				else
					THIS_SHOULD_NEVER_HAPPEN;
			}
		}
	}

	if (0 != upd_groups)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
		DBexecute("%s", sql);
		zbx_free(sql);
	}

	if (0 != new_groups)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_gdiscovery);
		zbx_db_insert_clean(&db_insert_gdiscovery);
	}

	DBcommit();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_hostmacros_get                                               *
 *                                                                            *
 * Purpose: retrieve list of host macros which should be present on the each  *
 *          discovered host                                                   *
 *                                                                            *
 * Parameters: hostmacros - [OUT] list of host macros                         *
 *                                                                            *
 ******************************************************************************/
static void	lld_hostmacros_get(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *hostmacros)
{
	const char		*__function_name = "lld_hostmacros_get";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_hostmacro_t	*hostmacro;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select hm.macro,hm.value"
			" from hostmacro hm,items i"
			" where hm.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		hostmacro = zbx_malloc(NULL, sizeof(zbx_lld_hostmacro_t));

		hostmacro->macro = zbx_strdup(NULL, row[0]);
		hostmacro->value = zbx_strdup(NULL, row[1]);

		zbx_vector_ptr_append(hostmacros, hostmacro);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_hostmacros_make                                              *
 *                                                                            *
 * Parameters: hostmacros       - [IN] list of host macros which              *
 *                                     should be present on the each          *
 *                                     discovered host                        *
 *             hosts            - [IN/OUT] list of hosts                      *
 *                                         should be sorted by hostid         *
 *             del_hostmacroids - [OUT] list of host macros which should be   *
 *                                      deleted                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_hostmacros_make(const zbx_vector_ptr_t *hostmacros, zbx_vector_ptr_t *hosts,
		zbx_vector_uint64_t *del_hostmacroids)
{
	const char		*__function_name = "lld_hostmacros_make";

	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		hostmacroid, hostid;
	zbx_lld_host_t		*host;
	zbx_lld_hostmacro_t	*hostmacro = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_ptr_reserve(&host->new_hostmacros, hostmacros->values_num);
		for (j = 0; j < hostmacros->values_num; j++)
		{
			hostmacro = zbx_malloc(NULL, sizeof(zbx_lld_hostmacro_t));

			hostmacro->hostmacroid = 0;
			hostmacro->macro = zbx_strdup(NULL, ((zbx_lld_hostmacro_t *)hostmacros->values[j])->macro);
			hostmacro->value = zbx_strdup(NULL, ((zbx_lld_hostmacro_t *)hostmacros->values[j])->value);

			zbx_vector_ptr_append(&host->new_hostmacros, hostmacro);
		}

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostmacroid,hostid,macro,value"
				" from hostmacro"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[1]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(hosts, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = (zbx_lld_host_t *)hosts->values[i];

			for (i = 0; i < host->new_hostmacros.values_num; i++)
			{
				hostmacro = (zbx_lld_hostmacro_t *)host->new_hostmacros.values[i];

				if (0 == strcmp(hostmacro->macro, row[2]))
					break;
			}

			if (i == host->new_hostmacros.values_num)
			{
				/* host macros which should be deleted */
				ZBX_STR2UINT64(hostmacroid, row[0]);
				zbx_vector_uint64_append(del_hostmacroids, hostmacroid);
			}
			else
			{
				/* host macros which are already added */
				if (0 == strcmp(hostmacro->value, row[3]))	/* value doesn't changed */
				{
					lld_hostmacro_free(hostmacro);
					zbx_vector_ptr_remove(&host->new_hostmacros, i);
				}
				else
					ZBX_STR2UINT64(hostmacro->hostmacroid, row[0]);
			}
		}
		DBfree_result(result);

		zbx_vector_uint64_sort(del_hostmacroids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_templates_make                                               *
 *                                                                            *
 * Purpose: gets templates from a host prototype                              *
 *                                                                            *
 * Parameters: parent_hostid - [IN] host prototype identificator              *
 *             hosts         - [IN/OUT] list of hosts                         *
 *                                      should be sorted by hostid            *
 *                                                                            *
 ******************************************************************************/
static void	lld_templates_make(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts)
{
	const char		*__function_name = "lld_templates_make";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	templateids, hostids;
	zbx_uint64_t		templateid, hostid;
	zbx_lld_host_t		*host;
	int			i, j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&templateids);
	zbx_vector_uint64_create(&hostids);

	/* select templates which should be linked */

	result = DBselect("select templateid from hosts_templates where hostid=" ZBX_FS_UI64, parent_hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(templateid, row[0]);
		zbx_vector_uint64_append(&templateids, templateid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* select list of already created hosts */

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_uint64_reserve(&host->lnk_templateids, templateids.values_num);
		for (j = 0; j < templateids.values_num; j++)
			zbx_vector_uint64_append(&host->lnk_templateids, templateids.values[j]);

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		/* select already linked temlates */

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostid,templateid"
				" from hosts_templates"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids.values, hostids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_STR2UINT64(templateid, row[1]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(hosts, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = (zbx_lld_host_t *)hosts->values[i];

			if (FAIL == (i = zbx_vector_uint64_bsearch(&host->lnk_templateids, templateid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				/* templates which should be unlinked */
				zbx_vector_uint64_append(&host->del_templateids, templateid);
			}
			else
			{
				/* templates which are already linked */
				zbx_vector_uint64_remove(&host->lnk_templateids, i);
			}
		}
		DBfree_result(result);

		for (i = 0; i < hosts->values_num; i++)
		{
			host = (zbx_lld_host_t *)hosts->values[i];

			if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
				continue;

			zbx_vector_uint64_sort(&host->del_templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		}
	}

	zbx_vector_uint64_destroy(&hostids);
	zbx_vector_uint64_destroy(&templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_hosts_save                                                   *
 *                                                                            *
 * Parameters: hosts            - [IN] list of hosts;                         *
 *                                     should be sorted by hostid             *
 *             status           - [IN] initial host satatus                   *
 *             del_hostgroupids - [IN] host groups which should be deleted    *
 *             del_hostmacroids - [IN] host macros which should be deleted    *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_save(zbx_uint64_t parent_hostid, zbx_vector_ptr_t *hosts, const char *host_proto,
		zbx_uint64_t proxy_hostid, char ipmi_authtype, unsigned char ipmi_privilege, const char *ipmi_username,
		const char *ipmi_password, unsigned char status, char inventory_mode,
		zbx_vector_uint64_t *del_hostgroupids, zbx_vector_uint64_t *del_hostmacroids)
{
	const char		*__function_name = "lld_hosts_save";

	int			i, j, new_hosts = 0, new_host_inventories = 0, upd_hosts = 0, new_hostgroups = 0,
				new_hostmacros = 0, upd_hostmacros = 0, new_interfaces = 0, upd_interfaces = 0;
	zbx_lld_host_t		*host;
	zbx_lld_hostmacro_t	*hostmacro;
	zbx_lld_interface_t	*interface;
	zbx_vector_uint64_t	upd_host_inventory_hostids, del_host_inventory_hostids, del_interfaceids;
	zbx_uint64_t		hostid = 0, hostgroupid = 0, hostmacroid = 0, interfaceid = 0;
	char			*sql1 = NULL, *sql2 = NULL, *host_esc, *name_esc, *host_proto_esc,
				*ipmi_username_esc, *ipmi_password_esc, *value_esc, *ip_esc, *dns_esc,
				*port_esc;
	size_t			sql1_alloc = 0, sql1_offset = 0,
				sql2_alloc = 0, sql2_offset = 0;
	zbx_db_insert_t		db_insert, db_insert_hdiscovery, db_insert_hinventory, db_insert_hgroups,
				db_insert_hmacro, db_insert_interface, db_insert_idiscovery;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&upd_host_inventory_hostids);
	zbx_vector_uint64_create(&del_host_inventory_hostids);
	zbx_vector_uint64_create(&del_interfaceids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 == host->hostid)
		{
			new_hosts++;
			if (HOST_INVENTORY_DISABLED != inventory_mode)
				new_host_inventories++;
		}
		else
		{
			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE))
				upd_hosts++;

			if (host->inventory_mode != inventory_mode)
			{
				if (HOST_INVENTORY_DISABLED == inventory_mode)
					zbx_vector_uint64_append(&del_host_inventory_hostids, host->hostid);
				else if (HOST_INVENTORY_DISABLED == host->inventory_mode)
					new_host_inventories++;
				else
					zbx_vector_uint64_append(&upd_host_inventory_hostids, host->hostid);
			}
		}

		new_hostgroups += host->new_groupids.values_num;

		for (j = 0; j < host->interfaces.values_num; j++)
		{
			interface = (zbx_lld_interface_t *)host->interfaces.values[j];

			if (0 == interface->interfaceid)
				new_interfaces++;
			else if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE))
				upd_interfaces++;
			else if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_REMOVE))
				zbx_vector_uint64_append(&del_interfaceids, interface->interfaceid);
		}

		for (j = 0; j < host->new_hostmacros.values_num; j++)
		{
			hostmacro = (zbx_lld_hostmacro_t *)host->new_hostmacros.values[j];

			if (0 == hostmacro->hostmacroid)
				new_hostmacros++;
			else
				upd_hostmacros++;
		}
	}

	if (0 == new_hosts && 0 == new_host_inventories && 0 == upd_hosts && 0 == upd_interfaces &&
			0 == upd_hostmacros && 0 == new_hostgroups && 0 == new_hostmacros && 0 == new_interfaces &&
			0 == del_hostgroupids->values_num && 0 == del_hostmacroids->values_num &&
			0 == upd_host_inventory_hostids.values_num && 0 == del_host_inventory_hostids.values_num &&
			0 == del_interfaceids.values_num)
	{
		goto out;
	}

	DBbegin();

	if (0 != new_hosts)
	{
		hostid = DBget_maxid_num("hosts", new_hosts);

		zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "host", "name", "proxy_hostid", "ipmi_authtype",
				"ipmi_privilege", "ipmi_username", "ipmi_password", "status", "flags", NULL);

		zbx_db_insert_prepare(&db_insert_hdiscovery, "host_discovery", "hostid", "parent_hostid", "host",
				NULL);
	}

	if (0 != new_host_inventories)
	{
		zbx_db_insert_prepare(&db_insert_hinventory, "host_inventory", "hostid", "inventory_mode", NULL);
	}

	if (0 != upd_hosts || 0 != upd_interfaces || 0 != upd_hostmacros)
	{
		DBbegin_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
	}

	if (0 != new_hostgroups)
	{
		hostgroupid = DBget_maxid_num("hosts_groups", new_hostgroups);

		zbx_db_insert_prepare(&db_insert_hgroups, "hosts_groups", "hostgroupid", "hostid", "groupid", NULL);
	}

	if (0 != new_hostmacros)
	{
		hostmacroid = DBget_maxid_num("hostmacro", new_hostmacros);

		zbx_db_insert_prepare(&db_insert_hmacro, "hostmacro", "hostmacroid", "hostid", "macro", "value", NULL);
	}

	if (0 != new_interfaces)
	{
		interfaceid = DBget_maxid_num("interface", new_interfaces);

		zbx_db_insert_prepare(&db_insert_interface, "interface", "interfaceid", "hostid", "type", "main",
				"useip", "ip", "dns", "port", NULL);

		zbx_db_insert_prepare(&db_insert_idiscovery, "interface_discovery", "interfaceid",
				"parent_interfaceid", NULL);
	}

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 == host->hostid)
		{
			host->hostid = hostid++;

			zbx_db_insert_add_values(&db_insert, host->hostid, host->host, host->name, proxy_hostid,
					(int)ipmi_authtype, (int)ipmi_privilege, ipmi_username, ipmi_password,
					(int)status, (int)ZBX_FLAG_DISCOVERY_CREATED);

			zbx_db_insert_add_values(&db_insert_hdiscovery, host->hostid, parent_hostid, host_proto);

			if (HOST_INVENTORY_DISABLED != inventory_mode)
				zbx_db_insert_add_values(&db_insert_hinventory, host->hostid, (int)inventory_mode);
		}
		else
		{
			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update hosts set ");
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
				{
					host_esc = DBdyn_escape_string(host->host);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "host='%s'", host_esc);
					d = ",";

					zbx_free(host_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_NAME))
				{
					name_esc = DBdyn_escape_string(host->name);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sname='%s'", d, name_esc);
					d = ",";

					zbx_free(name_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_PROXY))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sproxy_hostid=%s", d, DBsql_id_ins(proxy_hostid));
					d = ",";
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_AUTH))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_authtype=%d", d, (int)ipmi_authtype);
					d = ",";
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PRIV))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_privilege=%d", d, (int)ipmi_privilege);
					d = ",";
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_USER))
				{
					ipmi_username_esc = DBdyn_escape_string(ipmi_username);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_username='%s'", d, ipmi_username_esc);
					d = ",";

					zbx_free(ipmi_username_esc);
				}
				if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_IPMI_PASS))
				{
					ipmi_password_esc = DBdyn_escape_string(ipmi_password);

					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
							"%sipmi_password='%s'", d, ipmi_password_esc);

					zbx_free(ipmi_password_esc);
				}
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, " where hostid=" ZBX_FS_UI64 ";\n",
						host->hostid);
			}

			if (host->inventory_mode != inventory_mode && HOST_INVENTORY_DISABLED == host->inventory_mode)
				zbx_db_insert_add_values(&db_insert_hinventory, host->hostid, (int)inventory_mode);

			if (0 != (host->flags & ZBX_FLAG_LLD_HOST_UPDATE_HOST))
			{
				host_proto_esc = DBdyn_escape_string(host_proto);

				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						"update host_discovery"
						" set host='%s'"
						" where hostid=" ZBX_FS_UI64 ";\n",
						host_proto_esc, host->hostid);

				zbx_free(host_proto_esc);
			}
		}

		for (j = 0; j < host->interfaces.values_num; j++)
		{
			interface = (zbx_lld_interface_t *)host->interfaces.values[j];

			if (0 == interface->interfaceid)
			{
				interface->interfaceid = interfaceid++;

				zbx_db_insert_add_values(&db_insert_interface, interface->interfaceid, host->hostid,
						(int)interface->type, (int)interface->main, (int)interface->useip,
						interface->ip, interface->dns, interface->port);

				zbx_db_insert_add_values(&db_insert_idiscovery, interface->interfaceid,
						interface->parent_interfaceid);
			}
			else if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE))
			{
				const char	*d = "";

				zbx_strcpy_alloc(&sql1, &sql1_alloc, &sql1_offset, "update interface set ");
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "type=%d",
							(int)interface->type);
					d = ",";
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%smain=%d",
							d, (int)interface->main);
					d = ",";
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP))
				{
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%suseip=%d",
							d, (int)interface->useip);
					d = ",";
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_IP))
				{
					ip_esc = DBdyn_escape_string(interface->ip);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sip='%s'", d, ip_esc);
					zbx_free(ip_esc);
					d = ",";
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS))
				{
					dns_esc = DBdyn_escape_string(interface->dns);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sdns='%s'", d, dns_esc);
					zbx_free(dns_esc);
					d = ",";
				}
				if (0 != (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT))
				{
					port_esc = DBdyn_escape_string(interface->port);
					zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset, "%sport='%s'",
							d, port_esc);
					zbx_free(port_esc);
				}
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						" where interfaceid=" ZBX_FS_UI64 ";\n", interface->interfaceid);
			}
		}

		for (j = 0; j < host->new_groupids.values_num; j++)
		{
			zbx_db_insert_add_values(&db_insert_hgroups, hostgroupid++, host->hostid,
					host->new_groupids.values[j]);
		}

		for (j = 0; j < host->new_hostmacros.values_num; j++)
		{
			hostmacro = (zbx_lld_hostmacro_t *)host->new_hostmacros.values[j];

			value_esc = DBdyn_escape_string(hostmacro->value);

			if (0 == hostmacro->hostmacroid)
			{
				zbx_db_insert_add_values(&db_insert_hmacro, hostmacroid++, host->hostid,
						hostmacro->macro, hostmacro->value);
			}
			else
			{
				zbx_snprintf_alloc(&sql1, &sql1_alloc, &sql1_offset,
						"update hostmacro"
						" set value='%s'"
						" where hostmacroid=" ZBX_FS_UI64 ";\n",
						value_esc, hostmacro->hostmacroid);
			}

			zbx_free(value_esc);
		}
	}

	if (0 != new_hosts)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		zbx_db_insert_execute(&db_insert_hdiscovery);
		zbx_db_insert_clean(&db_insert_hdiscovery);
	}

	if (0 != new_host_inventories)
	{
		zbx_db_insert_execute(&db_insert_hinventory);
		zbx_db_insert_clean(&db_insert_hinventory);
	}

	if (0 != new_hostgroups)
	{
		zbx_db_insert_execute(&db_insert_hgroups);
		zbx_db_insert_clean(&db_insert_hgroups);
	}

	if (0 != new_hostmacros)
	{
		zbx_db_insert_execute(&db_insert_hmacro);
		zbx_db_insert_clean(&db_insert_hmacro);
	}

	if (0 != new_interfaces)
	{
		zbx_db_insert_execute(&db_insert_interface);
		zbx_db_insert_clean(&db_insert_interface);

		zbx_db_insert_execute(&db_insert_idiscovery);
		zbx_db_insert_clean(&db_insert_idiscovery);
	}

	if (0 != upd_hosts || 0 != upd_interfaces || 0 != upd_hostmacros)
	{
		DBend_multiple_update(&sql1, &sql1_alloc, &sql1_offset);
		DBexecute("%s", sql1);
		zbx_free(sql1);
	}

	if (0 != del_hostgroupids->values_num || 0 != del_hostmacroids->values_num ||
			0 != upd_host_inventory_hostids.values_num || 0 != del_host_inventory_hostids.values_num ||
			0 != del_interfaceids.values_num)
	{
		DBbegin_multiple_update(&sql2, &sql2_alloc, &sql2_offset);

		if (0 != del_hostgroupids->values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hosts_groups where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostgroupid",
					del_hostgroupids->values, del_hostgroupids->values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_hostmacroids->values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from hostmacro where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostmacroid",
					del_hostmacroids->values, del_hostmacroids->values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != upd_host_inventory_hostids.values_num)
		{
			zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset,
					"update host_inventory set inventory_mode=%d where", (int)inventory_mode);
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostid",
					upd_host_inventory_hostids.values, upd_host_inventory_hostids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_host_inventory_hostids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from host_inventory where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "hostid",
					del_host_inventory_hostids.values, del_host_inventory_hostids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		if (0 != del_interfaceids.values_num)
		{
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, "delete from interface where");
			DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "interfaceid",
					del_interfaceids.values, del_interfaceids.values_num);
			zbx_strcpy_alloc(&sql2, &sql2_alloc, &sql2_offset, ";\n");
		}

		DBend_multiple_update(&sql2, &sql2_alloc, &sql2_offset);
		DBexecute("%s", sql2);
		zbx_free(sql2);
	}

	DBcommit();
out:
	zbx_vector_uint64_destroy(&del_interfaceids);
	zbx_vector_uint64_destroy(&del_host_inventory_hostids);
	zbx_vector_uint64_destroy(&upd_host_inventory_hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_templates_link                                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_templates_link(const zbx_vector_ptr_t *hosts)
{
	const char	*__function_name = "lld_templates_link";

	int		i;
	zbx_lld_host_t	*host;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		if (0 != host->del_templateids.values_num)
			DBdelete_template_elements(host->hostid, &host->del_templateids);

		if (0 != host->lnk_templateids.values_num)
			DBcopy_template_elements(host->hostid, &host->lnk_templateids);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_hosts_remove                                                 *
 *                                                                            *
 * Purpose: updates host_discovery.lastcheck and host_discovery.ts_delete     *
 *          fields; removes lost resources                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_hosts_remove(zbx_vector_ptr_t *hosts, unsigned short lifetime, int lastcheck)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_lld_host_t		*host;
	zbx_vector_uint64_t	del_hostids, lc_hostids, ts_hostids;
	int			i, lifetime_sec;

	if (0 == hosts->values_num)
		return;

	lifetime_sec = lifetime * SEC_PER_DAY;

	zbx_vector_uint64_create(&del_hostids);
	zbx_vector_uint64_create(&lc_hostids);
	zbx_vector_uint64_create(&ts_hostids);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == host->hostid)
			continue;

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
		{
			if (host->lastcheck < lastcheck - lifetime_sec)
			{
				zbx_vector_uint64_append(&del_hostids, host->hostid);
			}
			else if (host->ts_delete != host->lastcheck + lifetime_sec)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update host_discovery"
						" set ts_delete=%d"
						" where hostid=" ZBX_FS_UI64 ";\n",
						host->lastcheck + lifetime_sec, host->hostid);
			}
		}
		else
		{
			zbx_vector_uint64_append(&lc_hostids, host->hostid);
			if (0 != host->ts_delete)
				zbx_vector_uint64_append(&ts_hostids, host->hostid);
		}
	}

	if (0 != lc_hostids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update host_discovery set lastcheck=%d where",
				lastcheck);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				lc_hostids.values, lc_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != ts_hostids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update host_discovery set ts_delete=0 where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid",
				ts_hostids.values, ts_hostids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		DBbegin();

		DBexecute("%s", sql);

		DBcommit();
	}

	zbx_free(sql);

	if (0 != del_hostids.values_num)
	{
		zbx_vector_uint64_sort(&del_hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DBbegin();

		DBdelete_hosts(&del_hostids);

		DBcommit();
	}

	zbx_vector_uint64_destroy(&ts_hostids);
	zbx_vector_uint64_destroy(&lc_hostids);
	zbx_vector_uint64_destroy(&del_hostids);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_groups_remove                                                *
 *                                                                            *
 * Purpose: updates group_discovery.lastcheck and group_discovery.ts_delete   *
 *          fields; removes lost resources                                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_groups_remove(zbx_vector_ptr_t *groups, unsigned short lifetime, int lastcheck)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_lld_group_t		*group;
	zbx_vector_uint64_t	del_groupids, lc_groupids, ts_groupids;
	int			i, lifetime_sec;

	if (0 == groups->values_num)
		return;

	lifetime_sec = lifetime * SEC_PER_DAY;

	zbx_vector_uint64_create(&del_groupids);
	zbx_vector_uint64_create(&lc_groupids);
	zbx_vector_uint64_create(&ts_groupids);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < groups->values_num; i++)
	{
		group = (zbx_lld_group_t *)groups->values[i];

		if (0 == group->groupid)
			continue;

		if (0 == (group->flags & ZBX_FLAG_LLD_GROUP_DISCOVERED))
		{
			if (group->lastcheck < lastcheck - lifetime_sec)
			{
				zbx_vector_uint64_append(&del_groupids, group->groupid);
			}
			else if (group->ts_delete != group->lastcheck + lifetime_sec)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"update group_discovery"
						" set ts_delete=%d"
						" where groupid=" ZBX_FS_UI64 ";\n",
						group->lastcheck + lifetime_sec, group->groupid);
			}
		}
		else
		{
			zbx_vector_uint64_append(&lc_groupids, group->groupid);
			if (0 != group->ts_delete)
				zbx_vector_uint64_append(&ts_groupids, group->groupid);
		}
	}

	if (0 != lc_groupids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update group_discovery set lastcheck=%d where",
				lastcheck);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid",
				lc_groupids.values, lc_groupids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (0 != ts_groupids.values_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update group_discovery set ts_delete=0 where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid",
				ts_groupids.values, ts_groupids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
	}

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		DBbegin();

		DBexecute("%s", sql);

		DBcommit();
	}

	zbx_free(sql);

	if (0 != del_groupids.values_num)
	{
		zbx_vector_uint64_sort(&del_groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DBbegin();

		DBdelete_groups(&del_groupids);

		DBcommit();
	}

	zbx_vector_uint64_destroy(&ts_groupids);
	zbx_vector_uint64_destroy(&lc_groupids);
	zbx_vector_uint64_destroy(&del_groupids);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_interfaces_get                                               *
 *                                                                            *
 * Purpose: retrieves list of interfaces from the lld rule's host             *
 *                                                                            *
 ******************************************************************************/
static void	lld_interfaces_get(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *interfaces)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_interface_t	*interface;

	result = DBselect(
			"select hi.interfaceid,hi.type,hi.main,hi.useip,hi.ip,hi.dns,hi.port"
			" from interface hi,items i"
			" where hi.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		interface = zbx_malloc(NULL, sizeof(zbx_lld_interface_t));

		ZBX_STR2UINT64(interface->interfaceid, row[0]);
		interface->type = (unsigned char)atoi(row[1]);
		interface->main = (unsigned char)atoi(row[2]);
		interface->useip = (unsigned char)atoi(row[3]);
		interface->ip = zbx_strdup(NULL, row[4]);
		interface->dns = zbx_strdup(NULL, row[5]);
		interface->port = zbx_strdup(NULL, row[6]);

		zbx_vector_ptr_append(interfaces, interface);
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_interface_make                                               *
 *                                                                            *
 ******************************************************************************/
static void	lld_interface_make(zbx_vector_ptr_t *interfaces, zbx_uint64_t parent_interfaceid,
		zbx_uint64_t interfaceid, unsigned char type, unsigned char main, unsigned char useip, const char *ip,
		const char *dns, const char *port)
{
	zbx_lld_interface_t	*interface;
	int			i;

	for (i = 0; i < interfaces->values_num; i++)
	{
		interface = (zbx_lld_interface_t *)interfaces->values[i];

		if (0 != interface->interfaceid)
			continue;

		if (interface->parent_interfaceid == parent_interfaceid)
			break;
	}

	if (i == interfaces->values_num)
	{
		/* interface which should be deleted */
		interface = zbx_malloc(NULL, sizeof(zbx_lld_interface_t));

		interface->interfaceid = interfaceid;
		interface->parent_interfaceid = 0;
		interface->type = type;
		interface->main = main;
		interface->useip = 0;
		interface->ip = NULL;
		interface->dns = NULL;
		interface->port = NULL;
		interface->flags = ZBX_FLAG_LLD_INTERFACE_REMOVE;

		zbx_vector_ptr_append(interfaces, interface);
	}
	else
	{
		/* interface which are already added */
		if (interface->type != type)
		{
			interface->type_orig = type;
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE;
		}
		if (interface->main != main)
		{
			interface->main_orig = main;
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN;
		}
		if (interface->useip != useip)
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_USEIP;
		if (0 != strcmp(interface->ip, ip))
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_IP;
		if (0 != strcmp(interface->dns, dns))
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_DNS;
		if (0 != strcmp(interface->port, port))
			interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_PORT;
	}

	interface->interfaceid = interfaceid;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_interfaces_make                                              *
 *                                                                            *
 * Parameters: interfaces - [IN] sorted list of interfaces which              *
 *                               should be present on the each                *
 *                               discovered host                              *
 *             hosts      - [IN/OUT] sorted list of hosts                     *
 *                                                                            *
 ******************************************************************************/
static void	lld_interfaces_make(const zbx_vector_ptr_t *interfaces, zbx_vector_ptr_t *hosts)
{
	const char		*__function_name = "lld_interfaces_make";

	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	hostids;
	zbx_uint64_t		parent_interfaceid, hostid, interfaceid;
	zbx_lld_host_t		*host;
	zbx_lld_interface_t	*new_interface, *interface;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		if (0 == (host->flags & ZBX_FLAG_LLD_HOST_DISCOVERED))
			continue;

		zbx_vector_ptr_reserve(&host->interfaces, interfaces->values_num);
		for (j = 0; j < interfaces->values_num; j++)
		{
			interface = (zbx_lld_interface_t *)interfaces->values[j];

			new_interface = zbx_malloc(NULL, sizeof(zbx_lld_interface_t));

			new_interface->interfaceid = 0;
			new_interface->parent_interfaceid = interface->interfaceid;
			new_interface->type = interface->type;
			new_interface->main = interface->main;
			new_interface->useip = interface->useip;
			new_interface->ip = zbx_strdup(NULL, interface->ip);
			new_interface->dns = zbx_strdup(NULL, interface->dns);
			new_interface->port = zbx_strdup(NULL, interface->port);
			new_interface->flags = 0x00;

			zbx_vector_ptr_append(&host->interfaces, new_interface);
		}

		if (0 != host->hostid)
			zbx_vector_uint64_append(&hostids, host->hostid);
	}

	if (0 != hostids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 0, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
				"select hi.hostid,id.parent_interfaceid,hi.interfaceid,hi.type,hi.main,hi.useip,hi.ip,"
					"hi.dns,hi.port"
				" from interface hi"
					" left join interface_discovery id"
						" on hi.interfaceid=id.interfaceid"
				" where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hi.hostid", hostids.values, hostids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(hostid, row[0]);
			ZBX_DBROW2UINT64(parent_interfaceid, row[1]);
			ZBX_DBROW2UINT64(interfaceid, row[2]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(hosts, &hostid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			host = (zbx_lld_host_t *)hosts->values[i];

			lld_interface_make(&host->interfaces, parent_interfaceid, interfaceid,
					(unsigned char)atoi(row[3]), (unsigned char)atoi(row[4]),
					(unsigned char)atoi(row[5]), row[6], row[7], row[8]);
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&hostids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: another_main_interface_exists                                    *
 *                                                                            *
 * Return value: SUCCEED if interface with same type exists in the list of    *
 *               interfaces; FAIL - otherwise                                 *
 *                                                                            *
 * Comments: interfaces with ZBX_FLAG_LLD_INTERFACE_REMOVE flag are ignored   *
 *           auxiliary function for lld_interfaces_validate()                 *
 *                                                                            *
 ******************************************************************************/
static int	another_main_interface_exists(const zbx_vector_ptr_t *interfaces, const zbx_lld_interface_t *interface)
{
	zbx_lld_interface_t	*interface_b;
	int			i;

	for (i = 0; i < interfaces->values_num; i++)
	{
		interface_b = (zbx_lld_interface_t *)interfaces->values[i];

		if (interface_b == interface)
			continue;

		if (0 != (interface_b->flags & ZBX_FLAG_LLD_INTERFACE_REMOVE))
			continue;

		if (interface_b->type != interface->type)
			continue;

		if (1 == interface_b->main)
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_interfaces_validate                                          *
 *                                                                            *
 * Parameters: hosts - [IN/OUT] list of hosts                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_interfaces_validate(zbx_vector_ptr_t *hosts, char **error)
{
	const char		*__function_name = "lld_interfaces_validate";

	DB_RESULT		result;
	DB_ROW			row;
	int			i, j;
	zbx_vector_uint64_t	interfaceids;
	zbx_uint64_t		interfaceid;
	zbx_lld_host_t		*host;
	zbx_lld_interface_t	*interface;
	unsigned char		type;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* validate changed types */

	zbx_vector_uint64_create(&interfaceids);

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		for (j = 0; j < host->interfaces.values_num; j++)
		{
			interface = (zbx_lld_interface_t *)host->interfaces.values[j];

			if (0 == (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE))
				continue;

			zbx_vector_uint64_append(&interfaceids, interface->interfaceid);
		}
	}

	if (0 != interfaceids.values_num)
	{
		zbx_vector_uint64_sort(&interfaceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select interfaceid,type from items where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "interfaceid",
				interfaceids.values, interfaceids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " group by interfaceid,type");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			type = get_interface_type_by_item_type((unsigned char)atoi(row[1]));

			if (type != INTERFACE_TYPE_ANY && type != INTERFACE_TYPE_UNKNOWN)
			{
				ZBX_STR2UINT64(interfaceid, row[0]);

				for (i = 0; i < hosts->values_num; i++)
				{
					host = (zbx_lld_host_t *)hosts->values[i];

					for (j = 0; j < host->interfaces.values_num; j++)
					{
						interface = (zbx_lld_interface_t *)host->interfaces.values[j];

						if (0 == (interface->flags & ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE))
							continue;

						if (interface->interfaceid != interfaceid)
							continue;

						*error = zbx_strdcatf(*error,
								"Cannot update \"%s\" interface on host \"%s\":"
								" the interface is used by items.\n",
								zbx_interface_type_string(interface->type_orig),
								host->host);

						/* return an original interface type and drop the correspond flag */
						interface->type = interface->type_orig;
						interface->flags &= ~ZBX_FLAG_LLD_INTERFACE_UPDATE_TYPE;
					}
				}
			}
		}
		DBfree_result(result);
	}

	/* validate interfaces which should be deleted */

	interfaceids.values_num = 0;

	for (i = 0; i < hosts->values_num; i++)
	{
		host = (zbx_lld_host_t *)hosts->values[i];

		for (j = 0; j < host->interfaces.values_num; j++)
		{
			interface = (zbx_lld_interface_t *)host->interfaces.values[j];

			if (0 == (interface->flags & ZBX_FLAG_LLD_INTERFACE_REMOVE))
				continue;

			zbx_vector_uint64_append(&interfaceids, interface->interfaceid);
		}
	}

	if (0 != interfaceids.values_num)
	{
		zbx_vector_uint64_sort(&interfaceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select interfaceid from items where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "interfaceid",
				interfaceids.values, interfaceids.values_num);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " group by interfaceid");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(interfaceid, row[0]);

			for (i = 0; i < hosts->values_num; i++)
			{
				host = (zbx_lld_host_t *)hosts->values[i];

				for (j = 0; j < host->interfaces.values_num; j++)
				{
					interface = (zbx_lld_interface_t *)host->interfaces.values[j];

					if (0 == (interface->flags & ZBX_FLAG_LLD_INTERFACE_REMOVE))
						continue;

					if (interface->interfaceid != interfaceid)
						continue;

					*error = zbx_strdcatf(*error, "Cannot delete \"%s\" interface on host \"%s\":"
							" the interface is used by items.\n",
							zbx_interface_type_string(interface->type), host->host);

					/* drop the correspond flag */
					interface->flags &= ~ZBX_FLAG_LLD_INTERFACE_REMOVE;

					if (SUCCEED == another_main_interface_exists(&host->interfaces, interface))
					{
						if (1 == interface->main)
						{
							/* drop main flag */
							interface->main_orig = interface->main;
							interface->main = 0;
							interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN;
						}
					}
					else if (1 != interface->main)
					{
						/* set main flag */
						interface->main_orig = interface->main;
						interface->main = 1;
						interface->flags |= ZBX_FLAG_LLD_INTERFACE_UPDATE_MAIN;
					}
				}
			}
		}
		DBfree_result(result);
	}

	zbx_vector_uint64_destroy(&interfaceids);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_update_hosts                                                 *
 *                                                                            *
 * Purpose: add or update low-level discovered hosts                          *
 *                                                                            *
 ******************************************************************************/
void	lld_update_hosts(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *lld_rows, char **error, unsigned short lifetime,
		int lastcheck)
{
	const char		*__function_name = "lld_update_hosts";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_ptr_t	hosts, group_prototypes, groups, interfaces, hostmacros;
	zbx_vector_uint64_t	groupids;		/* list of host groups which should be added */
	zbx_vector_uint64_t	del_hostgroupids;	/* list of host groups which should be deleted */
	zbx_vector_uint64_t	del_hostmacroids;	/* list of host macros which should be deleted */
	zbx_uint64_t		proxy_hostid;
	char			*ipmi_username = NULL, *ipmi_password;
	char			ipmi_authtype, inventory_mode;
	unsigned char		ipmi_privilege;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select h.proxy_hostid,h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password"
			" from hosts h,items i"
			" where h.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64,
			lld_ruleid);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_DBROW2UINT64(proxy_hostid, row[0]);
		ipmi_authtype = (char)atoi(row[1]);
		ipmi_privilege = (unsigned char)atoi(row[2]);
		ipmi_username = zbx_strdup(NULL, row[3]);
		ipmi_password = zbx_strdup(NULL, row[4]);
	}
	DBfree_result(result);

	if (NULL == ipmi_username)
	{
		*error = zbx_strdcatf(*error, "Cannot process host prototypes: a parent host not found.\n");
		return;
	}

	zbx_vector_ptr_create(&hosts);
	zbx_vector_uint64_create(&groupids);
	zbx_vector_ptr_create(&group_prototypes);
	zbx_vector_ptr_create(&groups);
	zbx_vector_uint64_create(&del_hostgroupids);
	zbx_vector_uint64_create(&del_hostmacroids);
	zbx_vector_ptr_create(&interfaces);
	zbx_vector_ptr_create(&hostmacros);

	lld_interfaces_get(lld_ruleid, &interfaces);
	lld_hostmacros_get(lld_ruleid, &hostmacros);

	result = DBselect(
			"select h.hostid,h.host,h.name,h.status,hi.inventory_mode"
			" from hosts h,host_discovery hd"
				" left join host_inventory hi"
					" on hd.hostid=hi.hostid"
			" where h.hostid=hd.hostid"
				" and hd.parent_itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	parent_hostid;
		const char	*host_proto, *name_proto;
		zbx_lld_host_t	*host;
		unsigned char	status;
		zbx_lld_row_t	*lld_row;
		int		i;

		ZBX_STR2UINT64(parent_hostid, row[0]);
		host_proto = row[1];
		name_proto = row[2];
		status = (unsigned char)atoi(row[3]);
		if (SUCCEED == DBis_null(row[4]))
			inventory_mode = HOST_INVENTORY_DISABLED;
		else
			inventory_mode = (char)atoi(row[4]);

		lld_hosts_get(parent_hostid, &hosts, proxy_hostid, ipmi_authtype, ipmi_privilege, ipmi_username,
				ipmi_password, inventory_mode);

		lld_simple_groups_get(parent_hostid, &groupids);

		lld_group_prototypes_get(parent_hostid, &group_prototypes);
		lld_groups_get(parent_hostid, &groups);

		for (i = 0; i < lld_rows->values_num; i++)
		{
			lld_row = (zbx_lld_row_t *)lld_rows->values[i];

			host = lld_host_make(&hosts, host_proto, name_proto, &lld_row->jp_row);
			lld_groups_make(host, &groups, &group_prototypes, &lld_row->jp_row);
		}

		zbx_vector_ptr_sort(&hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		lld_groups_validate(&groups, error);
		lld_hosts_validate(&hosts, error);

		lld_interfaces_make(&interfaces, &hosts);
		lld_interfaces_validate(&hosts, error);

		lld_hostgroups_make(&groupids, &hosts, &groups, &del_hostgroupids);
		lld_templates_make(parent_hostid, &hosts);
		lld_hostmacros_make(&hostmacros, &hosts, &del_hostmacroids);

		lld_groups_save(&groups, &group_prototypes);
		lld_hosts_save(parent_hostid, &hosts, host_proto, proxy_hostid, ipmi_authtype, ipmi_privilege,
				ipmi_username, ipmi_password, status, inventory_mode, &del_hostgroupids,
				&del_hostmacroids);

		/* linking of the templates */
		lld_templates_link(&hosts);

		lld_hosts_remove(&hosts, lifetime, lastcheck);
		lld_groups_remove(&groups, lifetime, lastcheck);

		zbx_vector_ptr_clean(&groups, (zbx_mem_free_func_t)lld_group_free);
		zbx_vector_ptr_clean(&group_prototypes, (zbx_mem_free_func_t)lld_group_prototype_free);
		zbx_vector_ptr_clean(&hosts, (zbx_mem_free_func_t)lld_host_free);

		groupids.values_num = 0;
		del_hostgroupids.values_num = 0;
		del_hostmacroids.values_num = 0;
	}
	DBfree_result(result);

	zbx_vector_ptr_clean(&hostmacros, (zbx_mem_free_func_t)lld_hostmacro_free);
	zbx_vector_ptr_clean(&interfaces, (zbx_mem_free_func_t)lld_interface_free);

	zbx_vector_ptr_destroy(&hostmacros);
	zbx_vector_ptr_destroy(&interfaces);
	zbx_vector_uint64_destroy(&del_hostmacroids);
	zbx_vector_uint64_destroy(&del_hostgroupids);
	zbx_vector_ptr_destroy(&groups);
	zbx_vector_ptr_destroy(&group_prototypes);
	zbx_vector_uint64_destroy(&groupids);
	zbx_vector_ptr_destroy(&hosts);

	zbx_free(ipmi_password);
	zbx_free(ipmi_username);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
