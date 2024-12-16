/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "operations.h"

#include "audit/zbxaudit.h"
#include "audit/zbxaudit_host.h"
#include "zbxdbwrap.h"
#include "zbxcomms.h"
#include "zbxdb.h"
#include "zbxexpr.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxinterface.h"
#include "zbx_availability_constants.h"
#include "zbx_host_constants.h"
#include "zbx_discoverer_constants.h"
#include "../server_constants.h"

typedef enum
{
	ZBX_DISCOVERY_UNSPEC = 0,
	ZBX_DISCOVERY_DNS,
	ZBX_DISCOVERY_IP,
	ZBX_DISCOVERY_VALUE
}
zbx_dcheck_source_t;

typedef enum
{
	ZBX_OP_HOST_TAGS_ADD,
	ZBX_OP_HOST_TAGS_DEL
}
zbx_host_tag_op_t;

/******************************************************************************
 *                                                                            *
 * Purpose: selects hostid of discovered host                                 *
 *                                                                            *
 * Parameters: event          - [IN] source event data                        *
 *             hostname       - [OUT] hostname where event occurred           *
 *                                                                            *
 * Return value: hostid - existing hostid,                                    *
 *                    0 - if not found                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	select_discovered_host(const zbx_db_event *event, char **hostname)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	hostid = 0, proxyid;
	char		*sql = NULL, *ip_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64, __func__, event->eventid);

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
		case EVENT_OBJECT_DSERVICE:
			result = zbx_db_select(
					"select dr.proxyid,ds.ip"
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

			ZBX_DBROW2UINT64(proxyid, row[0]);
			ip_esc = zbx_db_dyn_escape_string(row[1]);
			zbx_db_free_result(result);

			sql = zbx_dsprintf(sql,
					"select h.hostid,h.name"
					" from hosts h,interface i"
					" where h.hostid=i.hostid"
						" and i.ip='%s'"
						" and i.useip=1"
						" and h.status in (%d,%d)"
						" and h.proxyid%s"
					" order by i.hostid",
					ip_esc,
					HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
					zbx_db_sql_id_cmp(proxyid));

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
 * Purpose: adds group to host if not added already                           *
 *                                                                            *
 * Parameters: hostid         - [IN]                                          *
 *             groupids       - [IN]                                          *
 *             event          - [IN]  (for audit context)                     *
 *                                                                            *
 ******************************************************************************/
static void	add_discovered_host_groups(zbx_uint64_t hostid, zbx_vector_uint64_t *groupids,
		const zbx_db_event *event)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_uint64_t		groupid;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset = 0;

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
		int	i;

		ZBX_STR2UINT64(groupid, row[0]);

		if (FAIL == (i = zbx_vector_uint64_search(groupids, groupid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_uint64_remove_noorder(groupids, i);
	}
	zbx_db_free_result(result);

	if (0 < groupids->values_num)
		zbx_host_groups_add(hostid, groupids, zbx_map_db_event_to_audit_context(event));

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: find host monitoring settings by the data source                  *
 *                                                                            *
 * Parameters: proxyid           - [IN] proxyid of the data source            *
 *             new_proxyid       - [OUT]                                      *
 *             new_proxy_groupid - [OUT]                                      *
 *                                                                            *
 * Return value: The host monitored_by setting (see HOST_MONITORED_BY_*       *
 *               defines).                                                    *
 *                                                                            *
 * Comments: This function is used to determine the entity the host is        *
 *           monitored by (server, proxy or proxy group) since both - proxy   *
 *           and proxy group - will have proxy as data source.                *
 *                                                                            *
 ******************************************************************************/
static unsigned char	get_host_monitored_by(zbx_uint64_t src_proxyid, zbx_uint64_t *proxyid,
		zbx_uint64_t *proxy_groupid)
{
	if (0 == (*proxy_groupid = zbx_dc_get_proxy_groupid(src_proxyid)))
	{
		if (0 == (*proxyid = src_proxyid))
			return HOST_MONITORED_BY_SERVER;

		return HOST_MONITORED_BY_PROXY;
	}

	*proxyid = 0;

	return HOST_MONITORED_BY_PROXY_GROUP;
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds discovered host if it was not added already                  *
 *                                                                            *
 * Parameters: event          - [IN] source event                             *
 *             status         - [OUT] found or created host status            *
 *             cfg            - [IN] global configuration data                *
 *                                                                            *
 * Return value: hostid - new/existing hostid                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	add_discovered_host(const zbx_db_event *event, int *status, zbx_config_t *cfg)
{
	zbx_db_result_t		result, result2;
	zbx_db_row_t		row, row2;
	zbx_uint64_t		hostid = 0, proxyid, new_proxy_groupid;
	char			*host_visible, *hostname = NULL;
	unsigned short		port;
	zbx_vector_uint64_t	groupids;
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
					"select ds.dhostid,dr.proxyid,ds.ip,ds.dns,ds.port,dc.type,"
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
					"select ds.dhostid,dr.proxyid,ds.ip,ds.dns,ds.port,dc.type,"
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
			zbx_uint64_t	interfaceid, dhostid, druleid, new_proxyid;
			unsigned char	svc_type, interface_type, monitored_by;

			ZBX_STR2UINT64(dhostid, row[0]);
			ZBX_STR2UINT64(druleid, row[8]);
			ZBX_DBROW2UINT64(proxyid, row[1]);

			monitored_by = get_host_monitored_by(proxyid, &new_proxyid, &new_proxy_groupid);

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
				char	*sql = NULL;
				size_t	sql_alloc = 0, sql_offset = 0;

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"select distinct h.hostid,h.name,h.status"
						" from hosts h,interface i,dservices ds"
						" where h.hostid=i.hostid"
							" and i.ip=ds.ip"
							" and h.status in (%d,%d)"
							" and h.flags<>%d"
							" and h.monitored_by=%u"
							" and ds.dhostid=" ZBX_FS_UI64,
							HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
							ZBX_FLAG_DISCOVERY_PROTOTYPE, monitored_by, dhostid);

				if (HOST_MONITORED_BY_PROXY == monitored_by)
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and h.proxyid=" ZBX_FS_UI64,
							new_proxyid);
				}
				else if (HOST_MONITORED_BY_PROXY_GROUP == monitored_by)
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and h.proxy_groupid="
							ZBX_FS_UI64, new_proxy_groupid);
				}

				result2 = zbx_db_select("%s", sql);
				zbx_free(sql);

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
				zbx_db_result_t		result3;
				zbx_db_row_t		row3;
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
					if (SUCCEED == zbx_db_is_null(row3[0]) || '\0' == *row3[0])
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

				char	*host;

				if (ZBX_DISCOVERY_VALUE == host_source)
					host = zbx_strdup(NULL, row3[0]);
				else if (ZBX_DISCOVERY_IP == host_source || '\0' == *row[3])
					host = zbx_strdup(NULL, row[2]);
				else
					host = zbx_strdup(NULL, row[3]);

				zbx_db_free_result(result3);

				char	*host_unique;

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
					if (SUCCEED == zbx_db_is_null(row3[0]) || '\0' == *row3[0])
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

				zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "proxyid", "proxy_groupid", "host",
						"name", "monitored_by", (char *)NULL);
				zbx_db_insert_add_values(&db_insert, hostid, new_proxyid, new_proxy_groupid,
						host_unique, hostname, monitored_by);
				zbx_db_insert_execute(&db_insert);
				zbx_db_insert_clean(&db_insert);

				zbx_db_insert_prepare(&db_insert_host_rtdata, "host_rtdata", "hostid",
						"active_available", (char *)NULL);

				zbx_db_insert_add_values(&db_insert_host_rtdata, hostid,
						ZBX_INTERFACE_AVAILABLE_UNKNOWN);
				zbx_db_insert_execute(&db_insert_host_rtdata);
				zbx_db_insert_clean(&db_insert_host_rtdata);

				zbx_audit_host_create_entry(zbx_map_db_event_to_audit_context(event),
						ZBX_AUDIT_ACTION_ADD, hostid, hostname);

				if (HOST_INVENTORY_DISABLED != cfg->default_inventory_mode)
				{
					zbx_db_add_host_inventory(hostid, cfg->default_inventory_mode,
							zbx_map_db_event_to_audit_context(event));
				}

				zbx_audit_host_update_json_add_monitoring_and_hostname_and_inventory_mode(
						zbx_map_db_event_to_audit_context(event), hostid, monitored_by,
						new_proxyid, new_proxy_groupid, host_unique,
						cfg->default_inventory_mode);

				interfaceid = zbx_db_add_interface(hostid, interface_type, 1, row[2], row[3], port,
						ZBX_CONN_DEFAULT, zbx_map_db_event_to_audit_context(event));

				zbx_free(host_unique);

				add_discovered_host_groups(hostid, &groupids, event);
			}
			else
			{
				zbx_audit_host_create_entry(zbx_map_db_event_to_audit_context(event),
						ZBX_AUDIT_ACTION_UPDATE, hostid, hostname);
				interfaceid = zbx_db_add_interface(hostid, interface_type, 1, row[2], row[3], port,
						ZBX_CONN_DEFAULT, zbx_map_db_event_to_audit_context(event));
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
						hostid, zbx_map_db_event_to_audit_context(event));
			}
		}
		zbx_db_free_result(result);
	}
	else if (EVENT_OBJECT_ZABBIX_ACTIVE == event->object)
	{
		result = zbx_db_select(
				"select proxyid,host,listen_ip,listen_dns,listen_port,flags,tls_accepted"
				" from autoreg_host"
				" where autoreg_hostid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL != (row = zbx_db_fetch(result)))
		{
			char			*host_esc, *sql = NULL;
			zbx_uint64_t		host_proxyid, new_proxyid;
			zbx_conn_flags_t	flags;
			int			flags_int, tls_accepted;
			unsigned char		useip = 1, new_monitored_by;

			ZBX_DBROW2UINT64(proxyid, row[0]);

			new_monitored_by = get_host_monitored_by(proxyid, &new_proxyid, &new_proxy_groupid);

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
					"select hostid,proxyid,name,status,proxy_groupid,monitored_by"
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

					zbx_dc_get_autoregistration_psk(psk_identity, sizeof(psk_identity),
							(unsigned char *)psk, sizeof(psk));

					zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "proxyid", "proxy_groupid",
							"host", "name", "tls_connect", "tls_accept",
							"tls_psk_identity", "tls_psk", "monitored_by", (char *)NULL);
					zbx_db_insert_add_values(&db_insert, hostid, new_proxyid, new_proxy_groupid,
							hostname, hostname, tls_accepted, tls_accepted, psk_identity,
							psk, new_monitored_by);

					zbx_audit_host_create_entry(zbx_map_db_event_to_audit_context(event),
							ZBX_AUDIT_ACTION_ADD, hostid, hostname);
					zbx_audit_host_update_json_add_tls_and_psk(
							zbx_map_db_event_to_audit_context(event), hostid, tls_accepted,
							tls_accepted);
				}
				else
				{
					zbx_db_insert_prepare(&db_insert, "hosts", "hostid", "proxyid", "proxy_groupid",
							"host", "name", "monitored_by", (char *)NULL);

					zbx_audit_host_create_entry(zbx_map_db_event_to_audit_context(event),
							ZBX_AUDIT_ACTION_ADD, hostid, hostname);
					zbx_db_insert_add_values(&db_insert, hostid, new_proxyid, new_proxy_groupid,
							hostname, hostname, new_monitored_by);
				}

				zbx_db_insert_execute(&db_insert);
				zbx_db_insert_clean(&db_insert);

				zbx_db_insert_prepare(&db_insert_host_rtdata, "host_rtdata", "hostid",
						"active_available", (char *)NULL);

				zbx_db_insert_add_values(&db_insert_host_rtdata, hostid,
						ZBX_INTERFACE_AVAILABLE_UNKNOWN);
				zbx_db_insert_execute(&db_insert_host_rtdata);
				zbx_db_insert_clean(&db_insert_host_rtdata);

				if (HOST_INVENTORY_DISABLED != cfg->default_inventory_mode)
				{
					zbx_db_add_host_inventory(hostid, cfg->default_inventory_mode,
							zbx_map_db_event_to_audit_context(event));
				}

				zbx_audit_host_update_json_add_monitoring_and_hostname_and_inventory_mode(
						zbx_map_db_event_to_audit_context(event), hostid, new_monitored_by,
						new_proxyid, new_proxy_groupid, hostname, cfg->default_inventory_mode);

				zbx_db_add_interface(hostid, INTERFACE_TYPE_AGENT, useip, row[2], row[3], port, flags,
						zbx_map_db_event_to_audit_context(event));

				add_discovered_host_groups(hostid, &groupids, event);
			}
			else
			{
				zbx_uint64_t	proxy_groupid;
				unsigned char	monitored_by;

				ZBX_STR2UINT64(hostid, row2[0]);
				ZBX_DBROW2UINT64(host_proxyid, row2[1]);
				hostname = zbx_strdup(hostname, row2[2]);
				*status = atoi(row2[3]);
				ZBX_DBROW2UINT64(proxy_groupid, row2[4]);
				ZBX_STR2UCHAR(monitored_by, row2[5]);

				zbx_audit_host_create_entry(zbx_map_db_event_to_audit_context(event),
						ZBX_AUDIT_ACTION_UPDATE, hostid, hostname);

				if (host_proxyid != new_proxyid || proxy_groupid != new_proxy_groupid ||
						monitored_by != new_monitored_by)
				{
					char	delim = ' ';
					size_t	sql_alloc = 0, sql_offset = 0;

					sql_offset = 0;
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set");

					if (host_proxyid != new_proxyid)
					{
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
								"%cproxyid=%s", delim,
								zbx_db_sql_id_ins(new_proxyid));
						delim = ',';

						zbx_audit_host_update_json_update_proxyid(
								zbx_map_db_event_to_audit_context(event), hostid,
								host_proxyid, new_proxyid);
					}

					if (proxy_groupid != new_proxy_groupid)
					{
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
								"%cproxy_groupid=%s", delim,
								zbx_db_sql_id_ins(new_proxy_groupid));
						delim = ',';

						zbx_audit_host_update_json_update_proxy_groupid(
								zbx_map_db_event_to_audit_context(event), hostid,
								proxy_groupid, new_proxy_groupid);
					}

					if (monitored_by != new_monitored_by)
					{
						zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
								"%cmonitored_by=%d", delim, (int)new_monitored_by);

						zbx_audit_host_update_json_update_monitored_by(
								zbx_map_db_event_to_audit_context(event), hostid,
								(int)monitored_by, (int)new_monitored_by);
					}

					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
							" where hostid=" ZBX_FS_UI64, hostid);

					(void)zbx_db_execute("%s", sql);
					zbx_free(sql);
				}

				zbx_db_add_interface(hostid, INTERFACE_TYPE_AGENT, useip, row[2], row[3], port, flags,
						zbx_map_db_event_to_audit_context(event));
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
 * Purpose: checks if event is discovery or autoregistration event            *
 *                                                                            *
 * Parameters: event - [IN] source event data                                 *
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
 * Purpose: auxiliary function for op_add_del_tags()                          *
 *                                                                            *
 * Parameters: op        - [IN] operation type: add or delete                 *
 *             optagids  - [IN] operation tag IDs to add or delete            *
 *             host_tags - [IN/OUT]                                           *
 *                                                                            *
 ******************************************************************************/
static void	discovered_host_tags_add_del(zbx_host_tag_op_t op, zbx_vector_uint64_t *optagids,
		zbx_vector_db_tag_ptr_t *host_tags)
{
	size_t			sql_alloc = 0, sql_offset = 0;
	char			*sql = NULL;
	zbx_vector_db_tag_ptr_t	optags;
	zbx_db_result_t		result;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_db_tag_ptr_create(&optags);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select tag,value"
			" from optag"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "optagid", optagids->values, optagids->values_num);

	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_db_tag_t	*new_tag = zbx_db_tag_create(row[0], row[1]);

		zbx_vector_db_tag_ptr_append(&optags, new_tag);
	}

	zbx_db_free_result(result);

	if (ZBX_OP_HOST_TAGS_ADD == op)
		zbx_add_tags(host_tags, &optags);
	else
		zbx_del_tags(host_tags, &optags);

	zbx_vector_db_tag_ptr_clear_ext(&optags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&optags);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/********************************************************************************
 *                                                                              *
 * Purpose: auxiliary function for op_add_del_tags()                            *
 *                                                                              *
 * Parameters: hostid    - [IN] discovered host ID                              *
 *             host_tags - [IN] new state of host tags to save if not saved yet *
 *             event     - [IN]                                                 *
 *                                                                              *
 *******************************************************************************/
static void	discovered_host_tags_save(zbx_uint64_t hostid, zbx_vector_db_tag_ptr_t *host_tags,
		const zbx_db_event *event)
{
	int			new_tags_cnt = 0, res = SUCCEED;
	zbx_vector_db_tag_ptr_t	upd_tags;
	zbx_vector_uint64_t	del_tagids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_db_tag_ptr_create(&upd_tags);
	zbx_vector_uint64_create(&del_tagids);

	for (int i = 0; i < host_tags->values_num; i++)
	{
		zbx_db_tag_t	*tag = host_tags->values[i];

		if (0 == tag->tagid)
			new_tags_cnt++;
		else if (ZBX_FLAG_DB_TAG_REMOVE == tag->flags)
			zbx_vector_uint64_append(&del_tagids, tag->tagid);
	}

	if (0 != new_tags_cnt)
	{
		zbx_uint64_t	first_hosttagid, hosttagid;
		zbx_db_insert_t	db_insert_tag;

		hosttagid = first_hosttagid = zbx_db_get_maxid_num("host_tag", new_tags_cnt);

		zbx_db_insert_prepare(&db_insert_tag, "host_tag", "hosttagid", "hostid", "tag", "value", "automatic",
				(char *)NULL);

		for (int i = 0; i < host_tags->values_num; i++)
		{
			zbx_db_tag_t	*tag = host_tags->values[i];

			if (0 == tag->tagid)
			{
				zbx_db_insert_add_values(&db_insert_tag, hosttagid, hostid, tag->tag, tag->value,
						ZBX_DB_TAG_NORMAL);
				hosttagid++;
			}
		}

		if (SUCCEED != (res = zbx_db_insert_execute(&db_insert_tag)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to add tags to discovered host, hostid = " ZBX_FS_UI64,
					hostid);
		}
		else
		{
			hosttagid = first_hosttagid;

			for (int i = 0; i < host_tags->values_num; i++)
			{
				zbx_db_tag_t	*tag = host_tags->values[i];

				if (0 == tag->tagid)
				{
					zbx_audit_host_update_json_add_tag(zbx_map_db_event_to_audit_context(event),
							hostid, hosttagid, tag->tag, tag->value, ZBX_DB_TAG_NORMAL);
					hosttagid++;
				}
			}
		}

		zbx_db_insert_clean(&db_insert_tag);
	}

	if (SUCCEED == res && 0 != del_tagids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = ZBX_KIBIBYTE, sql_offset = 0;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from host_tag where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hosttagid", del_tagids.values,
				del_tagids.values_num);

		if (ZBX_DB_OK > zbx_db_execute("%s", sql))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to delete tags from a discovered host, hostid = "
					ZBX_FS_UI64, hostid);
		}
		else
		{
			for (int i = 0; i < host_tags->values_num; i++)
			{
				zbx_db_tag_t	*tag = host_tags->values[i];

				if (ZBX_FLAG_DB_TAG_REMOVE == tag->flags)
				{
					zbx_audit_host_update_json_delete_tag(zbx_map_db_event_to_audit_context(event),
							hostid, tag->tagid);
				}
			}
		}

		zbx_free(sql);
	}

	zbx_vector_db_tag_ptr_destroy(&upd_tags);
	zbx_vector_uint64_destroy(&del_tagids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds discovered host                                              *
 *                                                                            *
 * Parameters: event - [IN] source event data                                 *
 *             cfg   - [IN] global configuration data                         *
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
 * Purpose: deletes host                                                      *
 *                                                                            *
 * Parameters: event - [IN] source event data                                 *
 *                                                                            *
 ******************************************************************************/
void	op_host_del(const zbx_db_event *event)
{
	zbx_vector_uint64_t	hostids;
	zbx_vector_str_t	hostnames;
	zbx_uint64_t		hostid;
	char			*hostname = NULL, *hostname_esc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 == (hostid = select_discovered_host(event, &hostname)))
		goto out;

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_append(&hostids, hostid);
	zbx_vector_str_create(&hostnames);
	zbx_vector_str_append(&hostnames, zbx_strdup(NULL, hostname));

	zbx_db_delete_hosts_with_prototypes(&hostids, &hostnames, zbx_map_db_event_to_audit_context(event));
	hostname_esc = zbx_db_dyn_escape_string(hostname);
	zbx_db_execute("delete from autoreg_host where host='%s'", hostname_esc);

	zbx_vector_str_clear_ext(&hostnames, zbx_str_free);
	zbx_vector_str_destroy(&hostnames);
	zbx_vector_uint64_destroy(&hostids);

	zbx_audit_host_del(zbx_map_db_event_to_audit_context(event), hostid, hostname);
out:
	zbx_free(hostname);
	zbx_free(hostname_esc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enables discovered hosts                                          *
 *                                                                            *
 * Parameters: event - [IN] source event                                      *
 *             cfg   - [IN] global configuration data                         *
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

		zbx_audit_host_update_json_update_host_status(zbx_map_db_event_to_audit_context(event), hostid, status,
				HOST_STATUS_MONITORED);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: disables host                                                     *
 *                                                                            *
 * Parameters: event - [IN] source event                                      *
 *             cfg   - [IN] global configuration data                         *
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
		char		*sql;
		zbx_db_result_t	result;

		zbx_db_execute(
				"update hosts"
				" set status=%d"
				" where hostid=" ZBX_FS_UI64,
				HOST_STATUS_NOT_MONITORED, hostid);
		zbx_audit_host_update_json_update_host_status(zbx_map_db_event_to_audit_context(event), hostid, status,
				HOST_STATUS_NOT_MONITORED);

		sql = zbx_dsprintf(NULL, "select null"
				" from host_discovery"
				" where disable_source=%d"
					" and hostid=" ZBX_FS_UI64,
				ZBX_DISABLE_SOURCE_LLD_LOST, hostid);

		result = zbx_db_select_n(sql, 1);
		zbx_free(sql);

		if (NULL != zbx_db_fetch(result))
		{
			zbx_db_execute("update host_discovery"
					" set disable_source=%d"
					" where hostid=" ZBX_FS_UI64,
					ZBX_DISABLE_SOURCE_DEFAULT, hostid);
		}
		zbx_db_free_result(result);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets host inventory mode                                          *
 *                                                                            *
 * Parameters: event          - [IN] source event                             *
 *             cfg            - [IN] global configuration data                *
 *             inventory_mode - [IN] new inventory mode, see                  *
 *                                   HOST_INVENTORY_ defines                  *
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

	zbx_db_set_host_inventory(hostid, inventory_mode, zbx_map_db_event_to_audit_context(event));
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds groups to discovered host                                    *
 *                                                                            *
 * Parameters: event    - [IN] source event data                              *
 *             cfg      - [IN] global configuration data                      *
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

	add_discovered_host_groups(hostid, groupids, event);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes groups from discovered host                               *
 *                                                                            *
 * Parameters: event    - [IN] source event data                              *
 *             groupids - [IN] IDs of groups to delete                        *
 *                                                                            *
 ******************************************************************************/
void	op_groups_del(const zbx_db_event *event, zbx_vector_uint64_t *groupids)
{
	zbx_db_result_t	result;
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
		zbx_db_result_t		result2;
		zbx_db_row_t		row;

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
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "groupid", groupids->values,
				groupids->values_num);

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
			zbx_host_groups_remove(hostid, &hostgroupids);
			zbx_audit_host_hostgroup_delete(zbx_map_db_event_to_audit_context(event), hostid, hostname,
					&hostgroupids, &found_groupids);
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
 * Purpose: links host with template                                          *
 *                                                                            *
 * Parameters: event           - [IN] source event data                       *
 *             cfg             - [IN] global configuration data               *
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

	if (SUCCEED != zbx_db_copy_template_elements(hostid, lnk_templateids, ZBX_TEMPLATE_LINK_MANUAL,
			zbx_map_db_event_to_audit_context(event), &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot link template(s) %s", error);
		zbx_free(error);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlinks and clears host from template                             *
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

	if (SUCCEED != zbx_db_delete_template_elements(hostid, hostname, del_templateids,
			zbx_map_db_event_to_audit_context(event), &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot unlink template: %s", error);
		zbx_free(error);
	}
out:
	zbx_free(hostname);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds and deletes tags from discovered host if they are not        *
 *          already added/deleted                                             *
 *                                                                            *
 * Parameters: event           - [IN] source event data                       *
 *             cfg             - [IN] global configuration data               *
 *             new_optagids    - [IN]                                         *
 *             del_optagids    - [IN]                                         *
 *                                                                            *
 ******************************************************************************/
void	op_add_del_tags(const zbx_db_event *event, zbx_config_t *cfg, zbx_vector_uint64_t *new_optagids,
		zbx_vector_uint64_t *del_optagids)
{
	zbx_uint64_t		hostid = 0;
	int			status;
	char			*hostname = NULL;
	zbx_vector_db_tag_ptr_t	host_tags;
	zbx_db_result_t		result;
	zbx_db_row_t		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == is_discovery_or_autoregistration(event))
		goto out;

	if (0 != new_optagids->values_num)
		hostid = add_discovered_host(event, &status, cfg);
	else
		hostid = select_discovered_host(event, &hostname);

	if (0 == hostid)
		goto out;

	zbx_vector_db_tag_ptr_create(&host_tags);

	result = zbx_db_select(
			"select hosttagid,tag,value,automatic"
			" from host_tag"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_db_tag_t	*host_tag = zbx_db_tag_create(row[1], row[2]);

		ZBX_DBROW2UINT64(host_tag->tagid, row[0]);
		host_tag->automatic = atoi(row[3]);
		zbx_vector_db_tag_ptr_append(&host_tags, host_tag);
	}

	zbx_db_free_result(result);

	if (0 != new_optagids->values_num)
		discovered_host_tags_add_del(ZBX_OP_HOST_TAGS_ADD, new_optagids, &host_tags);

	if (0 != del_optagids->values_num)
		discovered_host_tags_add_del(ZBX_OP_HOST_TAGS_DEL, del_optagids, &host_tags);

	discovered_host_tags_save(hostid, &host_tags, event);

	zbx_vector_db_tag_ptr_clear_ext(&host_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&host_tags);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

int	zbx_map_db_event_to_audit_context(const zbx_db_event *event)
{
	if (EVENT_SOURCE_AUTOREGISTRATION == event->source)
	{
		return ZBX_AUDIT_AUTOREGISTRATION_CONTEXT;
	}
	else if (EVENT_SOURCE_DISCOVERY == event->source)
	{
		return ZBX_AUDIT_NETWORK_DISCOVERY_CONTEXT;
	}

	return ZBX_AUDIT_ALL_CONTEXT;
}
