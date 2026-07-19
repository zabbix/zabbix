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

#include "otel_clickhouse.h"
#include "otel_dataset.h"
#include "libs/zbxhistory/history_curl.h"
#include "zbxcommon.h"
#include "zbxcurl.h"
#include "zbxhttp.h"
#include "zbxjson.h"
#include "zbxlog.h"
#include "zbxstr.h"

int	otel_clickhouse_init(zbx_otel_clickhouse_t *conn, const zbx_otel_clickhouse_cfg_t *cfg, char **error)
{
	CURLoption	opt;
	CURLcode	err;
	int		ret = FAIL;

	if (NULL == (conn->handle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "cannot initialize curl easy handle");
		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_POST, 1L)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEFUNCTION, history_curl_recv)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEDATA, &conn->resp.page)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_ERRORBUFFER, conn->resp.errbuf)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_ACCEPT_ENCODING, "")) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_PRIVATE, conn)))
	{
		*error = zbx_dsprintf(NULL, "cannot set curl option %d: %s", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_https(conn->handle, error))
		goto out;

	/* either username and password both have been set or both are NULL */
	if (NULL != cfg->username)
	{
		if (SUCCEED != zbx_http_prepare_auth(conn->handle, CURLAUTH_BASIC, cfg->username, cfg->password,
				NULL, error))
		{
			goto out;
		}
	}

	ret = zbx_http_prepare_ssl(conn->handle, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, error);
out:
	return ret;
}

void	otel_clickhouse_clear(zbx_otel_clickhouse_t *conn)
{
	if (NULL != conn->handle)
		curl_easy_cleanup(conn->handle);

	zbx_free(conn->resp.page.data);
}

static void	otel_clickhouse_write_value(struct zbx_json *json, const zbx_otel_col_t *col,
		const zbx_otel_value_t *value)
{
	switch (col->type)
	{
		case OTEL_COL_BOOL:
			zbx_json_addstring(json, NULL, 0 != value->ui64 ? "true" : "false", ZBX_JSON_TYPE_INT);
			break;
		case OTEL_COL_UINT8:
		case OTEL_COL_UINT32:
		case OTEL_COL_UINT64:
		case OTEL_COL_DATETIME:
		case OTEL_COL_DATETIME64:
			zbx_json_adduint64(json, NULL, value->ui64);
			break;
		case OTEL_COL_INT32:
			zbx_json_addint64(json, NULL, value->i32);
			break;
		case OTEL_COL_FLOAT64:
			zbx_json_adddouble(json, NULL, value->dbl);
			break;
		case OTEL_COL_STRING:
			zbx_json_addstring(json, NULL, ZBX_NULL2EMPTY_STR(value->str), ZBX_JSON_TYPE_STRING);
			break;
		case OTEL_COL_MAP:
		case OTEL_COL_ARRAY_MAP:
		case OTEL_COL_ARRAY_STRING:
		case OTEL_COL_ARRAY_UINT64:
		case OTEL_COL_ARRAY_FLOAT64:
		case OTEL_COL_ARRAY_DATETIME:
		case OTEL_COL_ARRAY_DATETIME64:
			zbx_json_addraw(json, NULL, ZBX_NULL2EMPTY_STR(value->str));
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unexpected column type %d", (int)col->type);
			break;
	}
}

static int	otel_clickhouse_commit_rowset(zbx_otel_clickhouse_t *conn, const zbx_otel_clickhouse_cfg_t *cfg,
		const char *table, zbx_otel_rowset_t *rs)
{
#define ZBX_CLICKHOUSE_ASYNC_INSERT	"&async_insert=1&wait_for_async_insert=0"

	char		url[MAX_STRING_LEN];
	CURLcode	err;
	int		ret = FAIL;
	char		*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;
	long		http_ret;

	if (0 == rs->rows.values_num)
		return SUCCEED;

	zbx_snprintf(url, sizeof(url), "%s?database=%s"
			"&query=INSERT%%20INTO%%20%s%%20FORMAT%%20JSONCompactEachRow"
			ZBX_CLICKHOUSE_ASYNC_INSERT, cfg->url, cfg->database, table);

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_URL, url)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot write data to ClickHouse: cannot set curl option %d: %s",
				(int)CURLOPT_URL, curl_easy_strerror(err));
		goto out;
	}

	struct zbx_json	json;

	for (int i = 0; i < rs->rows.values_num; i++)
	{
		zbx_json_initarray(&json, 1024);

		for (int j = 0; j < rs->cols_num; j++)
		{
			otel_clickhouse_write_value(&json, &rs->cols[j], &rs->rows.values[i].cols[j]);
		}

		zbx_strncpy_alloc(&data, &data_alloc, &data_offset, json.buffer, json.buffer_size);
		zbx_chrcpy_alloc(&data, &data_alloc, &data_offset, '\n');

		zbx_json_setempty(&json);
	}

	zbx_json_free(&json);

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_POSTFIELDS, data)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot post telemetry data: %s", curl_easy_strerror(err));
		goto out;
	}

	conn->resp.page.offset = 0;
	if (CURLE_OK != (err = curl_easy_perform(conn->handle)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot post telemetry data to ClickHouse: %s", curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(conn->handle, CURLINFO_RESPONSE_CODE, &http_ret)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot obtain ClickHouse response HTTP code: %s",
				curl_easy_strerror(err));
		goto out;
	}

	if (200 != http_ret)
	{
		zabbix_log(LOG_LEVEL_WARNING, "got failure response ClickHouse: %s (%d)",
			0 != conn->resp.page.offset ? conn->resp.page.data : "", http_ret);
		goto out;
	}

	otel_rowset_clear(rs);

	ret = SUCCEED;
out:
	zbx_free(data);

	return ret;

#undef ZBX_CLICKHOUSE_ASYNC_INSERT
}

int	otel_clickhouse_commit(zbx_otel_clickhouse_t *conn, const zbx_otel_clickhouse_cfg_t *cfg,
		zbx_otel_dataset_t *ds)
{
	int	ret = SUCCEED;

	if (FAIL == otel_clickhouse_commit_rowset(conn, cfg, "otel_metrics_gauge", &ds->metrics_gauge))
		ret = FAIL;
	if (FAIL == otel_clickhouse_commit_rowset(conn, cfg, "otel_metrics_sum", &ds->metrics_sum))
		ret = FAIL;
	if (FAIL == otel_clickhouse_commit_rowset(conn, cfg, "otel_metrics_histogram", &ds->metrics_histogram))
		ret = FAIL;
	if (FAIL == otel_clickhouse_commit_rowset(conn, cfg, "otel_metrics_exponential_histogram",
			&ds->metrics_exponential_histogram))
	{
		ret = FAIL;
	}
	if (FAIL == otel_clickhouse_commit_rowset(conn, cfg, "otel_metrics_summary", &ds->metrics_summary))
		ret = FAIL;
	if (FAIL == otel_clickhouse_commit_rowset(conn, cfg, "otel_logs", &ds->logs))
		ret = FAIL;
	if (FAIL == otel_clickhouse_commit_rowset(conn, cfg, "otel_traces", &ds->traces))
		ret = FAIL;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		otel_dataset_dump(ds);

	return ret;
}
