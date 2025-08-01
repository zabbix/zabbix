/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#define		ZBX_IDX_JSON_ALLOCATE		256
#define		ZBX_JSON_ALLOCATE		2048

const char	*value_type_str[] = {"dbl", "str", "log", "uint", "text"};

typedef struct
{
	unsigned char		value_type;
	int			status;
	char			*post_url;
	char			*buf;
	CURL			*handle;

	zbx_curl_response_t	resp;
}
zbx_elastic_conn_t;

ZBX_PTR_VECTOR_DECL(elastic_conn_ptr, zbx_elastic_conn_t *)
ZBX_PTR_VECTOR_IMPL(elastic_conn_ptr, zbx_elastic_conn_t *)

typedef struct
{
	unsigned char			log_slow_queries;
	unsigned char			pipelines;

	char				*base_url;

	zbx_vector_elastic_conn_ptr_t	conns;
}
zbx_history_elastic_data_t;

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
			exit(EXIT_FAILURE);
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
			exit(EXIT_FAILURE);
	}

	return buffer;
}

static int	history_parse_value(struct zbx_json_parse *jp, unsigned char value_type, zbx_history_record_t *hr)
{
	char	*value = NULL;
	size_t	value_alloc = 0;
	int	ret = FAIL;

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
	zbx_free(value);

	return ret;
}

static void	elastic_log_error(CURL *handle, CURLcode error, const char *errbuf, const zbx_httppage_t *page)
{
	char		http_status[MAX_STRING_LEN];
	long int	http_code;

	if (CURLE_HTTP_RETURNED_ERROR == error)
	{
		if (CURLE_OK == curl_easy_getinfo(handle, CURLINFO_RESPONSE_CODE, &http_code))
			zbx_snprintf(http_status, sizeof(http_status), "HTTP status code: %ld", http_code);
		else
			zbx_strlcpy(http_status, "unknown HTTP status code", sizeof(http_status));

		if (0 != page->offset)
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot get values from elasticsearch, %s, message: %s", http_status,
					page->data);
		}
		else
			zabbix_log(LOG_LEVEL_ERR, "cannot get values from elasticsearch, %s", http_status);
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot get values from elasticsearch: %s",
				'\0' != *errbuf ? errbuf : curl_easy_strerror(error));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: check an error from Elastic json response                         *
 *                                                                            *
 * Parameters: page - [IN]  the buffer with json response                     *
 *             err  - [OUT] the parse error message. If the error value is    *
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
			FAIL == rc_js ? " / elasticsearch version is not fully compatible with zabbix server" : "");

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

	zbx_free(conn->resp.page.data);
	zbx_free(conn->post_url);
	zbx_free(conn->buf);
}


static void	elastic_conn_free(zbx_elastic_conn_t *conn)
{
	elastic_conn_clear(conn);
	zbx_free(conn);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a new ElasticSearch connection                                *
 *                                                                            *
 * Parameters:                                                                *
 *     d          - [IN/OUT] Elasticsearch history data structure             *
 *     value_type - [IN] value type                                           *
 *     data       - [IN] JSON-formatted historical data to be sent            *
 *                                                                            *
 ******************************************************************************/
static void	history_elastic_add_conn(zbx_history_elastic_data_t *d, unsigned char value_type, char *data)
{
	zbx_elastic_conn_t	*conn;
	CURLoption		opt;
	CURLcode		err;
	char			*error = NULL;

	conn = (zbx_elastic_conn_t *)zbx_malloc(NULL, sizeof(zbx_elastic_conn_t));
	memset(conn, 0, sizeof(zbx_elastic_conn_t));
	conn->buf = data;
	conn->value_type = value_type;

	if (NULL == (conn->handle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL session");
		elastic_conn_free(conn);

		return;
	}

	conn->post_url = zbx_dsprintf(NULL, "%s/_bulk", d->base_url);

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_URL, conn->post_url)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_POST, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_POSTFIELDS, conn->buf)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEFUNCTION,
					history_curl_recv)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEDATA,
					&conn->resp.page)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_FAILONERROR, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_ERRORBUFFER,
					conn->resp.errbuf)) ||
			CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_ACCEPT_ENCODING, "")))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot set cURL option %d: [%s]", (int)opt, curl_easy_strerror(err));
		elastic_conn_free(conn);

		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_https(conn->handle, &error))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s", error);
		elastic_conn_free(conn);

		goto out;
	}

	*conn->resp.errbuf = '\0';

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_PRIVATE, &conn->resp)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot set cURL option %d: [%s]", (int)opt, curl_easy_strerror(err));
		elastic_conn_free(conn);
		goto out;
	}

	conn->resp.page.offset = 0;

	if (0 < conn->resp.page.alloc)
		*conn->resp.page.data = '\0';

	zbx_vector_elastic_conn_ptr_append(&d->conns, conn);

	return;
out:
	zbx_free(error);
}

/************************************************************************************
 *                                                                                  *
 * Purpose: post historical data to elastic storage                                 *
 *                                                                                  *
 ************************************************************************************/
static zbx_uint64_t	history_elastic_flush(void *data)
{
	zbx_history_elastic_data_t	*d = (zbx_history_elastic_data_t *)data;
	struct curl_slist		*curl_headers = NULL;
	int				i, running, previous, msgnum;
	CURLMsg				*msg;
	zbx_vector_ptr_t		retries;
	CURLcode			err;
	CURLM				*mhandle;
	zbx_uint64_t			flush_err = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (mhandle = curl_multi_init()))
	{
		zbx_error("Cannot initialize cURL multi session");
		exit(EXIT_FAILURE);
	}

	for (i = 0; i < d->conns.values_num; i++)
	{
		curl_multi_add_handle(mhandle, d->conns.values[i]->handle);
		d->conns.values[i]->status = FAIL;
	}

	zbx_vector_ptr_create(&retries);

	curl_headers = curl_slist_append(curl_headers, "Content-Type: application/x-ndjson");

	for (i = 0; i < d->conns.values_num; i++)
	{
		zbx_elastic_conn_t	*conn = d->conns.values[i];

		if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_HTTPHEADER, curl_headers)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot set cURL option %d: [%s]", (int)CURLOPT_HTTPHEADER,
					curl_easy_strerror(err));

			goto clean;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "sending %s", conn->buf);
	}

try_again:
	previous = 0;

	do
	{
		int			fds;
		CURLMcode		code;
		char			*error;
		zbx_curl_response_t	*resp;

		if (CURLM_OK != (code = curl_multi_perform(mhandle, &running)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot perform on curl multi handle: %s", curl_multi_strerror(code));
			break;
		}

		if (CURLM_OK != (code = zbx_curl_multi_wait(mhandle, ZBX_HISTORY_STORAGE_DOWN, &fds)))
		{
			curl_multi_cleanup(mhandle);

			zabbix_log(LOG_LEVEL_ERR, "cannot wait on curl multi handle: %s", curl_multi_strerror(code));
			break;
		}

		if (previous == running)
			continue;

		while (NULL != (msg = curl_multi_info_read(mhandle, &msgnum)))
		{
			/* If the error is due to malformed data, there is no sense on re-trying to send. */
			/* That's why we actually check for transport and curl errors separately */
			if (CURLE_HTTP_RETURNED_ERROR == msg->data.result)
			{
				if (CURLE_OK == curl_easy_getinfo(msg->easy_handle, CURLINFO_PRIVATE, (char **)&resp) &&
						'\0' != *resp->errbuf)
				{
					zabbix_log(LOG_LEVEL_ERR, "cannot send data to elasticsearch, HTTP error"
							" message: %s", resp->errbuf);
				}
				else
				{
					char		http_status[MAX_STRING_LEN];
					long int	response_code;

					if (CURLE_OK == curl_easy_getinfo(msg->easy_handle,
							CURLINFO_RESPONSE_CODE, &response_code))
					{
						zbx_snprintf(http_status, sizeof(http_status), "HTTP status code: %ld",
								response_code);
					}
					else
					{
						zbx_strlcpy(http_status, "unknown HTTP status code",
								sizeof(http_status));
					}

					zabbix_log(LOG_LEVEL_ERR, "cannot send data to elasticsearch, %s", http_status);
				}
			}
			else if (CURLE_OK != msg->data.result)
			{
				if (CURLE_OK == curl_easy_getinfo(msg->easy_handle, CURLINFO_PRIVATE,
						(char **)&resp) && '\0' != *resp->errbuf)
				{
					zabbix_log(LOG_LEVEL_WARNING, "cannot send data to elasticsearch: %s",
							resp->errbuf);
				}
				else
				{
					zabbix_log(LOG_LEVEL_WARNING, "cannot send data to elasticsearch: %s",
							curl_easy_strerror(msg->data.result));
				}

				/* If the error is due to curl internal problems or unrelated */
				/* problems with HTTP, we put the handle in a retry list and */
				/* remove it from the current execution loop */
				zbx_vector_ptr_append(&retries, msg->easy_handle);
				curl_multi_remove_handle(mhandle, msg->easy_handle);
			}
			else if (CURLE_OK == curl_easy_getinfo(msg->easy_handle, CURLINFO_PRIVATE, (char **)&resp)
					&& SUCCEED == elastic_is_error_present(&resp->page, &error))
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s() cannot send data to elasticsearch: %s",
						__func__, error);
				zbx_free(error);

				/* If the error is due to elastic internal problems (for example an index */
				/* became read-only), we put the handle in a retry list and */
				/* remove it from the current execution loop */
				zbx_vector_ptr_append(&retries, msg->easy_handle);
				curl_multi_remove_handle(mhandle, msg->easy_handle);
			}
			else
			{
				/* mark connection as completed */
				for (i = 0; i < d->conns.values_num; i++)
				{
					if (d->conns.values[i]->handle == msg->easy_handle)
						d->conns.values[i]->status = SUCCEED;
				}

			}
		}

		previous = running;
	}
	while (running);

	/* We check if we have handles to retry. If yes, we put them back in the multi */
	/* handle and go to the beginning of the do while() for try sending the data again */
	/* after sleeping for ZBX_HISTORY_STORAGE_DOWN / 1000 (seconds) */
	if (0 < retries.values_num)
	{
		for (i = 0; i < retries.values_num; i++)
			curl_multi_add_handle(mhandle, retries.values[i]);

		zbx_vector_ptr_clear(&retries);

		sleep(ZBX_HISTORY_STORAGE_DOWN / 1000);
		goto try_again;
	}
clean:
	for (i = 0; i < d->conns.values_num; i++)
	{
		if (SUCCEED != d->conns.values[i]->status)
			flush_err |= history_make_flush_error(ZBX_HISTORY_FLUSH_FAIL, d->conns.values[i]->value_type);
	}

	curl_slist_free_all(curl_headers);

	zbx_vector_ptr_destroy(&retries);
	zbx_vector_elastic_conn_ptr_clear_ext(&d->conns, elastic_conn_free);

	curl_multi_cleanup(mhandle);

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
 * Parameters:  data       - [IN] the history storage data                          *
 *              itemid     - [IN] the itemid                                        *
 *              value_type - [IN] the value type                                    *
 *              start      - [IN] the period start timestamp                        *
 *              count      - [IN/OUT] the number of values to read                  *
 *              end        - [IN] the period end timestamp                          *
 *              values     - [OUT] the item history data values                     *
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
	int			empty, ret;
	CURLcode		err;
	CURL			*handle;
	struct zbx_json		query;
	struct curl_slist	*curl_headers = NULL;
	char			*scroll_id = NULL, *scroll_query = NULL, errbuf[CURL_ERROR_SIZE], *error = NULL,
				*post_url = NULL;
	CURLoption		opt;
	double			sec = 0;
	zbx_httppage_t		page = {0};

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	start_str[32], end_str[32];

		strftime(start_str, sizeof(start_str), "%Y-%m-%d %H:%M:%S", localtime(&start));
		strftime(end_str, sizeof(end_str), "%Y-%m-%d %H:%M:%S", localtime(&end));

		zabbix_log(LOG_LEVEL_DEBUG, "In %s() window:(%s, %s] age: %s count:%d", __func__, start_str, end_str,
				zbx_age2str(end - start), *count);
	}

	if (0 != data->log_slow_queries)
		sec = zbx_time();

	ret = FAIL;

	if (NULL == (handle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL session");

		return FAIL;
	}

	url_offset = 0;
	zbx_snprintf_alloc(&post_url, &url_alloc, &url_offset, "%s/%s*/_search?scroll=10s", data->base_url,
			value_type_str[value_type]);

	/* prepare the json query for elasticsearch, apply ranges if needed */
	zbx_json_init(&query, ZBX_JSON_ALLOCATE);

	if (0 < *count)
	{
		zbx_json_adduint64(&query, "size", *count);
		zbx_json_addarray(&query, "sort");
		zbx_json_addobject(&query, NULL);
		zbx_json_addobject(&query, "clock");
		zbx_json_addstring(&query, "order", "desc", ZBX_JSON_TYPE_STRING);
		zbx_json_close(&query);
		zbx_json_close(&query);
		zbx_json_close(&query);
	}

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

	curl_headers = curl_slist_append(curl_headers, "Content-Type: application/json");

	if (CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_URL, post_url)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_POSTFIELDS, query.buffer)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_WRITEFUNCTION,
					history_curl_recv)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_WRITEDATA, &page)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_HTTPHEADER, curl_headers)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_FAILONERROR, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_ERRORBUFFER, errbuf)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_ACCEPT_ENCODING, "")))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot set cURL option %d: [%s]", (int)opt, curl_easy_strerror(err));
		goto out;
	}

	if (SUCCEED != zbx_curl_setopt_https(handle, &error))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s", error);
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "sending query to %s; post data: %s", post_url, query.buffer);

	page.offset = 0;
	*errbuf = '\0';
	if (CURLE_OK != (err = curl_easy_perform(handle)))
	{
		elastic_log_error(handle, err, errbuf, &page);
		goto out;
	}

	url_offset = 0;
	zbx_snprintf_alloc(&post_url, &url_alloc, &url_offset, "%s/_search/scroll", data->base_url);

	if (CURLE_OK != (err = curl_easy_setopt(handle, CURLOPT_URL, post_url)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot set cURL option %d: [%s]", (int)CURLOPT_URL,
				curl_easy_strerror(err));
		goto out;
	}

	/* For processing the records, we need to keep track of the total requested and if the response from the */
	/* elasticsearch server is empty. For this we use two variables, empty and total. If the result is empty or */
	/* the total reach zero, we terminate the scrolling query and return what we currently have. */
	do
	{
		struct zbx_json_parse	jp, jp_values, jp_item, jp_sub, jp_hits, jp_source;
		zbx_history_record_t	hr;
		const char		*p = NULL;

		empty = 1;

		zabbix_log(LOG_LEVEL_DEBUG, "received from elasticsearch: %s", page.data);

		zbx_json_open(page.data, &jp);
		zbx_json_brackets_open(jp.start, &jp_values);

		/* get the scroll id immediately, for being used in subsequent queries */
		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_values, "_scroll_id", &scroll_id, &id_alloc, NULL))
		{
			zabbix_log(LOG_LEVEL_WARNING, "elasticsearch version is not compatible with zabbix server. "
					"_scroll_id tag is absent");
		}

		zbx_json_brackets_by_name(&jp_values, "hits", &jp_sub);
		zbx_json_brackets_by_name(&jp_sub, "hits", &jp_hits);

		while (NULL != (p = zbx_json_next(&jp_hits, p)))
		{
			empty = 0;

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

		if (1 == empty)
		{
			ret = SUCCEED;
			break;
		}

		/* scroll to the next page */
		scroll_offset = 0;
		zbx_snprintf_alloc(&scroll_query, &scroll_alloc, &scroll_offset,
				"{\"scroll\":\"10s\",\"scroll_id\":\"%s\"}\n", ZBX_NULL2EMPTY_STR(scroll_id));

		if (CURLE_OK != (err = curl_easy_setopt(handle, CURLOPT_POSTFIELDS, scroll_query)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot set cURL option %d: [%s]", (int)CURLOPT_POSTFIELDS,
					curl_easy_strerror(err));
			break;
		}

		page.offset = 0;
		*errbuf = '\0';
		if (CURLE_OK != (err = curl_easy_perform(handle)))
		{
			elastic_log_error(handle, err, errbuf, &page);
			break;
		}
	}
	while (0 == empty);

	/* as recommended by the elasticsearch documentation, we close the scroll search through a DELETE request */
	if (NULL != scroll_id)
	{
		url_offset = 0;
		zbx_snprintf_alloc(&post_url, &url_alloc, &url_offset, "%s/_search/scroll/%s", data->base_url,
				scroll_id);

		if (CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_URL, post_url)) ||
				CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_POSTFIELDS, "")) ||
				CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_CUSTOMREQUEST, "DELETE")))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot set cURL option %d: [%s]", (int)opt,
					curl_easy_strerror(err));
			ret = FAIL;
			goto out;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "elasticsearch closing scroll %s", post_url);

		page.offset = 0;
		*errbuf = '\0';
		if (CURLE_OK != (err = curl_easy_perform(handle)))
			elastic_log_error(handle, err, errbuf, &page);
	}

out:
	curl_easy_cleanup(handle);
	zbx_free(post_url);
	zbx_free(page.data);

	curl_slist_free_all(curl_headers);

	if (0 != data->log_slow_queries)
	{
		sec = zbx_time() - sec;
		if (sec > (double)data->log_slow_queries / 1000.0)
			zabbix_log(LOG_LEVEL_WARNING, "slow query: " ZBX_FS_DBL " sec, \"%s\"", sec, query.buffer);
	}

	zbx_json_free(&query);

	zbx_free(scroll_id);
	zbx_free(scroll_query);
	zbx_free(error);

	zbx_vector_history_record_sort(values, (zbx_compare_func_t)zbx_history_record_compare_desc_func);

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
 *              clock_to_shift  - [OUT] next period end timestamp                   *
 *                                                                                  *
 * Return value: period - current period                                            *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function gets window in increments in order to touch as           *
 *           less partitions as possible                                            *
 *                                                                                  *
 ************************************************************************************/
static int	period_iter_next(const int *periods, int num, int *step, time_t *clock_from, time_t clock_to,
		time_t *clock_to_shift)
{
	int	period = periods[*step];

	if (-1 == period)
		return period;

	if (0 > (*clock_from = clock_to - period))
	{
		*clock_from = clock_to;

		*step = num - 1;

		return period;
	}

	*clock_to_shift = clock_to - period;
	(*step)++;

	return period;
}

/************************************************************************************
 *                                                                                  *
 * Purpose: get item history data from history storage                              *
 *                                                                                  *
 * Parameters:  data       - [IN] the history storage data                          *
 *              itemid     - [IN] the itemid                                        *
 *              value_type - [IN] the value type                                    *
 *              count      - [IN] the number of values to read                      *
 *              clock_to   - [IN] the period end timestamp (including)              *
 *              values     - [OUT] the item history data values                     *
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

	while (-1 != period_iter_next(periods, ARRSIZE(periods), &step, &clock_from, clock_to, &clock_to_shift) &&
			1 < count)
	{
		if (clock_from == clock_to)
			clock_from = 0;

		zbx_recalc_time_period(&clock_from, ZBX_RECALC_TIME_PERIOD_HISTORY);

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
 * Purpose: fetch item history data from ElasticSearch                        *
 *                                                                            *
 * Parameters: data       - [IN] history provider data                        *
 *             itemid     - [IN] the itemid                                   *
 *             value_type - [IN] the item value type                          *
 *             start      - [IN] the period start timestamp                   *
 *             end        - [IN] the period end timestamp                     *
 *             count      - [IN] the number of values to read                 *
 *             values     - [OUT] the item history records                    *
 *             error      - [OUT] error message                               *
 *                                                                            *
 * Return value: >=0      - number of records retrieved                       *
 *               FAIL     - otherwise                                         *
 *                                                                            *
 * Comments: The                                                              *
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
		ret = elastic_get_values_for_period(d, itemid, value_type, start, &count, end, &result);
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

/******************************************************************************
 *                                                                            *
 * Purpose: write history data to Elasticsearch storage                       *
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
	zbx_json_addstring(&json_idx, "_index", value_type_str[value_type], ZBX_JSON_TYPE_STRING);

	if (1 == d->pipelines)
	{
		zbx_snprintf(pipeline, sizeof(pipeline), "%s-pipeline", value_type_str[value_type]);
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


/************************************************************************************
 *                                                                                  *
 * Purpose: query elastic search version and extracts the numeric version from      *
 *          the response string                                                     *
 *                                                                                  *
 ************************************************************************************/
static int	history_elastic_get_info(void *data, zbx_history_provider_info_t *info, char **error)
{
#define ZBX_ELASTIC_MIN_VERSION					70000
#define ZBX_ELASTIC_MIN_VERSION_STR				"7.x"
#define ZBX_ELASTIC_MAX_VERSION					89999
#define ZBX_ELASTIC_MAX_VERSION_STR				"8.x"

#define RIGHT2(x)	((int)((zbx_uint32_t)(x) - ((zbx_uint32_t)((x)/100))*100))
	zbx_history_elastic_data_t	*d = (zbx_history_elastic_data_t *)data;
	zbx_httppage_t			page;
	struct zbx_json_parse		jp, jp_values, jp_sub;
	struct curl_slist		*curl_headers;
	CURLcode			err;
	CURLoption			opt;
	CURL				*handle;
	size_t				version_len = 0;
	char				*version_friendly = NULL, errbuf[CURL_ERROR_SIZE];
	int				major_num, minor_num, increment_num, ret = FAIL;
	zbx_uint32_t			version;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	memset(&page, 0, sizeof(zbx_httppage_t));

	if (NULL == (handle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "cannot initialize cURL session");
		goto out;
	}

	curl_headers = curl_slist_append(NULL, "Content-Type: application/json");

	if (CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_URL, d->base_url)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_WRITEFUNCTION, history_curl_recv)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_WRITEDATA, &page)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_HTTPHEADER, curl_headers)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_FAILONERROR, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_ERRORBUFFER, errbuf)) ||
			CURLE_OK != (err = curl_easy_setopt(handle, opt = CURLOPT_ACCEPT_ENCODING, "")))
	{
		*error = zbx_dsprintf(NULL, "cannot set cURL option %d: [%s]", (int)opt, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != zbx_curl_setopt_https(handle, error))
		goto out;

	*errbuf = '\0';

	if (CURLE_OK != (err = curl_easy_perform(handle)))
	{
		elastic_log_error(handle, err, errbuf, &page);
		if (CURLE_HTTP_RETURNED_ERROR == err)
			*error = zbx_dsprintf(NULL, "cannot perform cURL request: %s", curl_easy_strerror(err));
		else
			*error = zbx_dsprintf(NULL, "cannot perform cURL request: %s", errbuf);

		goto clean;

	}

	if (SUCCEED != zbx_json_open(page.data, &jp) ||
		SUCCEED != zbx_json_brackets_open(jp.start, &jp_values) ||
		SUCCEED != zbx_json_brackets_by_name(&jp_values, "version", &jp_sub) ||
		SUCCEED != zbx_json_value_by_name_dyn(&jp_sub, "number", &version_friendly, &version_len, NULL))
	{
		*error = zbx_strdup(NULL, "cannot extract ElasticSearch version information");
		goto clean;
	}

	ret = SUCCEED;
clean:
	curl_slist_free_all(curl_headers);
	curl_easy_cleanup(handle);
out:
	if (FAIL == ret)
	{
		version = ZBX_DBVERSION_UNDEFINED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ElasticDB version retrieved unparsed: %s", version_friendly);

		if (3 != sscanf(version_friendly, "%d.%d.%d", &major_num, &minor_num, &increment_num) ||
				major_num >= 100 || major_num <= 0 || minor_num >= 100 || minor_num < 0 ||
				increment_num >= 100 || increment_num < 0)
		{
			*error = zbx_dsprintf(NULL, "Failed to detect ElasticDB version from the "
					"following query result: %s", version_friendly);
			version = ZBX_DBVERSION_UNDEFINED;
		}
		else
		{
			version = major_num * 10000 + minor_num * 100 + increment_num;
		}
	}

	info->database = zbx_strdup(NULL, "ElasticDB");
	info->current_version = version;
	info->min_version = ZBX_ELASTIC_MIN_VERSION;
	info->max_version = ZBX_ELASTIC_MAX_VERSION;
	info->min_supported_version = ZBX_DBVERSION_UNDEFINED;

	info->friendly_current_version = version_friendly;
	info->friendly_min_version = zbx_strdup(NULL, ZBX_ELASTIC_MIN_VERSION_STR);
	info->friendly_max_version = zbx_strdup(NULL, ZBX_ELASTIC_MAX_VERSION_STR);
	info->friendly_min_supported_version = zbx_strdup(NULL, ZBX_ELASTIC_MIN_VERSION_STR);

	zbx_free(page.data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s version:%lu", __func__, zbx_result_string(ret),
			(unsigned long)version);

	return ret;

#undef RIGHT2
#undef ZBX_ELASTIC_MAX_VERSION_STR
#undef ZBX_ELASTIC_MAX_VERSION
#undef ZBX_ELASTIC_MIN_VERSION_STR
#undef ZBX_ELASTIC_MIN_VERSION
}

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize Elasticsearch history data structure        *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] array of history storage options                    *
 *     options_num - [IN] number of elements in the options array             *
 *     error       - [OUT] error message if function fails                    *
 *                                                                            *
 * Return value: elasticsarch history provier or NULL on failure              *
 *                                                                            *
 ******************************************************************************/
static void	*history_elastic_create_data(const zbx_history_option_t *options, int options_num, char **error)
{
	zbx_history_elastic_data_t	*data;
	const char		*value;

	data = (zbx_history_elastic_data_t *)zbx_malloc(NULL, sizeof(zbx_history_elastic_data_t));
	memset(data, 0, sizeof(zbx_history_elastic_data_t));

	if (NULL == (value = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_URL)))
	{
		zbx_free(data);
		*error = zbx_strdup(*error, "missing \"url\" option for ElasticSearch history backend");

		return NULL;
	}

	data->base_url = zbx_strdup(NULL, value);
	zbx_rtrim(data->base_url, "/");

	if (NULL != (value = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_DATE_INDEX)))
		data->pipelines = atoi(value);

	if (NULL != (value = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES)))
		data->log_slow_queries = atoi(value);

	zbx_vector_elastic_conn_ptr_create(&data->conns);

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

	zbx_vector_elastic_conn_ptr_destroy(&d->conns);

	zbx_free(d->base_url);
	zbx_free(d);
}

/******************************************************************************
 *                                                                            *
 * Purpose: open and initialize the elsticsearch history provider             *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] array of history storage options                    *
 *     options_num - [IN] number of elements in the options array             *
 *     error       - [OUT] error message if function fails                    *
 *                                                                            *
 * Return value: history provider or NULL if initialization fails             *
 *                                                                            *
 ******************************************************************************/
zbx_history_provider_t *history_elastic_open(const zbx_history_option_t *options, int options_num, char **error)
{
	static int		initialized = 0;
	zbx_history_provider_t	*provider;
	void			*data;

	if (0 == initialized)
	{
		if (0 != curl_global_init(CURL_GLOBAL_ALL))
		{
			*error = zbx_strdup(*error, "cannot initialize cURL library");
			return NULL;
		}

		initialized = 1;
	}

	if (NULL == (data = history_elastic_create_data(options, options_num, error)))
		return NULL;

	provider = (zbx_history_provider_t *)zbx_malloc(NULL, sizeof(zbx_history_provider_t));

	provider->name = zbx_strdup(NULL, HISTORY_PROVIDER_ELASTIC);
	provider->traits = ZBX_HISTORY_TRAIT_TYPES_NOBIN;
	provider->impl.write = history_elastic_write;
	provider->impl.flush = history_elastic_flush;
	provider->impl.fetch = history_elastic_fetch;
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
