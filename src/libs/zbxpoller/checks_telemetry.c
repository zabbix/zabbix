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
#include "zbxhttp.h"
#include "zbxstr.h"

#ifdef HAVE_LIBCURL
static int	send_query_clickhouse_raw(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp,
		const zbx_tq_conn_params_clickhouse_t *conn_params, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **out, char **error)
{
	int			ret;
	char			*sql = NULL;
	long			response_code;
	zbx_http_context_t	context;
	char			*http_out = NULL;
	char			*http_error = NULL;
	char			query_fields[] = "", headers[] = "", status_codes[] = "200,201,202,203,204";

	zbx_tq_sql_generate_clickhouse(query, now, lasttimestamp, &sql);
	zbx_http_context_create(&context);

	zabbix_log(LOG_LEVEL_TRACE, "%s(): generated SQL: '%s'", __func__, sql);

	if (SUCCEED == zbx_http_request_prepare(&context, HTTP_REQUEST_POST, conn_params->url, query_fields, headers,
			sql, ZBX_RETRIEVE_MODE_CONTENT, NULL, 0, conn_params->timeout, conn_params->max_attempts,
			conn_params->ssl_cert_file, conn_params->ssl_key_file, conn_params->ssl_key_password,
			conn_params->verify_peer, conn_params->verify_host, conn_params->authtype,
			conn_params->username, conn_params->password, conn_params->token, ZBX_POSTTYPE_RAW,
			HTTP_STORE_RAW, config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
			config_ssl_key_location, &http_error))
	{
		CURLcode	err = zbx_http_request_sync_perform(context.easyhandle, &context, 0,
				ZBX_HTTP_IGNORE_RESPONSE_CODE);

		if (SUCCEED == zbx_http_handle_response(context.easyhandle, &context, err, &response_code, &http_out,
				&http_error) && SUCCEED == zbx_handle_response_code(status_codes, response_code,
				http_out, &http_error))
			ret = SUCCEED;
		else
			ret = FAIL;
	}
	else
		ret = FAIL;

	if (SUCCEED == ret)
	{
		*out = http_out;
		http_out = NULL;
	}
	else
	{
		*error = http_error;
		http_error = NULL;
	}

	zbx_free(sql);
	zbx_free(http_out);
	zbx_free(http_error);
	zbx_http_context_destroy(&context);

	return ret;
}

static int	send_query_clickhouse(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp,
		const zbx_tq_conn_params_clickhouse_t *conn_params, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **out, char **error)
{
	int	ret = FAIL;
	char	*resp = NULL;

#ifdef HAVE_LIBCURL
	if (SUCCEED != send_query_clickhouse_raw(query, now, lasttimestamp, conn_params, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &resp, error))
		goto out;
#elif
	*error = zbx_strdup(NULL, "cURL was not compiled in");
	goto out;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "%s(): Clickhouse response: '%s'", __func__, ZBX_NULL2STR(resp));

	if (SUCCEED != zbx_tq_clickhouse_resp_to_json(query, resp, out)) {
		*error = zbx_strdup(NULL, "Failed to parse Clickhouse response");
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): resulting json: '%s'", __func__, ZBX_NULL2STR(*out));

	ret = SUCCEED;
out:
	zbx_free(resp);

	return ret;
}
#endif

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

	if (SUCCEED != send_query_clickhouse(&query, now, lasttimestamp, &conn_params, config_source_ip,
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
