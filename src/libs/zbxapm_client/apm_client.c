/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbx_apm_client.h"

#include "zbxcommon.h"
#include "zbxserialize.h"
#include "zbxipcservice.h"

int	zbx_apm_get_stats(zbx_apm_stats_t *stats, char **error)
{
	zbx_ipc_socket_t	socket;
	char			*errmsg = NULL;
	int			ret = FAIL;
	zbx_ipc_message_t	response = {0};
	unsigned char		*ptr;

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_APM, SEC_PER_MIN, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot connect to CEP service: %s", errmsg);
		zbx_free(errmsg);
		return ret;
	}

	if (FAIL == zbx_ipc_socket_write(&socket, ZBX_APM_GET_STATS, NULL, 0))
	{
		*error = zbx_strdup(NULL, "cannot send get stats message to CEP service");
		goto out;
	}

	if (FAIL == zbx_ipc_socket_read(&socket, &response))
	{
		*error = zbx_strdup(NULL, "cannot send delete events message to CEP service");
		goto out;
	}

	ptr = response.data;
	ptr += zbx_deserialize_value(ptr, &stats->written_logs);
	ptr += zbx_deserialize_value(ptr, &stats->written_traces);
	ptr += zbx_deserialize_value(ptr, &stats->written_metrics_gauge);
	ptr += zbx_deserialize_value(ptr, &stats->written_metrics_sum);
	ptr += zbx_deserialize_value(ptr, &stats->written_metrics_histogram);
	ptr += zbx_deserialize_value(ptr, &stats->written_metrics_exponential_histogram);
	ptr += zbx_deserialize_value(ptr, &stats->written_metrics_summary);
	(void)zbx_deserialize_value(ptr, &stats->processed_requests);

	zbx_ipc_message_clean(&response);

	ret = SUCCEED;
out:
	zbx_ipc_socket_close(&socket);

	return ret;
}

zbx_uint32_t	zbx_apm_serialize_stats(zbx_apm_stats_t *stats, unsigned char *buf, zbx_uint64_t len)
{
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, stats->written_logs);
	zbx_serialize_prepare_value(data_len, stats->written_traces);
	zbx_serialize_prepare_value(data_len, stats->written_metrics_gauge);
	zbx_serialize_prepare_value(data_len, stats->written_metrics_sum);
	zbx_serialize_prepare_value(data_len, stats->written_metrics_histogram);
	zbx_serialize_prepare_value(data_len, stats->written_metrics_exponential_histogram);
	zbx_serialize_prepare_value(data_len, stats->written_metrics_summary);
	zbx_serialize_prepare_value(data_len, stats->processed_requests);

	if (data_len > len)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("insufficient buffer size to serialize APM stats");
		return 0;
	}

	buf += zbx_serialize_value(buf, stats->written_logs);
	buf += zbx_serialize_value(buf, stats->written_traces);
	buf += zbx_serialize_value(buf, stats->written_metrics_gauge);
	buf += zbx_serialize_value(buf, stats->written_metrics_sum);
	buf += zbx_serialize_value(buf, stats->written_metrics_histogram);
	buf += zbx_serialize_value(buf, stats->written_metrics_exponential_histogram);
	buf += zbx_serialize_value(buf, stats->written_metrics_summary);
	(void)zbx_serialize_value(buf, stats->processed_requests);

	return data_len;
}

