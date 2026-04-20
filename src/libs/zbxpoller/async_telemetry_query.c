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

#include "async_telemetry_query.h"
#include "module.h"
#include "zbxcommon.h"
#include "zbxtelemetry.h"
#include "zbxtypes.h"
#include <time.h>

#ifdef HAVE_LIBCURL
void	zbx_async_check_telemetry_query_clean(zbx_telemetry_query_context *telemetry_query_context)
{
	zbx_free(telemetry_query_context->item_context.posts);
	zbx_http_context_destroy(&telemetry_query_context->http_context);

	zbx_tq_query_clean(telemetry_query_context->item_context.query);
	zbx_free(telemetry_query_context->item_context.query);
}

/******************************************************************************
 *                                                                            *
 * Comments: takes ownership of query on success                              *
 *                                                                            *
 ******************************************************************************/
static int	async_send_telemetry_query_clickhouse(zbx_dc_telemetry_query_item_t *item, zbx_tq_query_t *query,
		time_t now, time_t lasttimestamp, const zbx_tq_conn_params_clickhouse_t *conn_params,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, CURLM *curl_handle, char **error)
{
	char				*http_error = NULL;
	zbx_telemetry_query_context	*telemetry_query_context;
	CURLcode			err;
	CURLMcode			merr;
	char				query_fields[] = "", headers[] = "";

	telemetry_query_context = zbx_malloc(NULL, sizeof(zbx_telemetry_query_context));

	zbx_http_context_create(&telemetry_query_context->http_context);

	telemetry_query_context->item_context.itemid = item->itemid;
	telemetry_query_context->item_context.value_type = item->value_type;
	telemetry_query_context->item_context.flags = item->flags;
	telemetry_query_context->item_context.preprocessing = item->preprocessing;

	zbx_tq_sql_generate_clickhouse(query, now, lasttimestamp, &telemetry_query_context->item_context.posts);
	telemetry_query_context->item_context.query = query;

	zabbix_log(LOG_LEVEL_TRACE, "%s(): generated SQL: '%s'", __func__, telemetry_query_context->item_context.posts);

	if (SUCCEED != zbx_http_request_prepare(&telemetry_query_context->http_context, HTTP_REQUEST_POST,
			conn_params->url, query_fields, headers, telemetry_query_context->item_context.posts,
			ZBX_RETRIEVE_MODE_CONTENT, NULL, 0, item->timeout, conn_params->max_attempts,
			conn_params->ssl_cert_file, conn_params->ssl_key_file, conn_params->ssl_key_password,
			conn_params->verify_peer, conn_params->verify_host, conn_params->authtype,
			conn_params->username, conn_params->password, conn_params->token, ZBX_POSTTYPE_RAW,
			HTTP_STORE_RAW, config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
			config_ssl_key_location, &http_error))
	{
		*error = http_error;
		http_error = NULL;

		goto fail;
	}

	if (CURLE_OK != (err = curl_easy_setopt(telemetry_query_context->http_context.easyhandle, CURLOPT_PRIVATE,
			telemetry_query_context)))
	{
		*error = zbx_dsprintf(NULL, "Cannot set pointer to private data: %s", curl_easy_strerror(err));

		goto fail;
	}

	if (CURLM_OK != (merr = curl_multi_add_handle(curl_handle, telemetry_query_context->http_context.easyhandle)))
	{
		*error = zbx_dsprintf(NULL, "Cannot add a standard curl handle to the multi stack: %s",
				curl_multi_strerror(merr));

		goto fail;
	}

	/* telemetry_query_context is associated with this curl handle and will be freed when handle is freed */
	return SUCCEED;
fail:
	zbx_async_check_telemetry_query_clean(telemetry_query_context);
	zbx_free(telemetry_query_context);

	return NOTSUPPORTED;
}

static int	async_check_telemetry_query_clickhouse(zbx_dc_telemetry_query_item_t *item, AGENT_RESULT *result,
		zbx_poller_config_t *poller_config)
{
	int		ret = NOTSUPPORTED;
	time_t		now;
	time_t		lasttimestamp;
	char		*send_error = NULL;
	zbx_tq_query_t	*query;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " key:'%s'", __func__, item->itemid, item->key);

	now = time(NULL);
	query = zbx_malloc(NULL, sizeof(zbx_tq_query_t));

	/* FIXME: placeholder */
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
	const char *config_source_ip		= poller_config->config_source_ip;
	const char *config_ssl_ca_location	= poller_config->config_ssl_ca_location;
	const char *config_ssl_cert_location	= poller_config->config_ssl_cert_location;
	const char *config_ssl_key_location	= poller_config->config_ssl_key_location;

	/* FIXME: placeholder */
	lasttimestamp = 0;

	if (FAIL == zbx_tq_query_from_json(item->query_fields, query))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid query format"));
		zbx_free(query);

		goto out;
	}

	if (SUCCEED != async_send_telemetry_query_clickhouse(item, query, now, lasttimestamp, &conn_params,
			config_source_ip, config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location,
			poller_config->curl_handle, &send_error))
	{
		SET_MSG_RESULT(result, send_error);
		zbx_tq_query_clean(query);
		zbx_free(query);

		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
#endif

int	zbx_async_check_telemetry_query(zbx_dc_telemetry_query_item_t *item, AGENT_RESULT *result,
		zbx_poller_config_t *poller_config)
{
	int ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " key:'%s'", __func__, item->itemid, item->key);

	/* TODO: do other things if db type is different */
#ifdef HAVE_LIBCURL
	ret = async_check_telemetry_query_clickhouse(item, result, poller_config);
#elif
	SET_MSG_RESULT(result, zbx_strdup(NULL, "cURL library was not compiled in"));
	ret = NOTSUPPORTED;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
