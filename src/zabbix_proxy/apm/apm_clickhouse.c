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

#include "apm_clickhouse.h"
#include "apm_dataset.h"
#include "apm_exporter.h"
#include "libs/zbxhistory/history_curl.h"
#include "zbxcommon.h"
#include "zbxtelemetry.h"
#include "zbxtypes.h"
#include "zbxcurl.h"
#include "zbxhttp.h"
#include "zbxjson.h"
#include "zbxstr.h"

#if defined(HAVE_LIBCURL)

/******************************************************************************
 *                                                                            *
 * Purpose: initialize ClickHouse connection                                  *
 *                                                                            *
 * Parameters: conn  - [OUT] ClickHouse exporter connection                   *
 *             cfg   - [IN] ClickHouse provider configuration                 *
 *             error - [OUT] error message if the operation fails             *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	apm_clickhouse_init(zbx_apm_clickhouse_t *conn, const zbx_apm_clickhouse_cfg_t *cfg, char **error)
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

	ret = zbx_http_prepare_ssl(conn->handle, cfg->ssl_cert_file, cfg->ssl_key_file, cfg->ssl_key_password,
			cfg->ssl_verify_peer, cfg->ssl_verify_host, cfg->source_ip, cfg->ssl_ca_location,
			cfg->ssl_cert_location, cfg->ssl_key_location, error);
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by ClickHouse exporter connection        *
 *                                                                            *
 * Parameters: conn - [IN] ClickHouse connection                              *
 *                                                                            *
 ******************************************************************************/
void	apm_clickhouse_clear(zbx_apm_clickhouse_t *conn)
{
	if (NULL != conn->handle)
		curl_easy_cleanup(conn->handle);

	zbx_free(conn->resp.page.data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: append column value to the row being serialized                   *
 *                                                                            *
 * Parameters: json  - [IN/OUT] row array being serialized                    *
 *             col   - [IN] column definition                                 *
 *             value - [IN] value to append                                   *
 *                                                                            *
 * Comments: Map and array columns are serialized into JSON by decoder and    *
 *           are appended as raw values.                                      *
 *                                                                            *
 ******************************************************************************/
static void	apm_clickhouse_write_value(struct zbx_json *json, const zbx_apm_col_t *col,
		const zbx_apm_value_t *value)
{
	char	buffer[ZBX_MAX_UINT64_LEN * 2];

	switch (col->type)
	{
		case APM_COL_BOOL:
			zbx_json_addstring(json, NULL, 0 != value->ui64 ? "true" : "false", ZBX_JSON_TYPE_INT);
			break;
		case APM_COL_UINT8:
		case APM_COL_UINT32:
		case APM_COL_UINT64:
		case APM_COL_DATETIME:
			zbx_json_adduint64(json, NULL, value->ui64);
			break;
		case APM_COL_DATETIME64:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64 ".%09u",
					value->ui64 / 1000000000, (unsigned int)(value->ui64 % 1000000000));
			zbx_json_addstring(json, NULL, buffer, ZBX_JSON_TYPE_NUMBER);
			break;
		case APM_COL_INT32:
			zbx_json_addint64(json, NULL, value->i32);
			break;
		case APM_COL_FLOAT64:
			zbx_json_adddouble(json, NULL, value->dbl);
			break;
		case APM_COL_STRING:
			zbx_json_addstring(json, NULL, ZBX_NULL2EMPTY_STR(value->str), ZBX_JSON_TYPE_STRING);
			break;
		case APM_COL_MAP:
		case APM_COL_ARRAY_MAP:
		case APM_COL_ARRAY_STRING:
		case APM_COL_ARRAY_UINT64:
		case APM_COL_ARRAY_FLOAT64:
		case APM_COL_ARRAY_DATETIME:
		case APM_COL_ARRAY_DATETIME64:
			zbx_json_addraw(json, NULL, ZBX_NULL2EMPTY_STR(value->str));
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unexpected column type %d", (int)col->type);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: insert rowset contents into the specified ClickHouse table        *
 *                                                                            *
 * Parameters: conn  - [IN] ClickHouse exporter connection                    *
 *             cfg   - [IN] ClickHouse provider configuration                 *
 *             table - [IN] target table name                                 *
 *             rs    - [IN/OUT] rowset to insert                              *
 *                                                                            *
 * Return value: APM_COMMIT_OK    - rows were inserted                        *
 *               APM_COMMIT_ERR   - insertion failed                          *
 *               APM_COMMIT_RETRY - insertion must be retried                 *
 *                                                                            *
 * Comments: Empty rowset is treated as successfully inserted. The rowset is  *
 *           cleared unless the insertion has to be retried, leaving only the *
 *           rows that still have to be inserted.                             *
 *                                                                            *
 ******************************************************************************/
static int	apm_clickhouse_commit_rowset(zbx_apm_clickhouse_t *conn, const zbx_apm_clickhouse_cfg_t *cfg,
		const char *table, zbx_apm_rowset_t *rs)
{
#define ZBX_CLICKHOUSE_ASYNC_INSERT	"&async_insert=1&wait_for_async_insert=0"

	char		url[MAX_STRING_LEN];
	CURLcode	err;
	int		ret = APM_COMMIT_ERR;
	char		*data = NULL;
	size_t		data_alloc = 0, data_offset = 0;
	long		http_ret;

	if (0 == rs->rows.values_num)
		return APM_COMMIT_OK;

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

	zbx_json_initarray(&json, 1024);

	for (int i = 0; i < rs->rows.values_num; i++)
	{
		for (int j = 0; j < rs->cols_num; j++)
		{
			apm_clickhouse_write_value(&json, &rs->cols[j], &rs->rows.values[i].cols[j]);
		}

		zbx_strncpy_alloc(&data, &data_alloc, &data_offset, json.buffer, json.buffer_size);
		zbx_chrcpy_alloc(&data, &data_alloc, &data_offset, '\n');

		zbx_json_reset_array(&json);
	}

	zbx_json_free(&json);

	zabbix_log(LOG_LEVEL_TRACE, "posting otel data to clickhouse: %s", data);

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_POSTFIELDS, data)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot post telemetry data: %s", curl_easy_strerror(err));
		goto out;
	}

	conn->resp.page.offset = 0;
	if (CURLE_OK != (err = curl_easy_perform(conn->handle)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot post telemetry data to ClickHouse: %s", curl_easy_strerror(err));
		ret |= APM_COMMIT_RETRY;
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

	ret = APM_COMMIT_OK;
out:
	if (0 == (ret & APM_COMMIT_RETRY))
		apm_rowset_clear(rs);

	zbx_free(data);

	return ret;

#undef ZBX_CLICKHOUSE_ASYNC_INSERT
}

/******************************************************************************
 *                                                                            *
 * Purpose: insert dataset contents into ClickHouse telemetry tables          *
 *                                                                            *
 * Parameters: conn - [IN] ClickHouse exporter connection                     *
 *             cfg  - [IN] ClickHouse provider configuration                  *
 *             ds   - [IN/OUT] dataset to insert                              *
 *                                                                            *
 * Return value: combined flags of the individual rowset insertions:          *
 *               APM_COMMIT_OK    - all rows were inserted                    *
 *               APM_COMMIT_ERR   - at least one insertion failed             *
 *               APM_COMMIT_RETRY - at least one insertion must be retried    *
 *                                                                            *
 ******************************************************************************/
int	apm_clickhouse_commit(zbx_apm_clickhouse_t *conn, const zbx_apm_clickhouse_cfg_t *cfg,
		zbx_apm_dataset_t *ds)
{
	int	ret = APM_COMMIT_OK;

	ret |= apm_clickhouse_commit_rowset(conn, cfg, "otel_metrics_gauge", &ds->metrics_gauge);
	ret |= apm_clickhouse_commit_rowset(conn, cfg, "otel_metrics_sum", &ds->metrics_sum);
	ret |= apm_clickhouse_commit_rowset(conn, cfg, "otel_metrics_histogram", &ds->metrics_histogram);
	ret |= apm_clickhouse_commit_rowset(conn, cfg, "otel_metrics_exponential_histogram",
			&ds->metrics_exponential_histogram);
	ret |= apm_clickhouse_commit_rowset(conn, cfg, "otel_metrics_summary", &ds->metrics_summary);
	ret |= apm_clickhouse_commit_rowset(conn, cfg, "otel_logs", &ds->logs);
	ret |= apm_clickhouse_commit_rowset(conn, cfg, "otel_traces", &ds->traces);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		apm_dataset_dump(ds);

	return ret;
}

#else
int	apm_clickhouse_init(zbx_apm_clickhouse_t *conn, const zbx_apm_clickhouse_cfg_t *cfg, char **error)
{
	ZBX_UNUSED(conn);
	ZBX_UNUSED(cfg);

	*error = zbx_strdup(NULL, "ClickHouse APM provider requires curl library."
			" This Zabbix server binary was compiled without curl");

	return APM_COMMIT_ERR;
}

void	apm_clickhouse_clear(zbx_apm_clickhouse_t *conn)
{
	ZBX_UNUSED(conn);
}

int	apm_clickhouse_commit(zbx_apm_clickhouse_t *conn, const zbx_apm_clickhouse_cfg_t *cfg,
		zbx_apm_dataset_t *ds)
{
	ZBX_UNUSED(conn);
	ZBX_UNUSED(cfg);
	ZBX_UNUSED(ds);
	return FAIL;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: initialize ClickHouse exporter configuration from provider        *
 *          options                                                           *
 *                                                                            *
 * Parameters: cfg           - [OUT] ClickHouse exporter configuration        *
 *             export_config - [IN] provider options                          *
 *             error         - [OUT] error message if a mandatory option is   *
 *                                  missing or curl support is unavailable    *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	apm_clickhouse_cfg_init(zbx_apm_clickhouse_cfg_t *cfg, const zbx_apm_db_config_t *export_config,
		char **error)
{
#if defined(HAVE_LIBCURL)
	ZBX_UNUSED(error);

	if (NULL != export_config->url)
		cfg->url = zbx_strdup(NULL, export_config->url);

	if (NULL != export_config->db)
		cfg->database = zbx_strdup(NULL, export_config->db);

	if (NULL != export_config->username)
		cfg->username = zbx_strdup(NULL, export_config->username);

	if (NULL != export_config->password)
		cfg->password = zbx_strdup(NULL, export_config->password);

	if (NULL != export_config->source_ip)
		cfg->source_ip = zbx_strdup(NULL, export_config->source_ip);

	if (NULL != export_config->vault_path)
		cfg->vault_path = zbx_strdup(NULL, export_config->vault_path);

	if (NULL != export_config->ssl_cert_file)
		cfg->ssl_cert_file = zbx_strdup(NULL, export_config->ssl_cert_file);

	if (NULL != export_config->ssl_key_file)
		cfg->ssl_key_file = zbx_strdup(NULL, export_config->ssl_key_file);

	if (NULL != export_config->ssl_key_password)
		cfg->ssl_key_password = zbx_strdup(NULL, export_config->ssl_key_password);

	cfg->ssl_verify_peer = export_config->ssl_verify_peer;
	cfg->ssl_verify_host = export_config->ssl_verify_host;

	if (NULL != export_config->ssl_ca_location)
		cfg->ssl_ca_location = zbx_strdup(NULL, export_config->ssl_ca_location);

	if (NULL != export_config->ssl_cert_location)
		cfg->ssl_cert_location = zbx_strdup(NULL, export_config->ssl_cert_location);

	if (NULL != export_config->ssl_key_location)
		cfg->ssl_key_location = zbx_strdup(NULL, export_config->ssl_key_location);

	return SUCCEED;
#else
	ZBX_UNUSED(cfg);
	ZBX_UNUSED(options);
	ZBX_UNUSED(options_num);

	*error = zbx_strdup(NULL, "ClickHouse telemetry provider requires curl library."
			" This Zabbix server binary was compiled without curl");

	return FAIL;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by ClickHouse exporter configuration     *
 *                                                                            *
 ******************************************************************************/
void	apm_clickhouse_cfg_clear(zbx_apm_clickhouse_cfg_t *cfg)
{
	zbx_free(cfg->url);
	zbx_free(cfg->database);
	zbx_free(cfg->username);
	zbx_free(cfg->password);
	zbx_free(cfg->source_ip);
	zbx_free(cfg->vault_path);
	zbx_free(cfg->ssl_cert_file);
	zbx_free(cfg->ssl_key_file);
	zbx_free(cfg->ssl_key_password);
	zbx_free(cfg->ssl_ca_location);
	zbx_free(cfg->ssl_cert_location);
	zbx_free(cfg->ssl_key_location);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy ClickHouse exporter configuration                            *
 *                                                                            *
 * Parameters: dst - [OUT] destination configuration                          *
 *             src - [IN] source configuration                                *
 *                                                                            *
 ******************************************************************************/
void	apm_clickhouse_cfg_copy(zbx_apm_clickhouse_cfg_t *dst, const zbx_apm_clickhouse_cfg_t *src)
{
	if (NULL != src->url)
		dst->url = zbx_strdup(NULL, src->url);

	if (NULL != src->database)
		dst->database = zbx_strdup(NULL, src->database);

	if (NULL != src->username)
		dst->username = zbx_strdup(NULL, src->username);

	if (NULL != src->password)
		dst->password = zbx_strdup(NULL, src->password);

	if (NULL != src->source_ip)
		dst->source_ip = zbx_strdup(NULL, src->source_ip);

	if (NULL != src->vault_path)
		dst->vault_path = zbx_strdup(NULL, src->vault_path);

	if (NULL != src->ssl_cert_file)
		dst->ssl_cert_file = zbx_strdup(NULL, src->ssl_cert_file);

	if (NULL != src->ssl_key_file)
		dst->ssl_key_file = zbx_strdup(NULL, src->ssl_key_file);

	if (NULL != src->ssl_key_password)
		dst->ssl_key_password = zbx_strdup(NULL, src->ssl_key_password);

	dst->ssl_verify_peer = src->ssl_verify_peer;
	dst->ssl_verify_host = src->ssl_verify_host;

	if (NULL != src->ssl_ca_location)
		dst->ssl_ca_location = zbx_strdup(NULL, src->ssl_ca_location);

	if (NULL != src->ssl_cert_location)
		dst->ssl_cert_location = zbx_strdup(NULL, src->ssl_cert_location);

	if (NULL != src->ssl_key_location)
		dst->ssl_key_location = zbx_strdup(NULL, src->ssl_key_location);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether new apm clickhouse config matches current config    *
 *                                                                            *
 * Parameters: cfg_now - [IN] current apm clickhouse config                   *
 *             cfg_new - [IN] new config to compare against current           *
 *                                                                            *
 * Return value: SUCCEED - configs match, FAIL - configs differ               *
 *                                                                            *
 ******************************************************************************/
int	apm_clickhouse_cfg_compare_global(const zbx_apm_clickhouse_cfg_t *cfg_now, const zbx_apm_db_config_t *cfg_new)
{
	if (0 != zbx_strcmp_null(cfg_now->url, cfg_new->url))
		return FAIL;

	if (0 != zbx_strcmp_null(cfg_now->database,  cfg_new->db))
		return FAIL;

	if (0 != zbx_strcmp_null(cfg_now->username,  cfg_new->username))
		return FAIL;

	if (0 != zbx_strcmp_null(cfg_now->password,  cfg_new->password))
		return FAIL;

	if (0 != zbx_strcmp_null(cfg_now->source_ip,  cfg_new->source_ip))
		return FAIL;

	if (0 != zbx_strcmp_null(cfg_now->ssl_ca_location,  cfg_new->ssl_ca_location))
		return FAIL;

	if (cfg_now->ssl_verify_peer != cfg_new->ssl_verify_peer)
		return FAIL;

	if (cfg_now->ssl_verify_host != cfg_new->ssl_verify_host)
		return FAIL;

	return SUCCEED;
}

/*******************************************************************************
 *                                                                             *
 * Purpose: copy global APM configuration into ClickHouse config               *
 *                                                                             *
 * Parameters: dst - [OUT] destination config to populate                      *
 *             src - [IN] source config to copy from                           *
 *                                                                             *
 ******************************************************************************/
void	apm_clickhouse_cfg_copy_global(zbx_apm_clickhouse_cfg_t *dst, const zbx_apm_db_config_t *src)
{
	if (NULL != src->url)
		dst->url = zbx_strdup(NULL, src->url);

	if (NULL != src->db)
		dst->database = zbx_strdup(NULL, src->db);

	if (NULL != src->username)
		dst->username = zbx_strdup(NULL, src->username);

	if (NULL != src->password)
		dst->password = zbx_strdup(NULL, src->password);

	if (NULL != src->source_ip)
		dst->source_ip = zbx_strdup(NULL, src->source_ip);

	if (NULL != src->ssl_ca_location)
		dst->ssl_ca_location = zbx_strdup(NULL, src->ssl_ca_location);

	dst->ssl_verify_host = src->ssl_verify_host;
	dst->ssl_verify_peer = src->ssl_verify_peer;
}

