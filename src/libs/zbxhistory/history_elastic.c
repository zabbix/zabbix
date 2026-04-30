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

#include "history_elastic.h"
#include "zbxcommon.h"

#ifdef HAVE_LIBCURL

#include "history.h"
#include "history_curl.h"
#include "history_option.h"
#include "zbxhistory.h"
#include "zbxtime.h"
#include "zbxalgo.h"
#include "zbxjson.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxcurl.h"
#include "zbxcacheconfig.h"
#include "zbxtypes.h"
#include "zbxdb.h"
#include "zbx_dbversion_constants.h"

#define		ZBX_IDX_JSON_ALLOCATE		256
#define		ZBX_JSON_ALLOCATE		2048
#define		ZBX_MAX_RESULT_WINDOW		9999

typedef enum
{
	ELASTIC_RETRIES_OFF,
	ELASTIC_RETRIES_ON
}
zbx_elastic_retries_t;

typedef struct
{
	unsigned char		value_type;
	int			status;
	char			*url;
	char			*buf;
	CURL			*handle;
	struct curl_slist	*headers;

	zbx_curl_response_t	resp;
}
zbx_elastic_conn_t;

ZBX_PTR_VECTOR_DECL(elastic_conn_ptr, zbx_elastic_conn_t *)
ZBX_PTR_VECTOR_IMPL(elastic_conn_ptr, zbx_elastic_conn_t *)

typedef struct
{
	int				log_slow_queries;
	unsigned char			pipelines;

	zbx_uint64_t			value_type_flags;

	char				*base_url;

	zbx_vector_elastic_conn_ptr_t	conns;
	CURLM				*mhandle;
}
zbx_history_elastic_data_t;

/******************************************************************************
 *                                                                            *
 * Purpose: get elasticsearch index name                                      *
 *                                                                            *
 * Parameters: value_type - [IN] the history value type                       *
 *                                                                            *
 * Return value: description of the history value type                        *
 *                                                                            *
 ******************************************************************************/
static const char	*elastic_get_index_name(unsigned char value_type)
{
	static const char	*value_type_str[ITEM_VALUE_TYPE_COUNT] = {"dbl", "str", "log", "uint", "text", "bin",
				"json"};

	if (value_type >= ARRSIZE(value_type_str))
		return "unknown";

	return value_type_str[value_type];
}

static zbx_history_value_t	history_str2value(char *str, unsigned char value_type)
{
	zbx_history_value_t	value;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_LOG:
			value.log = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));
			memset(value.log, 0, sizeof(zbx_log_value_t));
			value.log->value = zbx_strdup(NULL, str);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_JSON:
			value.str = zbx_strdup(NULL, str);
			break;
		case ITEM_VALUE_TYPE_FLOAT:
			value.dbl = atof(str);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(value.ui64, str);
			break;
		case ITEM_VALUE_TYPE_BIN:
		case ITEM_VALUE_TYPE_NONE:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_exit(EXIT_FAILURE);
	}

	return value;
}

static const char	*history_value2str(const zbx_history_entry_t *h)
{
	static char	buffer[ZBX_MAX_DOUBLE_LEN + 1];

	switch (h->value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
		case ITEM_VALUE_TYPE_JSON:
			return h->value.str;
		case ITEM_VALUE_TYPE_LOG:
			return h->value.log->value;
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL64, h->value.dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, h->value.ui64);
			break;
		case ITEM_VALUE_TYPE_BIN:
		case ITEM_VALUE_TYPE_NONE:
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_exit(EXIT_FAILURE);
	}

	return buffer;
}

static int	history_parse_value(struct zbx_json_parse *jp, unsigned char value_type, zbx_history_record_t *hr)
{
	char	*value = NULL;
	size_t	value_alloc = 0;
	int	ret = FAIL;

	memset(hr, 0, sizeof(zbx_history_record_t));

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, "clock", &value, &value_alloc, NULL))
		goto out;

	hr->timestamp.sec = atoi(value);

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, "ns", &value, &value_alloc, NULL))
		goto out;

	hr->timestamp.ns = atoi(value);

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, "value", &value, &value_alloc, NULL))
		goto out;

	hr->value = history_str2value(value, value_type);

	if (ITEM_VALUE_TYPE_LOG == value_type)
	{

		if (SUCCEED != zbx_json_value_by_name_dyn(jp, "timestamp", &value, &value_alloc, NULL))
			goto out;

		hr->value.log->timestamp = atoi(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(jp, "logeventid", &value, &value_alloc, NULL))
			goto out;

		hr->value.log->logeventid = atoi(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(jp, "severity", &value, &value_alloc, NULL))
			goto out;

		hr->value.log->severity = atoi(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(jp, "source", &value, &value_alloc, NULL))
			goto out;

		hr->value.log->source = zbx_strdup(NULL, value);
	}

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
		zbx_history_record_clear(hr, value_type);

	zbx_free(value);

	return ret;
}

static void	history_elastic_prepare(zbx_history_elastic_data_t *d)
{
	if (NULL == d->mhandle)
	{
		if (NULL == (d->mhandle = curl_multi_init()))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot initialize curl multi session");
			exit(EXIT_FAILURE);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: check an error from elasticsearch json response                   *
 *                                                                            *
 * Parameters: page - [IN]  buffer with json response                         *
 *             err  - [OUT] parse error message. If the error value is        *
 *                           set it must be freed by caller after it has      *
 *                           been used.                                       *
 *                                                                            *
 * Return value: SUCCEED - the response contains an error                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	elastic_is_error_present(zbx_httppage_t *page, char **err)
{
	struct zbx_json_parse	jp, jp_values, jp_index, jp_error, jp_items, jp_item;
	const char		*errors, *p = NULL;
	char			*index = NULL, *status = NULL, *type = NULL, *reason = NULL;
	size_t			index_alloc = 0, status_alloc = 0, type_alloc = 0, reason_alloc = 0;
	int			rc_js = SUCCEED;

	zabbix_log(LOG_LEVEL_TRACE, "%s() raw json: %s", __func__, ZBX_NULL2EMPTY_STR(page->data));

	if (SUCCEED != zbx_json_open(page->data, &jp) || SUCCEED != zbx_json_brackets_open(jp.start, &jp_values))
		return FAIL;

	if (NULL == (errors = zbx_json_pair_by_name(&jp_values, "errors")) || 0 != strncmp("true", errors, 4))
		return FAIL;

	if (SUCCEED == zbx_json_brackets_by_name(&jp, "items", &jp_items))
	{
		while (NULL != (p = zbx_json_next(&jp_items, p)))
		{
			if (SUCCEED == zbx_json_brackets_open(p, &jp_item) &&
					SUCCEED == zbx_json_brackets_by_name(&jp_item, "index", &jp_index) &&
					SUCCEED == zbx_json_brackets_by_name(&jp_index, "error", &jp_error))
			{
				if (SUCCEED != zbx_json_value_by_name_dyn(&jp_error, "type", &type, &type_alloc, NULL))
					rc_js = FAIL;
				if (SUCCEED != zbx_json_value_by_name_dyn(&jp_error, "reason", &reason, &reason_alloc, NULL))
					rc_js = FAIL;
			}
			else
				continue;

			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_index, "status", &status, &status_alloc, NULL))
				rc_js = FAIL;
			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_index, "_index", &index, &index_alloc, NULL))
				rc_js = FAIL;

			break;
		}
	}
	else
		rc_js = FAIL;

	*err = zbx_dsprintf(NULL,"index:%s status:%s type:%s reason:%s%s", ZBX_NULL2EMPTY_STR(index),
			ZBX_NULL2EMPTY_STR(status), ZBX_NULL2EMPTY_STR(type), ZBX_NULL2EMPTY_STR(reason),
			FAIL == rc_js ? " / ElasticSearch version is not fully compatible with zabbix server" : "");

	zbx_free(status);
	zbx_free(type);
	zbx_free(reason);
	zbx_free(index);

	return SUCCEED;
}

static void	elastic_conn_clear(zbx_elastic_conn_t *conn)
{
	if (NULL != conn->handle)
		curl_easy_cleanup(conn->handle);

	if (NULL != conn->headers)
		curl_slist_free_all(conn->headers);

	zbx_free(conn->resp.page.data);
	zbx_free(conn->url);
	zbx_free(conn->buf);
}


static void	elastic_conn_free(zbx_elastic_conn_t *conn)
{
	elastic_conn_clear(conn);
	zbx_free(conn);
}

/******************************************************************************
 *                                                                            *
 * Purpose: utility function for checking curl attribute setting errors       *
 *                                                                            *
 ******************************************************************************/
static int	history_elastic_curl_check_error(CURLcode err, CURLoption opt, char **error)
{
	if (CURLE_OK == err)
		return SUCCEED;

	*error = zbx_dsprintf(NULL, "cannot set cURL option %u: %s.", opt, curl_easy_strerror(err));

	return FAIL;
}

#define CURL_SETOPT(conn, option, value, error)	\
		history_elastic_curl_check_error(curl_easy_setopt(conn->handle, option, value), option, error)

/******************************************************************************
 *                                                                            *
 * Purpose: initialize elasticsearch connection structure                     *
 *                                                                            *
 * Parameters: conn         - [OUT] connection structure to initialize        *
 *             d            - [IN] elasticsearch data                         *
 *             path         - [IN] URL path (optional)                        *
 *             content_type - [IN] HTTP content type header (optional)        *
 *             data         - [IN] POST data (optional)                       *
 *                                 If set the connection takes ownership of   *
 *                                 the data and it's freed when connection    *
 *                                 is cleared/freed.                          *
 *             error        - [OUT] error message                             *
 *                                                                            *
 * Return value: SUCCEED - connection initialized successfully                *
 *               FAIL    - initialization failed                              *
 *                                                                            *
 ******************************************************************************/
static int	history_elastic_conn_init(zbx_elastic_conn_t *conn, zbx_history_elastic_data_t *d,
		const char *path, const char *content_type, char *data, char **error)
{
	memset(conn, 0, sizeof(zbx_elastic_conn_t));

	if (NULL != path)
		conn->url = zbx_dsprintf(NULL, "%s/%s", d->base_url, path);
	else
		conn->url = zbx_strdup(NULL, d->base_url);

	conn->status = FAIL;

	if (NULL != content_type)
	{
		char	*header = zbx_dsprintf(NULL, "Content-Type: %s", content_type);

		conn->headers = curl_slist_append(conn->headers, header);
		zbx_free(header);

		if (NULL == conn->headers)
		{
			*error = zbx_strdup(NULL, "cannot create curl header list");
			return FAIL;
		}
	}

	if (NULL == (conn->handle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "cannot initialize curl session");
		return FAIL;
	}

	if (NULL == data)
	{
		if (SUCCEED != CURL_SETOPT(conn, CURLOPT_POSTFIELDSIZE, 0L, error) ||
				SUCCEED != CURL_SETOPT(conn, CURLOPT_POST, 0L, error))
		{
			return FAIL;
		}
	}
	else
	{
		if (SUCCEED != CURL_SETOPT(conn, CURLOPT_POST, 1L, error) ||
				SUCCEED != CURL_SETOPT(conn, CURLOPT_POSTFIELDSIZE, strlen(data), error) ||
				SUCCEED != CURL_SETOPT(conn, CURLOPT_POSTFIELDS, data, error))
		{
			return FAIL;
		}

		conn->buf = data;
	}

	if (NULL != conn->headers)
	{
		if (SUCCEED != CURL_SETOPT(conn, CURLOPT_HTTPHEADER, conn->headers, error))
			return FAIL;
	}

	if (SUCCEED != CURL_SETOPT(conn, CURLOPT_URL, conn->url, error) ||
			SUCCEED != CURL_SETOPT(conn, CURLOPT_WRITEFUNCTION, history_curl_recv, error) ||
			SUCCEED != CURL_SETOPT(conn, CURLOPT_WRITEDATA, &conn->resp.page, error) ||
			SUCCEED != CURL_SETOPT(conn, CURLOPT_FAILONERROR, 1L, error) ||
			SUCCEED != CURL_SETOPT(conn, CURLOPT_ERRORBUFFER, conn->resp.errbuf, error) ||
			SUCCEED != CURL_SETOPT(conn, CURLOPT_ACCEPT_ENCODING, "", error) ||
			SUCCEED != CURL_SETOPT(conn, CURLOPT_PRIVATE, conn, error))
	{
		return FAIL;
	}

	if (SUCCEED != zbx_curl_setopt_https(conn->handle, error))
		return FAIL;

	*conn->resp.errbuf = '\0';

	if (0 < conn->resp.page.alloc)
	{
		*conn->resp.page.data = '\0';
		conn->resp.page.offset = 0;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set URL path for elasticsearch connection                         *
 *                                                                            *
 * Parameters: conn  - [IN/OUT] connection structure                          *
 *             d     - [IN] elasticsearch data                                *
 *             path  - [IN] URL path to set                                   *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - URL path set successfully                          *
 *               FAIL    - failed to set URL path                             *
 *                                                                            *
 ******************************************************************************/
static int	history_elastic_conn_set_url_path(zbx_elastic_conn_t *conn, zbx_history_elastic_data_t *d,
		const char *path, char **error)
{
	conn->url = zbx_dsprintf(conn->url, "%s/%s", d->base_url, path);

	return CURL_SETOPT(conn, CURLOPT_URL, conn->url, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set POST data for elasticsearch connection                        *
 *                                                                            *
 * Parameters: conn     - [IN/OUT] connection structure                       *
 *             data     - [IN] POST data to set                               *
 *             data_len - [IN] length of POST data                            *
 *             error    - [OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED - POST data set successfully                         *
 *               FAIL    - failed to set POST data                            *
 *                                                                            *
 ******************************************************************************/
static int	history_elastic_conn_set_post_data(zbx_elastic_conn_t *conn, const char *data, size_t data_len,
		char **error)
{
	if (SUCCEED != CURL_SETOPT(conn, CURLOPT_POST, 1L, error) ||
			SUCCEED != CURL_SETOPT(conn, CURLOPT_POSTFIELDSIZE, data_len, error) ||
			SUCCEED != CURL_SETOPT(conn, CURLOPT_POSTFIELDS, data, error))
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: configure connection for DELETE request                           *
 *                                                                            *
 * Parameters: conn  - [IN/OUT] connection structure                          *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - DELETE request configured successfully             *
 *               FAIL    - failed to configure DELETE request                 *
 *                                                                            *
 ******************************************************************************/
static int	history_elastic_conn_set_delete(zbx_elastic_conn_t *conn, char **error)
{
	if (SUCCEED != CURL_SETOPT(conn, CURLOPT_POSTFIELDSIZE, 0L, error) ||
			SUCCEED != CURL_SETOPT(conn, CURLOPT_CUSTOMREQUEST, "DELETE", error))
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a new elasticsearch connection                                *
 *                                                                            *
 * Parameters:                                                                *
 *     d          - [IN/OUT] elasticsearch history data structure             *
 *     value_type - [IN] value type                                           *
 *     data       - [IN] JSON-formatted historical data to be sent            *
 *                                                                            *
 ******************************************************************************/
static void	history_elastic_add_conn(zbx_history_elastic_data_t *d, unsigned char value_type, char *data)
{
	zbx_elastic_conn_t	*conn;
	char			*error = NULL;

	conn = (zbx_elastic_conn_t *)zbx_malloc(NULL, sizeof(zbx_elastic_conn_t));

	if (SUCCEED != history_elastic_conn_init(conn, d, "_bulk", "application/x-ndjson", data, &error))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize Elasticsearch connection: %s", error);
		elastic_conn_free(conn);
		zbx_free(error);
		return;
	}

	conn->value_type = value_type;

	zbx_vector_elastic_conn_ptr_append(&d->conns, conn);

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform single iteration of elasticsearch requests                *
 *                                                                            *
 * Parameters: d       - [IN] elasticsearch data structure                    *
 *             mhandle - [IN] curl multi handle                               *
 *                                                                            *
 * Return value: number of handles to retry                                   *
 *               FAIL - if curl multi handle operation failed                 *
 *                                                                            *
 * Comments: Retryable connections are re-added to multi handle after         *
 *           iteration so they would be retried with the next                 *
 *           history_elastic_perform_once() call.                             *
 *                                                                            *
 ******************************************************************************/
static int	history_elastic_perform_once(zbx_history_elastic_data_t *d, CURLM *mhandle)
{
	CURLMcode		code;
	CURLMsg			*msg;
	int			running = 0, ret = FAIL, long_query_limit, msg_num;
	double			ts_now, ts_last;
	zbx_vector_ptr_t	retries;

	zbx_vector_ptr_create(&retries);

	if (0 == (long_query_limit = d->log_slow_queries))
		long_query_limit = ZBX_HISTORY_STORAGE_DOWN_DELAY;
	else
		long_query_limit /= 1000;

	ts_last = zbx_time();

	do
	{
		/* curl_multi_perform/curl_multi_wait failures are indication of internal libcurl errors  */
		/* or system resource exhaustion - in both cases state of multi handle could be corrupted */
		/* and better to fail the whole batch                                                     */

		if (CURLM_OK != (code = curl_multi_perform(mhandle, &running)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot perform on curl multi handle: %s",
					curl_multi_strerror(code));
			goto out;
		}

		if (CURLM_OK != (code = zbx_curl_multi_wait(mhandle, ZBX_HISTORY_STORAGE_TIMEOUT_MS, NULL)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot wait on curl multi handle: %s",
					curl_multi_strerror(code));
			goto out;
		}

		if (0 == running)
			break;

		ts_now = zbx_time();
		if (ts_now - ts_last >= long_query_limit)
		{
			zabbix_log(LOG_LEVEL_WARNING, "waiting for Elasticsearch response " ZBX_FS_DBL "sec",
					ts_now - ts_last);
			ts_last = ts_now;
		}
	}
	while (0 != running);

	while (NULL != (msg = curl_multi_info_read(mhandle, &msg_num)))
	{
		zbx_elastic_conn_t	*conn;
		char			*error = NULL;

		if (CURLE_OK != curl_easy_getinfo(msg->easy_handle, CURLINFO_PRIVATE, (char **)&conn))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot obtain internal Elasticsearch data");
			goto out;
		}

		/* If the error is due to malformed data, there is no sense on re-trying to send. */
		/* That's why we actually check for transport and curl errors separately */
		if (CURLE_HTTP_RETURNED_ERROR == msg->data.result)
		{
			char		http_status[MAX_STRING_LEN];
			long int	response_code = -1;

			if (CURLE_OK == curl_easy_getinfo(msg->easy_handle, CURLINFO_RESPONSE_CODE, &response_code))
			{
				zbx_snprintf(http_status, sizeof(http_status), "HTTP status code: %ld",
						response_code);
				/* add retry 'too many requests' response */
				if (429 == response_code)
				{
					zabbix_log(LOG_LEVEL_ERR, "cannot query Elasticsearch, %s", http_status);
					zbx_vector_ptr_append(&retries, conn->handle);
					continue;
				}

			}
			else
			{
				zbx_strlcpy(http_status, "unknown HTTP status code", sizeof(http_status));
			}

			if ('\0' != *conn->resp.errbuf)
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot query Elasticsearch, HTTP error message: %s",
						conn->resp.errbuf);
			}
			else
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot query Elasticsearch, %s", http_status);
			}

			if (0 != conn->resp.page.offset)
				zabbix_log(LOG_LEVEL_ERR, "received response: %s", conn->resp.page.data);
		}
		else if (CURLE_OK != msg->data.result)
		{
			if ('\0' != *conn->resp.errbuf)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot query Elasticsearch: %s", conn->resp.errbuf);
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot query Elasticsearch: %s",
						curl_easy_strerror(msg->data.result));
			}

			/* If the error is due to curl internal problems or unrelated */
			/* problems with HTTP, we put the handle in a retry list and */
			/* remove it from the current execution loop */
			zbx_vector_ptr_append(&retries, conn->handle);
		}
		else if (SUCCEED == elastic_is_error_present(&conn->resp.page, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot send data to Elasticsearch: %s", error);
			zbx_free(error);

			/* If the error is due to elasticsearch internal problems (for example an index */
			/* became read-only), we put the handle in a retry list and */
			/* remove it from the current execution loop */
			zbx_vector_ptr_append(&retries, conn->handle);
		}
		else
		{
			/* mark connection as completed */
			conn->status = SUCCEED;
		}
	}

	ret = 0;

	for (int i = 0; i < retries.values_num; i++)
	{
		/* If the error is due to curl internal problems or unrelated */
		/* problems with HTTP, we put the handle in a retry list and */
		/* remove it from the current execution loop */
		if (CURLM_OK != (code = curl_multi_remove_handle(mhandle, retries.values[i])))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot remove handle from curl multi handle: %s",
					curl_multi_strerror(code));
		}
		else if (CURLM_OK != (code = curl_multi_add_handle(mhandle, retries.values[i])))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot add handle to curl multi handle: %s",
						curl_multi_strerror(code));
		}
		else
			ret++;
	}
out:
	zbx_vector_ptr_destroy(&retries);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform elasticsearch requests with retry logic                   *
 *                                                                            *
 * Parameters: d       - [IN] elasticsearch data structure                    *
 *             mhandle - [IN] curl multi handle                               *
 *                                                                            *
 * Return value: SUCCEED - all requests completed successfully                *
 *               FAIL    - curl multi handle operation failed                 *
 *                                                                            *
 * Comments: Retries failed requests with delay until all succeed or          *
 *           unrecoverable error occurs.                                      *
 *                                                                            *
 ******************************************************************************/
static int	history_elastic_perform(zbx_history_elastic_data_t *d, CURLM *mhandle)
{
	int	retries_num = 0, ret;

	while (0 < (ret = history_elastic_perform_once(d, mhandle)))
	{
		retries_num++;
		zabbix_log(LOG_LEVEL_ERR, "Elasticsearch database is down: reconnecting in %d seconds",
				ZBX_HISTORY_STORAGE_DOWN_DELAY);

		sleep(ZBX_HISTORY_STORAGE_DOWN_DELAY);
	}

	if (0 < retries_num)
		zabbix_log(LOG_LEVEL_ERR, "Elasticsearch database connection re-established");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute single elasticsearch query                                *
 *                                                                            *
 * Parameters: d          - [IN] elasticsearch data structure                 *
 *             mhandle    - [IN] curl multi handle                            *
 *             conn       - [IN/OUT] elasticsearch connection                 *
 *             retry_mode - [IN]                                              *
 *                                                                            *
 * Return value: SUCCEED - query executed successfully                        *
 *               FAIL    - query execution failed                             *
 *                                                                            *
 ******************************************************************************/
static int	history_elastic_query(zbx_history_elastic_data_t *d, CURLM *mhandle, zbx_elastic_conn_t *conn,
		zbx_elastic_retries_t retry_mode)
{
	CURLMcode	code;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	conn->resp.page.offset = 0;
	*conn->resp.errbuf = '\0';

	if (CURLM_OK != (code = curl_multi_add_handle(mhandle, conn->handle)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add handle to curl multi handle: %s",
				curl_multi_strerror(code));
		return FAIL;
	}

	if (ELASTIC_RETRIES_ON == retry_mode)
	{
		ret = history_elastic_perform(d, mhandle);
	}
	else
	{
		if (0 < (ret = history_elastic_perform_once(d, mhandle)))
			ret = FAIL;
	}

	if (CURLM_OK != (code = curl_multi_remove_handle(mhandle, conn->handle)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot remove handle from curl multi handle: %s",
				curl_multi_strerror(code));
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/************************************************************************************
 *                                                                                  *
 * Purpose: post historical data to elasticsearch storage                           *
 *                                                                                  *
 ************************************************************************************/
static zbx_uint64_t	history_elastic_flush(void *data)
{
	zbx_history_elastic_data_t	*d = (zbx_history_elastic_data_t *)data;
	zbx_uint64_t			flush_err = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	history_elastic_prepare(d);

	for (int i = 0; i < d->conns.values_num; i++)
	{
		zbx_elastic_conn_t	*conn = d->conns.values[i];
		CURLMcode		code;

		if (CURLM_OK != (code = curl_multi_add_handle(d->mhandle, conn->handle)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot add handle to curl multi handle: %s",
					curl_multi_strerror(code));
		}
	}

	(void)history_elastic_perform(d, d->mhandle);

	for (int i = 0; i < d->conns.values_num; i++)
	{
		zbx_elastic_conn_t	*conn = d->conns.values[i];
		CURLMcode		code;

		if (SUCCEED != conn->status)
			flush_err |= history_make_flush_error(ZBX_HISTORY_FLUSH_FAIL, conn->value_type);

		if (CURLM_OK != (code = curl_multi_remove_handle(d->mhandle, conn->handle)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot remove handle from curl multi handle: %s",
					curl_multi_strerror(code));
		}
	}

	zbx_vector_elastic_conn_ptr_clear_ext(&d->conns, elastic_conn_free);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): ret:%lx", __func__, flush_err);

	return flush_err;
}

/******************************************************************************************************************
 *                                                                                                                *
 * history interface support                                                                                      *
 *                                                                                                                *
 ******************************************************************************************************************/

/************************************************************************************
 *                                                                                  *
 * Purpose: get item history data from history storage                              *
 *                                                                                  *
 * Parameters:  data       - [IN] history storage data                              *
 *              itemid     - [IN] itemid                                            *
 *              value_type - [IN] value type                                        *
 *              start      - [IN] period start timestamp                            *
 *              count      - [IN/OUT] number of values to read                      *
 *              end        - [IN]  period end timestamp                             *
 *              values     - [OUT] item history data values                         *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values from ]<start>,<end>] interval or    *
 *           all values from the specified interval if count is zero.               *
 *                                                                                  *
 ************************************************************************************/
static int	elastic_get_values_for_period(zbx_history_elastic_data_t *data, zbx_uint64_t itemid,
		unsigned char value_type, time_t start, int *count, time_t end, zbx_vector_history_record_t *values)
{
	size_t			url_alloc = 0, url_offset = 0, id_alloc = 0, scroll_alloc = 0, scroll_offset = 0;
	int			empty, ret = FAIL, hits_num = 0;
	struct zbx_json		query;
	char			*scroll_id = NULL, *scroll_query = NULL, *error = NULL,
				*post_url = NULL;
	double			sec = 0;
	zbx_elastic_conn_t	conn = {0};
	char			*scroll = "?scroll=10s";


	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	start_str[32], end_str[32];

		strftime(start_str, sizeof(start_str), "%Y-%m-%d %H:%M:%S", zbx_localtime(&start, NULL));
		strftime(end_str, sizeof(end_str), "%Y-%m-%d %H:%M:%S", zbx_localtime(&end, NULL));

		zabbix_log(LOG_LEVEL_DEBUG, "In %s() window:(%s, %s] age: %s count:%d", __func__, start_str, end_str,
				zbx_age2str(end - start), *count);
	}

	history_elastic_prepare(data);

	if (0 != data->log_slow_queries)
		sec = zbx_time();

	/* prepare the json query for elasticsearch, apply ranges if needed */
	zbx_json_init(&query, ZBX_JSON_ALLOCATE);

	if (0 < *count)
	{
		/* creating scroll context can be extremely slow, avoid if not needed */
		if (ZBX_MAX_RESULT_WINDOW >= *count)
			scroll = "";

		zbx_json_adduint64(&query, "size", *count);
		zbx_json_addarray(&query, "sort");
		zbx_json_addobject(&query, NULL);
		zbx_json_addobject(&query, "clock");
		zbx_json_addstring(&query, "order", "desc", ZBX_JSON_TYPE_STRING);
		zbx_json_close(&query);
		zbx_json_close(&query);
		zbx_json_close(&query);
	}
	else
		zbx_json_adduint64(&query, "size", ZBX_MAX_RESULT_WINDOW);

	zbx_json_addobject(&query, "query");
	zbx_json_addobject(&query, "bool");
	zbx_json_addarray(&query, "must");
	zbx_json_addobject(&query, NULL);
	zbx_json_addobject(&query, "match");
	zbx_json_adduint64(&query, "itemid", itemid);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_addarray(&query, "filter");
	zbx_json_addobject(&query, NULL);
	zbx_json_addobject(&query, "range");
	zbx_json_addobject(&query, "clock");

	zbx_json_addstring(&query, "format", "epoch_second", ZBX_JSON_TYPE_STRING);
	if (0 < start)
		zbx_json_adduint64(&query, "gt", start);

	if (0 < end)
		zbx_json_adduint64(&query, "lte", end);

	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);
	zbx_json_close(&query);

	url_offset = 0;
	zbx_snprintf_alloc(&post_url, &url_alloc, &url_offset, "%s*/_search%s", elastic_get_index_name(value_type),
			scroll);

	if (SUCCEED != history_elastic_conn_init(&conn, data, post_url, "application/json", NULL, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot initialize Elasticsearch connection: %s", error);
		zbx_free(error);

		goto out;
	}

	if (FAIL == history_elastic_conn_set_post_data(&conn, query.buffer, query.buffer_size, &error))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot set post data for Elasticsearch: %s", error);
		zbx_free(error);

		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "sending query to %s; post data: %s", conn.url, query.buffer);

	/* initiate search context */

	if (SUCCEED != history_elastic_query(data, data->mhandle, &conn, ELASTIC_RETRIES_ON))
		goto out;

	/* fetch search results */

	if ('\0' != *scroll)
	{
		if (SUCCEED != history_elastic_conn_set_url_path(&conn, data, "_search/scroll", &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot set URL for Elasticsearch: %s", error);
			zbx_free(error);

			goto out;
		}
	}

	/* For processing the records, we need to keep track of the total requested and if the response from the */
	/* elasticsearch server is empty. For this we use two variables, empty and total. If the result is empty or */
	/* the total reach zero, we terminate the scrolling query and return what we currently have. */

	while (0 != conn.resp.page.offset)
	{
		struct zbx_json_parse	jp, jp_values, jp_item, jp_sub, jp_hits, jp_source;
		zbx_history_record_t	hr;
		const char		*p = NULL;

		empty = 1;

		zabbix_log(LOG_LEVEL_TRACE, "received from Elasticsearch: %s", conn.resp.page.data);

		zbx_json_open(conn.resp.page.data, &jp);
		zbx_json_brackets_open(jp.start, &jp_values);

		if ('\0' != *scroll)
		{
			/* get the scroll id immediately, for being used in subsequent queries */
			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_values, "_scroll_id", &scroll_id, &id_alloc,
					NULL))
			{
				zabbix_log(LOG_LEVEL_WARNING, "Elasticsearch version is not compatible with"
						" zabbix server. _scroll_id tag is absent");
			}
		}

		zbx_json_brackets_by_name(&jp_values, "hits", &jp_sub);
		zbx_json_brackets_by_name(&jp_sub, "hits", &jp_hits);

		while (NULL != (p = zbx_json_next(&jp_hits, p)))
		{
			empty = 0;

			hits_num++;

			if (SUCCEED != zbx_json_brackets_open(p, &jp_item))
				continue;

			if (SUCCEED != zbx_json_brackets_by_name(&jp_item, "_source", &jp_source))
				continue;

			if (SUCCEED != history_parse_value(&jp_source, value_type, &hr))
				continue;

			zbx_vector_history_record_append_ptr(values, &hr);

			if (0 != *count)
			{
				(*count)--;

				if (0 == *count)
				{
					empty = 1;
					break;
				}
			}
		}

		if (1 == empty || '\0' == *scroll)
		{
			ret = SUCCEED;
			break;
		}

		/* scroll to the next page */

		zabbix_log(LOG_LEVEL_DEBUG, "scroll next batch: sending query to %s; post data: %s values:%d", conn.url,
				scroll_query, values->values_num);

		scroll_offset = 0;
		zbx_snprintf_alloc(&scroll_query, &scroll_alloc, &scroll_offset,
				"{\"scroll\":\"10s\",\"scroll_id\":\"%s\"}\n", ZBX_NULL2EMPTY_STR(scroll_id));

		if (FAIL == history_elastic_conn_set_post_data(&conn, scroll_query, scroll_offset, &error))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot set post data for Elasticsearch: %s", error);
			zbx_free(error);
			break;
		}

		if (SUCCEED != history_elastic_query(data, data->mhandle, &conn, ELASTIC_RETRIES_ON))
			break;
	}

	/* as recommended by the elasticsearch documentation, we close the scroll search through a DELETE request */
	if (NULL != scroll_id && 0 != hits_num)
	{
		url_offset = 0;
		zbx_snprintf_alloc(&post_url, &url_alloc, &url_offset, "_search/scroll/%s", scroll_id);

		if (SUCCEED != (ret = history_elastic_conn_set_url_path(&conn, data, post_url, &error)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot set URL for Elasticsearch: %s", error);
			zbx_free(error);

			goto out;
		}

		if (SUCCEED != (ret = history_elastic_conn_set_delete(&conn, &error)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot set URL for Elasticsearch: %s", error);
			zbx_free(error);

			goto out;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "delete scroll: sending query to %s", conn.url);

		ret = history_elastic_query(data, data->mhandle, &conn, ELASTIC_RETRIES_ON);
	}

out:
	elastic_conn_clear(&conn);

	zbx_free(post_url);

	if (0 != data->log_slow_queries)
	{
		sec = zbx_time() - sec;
		if (sec > (double)data->log_slow_queries / 1000.0)
			zabbix_log(LOG_LEVEL_WARNING, "slow query: " ZBX_FS_DBL " sec, \"%s\"", sec, query.buffer);
	}

	zbx_json_free(&query);

	zbx_free(scroll_id);
	zbx_free(scroll_query);

	zbx_vector_history_record_sort(values, zbx_history_record_compare_desc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() values:%d", __func__, values->values_num);

	return ret;
}

/************************************************************************************
 *                                                                                  *
 * Purpose: get period window                                                       *
 *                                                                                  *
 * Parameters:  periods         - [IN] history storage periods                      *
 *              num             - [IN] count of history storage periods             *
 *              step            - [IN] period step                                  *
 *              clock_from      - [IN/OUT] period start timestamp                   *
 *              clock_to        - [IN] period end timestamp (including)             *
 *                                                                                  *
 * Return value: period - current period                                            *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function gets window in increments in order to touch as           *
 *           less partitions as possible                                            *
 *                                                                                  *
 ************************************************************************************/
static int	period_iter_next(const int *periods, int num, int *step, time_t *clock_from, time_t clock_to)
{
	int	period = periods[*step];

	if (-1 == period)
		return period;

	if (0 > (*clock_from = clock_to - period))
	{
		*clock_from = 0;
		*step = num - 1;
	}
	else
		(*step)++;

	return period;
}

/************************************************************************************
 *                                                                                  *
 * Purpose: get item history data from history storage                              *
 *                                                                                  *
 * Parameters:  data       - [IN] history storage data                              *
 *              itemid     - [IN] itemid                                            *
 *              value_type - [IN] value type                                        *
 *              count      - [IN] number of values to read                          *
 *              clock_to   - [IN] period end timestamp (including)                  *
 *              values     - [OUT] item history data values                         *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values and moves window in increments      *
 *           in order to touch as less partitions as possible                       *
 *                                                                                  *
 ************************************************************************************/
static int	elastic_read_values_by_count(zbx_history_elastic_data_t *data, zbx_uint64_t itemid,
		unsigned char value_type, int count, time_t clock_to, zbx_vector_history_record_t *values)
{
	const int	periods[] = {SEC_PER_HOUR, 12 * SEC_PER_HOUR, SEC_PER_DAY, SEC_PER_DAY, SEC_PER_WEEK,
				SEC_PER_MONTH, 0, -1};
	int		step = 0, ret = FAIL;
	time_t		clock_from, clock_to_shift;

	while (-1 != period_iter_next(periods, ARRSIZE(periods), &step, &clock_from, clock_to) && 1 < count)
	{
		clock_to_shift = clock_from;

		if (clock_from == clock_to)
			clock_from = 0;

		zbx_recalc_time_period(&clock_from, ZBX_RECALC_TIME_PERIOD_HISTORY, value_type);

		if (clock_from > clock_to)
			return SUCCEED;

		if (FAIL == (ret = elastic_get_values_for_period(data, itemid, value_type, clock_from, &count, clock_to,
				values)))
		{
			break;
		}

		clock_to = clock_to_shift;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch item history data from elasticsearch                        *
 *                                                                            *
 * Parameters: data       - [IN] history provider data                        *
 *             itemid     - [IN] itemid                                       *
 *             value_type - [IN] item value type                              *
 *             start      - [IN] period start timestamp                       *
 *             end        - [IN] period end timestamp                         *
 *             count      - [IN] number of values to read                     *
 *             values     - [OUT] item history records                        *
 *             error      - [OUT] error message                               *
 *                                                                            *
 * Return value: >=0      - number of records retrieved                       *
 *               FAIL     - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	history_elastic_fetch(void *data, zbx_uint64_t itemid, unsigned char value_type, time_t start,
		time_t end, int count, zbx_history_record_t **values, char **error)
{
	zbx_vector_history_record_t	result;
	zbx_history_elastic_data_t	*d = (zbx_history_elastic_data_t *)data;
	int				ret;

	ZBX_UNUSED(data);

	zbx_vector_history_record_create(&result);

	if (0 == count || 0 != start)
	{
		if (0 == count)
		{
			/* creating scroll context can be extremely slow, also it is unlikely to be ever needed */
			count = ZBX_MAX_RESULT_WINDOW;
			if (SUCCEED == (ret = elastic_get_values_for_period(d, itemid, value_type, start, &count, end,
					&result)))
			{
				if (0 == count)
				{
					zbx_history_record_vector_clean(&result, value_type);
					ret = elastic_get_values_for_period(d, itemid, value_type, start, &count, end,
							&result);
				}
			}
		}
		else
		{
			ret = elastic_get_values_for_period(d, itemid, value_type, start, &count, end,
				&result);
		}
	}
	else
		ret = elastic_read_values_by_count(d, itemid, value_type, count, end, &result);

	if (SUCCEED == ret)
	{
		*values = result.values;
		ret = result.values_num;
	}
	else
	{
		*error = zbx_strdup(NULL, "cannot read history data");
		zbx_vector_history_record_destroy(&result);
	}

	return ret;
}

static int	history_elastic_parse_bucket(const char *p_bucket, unsigned char value_type,
		zbx_vector_item_history_t *results)
{
	char			buffer[MAX_STRING_LEN];
	zbx_json_parse_t	jp_bucket, jp_top, jp_hits, jp_hits2;
	zbx_item_history_t	hist_local;
	int			index, rows_num = 0;

	if (SUCCEED != zbx_json_brackets_open(p_bucket, &jp_bucket))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot open items in Elasticsearch response starting with '%s'", p_bucket);
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp_bucket, "key", buffer, sizeof(buffer), NULL))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot get itemid from Elasticsearch response '%.*s'",
				(int)(jp_bucket.end - jp_bucket.start + 1), jp_bucket.start);
		goto out;
	}

	if (FAIL == zbx_is_uint64(buffer, &hist_local.itemid))
	{
		zabbix_log(LOG_LEVEL_ERR, "invalid itemid in Elasticsearch response '%s'", buffer);
		goto out;
	}

	if (FAIL == (index = zbx_vector_item_history_bsearch(results, hist_local, zbx_item_history_compare_by_itemid)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot find item " ZBX_FS_UI64 " in precache request", hist_local.itemid);
		goto out;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp_bucket, "top_values", &jp_top))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot find \"top_values\" tag in Elasticsearch response '%.*s'",
				(int)(jp_bucket.end - jp_bucket.start + 1), jp_bucket.start);
		goto out;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp_top, "hits", &jp_hits))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot find \"hits\" tag in Elasticsearch response '%.*s'",
				(int)(jp_top.end - jp_top.start + 1), jp_top.start);
		goto out;
	}

	if (SUCCEED != zbx_json_brackets_by_name(&jp_hits, "hits", &jp_hits2))

	{
		zabbix_log(LOG_LEVEL_ERR, "cannot find \"hits\" tag in Elasticsearch response '%.*s'",
				(int)(jp_hits.end - jp_hits.start + 1), jp_hits.start);
		goto out;
	}

	for (const char *p = zbx_json_next(&jp_hits2, NULL); NULL != p; p = zbx_json_next(&jp_hits2, p))
	{
		zbx_json_parse_t	jp_hit, jp_source;

		if (SUCCEED != zbx_json_brackets_open(p, &jp_hit))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot open \"hits\" array in Elasticsearch response starting "
					"with '%s'", p);
			continue;
		}

		if (SUCCEED != zbx_json_brackets_by_name(&jp_hit, "_source", &jp_source))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot find \"_source\" tag in Elasticsearch response '%.*s'",
					(int)(jp_hit.end - jp_hit.start + 1), jp_hit.start);
			continue;
		}

		zbx_history_record_t	hr;

		if (SUCCEED == history_parse_value(&jp_source, value_type, &hr))
		{
			zbx_vector_history_record_append_ptr(&results->values[index].rows, &hr);
			rows_num++;
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse history value in Elasticsearch response '%.*s'",
					(int)(jp_source.end - jp_source.start + 1), jp_source.start);
		}
	}
out:
	return rows_num;
}

static int	history_elastic_fetch_batch(void *data, zbx_vector_item_history_t *results,
		unsigned char value_type, time_t start, int limit, char **error)
{
	zbx_history_elastic_data_t	*d = (zbx_history_elastic_data_t *)data;
	size_t			url_alloc = 0, url_offset = 0;
	int			ret = FAIL, rows_num = 0;
	struct zbx_json		query;
	char			*post_url = NULL;
	double			sec = 0;
	zbx_elastic_conn_t	conn = {0};

	history_elastic_prepare(d);

	if (0 != d->log_slow_queries)
		sec = zbx_time();

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	start_str[32], end_str[32];
		time_t	end = time(NULL);

		strftime(start_str, sizeof(start_str), "%Y-%m-%d %H:%M:%S", localtime(&start));
		strftime(end_str, sizeof(end_str), "%Y-%m-%d %H:%M:%S", localtime(&end));

		zabbix_log(LOG_LEVEL_DEBUG, "In %s() window:(%s, %s] age: %s count:%d", __func__, start_str, end_str,
				zbx_age2str(end - start), 0);
	}

	/* prepare the json query for elasticsearch, apply ranges if needed */
	zbx_json_init(&query, ZBX_JSON_ALLOCATE);

	zbx_json_addint64(&query, "size", 0);
	zbx_json_addobject(&query, "query");	/* $.query. */
	zbx_json_addobject(&query, "bool");	/* $.query.bool. */

	zbx_json_addarray(&query, "filter");	/* $.query.bool.filter[ */
	zbx_json_addobject(&query, NULL);	/* $.query.bool.filter[. */

	zbx_json_addobject(&query, "range");	/* $.query.bool.filter[.range. */
	zbx_json_addobject(&query, "clock");	/* $.query.bool.filter[.range.clock. */
	zbx_json_addstring(&query, "format", "epoch_second", ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&query, "gt", start);
	zbx_json_close(&query);			/* $.query.bool.filter[.range. */
	zbx_json_close(&query);			/* $.query.bool.filter[. */
	zbx_json_close(&query);			/* $.query.bool.filter[ */

	zbx_json_addobject(&query, NULL);	/* $.query.bool.filter[. */
	zbx_json_addobject(&query, "terms");	/* $.query.bool.filter[.terms. */
	zbx_json_addarray(&query, "itemid");	/* #.query.bool.filter[.terms.itemid[ */

	for (int i = 0; i < results->values_num; i++)
		zbx_json_addint64(&query, NULL, results->values[i].itemid);

	zbx_json_close(&query);			/* $.query.bool.filter[.terms. */
	zbx_json_close(&query);			/* $.query.bool.filter[. */
	zbx_json_close(&query);			/* $.query.bool.filter[ */

	zbx_json_close(&query);			/* $.query.bool.*/
	zbx_json_close(&query);			/* $.query.*/
	zbx_json_close(&query);			/* $. */

	zbx_json_addobject(&query, "aggs");	/* $.aggs. */
	zbx_json_addobject(&query, "items");	/* $.aggs.items. */

	zbx_json_addobject(&query, "terms");	/* $.aggs.items.terms. */
	zbx_json_addstring(&query, "field", "itemid", ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&query, "size", results->values_num);
	zbx_json_close(&query);			/* $.aggs.items. */

	zbx_json_addobject(&query, "aggs");		/* $.aggs.items.aggs. */
	zbx_json_addobject(&query, "top_values");	/* $.aggs.items.aggs.top_values. */
	zbx_json_addobject(&query, "top_hits");		/* $.aggs.items.aggs.top_values.top_hits. */
	zbx_json_addint64(&query, "size", limit);
	zbx_json_addarray(&query, "sort");		/* $.aggs.items.aggs.top_values.top_hits.sort[ */
	zbx_json_addobject(&query, NULL);		/* $.aggs.items.aggs.top_values.top_hits.sort[. */
	zbx_json_addobject(&query, "clock");		/* $.aggs.items.aggs.top_values.top_hits.sort[.clock. */
	zbx_json_addstring(&query, "order", "desc", ZBX_JSON_TYPE_STRING);

	zbx_json_close(&query);				/* $.aggs.items.aggs.top_values.top_hits.sort[. */
	zbx_json_close(&query);				/* $.aggs.items.aggs.top_values.top_hits.sort[ */
	zbx_json_close(&query);				/* $.aggs.items.aggs.top_values.top_hits. */
	zbx_json_close(&query);				/* $.aggs.items.aggs.top_values. */
	zbx_json_close(&query);				/* $.aggs.items.aggs. */
	zbx_json_close(&query);				/* $.aggs.items. */
	zbx_json_close(&query);				/* $.aggs.*/
	zbx_json_close(&query);				/* $.*/

	url_offset = 0;
	zbx_snprintf_alloc(&post_url, &url_alloc, &url_offset, "%s*/_search", elastic_get_index_name(value_type));

	if (SUCCEED != history_elastic_conn_init(&conn, d, post_url, "application/json", NULL, error))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize curl for elasticsearch connection");
		goto out;
	}

	if (FAIL == history_elastic_conn_set_post_data(&conn, query.buffer, query.buffer_size, error))
		goto out;

	zabbix_log(LOG_LEVEL_DEBUG, "sending query to %s; post data: %s", conn.url, query.buffer);

	/* initiate search context */

	if (SUCCEED != history_elastic_query(data, d->mhandle, &conn, ELASTIC_RETRIES_ON))
		goto out;

	/* fetch search results */

	if (0 != conn.resp.page.offset)
	{
		struct zbx_json_parse	jp, jp_aggs, jp_items, jp_buckets;
		const char		*p = NULL;

		zabbix_log(LOG_LEVEL_TRACE, "received from Elasticsearch: %s", conn.resp.page.data);

		if (SUCCEED != zbx_json_open(conn.resp.page.data, &jp))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse Elasticsearch error: %s response '%s',"
					" query '%s'", zbx_json_strerror(), conn.resp.page.data, query.buffer);
			goto out;
		}
		if (SUCCEED != zbx_json_brackets_by_name(&jp, "aggregations", &jp_aggs))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot find aggregations in Elasticsearch response '%s',"
					" query '%s'", conn.resp.page.data, query.buffer);
			goto out;
		}

		if (SUCCEED != zbx_json_brackets_by_name(&jp_aggs, "items", &jp_items))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot find items in Elasticsearch response '%s',"
					" query '%s'", conn.resp.page.data, query.buffer);
			goto out;
		}

		if (SUCCEED != zbx_json_brackets_by_name(&jp_items, "buckets", &jp_buckets))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot find items in Elasticsearch response '%s',"
					" query '%s'", conn.resp.page.data, query.buffer);
			goto out;
		}

		while (NULL != (p = zbx_json_next(&jp_buckets, p)))
		{
			rows_num += history_elastic_parse_bucket(p, value_type, results);
		}
	}

	ret = SUCCEED;

out:
	elastic_conn_clear(&conn);

	zbx_free(post_url);

	if (0 != d->log_slow_queries)
	{
		sec = zbx_time() - sec;
		if (sec > (double)d->log_slow_queries / 1000.0)
			zabbix_log(LOG_LEVEL_WARNING, "slow query: " ZBX_FS_DBL " sec, \"%s\"", sec, query.buffer);
	}

	zbx_json_free(&query);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rows:%d results:%d", __func__, rows_num, results->values_num);

	return ret;
}


/******************************************************************************
 *                                                                            *
 * Purpose: write history data to elasticsearch storage                       *
 *                                                                            *
 * Parameters: data        - [IN] history provider data                       *
 *             value_type  - [IN] value type of history data                  *
 *             entries     - [IN] array of history entries to write           *
 *             entries_num - [IN] number of entries in the array              *
 *                                                                            *
 ******************************************************************************/
static void	history_elastic_write(void *data, unsigned char value_type,
		const zbx_history_entry_t * const *entries, int entries_num)
{
	zbx_history_elastic_data_t	*d = (zbx_history_elastic_data_t *)data;
	int				i;
	const zbx_history_entry_t	*h;
	struct zbx_json			json_idx, json;
	size_t				buf_alloc = 0, buf_offset = 0;
	char				pipeline[14]; /* index name length + suffix "-pipeline" */
	char				*buf = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_json_init(&json_idx, ZBX_IDX_JSON_ALLOCATE);
	zbx_json_addobject(&json_idx, "index");
	zbx_json_addstring(&json_idx, "_index", elastic_get_index_name(value_type), ZBX_JSON_TYPE_STRING);

	if (1 == d->pipelines)
	{
		zbx_snprintf(pipeline, sizeof(pipeline), "%s-pipeline", elastic_get_index_name(value_type));
		zbx_json_addstring(&json_idx, "pipeline", pipeline, ZBX_JSON_TYPE_STRING);
	}

	zbx_json_close(&json_idx);
	zbx_json_close(&json_idx);

	for (i = 0; i < entries_num; i++)
	{
		h = entries[i];

		zbx_json_init(&json, ZBX_JSON_ALLOCATE);

		zbx_json_adduint64(&json, "itemid", h->itemid);

		zbx_json_addstring(&json, "value", history_value2str(h), ZBX_JSON_TYPE_STRING);

		if (ITEM_VALUE_TYPE_LOG == h->value_type)
		{
			const zbx_log_value_t	*log;

			log = h->value.log;

			zbx_json_adduint64(&json, "timestamp", log->timestamp);
			zbx_json_addstring(&json, "source", ZBX_NULL2EMPTY_STR(log->source), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&json, "severity", log->severity);
			zbx_json_adduint64(&json, "logeventid", log->logeventid);
		}

		zbx_json_adduint64(&json, "clock", h->ts.sec);
		zbx_json_adduint64(&json, "ns", h->ts.ns);
		zbx_json_adduint64(&json, "ttl", h->ttl);

		zbx_json_close(&json);

		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%s\n%s\n", json_idx.buffer, json.buffer);

		zbx_json_free(&json);
	}

	if (NULL != buf)
		history_elastic_add_conn(d, value_type, buf);

	zbx_json_free(&json_idx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: populate value type information for elasticsearch provider        *
 *                                                                            *
 * Parameters:                                                                *
 *     d    - [IN] elasticsearch history data structure                       *
 *     info - [OUT] history provider information structure                    *
 *                                                                            *
 ******************************************************************************/
static void	history_elastic_get_value_type_data(zbx_history_elastic_data_t *d, zbx_history_provider_info_t *info)
{
	zbx_vector_history_provider_value_type_info_reserve(&info->value_types, ITEM_VALUE_TYPE_COUNT);

	for (unsigned char i = 0; i <= ITEM_VALUE_TYPE_JSON; i++)
	{
		if (FAIL == ZBX_HISTORY_CHECK_TYPE_FLAGS(d->value_type_flags, i))
			continue;

		zbx_history_provider_value_type_info_t	vti = {.value_type = i};

		zbx_vector_history_provider_value_type_info_append(&info->value_types, vti);
	}
}

/************************************************************************************
 *                                                                                  *
 * Purpose: query elasticsearch version and extracts the numeric version from       *
 *          the response string                                                     *
 *                                                                                  *
 ************************************************************************************/
static int	history_elastic_get_info(void *data, zbx_history_provider_info_t *info, char **error)
{
#define RIGHT2(x)	((int)((zbx_uint32_t)(x) - ((zbx_uint32_t)((x)/100))*100))
	zbx_history_elastic_data_t	*d = (zbx_history_elastic_data_t *)data;
	struct zbx_json_parse		jp, jp_values, jp_sub;
	size_t				version_len = 0;
	char				*version_friendly = NULL;
	int				major_num, minor_num, increment_num, ret = FAIL;
	zbx_uint32_t			version = ZBX_DBVERSION_UNDEFINED;
	zbx_elastic_conn_t		conn = {0};
	CURLM				*mhandle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (mhandle = curl_multi_init()))
	{
		*error = zbx_strdup(NULL, "Cannot initialize curl multi session");
		goto out;
	}

	if (SUCCEED != history_elastic_conn_init(&conn, d, NULL, "application/json", NULL, error))
		goto out;

	if (FAIL == history_elastic_query(d, mhandle, &conn, ELASTIC_RETRIES_OFF))
	{
		*error = zbx_strdup(NULL, "Cannot perform Elasticsearch query");
		goto out;
	}

	if (SUCCEED != zbx_json_open(conn.resp.page.data, &jp) ||
		SUCCEED != zbx_json_brackets_open(jp.start, &jp_values) ||
		SUCCEED != zbx_json_brackets_by_name(&jp_values, "version", &jp_sub) ||
		SUCCEED != zbx_json_value_by_name_dyn(&jp_sub, "number", &version_friendly, &version_len, NULL))
	{
		*error = zbx_strdup(NULL, "cannot extract Elasticsearch version information");
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "Elasticsearch version retrieved unparsed: %s", version_friendly);

	if (3 != sscanf(version_friendly, "%d.%d.%d", &major_num, &minor_num, &increment_num) ||
			major_num >= 100 || major_num <= 0 || minor_num >= 100 || minor_num < 0 ||
			increment_num >= 100 || increment_num < 0)
	{
		*error = zbx_dsprintf(NULL, "Failed to detect Elasticsearch version from the "
				"following query result: %s", version_friendly);
	}
	else
	{
		version = major_num * 10000 + minor_num * 100 + increment_num;
	}

	info->database = zbx_strdup(NULL, "Elasticsearch");
	info->provider = zbx_strdup(NULL, HISTORY_PROVIDER_ELASTICSEARCH);
	info->current_version = version;
	info->min_version = ZBX_ELASTIC_MIN_VERSION;
	info->max_version = ZBX_ELASTIC_MAX_VERSION;
	info->min_supported_version = ZBX_DBVERSION_UNDEFINED;

	info->friendly_current_version = version_friendly;
	info->friendly_min_version = zbx_strdup(NULL, ZBX_ELASTIC_MIN_VERSION_STR);
	info->friendly_max_version = zbx_strdup(NULL, ZBX_ELASTIC_MAX_VERSION_STR);
	info->friendly_min_supported_version = zbx_strdup(NULL, ZBX_ELASTIC_MIN_VERSION_STR);

	zbx_vector_history_provider_value_type_info_create(&info->value_types);
	history_elastic_get_value_type_data(d, info);
	ret = SUCCEED;
out:
	elastic_conn_clear(&conn);
	curl_multi_cleanup(mhandle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s version:%lu", __func__, zbx_result_string(ret),
			(unsigned long)version);

	return ret;

#undef RIGHT2
}

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize elasticsearch history data structure        *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] array of history storage options                    *
 *     options_num - [IN] number of elements in the options array             *
 *     error       - [OUT] error message if function fails                    *
 *                                                                            *
 * Return value: elasticsearch history provider or NULL on failure            *
 *                                                                            *
 ******************************************************************************/
static void	*history_elastic_create_data(const zbx_history_option_t *options, int options_num, char **error)
{
	zbx_history_elastic_data_t	*data;
	const char			*value;

	if (NULL == (value = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_URL)))
	{
		*error = zbx_strdup(*error, "missing \"url\" option for Elasticsearch history backend");
		return NULL;
	}

	data = (zbx_history_elastic_data_t *)zbx_malloc(NULL, sizeof(zbx_history_elastic_data_t));
	memset(data, 0, sizeof(zbx_history_elastic_data_t));

	data->base_url = zbx_strdup(NULL, value);
	zbx_rtrim(data->base_url, "/");

	if (NULL != (value = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_DATE_INDEX)))
		data->pipelines = (unsigned char)atoi(value);

	if (NULL != (value = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES)))
		data->log_slow_queries = atoi(value);

	zbx_vector_elastic_conn_ptr_create(&data->conns);

	data->value_type_flags = history_options_type_mask(options, options_num);

	return (void *)data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: close elasticsearch history provider                              *
 *                                                                            *
 ******************************************************************************/
static void	history_elastic_close(void *data)
{
	zbx_history_elastic_data_t	*d = (zbx_history_elastic_data_t *)data;

	if (NULL != d->mhandle)
		curl_multi_cleanup(d->mhandle);

	zbx_vector_elastic_conn_ptr_destroy(&d->conns);

	zbx_free(d->base_url);
	zbx_free(d);
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate configuration options for elasticsearch history provider *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] configuration options                               *
 *     options_num - [IN] number of configuration options                     *
 *                                                                            *
 ******************************************************************************/
static void	history_elastic_validate_options(const zbx_history_option_t *options, int options_num)
{
	const char	*supported_options = ""
				HISTORY_PROVIDER_OPTION_NAME ","
				HISTORY_PROVIDER_OPTION_URL ","
				HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES ","
				HISTORY_PROVIDER_OPTION_VALUE_TYPES ","
				HISTORY_PROVIDER_OPTION_SOURCE_IP ","
				HISTORY_PROVIDER_OPTION_DATE_INDEX ","
				HISTORY_PROVIDER_OPTION_SSL_CERT_FILE ","
				HISTORY_PROVIDER_OPTION_SSL_KEY_FILE ","
				HISTORY_PROVIDER_OPTION_SSL_KEY_PASSWORD ","
				HISTORY_PROVIDER_OPTION_SSL_VERIFY_PEER ","
				HISTORY_PROVIDER_OPTION_SSL_VERIFY_HOST ","
				HISTORY_PROVIDER_OPTION_SSL_CA_LOCATION ","
				HISTORY_PROVIDER_OPTION_SSL_CERT_LOCATION ","
				HISTORY_PROVIDER_OPTION_SSL_KEY_LOCATION ","
				HISTORY_PROVIDER_OPTION_PRECACHE ","
			;

	for (int i = 0; i < options_num; i++)
	{
		if (SUCCEED != zbx_str_in_list(supported_options, options[i].name, ','))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unsupported Elasticsearch history provider option: %s=%s",
					options[i].name, options[i].value);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: open and initialize the elasticsearch history provider            *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] array of history storage options                    *
 *     options_num - [IN] number of elements in the options array             *
 *     error       - [OUT] error message if function fails                    *
 *                                                                            *
 * Return value: history provider or NULL if initialization fails             *
 *                                                                            *
 ******************************************************************************/
zbx_history_provider_t	*history_elastic_open(const zbx_history_option_t *options, int options_num, char **error)
{
	zbx_history_provider_t	*provider;
	void			*data;

	zbx_curl_init();

	history_elastic_validate_options(options, options_num);

	if (NULL == (data = history_elastic_create_data(options, options_num, error)))
		return NULL;

	provider = (zbx_history_provider_t *)zbx_malloc(NULL, sizeof(zbx_history_provider_t));

	provider->name = zbx_strdup(NULL, HISTORY_PROVIDER_ELASTICSEARCH);
	provider->traits = ZBX_HISTORY_TRAIT_TYPES_NOBIN | history_options_precache(options, options_num);
	provider->impl.write = history_elastic_write;
	provider->impl.flush = history_elastic_flush;
	provider->impl.fetch = history_elastic_fetch;
	provider->impl.fetch_batch = history_elastic_fetch_batch;
	provider->impl.close = history_elastic_close;
	provider->impl.get_info = history_elastic_get_info;

	provider->data = data;

	return provider;
}

#else
zbx_history_provider_t *history_elastic_open(const zbx_history_option_t *options, int options_num, char **error)
{
	ZBX_UNUSED(options);
	ZBX_UNUSED(options_num);

	*error = zbx_strdup(*error, "Zabbix must be compiled with cURL library for Elasticsearch history provider");

	return NULL;
}
#endif
