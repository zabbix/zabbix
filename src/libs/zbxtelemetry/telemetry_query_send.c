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

#include "zbxjson.h"
#include "zbxtelemetry.h"
#include "telemetry.h"
#include "zbxhttp.h"
#include "zbxstr.h"
#include "zbxcommon.h"
#include "zbxtypes.h"

static int	tq_send_query_clickhouse_raw(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp,
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

	tq_sql_generate_clickhouse(query, now, lasttimestamp, &sql);
	zbx_http_context_create(&context);

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): generated SQL: '%s'", __func__, sql);

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

static int	tq_clickhouse_parse_row(const zbx_tq_query_t *query, struct zbx_json_parse *jp, struct zbx_json *j)
{
	int		ret = FAIL;
	const char	*p = NULL;
	char		buf[MAX_STRING_LEN];
	zbx_uint32_t	row_idx;
	zbx_uint64_t	timestamp;

	zbx_json_addobject(j, NULL);

	/* row index */
	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)) || SUCCEED != zbx_is_uint32(buf, &row_idx))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse row index from row \"%s\"", jp->start);
		goto out;
	}

	zbx_json_adduint64(j, "id", row_idx);

	/* skip rounded time */
	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse rounded time from row \"%s\"", jp->start);
		goto out;
	}

	/* timestamp */
	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)) ||
			SUCCEED != zbx_is_uint64(buf, &timestamp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse timestamp from row \"%s\"", jp->start);
		goto out;
	}

	zbx_json_adduint64(j, "timestamp", timestamp);

	zbx_json_addobject(j, "columns");

	for (int i = 0; i < query->columns.values_num; i++)
	{
		const zbx_tq_column_t	*col = &query->columns.values[i];
		char		*field_name;
		zbx_json_type_t	type;

		/* TODO: decide on a format for columns with keys, maybe nested? */
		if (NULL != col->key)
			field_name = zbx_dsprintf(NULL, "%s.%s", col->name, col->key);
		else
			field_name = zbx_strdup(NULL, col->name);

		if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), &type)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from row \"%s\"", field_name,
					jp->start);
			zbx_free(field_name);
			goto out_columns;
		}

		zbx_json_addstring(j, field_name, buf, type);

		zbx_free(field_name);
	}

	for (int i = 0; i < query->aggregated_columns.values_num; i++)
	{
		const char	*field_name = query->aggregated_columns.values[i].alias;
		zbx_json_type_t	type;

		if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), &type)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse column \"%s\" from row \"%s\"", field_name,
					jp->start);
			goto out_columns;
		}

		zbx_json_addstring(j, field_name, buf, type);
	}

	ret = SUCCEED;
out_columns:
	zbx_json_close(j);
out:
	zbx_json_close(j);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: modifies resp during parsing but returns it to initial state     *
 *                                                                            *
 ******************************************************************************/
static int	tq_clickhouse_resp_to_json(const zbx_tq_query_t *query, char *resp, char **out_json)
{
	int		ret = SUCCEED;
	struct zbx_json	j;
	char		*start = resp;

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	while (1)
	{
		char			*end;
		struct zbx_json_parse	jp;

		/* handle empty resp */
		if ('\0' == *start)
			break;

		if (NULL != (end = strchr(start, '\n')))
			*end = '\0';

		if (SUCCEED != zbx_json_open(start, &jp) || SUCCEED != tq_clickhouse_parse_row(query, &jp, &j))
			ret = FAIL;

		if (NULL == end) {
			break;
		}

		*end = '\n';
		start = end + 1;

		if (SUCCEED != ret)
			break;
	}

	if (SUCCEED == ret)
		*out_json = zbx_strdup(NULL, j.buffer);

	zbx_json_close(&j);

	return ret;
}

int	zbx_tq_send_query_clickhouse(const zbx_tq_query_t *query, time_t now, time_t lasttimestamp,
		const zbx_tq_conn_params_clickhouse_t *conn_params, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **out, char **error)
{
	int	ret = FAIL;
	char	*resp = NULL;

	if (SUCCEED != tq_send_query_clickhouse_raw(query, now, lasttimestamp, conn_params, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &resp, error))
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): Clickhouse response: '%s'", __func__, ZBX_NULL2STR(resp));

	if (SUCCEED != tq_clickhouse_resp_to_json(query, resp, out)) {
		*error = zbx_strdup(NULL, "Failed to parse Clickhouse response");
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): resulting json: '%s'", __func__, ZBX_NULL2STR(*out));

	ret = SUCCEED;
out:
	/* FIXME: placeholder */
	if (SUCCEED != ret)
		zabbix_log(LOG_LEVEL_ERR, "%s(): query failed: \"%s\"", __func__, ZBX_NULL2STR(*error));

	zbx_free(resp);

	return ret;
}
