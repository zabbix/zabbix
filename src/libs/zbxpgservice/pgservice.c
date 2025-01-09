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

#include "zbxpgservice.h"
#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "zbxtypes.h"

static ZBX_THREAD_LOCAL	zbx_ipc_socket_t	pgservice_sock;

ZBX_VECTOR_IMPL(objmove, zbx_objmove_t)

/******************************************************************************
 *                                                                            *
 * Purpose: send object relocation updates to proxy group service             *
 *                                                                            *
 * Comments: used only by server                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_pg_update_object_relocations(zbx_uint32_t code, zbx_vector_objmove_t *updates)
{
	if (0 == updates->values_num)
		return;

	if (0 == pgservice_sock.fd)
	{
		char	*error = NULL;

		if (FAIL == zbx_ipc_socket_open(&pgservice_sock, ZBX_IPC_SERVICE_PGSERVICE, ZBX_PG_SERVICE_TIMEOUT,
				&error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "Cannot connect to proxy group manager service: %s", error);
			zbx_free(error);
			exit(EXIT_FAILURE);
		}
	}

	unsigned char	*data, *ptr;

	ptr = data = (unsigned char *)zbx_malloc(NULL, sizeof(zbx_uint64_t) * 3 * (size_t)updates->values_num);

	for (int i = 0; i < updates->values_num; i++)
	{
		ptr += zbx_serialize_value(ptr, updates->values[i].objid);
		ptr += zbx_serialize_value(ptr, updates->values[i].srcid);
		ptr += zbx_serialize_value(ptr, updates->values[i].dstid);
	}

	if (FAIL == zbx_ipc_socket_write(&pgservice_sock, code, data, (zbx_uint32_t)(ptr - data)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot send data to proxy group manager service");
		exit(EXIT_FAILURE);
	}

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update proxy runtime data                                         *
 *                                                                            *
 * Comments: used only by server                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_pg_update_proxy_rtdata(zbx_uint64_t proxyid, int lastaccess, int version)
{
	if (0 == pgservice_sock.fd)
	{
		char	*error = NULL;

		if (FAIL == zbx_ipc_socket_open(&pgservice_sock, ZBX_IPC_SERVICE_PGSERVICE, ZBX_PG_SERVICE_TIMEOUT,
				&error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "Cannot connect to proxy group manager service: %s", error);
			zbx_free(error);
			exit(EXIT_FAILURE);
		}
	}

	unsigned char	data[sizeof(proxyid) + sizeof(lastaccess) + sizeof(version)], *ptr = data;

	ptr += zbx_serialize_value(ptr, proxyid);
	ptr += zbx_serialize_value(ptr, lastaccess);
	ptr += zbx_serialize_value(ptr, version);

	if (FAIL == zbx_ipc_socket_write(&pgservice_sock, ZBX_IPC_PGM_PROXY_RTDATA, data,
			(zbx_uint32_t)(ptr - data)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot send data to proxy group manager service");
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy group statistics                                        *
 *                                                                            *
 * Comments: used only by server                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_pg_get_stats(const char *pg_name, zbx_pg_stats_t *pg_stats, char **error)
{
	zbx_ipc_socket_t	sock, *psock;
	int			ret = FAIL, proxyids_num, status;
	zbx_ipc_message_t	message = {0};
	const unsigned char	*ptr = NULL;

	if (0 == pgservice_sock.fd)
	{
		if (FAIL == zbx_ipc_socket_open(&sock, ZBX_IPC_SERVICE_PGSERVICE, ZBX_PG_SERVICE_TIMEOUT, error))
			return FAIL;

		psock = &sock;
	}
	else
		psock = &pgservice_sock;

	if (FAIL == zbx_ipc_socket_write(psock, ZBX_IPC_PGM_GET_STATS, (const unsigned char *)pg_name,
			(zbx_uint32_t)strlen(pg_name) + 1))
	{
		*error = zbx_strdup(NULL, "Cannot send request to proxy group manager service");
		goto out;
	}

	if (FAIL == zbx_ipc_socket_read(psock, &message))
	{
		*error = zbx_strdup(NULL, "Cannot read proxy group manager service response");
		goto out;
	}

	ptr = message.data;
	ptr += zbx_deserialize_value(ptr, &status);

	if (-1 == status)
	{
		*error = zbx_dsprintf(NULL, "Unknown proxy group \"%s\"", pg_name);
		goto out;
	}

	pg_stats->status = status;
	ptr += zbx_deserialize_value(ptr, &pg_stats->proxy_online_num);
	ptr += zbx_deserialize_value(ptr, &proxyids_num);

	zbx_vector_uint64_create(&pg_stats->proxyids);
	if (0 != proxyids_num)
	{
		zbx_vector_uint64_reserve(&pg_stats->proxyids, (size_t)proxyids_num);

		for (int i = 0; i < proxyids_num; i++)
		{
			zbx_uint64_t	proxyid;

			ptr += zbx_deserialize_value(ptr, &proxyid);
			zbx_vector_uint64_append(&pg_stats->proxyids, proxyid);
		}
	}

	ret = SUCCEED;
out:
	zbx_ipc_message_clean(&message);

	if (psock == &sock)
		zbx_ipc_socket_close(psock);

	return ret;
}
/******************************************************************************
 *                                                                            *
 * Purpose: gets all proxy group real-time data from proxy group manager      *
 *                                                                            *
 * Parameter: pgroups_rtdata - [OUT] proxy groups real-time data              *
 *            error          - [OUT]                                          *
 *                                                                            *
 * Comments: used only by server                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_pg_get_all_rtdata(zbx_hashset_t *pgroups_rtdata, char **error)
{
	zbx_ipc_socket_t	sock, *psock;
	int			ret = FAIL, pgroups_num;
	zbx_ipc_message_t	message = {0};
	const unsigned char	*ptr = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == pgservice_sock.fd)
	{
		if (FAIL == zbx_ipc_socket_open(&sock, ZBX_IPC_SERVICE_PGSERVICE, ZBX_PG_SERVICE_TIMEOUT, error))
			goto out;

		psock = &sock;
	}
	else
		psock = &pgservice_sock;

	if (FAIL == zbx_ipc_socket_write(psock, ZBX_IPC_PGM_GET_ALL_PGROUP_RTDATA, NULL, 0))
	{
		*error = zbx_strdup(NULL, "Cannot send request to proxy group manager service");
		goto clean;
	}

	if (FAIL == zbx_ipc_socket_read(psock, &message))
	{
		*error = zbx_strdup(NULL, "Cannot read proxy group manager service response");
		goto clean;
	}

	ptr = message.data;
	ptr += zbx_deserialize_value(ptr, &pgroups_num);

	for (int i = 0; i < pgroups_num; i++)
	{
		zbx_pg_rtdata_t	pg_rtdata;

		ptr += zbx_deserialize_value(ptr, &pg_rtdata.proxy_groupid);
		ptr += zbx_deserialize_value(ptr, &pg_rtdata.status);
		ptr += zbx_deserialize_value(ptr, &pg_rtdata.proxy_online_num);
		ptr += zbx_deserialize_value(ptr, &pg_rtdata.proxy_num);

		(void)zbx_hashset_insert(pgroups_rtdata, &pg_rtdata, sizeof(pg_rtdata));
	}

	ret = SUCCEED;
clean:
	zbx_ipc_message_clean(&message);

	if (psock == &sock)
		zbx_ipc_socket_close(psock);
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
