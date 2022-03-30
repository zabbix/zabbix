/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "lld_protocol.h"
#include "zbxlld.h"

#include "log.h"
#include "zbxserialize.h"
#include "zbxipcservice.h"
#include "sysinfo.h"

zbx_uint32_t	zbx_lld_serialize_item_value(unsigned char **data, zbx_uint64_t itemid, zbx_uint64_t hostid,
		const char *value, const zbx_timespec_t *ts, unsigned char meta, zbx_uint64_t lastlogsize, int mtime,
		const char *error)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, value_len, error_len;

	zbx_serialize_prepare_value(data_len, itemid);
	zbx_serialize_prepare_value(data_len, hostid);
	zbx_serialize_prepare_str(data_len, value);
	zbx_serialize_prepare_value(data_len, *ts);
	zbx_serialize_prepare_str(data_len, error);

	zbx_serialize_prepare_value(data_len, meta);
	if (0 != meta)
	{
		zbx_serialize_prepare_value(data_len, lastlogsize);
		zbx_serialize_prepare_value(data_len, mtime);
	}

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, itemid);
	ptr += zbx_serialize_value(ptr, hostid);
	ptr += zbx_serialize_str(ptr, value, value_len);
	ptr += zbx_serialize_value(ptr, *ts);
	ptr += zbx_serialize_str(ptr, error, error_len);
	ptr += zbx_serialize_value(ptr, meta);
	if (0 != meta)
	{
		ptr += zbx_serialize_value(ptr, lastlogsize);
		(void)zbx_serialize_value(ptr, mtime);
	}

	return data_len;
}

void	zbx_lld_deserialize_item_value(const unsigned char *data, zbx_uint64_t *itemid, zbx_uint64_t *hostid,
		char **value, zbx_timespec_t *ts, unsigned char *meta, zbx_uint64_t *lastlogsize, int *mtime,
		char **error)
{
	zbx_uint32_t	value_len, error_len;

	data += zbx_deserialize_value(data, itemid);
	data += zbx_deserialize_value(data, hostid);
	data += zbx_deserialize_str(data, value, value_len);
	data += zbx_deserialize_value(data, ts);
	data += zbx_deserialize_str(data, error, error_len);
	data += zbx_deserialize_value(data, meta);
	if (0 != *meta)
	{
		data += zbx_deserialize_value(data, lastlogsize);
		(void)zbx_deserialize_value(data, mtime);
	}
}

zbx_uint32_t	zbx_lld_serialize_diag_stats(unsigned char **data, zbx_uint64_t items_num, zbx_uint64_t values_num)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, items_num);
	zbx_serialize_prepare_value(data_len, values_num);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, items_num);
	(void)zbx_serialize_value(ptr, values_num);

	return data_len;
}

static void	zbx_lld_deserialize_diag_stats(const unsigned char *data, zbx_uint64_t *items_num, zbx_uint64_t *values_num)
{
	data += zbx_deserialize_value(data, items_num);
	(void)zbx_deserialize_value(data, values_num);
}

static zbx_uint32_t	zbx_lld_serialize_top_items_request(unsigned char **data, int limit)
{
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, limit);
	*data = (unsigned char *)zbx_malloc(NULL, data_len);
	(void)zbx_serialize_value(*data, limit);

	return data_len;
}

void	zbx_lld_deserialize_top_items_request(const unsigned char *data, int *limit)
{
	(void)zbx_deserialize_value(data, limit);
}

zbx_uint32_t	zbx_lld_serialize_top_items_result(unsigned char **data, const zbx_lld_rule_info_t **rule_infos,
		int num)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, item_len = 0;
	int		i;

	if (0 != num)
	{
		zbx_serialize_prepare_value(item_len, rule_infos[0]->itemid);
		zbx_serialize_prepare_value(item_len, rule_infos[0]->values_num);
	}

	zbx_serialize_prepare_value(data_len, num);
	data_len += item_len * num;
	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, num);

	for (i = 0; i < num; i++)
	{
		ptr += zbx_serialize_value(ptr, rule_infos[i]->itemid);
		ptr += zbx_serialize_value(ptr, rule_infos[i]->values_num);
	}

	return data_len;
}

static void	zbx_lld_deserialize_top_items_result(const unsigned char *data, zbx_vector_uint64_pair_t *items)
{
	int	i, items_num;

	data += zbx_deserialize_value(data, &items_num);

	if (0 != items_num)
	{
		zbx_vector_uint64_pair_reserve(items, items_num);

		for (i = 0; i < items_num; i++)
		{
			zbx_uint64_pair_t	pair;
			int			value;

			data += zbx_deserialize_value(data, &pair.first);
			data += zbx_deserialize_value(data, &value);
			pair.second = value;
			zbx_vector_uint64_pair_append_ptr(items, &pair);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process low level discovery value/error                           *
 *                                                                            *
 * Parameters: itemid - [IN] the LLD rule id                                  *
 *             hostid - [IN] the host id                                      *
 *             value  - [IN] the rule value (can be NULL if error is set)     *
 *             ts     - [IN] the value timestamp                              *
 *             error  - [IN] the error message (can be NULL)                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_lld_process_value(zbx_uint64_t itemid, zbx_uint64_t hostid, const char *value, const zbx_timespec_t *ts,
		unsigned char meta, zbx_uint64_t lastlogsize, int mtime, const char *error)
{
	static zbx_ipc_socket_t	socket;
	char			*errmsg = NULL;
	unsigned char		*data;
	zbx_uint32_t		data_len;

	/* each process has a permanent connection to manager */
	if (0 == socket.fd && FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_LLD, SEC_PER_MIN, &errmsg))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to LLD manager service: %s", errmsg);
		exit(EXIT_FAILURE);
	}

	data_len = zbx_lld_serialize_item_value(&data, itemid, hostid, value, ts, meta, lastlogsize, mtime, error);

	if (FAIL == zbx_ipc_socket_write(&socket, ZBX_IPC_LLD_REQUEST, data, data_len))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to LLD manager service");
		exit(EXIT_FAILURE);
	}

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process low level discovery agent result                          *
 *                                                                            *
 * Parameters: itemid - [IN] the LLD rule id                                  *
 *             hostid - [IN] the host id                                      *
 *             result - [IN] the agent result                                 *
 *             ts     - [IN] the value timestamp                              *
 *             error  - [IN] the error message (can be NULL)                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_lld_process_agent_result(zbx_uint64_t itemid, zbx_uint64_t hostid, AGENT_RESULT *result,
		zbx_timespec_t *ts, char *error)
{
	const char	*value = NULL;
	unsigned char	meta = 0;
	zbx_uint64_t	lastlogsize = 0;
	int		mtime = 0;

	if (NULL != result)
	{
		if (NULL != GET_TEXT_RESULT(result))
			value = *(GET_TEXT_RESULT(result));

		if (0 != ISSET_META(result))
		{
			meta = 1;
			lastlogsize = result->lastlogsize;
			mtime = result->mtime;
		}
	}

	if (NULL != value || NULL != error || 0 != meta)
		zbx_lld_process_value(itemid, hostid, value, ts, meta, lastlogsize, mtime, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get queue size (enqueued value count) of LLD manager              *
 *                                                                            *
 * Parameters: size  - [OUT] the queue size                                   *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - the queue size was returned successfully           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_lld_get_queue_size(zbx_uint64_t *size, char **error)
{
	zbx_ipc_message_t	message;
	zbx_ipc_socket_t	lld_socket;
	int			ret = FAIL;

	if (FAIL == zbx_ipc_socket_open(&lld_socket, ZBX_IPC_SERVICE_LLD, SEC_PER_MIN, error))
		return FAIL;

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_write(&lld_socket, ZBX_IPC_LLD_QUEUE, NULL, 0))
	{
		*error = zbx_strdup(NULL, "cannot send queue request to LLD manager service");
		goto out;
	}

	if (FAIL == zbx_ipc_socket_read(&lld_socket, &message))
	{
		*error = zbx_strdup(NULL, "cannot read queue response from LLD manager service");
		goto out;
	}

	memcpy(size, message.data, sizeof(zbx_uint64_t));
	ret = SUCCEED;
out:
	zbx_ipc_socket_close(&lld_socket);
	zbx_ipc_message_clean(&message);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get lld manager diagnostic statistics                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_lld_get_diag_stats(zbx_uint64_t *items_num, zbx_uint64_t *values_num, char **error)
{
	unsigned char	*result;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_LLD, ZBX_IPC_LLD_DIAG_STATS, SEC_PER_MIN, NULL, 0,
			&result, error))
	{
		return FAIL;
	}

	zbx_lld_deserialize_diag_stats(result, items_num, values_num);
	zbx_free(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the top N items by the number of queued values                *
 *                                                                            *
 * Parameters limit - [IN] the number of top records to retrieve              *
 *            items - [OUT] a vector of top itemid, values_num pairs          *
 *            error - [OUT] the error message                                 *
 *                                                                            *
 * Return value: SUCCEED - the top n items were returned successfully         *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_lld_get_top_items(int limit, zbx_vector_uint64_pair_t *items, char **error)
{
	int		ret;
	unsigned char	*data, *result;
	zbx_uint32_t	data_len;

	data_len = zbx_lld_serialize_top_items_request(&data, limit);

	if (SUCCEED != (ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_LLD, ZBX_IPC_LLD_TOP_ITEMS, SEC_PER_MIN, data,
			data_len, &result, error)))
	{
		goto out;
	}

	zbx_lld_deserialize_top_items_result(result, items);
	zbx_free(result);
out:
	zbx_free(data);

	return ret;
}
