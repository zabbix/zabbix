/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxserver.h"
#include "operations.h"

#include "log.h"
#include "zbxavailability.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_host.h"
#include "zbxnum.h"
#include "zbxdbwrap.h"
#include "zbx_host_constants.h"

typedef enum
{
	ZBX_DISCOVERY_UNSPEC = 0,
	ZBX_DISCOVERY_DNS,
	ZBX_DISCOVERY_IP,
	ZBX_DISCOVERY_VALUE
}
zbx_dcheck_source_t;

/******************************************************************************
 *                                                                            *
 * Purpose: select hostid of discovered host                                  *
 *                                                                            *
 * Parameters: event          - [IN] source event data                        *
 *             hostname       - [OUT] hostname where event occurred           *
 *                                                                            *
 * Return value: hostid - existing hostid, 0 - if not found                   *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	select_discovered_host(const zbx_db_event *event, char **hostname)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	hostid = 0, proxy_hostid;
	char		*sql = NULL, *ip_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64, __func__, event->eventid);

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
		case EVENT_OBJECT_DSERVICE:
			result = zbx_db_select(
					"select dr.proxy_hostid,ds.ip"
					" from drules dr,dchecks dc,dservices ds"
					" where dc.druleid=dr.druleid"
						" and ds.dcheckid=dc.dcheckid"
						" and ds.%s=" ZBX_FS_UI64,
					EVENT_OBJECT_DSERVICE == event->object ? "dserviceid" : "dhostid",
					event->objectid);

			if (NULL == (row = zbx_db_fetch(result)))
			{
				zbx_db_free_result(result);
				goto exit;
			}

			ZBX_DBROW2UINT64(proxy_hostid, row[0]);
			ip_esc = zbx_db_dyn_escape_string(row[1]);
			zbx_db_free_result(result);

			sql = zbx_dsprintf(sql,
					"select h.hostid,h.name"
					" from hosts h,interface i"
					" where h.hostid=i.hostid"
						" and i.ip='%s'"
						" and i.useip=1"
						" and h.status in (%d,%d)"
						" and h.proxy_hostid%s"
					" order by i.hostid",
					ip_esc,
					HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
					zbx_db_sql_id_cmp(proxy_hostid));

			zbx_free(ip_esc);
			break;
		case EVENT_OBJECT_ZABBIX_ACTIVE:
			sql = zbx_dsprintf(sql,
					"select h.hostid,h.name"
					" from hosts h,autoreg_host a"
					" where h.host=a.host"
						" and a.autoreg_hostid=" ZBX_FS_UI64
						" and h.status in (%d,%d)",
					event->objectid,
					HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);
			break;
		default:
			goto exit;
	}

	result = zbx_db_select_n(sql, 1);

	zbx_free(sql);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		size_t	out_alloc = 0, out_offset = 0;

		ZBX_STR2UINT64(hostid, row[0]);
		zbx_strcpy_alloc(hostname, &out_alloc, &out_offset, row[1]);
	}
	zbx_db_free_result(result);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64, __func__, hostid);

	return hostid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add group to host if not added already                            *
 *                                                                            *
 * Parameters: hostid         - [IN]  host identifier                         *
 *             groupids       - [IN]  array of group identifiers              *
 *                                                                            *
 ******************************************************************************/
static void	add_discovered_host_groups(zbx_uint64_t hostid, zbx_vector_uint64_t *groupids)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	groupid;
	char		*sql = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select groupid"
			" from hosts_groups"
			" where hostid=" ZBX_FS_UI64
				" and",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);

	result = zbx_db_select("%s", sql);

	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(groupid, row[0]);

		if (FAIL == (i = zbx_vector_uint64_search(groupids, groupid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_uint64_remove_noorder(groupids, i);
	}
	zbx_db_free_result(result);

	if (0 != groupids->values_num)
	{
		zbx_uint64_t	hostgroupid;
		zbx_db_insert_t	db_insert;

		hostgroupid = zbx_db_get_maxid_num("hosts_groups", groupids->values_num);

		zbx_db_insert_prepare(&db_insert, "hosts_groups", "hostgroupid", "hostid", "groupid", NULL);

		zbx_vector_uint64_sort(groupids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (i = 0; i < groupids->values_num; i++)
		{
			zbx_db_insert_add_values(&db_insert, hostgroupid, hostid, groupids->values[i]);
			zbx_audit_hostgroup_update_json_add_group(hostid, hostgroupid, groupids->values[i]);
			hostgroupid++;
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add discovered host if it was not added already                   *
 *                                                                            *
 * Parameters: event          - [IN] the source event                         *
 *             status         - [OUT] found or created host status            *
 *             cfg            - [IN] the global configuration data            *
 *                                                                            *
 * Return value: hostid - new/existing hostid                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	add_discovered_host(const zbx_db_event *event, int *status, zbx_config_t *cfg)
{
	DB_RESULT		result;
	DB_RESULT		result2;
	DB_ROW			row;
	DB_ROW			row2;
	zbx_uint64_t		dhostid, hostid = 0, proxy_hostid, druleid;
	char			*host, *host_esc, *host_unique, *host_visible, *hostname = NULL;
	unsigned short		port;
	zbx_vector_uint64_t	groupids;
	unsigned char		svc_type, interface_type;
	zbx_db_insert_t		db_insert, db_insert_host_rtdata;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64, __func__, event->eventid);

	zbx_vector_uint64_create(&groupids);

	if (ZBX_DISCOVERY_GROUPID_UNDEFINED == cfg->discovery_groupid)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add discovered host: group for discovered hosts is not defined");
		goto clean;
	}

	zbx_vector_uint64_append(&groupids, cfg->discovery_groupid);

	if (EVENT_OBJECT_DHOST == event->object || EVENT_OBJECT_DSERVICE == event->object)
	{
		if (EVENT_OBJECT_DHOST == event->object)
		{
			result = zbx_db_select(
					"select ds.dhostid,dr.proxy_hostid,ds.ip,ds.dns,ds.port,dc.type,"
						"dc.host_source,dc.name_source,dr.druleid,"
						"dc.snmp_community,dc.snmpv3_securityname,dc.snmpv3_securitylevel,"
						"dc.snmpv3_authpassphrase,dc.snmpv3_privpassphrase,"
						"dc.snmpv3_authprotocol,dc.snmpv3_privprotocol,dc.snmpv3_contextname"
					" from drules dr,dchecks dc,dservices ds"
					" where dc.druleid=dr.druleid"
						" and ds.dcheckid=dc.dcheckid"
						" and ds.dhostid=" ZBX_FS_UI64
					" order by ds.dserviceid",
					event->objectid);
		}
		else
		{
			result = zbx_db_select(
					"select ds.dhostid,dr.proxy_hostid,ds.ip,ds.dns,ds.port,dc.type,"
						"dc.host_source,dc.name_source,dr.druleid,"
						"dc.snmp_community,dc.snmpv3_securityname,dc.snmpv3_securitylevel,"
						"dc.snmpv3_authpassphrase,dc.snmpv3_privpassphrase,"
						"dc.snmpv3_authprotocol,dc.snmpv3_privprotocol,dc.snmpv3_contextname"
					" from drules dr,dchecks dc,dservices ds,dservices ds1"
					" where dc.druleid=dr.druleid"
						" and ds.dcheckid=dc.dcheckid"
						" and ds1.dhostid=ds.dhostid"
						" and ds1.dserviceid=" ZBX_FS_UI64
					" order by ds.dserviceid",
					event->objectid);
		}

		while (NULL != (row = zbx_db_fetch(result)))
		{
			zbx_uint64_t	interfaceid;

			ZBX_STR2UINT64(dhostid, row[0]);
			ZBX_STR2UINT64(druleid, row[8]);
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
				result2 = zbx_db_select(
						"select distinct h.hostid,h.name,h.status"
						" from hosts h,interface i,dservices ds"
						" where h.hostid=i.hostid"
							" and i.ip=ds.ip"
							" and h.status in (%d,%d)"
							" and h.flags<>%d"
							" and h.proxy_hostid%s"
							" and ds.dhostid=" ZBX_FS_UI64
						" order by h.hostid",
						HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
						ZBX_FLAG_DISCOVERY_PROTOTYPE,
						zbx_db_sql_id_cmp(proxy_hostid), dhostid);

				if (NULL != (row2 = zbx_db_fetch(result2)))
				{
					ZBX_STR2UINT64(hostid, row2[0]);
					hostname = zbx_strdup(NULL, row2[1]);
					*status = atoi(row2[2]);
				}

				zbx_db_free_result(result2);
			}

			if (0 == hostid)
			{
				DB_RESULT		result3;
				DB_ROW			row3;
				zbx_dcheck_source_t	host_source, name_source;
				char			*sql = NULL;
				size_t			sql_alloc, sql_offset;

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"select ds.value"
						" from dchecks dc"
							" left join dservices ds"
								" on ds.dcheckid=dc.dcheckid"
									" and ds.dhostid=" ZBX_FS_UI64
						" where dc.druleid=" ZBX_FS_UI64
							" and dc.host_source=%d"
						" order by ds.dserviceid",
							dhostid, druleid, ZBX_DISCOVERY_VALUE);

				result3 = zbx_db_select_n(sql, 1);

				if (NULL != (row3 = zbx_db_fetch(result3)))
				{
					if (SUCCEED == zbx_db_is_null_basic(row3[0]) || '\0' == *row3[0])
					{
						zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve service value for"
								" host name on \"%s\"", row[2]);
						host_source = ZBX_DISCOVERY_DNS;
					}
					else
						host_source = ZBX_DISCOVERY_VALUE;
				}
				else
				{
					if (ZBX_DISCOVERY_VALUE == (host_source = atoi(row[6])))
					{
						zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve service value for"
								" host name on \"%s\"", row[2]);
						host_source = ZBX_DISCOVERY_DNS;
					}
				}

				if (ZBX_DISCOVERY_VALUE == host_source)
					host = zbx_strdup(NULL, row3[0]);
				else if (ZBX_DISCOVERY_IP == host_source || '\0' == *row[3])
					host = zbx_strdup(NULL, row[2]);
				else
					host = zbx_strdup(NULL, row[3]);

				zbx_db_free_result(result3);

				/* for host uniqueness purposes */
				zbx_make_hostname(host);	/* replace not-allowed symbols */
				host_unique = zbx_db_get_unique_hostname_by_sample(host, "host");
				zbx_free(host);

				sql_offset = 0;
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"select ds.value"
						" from dchecks dc"
							" left join dservices ds"
								" on ds.dcheckid=dc.dcheckid"
									" and ds.dhostid=" ZBX_FS_UI64
						" where dc.druleid=" ZBX_FS_UI64
							" and dc.host_source in (%d,%d,%d,%d)"
							" and dc.name_source=%d"
						" order by ds.dserviceid",
							dhostid, druleid, ZBX_DISCOVERY_UNSPEC, ZBX_DISCOVERY_DNS,
							ZBX_DISCOVERY_IP, ZBX_DISCOVERY_VALUE, ZBX_DISCOVERY_VALUE);

				result3 = zbx_db_select_n(sql, 1);

				if (NULL != (row3 = zbx_db_fetch(result3)))
				{
					if (SUCCEED == zbx_db_is_null_basic(row3[0]) || '\0' == *row3[0])
					{
						zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve service value for"
								" host visible name on \"%s\"", row[2]);
						name_source = ZBX_DISCOVERY_UNSPEC;
					}
					else
						name_source = ZBX_DISCOVERY_VALUE;
				}
				else
				{
					if (ZBX_DISCOVERY_VALUE == (name_source = atoi(row[7])))
					{
						zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve service value for"
								" host visible name on \"%s\"", row[2]);
						name_source = ZBX_DISCOVERY_UNSPEC;
					}
				}

				if (ZBX_DISCOVERY_VALUE == name_source)
					host_visible = zbx_strdup(NULL, row3[0]);
				else if (ZBX_DISCOVERY_IP == name_source ||
						(ZBX_DISCOVERY_DNS == name_source && '\0' == *row[3]))
					host_visible = zbx_strdup(NULL, row[2]);
				else if (ZBX_DISCOVERY_DNS == name_source)
					host_visible = zbx_strdup(NULL, row[3]);
				else
					host_visible = zbx_strdup(NULL, host_unique);

				zbx_db_free_result(result3);
				zbx_free(sql);

				zbx_make_hostname(host_visible);	/* replace not-allowed symbols */
				zbx_free(hostname);
				hostname = zbx_db_get_unique_hostname_by_sample(host_visible, "name");
				zbx_free(host_visible);

				*status = HOST_STATUS_MONITORED;

				hostid = zbx_db_get_maxid("hosts");

				zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "proxy_hostid", "host", "name",
						NULL);
				zbx_db_insert_add_values(&db_insert, hostid, proxy_hostid, host_unique,
						hostname);
				zbx_db_insert_execute(&db_insert);
				zbx_db_insert_clean(&db_insert);

				zbx_db_insert_prepare(&db_insert_host_rtdata, "host_rtdata", "hostid",
						"active_available", NULL);

				zbx_db_insert_add_values(&db_insert_host_rtdata, hostid, INTERFACE_AVAILABLE_UNKNOWN);
				zbx_db_insert_execute(&db_insert_host_rtdata);
				zbx_db_insert_clean(&db_insert_host_rtdata);

				zbx_audit_host_create_entry(ZBX_AUDIT_ACTION_ADD, hostid, hostname);

				if (HOST_INVENTORY_DISABLED != cfg->default_inventory_mode)
					zbx_db_add_host_inventory(hostid, cfg->default_inventory_mode);

				zbx_audit_host_update_json_add_proxy_hostid_and_hostname_and_inventory_mode(hostid,
						proxy_hostid, host_unique, cfg->default_inventory_mode);

				interfaceid = zbx_db_add_interface(hostid, interface_type, 1, row[2], row[3], port,
						ZBX_CONN_DEFAULT);

				zbx_free(host_unique);

				add_discovered_host_groups(hostid, &groupids);
			}
			else
			{
				zbx_audit_host_create_entry(ZBX_AUDIT_ACTION_UPDATE, hostid, hostname);
				interfaceid = zbx_db_add_interface(hostid, interface_type, 1, row[2], row[3], port,
						ZBX_CONN_DEFAULT);
			}

			if (INTERFACE_TYPE_SNMP == interface_type)
			{
				unsigned char	securitylevel, authprotocol, privprotocol,
						version = ZBX_IF_SNMP_VERSION_2;

				ZBX_STR2UCHAR(securitylevel, row[11]);
				ZBX_STR2UCHAR(authprotocol, row[14]);
				ZBX_STR2UCHAR(privprotocol, row[15]);

				if (SVC_SNMPv1 == svc_type)
					version = ZBX_IF_SNMP_VERSION_1;
				else if (SVC_SNMPv3 == svc_type)
					version = ZBX_IF_SNMP_VERSION_3;

				zbx_db_add_interface_snmp(interfaceid, version, SNMP_BULK_ENABLED, row[9], row[10],
						securitylevel, row[12], row[13], authprotocol, privprotocol, row[16],
						hostid);
			}
		}
		zbx_db_free_result(result);
	}
	else if (EVENT_OBJECT_ZABBIX_ACTIVE == event->object)
	{
		result = zbx_db_select(
				"select proxy_hostid,host,listen_ip,listen_dns,listen_port,flags,tls_accepted"
				" from autoreg_host"
				" where autoreg_hostid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL != (row = zbx_db_fetch(result)))
		{
			char			*sql = NULL;
			zbx_uint64_t		host_proxy_hostid;
			zbx_conn_flags_t	flags;
			int			flags_int, tls_accepted;
			unsigned char		useip = 1;

			ZBX_DBROW2UINT64(proxy_hostid, row[0]);
			host_esc = zbx_db_dyn_escape_field("hosts", "host", row[1]);
			port = (unsigned short)atoi(row[4]);
			flags_int = atoi(row[5]);

			switch (flags_int)
			{
				case ZBX_CONN_DEFAULT:
				case ZBX_CONN_IP:
				case ZBX_CONN_DNS:
					flags = (zbx_conn_flags_t)flags_int;
					break;
				default:
					flags = ZBX_CONN_DEFAULT;
					zabbix_log(LOG_LEVEL_WARNING, "wrong flags value: %d for host \"%s\":",
							flags_int, row[1]);
			}

			if (ZBX_CONN_DNS == flags)
				useip = 0;

			tls_accepted = atoi(row[6]);

			result2 = zbx_db_select(
					"select null"
					" from hosts"
					" where host='%s'"
						" and status=%d",
					host_esc, HOST_STATUS_TEMPLATE);

			if (NULL != zbx_db_fetch(result2))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot add discovered host \"%s\":"
						" template with the same name already exists", row[1]);
				zbx_db_free_result(result2);
				goto out;
			}
			zbx_db_free_result(result2);

			sql = zbx_dsprintf(sql,
					"select hostid,proxy_hostid,name,status"
					" from hosts"
					" where host='%s'"
						" and flags<>%d"
						" and status in (%d,%d)"
					" order by hostid",
					host_esc, ZBX_FLAG_DISCOVERY_PROTOTYPE,
					HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);

			result2 = zbx_db_select_n(sql, 1);

			zbx_free(sql);

			if (NULL == (row2 = zbx_db_fetch(result2)))
			{
				hostid = zbx_db_get_maxid("hosts");
				hostname = zbx_strdup(hostname, row[1]);
				*status = HOST_STATUS_MONITORED;

				if (ZBX_TCP_SEC_TLS_PSK == tls_accepted)
				{
					char	psk_identity[HOST_TLS_PSK_IDENTITY_LEN_MAX], psk[HOST_TLS_PSK_LEN_MAX];

					DCget_autoregistration_psk(psk_identity, sizeof(psk_identity),
							(unsigned char *)psk, sizeof(psk));

					zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "proxy_hostid",
							"host", "name", "tls_connect", "tls_accept",
							"tls_psk_identity", "tls_psk", NULL);
					zbx_db_insert_add_values(&db_insert, hostid, proxy_hostid, hostname, hostname,
						tls_accepted, tls_accepted, psk_identity, psk);

					zbx_audit_host_create_entry(ZBX_AUDIT_ACTION_ADD, hostid, hostname);
					zbx_audit_host_update_json_add_tls_and_psk(hostid, tls_accepted, tls_accepted,
							psk_identity, psk);
				}
				else
				{
					zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "proxy_hostid", "host",
							"name", NULL);

					zbx_audit_host_create_entry(ZBX_AUDIT_ACTION_ADD, hostid, hostname);
					zbx_db_insert_add_values(&db_insert, hostid, proxy_hostid, hostname,
							hostname);
				}

				zbx_db_insert_execute(&db_insert);
				zbx_db_insert_clean(&db_insert);

				zbx_db_insert_prepare(&db_insert_host_rtdata, "host_rtdata", "hostid",
						"active_available", NULL);

				zbx_db_insert_add_values(&db_insert_host_rtdata, hostid, INTERFACE_AVAILABLE_UNKNOWN);
				zbx_db_insert_execute(&db_insert_host_rtdata);
				zbx_db_insert_clean(&db_insert_host_rtdata);

				if (HOST_INVENTORY_DISABLED != cfg->default_inventory_mode)
					zbx_db_add_host_inventory(hostid, cfg->default_inventory_mode);

				zbx_audit_host_update_json_add_proxy_hostid_and_hostname_and_inventory_mode(hostid,
						proxy_hostid, hostname, cfg->default_inventory_mode);

				zbx_db_add_interface(hostid, INTERFACE_TYPE_AGENT, useip, row[2], row[3], port, flags);

				add_discovered_host_groups(hostid, &groupids);
			}
			else
			{
				ZBX_STR2UINT64(hostid, row2[0]);
				ZBX_DBROW2UINT64(host_proxy_hostid, row2[1]);
				hostname = zbx_strdup(hostname, row2[2]);
				*status = atoi(row2[3]);

				zbx_audit_host_create_entry(ZBX_AUDIT_ACTION_UPDATE, hostid, hostname);

				if (host_proxy_hostid != proxy_hostid)
				{
					zbx_db_execute("update hosts"
							" set proxy_hostid=%s"
							" where hostid=" ZBX_FS_UI64,
							zbx_db_sql_id_ins(proxy_hostid), hostid);

					zbx_audit_host_update_json_update_proxy_hostid(hostid, host_proxy_hostid,
							proxy_hostid);
				}

				zbx_db_add_interface(hostid, INTERFACE_TYPE_AGENT, useip, row[2], row[3], port, flags);
			}
			zbx_db_free_result(result2);
out:
			zbx_free(host_esc);
		}
		zbx_db_free_result(result);
	}
clean:
	zbx_config_clean(cfg);
	zbx_vector_uint64_destroy(&groupids);
	zbx_free(hostname);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return hostid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the event is discovery or autoregistration event        *
 *                                                                            *
 * Parameters: event          - [IN] source event data                        *
 *                                                                            *
 * Return value: SUCCEED - it's discovery or autoregistration event           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	is_discovery_or_autoregistration(const zbx_db_event *event)
{
	if (event->source == EVENT_SOURCE_DISCOVERY && (event->object == EVENT_OBJECT_DHOST ||
			event->object == EVENT_OBJECT_DSERVICE))
	{
		return SUCCEED;
	}

	if (event->source == EVENT_SOURCE_AUTOREGISTRATION && event->object == EVENT_OBJECT_ZABBIX_ACTIVE)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add discovered host                                               *
 *                                                                            *
 * Parameters: event          - [IN] source event data                        *
 *             cfg            - [IN] the global configuration data            *
 *                                                                            *
 ******************************************************************************/
void	op_host_add(const zbx_db_event *event, zbx_config_t *cfg)
{
	int	status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	add_discovered_host(event, &status, cfg);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: delete host                                                       *
 *                                                                            *
 * Parameters: event          - [IN] source event data                        *
 *                                                                            *
 ******************************************************************************/
void	op_host_del(const zbx_db_event *event)
{
	zbx_vector_uint64_t	hostids;
	zbx_vector_str_t	hostnames;
	zbx_uint64_t		hostid;
	char			*hostname = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 == (hostid = select_discovered_host(event, &hostname)))
		goto out;

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_append(&hostids, hostid);
	zbx_vector_str_create(&hostnames);
	zbx_vector_str_append(&hostnames, zbx_strdup(NULL, hostname));

	zbx_db_delete_hosts_with_prototypes(&hostids, &hostnames);

	zbx_vector_str_clear_ext(&hostnames, zbx_str_free);
	zbx_vector_str_destroy(&hostnames);
	zbx_vector_uint64_destroy(&hostids);

	zbx_audit_host_del(hostid, hostname);
out:
	zbx_free(hostname);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enable discovered                                                 *
 *                                                                            *
 * Parameters: event          - [IN] the source event                         *
 *             cfg            - [IN] the global configuration data            *
 *                                                                            *
 ******************************************************************************/
void	op_host_enable(const zbx_db_event *event, zbx_config_t *cfg)
{
	zbx_uint64_t	hostid;
	int		status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 == (hostid = add_discovered_host(event, &status, cfg)))
		goto out;

	if (HOST_STATUS_MONITORED != status)
	{
		zbx_db_execute("update hosts"
				" set status=%d"
				" where hostid=" ZBX_FS_UI64,
				HOST_STATUS_MONITORED, hostid);

		zbx_audit_host_update_json_update_host_status(hostid, status, HOST_STATUS_MONITORED);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: disable host                                                      *
 *                                                                            *
 * Parameters: event          - [IN] the source event                         *
 *             cfg            - [IN] the global configuration data            *
 *                                                                            *
 ******************************************************************************/
void	op_host_disable(const zbx_db_event *event, zbx_config_t *cfg)
{
	zbx_uint64_t	hostid;
	int		status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 == (hostid = add_discovered_host(event, &status, cfg)))
		goto out;

	if (HOST_STATUS_NOT_MONITORED != status)
	{
		zbx_db_execute(
				"update hosts"
				" set status=%d"
				" where hostid=" ZBX_FS_UI64,
				HOST_STATUS_NOT_MONITORED, hostid);
		zbx_audit_host_update_json_update_host_status(hostid, status, HOST_STATUS_NOT_MONITORED);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets host inventory mode                                          *
 *                                                                            *
 * Parameters: event          - [IN] the source event                         *
 *             cfg            - [IN] the global configuration data            *
 *             inventory_mode - [IN] the new inventory mode, see              *
 *                              HOST_INVENTORY_ defines                       *
 *                                                                            *
 * Comments: This function does not allow disabling host inventory - only     *
 *           setting manual or automatic host inventory mode is supported.    *
 *                                                                            *
 ******************************************************************************/
void	op_host_inventory_mode(const zbx_db_event *event, zbx_config_t *cfg, int inventory_mode)
{
	zbx_uint64_t	hostid;
	int		status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 == (hostid = add_discovered_host(event, &status, cfg)))
		goto out;

	zbx_db_set_host_inventory(hostid, inventory_mode);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add groups to discovered host                                     *
 *                                                                            *
 * Parameters: event    - [IN] the source event data                          *
 *             cfg      - [IN] the global configuration data                  *
 *             groupids - [IN] IDs of groups to add                           *
 *                                                                            *
 ******************************************************************************/
void	op_groups_add(const zbx_db_event *event, zbx_config_t *cfg, zbx_vector_uint64_t *groupids)
{
	zbx_uint64_t	hostid;
	int		status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 == (hostid = add_discovered_host(event, &status, cfg)))
		goto out;

	add_discovered_host_groups(hostid, groupids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: delete groups from discovered host                                *
 *                                                                            *
 * Parameters: event    - [IN] source event data                              *
 *             groupids - [IN] IDs of groups to delete                        *
 *                                                                            *
 ******************************************************************************/
void	op_groups_del(const zbx_db_event *event, zbx_vector_uint64_t *groupids)
{
	DB_RESULT	result;
	zbx_uint64_t	hostid;
	char		*sql = NULL, *hostname = NULL;
	size_t		sql_alloc = 256, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 == (hostid = select_discovered_host(event, &hostname)))
		goto out;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	/* make sure the host belongs to at least one hostgroup after removing it from specified host groups */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select groupid"
			" from hosts_groups"
			" where hostid=" ZBX_FS_UI64
				" and not",
			hostid);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);

	result = zbx_db_select_n(sql, 1);

	if (NULL == zbx_db_fetch(result))
	{
		zbx_db_free_result(result);

		zabbix_log(LOG_LEVEL_WARNING, "cannot remove host \"%s\" from all host groups:"
				" it must belong to at least one", zbx_host_string(hostid));
	}
	else
	{
		zbx_vector_uint64_t	hostgroupids, found_groupids;
		DB_RESULT		result2;
		DB_ROW			row;

		zbx_db_free_result(result);

		zbx_vector_uint64_create(&hostgroupids);
		zbx_vector_uint64_create(&found_groupids);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select hostgroupid,groupid"
				" from hosts_groups"
				" where hostid=" ZBX_FS_UI64
					" and",
				hostid);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values, groupids->values_num);

		result2 = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result2)))
		{
			zbx_uint64_t	hostgroupid, groupid;

			ZBX_STR2UINT64(hostgroupid, row[0]);
			ZBX_STR2UINT64(groupid, row[1]);

			zbx_vector_uint64_append(&hostgroupids, hostgroupid);
			zbx_vector_uint64_append(&found_groupids, groupid);
		}

		zbx_db_free_result(result2);

		if (0 != hostgroupids.values_num)
		{
			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"delete from hosts_groups"
					" where hostid=" ZBX_FS_UI64
						" and",
					hostid);
			zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values,
					groupids->values_num);

			zbx_db_execute("%s", sql);

			zbx_audit_host_hostgroup_delete(hostid, hostname, &hostgroupids, &found_groupids);
		}

		zbx_vector_uint64_destroy(&found_groupids);
		zbx_vector_uint64_destroy(&hostgroupids);
	}

	zbx_free(sql);
out:
	zbx_free(hostname);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: link host with template                                           *
 *                                                                            *
 * Parameters: event           - [IN] source event data                       *
 *             cfg             - [IN] the global configuration data           *
 *             lnk_templateids - [IN] array of template IDs                   *
 *                                                                            *
 ******************************************************************************/
void	op_template_add(const zbx_db_event *event, zbx_config_t *cfg, zbx_vector_uint64_t *lnk_templateids)
{
	zbx_uint64_t	hostid;
	char		*error = NULL;
	int		status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 == (hostid = add_discovered_host(event, &status, cfg)))
		goto out;

	if (SUCCEED != zbx_db_copy_template_elements(hostid, lnk_templateids, ZBX_TEMPLATE_LINK_MANUAL, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot link template(s) %s", error);
		zbx_free(error);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlink and clear host from template                               *
 *                                                                            *
 * Parameters: event           - [IN] source event data                       *
 *             del_templateids - [IN] array of template IDs                   *
 *                                                                            *
 ******************************************************************************/
void	op_template_del(const zbx_db_event *event, zbx_vector_uint64_t *del_templateids)
{
	zbx_uint64_t	hostid;
	char		*error, *hostname = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 == (hostid = select_discovered_host(event, &hostname)))
		goto out;

	if (SUCCEED != zbx_db_delete_template_elements(hostid, hostname, del_templateids, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot unlink template: %s", error);
		zbx_free(error);
	}
out:
	zbx_free(hostname);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
