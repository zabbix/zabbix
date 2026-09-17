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

#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxtelemetry.h"
#include "zbxstr.h"
#include "zbxtypes.h"

#ifdef HAVE_LIBCURL
#	include "zbxhttp.h"
#endif

#ifdef HAVE_LIBCURL
static int	send_query_http(const char *posts, const zbx_apm_db_config_t *apm_db_config, const char *url,
		int timeout, unsigned char post_type, unsigned char output_format, char **out, char **error)
{
	int			ret;
	long			response_code;
	zbx_http_context_t	context;
	char			*http_out = NULL;
	char			*http_error = NULL;
	char			query_fields[] = "", headers[] = "", status_codes[] = "200,201,202,203,204";

	unsigned char	authtype = (NULL != apm_db_config->username ? HTTPTEST_AUTH_BASIC : HTTPTEST_AUTH_NONE);

	zbx_http_context_create(&context);

	if (SUCCEED == zbx_http_request_prepare(&context, HTTP_REQUEST_POST, url, query_fields, headers,
			posts, ZBX_RETRIEVE_MODE_CONTENT, NULL, 0, timeout, 1, apm_db_config->ssl_cert_file,
			apm_db_config->ssl_key_file, apm_db_config->ssl_key_password, apm_db_config->ssl_verify_peer,
			apm_db_config->ssl_verify_host, authtype, apm_db_config->username, apm_db_config->password,
			NULL, post_type, output_format, apm_db_config->source_ip, apm_db_config->ssl_ca_location,
			apm_db_config->ssl_cert_location, apm_db_config->ssl_key_location, &http_error))
	{
		CURLcode	err = zbx_http_request_sync_perform(context.easyhandle, &context, 0,
				ZBX_HTTP_IGNORE_RESPONSE_CODE);

		if (SUCCEED == zbx_http_handle_response(context.easyhandle, &context, err, &response_code, &http_out,
				&http_error) && SUCCEED == zbx_handle_response_code(status_codes, response_code,
				http_out, &http_error))
		{
			ret = SUCCEED;
		}
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

	zbx_free(http_out);
	zbx_free(http_error);
	zbx_http_context_destroy(&context);

	return ret;
}

static int	get_values_telemetry_http(zbx_dc_item_t *item, time_t now, time_t lasttimestamp,
		const zbx_apm_db_config_t *apm_db_config, zbx_vector_str_t *values, char **error)
{
	int			ret = FAIL, parse_ret;
	char			*posts = NULL;
	char			*resp = NULL;
	zbx_tq_query_t		*query = item->telemetry_query;
	char			*url = NULL;
	unsigned char		post_type, output_format;

	if (ZBX_APM_DB_TYPE_CLICKHOUSE != apm_db_config->db_type)
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_tq_clickhouse_get_query_url(apm_db_config->url, apm_db_config->db, &url);

	post_type = ZBX_POSTTYPE_RAW;
	output_format = HTTP_STORE_RAW;

	zbx_tq_sql_generate_clickhouse(query, item->time_shift, item->lookback_limit, item->granularity,
			now, lasttimestamp, &posts);

	if (SUCCEED != send_query_http(posts, apm_db_config, url, item->timeout, post_type, output_format, &resp,
			error))
	{
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): response: '%s'", __func__, ZBX_NULL2STR(resp));

	if (FAIL == (parse_ret = zbx_tq_clickhouse_parse_resp(query, resp, values)))
	{
		*error = zbx_strdup(NULL, "Failed to parse data store response");
		goto out;
	}
	else if (SUCCEED_PARTIAL == parse_ret)
	{
		/* when testing an item, if result is truncated, the check is considered not successful */
		*error = zbx_dsprintf(NULL, "telemetry query result row limit (%d) exceeded", ZBX_TQ_MAX_RESULT_ROWS);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(url);
	zbx_free(posts);
	zbx_free(resp);

	return ret;
}
#endif

int	get_value_telemetry(zbx_dc_item_t *item, const zbx_apm_db_config_t *apm_db_config, AGENT_RESULT *result)
{
	int			ret = NOTSUPPORTED, values_ret;
	time_t			now, lasttimestamp;
	zbx_vector_str_t	values;
	char			*error = NULL;

	if (0 == apm_db_config->have_local_config)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "APM database is not configured"));
		return NOTSUPPORTED;
	}

	now = time(NULL);

	/* when testing the item, the time range being queried is restricted only by the lookback limit */
	lasttimestamp = 0;

	zbx_vector_str_create(&values);

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == apm_db_config->db_type)
	{
#ifdef HAVE_LIBCURL
		values_ret = get_values_telemetry_http(item, now, lasttimestamp, apm_db_config, &values, &error);
#else
		ZBX_UNUSED(item);
		ZBX_UNUSED(lasttimestamp);
		ZBX_UNUSED(now);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "cURL library was not compiled in"));
		goto out;
#endif
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		error = zbx_strdup(NULL, "Unsupported APM data source type");
		values_ret = FAIL;
	}

	if (SUCCEED != values_ret)
	{
		SET_MSG_RESULT(result, error);
		goto out;
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		for (int i = 0; i < values.values_num; i++)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): row #%d: '%s'", __func__, i + 1, values.values[i]);
		}
	}

	if (0 != values.values_num)
	{
		SET_TEXT_RESULT(result, values.values[0]);
		values.values[0] = NULL;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): no buckets, not setting value", __func__);
	}

	ret = SUCCEED;
out:
	zbx_vector_str_clear_ext(&values, zbx_str_free);
	zbx_vector_str_destroy(&values);

	return ret;
}
