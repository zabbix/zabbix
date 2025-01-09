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

#include "pg_service.h"
#include "zbxcommon.h"
#include "pg_cache.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxnix.h"
#include "zbxpgservice.h"
#include "zbxserialize.h"
#include "zbxthreads.h"
#include "zbxtime.h"
#include "zbxtypes.h"
#include "zbxversion.h"

/******************************************************************************
 *                                                                            *
 * Purpose: move hosts between proxy groups in cache                          *
 *                                                                            *
 * Parameter: pgs     - [IN] proxy group service                              *
 *            message - [IN] IPC message with host relocation data            *
 *                                                                            *
 ******************************************************************************/
static void	pg_update_host_pgroup(zbx_pg_service_t *pgs, zbx_ipc_message_t *message)
{
	unsigned char	*ptr = message->data;
	zbx_uint64_t	hostid, srcid, dstid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pg_cache_lock(pgs->cache);

	(void)pg_cache_update_groups(pgs->cache);

	while (ptr - message->data < message->size)
	{
		zbx_pg_group_t	*group;

		ptr += zbx_deserialize_value(ptr, &hostid);
		ptr += zbx_deserialize_value(ptr, &srcid);
		ptr += zbx_deserialize_value(ptr, &dstid);

		if (srcid == dstid)
		{
			/* handle host name change by re-assigning host to the same proxy */
			/* causing its hostmap revision to change                         */
			zbx_pg_host_t	*host;
			zbx_pg_proxy_t	*proxy;

			if (NULL == (host = (zbx_pg_host_t *)zbx_hashset_search(&pgs->cache->hostmap, &hostid)))
				continue;

			pg_cache_set_host_proxy(pgs->cache, hostid, host->proxyid);

			if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&pgs->cache->proxies,
					&host->proxyid)) && NULL != proxy->group)
			{
				pg_cache_queue_group_update(pgs->cache, proxy->group);
			}
		}
		else
		{
			if (0 != srcid)
			{
				if (NULL != (group = (zbx_pg_group_t *)zbx_hashset_search(&pgs->cache->groups, &srcid)))
					pg_cache_group_remove_host(pgs->cache, group, hostid);
			}

			if (0 != dstid)
			{
				if (NULL != (group = (zbx_pg_group_t *)zbx_hashset_search(&pgs->cache->groups, &dstid)))
					pg_cache_group_add_host(pgs->cache, group, hostid);
			}
		}
	}

	pg_cache_unlock(pgs->cache);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy lastaccess                                           *
 *                                                                            *
 * Parameter: pgs     - [IN] proxy group service                              *
 *            message - [IN] IPC message with proxy last access               *
 *                                                                            *
 ******************************************************************************/
static void	pg_update_proxy_rtdata(zbx_pg_service_t *pgs, zbx_ipc_message_t *message)
{
	unsigned char	*ptr = message->data;
	zbx_uint64_t	proxyid;
	int		lastaccess, version;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ptr += zbx_deserialize_value(ptr, &proxyid);
	ptr += zbx_deserialize_value(ptr, &lastaccess);
	ptr += zbx_deserialize_value(ptr, &version);

	pg_cache_lock(pgs->cache);

	zbx_pg_proxy_t 	*proxy;

	if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&pgs->cache->proxies, &proxyid)))
	{
		if (0 != lastaccess)
			proxy->lastaccess = lastaccess;

		if (0 != version)
			proxy->version = ZBX_COMPONENT_VERSION_WITHOUT_PATCH(version);
	}

	pg_cache_unlock(pgs->cache);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy configuration sync data                                 *
 *                                                                            *
 * Parameter: pgs     - [IN] proxy group service                              *
 *            message - [IN] IPC message with host relocation data            *
 *                                                                            *
 ******************************************************************************/
static void	pg_get_proxy_sync_data(zbx_pg_service_t *pgs, zbx_ipc_client_t *client, zbx_ipc_message_t *message)
{
	unsigned char	*ptr = message->data, *data, mode = ZBX_PROXY_SYNC_NONE;
	zbx_uint64_t	proxyid, proxy_hostmap_revision, hostmap_revision = 0;
	time_t		now;
	zbx_uint32_t	data_len, failover_delay_len;
	zbx_pg_proxy_t	*proxy;
	char		*failover_delay = ZBX_PG_DEFAULT_FAILOVER_DELAY_STR;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ptr += zbx_deserialize_value(ptr, &proxyid);
	(void)zbx_deserialize_value(ptr, &proxy_hostmap_revision);

	now = time(NULL);

	pg_cache_lock(pgs->cache);

	/* if proxy is not cached or registered to proxy group return 'no sync' mode */
	/* with 0 hostmap_revision, forcing full sync next time                      */
	/* If hostmap revision is 0 it indicates that hostmap is not defined for the */
	/* proxy group. Return 'no sync' to prevent unneeded sync of proxy list and  */
	/* empty host map each sync period.                                          */
	if (NULL != (proxy = (zbx_pg_proxy_t *)zbx_hashset_search(&pgs->cache->proxies, &proxyid)) &&
			NULL != proxy->group && 0 != (hostmap_revision = proxy->group->hostmap_revision))
	{
		failover_delay = proxy->group->failover_delay;

		if (0 == proxy_hostmap_revision || proxy_hostmap_revision > hostmap_revision ||
				SEC_PER_DAY <= now - proxy->sync_time)
		{
			/* either proxy or server has been restarted or too much time has passed - */
			/* process with full sync                                                  */
			mode = ZBX_PROXY_SYNC_FULL;
		}
		else if (proxy_hostmap_revision < hostmap_revision)
		{
			for (int i = 0; i < proxy->deleted_group_hosts.values_num;)
			{
				if (proxy->deleted_group_hosts.values[i].revision <= proxy_hostmap_revision)
					zbx_vector_pg_host_remove_noorder(&proxy->deleted_group_hosts, i);
				else
					i++;
			}

			mode = ZBX_PROXY_SYNC_PARTIAL;
		}

		proxy->sync_time = now;
	}

	data_len = sizeof(unsigned char) + sizeof(zbx_uint64_t);
	zbx_serialize_prepare_str_len(data_len, failover_delay, failover_delay_len);

	if (ZBX_PROXY_SYNC_PARTIAL == mode)
	{
		data_len += (zbx_uint32_t)(sizeof(int) + (size_t)proxy->deleted_group_hosts.values_num *
				sizeof(zbx_uint64_t));
	}

	ptr = data = (unsigned char *)zbx_malloc(NULL, data_len);
	ptr += zbx_serialize_value(ptr, mode);
	ptr += zbx_serialize_value(ptr, hostmap_revision);
	ptr += zbx_serialize_str(ptr, failover_delay, failover_delay_len);

	if (ZBX_PROXY_SYNC_PARTIAL == mode)
	{
		ptr += zbx_serialize_value(ptr, proxy->deleted_group_hosts.values_num);

		for (int i = 0; i < proxy->deleted_group_hosts.values_num; i++)
			ptr += zbx_serialize_value(ptr, proxy->deleted_group_hosts.values[i].hostproxyid);
	}

	pg_cache_unlock(pgs->cache);

	zbx_ipc_client_send(client, ZBX_IPC_PGM_PROXY_SYNC_DATA, data, data_len);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy group statistics                                        *
 *                                                                            *
 * Parameter: pgs     - [IN] proxy group service                              *
 *            message - [IN] IPC message with host relocation data            *
 *                                                                            *
 ******************************************************************************/
static void	pg_get_proxy_group_stats(zbx_pg_service_t *pgs, zbx_ipc_client_t *client, zbx_ipc_message_t *message)
{
	unsigned char	*ptr, *data;
	int		state, proxies_online_num = 0;
	zbx_uint32_t	data_len;
	zbx_pg_group_t	*pg, *proxy_group = NULL;
	const char	*name = (const char *)message->data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pg_cache_lock(pgs->cache);

	zbx_hashset_iter_t	iter;

	zbx_hashset_iter_reset(&pgs->cache->groups, &iter);
	while (NULL != (pg = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
	{
		if (0 == strcmp(pg->name, name))
		{
			proxy_group = pg;
			break;
		}
	}

	if (NULL != proxy_group)
	{
		data_len = (zbx_uint32_t)((size_t)proxy_group->proxies.values_num * sizeof(zbx_uint64_t) +
				3 * sizeof(int));
		ptr = data = (unsigned char *)zbx_malloc(NULL, data_len);

		for (int i = 0; i < proxy_group->proxies.values_num; i++)
		{
			if (ZBX_PG_PROXY_STATE_ONLINE == proxy_group->proxies.values[i]->state)
				proxies_online_num++;
		}

		ptr += zbx_serialize_value(ptr, proxy_group->state);
		ptr += zbx_serialize_value(ptr, proxies_online_num);
		ptr += zbx_serialize_value(ptr, proxy_group->proxies.values_num);

		for (int i = 0; i < proxy_group->proxies.values_num; i++)
			ptr += zbx_serialize_value(ptr, proxy_group->proxies.values[i]->proxyid);

		zbx_ipc_client_send(client, ZBX_IPC_PGM_STATS, data, data_len);

		zbx_free(data);
	}
	else
	{
		state = -1;
		zbx_ipc_client_send(client, ZBX_IPC_PGM_STATS, (const unsigned char *)&state, sizeof(state));
	}

	pg_cache_unlock(pgs->cache);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets all proxy group real-time data                               *
 *                                                                            *
 * Parameter: pgs     - [IN] proxy group service                              *
 *            client  - [IN] IPC client                                       *
 *                                                                            *
 * Comments: skips standalone proxies                                         *
 *                                                                            *
 ******************************************************************************/
static void	pg_get_all_pgroup_rtdata(zbx_pg_service_t *pgs, zbx_ipc_client_t *client)
{
#define PROXY_GROUP_LEN	(sizeof(zbx_uint64_t) + 3 * sizeof(int))
	int			num_pgroups = 0;
	zbx_uint32_t		data_len;
	unsigned char		*ptr, *data;
	zbx_hashset_iter_t	iter;
	zbx_pg_group_t		*pg;
#define HEADER_LEN	sizeof(num_pgroups)
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pg_cache_lock(pgs->cache);

	data_len = (zbx_uint32_t)(HEADER_LEN + (size_t)pgs->cache->groups.num_data * PROXY_GROUP_LEN);
	ptr = data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr += HEADER_LEN;

	zbx_hashset_iter_reset(&pgs->cache->groups, &iter);
	while (NULL != (pg = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
	{
		/* skip standalone proxies */
		if (ZBX_PG_GROUP_STATE_DISABLED == pg->state)
			continue;

		int	proxies_online_num = 0;

		for (int i = 0; i < pg->proxies.values_num; i++)
		{
			if (ZBX_PG_PROXY_STATE_ONLINE == pg->proxies.values[i]->state)
				proxies_online_num++;
		}

		ptr += zbx_serialize_value(ptr, pg->proxy_groupid);
		ptr += zbx_serialize_value(ptr, pg->state);
		ptr += zbx_serialize_value(ptr, proxies_online_num);
		ptr += zbx_serialize_value(ptr, pg->proxies.values_num);

		num_pgroups++;
	}

	(void)zbx_serialize_value(data, num_pgroups);

	data_len = (zbx_uint32_t)(HEADER_LEN + (size_t)num_pgroups * PROXY_GROUP_LEN);
	zbx_ipc_client_send(client, ZBX_IPC_PGM_ALL_PGROUP_RTDATA, data, data_len);

	zbx_free(data);

	pg_cache_unlock(pgs->cache);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
#undef HEADER_LEN
#undef PROXY_GROUP_LEN
}

/******************************************************************************
 *                                                                            *
 * Purpose: proxy group service thread entry                                  *
 *                                                                            *
 ******************************************************************************/
static void	*pg_service_entry(void *data)
{
	zbx_pg_service_t	*pgs = (zbx_pg_service_t *)data;
	zbx_timespec_t		timeout = {1, 0};
	zbx_ipc_client_t	*client;
	zbx_ipc_message_t	*message;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (ZBX_IS_RUNNING())
	{
		(void)zbx_ipc_service_recv(&pgs->service, &timeout, &client, &message);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_IPC_PGM_HOST_PGROUP_UPDATE:
					pg_update_host_pgroup(pgs, message);
					break;
				case ZBX_IPC_PGM_GET_PROXY_SYNC_DATA:
					pg_get_proxy_sync_data(pgs, client, message);
					break;
				case ZBX_IPC_PGM_GET_STATS:
					pg_get_proxy_group_stats(pgs, client, message);
					break;
				case ZBX_IPC_PGM_PROXY_RTDATA:
					pg_update_proxy_rtdata(pgs, message);
					break;
				case ZBX_IPC_PGM_GET_ALL_PGROUP_RTDATA:
					pg_get_all_pgroup_rtdata(pgs, client);
					break;
				case ZBX_IPC_PGM_STOP:
					goto out;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize proxy group service                                    *
 *                                                                            *
 ******************************************************************************/
int	pg_service_init(zbx_pg_service_t *pgs, zbx_pg_cache_t *cache, char **error)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == zbx_ipc_service_start(&pgs->service, ZBX_IPC_SERVICE_PGSERVICE, error))
		goto out;

	pgs->cache = cache;

	pthread_attr_t	attr;
	int		err;

	zbx_pthread_init_attr(&attr);
	if (0 != (err = pthread_create(&pgs->thread, &attr, pg_service_entry, (void *)pgs)))
	{
		*error = zbx_dsprintf(NULL, "cannot create thread: %s", zbx_strerror(err));
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy proxy group service                                       *
 *                                                                            *
 ******************************************************************************/
void	pg_service_destroy(zbx_pg_service_t *pgs)
{
	zbx_ipc_socket_t	sock;
	char			*error = NULL;

	if (FAIL == zbx_ipc_socket_open(&sock, ZBX_IPC_SERVICE_PGSERVICE, ZBX_PG_SERVICE_TIMEOUT, &error))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot connect to to proxy group manager service: %s", error);
		zbx_free(error);
		return;
	}

	zbx_ipc_socket_write(&sock, ZBX_IPC_PGM_STOP, NULL, 0);
	zbx_ipc_socket_close(&sock);

	void	*retval;

	pthread_join(pgs->thread, &retval);
}
