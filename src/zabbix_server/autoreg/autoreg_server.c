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

#include "zbxcacheconfig.h"
#include "autoreg_server.h"
#include "zbx_host_constants.h"
#include "zbx_trigger_constants.h"
#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxautoreg.h"
#include "zbxalgo.h"

void	zbx_autoreg_host_free_server(zbx_autoreg_host_t *autoreg_host)
{
	zbx_free(autoreg_host->host);
	zbx_free(autoreg_host->ip);
	zbx_free(autoreg_host->dns);
	zbx_free(autoreg_host->host_metadata);
	zbx_free(autoreg_host);
}

static void	autoreg_get_hosts_server(zbx_vector_autoreg_host_ptr_t *autoreg_hosts, zbx_vector_str_t *hosts)
{
	for (int i = 0; i < autoreg_hosts->values_num; i++)
	{
		zbx_autoreg_host_t	*autoreg_host = autoreg_hosts->values[i];

		zbx_vector_str_append(hosts, autoreg_host->host);
	}
}

static void	autoreg_process_hosts_server(zbx_vector_autoreg_host_ptr_t *autoreg_hosts, const zbx_dc_proxy_t *proxy)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_str_t	hosts;
	zbx_uint64_t		current_proxyid, current_proxy_groupid;
	unsigned char		current_monitored_by, monitored_by;
	char			*sql = NULL;
	size_t			sql_alloc = 512, sql_offset;
	zbx_autoreg_host_t	*autoreg_host;
	sql = (char *)zbx_malloc(sql, sql_alloc);
	zbx_vector_str_create(&hosts);

	autoreg_get_hosts_server(autoreg_hosts, &hosts);

	/* delete from vector if already exist in hosts table */
	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select h.host,h.hostid,h.proxyid,a.host_metadata,a.listen_ip,a.listen_dns,"
				"a.listen_port,a.flags,a.autoreg_hostid,"
				"a.proxyid,h.monitored_by,h.proxy_groupid"
			" from hosts h"
			" left join autoreg_host a"
				" on a.host=h.host"
			" where");
	zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "h.host",
			(const char * const *)hosts.values, hosts.values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		for (int i = 0; i < autoreg_hosts->values_num; i++)
		{
			autoreg_host = autoreg_hosts->values[i];

			if (0 != strcmp(autoreg_host->host, row[0]))
				continue;

			if (SUCCEED == zbx_db_is_null(row[8]))
				break;

			ZBX_STR2UINT64(autoreg_host->autoreg_hostid, row[8]);
			ZBX_STR2UINT64(autoreg_host->hostid, row[1]);

			if (0 != strcmp(autoreg_host->host_metadata, row[3]) ||
					autoreg_host->flag != atoi(row[7]))
				break;

			/* process with autoregistration if the connection type was forced and */
			/* is different from the last registered connection type               */
			if (ZBX_CONN_DEFAULT != autoreg_host->flag)
			{
				unsigned short	port;

				if (FAIL == zbx_is_ushort(row[6], &port) || port != autoreg_host->port)
					break;

				if (ZBX_CONN_IP == autoreg_host->flag && 0 != strcmp(row[4], autoreg_host->ip))
					break;

				if (ZBX_CONN_DNS == autoreg_host->flag && 0 != strcmp(row[5],
						autoreg_host->dns))
				{
					break;
				}
			}

			ZBX_STR2UCHAR(current_monitored_by, row[10]);

			if (NULL == proxy)
			{
				if (HOST_MONITORED_BY_SERVER != current_monitored_by)
					break;
			}
			else
			{
				if (0 != proxy->proxy_groupid)
					monitored_by = HOST_MONITORED_BY_PROXY_GROUP;
				else
					monitored_by = HOST_MONITORED_BY_PROXY;

				if (monitored_by != current_monitored_by)
					break;

				if (monitored_by == HOST_MONITORED_BY_PROXY)
				{
					ZBX_DBROW2UINT64(current_proxyid, row[2]);
					if (current_proxyid != proxy->proxyid)
						break;
				}
				else if (monitored_by == HOST_MONITORED_BY_PROXY_GROUP)
				{
					ZBX_DBROW2UINT64(current_proxy_groupid, row[11]);

					if (current_proxy_groupid != proxy->proxy_groupid)
						break;
				}

				ZBX_DBROW2UINT64(current_proxyid, row[9]);

				if (current_proxyid != proxy->proxyid)
				{
					autoreg_host->autoreg_flags |= ZBX_AUTOREG_FLAGS_SKIP_EVENT;
					break;
				}
			}

			zbx_vector_autoreg_host_ptr_remove(autoreg_hosts, i);
			zbx_autoreg_host_free_server(autoreg_host);

			break;
		}

	}

	zbx_db_free_result(result);
	hosts.values_num = 0;
	zbx_vector_str_destroy(&hosts);
	zbx_free(sql);
}

static int	compare_autoreg_host_by_hostid(const void *d1, const void *d2)
{
	const zbx_autoreg_host_t	*p1 = *(const zbx_autoreg_host_t * const *)d1;
	const zbx_autoreg_host_t	*p2 = *(const zbx_autoreg_host_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->hostid, p2->hostid);

	return 0;
}

void	zbx_autoreg_flush_hosts_server(zbx_vector_autoreg_host_ptr_t *autoreg_hosts, const zbx_dc_proxy_t *proxy,
		const zbx_events_funcs_t *events_cbs)
{
	zbx_autoreg_host_t	*autoreg_host;
	zbx_uint64_t		autoreg_hostid = 0;
	zbx_db_insert_t		db_insert;
	int			create = 0, update = 0;
	char			*sql = NULL, *ip_esc, *dns_esc, *host_metadata_esc;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_timespec_t		ts = {0, 0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	autoreg_process_hosts_server(autoreg_hosts, proxy);

	for (int i = 0; i < autoreg_hosts->values_num; i++)
	{
		autoreg_host = autoreg_hosts->values[i];

		if (0 == autoreg_host->autoreg_hostid)
			create++;
	}

	if (0 != create)
	{
		autoreg_hostid = zbx_db_get_maxid_num("autoreg_host", create);

		zbx_db_insert_prepare(&db_insert, "autoreg_host", "autoreg_hostid", "proxyid", "host", "listen_ip",
				"listen_dns", "listen_port", "tls_accepted", "host_metadata", "flags", (char *)NULL);
	}

	if (0 != (update = autoreg_hosts->values_num - create))
	{
		sql = (char *)zbx_malloc(sql, sql_alloc);
	}

	zbx_vector_autoreg_host_ptr_sort(autoreg_hosts, zbx_autoreg_host_compare_func);

	for (int i = 0; i < autoreg_hosts->values_num; i++)
	{
		zbx_uint64_t	proxyid;

		if (NULL != proxy)
			proxyid = proxy->proxyid;
		else
			proxyid = 0;

		autoreg_host = autoreg_hosts->values[i];

		if (0 == autoreg_host->autoreg_hostid)
		{
			autoreg_host->autoreg_hostid = autoreg_hostid++;

			zbx_db_insert_add_values(&db_insert, autoreg_host->autoreg_hostid, proxyid,
					autoreg_host->host, autoreg_host->ip, autoreg_host->dns,
					(int)autoreg_host->port, (int)autoreg_host->connection_type,
					autoreg_host->host_metadata, autoreg_host->flag);
		}
		else
		{
			ip_esc = zbx_db_dyn_escape_string(autoreg_host->ip);
			dns_esc = zbx_db_dyn_escape_string(autoreg_host->dns);
			host_metadata_esc = zbx_db_dyn_escape_string(autoreg_host->host_metadata);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update autoreg_host"
					" set listen_ip='%s',"
						"listen_dns='%s',"
						"listen_port=%hu,"
						"host_metadata='%s',"
						"tls_accepted='%u',"
						"flags=%hu,"
						"proxyid=%s"
					" where autoreg_hostid=" ZBX_FS_UI64 ";\n",
				ip_esc, dns_esc, autoreg_host->port, host_metadata_esc, autoreg_host->connection_type,
				autoreg_host->flag, zbx_db_sql_id_ins(proxyid), autoreg_host->autoreg_hostid);

			zbx_free(host_metadata_esc);
			zbx_free(dns_esc);
			zbx_free(ip_esc);
		}
	}

	if (0 != create)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != update)
	{
		zbx_db_execute("%s", sql);
		zbx_free(sql);
	}

	zbx_vector_autoreg_host_ptr_sort(autoreg_hosts, compare_autoreg_host_by_hostid);

	for (int i = 0; i < autoreg_hosts->values_num; i++)
	{
		autoreg_host = autoreg_hosts->values[i];

		if (0 != (autoreg_host->autoreg_flags & ZBX_AUTOREG_FLAGS_SKIP_EVENT))
		{
			break;
		}
		ts.sec = autoreg_host->now;

		if (NULL != events_cbs->add_event_cb)
		{
			events_cbs->add_event_cb(EVENT_SOURCE_AUTOREGISTRATION, EVENT_OBJECT_ZABBIX_ACTIVE,
					autoreg_host->autoreg_hostid, &ts, TRIGGER_VALUE_PROBLEM, NULL, NULL, NULL, 0,
					0, NULL, 0, NULL, 0, NULL, NULL, NULL);
		}
	}

	if (NULL != events_cbs->process_events_cb)
		events_cbs->process_events_cb(NULL, NULL, NULL);

	if (NULL != events_cbs->clean_events_cb)
		events_cbs->clean_events_cb();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_autoreg_prepare_host_server(zbx_vector_autoreg_host_ptr_t *autoreg_hosts, const char *host, const char *ip,
		const char *dns, unsigned short port, unsigned int connection_type, const char *host_metadata,
		unsigned short flag, int now)
{
	zbx_autoreg_host_t	*autoreg_host;

	for (int i = 0; i < autoreg_hosts->values_num; i++)	/* duplicate check */
	{
		autoreg_host = autoreg_hosts->values[i];

		if (0 == strcmp(host, autoreg_host->host))
		{
			zbx_vector_autoreg_host_ptr_remove(autoreg_hosts, i);
			zbx_autoreg_host_free_server(autoreg_host);
			break;
		}
	}

	autoreg_host = (zbx_autoreg_host_t *)zbx_malloc(NULL, sizeof(zbx_autoreg_host_t));
	autoreg_host->autoreg_hostid = autoreg_host->hostid = 0;
	autoreg_host->host = zbx_strdup(NULL, host);
	autoreg_host->ip = zbx_strdup(NULL, ip);
	autoreg_host->dns = zbx_strdup(NULL, dns);
	autoreg_host->port = port;
	autoreg_host->connection_type = connection_type;
	autoreg_host->host_metadata = zbx_strdup(NULL, host_metadata);
	autoreg_host->flag = flag;
	autoreg_host->now = now;
	autoreg_host->autoreg_flags = 0;

	zbx_vector_autoreg_host_ptr_append(autoreg_hosts, autoreg_host);
}

void	zbx_autoreg_update_host_server(const zbx_dc_proxy_t *proxy, const char *host, const char *ip, const char *dns,
		unsigned short port, unsigned int connection_type, const char *host_metadata, unsigned short flags,
		int clock, const zbx_events_funcs_t *events_cbs)
{
	zbx_vector_autoreg_host_ptr_t	autoreg_hosts;

	zbx_vector_autoreg_host_ptr_create(&autoreg_hosts);

	zbx_autoreg_prepare_host_server(&autoreg_hosts, host, ip, dns, port, connection_type, host_metadata, flags,
			clock);
	zbx_db_begin();
	zbx_autoreg_flush_hosts_server(&autoreg_hosts, proxy, events_cbs);
	zbx_db_commit();

	zbx_vector_autoreg_host_ptr_clear_ext(&autoreg_hosts, zbx_autoreg_host_free_server);
	zbx_vector_autoreg_host_ptr_destroy(&autoreg_hosts);
}
