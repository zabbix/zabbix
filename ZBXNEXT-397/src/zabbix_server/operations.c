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

#include "common.h"
#include "comms.h"
#include "db.h"
#include "log.h"
#include "dbcache.h"

#include "operations.h"

/******************************************************************************
 *                                                                            *
 * Function: select_discovered_host                                           *
 *                                                                            *
 * Purpose: select hostid of discovered host                                  *
 *                                                                            *
 * Parameters: dhostid - discovered host id                                   *
 *                                                                            *
 * Return value: hostid - existing hostid, 0 - if not found                   *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	select_discovered_host(DB_EVENT *event)
{
	const char	*__function_name = "select_discovered_host";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostid = 0;
	char		sql[512];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64,
			__function_name, event->eventid);

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
			zbx_snprintf(sql, sizeof(sql),
				"select h.hostid"
				" from hosts h,interface i,dservices ds"
				" where h.hostid=i.hostid"
					" and i.useip=1"
					" and i.ip=ds.ip"
					" and ds.dhostid=" ZBX_FS_UI64
					DB_NODE
				" order by i.hostid",
				event->objectid, DBnode_local("i.interfaceid"));
			break;
		case EVENT_OBJECT_DSERVICE:
			zbx_snprintf(sql, sizeof(sql),
				"select h.hostid"
				" from hosts h,interface i,dservices ds"
				" where h.hostid=i.hostid"
					" and i.useip=1"
					" and i.ip=ds.ip"
					" and ds.dserviceid =" ZBX_FS_UI64
					DB_NODE
				" order by i.hostid",
				event->objectid, DBnode_local("i.interfaceid"));
			break;
		default:
			goto exit;
	}

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(hostid, row[0]);
	DBfree_result(result);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64, __function_name, hostid);

	return hostid;
}

/******************************************************************************
 *                                                                            *
 * Function: add_discovered_host_groups                                       *
 *                                                                            *
 * Purpose: add group to host if not added already                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	add_discovered_host_groups(zbx_uint64_t hostid, zbx_vector_uint64_t *groupids)
{
	const char	*__function_name = "add_discovered_host_groups";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	groupid;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select groupid"
			" from hosts_groups"
			" where hostid=" ZBX_FS_UI64
				" and",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);

		if (FAIL == (i = zbx_vector_uint64_bsearch(groupids, groupid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_uint64_remove_noorder(groupids, i);
	}
	DBfree_result(result);

	if (0 != groupids->values_num)
	{
		const char	*ins_hosts_groups_sql = "insert into hosts_groups (hostgroupid,hostid,groupid) values ";
		zbx_uint64_t	hostgroupid;
#ifdef HAVE_MULTIROW_INSERT
		const char	*row_dl = ",";
#else
		const char	*row_dl = ";\n";
#endif

		hostgroupid = DBget_maxid_num("hosts_groups", groupids->values_num);

		sql_offset = 0;
#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "begin\n");
#endif
#ifdef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_hosts_groups_sql);
#endif
		for (i = 0; i < groupids->values_num; i++)
		{
#ifndef HAVE_MULTIROW_INSERT
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_hosts_groups_sql);
#endif
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"(" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")%s",
					hostgroupid++, hostid, groupids->values[i], row_dl);

#ifdef HAVE_ORACLE
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "end;\n");
#endif
		}

#ifdef HAVE_MULTIROW_INSERT
		sql_offset--;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
#endif
		DBexecute("%s", sql);
	}

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: add_discovered_host                                              *
 *                                                                            *
 * Purpose: add discovered host if it was not added already                   *
 *                                                                            *
 * Parameters: dhostid - discovered host id                                   *
 *                                                                            *
 * Return value: hostid - new/existing hostid                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	add_discovered_host(DB_EVENT *event)
{
	const char		*__function_name = "add_discovered_host";

	DB_RESULT		result;
	DB_RESULT		result2;
	DB_ROW			row;
	DB_ROW			row2;
	zbx_uint64_t		dhostid, hostid = 0, proxy_hostid, host_proxy_hostid;
	char			*host = NULL, *host_esc, *host_unique;
	unsigned short		port;
	zbx_uint64_t		groupid;
	zbx_vector_uint64_t	groupids;
	unsigned char		svc_type, interface_type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64, __function_name, event->eventid);

	zbx_vector_uint64_create(&groupids);

	if (0 == *(zbx_uint64_t *)DCconfig_get_config_data(&groupid, CONFIG_DISCOVERY_GROUPID))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add discovered host: group for discovered hosts is not defined");
		goto clean;
	}

	zbx_vector_uint64_append(&groupids, groupid);

	if (EVENT_OBJECT_DHOST == event->object || EVENT_OBJECT_DSERVICE == event->object)
	{
		if (EVENT_OBJECT_DHOST == event->object)
		{
			result = DBselect(
					"select ds.dhostid,dr.proxy_hostid,ds.ip,ds.dns,ds.port,ds.type"
					" from drules dr,dchecks dc,dservices ds"
					" where dc.druleid=dr.druleid"
						" and ds.dcheckid=dc.dcheckid"
						" and ds.dhostid=" ZBX_FS_UI64
					" order by ds.dserviceid",
					event->objectid);
		}
		else
		{
			result = DBselect(
					"select ds.dhostid,dr.proxy_hostid,ds.ip,ds.dns,ds.port,ds.type"
					" from drules dr,dchecks dc,dservices ds,dservices ds1"
					" where dc.druleid=dr.druleid"
						" and ds.dcheckid=dc.dcheckid"
						" and ds1.dhostid=ds.dhostid"
						" and ds1.dserviceid=" ZBX_FS_UI64
					" order by ds.dserviceid",
					event->objectid);
		}

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(dhostid, row[0]);
			ZBX_DBROW2UINT64(proxy_hostid, row[1]);
			svc_type = (unsigned char)atoi(row[5]);

			switch (svc_type)
			{
				case SVC_AGENT:
					port = (unsigned short)atoi(row[4]);
					interface_type = INTERFACE_TYPE_AGENT;
					break;
				case SVC_SNMPv1:
				case SVC_SNMPv2c:
				case SVC_SNMPv3:
					port = (unsigned short)atoi(row[4]);
					interface_type = INTERFACE_TYPE_SNMP;
					break;
				default:
					port = ZBX_DEFAULT_AGENT_PORT;
					interface_type = INTERFACE_TYPE_AGENT;
			}

			if (0 == hostid)
			{
				result2 = DBselect(
						"select distinct h.hostid,h.proxy_hostid"
						" from hosts h,interface i,dservices ds"
						" where h.hostid=i.hostid"
							" and i.ip=ds.ip"
							" and ds.dhostid=" ZBX_FS_UI64
						       	DB_NODE
						" order by h.hostid",
						dhostid,
						DBnode_local("h.hostid"));

				if (NULL != (row2 = DBfetch(result2)))
				{
					ZBX_STR2UINT64(hostid, row2[0]);
					ZBX_DBROW2UINT64(host_proxy_hostid, row2[1]);
				}
				DBfree_result(result2);
			}

			if (0 == hostid)
			{
				hostid = DBget_maxid("hosts");

				/* for host uniqueness purposes */
				host = zbx_strdup(host, '\0' != *row[3] ? row[3] : row[2]);

				make_hostname(host);	/* replace not-allowed symbols */
				host_unique = DBget_unique_hostname_by_sample(host);
				host_esc = DBdyn_escape_string(host_unique);

				zbx_free(host);

				DBexecute("insert into hosts"
							" (hostid,proxy_hostid,host,name)"
						" values"
							" (" ZBX_FS_UI64 ",%s,'%s','%s')",
						hostid, DBsql_id_ins(proxy_hostid), host_esc, host_esc);

				DBadd_interface(hostid, interface_type, 1, row[2], row[3], port);

				zbx_free(host_unique);
				zbx_free(host_esc);

				add_discovered_host_groups(hostid, &groupids);
			}
			else
			{
				if (host_proxy_hostid != proxy_hostid)
				{
					DBexecute("update hosts"
							" set proxy_hostid=%s"
							" where hostid=" ZBX_FS_UI64,
							DBsql_id_ins(proxy_hostid), hostid);
				}

				DBadd_interface(hostid, interface_type, 1, row[2], row[3], port);
			}
		}
		DBfree_result(result);
	}
	else if (EVENT_OBJECT_ZABBIX_ACTIVE == event->object)
	{
		result = DBselect(
				"select proxy_hostid,host,listen_ip,listen_dns,listen_port"
				" from autoreg_host"
				" where autoreg_hostid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL != (row = DBfetch(result)))
		{
			char	sql[512];

			ZBX_DBROW2UINT64(proxy_hostid, row[0]);
			host_esc = DBdyn_escape_string_len(row[1], HOST_HOST_LEN);
			port = (unsigned short)atoi(row[4]);

			zbx_snprintf(sql, sizeof(sql),
					"select hostid,proxy_hostid"
					" from hosts"
					" where host='%s'"
						DB_NODE
					" order by hostid",
					host_esc,
					DBnode_local("hostid"));

			result2 = DBselectN(sql, 1);

			if (NULL == (row2 = DBfetch(result2)))
			{
				hostid = DBget_maxid("hosts");

				DBexecute("insert into hosts"
							" (hostid,proxy_hostid,host,name)"
						" values"
							" (" ZBX_FS_UI64 ",%s,'%s','%s')",
						hostid, DBsql_id_ins(proxy_hostid), host_esc, host_esc);

				DBadd_interface(hostid, INTERFACE_TYPE_AGENT, 1, row[2], row[3], port);

				add_discovered_host_groups(hostid, &groupids);
			}
			else
			{
				ZBX_STR2UINT64(hostid, row2[0]);
				ZBX_DBROW2UINT64(host_proxy_hostid, row2[1]);

				if (host_proxy_hostid != proxy_hostid)
				{
					DBexecute("update hosts"
							" set proxy_hostid=%s"
							" where hostid=" ZBX_FS_UI64,
							DBsql_id_ins(proxy_hostid), hostid);
				}

				DBadd_interface(hostid, INTERFACE_TYPE_AGENT, 1, row[2], row[3], port);
			}
			DBfree_result(result2);

			zbx_free(host_esc);
		}
		DBfree_result(result);
	}
clean:
	zbx_vector_uint64_destroy(&groupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return hostid;
}

/******************************************************************************
 *                                                                            *
 * Function: op_host_add                                                      *
 *                                                                            *
 * Purpose: add discovered host                                               *
 *                                                                            *
 * Parameters: trigger - trigger data                                         *
 *             action  - action data                                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	op_host_add(DB_EVENT *event)
{
	const char	*__function_name = "op_host_add";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	add_discovered_host(event);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_host_del                                                      *
 *                                                                            *
 * Purpose: delete host                                                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	op_host_del(DB_EVENT *event)
{
	const char	*__function_name = "op_host_del";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	DBdelete_host(hostid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_host_enable                                                   *
 *                                                                            *
 * Purpose: enable discovered                                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	op_host_enable(DB_EVENT *event)
{
	const char	*__function_name = "op_host_enable";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = add_discovered_host(event)))
		return;

	DBexecute(
			"update hosts"
			" set status=%d"
			" where hostid=" ZBX_FS_UI64,
			HOST_STATUS_MONITORED,
			hostid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_host_disable                                                  *
 *                                                                            *
 * Purpose: disable host                                                      *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	op_host_disable(DB_EVENT *event)
{
	const char	*__function_name = "op_host_disable";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	if (0 == (hostid = add_discovered_host(event)))
		return;

	DBexecute(
			"update hosts"
			" set status=%d"
			" where hostid=" ZBX_FS_UI64,
			HOST_STATUS_NOT_MONITORED,
			hostid);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_groups_add                                                    *
 *                                                                            *
 * Purpose: add groups to discovered host                                     *
 *                                                                            *
 * Parameters: event    - [IN] event data                                     *
 *             groupids - [IN] IDs of groups to add                           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	op_groups_add(DB_EVENT *event, zbx_vector_uint64_t *groupids)
{
	const char	*__function_name = "op_groups_add";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	if (0 == (hostid = add_discovered_host(event)))
		return;

	add_discovered_host_groups(hostid, groupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_groups_del                                                    *
 *                                                                            *
 * Purpose: delete groups from discovered host                                *
 *                                                                            *
 * Parameters: event    - [IN] event data                                     *
 *             groupids - [IN] IDs of groups to delete                        *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
void	op_groups_del(DB_EVENT *event, zbx_vector_uint64_t *groupids)
{
	const char	*__function_name = "op_groups_del";

	DB_RESULT	result;
	zbx_uint64_t	hostid;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	sql = zbx_malloc(sql, sql_alloc);

	/* make sure host belongs to at least one hostgroup */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select groupid"
			" from hosts_groups"
			" where hostid=" ZBX_FS_UI64
				" and not",
			hostid);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);

	result = DBselectN(sql, 1);

	if (NULL == DBfetch(result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot remove host \"%s\" from all host groups:"
				" it must belong to at least one", zbx_host_string(hostid));
	}
	else
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"delete from hosts_groups"
				" where hostid=" ZBX_FS_UI64
					" and",
				hostid);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);

		DBexecute("%s", sql);
	}
	DBfree_result(result);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_template_add                                                  *
 *                                                                            *
 * Purpose: link host with template                                           *
 *                                                                            *
 * Parameters: event           - [IN] event data                              *
 *             lnk_templateids - [IN] array of template IDs                   *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	op_template_add(DB_EVENT *event, zbx_vector_uint64_t *lnk_templateids)
{
	const char	*__function_name = "op_template_add";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY && event->source != EVENT_SOURCE_AUTO_REGISTRATION)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE && event->object != EVENT_OBJECT_ZABBIX_ACTIVE)
		return;

	if (0 == (hostid = add_discovered_host(event)))
		return;

	DBcopy_template_elements(hostid, lnk_templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: op_template_del                                                  *
 *                                                                            *
 * Purpose: unlink and clear host from template                               *
 *                                                                            *
 * Parameters: event           - [IN] event data                              *
 *             lnk_templateids - [IN] array of template IDs                   *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	op_template_del(DB_EVENT *event, zbx_vector_uint64_t *del_templateids)
{
	const char	*__function_name = "op_template_del";
	zbx_uint64_t	hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (event->source != EVENT_SOURCE_DISCOVERY)
		return;

	if (event->object != EVENT_OBJECT_DHOST && event->object != EVENT_OBJECT_DSERVICE)
		return;

	if (0 == (hostid = select_discovered_host(event)))
		return;

	DBdelete_template_elements(hostid, del_templateids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
