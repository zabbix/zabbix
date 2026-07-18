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
#include "zbxcurl.h"
#include "zbxhttp.h"
#include "zbxlog.h"

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


static void	otel_clickhouse_write_value(char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_otel_col_t *col, const zbx_otel_value_t *value)
{
	switch (col->type)
	{
		default:
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

	if (0 == rs->rows.values_num)
		return SUCCEED;

	zbx_snprintf(url, sizeof(url), "%s?database=%s"
			"&query=INSERT%%20INTO%%20%s%%20FORMAT%%20RowBinary&input_format_binary_read_json_as_string=1"
			ZBX_CLICKHOUSE_ASYNC_INSERT, cfg->url, cfg->database, table);

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_URL, url)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot write data to ClickHouse: cannot set curl option %d: %s",
				(int)CURLOPT_URL, curl_easy_strerror(err));
		goto out;
	}

	for (int i = 0; i < rs->rows.values_num; i++)
	{
		for (int j = 0; j < rs->cols_num; j++)
		{
			otel_clickhouse_write_value(&data, &data_alloc, &data_offset, &rs->cols[j],
					&rs->rows.values[i].cols[j]);
		}
	}


	ret = SUCCEED;
out:
	return ret;

#undef ZBX_CLICKHOUSE_ASYNC_INSERT
}

void	otel_clickhouse_commit(zbx_otel_clickhouse_t *conn, const zbx_otel_clickhouse_cfg_t *cfg,
		zbx_otel_dataset_t *ds)
{
	otel_clickhouse_commit_rowset(conn, cfg, "otel_logs", &ds->logs);

	/* TODO: remove forced trace loglevel */
	int	loglevel = zbx_set_log_level(LOG_LEVEL_TRACE);

	otel_dataset_dump(ds);

	zbx_set_log_level(loglevel);
}
