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

#include "ha.h"

#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "zbx_ha_constants.h"

/******************************************************************************
 *                                                                            *
 * Purpose: get HA nodes in json format                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_get_nodes(char **nodes, char **error)
{
	unsigned char	*data, *ptr;
	zbx_uint32_t	str_len;
	int		ret;
	char		*str;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_GET_NODES,
			ZBX_HA_SERVICE_TIMEOUT, NULL, 0, &data, error))
	{
		return FAIL;
	}

	ptr = data;
	ptr += zbx_deserialize_value(ptr, &ret);
	(void)zbx_deserialize_str(ptr, &str, str_len);
	zbx_free(data);

	if (SUCCEED == ret)
		*nodes = str;
	else
		*error = str;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove HA node                                                    *
 *                                                                            *
 * Comments: A new socket is opened to avoid interfering with notification    *
 *           channel                                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_remove_node(const char *node, char **result, char **error)
{
	unsigned char	*data, *ptr;
	zbx_uint32_t	error_len, result_len;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_REMOVE_NODE,
			ZBX_HA_SERVICE_TIMEOUT, (const unsigned char *)node, (zbx_uint32_t)strlen(node) + 1, &data,
			error))
	{
		return FAIL;
	}

	ptr = data;
	ptr += zbx_deserialize_str(ptr, result, result_len);
	(void)zbx_deserialize_str(ptr, error, error_len);
	zbx_free(data);

	return (0 == error_len ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set HA failover delay                                             *
 *                                                                            *
 * Comments: A new socket is opened to avoid interfering with notification    *
 *           channel                                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_set_failover_delay(int delay, char **error)
{
	unsigned char	*data;
	zbx_uint32_t	error_len;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_SET_FAILOVER_DELAY,
			ZBX_HA_SERVICE_TIMEOUT, (unsigned char *)&delay, sizeof(delay), &data, error))
	{
		return FAIL;
	}

	(void)zbx_deserialize_str(data, error, error_len);
	zbx_free(data);

	return (0 == error_len ? SUCCEED : FAIL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get HA failover delay                                             *
 *                                                                            *
 * Comments: A new socket is opened to avoid interfering with notification    *
 *           channel                                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_get_failover_delay(int *delay, char **error)
{
	unsigned char	*data;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, ZBX_IPC_SERVICE_HA_GET_FAILOVER_DELAY,
			ZBX_HA_SERVICE_TIMEOUT, NULL, 0, &data, error))
	{
		return FAIL;
	}

	memcpy(delay, data, sizeof(*delay));
	zbx_free(data);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: change HA manager log level                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_ha_change_loglevel(int direction, char **error)
{
	int		ret = FAIL;
	zbx_uint32_t	cmd;
	unsigned char	*result = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	cmd = 0 < direction ? ZBX_IPC_SERVICE_HA_LOGLEVEL_INCREASE : ZBX_IPC_SERVICE_HA_LOGLEVEL_DECREASE;

	ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_HA, cmd, ZBX_HA_SERVICE_TIMEOUT, NULL, 0, &result, error);
	zbx_free(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get HA status in text format                                      *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_ha_status_str(int ha_status)
{
	switch (ha_status)
	{
		case ZBX_NODE_STATUS_STANDBY:
			return "standby";
		case ZBX_NODE_STATUS_STOPPED:
			return "stopped";
		case ZBX_NODE_STATUS_UNAVAILABLE:
			return "unavailable";
		case ZBX_NODE_STATUS_ACTIVE:
			return "active";
		case ZBX_NODE_STATUS_ERROR:
			return "error";
		default:
			return "unknown";
	}
}
