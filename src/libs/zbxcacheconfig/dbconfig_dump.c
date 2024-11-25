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
#include "dbconfig.h"
#include "user_macro.h"

#include "zbx_host_constants.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbxinterface.h"

static void	DCdump_config(void)
{
	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_dc_config_t	*config = get_dc_config();

	if (NULL == config->config)
		goto out;

	zabbix_log(LOG_LEVEL_TRACE, "revision:" ZBX_FS_UI64, config->revision.config_table);
	zabbix_log(LOG_LEVEL_TRACE, "discovery_groupid:" ZBX_FS_UI64, config->config->discovery_groupid);
	zabbix_log(LOG_LEVEL_TRACE, "snmptrap_logging:%hhu", config->config->snmptrap_logging);
	zabbix_log(LOG_LEVEL_TRACE, "default_inventory_mode:%d", config->config->default_inventory_mode);

	zabbix_log(LOG_LEVEL_TRACE, "db:");
	zabbix_log(LOG_LEVEL_TRACE, "  extension: %s", config->config->db.extension);
	zabbix_log(LOG_LEVEL_TRACE, "  history_compression_status: %d",
			config->config->db.history_compression_status);
	zabbix_log(LOG_LEVEL_TRACE, "  history_compress_older: %d", config->config->db.history_compress_older);

	zabbix_log(LOG_LEVEL_TRACE, "autoreg_tls_accept:%hhu", config->config->autoreg_tls_accept);

	zabbix_log(LOG_LEVEL_TRACE, "severity names:");
	for (int i = 0; TRIGGER_SEVERITY_COUNT > i; i++)
		zabbix_log(LOG_LEVEL_TRACE, "  %s", config->config->severity_name[i]);

	zabbix_log(LOG_LEVEL_TRACE, "housekeeping:");
	zabbix_log(LOG_LEVEL_TRACE, "  events, mode:%u period:[trigger:%d internal:%d autoreg:%d discovery:%d]",
			config->config->hk.events_mode, config->config->hk.events_trigger,
			config->config->hk.events_internal, config->config->hk.events_autoreg,
			config->config->hk.events_discovery);

	zabbix_log(LOG_LEVEL_TRACE, "  audit, mode:%u period:%d", config->config->hk.audit_mode,
			config->config->hk.audit);

	zabbix_log(LOG_LEVEL_TRACE, "  it services, mode:%u period:%d", config->config->hk.services_mode,
			config->config->hk.services);

	zabbix_log(LOG_LEVEL_TRACE, "  user sessions, mode:%u period:%d", config->config->hk.sessions_mode,
			config->config->hk.sessions);

	zabbix_log(LOG_LEVEL_TRACE, "  history, mode:%u global:%u period:%d", config->config->hk.history_mode,
			config->config->hk.history_global, config->config->hk.history);

	zabbix_log(LOG_LEVEL_TRACE, "  trends, mode:%u global:%u period:%d", config->config->hk.trends_mode,
			config->config->hk.trends_global, config->config->hk.trends);

	zabbix_log(LOG_LEVEL_TRACE, "  default timezone '%s'", config->config->default_timezone);

	zabbix_log(LOG_LEVEL_TRACE, "  auditlog_enabled: %d", config->config->auditlog_enabled);
	zabbix_log(LOG_LEVEL_TRACE, "  auditlog_mode: %d", config->config->auditlog_mode);

	zabbix_log(LOG_LEVEL_TRACE, "item timeouts:");
	zabbix_log(LOG_LEVEL_TRACE, "  agent:%s", config->config->item_timeouts.agent);
	zabbix_log(LOG_LEVEL_TRACE, "  simple:%s", config->config->item_timeouts.simple);
	zabbix_log(LOG_LEVEL_TRACE, "  snmp:%s", config->config->item_timeouts.snmp);
	zabbix_log(LOG_LEVEL_TRACE, "  external:%s", config->config->item_timeouts.external);
	zabbix_log(LOG_LEVEL_TRACE, "  odbc:%s", config->config->item_timeouts.odbc);
	zabbix_log(LOG_LEVEL_TRACE, "  http:%s", config->config->item_timeouts.http);
	zabbix_log(LOG_LEVEL_TRACE, "  ssh:%s", config->config->item_timeouts.ssh);
	zabbix_log(LOG_LEVEL_TRACE, "  telnet:%s", config->config->item_timeouts.telnet);
	zabbix_log(LOG_LEVEL_TRACE, "  script:%s", config->config->item_timeouts.script);
	zabbix_log(LOG_LEVEL_TRACE, "  browser:%s", config->config->item_timeouts.browser);
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_hosts(void)
{
	ZBX_DC_HOST		*host;
	zbx_hashset_iter_t	iter;
	zbx_vector_ptr_t	index;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->hosts, &iter);

	while (NULL != (host = (ZBX_DC_HOST *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, host);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		int	j;

		host = (ZBX_DC_HOST *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "hostid:" ZBX_FS_UI64 " host:'%s' name:'%s' status:%u revision:" ZBX_FS_UI64,
				host->hostid, host->host, host->name, host->status, host->revision);

		zabbix_log(LOG_LEVEL_TRACE, "monitored_by:%u  proxyid:" ZBX_FS_UI64 " proxy_groupid:" ZBX_FS_UI64,
				host->monitored_by, host->proxyid, host->proxy_groupid);
		zabbix_log(LOG_LEVEL_TRACE, "  data_expected_from:%d", host->data_expected_from);

		zabbix_log(LOG_LEVEL_TRACE, "  maintenanceid:" ZBX_FS_UI64 " maintenance_status:%u maintenance_type:%u"
				" maintenance_from:%d", host->maintenanceid, host->maintenance_status,
				host->maintenance_type, host->maintenance_from);

		/* 'tls_connect' and 'tls_accept' must be respected even if encryption support is not compiled in */
		zabbix_log(LOG_LEVEL_TRACE, "  tls:[connect:%u accept:%u]", host->tls_connect, host->tls_accept);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zabbix_log(LOG_LEVEL_TRACE, "  tls:[issuer:'%s' subject:'%s']", host->tls_issuer, host->tls_subject);

		if (NULL != host->tls_dc_psk)
		{
			zabbix_log(LOG_LEVEL_TRACE, "  tls:[psk_identity:'%s' psk:'%s' dc_psk:%u]",
					host->tls_dc_psk->tls_psk_identity, host->tls_dc_psk->tls_psk,
					host->tls_dc_psk->refcount);
		}
#endif
		for (j = 0; j < host->interfaces_v.values_num; j++)
		{
			ZBX_DC_INTERFACE	*interface = (ZBX_DC_INTERFACE *)host->interfaces_v.values[j];

			zabbix_log(LOG_LEVEL_TRACE, "  interfaceid:" ZBX_FS_UI64, interface->interfaceid);
		}

		zabbix_log(LOG_LEVEL_TRACE, "  httptests:");
		for (j = 0; j < host->httptests.values_num; j++)
		{
			zabbix_log(LOG_LEVEL_TRACE, "    httptestid:" ZBX_FS_UI64,
					host->httptests.values[j]->httptestid);
		}

		zabbix_log(LOG_LEVEL_TRACE, "  items:");

		zbx_hashset_iter_t	item_iter;
		ZBX_DC_ITEM_REF		*ref;

		zbx_hashset_iter_reset(&host->items, &item_iter);
		while (NULL != (ref = (ZBX_DC_ITEM_REF *)zbx_hashset_iter_next(&item_iter)))
			zabbix_log(LOG_LEVEL_TRACE, "    itemid:" ZBX_FS_UI64, ref->item->itemid);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_autoreg_hosts(void)
{
	ZBX_DC_AUTOREG_HOST	*autoreg_host;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_hashset_iter_reset(&(get_dc_config())->autoreg_hosts, &iter);

	while (NULL != (autoreg_host = (ZBX_DC_AUTOREG_HOST *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, " host:'%s' listen_ip:'%s' listen_dns:'%s' host_metadata:'%s' flags:%d"
				" timestamp:%d listen_port:%u",
				autoreg_host->host, autoreg_host->listen_ip, autoreg_host->listen_dns,
				autoreg_host->host_metadata, autoreg_host->flags, autoreg_host->timestamp,
				autoreg_host->listen_port);
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_host_tags(void)
{
	zbx_dc_host_tag_t	*host_tag;
	zbx_dc_host_tag_index_t	*host_tag_index;
	zbx_hashset_iter_t	iter;
	zbx_vector_ptr_t	index;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->host_tags_index, &iter);

	while (NULL != (host_tag_index = (zbx_dc_host_tag_index_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, host_tag_index);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		int	j;

		host_tag_index = (zbx_dc_host_tag_index_t *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "hostid:" ZBX_FS_UI64,  host_tag_index->hostid);

		for (j = 0; j < host_tag_index->tags.values_num; j++)
		{
			host_tag = (zbx_dc_host_tag_t *)host_tag_index->tags.values[j];
			zabbix_log(LOG_LEVEL_TRACE, "  '%s':'%s'", host_tag->tag, host_tag->value);
		}
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_proxies(void)
{
	ZBX_DC_PROXY		*proxy;
	zbx_hashset_iter_t	iter;
	zbx_vector_ptr_t	index;
	int			i, j;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->proxies, &iter);

	while (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, proxy);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		proxy = (ZBX_DC_PROXY *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "proxyid:" ZBX_FS_UI64 " location:%u revision:" ZBX_FS_UI64, proxy->proxyid,
				proxy->location, proxy->revision);
		zabbix_log(LOG_LEVEL_TRACE, "  name:'%s'", proxy->name);
		zabbix_log(LOG_LEVEL_TRACE, "  mode:%d", proxy->mode);
		zabbix_log(LOG_LEVEL_TRACE, "  allowed_addresses:'%s'", proxy->allowed_addresses);
		zabbix_log(LOG_LEVEL_TRACE, "  lastaccess:%d", proxy->lastaccess);
		zabbix_log(LOG_LEVEL_TRACE, "  tls_connect:%d", proxy->tls_connect);
		zabbix_log(LOG_LEVEL_TRACE, "  tls_accept:%d", proxy->tls_accept);
		zabbix_log(LOG_LEVEL_TRACE, "  address:'%s'", proxy->address);
		zabbix_log(LOG_LEVEL_TRACE, "  port:'%s'", proxy->port);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zabbix_log(LOG_LEVEL_TRACE, "  tls_issuer:'%s'", proxy->tls_issuer);
		zabbix_log(LOG_LEVEL_TRACE, "  tls_subject:'%s'", proxy->tls_subject);

		if (NULL != proxy->tls_dc_psk)
		{
			zabbix_log(LOG_LEVEL_TRACE, "  tls:[psk_identity:'%s' psk:'%s' dc_psk:%u]",
					proxy->tls_dc_psk->tls_psk_identity, proxy->tls_dc_psk->tls_psk,
					proxy->tls_dc_psk->refcount);
		}
#endif

		zabbix_log(LOG_LEVEL_TRACE, "  hosts:%d", proxy->hosts.values_num);
		for (j = 0; j < proxy->hosts.values_num; j++)
			zabbix_log(LOG_LEVEL_TRACE, "    hostid:" ZBX_FS_UI64, proxy->hosts.values[j]->hostid);

		zabbix_log(LOG_LEVEL_TRACE, "  removed hosts:%d", proxy->removed_hosts.values_num);
		for (j = 0; j < proxy->removed_hosts.values_num; j++)
			zabbix_log(LOG_LEVEL_TRACE, "    hostid:" ZBX_FS_UI64 " revision:" ZBX_FS_UI64,
					proxy->removed_hosts.values[j].hostid, proxy->removed_hosts.values[j].revision);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_ipmihosts(void)
{
	ZBX_DC_IPMIHOST		*ipmihost;
	zbx_hashset_iter_t	iter;
	zbx_vector_ptr_t	index;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->ipmihosts, &iter);

	while (NULL != (ipmihost = (ZBX_DC_IPMIHOST *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, ipmihost);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		ipmihost = (ZBX_DC_IPMIHOST *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "hostid:" ZBX_FS_UI64 " ipmi:[username:'%s' password:'%s' authtype:%d"
				" privilege:%u]", ipmihost->hostid, ipmihost->ipmi_username, ipmihost->ipmi_password,
				ipmihost->ipmi_authtype, ipmihost->ipmi_privilege);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_host_inventories(void)
{
	ZBX_DC_HOST_INVENTORY	*host_inventory;
	zbx_hashset_iter_t	iter;
	zbx_vector_ptr_t	index;
	int			i, j;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->host_inventories, &iter);

	while (NULL != (host_inventory = (ZBX_DC_HOST_INVENTORY *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, host_inventory);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		host_inventory = (ZBX_DC_HOST_INVENTORY *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "hostid:" ZBX_FS_UI64 " inventory_mode:%u", host_inventory->hostid,
				host_inventory->inventory_mode);

		for (j = 0; j < HOST_INVENTORY_FIELD_COUNT; j++)
		{
			zabbix_log(LOG_LEVEL_TRACE, "  %s: '%s'", zbx_db_get_inventory_field(j + 1),
					host_inventory->values[j]);
		}
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "  End of %s()", __func__);
}

static void	DCdump_kvs_paths(void)
{
	zbx_dc_kvs_path_t	*kvs_path;
	zbx_dc_kv_t		*kvs;
	zbx_hashset_iter_t	iter;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	for (i = 0; i < get_dc_config()->kvs_paths.values_num; i++)
	{
		kvs_path = (zbx_dc_kvs_path_t *)(get_dc_config())->kvs_paths.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "kvs_path:'%s'", kvs_path->path);

		zbx_hashset_iter_reset(&kvs_path->kvs, &iter);
		while (NULL != (kvs = (zbx_dc_kv_t *)zbx_hashset_iter_next(&iter)))
		{
			int	j;

			zabbix_log(LOG_LEVEL_TRACE, "  key:'%s'", kvs->key);

			for (j = 0; j < kvs->macros.values_num; j++)
			{
				zabbix_log(LOG_LEVEL_TRACE, "    hostid:" ZBX_FS_UI64 " macroid:" ZBX_FS_UI64,
						kvs->macros.values[j].first, kvs->macros.values[j].second);
			}
		}
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_interfaces(void)
{
	ZBX_DC_INTERFACE	*interface;
	ZBX_DC_SNMPINTERFACE	*snmp;
	zbx_hashset_iter_t	iter;
	zbx_vector_ptr_t	index;
	char			*if_msg = NULL;
	size_t			alloc, offset;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->interfaces, &iter);

	while (NULL != (interface = (ZBX_DC_INTERFACE *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, interface);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		interface = (ZBX_DC_INTERFACE *)index.values[i];

		zbx_snprintf_alloc(&if_msg, &alloc, &offset, "interfaceid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64
				" ip:'%s' dns:'%s' port:'%s' type:%u main:%u useip:%u"
				" available:%u errors_from:%d disable_until:%d error:'%s' availability_ts:%d"
				" reset_availability:%d version:%d items_num %d",
				interface->interfaceid, interface->hostid, interface->ip, interface->dns,
				interface->port, interface->type, interface->main, interface->useip,
				interface->available, interface->errors_from, interface->disable_until,
				interface->error, interface->availability_ts, interface->reset_availability,
				interface->version, interface->items_num);

		if (INTERFACE_TYPE_SNMP == interface->type &&
				NULL != (snmp = (ZBX_DC_SNMPINTERFACE *)
				zbx_hashset_search(&(get_dc_config())->interfaces_snmp, &interface->interfaceid)))
		{
			zbx_snprintf_alloc(&if_msg, &alloc, &offset, "snmp:[bulk:%u snmp_type:%u community:'%s']",
					snmp->bulk, snmp->version, snmp->community);

			if (ZBX_IF_SNMP_VERSION_3 == snmp->version)
			{
				zbx_snprintf_alloc(&if_msg, &alloc, &offset," snmpv3:["
					"securityname:'%s' securitylevel:%u authprotocol:%u privprotocol:%u"
					" contextname:'%s']", snmp->securityname, snmp->securitylevel,
					snmp->authprotocol, snmp->privprotocol, snmp->contextname);
			}
		}

		zabbix_log(LOG_LEVEL_TRACE, "%s", if_msg);

		offset = 0;
	}

	zbx_free(if_msg);
	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_numitem(const ZBX_DC_NUMITEM *numitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  units:'%s' trends:%s", numitem->units, numitem->trends_period);
}

static void	DCdump_snmpitem(const ZBX_DC_SNMPITEM *snmpitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  snmp:[oid:'%s' oid_type:%u]", snmpitem->snmp_oid, snmpitem->snmp_oid_type);
}

static void	DCdump_ipmiitem(const ZBX_DC_IPMIITEM *ipmiitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  ipmi_sensor:'%s'", ipmiitem->ipmi_sensor);
}

static void	DCdump_trapitem(const ZBX_DC_TRAPITEM *trapitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  trapper_hosts:'%s'", trapitem->trapper_hosts);
}

static void	DCdump_logitem(ZBX_DC_LOGITEM *logitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  logtimefmt:'%s'", logitem->logtimefmt);
}

static void	DCdump_dbitem(const ZBX_DC_DBITEM *dbitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  db:[params:'%s' username:'%s' password:'%s']", dbitem->params,
			dbitem->username, dbitem->password);
}

static void	DCdump_sshitem(const ZBX_DC_SSHITEM *sshitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  ssh:[username:'%s' password:'%s' authtype:%u params:'%s']",
			sshitem->username, sshitem->password, sshitem->authtype, sshitem->params);
	zabbix_log(LOG_LEVEL_TRACE, "  ssh:[publickey:'%s' privatekey:'%s']", sshitem->publickey,
			sshitem->privatekey);
}

static void	DCdump_depitem(const ZBX_DC_DEPENDENTITEM *depitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  depitem:[last_master_itemid:" ZBX_FS_UI64 " master_itemid:"
			ZBX_FS_UI64"]", depitem->last_master_itemid, depitem->master_itemid);
}

static void	DCdump_httpitem(const ZBX_DC_HTTPITEM *httpitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  http:[url:'%s']", httpitem->url);
	zabbix_log(LOG_LEVEL_TRACE, "  http:[query fields:'%s']", httpitem->query_fields);
	zabbix_log(LOG_LEVEL_TRACE, "  http:[headers:'%s']", httpitem->headers);
	zabbix_log(LOG_LEVEL_TRACE, "  http:[posts:'%s']", httpitem->posts);

	zabbix_log(LOG_LEVEL_TRACE, "  http:[status codes:'%s' follow redirects:%u post type:%u http proxy:'%s'"
			" retrieve mode:%u request method:%u output format:%u allow traps:%u"
			" trapper_hosts:'%s']",
			httpitem->status_codes, httpitem->follow_redirects, httpitem->post_type,
			httpitem->http_proxy, httpitem->retrieve_mode, httpitem->request_method,
			httpitem->output_format, httpitem->allow_traps, httpitem->trapper_hosts);

	zabbix_log(LOG_LEVEL_TRACE, "  http:[username:'%s' password:'%s' authtype:%u]",
			httpitem->username, httpitem->password, httpitem->authtype);
	zabbix_log(LOG_LEVEL_TRACE, "  http:[publickey:'%s' privatekey:'%s' ssl key password:'%s' verify peer:%u"
			" verify host:%u]", httpitem->ssl_cert_file, httpitem->ssl_key_file, httpitem->ssl_key_password,
			httpitem->verify_peer, httpitem->verify_host);
}

static void	DCdump_scriptitem(const ZBX_DC_SCRIPTITEM *scriptitem)
{
	int	i;

	zabbix_log(LOG_LEVEL_TRACE, "  script:[script:'%s']", scriptitem->script);

	for (i = 0; i < scriptitem->params.values_num; i++)
	{
		zbx_dc_item_param_t	*params = (zbx_dc_item_param_t *)scriptitem->params.values[i];

		zabbix_log(LOG_LEVEL_TRACE, "      item_paramid:" ZBX_FS_UI64 " name: '%s' value:'%s'",
				params->item_script_paramid, params->name, params->value);
	}
}

static void	DCdump_browseritem(const ZBX_DC_BROWSERITEM *browseritem)
{
	int	i;

	zabbix_log(LOG_LEVEL_TRACE, "  browseritem:[script:'%s']", browseritem->script);

	for (i = 0; i < browseritem->params.values_num; i++)
	{
		zbx_dc_item_param_t	*params = (zbx_dc_item_param_t *)browseritem->params.values[i];

		zabbix_log(LOG_LEVEL_TRACE, "      item_paramid:" ZBX_FS_UI64 " name: '%s' value:'%s'",
				params->item_script_paramid, params->name, params->value);
	}
}

static void	DCdump_telnetitem(const ZBX_DC_TELNETITEM *telnetitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  telnet:[username:'%s' password:'%s' params:'%s']", telnetitem->username,
			telnetitem->password, telnetitem->params);
}

static void	DCdump_simpleitem(const ZBX_DC_SIMPLEITEM *simpleitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  simple:[username:'%s' password:'%s']", simpleitem->username,
			simpleitem->password);
}

static void	DCdump_jmxitem(const ZBX_DC_JMXITEM *jmxitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  jmx:[username:'%s' password:'%s' endpoint:'%s']",
			jmxitem->username, jmxitem->password, jmxitem->jmx_endpoint);
}

static void	DCdump_calcitem(const ZBX_DC_CALCITEM *calcitem)
{
	zabbix_log(LOG_LEVEL_TRACE, "  calc:[params:'%s']", calcitem->params);
}

static void	DCdump_masteritem(ZBX_DC_MASTERITEM *masteritem)
{
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		*pitemid;

	zabbix_log(LOG_LEVEL_TRACE, "  dependent:");

	zbx_hashset_iter_reset(&masteritem->dep_itemids, &iter);
	while (NULL != (pitemid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "    itemid:" ZBX_FS_UI64, *pitemid);
	}
}

static void	DCdump_preprocitem(const ZBX_DC_PREPROCITEM *preprocitem)
{
	int	i;

	zabbix_log(LOG_LEVEL_TRACE, "  preprocessing:");

	for (i = 0; i < preprocitem->preproc_ops.values_num; i++)
	{
		zbx_dc_preproc_op_t	*op = (zbx_dc_preproc_op_t *)preprocitem->preproc_ops.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "      opid:" ZBX_FS_UI64 " step:%d type:%u params:'%s'"
				" error_handler:%d error_handler_params:'%s'",
				op->item_preprocid, op->step, op->type, op->params, op->error_handler, op->error_handler_params);
	}
}

static void	DCdump_item_tags(const ZBX_DC_ITEM *item)
{
	int				i;
	zbx_vector_dc_item_tag_t	index;

	zbx_vector_dc_item_tag_create(&index);

	zbx_vector_dc_item_tag_append_array(&index, item->tags.values, item->tags.values_num);
	zbx_vector_dc_item_tag_sort(&index, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_TRACE, "  tags:");

	for (i = 0; i < index.values_num; i++)
	{
		zbx_dc_item_tag_t	*tag = (zbx_dc_item_tag_t *)&index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "      tagid:" ZBX_FS_UI64 " tag:'%s' value:'%s'",
				tag->itemtagid, tag->tag, tag->value);
	}

	zbx_vector_dc_item_tag_destroy(&index);
}

static void	DCdump_items(void)
{
	ZBX_DC_ITEM		*item;
	zbx_hashset_iter_t	iter;
	int			i, j;
	zbx_vector_ptr_t	index;
	zbx_dc_config_t		*config = get_dc_config();

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&config->items, &iter);

	while (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, item);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		item = (ZBX_DC_ITEM *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "itemid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " key:'%s' revision:" ZBX_FS_UI64,
				item->itemid, item->hostid, item->key, item->revision);
		zabbix_log(LOG_LEVEL_TRACE, "  type:%u value_type:%u", item->type, item->value_type);
		zabbix_log(LOG_LEVEL_TRACE, "  interfaceid:" ZBX_FS_UI64, item->interfaceid);
		zabbix_log(LOG_LEVEL_TRACE, "  state:%u error:'%s'", item->state, item->error);
		zabbix_log(LOG_LEVEL_TRACE, "  flags:%u status:%u", item->flags, item->status);
		zabbix_log(LOG_LEVEL_TRACE, "  valuemapid:" ZBX_FS_UI64, item->valuemapid);
		zabbix_log(LOG_LEVEL_TRACE, "  lastlogsize:" ZBX_FS_UI64 " mtime:%d", item->lastlogsize, item->mtime);
		zabbix_log(LOG_LEVEL_TRACE, "  delay:'%s' nextcheck:%d", item->delay, item->nextcheck);
		zabbix_log(LOG_LEVEL_TRACE, "  data_expected_from:%d", item->data_expected_from);
		zabbix_log(LOG_LEVEL_TRACE, "  history:%s", item->history_period);
		zabbix_log(LOG_LEVEL_TRACE, "  poller_type:%u location:%u", item->poller_type, item->location);
		zabbix_log(LOG_LEVEL_TRACE, "  inventory_link:%u", item->inventory_link);
		zabbix_log(LOG_LEVEL_TRACE, "  priority:%u", item->queue_priority);

		switch ((zbx_item_value_type_t)item->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
				DCdump_numitem(item->itemvaluetype.numitem);
				break;
			case ITEM_VALUE_TYPE_LOG:
				if (NULL != item->itemvaluetype.logitem)
					DCdump_logitem(item->itemvaluetype.logitem);
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
			case ITEM_VALUE_TYPE_BIN:
			case ITEM_VALUE_TYPE_NONE:
				break;
		}

		switch ((zbx_item_type_t)item->type)
		{
			case ITEM_TYPE_ZABBIX:
				break;
			case ITEM_TYPE_TRAPPER:
				if (NULL != item->itemtype.trapitem)
					DCdump_trapitem(item->itemtype.trapitem);
				break;
			case ITEM_TYPE_SIMPLE:
				DCdump_simpleitem(item->itemtype.simpleitem);
				break;
			case ITEM_TYPE_INTERNAL:
				break;
			case ITEM_TYPE_ZABBIX_ACTIVE:
				break;
			case ITEM_TYPE_HTTPTEST:
				break;
			case ITEM_TYPE_EXTERNAL:
				break;
			case ITEM_TYPE_DB_MONITOR:
				DCdump_dbitem(item->itemtype.dbitem);
				break;
			case ITEM_TYPE_IPMI:
				DCdump_ipmiitem(item->itemtype.ipmiitem);
				break;
			case ITEM_TYPE_SSH:
				DCdump_sshitem(item->itemtype.sshitem);
				break;
			case ITEM_TYPE_TELNET:
				DCdump_telnetitem(item->itemtype.telnetitem);
				break;
			case ITEM_TYPE_CALCULATED:
				DCdump_calcitem(item->itemtype.calcitem);
				break;
			case ITEM_TYPE_JMX:
				DCdump_jmxitem(item->itemtype.jmxitem);
				break;
			case ITEM_TYPE_SNMPTRAP:
				break;
			case ITEM_TYPE_DEPENDENT:
				DCdump_depitem(item->itemtype.depitem);
				break;
			case ITEM_TYPE_HTTPAGENT:
				DCdump_httpitem(item->itemtype.httpitem);
				break;
			case ITEM_TYPE_SNMP:
				DCdump_snmpitem(item->itemtype.snmpitem);
				break;
			case ITEM_TYPE_SCRIPT:
				DCdump_scriptitem(item->itemtype.scriptitem);
				break;
			case ITEM_TYPE_BROWSER:
				DCdump_browseritem(item->itemtype.browseritem);
				break;
		}

		if (NULL != item->master_item)
			DCdump_masteritem(item->master_item);

		if (NULL != item->preproc_item)
			DCdump_preprocitem(item->preproc_item);

		if (0 != item->tags.values_num)
			DCdump_item_tags(item);

		if (NULL != item->triggers)
		{
			ZBX_DC_TRIGGER	*trigger;

			zabbix_log(LOG_LEVEL_TRACE, "  triggers:");

			for (j = 0; NULL != (trigger = item->triggers[j]); j++)
				zabbix_log(LOG_LEVEL_TRACE, "    triggerid:" ZBX_FS_UI64, trigger->triggerid);
		}
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_interface_snmpitems(void)
{
	ZBX_DC_INTERFACE_ITEM	*interface_snmpitem;
	zbx_hashset_iter_t	iter;
	int			i, j;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->interface_snmpitems, &iter);

	while (NULL != (interface_snmpitem = (ZBX_DC_INTERFACE_ITEM *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, interface_snmpitem);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		interface_snmpitem = (ZBX_DC_INTERFACE_ITEM *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "interfaceid:" ZBX_FS_UI64, interface_snmpitem->interfaceid);

		for (j = 0; j < interface_snmpitem->itemids.values_num; j++)
			zabbix_log(LOG_LEVEL_TRACE, "  itemid:" ZBX_FS_UI64, interface_snmpitem->itemids.values[j]);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_template_items(void)
{
	ZBX_DC_TEMPLATE_ITEM	*template_item;
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->template_items, &iter);

	while (NULL != (template_item = (ZBX_DC_TEMPLATE_ITEM *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, template_item);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		template_item = (ZBX_DC_TEMPLATE_ITEM *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "itemid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " templateid:" ZBX_FS_UI64,
				template_item->itemid, template_item->hostid, template_item->templateid);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_item_discovery(void)
{
	ZBX_DC_ITEM_DISCOVERY	*item_discovery;
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->item_discovery, &iter);

	while (NULL != (item_discovery = (ZBX_DC_ITEM_DISCOVERY *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, item_discovery);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		item_discovery = (ZBX_DC_ITEM_DISCOVERY *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "itemid:" ZBX_FS_UI64 " parent_itemid:" ZBX_FS_UI64,
				item_discovery->itemid, item_discovery->parent_itemid);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_functions(void)
{
	ZBX_DC_FUNCTION		*function;
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->functions, &iter);

	while (NULL != (function = (ZBX_DC_FUNCTION *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, function);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		function = (ZBX_DC_FUNCTION *)index.values[i];
		zabbix_log(LOG_LEVEL_DEBUG, "functionid:" ZBX_FS_UI64 " triggerid:" ZBX_FS_UI64 " itemid:"
				ZBX_FS_UI64 " function:'%s' parameter:'%s' type:%u timer_revision:" ZBX_FS_UI64,
				function->functionid, function->triggerid, function->itemid, function->function,
				function->parameter, function->type, function->timer_revision);

	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_trigger_tags(const ZBX_DC_TRIGGER *trigger)
{
	int			i;
	zbx_vector_ptr_t	index;

	zbx_vector_ptr_create(&index);

	zbx_vector_ptr_append_array(&index, trigger->tags.values, trigger->tags.values_num);
	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_TRACE, "  tags:");

	for (i = 0; i < index.values_num; i++)
	{
		zbx_dc_trigger_tag_t	*tag = (zbx_dc_trigger_tag_t *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "      tagid:" ZBX_FS_UI64 " tag:'%s' value:'%s'",
				tag->triggertagid, tag->tag, tag->value);
	}

	zbx_vector_ptr_destroy(&index);
}

static void	DCdump_triggers(void)
{
	ZBX_DC_TRIGGER		*trigger;
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->triggers, &iter);

	while (NULL != (trigger = (ZBX_DC_TRIGGER *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, trigger);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		zbx_uint64_t	*itemid;

		trigger = (ZBX_DC_TRIGGER *)index.values[i];
		if (ZBX_FLAG_DISCOVERY_PROTOTYPE == trigger->flags)
		{
			zabbix_log(LOG_LEVEL_TRACE, "triggerid:" ZBX_FS_UI64 " flags:%u", trigger->triggerid,
					trigger->flags);
			continue;
		}

		zabbix_log(LOG_LEVEL_TRACE, "triggerid:" ZBX_FS_UI64 " description:'%s' event_name:'%s' type:%u"
				" status:%u priority:%u flags:%u", trigger->triggerid, trigger->description,
				trigger->event_name, trigger->type, trigger->status, trigger->priority, trigger->flags);
		zabbix_log(LOG_LEVEL_TRACE, "  expression:'%s' recovery_expression:'%s'", trigger->expression,
				trigger->recovery_expression);
		zabbix_log(LOG_LEVEL_TRACE, "  value:%u state:%u error:'%s' lastchange:%d", trigger->value,
				trigger->state, ZBX_NULL2EMPTY_STR(trigger->error), trigger->lastchange);
		zabbix_log(LOG_LEVEL_TRACE, "  correlation_tag:'%s' recovery_mode:'%u' correlation_mode:'%u'",
				trigger->correlation_tag, trigger->recovery_mode, trigger->correlation_mode);
		zabbix_log(LOG_LEVEL_TRACE, "  topoindex:%u functional:%u locked:%u", trigger->topoindex,
				trigger->functional, trigger->locked);
		zabbix_log(LOG_LEVEL_TRACE, "  opdata:'%s'", trigger->opdata);

		if (NULL != trigger->itemids)
		{
			zabbix_log(LOG_LEVEL_TRACE, "  itemids:");

			for (itemid = trigger->itemids; 0 != *itemid; itemid++)
				zabbix_log(LOG_LEVEL_TRACE, "    " ZBX_FS_UI64, *itemid);
		}

		if (0 != trigger->tags.values_num)
			DCdump_trigger_tags(trigger);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_trigdeps(void)
{
	ZBX_DC_TRIGGER_DEPLIST	*trigdep;
	zbx_hashset_iter_t	iter;
	int			i, j;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->trigdeps, &iter);

	while (NULL != (trigdep = (ZBX_DC_TRIGGER_DEPLIST *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, trigdep);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		trigdep = (ZBX_DC_TRIGGER_DEPLIST *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "triggerid:" ZBX_FS_UI64 " refcount:%d", trigdep->triggerid,
				trigdep->refcount);

		for (j = 0; j < trigdep->dependencies.values_num; j++)
		{
			const ZBX_DC_TRIGGER_DEPLIST	*trigdep_up = (ZBX_DC_TRIGGER_DEPLIST *)trigdep->dependencies.values[j];

			zabbix_log(LOG_LEVEL_TRACE, "  triggerid:" ZBX_FS_UI64, trigdep_up->triggerid);
		}
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_expressions(void)
{
	ZBX_DC_EXPRESSION	*expression;
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zabbix_log(LOG_LEVEL_TRACE, "expression_revision:" ZBX_FS_UI64, (get_dc_config())->revision.expression);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->expressions, &iter);

	while (NULL != (expression = (ZBX_DC_EXPRESSION *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, expression);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		expression = (ZBX_DC_EXPRESSION *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "expressionid:" ZBX_FS_UI64 " regexp:'%s' expression:'%s delimiter:%d"
				" type:%u case_sensitive:%u", expression->expressionid, expression->regexp,
				expression->expression, expression->delimiter, expression->type,
				expression->case_sensitive);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_actions(void)
{
	zbx_dc_action_t		*action;
	zbx_hashset_iter_t	iter;
	int			i, j;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->actions, &iter);

	while (NULL != (action = (zbx_dc_action_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, action);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		action = (zbx_dc_action_t *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "actionid:" ZBX_FS_UI64 " formula:'%s' eventsource:%u evaltype:%u"
				" opflags:%x", action->actionid, action->formula, action->eventsource, action->evaltype,
				action->opflags);

		for (j = 0; j < action->conditions.values_num; j++)
		{
			zbx_dc_action_condition_t	*condition = (zbx_dc_action_condition_t *)action->conditions.values[j];

			zabbix_log(LOG_LEVEL_TRACE, "  conditionid:" ZBX_FS_UI64 " conditiontype:%u operator:%u"
					" value:'%s' value2:'%s'", condition->conditionid, condition->conditiontype,
					condition->op, condition->value, condition->value2);
		}
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_corr_conditions(zbx_dc_correlation_t *correlation)
{
	zbx_vector_dc_corr_condition_ptr_t	index;

	zbx_vector_dc_corr_condition_ptr_create(&index);

	zbx_vector_dc_corr_condition_ptr_append_array(&index, correlation->conditions.values,
			correlation->conditions.values_num);
	zbx_vector_dc_corr_condition_ptr_sort(&index, zbx_dc_corr_condition_compare_func);

	zabbix_log(LOG_LEVEL_TRACE, "  conditions:");

	for (int i = 0; i < index.values_num; i++)
	{
		zbx_dc_corr_condition_t	*condition = index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "      conditionid:" ZBX_FS_UI64 " type:%d",
				condition->corr_conditionid, condition->type);

		switch (condition->type)
		{
			case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
				zabbix_log(LOG_LEVEL_TRACE, "        oldtag:'%s' newtag:'%s'",
						condition->data.tag_pair.oldtag, condition->data.tag_pair.newtag);
				break;
			case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
				zabbix_log(LOG_LEVEL_TRACE, "        groupid:" ZBX_FS_UI64 " op:%u",
						condition->data.group.groupid, condition->data.group.op);
				break;
			case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
				zabbix_log(LOG_LEVEL_TRACE, "        tag:'%s'", condition->data.tag.tag);
				break;
			case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
				zabbix_log(LOG_LEVEL_TRACE, "        tag:'%s' value:'%s'",
						condition->data.tag_value.tag, condition->data.tag_value.value);
				break;
		}
	}

	zbx_vector_dc_corr_condition_ptr_destroy(&index);
}

static void	DCdump_corr_operations(zbx_dc_correlation_t *correlation)
{
	zbx_vector_dc_corr_operation_ptr_t	index;

	zbx_vector_dc_corr_operation_ptr_create(&index);

	zbx_vector_dc_corr_operation_ptr_append_array(&index, correlation->operations.values,
			correlation->operations.values_num);
	zbx_vector_dc_corr_operation_ptr_sort(&index, zbx_dc_corr_operation_compare_func);

	zabbix_log(LOG_LEVEL_TRACE, "  operations:");

	for (int i = 0; i < index.values_num; i++)
	{
		zbx_dc_corr_operation_t	*operation = (zbx_dc_corr_operation_t *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "      operetionid:" ZBX_FS_UI64 " type:%d",
				operation->corr_operationid, operation->type);
	}

	zbx_vector_dc_corr_operation_ptr_destroy(&index);
}

static void	DCdump_correlations(void)
{
	zbx_dc_correlation_t	*correlation;
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->correlations, &iter);

	while (NULL != (correlation = (zbx_dc_correlation_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, correlation);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		correlation = (zbx_dc_correlation_t *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "correlationid:" ZBX_FS_UI64 " name:'%s' evaltype:%u formula:'%s'",
				correlation->correlationid, correlation->name, correlation->evaltype,
				correlation->formula);

		DCdump_corr_conditions(correlation);
		DCdump_corr_operations(correlation);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_host_group_hosts(zbx_dc_hostgroup_t *group)
{
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_uint64_t	index;
	zbx_uint64_t		*phostid;

	zbx_vector_uint64_create(&index);
	zbx_hashset_iter_reset(&group->hostids, &iter);

	while (NULL != (phostid = (zbx_uint64_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_append_ptr(&index, phostid);

	zbx_vector_uint64_sort(&index, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_TRACE, "  hosts:");

	for (i = 0; i < index.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "    hostid:" ZBX_FS_UI64, index.values[i]);

	zbx_vector_uint64_destroy(&index);
}

static void	DCdump_host_groups(void)
{
	zbx_dc_hostgroup_t	*group;
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->hostgroups, &iter);

	while (NULL != (group = (zbx_dc_hostgroup_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, group);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		group = (zbx_dc_hostgroup_t *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "groupid:" ZBX_FS_UI64 " name:'%s'", group->groupid, group->name);

		if (0 != group->hostids.num_data)
			DCdump_host_group_hosts(group);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_host_group_index(void)
{
	zbx_dc_hostgroup_t	*group;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zabbix_log(LOG_LEVEL_TRACE, "group index:");

	for (i = 0; i < (get_dc_config())->hostgroups_name.values_num; i++)
	{
		group = (zbx_dc_hostgroup_t *)(get_dc_config())->hostgroups_name.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "  %s", group->name);
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_maintenance_groups(zbx_dc_maintenance_t *maintenance)
{
	int			i;
	zbx_vector_uint64_t	index;

	zbx_vector_uint64_create(&index);

	if (0 != maintenance->groupids.values_num)
	{
		zbx_vector_uint64_append_array(&index, maintenance->groupids.values, maintenance->groupids.values_num);
		zbx_vector_uint64_sort(&index, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zabbix_log(LOG_LEVEL_TRACE, "  groups:");

	for (i = 0; i < index.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "    groupid:" ZBX_FS_UI64, index.values[i]);

	zbx_vector_uint64_destroy(&index);
}

static void	DCdump_maintenance_hosts(zbx_dc_maintenance_t *maintenance)
{
	int			i;
	zbx_vector_uint64_t	index;

	zbx_vector_uint64_create(&index);

	if (0 != maintenance->hostids.values_num)
	{
		zbx_vector_uint64_append_array(&index, maintenance->hostids.values, maintenance->hostids.values_num);
		zbx_vector_uint64_sort(&index, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}

	zabbix_log(LOG_LEVEL_TRACE, "  hosts:");

	for (i = 0; i < index.values_num; i++)
		zabbix_log(LOG_LEVEL_TRACE, "    hostid:" ZBX_FS_UI64, index.values[i]);

	zbx_vector_uint64_destroy(&index);
}

static int	maintenance_tag_compare(const void *v1, const void *v2)
{
	const zbx_dc_maintenance_tag_t	*tag1 = *(const zbx_dc_maintenance_tag_t * const *)v1;
	const zbx_dc_maintenance_tag_t	*tag2 = *(const zbx_dc_maintenance_tag_t * const *)v2;
	int				ret;

	if (0 != (ret = (strcmp(tag1->tag, tag2->tag))))
		return ret;

	if (0 != (ret = (strcmp(tag1->value, tag2->value))))
		return ret;

	ZBX_RETURN_IF_NOT_EQUAL(tag1->op, tag2->op);

	return 0;
}

static void	DCdump_maintenance_tags(zbx_dc_maintenance_t *maintenance)
{
	int			i;
	zbx_vector_ptr_t	index;

	zbx_vector_ptr_create(&index);

	if (0 != maintenance->tags.values_num)
	{
		zbx_vector_ptr_append_array(&index, maintenance->tags.values, maintenance->tags.values_num);
		zbx_vector_ptr_sort(&index, maintenance_tag_compare);
	}

	zabbix_log(LOG_LEVEL_TRACE, "  tags:");

	for (i = 0; i < index.values_num; i++)
	{
		zbx_dc_maintenance_tag_t	*tag = (zbx_dc_maintenance_tag_t *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "    maintenancetagid:" ZBX_FS_UI64 " operator:%u tag:'%s' value:'%s'",
				tag->maintenancetagid, tag->op, tag->tag, tag->value);
	}

	zbx_vector_ptr_destroy(&index);
}

static void	DCdump_maintenance_periods(zbx_dc_maintenance_t *maintenance)
{
	int			i;
	zbx_vector_ptr_t	index;

	zbx_vector_ptr_create(&index);

	zbx_vector_ptr_append_array(&index, maintenance->periods.values, maintenance->periods.values_num);
	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_TRACE, "  periods:");

	for (i = 0; i < index.values_num; i++)
	{
		zbx_dc_maintenance_period_t	*period = (zbx_dc_maintenance_period_t *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "    timeperiodid:" ZBX_FS_UI64 " type:%u every:%d month:%d dayofweek:%d"
				" day:%d start_time:%d period:%d start_date:%d",
				period->timeperiodid, period->type, period->every, period->month, period->dayofweek,
				period->day, period->start_time, period->period, period->start_date);
	}

	zbx_vector_ptr_destroy(&index);
}

static void	DCdump_maintenances(void)
{
	zbx_dc_maintenance_t	*maintenance;
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->maintenances, &iter);

	while (NULL != (maintenance = (zbx_dc_maintenance_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, maintenance);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		maintenance = (zbx_dc_maintenance_t *)index.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "maintenanceid:" ZBX_FS_UI64 " type:%u tag_evaltype:%u active_since:%d"
				" active_until:%d", maintenance->maintenanceid, maintenance->type,
				maintenance->tags_evaltype, maintenance->active_since, maintenance->active_until);
		zabbix_log(LOG_LEVEL_TRACE, "  state:%u running_since:%d running_until:%d",
				maintenance->state, maintenance->running_since, maintenance->running_until);

		DCdump_maintenance_groups(maintenance);
		DCdump_maintenance_hosts(maintenance);
		DCdump_maintenance_tags(maintenance);
		DCdump_maintenance_periods(maintenance);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

/* stringpool dumping is disabled by default to avoid leaking secret macro data */
#ifdef HAVE_TESTS
static int	strpool_compare(const void *v1, const void *v2)
{
	const char	*s1 = *(const char * const *)v1 + sizeof(zbx_uint32_t);
	const char	*s2 = *(const char * const *)v2 + sizeof(zbx_uint32_t);

	return strcmp(s1, s2);
}

static void	DCdump_strpool(void)
{
	zbx_hashset_iter_t	iter;
	zbx_vector_ptr_t	records;
	char			*record;
	int			i;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&records);
	zbx_hashset_iter_reset(&(get_dc_config())->strpool, &iter);

	while (NULL != (record = (char *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&records, record);

	zbx_vector_ptr_sort(&records, strpool_compare);

	for (i = 0; i < records.values_num; i++)
	{
		zabbix_log(LOG_LEVEL_TRACE, "  %s: %u", (char *)records.values[i] + sizeof(zbx_uint32_t),
				*(zbx_uint32_t *)records.values[i]);
	}

	zbx_vector_ptr_destroy(&records);
}
#endif

static void	DCdump_drules(void)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_drule_t		*drule;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_hashset_iter_reset(&(get_dc_config())->drules, &iter);
	while (NULL != (drule = (zbx_dc_drule_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "druleid:" ZBX_FS_UI64 " proxyid:" ZBX_FS_UI64 " revision:" ZBX_FS_UI64,
				drule->druleid, drule->proxyid, drule->revision);
		zabbix_log(LOG_LEVEL_TRACE, "  status:%u delay:%d location:%d nextcheck:%ld concurrency_max:%d",
				drule->status, drule->delay, drule->location, (long int)drule->nextcheck,
				drule->concurrency_max);
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_dchecks(void)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_dcheck_t		*dcheck;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_hashset_iter_reset(&(get_dc_config())->dchecks, &iter);
	while (NULL != (dcheck = (zbx_dc_dcheck_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "dcheckid:" ZBX_FS_UI64 " druleid:" ZBX_FS_UI64,
				dcheck->dcheckid, dcheck->druleid);
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_httptests(void)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_httptest_t		*httptest;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_hashset_iter_reset(&(get_dc_config())->httptests, &iter);
	while (NULL != (httptest = (zbx_dc_httptest_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "httptestid:" ZBX_FS_UI64 " hostid:" ZBX_FS_UI64 " revision:" ZBX_FS_UI64,
				httptest->httptestid, httptest->hostid, httptest->revision);
		zabbix_log(LOG_LEVEL_TRACE, "  status:%u delay:%d location:%d nextcheck:%ld",
				httptest->status, httptest->delay, httptest->location, (long int)httptest->nextcheck);
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_httptest_fields(void)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_httptest_field_t	*httptest_field;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_hashset_iter_reset(&(get_dc_config())->httptest_fields, &iter);
	while (NULL != (httptest_field = (zbx_dc_httptest_field_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "httptest_fieldid:" ZBX_FS_UI64 " httptestid:" ZBX_FS_UI64,
				httptest_field->httptest_fieldid, httptest_field->httptestid);
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_httpsteps(void)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_httpstep_t	*httpstep;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_hashset_iter_reset(&(get_dc_config())->httpsteps, &iter);
	while (NULL != (httpstep = (zbx_dc_httpstep_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "httpstepid:" ZBX_FS_UI64 " httptestid:" ZBX_FS_UI64,
				httpstep->httpstepid, httpstep->httptestid);
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_httpstep_fields(void)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_httpstep_field_t	*httpstep_field;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_hashset_iter_reset(&(get_dc_config())->httpstep_fields, &iter);
	while (NULL != (httpstep_field = (zbx_dc_httpstep_field_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "httpstep_fieldid:" ZBX_FS_UI64 " httpstepid:" ZBX_FS_UI64,
				httpstep_field->httpstep_fieldid, httpstep_field->httpstepid);
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static int	connector_tag_compare(const void *v1, const void *v2)
{
	const zbx_dc_connector_tag_t	*tag1 = *(const zbx_dc_connector_tag_t * const *)v1;
	const zbx_dc_connector_tag_t	*tag2 = *(const zbx_dc_connector_tag_t * const *)v2;
	int				ret;

	if (0 != (ret = (strcmp(tag1->tag, tag2->tag))))
		return ret;

	if (0 != (ret = (strcmp(tag1->value, tag2->value))))
		return ret;

	ZBX_RETURN_IF_NOT_EQUAL(tag1->op, tag2->op);

	return 0;
}

static void	DCdump_connector_tags(zbx_dc_connector_t *connector)
{
	int				i;
	zbx_vector_dc_connector_tag_t	index;

	zbx_vector_dc_connector_tag_create(&index);

	if (0 != connector->tags.values_num)
	{
		zbx_vector_dc_connector_tag_append_array(&index, connector->tags.values, connector->tags.values_num);
		zbx_vector_dc_connector_tag_sort(&index, connector_tag_compare);
	}

	zabbix_log(LOG_LEVEL_TRACE, "  tags:");

	for (i = 0; i < index.values_num; i++)
	{
		zbx_dc_connector_tag_t	*tag = index.values[i];

		zabbix_log(LOG_LEVEL_TRACE, "    connectortagid:" ZBX_FS_UI64 " operator:%u tag:'%s' value:'%s'",
				tag->connectortagid, tag->op, tag->tag, tag->value);
	}

	zbx_vector_dc_connector_tag_destroy(&index);
}

static void	DCdump_connectors(void)
{
	zbx_dc_connector_t	*connector;
	zbx_hashset_iter_t	iter;
	int			i;
	zbx_vector_ptr_t	index;

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_vector_ptr_create(&index);
	zbx_hashset_iter_reset(&(get_dc_config())->connectors, &iter);

	while (NULL != (connector = (zbx_dc_connector_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_append(&index, connector);

	zbx_vector_ptr_sort(&index, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < index.values_num; i++)
	{
		connector = (zbx_dc_connector_t *)index.values[i];

		zabbix_log(LOG_LEVEL_TRACE, "connectorid:" ZBX_FS_UI64" url:'%s'",
				connector->connectorid, connector->url);
		zabbix_log(LOG_LEVEL_TRACE, "  protocol:%u data_type:%u", connector->protocol, connector->data_type);
		zabbix_log(LOG_LEVEL_TRACE, "  max_records:%d", connector->max_records);
		zabbix_log(LOG_LEVEL_TRACE, "  max_senders:%d", connector->max_senders);
		zabbix_log(LOG_LEVEL_TRACE, "  timeout:'%s'", connector->timeout);
		zabbix_log(LOG_LEVEL_TRACE, "  max_attempts:%d", connector->max_attempts);
		zabbix_log(LOG_LEVEL_TRACE, "  token:'%s'", connector->token);
		zabbix_log(LOG_LEVEL_TRACE, "  http_proxy:'%s'", connector->http_proxy);
		zabbix_log(LOG_LEVEL_TRACE, "  authtype:%u", connector->authtype);
		zabbix_log(LOG_LEVEL_TRACE, "  username:'%s'", connector->username);
		zabbix_log(LOG_LEVEL_TRACE, "  password:'%s'", connector->password);
		zabbix_log(LOG_LEVEL_TRACE, "  verify_peer:%u", connector->verify_peer);
		zabbix_log(LOG_LEVEL_TRACE, "  verify_host:%u", connector->verify_host);
		zabbix_log(LOG_LEVEL_TRACE, "  ssl_cert_file:'%s'", connector->ssl_cert_file);
		zabbix_log(LOG_LEVEL_TRACE, "  ssl_key_file:'%s'", connector->ssl_key_file);
		zabbix_log(LOG_LEVEL_TRACE, "  ssl_key_password:'%s'", connector->ssl_key_password);
		zabbix_log(LOG_LEVEL_TRACE, "  status:%d", connector->status);
		zabbix_log(LOG_LEVEL_TRACE, "  tags_evaltype:%d", connector->tags_evaltype);
		zabbix_log(LOG_LEVEL_TRACE, "  item_value_type:%d", connector->item_value_type);
		zabbix_log(LOG_LEVEL_TRACE, "  attempt_interval:'%s'", connector->attempt_interval);

		DCdump_connector_tags(connector);
	}

	zbx_vector_ptr_destroy(&index);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

static void	DCdump_proxy_groups(void)
{
	zbx_hashset_iter_t	iter;
	zbx_dc_proxy_group_t	*pg;
	zbx_dc_config_t		*config = get_dc_config();

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_hashset_iter_reset(&config->proxy_groups, &iter);
	while (NULL != (pg = (zbx_dc_proxy_group_t *)zbx_hashset_iter_next(&iter)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "proxy_groupid:" ZBX_FS_UI64 " failover_delay:%d min_online:%d"
				" revision:" ZBX_FS_UI64,
				pg->proxy_groupid, pg->failover_delay, pg->min_online, pg->revision);
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}


static void	DCdump_host_proxy_index(void)
{
	zbx_hashset_iter_t		iter;
	zbx_dc_host_proxy_index_t	*hpi;
	zbx_dc_config_t			*config = get_dc_config();

	zabbix_log(LOG_LEVEL_TRACE, "In %s()", __func__);

	zbx_hashset_iter_reset(&config->host_proxy_index, &iter);
	while (NULL != (hpi = (zbx_dc_host_proxy_index_t *)zbx_hashset_iter_next(&iter)))
		zabbix_log(LOG_LEVEL_TRACE, "host:%s proxyid:" ZBX_FS_UI64, hpi->host, hpi->host_proxy->proxyid);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s()", __func__);
}

void	DCdump_configuration(void)
{
	zabbix_log(LOG_LEVEL_TRACE, "=== Configuration cache contents (revision:" ZBX_FS_UI64 ") ===",
			get_dc_config()->revision.config);

	zabbix_log(LOG_LEVEL_TRACE, "  autoreg_tls_revision:" ZBX_FS_UI64, get_dc_config()->revision.autoreg_tls);

	DCdump_config();
	DCdump_hosts();
	DCdump_host_tags();
	DCdump_proxies();
	DCdump_ipmihosts();
	DCdump_host_inventories();
	DCdump_kvs_paths();
	um_cache_dump(get_dc_config()->um_cache);
	DCdump_interfaces();
	DCdump_items();
	DCdump_item_discovery();
	DCdump_interface_snmpitems();
	DCdump_template_items();
	DCdump_triggers();
	DCdump_trigdeps();
	DCdump_functions();
	DCdump_expressions();
	DCdump_actions();
	DCdump_correlations();
	DCdump_host_groups();
	DCdump_host_group_index();
	DCdump_maintenances();
	DCdump_drules();
	DCdump_dchecks();
	DCdump_httptests();
	DCdump_httptest_fields();
	DCdump_httpsteps();
	DCdump_httpstep_fields();
	DCdump_autoreg_hosts();
	DCdump_connectors();
	DCdump_proxy_groups();
	DCdump_host_proxy_index();
#ifdef HAVE_TESTS
	DCdump_strpool();
#endif
}
