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
#include "zbxdb.h"
#include "zbxtelemetry.h"
#include "zbxstr.h"

#ifdef HAVE_LIBCURL
#	include "zbxhttp.h"
#endif

#ifdef HAVE_LIBCURL
static int	send_query_http_raw(const char *posts, const telemetry_query_http_conn_params_t *conn_params,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **out, char **error)
{
	int			ret;
	long			response_code;
	zbx_http_context_t	context;
	char			*http_out = NULL;
	char			*http_error = NULL;
	char			query_fields[] = "", headers[] = "", status_codes[] = "200,201,202,203,204";

	zbx_http_context_create(&context);

	if (SUCCEED == zbx_http_request_prepare(&context, HTTP_REQUEST_POST, conn_params->url, query_fields, headers,
			posts, ZBX_RETRIEVE_MODE_CONTENT, NULL, 0, conn_params->timeout, conn_params->max_attempts,
			conn_params->ssl_cert_file, conn_params->ssl_key_file, conn_params->ssl_key_password,
			conn_params->verify_peer, conn_params->verify_host, conn_params->authtype,
			conn_params->username, conn_params->password, conn_params->token, conn_params->post_type,
			conn_params->output_format, config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
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

	zbx_free(http_out);
	zbx_free(http_error);
	zbx_http_context_destroy(&context);

	return ret;
}

static int	send_query_http(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp, zbx_tq_db_type_t db_type,
		const telemetry_query_http_conn_params_t *conn_params, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, zbx_vector_str_t *values, char **error)
{
	int	ret = FAIL;
	char	*posts = NULL;
	char	*resp = NULL;
	int	parse_ret;

	if (ZBX_TQ_DB_TYPE_CLICKHOUSE == db_type)
		zbx_tq_sql_generate_clickhouse(query, now, lasttimestamp, &posts);
	else
		zbx_tq_generate_elastic(query, now, lasttimestamp, &posts);

	if (SUCCEED != send_query_http_raw(posts, conn_params, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &resp, error))
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): response: '%s'", __func__, ZBX_NULL2STR(resp));


	if (ZBX_TQ_DB_TYPE_CLICKHOUSE == db_type)
		parse_ret = zbx_tq_clickhouse_parse_resp(query, resp, values);
	else
		parse_ret = zbx_tq_elastic_parse_resp(query, resp, values);

	if (SUCCEED != parse_ret)
	{
		*error = zbx_strdup(NULL, "Failed to parse data store response");
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(posts);
	zbx_free(resp);

	return ret;
}

static int	get_values_telemetry_http(const zbx_dc_item_t *item, zbx_tq_db_type_t db_type, time_t now,
		time_t lasttimestamp, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, zbx_vector_str_t *values,
		char **error)
{
	int	ret = FAIL;
	char	*send_error = NULL;
	char	*url = NULL;

	/* FIXME: placeholder start */
	if (ZBX_TQ_DB_TYPE_CLICKHOUSE == db_type)
		url = zbx_strdup(NULL, "http://127.0.0.1:8123");
	else
	{
		url = zbx_dsprintf(NULL, "https://127.0.0.1:9200/%s/_search",
				zbx_tq_elastic_get_index_name(item->telemetry_query->category,
				item->telemetry_query->metric_type));
	}

	const telemetry_query_http_conn_params_t	conn_params =
	{
		.url			= url,
		.http_proxy		= NULL,
		.timeout		= item->timeout,
		.max_attempts		= 1,
		.ssl_cert_file		= NULL,
		.ssl_key_file		= NULL,
		.ssl_key_password	= "",
		.verify_peer		= 0,
		.verify_host		= 0,
		.authtype		= HTTPTEST_AUTH_BASIC,
		.username		= (ZBX_TQ_DB_TYPE_CLICKHOUSE == db_type ? "default" : "elastic"),
		.password		= (ZBX_TQ_DB_TYPE_CLICKHOUSE == db_type ? "" : "U8G9kmwI54r3Gcf5YEs7"),
		.token			= NULL,
		.post_type		= (ZBX_TQ_DB_TYPE_CLICKHOUSE == db_type ? ZBX_POSTTYPE_RAW : ZBX_POSTTYPE_JSON),
		.output_format		= (ZBX_TQ_DB_TYPE_CLICKHOUSE == db_type ? HTTP_STORE_RAW : HTTP_STORE_JSON)
	};
	/* FIXME: placeholder end */

	if (SUCCEED != send_query_http(item->telemetry_query, now, lasttimestamp, db_type, &conn_params,
			config_source_ip, config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location,
			values, &send_error))
	{
		*error = zbx_dsprintf(NULL, "Query failed: '%s'", send_error);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(url);
	zbx_free(send_error);

	return ret;
}
#endif

static int	have_required_db(zbx_tq_db_type_t db_type)
{
#if defined(HAVE_MYSQL)
	return ZBX_TQ_DB_TYPE_MYSQL == db_type ? SUCCEED : FAIL;
#elif defined(HAVE_POSTGRESQL)
	return ZBX_TQ_DB_TYPE_POSTGRESQL == db_type ? SUCCEED : FAIL;
#else
	return FAIL;
#endif
}

static int get_values_telemetry_sql_lib(const zbx_dc_item_t *item, zbx_tq_db_type_t db_type, time_t now,
		time_t lasttimestamp, zbx_vector_str_t *values, char **error)
{
	int		ret = FAIL;
	char		*sql = NULL;
	zbx_db_result_t	sql_result;

	if (FAIL == have_required_db(db_type))
	{
		*error = zbx_dsprintf(NULL, "%s support was not compiled in",
				(ZBX_TQ_DB_TYPE_POSTGRESQL == db_type ? "PostgreSQL" : "MySQL"));
		return FAIL;
	}

	if (ZBX_TQ_DB_TYPE_POSTGRESQL == db_type)
		zbx_tq_sql_generate_postgresql(item->telemetry_query, now, lasttimestamp, &sql);
	else
		zbx_tq_sql_generate_mysql(item->telemetry_query, now, lasttimestamp, &sql);

	/* FIXME: retrying until db is up is probably unwanted, at least if the db is not the same as config db */
	sql_result = zbx_db_select("%s", sql);

	if (NULL == sql_result)
	{
		*error = zbx_strdup(NULL, "Query failed");
		goto clean;
	}

	if (SUCCEED != zbx_tq_parse_sql_result(item->telemetry_query, sql_result, values))
	{
		*error = zbx_strdup(NULL, "Failed to parse result");
		goto clean;
	}

	ret = SUCCEED;
clean:
	zbx_db_free_result(sql_result);
	zbx_free(sql);

	return ret;
}

int	get_value_telemetry(const zbx_dc_item_t *item, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, AGENT_RESULT *result)
{
	time_t			now = time(NULL);
	time_t			lasttimestamp;
	int			values_ret;
	zbx_vector_str_t	values;
	char			*error = NULL;

	/* FIXME: placeholder, also, when data store config is implemented, make sure to avoid race conditions */
	/* if it can be changed at runtime */
	zbx_tq_db_type_t	db_type = ZBX_TQ_DB_TYPE_ELASTIC;

	/* when testing the item, the time range being queried is restricted only by the loopback limit */
	lasttimestamp = 0;

	if (ZBX_TQ_DB_TYPE_CLICKHOUSE == db_type || ZBX_TQ_DB_TYPE_ELASTIC == db_type)
	{
#ifdef HAVE_LIBCURL
		values_ret = get_values_telemetry_http(item, db_type, now, lasttimestamp, config_source_ip,
				config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &values,
				&error);
#else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "cURL library was not compiled in"));
		return NOTSUPPORTED;
#endif
	}
	else
		values_ret = get_values_telemetry_sql_lib(item, db_type, now, lasttimestamp, &values, &error);

	if (SUCCEED != values_ret)
	{
		SET_MSG_RESULT(result, error);
		error = NULL;
		return NOTSUPPORTED;
	}

	for (int i = 0; i < values.values_num; i++)
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): row #%d: '%s'", __func__, i + 1, values.values[i]);

	if (0 != values.values_num)
	{
		SET_TEXT_RESULT(result, values.values[0]);
		values.values[0] = NULL;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() no buckets, not setting value", __func__);
	}

	zbx_vector_str_clear_ext(&values, zbx_str_free);
	zbx_vector_str_destroy(&values);

	return SUCCEED;
}
