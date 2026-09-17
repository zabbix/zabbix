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
#include "zbxtime.h"
#include "zbxstr.h"

#ifdef HAVE_LIBCURL
void	zbx_async_check_telemetry_query_clean(zbx_telemetry_query_context *telemetry_query_context)
{
	zbx_http_context_destroy(&telemetry_query_context->http_context);

	zbx_free(telemetry_query_context->item_context.posts);

	if (NULL != telemetry_query_context->item_context.query)
	{
		zbx_tq_query_clean(telemetry_query_context->item_context.query);
		zbx_free(telemetry_query_context->item_context.query);
	}
}

/******************************************************************************
 *                                                                            *
 * Comments: takes ownership of query on success                              *
 *                                                                            *
 ******************************************************************************/
static int	async_send_telemetry_query_http(zbx_dc_telemetry_query_item_t *item, zbx_tq_query_t *query,
		time_t now, time_t lasttimestamp, const zbx_timespec_t *min_free_ts,
		const zbx_apm_db_config_t *apm_db_config, const char *url, unsigned char post_type,
		unsigned char output_format, CURLM *curl_handle, char **error)
{
	char				*http_error = NULL;
	zbx_telemetry_query_context	*telemetry_query_context;
	CURLcode			err;
	CURLMcode			merr;
	char				query_fields[] = "", headers[] = "";

	unsigned char	authtype = (NULL != apm_db_config->username ? HTTPTEST_AUTH_BASIC : HTTPTEST_AUTH_NONE);

	telemetry_query_context = zbx_malloc(NULL, sizeof(zbx_telemetry_query_context));

	memset(&telemetry_query_context->item_context, 0, sizeof(telemetry_query_context->item_context));
	zbx_http_context_create(&telemetry_query_context->http_context);

	telemetry_query_context->item_context.itemid = item->itemid;
	telemetry_query_context->item_context.value_type = item->value_type;
	telemetry_query_context->item_context.flags = item->flags;
	telemetry_query_context->item_context.preprocessing = item->preprocessing;

	if (ZBX_APM_DB_TYPE_CLICKHOUSE != apm_db_config->db_type)
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_tq_sql_generate_clickhouse(query, item->time_shift, item->lookback_limit, item->granularity, now,
			lasttimestamp, &telemetry_query_context->item_context.posts);

	zbx_tq_get_newlasttimestamp(item->lookback_limit, item->granularity, now, lasttimestamp,
			&telemetry_query_context->item_context.newlasttimestamp);
	telemetry_query_context->item_context.min_free_ts = *min_free_ts;
	telemetry_query_context->item_context.db_type = apm_db_config->db_type;

	if (SUCCEED != zbx_http_request_prepare(&telemetry_query_context->http_context, HTTP_REQUEST_POST, url,
			query_fields, headers, telemetry_query_context->item_context.posts, ZBX_RETRIEVE_MODE_CONTENT,
			NULL, 0, item->timeout, 1, apm_db_config->ssl_cert_file, apm_db_config->ssl_key_file,
			apm_db_config->ssl_key_password, apm_db_config->ssl_verify_peer, apm_db_config->ssl_verify_host,
			authtype, apm_db_config->username, apm_db_config->password, NULL, post_type, output_format,
			apm_db_config->source_ip, apm_db_config->ssl_ca_location, apm_db_config->ssl_cert_location,
			apm_db_config->ssl_key_location, &http_error))
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

	telemetry_query_context->item_context.query = query;

	/* telemetry_query_context is associated with this curl handle and will be freed when handle is freed */
	return SUCCEED;
fail:
	zbx_async_check_telemetry_query_clean(telemetry_query_context);
	zbx_free(telemetry_query_context);

	return NOTSUPPORTED;
}

static int	async_check_telemetry_query_http(zbx_dc_telemetry_query_item_t *item, time_t now, time_t lasttimestamp,
		AGENT_RESULT *result, zbx_poller_config_t *poller_config, const zbx_apm_db_config_t *apm_db_config)
{
	int		ret = NOTSUPPORTED;
	char		*send_error = NULL;
	zbx_tq_query_t	*query;
	char		*url;
	unsigned char	post_type, output_format;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " key:'%s'", __func__, item->itemid, item->key);

	query = item->telemetry_query;
	item->telemetry_query = NULL;

	if (ZBX_APM_DB_TYPE_CLICKHOUSE != apm_db_config->db_type)
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_tq_clickhouse_get_query_url(apm_db_config->url, apm_db_config->db, &url);

	post_type = ZBX_POSTTYPE_RAW;
	output_format = HTTP_STORE_RAW;

	if (SUCCEED != async_send_telemetry_query_http(item, query, now, lasttimestamp, &item->min_free_ts,
			apm_db_config, url, post_type, output_format, poller_config->curl_handle, &send_error))
	{
		SET_MSG_RESULT(result, send_error);
		zbx_tq_query_clean(query);
		zbx_free(query);

		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(url);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
#endif

int	zbx_async_check_telemetry_query(zbx_dc_telemetry_query_item_t *item, AGENT_RESULT *result,
		zbx_poller_config_t *poller_config, const zbx_apm_db_config_t *apm_db_config)
{
	int	ret;
	time_t	now, lasttimestamp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " key:'%s'", __func__, item->itemid, item->key);

	now = time(NULL);

	lasttimestamp = item->lasttimestamp;

	/* lastlogsize is used to store lasttimestamp persistently throughout restarts and monitored_by changes */
	if ((time_t)item->lastlogsize > item->lasttimestamp)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): setting lasttimestamp to lastlogsize", __func__);
		lasttimestamp = (time_t)item->lastlogsize;
	}

	zabbix_log(LOG_LEVEL_DEBUG,
			"%s(): lasttimestamp: " ZBX_FS_TIME_T ", lastlogsize: " ZBX_FS_UI64 ", max: " ZBX_FS_TIME_T,
			__func__, (zbx_fs_time_t)item->lasttimestamp, item->lastlogsize, (zbx_fs_time_t)lasttimestamp);

	if (0 == apm_db_config->status)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "APM database is not configured"));
		ret = NOTSUPPORTED;
		goto out;
	}

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == apm_db_config->db_type)
	{
#ifdef HAVE_LIBCURL
		ret = async_check_telemetry_query_http(item, now, lasttimestamp, result, poller_config, apm_db_config);
#else
		ZBX_UNUSED(poller_config);
		ZBX_UNUSED(now);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "cURL library was not compiled in"));
		ret = NOTSUPPORTED;
#endif
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported APM data source type"));
		ret = NOTSUPPORTED;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
