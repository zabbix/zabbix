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

#include "checks_telemetry.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxtelemetry.h"

int	get_value_telemetry(const zbx_dc_item_t *item, AGENT_RESULT *result)
{
	int		ret = NOTSUPPORTED;
	zbx_tq_query_t	query;
	time_t		now = time(NULL);
	time_t		lasttimestamp;
	char		*send_out = NULL;
	char		*send_error = NULL;

	/* FIXME: placeholder, db type and connection parameters should be gotten from the global config */
	const zbx_tq_conn_params_clickhouse_t	conn_params =
	{
		.url			= "http://127.0.0.1:8123",
		.http_proxy		= NULL,
		.timeout		= item->timeout,
		.max_attempts		= 1,
		.ssl_cert_file		= NULL,
		.ssl_key_file		= NULL,
		.ssl_key_password	= "",
		.verify_peer		= 0,
		.verify_host		= 0,
		.authtype		= HTTPTEST_AUTH_BASIC,
		.username		= "default",
		.password		= "",
		.token			= NULL,
	};
	const char *config_source_ip		= NULL;
	const char *config_ssl_ca_location	= NULL;
	const char *config_ssl_cert_location	= NULL;
	const char *config_ssl_key_location	= NULL;

	/* when testing the item, the time range being queried is restricted only by the loopback limit */
	lasttimestamp = 0;

	if (FAIL == zbx_tq_query_from_json(item->query_fields, &query))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid query format"));
		goto out;
	}

	if (SUCCEED != zbx_tq_send_query_clickhouse(&query, now, lasttimestamp, &conn_params, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &send_out,
			&send_error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Query failed: '%s'", send_error));
		send_error = NULL;
		goto clean;
	}

	SET_TEXT_RESULT(result, send_out);
	send_out = NULL;

	ret = SUCCEED;

clean:
	zbx_tq_query_clean(&query);
	zbx_free(send_out);
	zbx_free(send_error);
out:
	return ret;
}
