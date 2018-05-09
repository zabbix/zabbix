/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "zbxhistory.h"
#include "zbxself.h"
#include "history.h"

/* curl_multi_wait() is supported starting with version 7.28.0 (0x071c00) */
#if defined(HAVE_LIBCURL) && LIBCURL_VERSION_NUM >= 0x071c00

#define		ZBX_HISTORY_STORAGE_DOWN	10000 /* Timeout in milliseconds */

#define		ZBX_IDX_JSON_ALLOCATE		256
#define		ZBX_JSON_ALLOCATE		2048


const char	*value_type_str[] = {"dbl", "str", "log", "uint", "text"};

extern char	*CONFIG_HISTORY_STORAGE_URL;
extern int	CONFIG_HISTORY_STORAGE_PIPELINES;

typedef struct
{
	char	*base_url;
	char	*post_url;
	char	*buf;
	CURL	*handle;
}
zbx_elastic_data_t;

typedef struct
{
	unsigned char		initialized;
	zbx_vector_ptr_t	ifaces;

	CURLM			*handle;
}
zbx_elastic_writer_t;

static zbx_elastic_writer_t	writer;

typedef struct
{
	char	*data;
	size_t	alloc;
	size_t	offset;
}
zbx_httppage_t;

static zbx_httppage_t	page_r;

typedef struct
{
	zbx_httppage_t	page;
	char		errbuf[CURL_ERROR_SIZE];
}
zbx_curlpage_t;

static zbx_curlpage_t	page_w[ITEM_VALUE_TYPE_MAX];

static size_t	curl_write_cb(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	zbx_httppage_t	*page = (zbx_httppage_t	*)userdata;

	zbx_strncpy_alloc(&page->data, &page->alloc, &page->offset, ptr, r_size);

	return r_size;
}

static history_value_t	history_str2value(char *str, unsigned char value_type)
{
	history_value_t	value;

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
	}

	return value;
}

static const char	*history_value2str(const ZBX_DC_HISTORY *h)
{
	static char	buffer[MAX_ID_LEN + 1];

	switch (h->value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			return h->value.str;
		case ITEM_VALUE_TYPE_LOG:
			return h->value.log->value;
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL, h->value.dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_UI64, h->value.ui64);
			break;
	}

	return buffer;
}

static int	history_parse_value(struct zbx_json_parse *jp, unsigned char value_type, zbx_history_record_t *hr)
{
	char	*value = NULL;
	size_t	value_alloc = 0;
	int	ret = FAIL;

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, "clock", &value, &value_alloc))
		goto out;

	hr->timestamp.sec = atoi(value);

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, "ns", &value, &value_alloc))
		goto out;

	hr->timestamp.ns = atoi(value);

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, "value", &value, &value_alloc))
		goto out;

	hr->value = history_str2value(value, value_type);

	if (ITEM_VALUE_TYPE_LOG == value_type)
	{

		if (SUCCEED != zbx_json_value_by_name_dyn(jp, "timestamp", &value, &value_alloc))
			goto out;

		hr->value.log->timestamp = atoi(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(jp, "logeventid", &value, &value_alloc))
			goto out;

		hr->value.log->logeventid = atoi(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(jp, "severity", &value, &value_alloc))
			goto out;

		hr->value.log->severity = atoi(value);

		if (SUCCEED != zbx_json_value_by_name_dyn(jp, "source", &value, &value_alloc))
			goto out;

		hr->value.log->source = zbx_strdup(NULL, value);
	}

	ret = SUCCEED;

out:
	zbx_free(value);

	return ret;
}

static void	elastic_log_error(CURL *handle, CURLcode error, const char *errbuf)
{
	long	http_code;

	if (CURLE_HTTP_RETURNED_ERROR == error)
	{
		curl_easy_getinfo(handle, CURLINFO_RESPONSE_CODE, &http_code);

		if (0 != page_r.offset)
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot get values from elasticsearch, HTTP error: %ld,",
					" message: %s", http_code, page_r.data);
		}
		else
			zabbix_log(LOG_LEVEL_ERR, "cannot get values from elasticsearch, HTTP error: %ld", http_code);
	}
	else
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot get values from elasticsearch: %s",
				'\0' != *errbuf ? errbuf : curl_easy_strerror(error));
	}
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_close                                                          *
 *                                                                                  *
 * Purpose: closes connection and releases allocated resources                      *
 *                                                                                  *
 * Parameters:  hist - [IN] the history storage interface                           *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_close(zbx_history_iface_t *hist)
{
	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;

	zbx_free(data->buf);
	zbx_free(data->post_url);

	if (NULL != data->handle)
	{
		if (NULL != writer.handle)
			curl_multi_remove_handle(writer.handle, data->handle);

		curl_easy_cleanup(data->handle);
		data->handle = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: elastic_is_error_present                                         *
 *                                                                            *
 * Purpose: check a error from Elastic json response                          *
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
	const char		*__function_name = "elastic_is_error_present";

	struct zbx_json_parse	jp, jp_values, jp_index, jp_error, jp_items, jp_item;
	const char		*errors, *p = NULL;
	char			*index = NULL, *status = NULL, *type = NULL, *reason = NULL;
	size_t			index_alloc = 0, status_alloc = 0, type_alloc = 0, reason_alloc = 0;
	int			rc_js = SUCCEED;

	zabbix_log(LOG_LEVEL_TRACE, "%s() raw json: %s", __function_name, ZBX_NULL2EMPTY_STR(page->data));

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
				if (SUCCEED != zbx_json_value_by_name_dyn(&jp_error, "type", &type, &type_alloc))
					rc_js = FAIL;
				if (SUCCEED != zbx_json_value_by_name_dyn(&jp_error, "reason", &reason, &reason_alloc))
					rc_js = FAIL;
			}
			else
				continue;

			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_index, "status", &status, &status_alloc))
				rc_js = FAIL;
			if (SUCCEED != zbx_json_value_by_name_dyn(&jp_index, "_index", &index, &index_alloc))
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

/******************************************************************************************************************
 *                                                                                                                *
 * common sql service support                                                                                     *
 *                                                                                                                *
 ******************************************************************************************************************/



/************************************************************************************
 *                                                                                  *
 * Function: elastic_writer_init                                                    *
 *                                                                                  *
 * Purpose: initializes elastic writer for a new batch of history values            *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_writer_init(void)
{
	if (0 != writer.initialized)
		return;

	zbx_vector_ptr_create(&writer.ifaces);

	if (NULL == (writer.handle = curl_multi_init()))
	{
		zbx_error("Cannot initialize cURL multi session");
		exit(EXIT_FAILURE);
	}

	writer.initialized = 1;
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_writer_release                                                 *
 *                                                                                  *
 * Purpose: releases initialized elastic writer by freeing allocated resources and  *
 *          setting its state to uninitialized.                                     *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_writer_release(void)
{
	int	i;

	for (i = 0; i < writer.ifaces.values_num; i++)
		elastic_close((zbx_history_iface_t *)writer.ifaces.values[i]);

	curl_multi_cleanup(writer.handle);
	writer.handle = NULL;

	zbx_vector_ptr_destroy(&writer.ifaces);

	writer.initialized = 0;
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_writer_add_iface                                               *
 *                                                                                  *
 * Purpose: adds history storage interface to be flushed later                      *
 *                                                                                  *
 * Parameters: db_insert - [IN] bulk insert data                                    *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_writer_add_iface(zbx_history_iface_t *hist)
{
	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;

	elastic_writer_init();

	if (NULL == (data->handle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL session");
		return;
	}
	curl_easy_setopt(data->handle, CURLOPT_URL, data->post_url);
	curl_easy_setopt(data->handle, CURLOPT_POST, 1L);
	curl_easy_setopt(data->handle, CURLOPT_POSTFIELDS, data->buf);
	curl_easy_setopt(data->handle, CURLOPT_WRITEFUNCTION, curl_write_cb);
	curl_easy_setopt(data->handle, CURLOPT_WRITEDATA, &page_w[hist->value_type].page);
	curl_easy_setopt(data->handle, CURLOPT_FAILONERROR, 1L);
	curl_easy_setopt(data->handle, CURLOPT_ERRORBUFFER, page_w[hist->value_type].errbuf);
	*page_w[hist->value_type].errbuf = '\0';
	curl_easy_setopt(data->handle, CURLOPT_PRIVATE, &page_w[hist->value_type]);
	page_w[hist->value_type].page.offset = 0;
	if (0 < page_w[hist->value_type].page.alloc)
		*page_w[hist->value_type].page.data = '\0';

	curl_multi_add_handle(writer.handle, data->handle);

	zbx_vector_ptr_append(&writer.ifaces, hist);
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_writer_flush                                                   *
 *                                                                                  *
 * Purpose: posts historical data to elastic storage                                *
 *                                                                                  *
 ************************************************************************************/
static int	elastic_writer_flush(void)
{
	const char		*__function_name = "elastic_writer_flush";

	struct curl_slist	*curl_headers = NULL;
	int			i, running, previous, msgnum;
	CURLMsg			*msg;
	zbx_vector_ptr_t	retries;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* The writer might be uninitialized only if the history */
	/* was already flushed. In that case, return SUCCEED */
	if (0 == writer.initialized)
		return SUCCEED;

	zbx_vector_ptr_create(&retries);

	curl_headers = curl_slist_append(curl_headers, "Content-Type: application/x-ndjson");

	for (i = 0; i < writer.ifaces.values_num; i++)
	{
		zbx_history_iface_t	*hist = (zbx_history_iface_t *)writer.ifaces.values[i];
		zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;

		(void)curl_easy_setopt(data->handle, CURLOPT_HTTPHEADER, curl_headers);

		zabbix_log(LOG_LEVEL_DEBUG, "sending %s", data->buf);
	}

try_again:
	previous = 0;

	do
	{
		int		fds;
		CURLMcode	code;
		char 		*error;
		zbx_curlpage_t	*curl_page;

		if (CURLM_OK != (code = curl_multi_perform(writer.handle, &running)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot perform on curl multi handle: %s", curl_multi_strerror(code));
			break;
		}

		if (CURLM_OK != (code = curl_multi_wait(writer.handle, NULL, 0, ZBX_HISTORY_STORAGE_DOWN, &fds)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot wait on curl multi handle: %s", curl_multi_strerror(code));
			break;
		}

		if (previous == running)
			continue;

		while (NULL != (msg = curl_multi_info_read(writer.handle, &msgnum)))
		{
			/* If the error is due to malformed data, there is no sense on re-trying to send. */
			/* That's why we actually check for transport and curl errors separately */
			if (CURLE_HTTP_RETURNED_ERROR == msg->data.result)
			{
				if (CURLE_OK == curl_easy_getinfo(msg->easy_handle, CURLINFO_PRIVATE,
						(char **)&curl_page) && '\0' != *curl_page->errbuf)
				{
					zabbix_log(LOG_LEVEL_ERR, "%s: %s",
							"cannot send data to elasticsearch, HTTP error message",
							curl_page->errbuf);
				}
				else
				{
					long int	err;

					curl_easy_getinfo(msg->easy_handle, CURLINFO_RESPONSE_CODE, &err);
					zabbix_log(LOG_LEVEL_ERR, "%s: %d",
							"cannot send data to elasticsearch, HTTP error code", err);
				}
			}
			else if (CURLE_OK != msg->data.result)
			{
				if (CURLE_OK == curl_easy_getinfo(msg->easy_handle, CURLINFO_PRIVATE,
						(char **)&curl_page) && '\0' != *curl_page->errbuf)
				{
					zabbix_log(LOG_LEVEL_WARNING, "%s: %s", "cannot send data to elasticsearch",
							curl_page->errbuf);
				}
				else
				{
					zabbix_log(LOG_LEVEL_WARNING, "%s: %s", "cannot send data to elasticsearch",
							curl_easy_strerror(msg->data.result));
				}

				/* If the error is due to curl internal problems or unrelated */
				/* problems with HTTP, we put the handle in a retry list and */
				/* remove it from the current execution loop */
				zbx_vector_ptr_append(&retries, msg->easy_handle);
				curl_multi_remove_handle(writer.handle, msg->easy_handle);
			}
			else if (CURLE_OK == curl_easy_getinfo(msg->easy_handle, CURLINFO_PRIVATE, (char **)&curl_page)
					&& SUCCEED == elastic_is_error_present(&curl_page->page, &error))
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s() %s: %s", __function_name,
						"cannot send data to elasticsearch", error);
				zbx_free(error);

				/* If the error is due to elastic internal problems (for example an index */
				/* became read-only), we put the handle in a retry list and */
				/* remove it from the current execution loop */
				zbx_vector_ptr_append(&retries, msg->easy_handle);
				curl_multi_remove_handle(writer.handle, msg->easy_handle);
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
			curl_multi_add_handle(writer.handle, retries.values[i]);

		zbx_vector_ptr_clear(&retries);

		sleep(ZBX_HISTORY_STORAGE_DOWN / 1000);
		goto try_again;
	}

	curl_slist_free_all(curl_headers);

	zbx_vector_ptr_destroy(&retries);

	elastic_writer_release();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;
}

/******************************************************************************************************************
 *                                                                                                                *
 * history interface support                                                                                      *
 *                                                                                                                *
 ******************************************************************************************************************/

/************************************************************************************
 *                                                                                  *
 * Function: elastic_destroy                                                        *
 *                                                                                  *
 * Purpose: destroys history storage interface                                      *
 *                                                                                  *
 * Parameters:  hist - [IN] the history storage interface                           *
 *                                                                                  *
 ************************************************************************************/
static void	elastic_destroy(zbx_history_iface_t *hist)
{
	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;

	elastic_close(hist);

	zbx_free(data->base_url);
	zbx_free(data);
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_get_values                                                     *
 *                                                                                  *
 * Purpose: gets item history data from history storage                             *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *              itemid  - [IN] the itemid                                           *
 *              start   - [IN] the period start timestamp                           *
 *              count   - [IN] the number of values to read                         *
 *              end     - [IN] the period end timestamp                             *
 *              values  - [OUT] the item history data values                        *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values from ]<start>,<end>] interval or    *
 *           all values from the specified interval if count is zero.               *
 *                                                                                  *
 ************************************************************************************/
static int	elastic_get_values(zbx_history_iface_t *hist, zbx_uint64_t itemid, int start, int count, int end,
		zbx_vector_history_record_t *values)
{
	const char		*__function_name = "elastic_get_values";

	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;
	size_t			url_alloc = 0, url_offset = 0, id_alloc = 0, scroll_alloc = 0, scroll_offset = 0;
	int			total, empty, ret;
	CURLcode		err;
	struct zbx_json		query;
	struct curl_slist	*curl_headers = NULL;
	char			*scroll_id = NULL, *scroll_query = NULL, errbuf[CURL_ERROR_SIZE];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = FAIL;

	if (NULL == (data->handle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL session");

		return FAIL;
	}

	zbx_snprintf_alloc(&data->post_url, &url_alloc, &url_offset, "%s/%s*/values/_search?scroll=10s", data->base_url,
			value_type_str[hist->value_type]);

	/* prepare the json query for elasticsearch, apply ranges if needed */
	zbx_json_init(&query, ZBX_JSON_ALLOCATE);

	if (0 < count)
	{
		zbx_json_adduint64(&query, "size", count);
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

	curl_easy_setopt(data->handle, CURLOPT_URL, data->post_url);
	curl_easy_setopt(data->handle, CURLOPT_POSTFIELDS, query.buffer);
	curl_easy_setopt(data->handle, CURLOPT_WRITEFUNCTION, curl_write_cb);
	curl_easy_setopt(data->handle, CURLOPT_WRITEDATA, &page_r);
	curl_easy_setopt(data->handle, CURLOPT_HTTPHEADER, curl_headers);
	curl_easy_setopt(data->handle, CURLOPT_FAILONERROR, 1L);
	curl_easy_setopt(data->handle, CURLOPT_ERRORBUFFER, errbuf);

	zabbix_log(LOG_LEVEL_DEBUG, "sending query to %s; post data: %s", data->post_url, query.buffer);

	page_r.offset = 0;
	*errbuf = '\0';
	if (CURLE_OK != (err = curl_easy_perform(data->handle)))
	{
		elastic_log_error(data->handle, err, errbuf);
		goto out;
	}

	url_offset = 0;
	zbx_snprintf_alloc(&data->post_url, &url_alloc, &url_offset, "%s/_search/scroll", data->base_url);

	curl_easy_setopt(data->handle, CURLOPT_URL, data->post_url);

	total = (0 == count ? -1 : count);

	/* For processing the records, we need to keep track of the total requested and if the response from the */
	/* elasticsearch server is empty. For this we use two variables, empty and total. If the result is empty or */
	/* the total reach zero, we terminate the scrolling query and return what we currently have. */
	do
	{
		struct zbx_json_parse	jp, jp_values, jp_item, jp_sub, jp_hits, jp_source;
		zbx_history_record_t	hr;
		const char		*p = NULL;

		empty = 1;

		zabbix_log(LOG_LEVEL_DEBUG, "received from elasticsearch: %s", page_r.data);

		zbx_json_open(page_r.data, &jp);
		zbx_json_brackets_open(jp.start, &jp_values);

		/* get the scroll id immediately, for being used in subsequent queries */
		if (SUCCEED != zbx_json_value_by_name_dyn(&jp_values, "_scroll_id", &scroll_id, &id_alloc))
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

			if (SUCCEED != history_parse_value(&jp_source, hist->value_type, &hr))
				continue;

			zbx_vector_history_record_append_ptr(values, &hr);

			if (-1 != total)
				--total;

			if (0 == total)
			{
				empty = 1;
				break;
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

		curl_easy_setopt(data->handle, CURLOPT_POSTFIELDS, scroll_query);

		page_r.offset = 0;
		*errbuf = '\0';
		if (CURLE_OK != (err = curl_easy_perform(data->handle)))
		{
			elastic_log_error(data->handle, err, errbuf);
			break;
		}
	}
	while (0 == empty);

	/* as recommended by the elasticsearch documentation, we close the scroll search through a DELETE request */
	if (NULL != scroll_id)
	{
		url_offset = 0;
		zbx_snprintf_alloc(&data->post_url, &url_alloc, &url_offset, "%s/_search/scroll/%s", data->base_url,
				scroll_id);

		curl_easy_setopt(data->handle, CURLOPT_URL, data->post_url);
		curl_easy_setopt(data->handle, CURLOPT_POSTFIELDS, NULL);
		curl_easy_setopt(data->handle, CURLOPT_CUSTOMREQUEST, "DELETE");

		zabbix_log(LOG_LEVEL_DEBUG, "elasticsearch closing scroll %s", data->post_url);

		page_r.offset = 0;
		*errbuf = '\0';
		if (CURLE_OK != (err = curl_easy_perform(data->handle)))
			elastic_log_error(data->handle, err, errbuf);
	}

out:
	elastic_close(hist);

	curl_slist_free_all(curl_headers);

	zbx_json_free(&query);

	zbx_free(scroll_id);
	zbx_free(scroll_query);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_add_values                                                     *
 *                                                                                  *
 * Purpose: sends history data to the storage                                       *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *              history - [IN] the history data vector (may have mixed value types) *
 *                                                                                  *
 ************************************************************************************/
static int	elastic_add_values(zbx_history_iface_t *hist, const zbx_vector_ptr_t *history)
{
	const char	*__function_name = "elastic_add_values";

	zbx_elastic_data_t	*data = (zbx_elastic_data_t *)hist->data;
	int			i, num = 0;
	ZBX_DC_HISTORY		*h;
	struct zbx_json		json_idx, json;
	size_t			buf_alloc = 0, buf_offset = 0;
	char			pipeline[14]; /* index name length + suffix "-pipeline" */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_json_init(&json_idx, ZBX_IDX_JSON_ALLOCATE);

	zbx_json_addobject(&json_idx, "index");
	zbx_json_addstring(&json_idx, "_index", value_type_str[hist->value_type], ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json_idx, "_type", "values", ZBX_JSON_TYPE_STRING);

	if (1 == CONFIG_HISTORY_STORAGE_PIPELINES)
	{
		zbx_snprintf(pipeline, sizeof(pipeline), "%s-pipeline", value_type_str[hist->value_type]);
		zbx_json_addstring(&json_idx, "pipeline", pipeline, ZBX_JSON_TYPE_STRING);
	}

	zbx_json_close(&json_idx);
	zbx_json_close(&json_idx);

	for (i = 0; i < history->values_num; i++)
	{
		h = (ZBX_DC_HISTORY *)history->values[i];

		if (hist->value_type != h->value_type)
			continue;

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

		zbx_snprintf_alloc(&data->buf, &buf_alloc, &buf_offset, "%s\n%s\n", json_idx.buffer, json.buffer);

		zbx_json_free(&json);

		num++;
	}

	if (num > 0)
	{
		data->post_url = zbx_dsprintf(NULL, "%s/_bulk?refresh=true", data->base_url);
		elastic_writer_add_iface(hist);
	}

	zbx_json_free(&json_idx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return num;
}

/************************************************************************************
 *                                                                                  *
 * Function: elastic_flush                                                          *
 *                                                                                  *
 * Purpose: flushes the history data to storage                                     *
 *                                                                                  *
 * Parameters:  hist    - [IN] the history storage interface                        *
 *                                                                                  *
 * Comments: This function will try to flush the data until it succeeds or          *
 *           unrecoverable error occurs                                             *
 *                                                                                  *
 ************************************************************************************/
static int	elastic_flush(zbx_history_iface_t *hist)
{
	ZBX_UNUSED(hist);

	return elastic_writer_flush();
}

/************************************************************************************
 *                                                                                  *
 * Function: zbx_history_elastic_init                                               *
 *                                                                                  *
 * Purpose: initializes history storage interface                                   *
 *                                                                                  *
 * Parameters:  hist       - [IN] the history storage interface                     *
 *              value_type - [IN] the target value type                             *
 *              error      - [OUT] the error message                                *
 *                                                                                  *
 * Return value: SUCCEED - the history storage interface was initialized            *
 *               FAIL    - otherwise                                                *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_elastic_init(zbx_history_iface_t *hist, unsigned char value_type, char **error)
{
	zbx_elastic_data_t	*data;

	if (0 != curl_global_init(CURL_GLOBAL_ALL))
	{
		*error = zbx_strdup(*error, "Cannot initialize cURL library");
		return FAIL;
	}

	data = (zbx_elastic_data_t *)zbx_malloc(NULL, sizeof(zbx_elastic_data_t));
	memset(data, 0, sizeof(zbx_elastic_data_t));
	data->base_url = zbx_strdup(NULL, CONFIG_HISTORY_STORAGE_URL);
	zbx_rtrim(data->base_url, "/");
	data->buf = NULL;
	data->post_url = NULL;
	data->handle = NULL;

	hist->value_type = value_type;
	hist->data = data;
	hist->destroy = elastic_destroy;
	hist->add_values = elastic_add_values;
	hist->flush = elastic_flush;
	hist->get_values = elastic_get_values;
	hist->requires_trends = 0;

	return SUCCEED;
}

#else

int	zbx_history_elastic_init(zbx_history_iface_t *hist, unsigned char value_type, char **error)
{
	ZBX_UNUSED(hist);
	ZBX_UNUSED(value_type);

	*error = zbx_strdup(*error, "cURL library support >= 7.28.0 is required for Elasticsearch history backend");
	return FAIL;
}

#endif
