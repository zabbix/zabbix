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

#include "proxy_group.h"
#include "config.h"
#include "dbconfig.h"
#include "dbsync.h"
#include "user_macro.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxpgservice.h"
#include "zbxtime.h"
#include "zbxversion.h"

ZBX_PTR_VECTOR_IMPL(pg_proxy_ptr, zbx_pg_proxy_t *)
ZBX_PTR_VECTOR_IMPL(pg_group_ptr, zbx_pg_group_t *)
ZBX_VECTOR_IMPL(pg_host, zbx_pg_host_t)
ZBX_VECTOR_IMPL(pg_host_ref_ptr, zbx_pg_host_ref_t *)

static zbx_hash_t	pg_host_ref_hash(const void *d)
{
	const zbx_pg_host_ref_t	*host = (const zbx_pg_host_ref_t *)d;

	return ZBX_DEFAULT_UINT64_HASH_FUNC(&host->host->hostid);
}

static int	pg_host_ref_compare(const void *d1, const void *d2)
{
	const zbx_pg_host_ref_t	*h1 = (const zbx_pg_host_ref_t *)d1;
	const zbx_pg_host_ref_t	*h2 = (const zbx_pg_host_ref_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(h1->host->hostid, h2->host->hostid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync proxy groups with configuration cache                        *
 *                                                                            *
 * Parameters: sync     - [IN] db synchronization data                        *
 *             revision - [IN] current sync revision                          *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - proxy_groupid                                                *
 *           1 - failover_delay                                               *
 *           2 - min_online                                                   *
 *                                                                            *
 ******************************************************************************/
void	dc_sync_proxy_group(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	zbx_dc_proxy_group_t	*pg;
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		zbx_uint64_t	proxy_groupid;
		int		found;

		ZBX_STR2UINT64(proxy_groupid, row[0]);

		pg = (zbx_dc_proxy_group_t *)DCfind_id(&get_dc_config()->proxy_groups, proxy_groupid,
				sizeof(zbx_dc_proxy_group_t), &found);

		dc_strpool_replace(found, &pg->failover_delay, row[1]);
		dc_strpool_replace(found, &pg->min_online, row[2]);
		dc_strpool_replace(found, &pg->name, row[3]);

		pg->revision = revision;
	}

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (pg = (zbx_dc_proxy_group_t *)zbx_hashset_search(&get_dc_config()->proxy_groups, &rowid)))
			continue;

		dc_strpool_release(pg->failover_delay);
		dc_strpool_release(pg->min_online);
		dc_strpool_release(pg->name);

		zbx_hashset_remove_direct(&get_dc_config()->proxy_groups, pg);
	}

	if (0 != sync->add_num + sync->update_num + sync->remove_num)
		get_dc_config()->revision.proxy_group = revision;

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update local proxy group cache                                    *
 *                                                                            *
 * Parameter: groups   - [IN/OUT] local proxy group cache                     *
 *            revision - [IN/OUT] local proxy group cache revision            *
 *                                                                            *
 * Return value: SUCCEED - local cache was updated                            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_fetch_proxy_groups(zbx_hashset_t *groups, zbx_uint64_t *revision)
{
	int		ret = FAIL;
	zbx_uint64_t	old_revision = *revision;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (*revision >= get_dc_config()->revision.proxy_group)
		goto out;

	zbx_hashset_iter_t	iter;
	zbx_dc_proxy_group_t	*dc_group;
	zbx_pg_group_t		*group;

	zbx_hashset_iter_reset(groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
		group->flags = ZBX_PG_GROUP_FLAGS_NONE;

	RDLOCK_CACHE;

	*revision = get_dc_config()->revision.proxy_group;

	zbx_hashset_iter_reset(&get_dc_config()->proxy_groups, &iter);
	while (NULL != (dc_group = (zbx_dc_proxy_group_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == (group = (zbx_pg_group_t *)zbx_hashset_search(groups, &dc_group->proxy_groupid)))
		{
			zbx_pg_group_t	group_local = {.proxy_groupid = dc_group->proxy_groupid};

			group = (zbx_pg_group_t *)zbx_hashset_insert(groups, &group_local, sizeof(group_local));
			zbx_vector_pg_proxy_ptr_create(&group->proxies);
			zbx_hashset_create(&group->hostids, 0, ZBX_DEFAULT_UINT64_HASH_FUNC,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_create(&group->unassigned_hostids);
			group->flags = ZBX_PG_GROUP_SYNC_ADDED;
		}
		else
			group->flags = ZBX_PG_GROUP_SYNC_MODIFIED;

		group->sync_revision = *revision;

		if (dc_group->revision > group->revision)
		{
			group->revision = dc_group->revision;

			if (NULL == group->name || 0 != strcmp(group->name, dc_group->name))
				group->name = zbx_strdup(group->name, dc_group->name);

			if (NULL == group->failover_delay ||
					0 != strcmp(group->failover_delay, dc_group->failover_delay))
			{
				group->failover_delay = zbx_strdup(group->failover_delay, dc_group->failover_delay);
			}

			if (NULL == group->min_online || 0 != strcmp(group->min_online, dc_group->min_online))
				group->min_online = zbx_strdup(group->min_online, dc_group->min_online);
		}
	}

	UNLOCK_CACHE;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s revision:" ZBX_FS_UI64 "->" ZBX_FS_UI64, __func__,
			zbx_result_string(ret), old_revision, *revision);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update local proxy cache                                          *
 *                                                                            *
 * Parameter: groups   - [IN] local proxy group cache                         *
 *            proxies  - [IN/OUT] local proxy cache                           *
 *            revision - [IN/OUT] local proxy cache revision                  *
 *            flags    - [IN] ZBX_PG_PROXY_FETCH_FORCE - force configuration  *
 *                            update ignoring revision checks                 *
 *                            ZBX_PG_PROXY_FETCH_REVISION - update by proxy   *
 *                            configuration revision                          *
 *            proxy_reloc - [OUT] proxy relocation data                       *
 *                                                                            *
 * Return value: SUCCEED - local cache was updated                            *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_fetch_proxies(zbx_hashset_t *groups, zbx_hashset_t *proxies, zbx_uint64_t *revision, int flags,
		zbx_vector_objmove_t *proxy_reloc)
{
	int		ret = FAIL;
	zbx_uint64_t	old_revision = *revision;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_PG_PROXY_FETCH_REVISION == flags && *revision >= get_dc_config()->revision.proxy)
		goto out;

	zbx_hashset_iter_t	iter;
	ZBX_DC_PROXY		*dc_proxy;
	zbx_pg_proxy_t		*proxy;

	RDLOCK_CACHE;

	*revision = get_dc_config()->revision.proxy;

	zbx_hashset_iter_reset(&get_dc_config()->proxies, &iter);

	while (NULL != (dc_proxy = (ZBX_DC_PROXY *)zbx_hashset_iter_next(&iter)))
	{
		if (0 != dc_proxy->proxy_groupid && NULL == zbx_hashset_search(groups, &dc_proxy->proxy_groupid))
			continue;

		proxy = (zbx_pg_proxy_t *)zbx_hashset_search(proxies, &dc_proxy->proxyid);

		if (NULL != proxy)
		{
			if (NULL != proxy->group && proxy->group->proxy_groupid != dc_proxy->proxy_groupid)
			{
				zbx_objmove_t	reloc = {
						.objid = proxy->proxyid,
						.srcid = proxy->group->proxy_groupid,
						.dstid = dc_proxy->proxy_groupid
					};

				zbx_vector_objmove_append_ptr(proxy_reloc, &reloc);
			}
		}
		else
		{
			zbx_pg_proxy_t	proxy_local = {.proxyid = dc_proxy->proxyid};

			proxy = (zbx_pg_proxy_t *)zbx_hashset_insert(proxies, &proxy_local, sizeof(proxy_local));
			proxy->group = NULL;

			zbx_hashset_create(&proxy->hosts, 0, pg_host_ref_hash, pg_host_ref_compare);
			zbx_vector_pg_host_create(&proxy->deleted_group_hosts);

			zbx_objmove_t	reloc = {
					.objid = proxy->proxyid,
					.srcid = 0,
					.dstid = dc_proxy->proxy_groupid,
				};

			zbx_vector_objmove_append_ptr(proxy_reloc, &reloc);
		}

		proxy->lastaccess = dc_proxy->lastaccess;
		proxy->version = ZBX_COMPONENT_VERSION_WITHOUT_PATCH(dc_proxy->version_int);
		proxy->revision = *revision;

		if (NULL == proxy->name || 0 != strcmp(proxy->name, dc_proxy->name))
			proxy->name = zbx_strdup(proxy->name, dc_proxy->name);
	}

	UNLOCK_CACHE;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s revision:" ZBX_FS_UI64 "->" ZBX_FS_UI64, __func__,
			zbx_result_string(ret), old_revision, *revision);
	return ret;
}

static void	dc_register_host_proxy(zbx_dc_host_proxy_t *hp)
{
	zbx_dc_host_proxy_index_t	*hpi, hpi_local = {.host = hp->host};

	if (NULL == (hpi = (zbx_dc_host_proxy_index_t *)zbx_hashset_search(&get_dc_config()->host_proxy_index, &hpi_local)))
	{
		hpi = (zbx_dc_host_proxy_index_t *)zbx_hashset_insert(&get_dc_config()->host_proxy_index, &hpi_local,
				sizeof(hpi_local));
		dc_strpool_acquire(hpi->host);
	}

	hpi->host_proxy = hp;
}

static void	dc_deregister_host_proxy(zbx_dc_host_proxy_t *hp)
{
	zbx_dc_host_proxy_index_t	*hpi, hpi_local = {.host = hp->host};

	if (NULL != (hpi = (zbx_dc_host_proxy_index_t *)zbx_hashset_search(&get_dc_config()->host_proxy_index,
			&hpi_local)))
	{
		dc_strpool_release(hpi->host);
		zbx_hashset_remove_direct(&get_dc_config()->host_proxy_index, hpi);
	}
}

void	dc_update_host_proxy(const char *host_old, const char *host_new)
{
	zbx_dc_host_proxy_index_t	*hpi, hpi_local = {.host = host_old};

	if (NULL != (hpi = (zbx_dc_host_proxy_index_t *)zbx_hashset_search(&get_dc_config()->host_proxy_index,
			&hpi_local)))
	{
		dc_strpool_release(hpi->host);
		zbx_hashset_remove_direct(&get_dc_config()->host_proxy_index, hpi);

		hpi_local.host_proxy = hpi->host_proxy;
		hpi_local.host = dc_strpool_intern(host_new);
		zbx_hashset_insert(&get_dc_config()->host_proxy_index, &hpi_local, sizeof(hpi_local));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync host proxy links with configuration cache                    *
 *                                                                            *
 * Parameters: sync     - [IN] db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - hostproxyid                                                  *
 *           1 - hostid                                                       *
 *           2 - host                                                         *
 *           3 - proxyid                                                      *
 *           4 - revision                                                     *
 *           5 - host.host (NULL on proxies)                                  *
 *                                                                            *
 ******************************************************************************/
void	dc_sync_host_proxy(zbx_dbsync_t *sync, zbx_uint64_t revision)
{
	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;
	zbx_dc_host_proxy_t		*hp;
	int				ret;
	ZBX_DC_HOST			*dc_host;
	zbx_vector_dc_host_ptr_t	hosts;
	zbx_hashset_t			psk_owners;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_dc_host_ptr_create(&hosts);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	/* Encryption data in host_proxy table is stored and synced only on proxy. */
	/* The identify conflicts have been already checked by server, so they can */
	/* be skipped by using separate psk_owner registry.                        */
	zbx_hashset_create(&psk_owners, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		zbx_uint64_t	hostproxyid;
		int		found;

		ZBX_STR2UINT64(hostproxyid, row[0]);

		hp = (zbx_dc_host_proxy_t *)DCfind_id(&get_dc_config()->host_proxy, hostproxyid,
				sizeof(zbx_dc_host_proxy_t), &found);

		ZBX_DBROW2UINT64(hp->hostid, row[1]);
		ZBX_STR2UINT64(hp->proxyid, row[3]);
		ZBX_STR2UINT64(hp->revision, row[4]);

		if (SUCCEED != zbx_db_is_null(row[5]))	/* server */
		{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			if (0 == found)
			{
				hp->tls_issuer = NULL;
				hp->tls_subject = NULL;
				hp->tls_dc_psk = NULL;
			}
#endif

			dc_strpool_replace(found, &hp->host, row[5]);

			if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&get_dc_config()->hosts, &hp->hostid)))
			{
				if (0 != dc_host->proxy_groupid)
				{
					if (0 != dc_host->proxyid)
						dc_host_deregister_proxy(dc_host, dc_host->proxyid, revision);

					dc_host_register_proxy(dc_host, hp->proxyid, revision);
					dc_host->proxyid = hp->proxyid;
					dc_host->revision = revision;
					zbx_vector_dc_host_ptr_append(&hosts, dc_host);
				}
			}
		}
		else	/* proxy */
		{
			dc_strpool_replace(found, &hp->host, row[2]);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			ZBX_STR2UCHAR(hp->tls_accept, row[6]);
			dc_strpool_replace(found, &hp->tls_issuer, row[7]);
			dc_strpool_replace(found, &hp->tls_subject, row[8]);
			hp->tls_dc_psk = dc_psk_sync(row[9], row[10], hp->host, found, &psk_owners, hp->tls_dc_psk);
#endif
		}

		dc_register_host_proxy(hp);
	}

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		if (NULL == (hp = (zbx_dc_host_proxy_t *)zbx_hashset_search(&get_dc_config()->host_proxy, &rowid)))
			continue;

		if (NULL != (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&get_dc_config()->hosts, &hp->hostid)))
		{
			if (0 != dc_host->proxy_groupid)
			{
				dc_host_deregister_proxy(dc_host, hp->proxyid, revision);
				dc_host->proxyid = 0;
				dc_host->revision = revision;
				zbx_vector_dc_host_ptr_append(&hosts, dc_host);
			}
		}

		dc_deregister_host_proxy(hp);

		dc_strpool_release(hp->host);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		if (NULL != hp->tls_issuer)	/* proxy */
		{
			dc_strpool_release(hp->tls_issuer);
			dc_strpool_release(hp->tls_subject);
			dc_psk_unlink(hp->tls_dc_psk);
		}
#endif
		zbx_hashset_remove_direct(&get_dc_config()->host_proxy, hp);
	}

	if (0 != hosts.values_num)
	{
		zbx_vector_uint64_t	hostids;

		zbx_vector_uint64_create(&hostids);

		for (int i = 0; i < hosts.values_num; i++)
		{
			ZBX_DC_INTERFACE	*interface;

			for (int j = 0; j < hosts.values[i]->interfaces_v.values_num; j++)
			{
				interface = (ZBX_DC_INTERFACE *)hosts.values[i]->interfaces_v.values[j];
				interface->reset_availability = 1;
			}
		}

		zbx_dbsync_process_active_avail_diff(&hostids);

		zbx_vector_uint64_destroy(&hostids);
	}

	zbx_hashset_destroy(&psk_owners);
	zbx_vector_dc_host_ptr_destroy(&hosts);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get redirection information for the host                          *
 *                                                                            *
 * Parameters: host     - [IN] host name                                      *
 *             attr     - [IN] connection attributes                          *
 *             redirect - [OUT] redirection information                       *
 *                                                                            *
 * Return value: SUCCEED - host must be redirected to the returned address    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	dc_get_host_redirect(const char *host, const zbx_tls_conn_attr_t *attr, zbx_comms_redirect_t *redirect)
{
	zbx_dc_host_proxy_index_t	*hpi, hpi_local = {.host = host};
	ZBX_DC_PROXY			*proxy;

	if (NULL == (hpi = (zbx_dc_host_proxy_index_t *)zbx_hashset_search(&get_dc_config()->host_proxy_index,
			&hpi_local)))
	{
		return FAIL;
	}

	if (NULL == (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&get_dc_config()->proxies, &hpi->host_proxy->proxyid)))
		return FAIL;

	if (NULL != get_dc_config()->proxy_hostname && 0 == strcmp(proxy->name, get_dc_config()->proxy_hostname))
	{
		int	now;

		now = (int)time(NULL);

		/* check if proxy is not offline and the client redirect should be reset */
		if (now - get_dc_config()->proxy_lastonline < get_dc_config()->proxy_failover_delay ||
				now - hpi->lastreset < get_dc_config()->proxy_failover_delay)
		{
			return FAIL;
		}

		hpi->lastreset = now;
		redirect->reset = ZBX_REDIRECT_RESET;

		return SUCCEED;
	}

	const char	*local_port = proxy->local_port;

	if ('{' == *local_port)
	{
		um_cache_resolve_const(get_dc_config()->um_cache, NULL, 0, proxy->local_port, ZBX_MACRO_ENV_NONSECURE,
				&local_port);
	}

	if ('\0' != *local_port)
		zbx_snprintf(redirect->address, sizeof(redirect->address), "%s:%s", proxy->local_address, local_port);
	else
		zbx_strlcpy(redirect->address, proxy->local_address, sizeof(redirect->address));

	redirect->revision = hpi->host_proxy->revision;
	redirect->reset = ZBX_REDIRECT_NONE;

	unsigned char	tls_accept;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	const char	*tls_issuer;
	const char	*tls_subject;
	ZBX_DC_PSK	*dc_psk = NULL;
#endif

	if (NULL != get_dc_config()->proxy_hostname)
	{
		/* on proxy encryption information is taken from host_proxy redirect mapping */
		tls_accept = hpi->host_proxy->tls_accept;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		tls_issuer = hpi->host_proxy->tls_issuer;
		tls_subject = hpi->host_proxy->tls_subject;
		dc_psk = hpi->host_proxy->tls_dc_psk;
#endif
	}
	else
	{
		ZBX_DC_HOST	*dc_host;

		/* on server encryption information is taken from hosts, block redirection for unknown hosts */
		if (NULL == (dc_host = DCfind_host(host)))
			return FAIL;

		tls_accept = dc_host->tls_accept;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		tls_issuer = dc_host->tls_issuer;
		tls_subject = dc_host->tls_subject;
		dc_psk = dc_host->tls_dc_psk;
#endif
	}

	if (0 == ((unsigned int)tls_accept & attr->connection_type))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot perform host \"%s\" redirection: connection of type \"%s\""
				" is not allowed",
				host, zbx_tcp_connection_type_name(attr->connection_type));

		return FAIL;
	}

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	const char	*msg;

	if (FAIL == zbx_tls_validate_attr(attr, tls_issuer, tls_subject,
			NULL == dc_psk ? NULL : dc_psk->tls_psk_identity, &msg))
	{

		zabbix_log(LOG_LEVEL_DEBUG, "cannot perform host \"%s\" redirection: %s", host, msg);
		return FAIL;
	}
#endif

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set proxy failover delay in configuration cache                   *
 *                                                                            *
 ******************************************************************************/
void	dc_update_proxy_failover_delay(void)
{
	if (NULL != get_dc_config()->proxy_failover_delay_raw)
	{
		const char	*ptr = get_dc_config()->proxy_failover_delay_raw;

		if ('{' == *ptr)
			um_cache_resolve_const(get_dc_config()->um_cache, NULL, 0, ptr, ZBX_MACRO_ENV_NONSECURE, &ptr);

		if (FAIL == zbx_is_time_suffix(ptr, &get_dc_config()->proxy_failover_delay, ZBX_LENGTH_UNLIMITED))
			get_dc_config()->proxy_failover_delay = ZBX_PG_DEFAULT_FAILOVER_DELAY;

		dc_strpool_release(get_dc_config()->proxy_failover_delay_raw);
		get_dc_config()->proxy_failover_delay_raw = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: set proxy failover delay in configuration cache                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_set_proxy_failover_delay(const char *failover_delay)
{
	WRLOCK_CACHE;

	int	found = (NULL != get_dc_config()->proxy_failover_delay_raw);

	/* failover delay can be updated only by one process at time, */
	/* so it can be checked without locking before update        */
	if (0 == found || 0 != strcmp(get_dc_config()->proxy_failover_delay_raw, failover_delay))
		dc_strpool_replace(found, &get_dc_config()->proxy_failover_delay_raw, failover_delay);

	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set proxy last online timestamp in configuration cache            *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_set_proxy_lastonline(int lastonline)
{
	WRLOCK_CACHE;
	get_dc_config()->proxy_lastonline = lastonline;
	UNLOCK_CACHE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy group revision                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_dc_get_proxy_group_revision(zbx_uint64_t proxy_groupid)
{
	zbx_uint64_t	revision;

	RDLOCK_CACHE;
	zbx_dc_proxy_group_t	*pg;

	if (NULL != (pg = (zbx_dc_proxy_group_t *)zbx_hashset_search(&get_dc_config()->proxy_groups, &proxy_groupid)))
		revision = pg->revision;
	else
		revision = 0;

	UNLOCK_CACHE;

	return revision;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy group by proxy id                                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_dc_get_proxy_groupid(zbx_uint64_t proxyid)
{
	zbx_uint64_t	proxy_groupid = 0;
	ZBX_DC_PROXY	*proxy;

	if (0 != proxyid)
	{
		RDLOCK_CACHE;

		if (NULL != (proxy = (ZBX_DC_PROXY *)zbx_hashset_search(&get_dc_config()->proxies, &proxyid)))
			proxy_groupid = proxy->proxy_groupid;

		UNLOCK_CACHE;
	}

	return proxy_groupid;
}

