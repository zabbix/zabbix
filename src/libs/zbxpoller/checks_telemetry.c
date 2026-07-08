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

	zbx_http_context_create(&context);

	if (SUCCEED == zbx_http_request_prepare(&context, HTTP_REQUEST_POST, url, query_fields, headers,
			posts, ZBX_RETRIEVE_MODE_CONTENT, NULL, 0, timeout, 1, apm_db_config->ssl_cert_file,
			apm_db_config->ssl_key_file, apm_db_config->ssl_key_password, apm_db_config->ssl_verify_peer,
			apm_db_config->ssl_verify_host, HTTPTEST_AUTH_BASIC, apm_db_config->username,
			apm_db_config->password, NULL, post_type, output_format, apm_db_config->source_ip,
			apm_db_config->ssl_ca_location, apm_db_config->ssl_cert_location,
			apm_db_config->ssl_key_location, &http_error))
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

static int	get_values_telemetry_http(const zbx_dc_item_t *item, time_t now, time_t lasttimestamp,
		const zbx_apm_db_config_t *apm_db_config, zbx_vector_str_t *values, char **error)
{
	int			ret = FAIL;
	char			*posts = NULL;
	char			*resp = NULL;
	int			parse_ret;
	const zbx_tq_query_t	*query = item->telemetry_query;
	char			*url = NULL;
	unsigned char		post_type, output_format;

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == apm_db_config->db_type)
		zbx_tq_clickhouse_get_query_url(apm_db_config->url, apm_db_config->db, &url);
	else
		zbx_tq_elastic_get_search_url(apm_db_config->url, query->category, query->metric_type, &url);

	post_type = (ZBX_APM_DB_TYPE_CLICKHOUSE == apm_db_config->db_type ? ZBX_POSTTYPE_RAW : ZBX_POSTTYPE_JSON);
	output_format = (ZBX_APM_DB_TYPE_CLICKHOUSE == apm_db_config->db_type ? HTTP_STORE_RAW : HTTP_STORE_JSON);

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == apm_db_config->db_type)
		zbx_tq_sql_generate_clickhouse(query, item->time_shift, item->lookback_limit, item->granularity,
				now, lasttimestamp, &posts);
	else
		zbx_tq_generate_elastic(query, item->time_shift, item->lookback_limit, item->granularity, now,
				lasttimestamp, &posts);

	if (SUCCEED != send_query_http(posts, apm_db_config, url, item->timeout, post_type, output_format, &resp,
			error))
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): response: '%s'", __func__, ZBX_NULL2STR(resp));


	if (ZBX_APM_DB_TYPE_CLICKHOUSE == apm_db_config->db_type)
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
	zbx_free(url);
	zbx_free(posts);
	zbx_free(resp);

	return ret;
}
#endif

static int	have_required_db(zbx_apm_db_type_t db_type)
{
#if defined(HAVE_MYSQL)
	return ZBX_APM_DB_TYPE_MYSQL == db_type ? SUCCEED : FAIL;
#elif defined(HAVE_POSTGRESQL)
	return ZBX_APM_DB_TYPE_POSTGRESQL == db_type ? SUCCEED : FAIL;
#else
	ZBX_UNUSED(db_type);

	return FAIL;
#endif
}

static zbx_db_result_t	sql_lib_execute_telemetry_query(const zbx_tq_query_t *query, int time_shift, int lookback_limit,
		int granularity, time_t now, time_t lasttimestamp, zbx_apm_db_type_t db_type, char **error)
{
	zbx_db_config_t	*db_config = zbx_db_config_create();
	zbx_dbconn_t	*db;
	char		*sql = NULL;
	zbx_db_result_t	res;
	int		open_ret;

	/* FIXME: placeholder start */
	ZBX_STRDUP(db_config->dbhost, "127.0.0.1");
	db_config->dbport = 5433;
	ZBX_STRDUP(db_config->dbuser, "testuser");
	ZBX_STRDUP(db_config->dbpassword, "testpass");
	ZBX_STRDUP(db_config->dbname, "testdb");

#if defined(HAVE_POSTGRESQL)
	if (0 != db_config->dbport)
		db_config->dbports = zbx_dsprintf(NULL, "%u", db_config->dbport);
#endif
	/* FIXME: placeholder end */

	db = zbx_dbconn_create_custom(db_config);
	zbx_dbconn_set_connect_options(db, ZBX_DB_CONNECT_ONCE);

	if (ZBX_DB_OK > (open_ret = zbx_dbconn_open(db)))
	{
		if (ZBX_DB_DOWN == open_ret)
			*error = zbx_strdup(NULL, "Failed to connect to database, database is down");
		else
			*error = zbx_strdup(NULL, "Failed to connect to database");

		res = NULL;
		goto out;
	}

	if (ZBX_APM_DB_TYPE_POSTGRESQL == db_type)
		zbx_tq_sql_generate_postgresql(query, time_shift, lookback_limit, granularity, now, lasttimestamp, &sql,
		db);
	else
		zbx_tq_sql_generate_mysql(query, time_shift, lookback_limit, granularity, now, lasttimestamp, &sql, db);

	res = zbx_dbconn_select(db, "%s", sql);

	if (NULL == res)
		*error = zbx_strdup(NULL, "Query failed");
	else if (ZBX_DB_DOWN == (intptr_t)res)
	{
		*error = zbx_strdup(NULL, "Query failed, database is down");
		res = NULL;
	}
out:
	zbx_free(sql);
	zbx_dbconn_free(db);
	zbx_db_config_free(db_config);

	return res;
}

static int	get_values_telemetry_sql_lib(const zbx_dc_item_t *item, zbx_apm_db_type_t db_type, time_t now,
		time_t lasttimestamp, zbx_vector_str_t *values, char **error)
{
	int		ret = FAIL;
	zbx_db_result_t	sql_result;

	if (FAIL == have_required_db(db_type))
	{
		*error = zbx_dsprintf(NULL, "%s support was not compiled in",
				(ZBX_APM_DB_TYPE_POSTGRESQL == db_type ? "PostgreSQL" : "MySQL"));
		return FAIL;
	}

	sql_result = sql_lib_execute_telemetry_query(item->telemetry_query, item->time_shift,
			item->lookback_limit, item->granularity, now, lasttimestamp, db_type, error);

	if (NULL == sql_result)
		goto clean;

	if (SUCCEED != zbx_tq_parse_sql_result(item->telemetry_query, sql_result, values))
	{
		*error = zbx_strdup(NULL, "Failed to parse result");
		goto clean;
	}

	ret = SUCCEED;
clean:
	zbx_db_free_result(sql_result);

	return ret;
}

int	get_value_telemetry(const zbx_dc_item_t *item, const zbx_apm_db_config_t *apm_db_config, AGENT_RESULT *result)
{
	time_t			now = time(NULL);
	time_t			lasttimestamp;
	int			values_ret;
	zbx_vector_str_t	values;
	char			*error = NULL;

	if (0 == apm_db_config->have_local_config)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "APM database is not configured"));

		return NOTSUPPORTED;
	}

	/* when testing the item, the time range being queried is restricted only by the lookback limit */
	lasttimestamp = 0;

	if (ZBX_APM_DB_TYPE_CLICKHOUSE == apm_db_config->db_type || ZBX_APM_DB_TYPE_ELASTIC == apm_db_config->db_type)
	{
#ifdef HAVE_LIBCURL
		values_ret = get_values_telemetry_http(item, now, lasttimestamp, apm_db_config, &values, &error);
#else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "cURL library was not compiled in"));

		return NOTSUPPORTED;
#endif
	}
	else
		values_ret = get_values_telemetry_sql_lib(item, apm_db_config->db_type, now, lasttimestamp, &values,
				&error);

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
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): no buckets, not setting value", __func__);
	}

	zbx_vector_str_clear_ext(&values, zbx_str_free);
	zbx_vector_str_destroy(&values);

	return SUCCEED;
}
