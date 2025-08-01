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

#include "history_clickhouse.h"
#include "zbxcommon.h"

#if defined(HAVE_LIBCURL)

#include "zbxhistory.h"
#include "history.h"
#include "history_curl.h"
#include "history_option.h"

#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxcurl.h"
#include "zbxexpr.h"
#include "zbxjson.h"
#include "zbxtypes.h"

typedef void (*write_value_t)(zbx_json_t *row, const zbx_history_value_t *value);

typedef struct
{
	unsigned char		value_type;
	int			status;
	char			*post_url;
	char			*buf;
	CURL			*handle;

	zbx_curl_response_t	resp;
}
zbx_clickhouse_conn_t;

ZBX_PTR_VECTOR_DECL(clickhouse_conn_ptr, zbx_clickhouse_conn_t *)
ZBX_PTR_VECTOR_IMPL(clickhouse_conn_ptr, zbx_clickhouse_conn_t *)

typedef struct
{
	char					*base_url;
	char					*db;

	struct curl_slist			*curl_headers;

	zbx_vector_clickhouse_conn_ptr_t	conns;
	zbx_vector_clickhouse_conn_ptr_t	active_conns;
}
zbx_clickhouse_data_t;

static char	*clickhouse_history_tables[] = {"history", "history_str", "history_log", "history_uint", "history_text",
					"unsupported"};

static void	clickhouse_conn_free(zbx_clickhouse_conn_t *conn)
{
	curl_easy_cleanup(conn->handle);
	zbx_free(conn->post_url);
	zbx_free(conn->buf);

	zbx_free(conn->resp.page.data);
}

static void	history_clickhouse_data_free(zbx_clickhouse_data_t *data)
{
	zbx_vector_clickhouse_conn_ptr_destroy(&data->active_conns);
	zbx_vector_clickhouse_conn_ptr_clear_ext(&data->conns, clickhouse_conn_free);
	zbx_vector_clickhouse_conn_ptr_destroy(&data->conns);

	curl_slist_free_all(data->curl_headers);

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get connection for specified value type                           *
 *                                                                            *
 * Parameters:                                                                *
 *     data       - [IN] internal ClickHouse data                             *
 *     value_type - [IN] item value type                                      *
 *                                                                            *
 * Return value: ClickHouse connection                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_clickhouse_conn_t	*history_clickhouse_get_conn(zbx_clickhouse_data_t *data, unsigned value_type)
{
	zbx_clickhouse_conn_t	*conn;

	if (0 < data->conns.values_num)
	{
		data->conns.values_num--;
		conn = data->conns.values[data->conns.values_num];
	}
	else
	{
		conn = (zbx_clickhouse_conn_t *)zbx_malloc(NULL, sizeof(zbx_clickhouse_conn_t));
		memset(conn, 0, sizeof(zbx_clickhouse_conn_t));
	}

	conn->value_type = value_type;

	return conn;
}

/******************************************************************************
 *                                                                            *
 * Purpose: release ClickHouse connection back to connection pool             *
 *                                                                            *
 * Parameters:                                                                *
 *     data - [IN] internal ClickHouse data                                   *
 *     conn - [IN] connection to release                                      *
 *                                                                            *
 ******************************************************************************/
static void	history_clickhouse_release_conn(zbx_clickhouse_data_t *data, zbx_clickhouse_conn_t *conn)
{
	zbx_vector_clickhouse_conn_ptr_append(&data->conns, conn);
}


/******************************************************************************
 *                                                                            *
 * Purpose: create full URL for ClickHouse connection                         *
 *                                                                            *
 * Parameters:                                                                *
 *     base_url - [IN] base URL for ClickHouse connection                     *
 *     username - [IN] username for authentication (optional)                 *
 *     password - [IN] password for authentication (optional)                 *
 *                                                                            *
 * Return value: dynamically allocated string containing full URL             *
 *                                                                            *
 ******************************************************************************/
static char	*history_clickhouse_make_url(const char *base_url, const char *username, const char *password)
{
	char		*url = NULL, *username_enc = NULL, *password_enc = NULL;
	size_t		url_alloc = 0, url_offset = 0;
	const char	*ptr;

	if (NULL != username)
		zbx_url_encode(username, &username_enc);
	if (NULL != password)
		zbx_url_encode(password, &password_enc);

	ptr = strstr(base_url, "//");

	if (NULL != ptr)
		zbx_strncpy_alloc(&url, &url_alloc, &url_offset, base_url, ptr - base_url + 2);

	if (NULL != username_enc)
		zbx_snprintf_alloc(&url, &url_alloc, &url_offset, "%s:%s@", username_enc, password_enc);

	if (NULL != ptr)
		zbx_strcpy_alloc(&url, &url_alloc, &url_offset, ptr + 2);

	zbx_free(username_enc);
	zbx_free(password_enc);

	return url;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize ClickHouse data structure                   *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] configuration options                               *
 *     options_num - [IN] number of configuration options                     *
 *     error       - [OUT] error message                                      *
 *                                                                            *
 * Return value: pointer to created data structure or NULL if failed          *
 *                                                                            *
 ******************************************************************************/
static void	*history_clickhouse_create_data(const zbx_history_option_t *options, int options_num, char **error)
{
	zbx_clickhouse_data_t	*data;
	const char		*url, *username, *password, *db;

	if (NULL == (url = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_URL)))
	{
		*error = zbx_dsprintf(NULL, "missing \"%s\" option", HISTORY_PROVIDER_OPTION_URL);
		return NULL;
	}

	if (NULL == (db = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_DB)))
	{
		*error = zbx_dsprintf(NULL, "missing \"%s\" option", HISTORY_PROVIDER_OPTION_DB);
		return NULL;
	}

	username = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_USERNAME);
	password = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_PASSWORD);

	if (NULL == username && NULL != password)
	{
		*error = zbx_dsprintf(NULL, "missing \"%s\" option", HISTORY_PROVIDER_OPTION_USERNAME);
		return NULL;
	}

	if (NULL != username && NULL == password)
	{
		*error = zbx_dsprintf(NULL, "missing \"%s\" option", HISTORY_PROVIDER_OPTION_PASSWORD);
		return NULL;
	}

	data = (zbx_clickhouse_data_t *)zbx_malloc(NULL, sizeof(zbx_clickhouse_data_t));

	memset(data, 0, sizeof(zbx_clickhouse_data_t));

	zbx_vector_clickhouse_conn_ptr_create(&data->conns);
	zbx_vector_clickhouse_conn_ptr_create(&data->active_conns);

	data->base_url = history_clickhouse_make_url(url, username, password);
	data->db = zbx_strdup(NULL, db);

	data->curl_headers = curl_slist_append(data->curl_headers, "Content-Type: text/plain");

	return (void *)data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: close ClickHouse connection and free resources                    *
 *                                                                            *
 ******************************************************************************/
static void	history_clickhouse_close(void *data)
{
	zbx_clickhouse_data_t	*d = (zbx_clickhouse_data_t *)data;

	history_clickhouse_data_free(d);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize ClickHouse connection                                  *
 *                                                                            *
 * Parameters:                                                                *
 *     conn    - [IN] ClickHouse connection                                   *
 *     headers - [IN] HTTP headers                                            *
 *     error   - [OUT] error message                                          *
 *                                                                            *
 * Return value: SUCCEED - connection initialized successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	history_clickhouse_conn_init(zbx_clickhouse_conn_t *conn, struct curl_slist *headers, char **error)
{
	CURLoption	opt;
	CURLcode	err;

	if (NULL == (conn->handle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "cannot initialize cURL session");
		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_POST, 1L)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_HTTPHEADER, headers)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEFUNCTION, history_curl_recv)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEDATA, &conn->resp.page)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_ERRORBUFFER, conn->resp.errbuf)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_ACCEPT_ENCODING, "")) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_PRIVATE, conn)))
	{
		*error = zbx_dsprintf(NULL, "cannot set cURL option %d: %s", (int)opt, curl_easy_strerror(err));
		return FAIL;
	}

	if (SUCCEED != zbx_curl_setopt_https(conn->handle, error))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * json writers for different value types                                     *
 *                                                                            *
 ******************************************************************************/

static void	history_clickhouse_write_dbl(zbx_json_t *row, const zbx_history_value_t *value)
{
	zbx_json_adddouble(row, NULL, value->dbl);
}

static void	history_clickhouse_write_str(zbx_json_t *row, const zbx_history_value_t *value)
{
	zbx_json_addstring(row, NULL, value->str, ZBX_JSON_TYPE_STRING);
}

static void	history_clickhouse_write_log(zbx_json_t *row, const zbx_history_value_t *value)
{
	zbx_json_addstring(row, NULL, value->log->value, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(row, NULL, value->log->source, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(row, NULL, value->log->severity);
	zbx_json_addint64(row, NULL, value->log->logeventid);
	zbx_json_addint64(row, NULL, value->log->timestamp);
}

static void	history_clickhouse_write_uint(zbx_json_t *row, const zbx_history_value_t *value)
{
	zbx_json_adduint64(row, NULL, value->ui64);
}

static void	history_clickhouse_write_text(zbx_json_t *row, const zbx_history_value_t *value)
{
	zbx_json_addstring(row, NULL, value->str, ZBX_JSON_TYPE_STRING);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write history data to ClickHouse                                  *
 *                                                                            *
 * Parameters:                                                                *
 *     data        - [IN] internal ClickHouse data                            *
 *     value_type  - [IN] value type (ITEM_VALUE_TYPE_*)                      *
 *     entries     - [IN] array of history entries to write                   *
 *     entries_num - [IN] number of entries to write                          *
 *                                                                            *
 * Comments: The data are buffered until flush() is called.                   *
 *                                                                            *
 ******************************************************************************/
static void	history_clickhouse_write(void *data, unsigned char value_type,
		const zbx_history_entry_t * const *entries, int entries_num)
{
	static write_value_t	write_funcs[] = {
					history_clickhouse_write_dbl,
					history_clickhouse_write_str,
					history_clickhouse_write_log,
					history_clickhouse_write_uint,
					history_clickhouse_write_text
				};

	zbx_clickhouse_data_t	*d = (zbx_clickhouse_data_t *)data;
	zbx_clickhouse_conn_t	*conn;
	char			*error = NULL, *url = NULL, *query, *query_enc = NULL, *buf = NULL;
	size_t			url_alloc = 0, url_offset = 0, buf_alloc = 0, buf_offset = 0;
	CURLcode		err;
	zbx_json_t		row;
	write_value_t		write_value = write_funcs[value_type];

	conn = history_clickhouse_get_conn(d, value_type);
	conn->status = FAIL;
	zbx_vector_clickhouse_conn_ptr_append(&d->active_conns, conn);

	if (NULL == conn->handle && SUCCEED != history_clickhouse_conn_init(conn, d->curl_headers, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot write data to ClickHouse: %s", error);
		zbx_free(error);

		return;
	}

	query = zbx_dsprintf(NULL, "INSERT INTO %s.%s FORMAT JSONCompactEachRow", d->db,
			clickhouse_history_tables[value_type]);
	zbx_url_encode(query, &query_enc);
	zbx_free(query);

	zbx_snprintf_alloc(&url, &url_alloc, &url_offset, "%s?query=%s", d->base_url, query_enc);
	zbx_free(query_enc);

	err = curl_easy_setopt(conn->handle, CURLOPT_URL, url);
	zbx_free(url);

	if (CURLE_OK != err)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot write data to ClickHouse: cannot set cURL option %d: %s",
				(int)CURLOPT_URL, curl_easy_strerror(err));
		return;
	}

	zbx_json_initarray(&row, 1024);

	for (int i = 0; i < entries_num; i++)
	{
		char	timestamp[MAX_ID_LEN * 2];

		zbx_json_adduint64(&row, NULL, entries[i]->itemid);
		write_value(&row, &entries[i]->value);

		zbx_snprintf(timestamp, sizeof(timestamp), "%d.%09d", entries[i]->ts.sec, entries[i]->ts.ns);
		zbx_json_addstring(&row, NULL, timestamp, ZBX_JSON_TYPE_STRING);

		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, row.buffer);
		zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, '\n');

		zbx_json_setempty(&row);
		zbx_json_initarray(&row, 1024);
	}
	zbx_json_free(&row);

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_POSTFIELDS, buf)))
	{
		zbx_free(buf);
		zabbix_log(LOG_LEVEL_WARNING, "cannot write data to ClickHouse: cannot set cURL option %d: %s",
				(int)CURLOPT_URL, curl_easy_strerror(err));
		return;
	}

	conn->buf = buf;

	if (0 != conn->resp.page.alloc)
	{
		conn->resp.page.offset = 0;
		*conn->resp.page.data = '\0';
	}
	*conn->resp.errbuf = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush active ClickHouse connections                               *
 *                                                                            *
 * Parameters:                                                                *
 *     mhandle - [IN] cURL multi handle                                       *
 *     retries - [OUT] vector of handles to retry                             *
 *                                                                            *
 ******************************************************************************/
static void	history_clickhouse_flush_conns(CURLM *mhandle, zbx_vector_ptr_t *retries)
{
	CURLMcode		code;
	int 			running;
	CURLMsg			*msg;
	int			msg_num;

	do
	{
		if (CURLM_OK != (code = curl_multi_perform(mhandle, &running)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot perform on curl multi handle: %s", curl_multi_strerror(code));
			break;
		}

		if (CURLM_OK != (code = zbx_curl_multi_wait(mhandle, ZBX_HISTORY_STORAGE_DOWN, NULL)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot wait on curl multi handle: %s", curl_multi_strerror(code));
			break;
		}

	}
	while (0 != running);

	while (NULL != (msg = curl_multi_info_read(mhandle, &msg_num)))
	{
		zbx_clickhouse_conn_t	*conn = NULL;

		if (CURLE_OK != curl_easy_getinfo(msg->easy_handle, CURLINFO_PRIVATE, (char **)&conn) || NULL == conn)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot obtain internal ClickHouse data conn");
			break;
		}

		if (CURLE_OK != msg->data.result)
		{
			if ('\0' != *conn->resp.errbuf)
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot send data to ClickHouse: %s", conn->resp.errbuf);
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot send data to ClickHouse: %s",
						curl_easy_strerror(msg->data.result));
			}

			/* If the error is due to curl internal problems or unrelated */
			/* problems with HTTP, we put the handle in a retry list and */
			/* remove it from the current execution loop */
			zbx_vector_ptr_append(retries, msg->easy_handle);
			curl_multi_remove_handle(mhandle, msg->easy_handle);
		}
		else
		{
			long 		status;
			CURLcode	err;

			if (CURLE_OK != (err = curl_easy_getinfo(msg->easy_handle, CURLINFO_RESPONSE_CODE, &status)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot obtain HTTP response code: %s",
						curl_easy_strerror(err));
				continue;
			}

			if (400 > status)
			{
				conn->status = SUCCEED;
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot send data to ClickHouse, HTTP response code: %ld",
						status);

				if (NULL != conn->resp.page.data)
				{
					zabbix_log(LOG_LEVEL_WARNING, "ClickHouse error message: %s",
							conn->resp.page.data);
				}
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush active ClickHouse connections                               *
 *                                                                            *
 * Parameters:                                                                *
 *     data - [IN] internal ClickHouse data                                   *
 *                                                                            *
 * Return value: flush error bitmap                                           *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	history_clickhouse_flush(void *data)
{
	zbx_clickhouse_data_t	*d = (zbx_clickhouse_data_t *)data;
	zbx_vector_ptr_t		retries;
	CURLM				*mhandle;
	zbx_uint64_t			flush_err = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() active connections:%d", __func__, d->active_conns.values_num);

	zbx_vector_ptr_create(&retries);

	if (0 == d->active_conns.values_num)
		goto out;

	if (NULL == (mhandle = curl_multi_init()))
	{
		zbx_error("Cannot initialize cURL multi session");
		flush_err = (1 << (ITEM_VALUE_TYPE_COUNT + 1)) - 1;

		goto out;
	}

	for (int i = 0; i < d->active_conns.values_num; i++)
	{
		zbx_clickhouse_conn_t	*conn = d->active_conns.values[i];

		zbx_vector_ptr_append(&retries, conn->handle);

		zabbix_log(LOG_LEVEL_TRACE, "posting history to ClickHouse for value_type %d: %s", conn->value_type,
				conn->buf);
	}

	while (1)
	{
		for (int i = 0; i < retries.values_num; i++)
			curl_multi_add_handle(mhandle, retries.values[i]);

		zbx_vector_ptr_clear(&retries);

		history_clickhouse_flush_conns(mhandle, &retries);

		if (0 == retries.values_num)
			break;

		sleep(ZBX_HISTORY_STORAGE_DOWN / 1000);
	}
out:
	for (int i = 0; i < d->active_conns.values_num; i++)
	{
		zbx_clickhouse_conn_t	*conn = d->active_conns.values[i];

		if (SUCCEED != conn->status)
			flush_err |= history_make_flush_error(ZBX_HISTORY_FLUSH_FAIL, conn->value_type);

		curl_multi_remove_handle(mhandle, conn->handle);
		curl_easy_setopt(conn->handle, CURLOPT_POSTFIELDS, NULL);

		zbx_free(conn->buf);
		zbx_free(conn->resp.page.data);
		conn->resp.page.alloc = 0;

		history_clickhouse_release_conn(d, conn);
	}

	zbx_vector_clickhouse_conn_ptr_clear(&d->active_conns);

	curl_multi_cleanup(mhandle);
	zbx_vector_ptr_destroy(&retries);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): ret:%lx", __func__, flush_err);

	return flush_err;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse log value from JSON                                         *
 *                                                                            *
 * Parameters:                                                                *
 *     jp  - [IN] row with log data as JSON array of values                   *
 *     p   - [IN] pointer to current position in JSON data                    *
 *     log - [OUT] log value structure to fill                                *
 *                                                                            *
 * Return value: SUCCEED - log value parsed successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	history_clickhouse_parse_log_value(const struct zbx_json_parse *jp, const char *p, zbx_log_value_t *log)
{
	char	buf[MAX_ID_LEN] = {0};
	size_t	source_alloc = 0;

	if (NULL == (p = zbx_json_next_value_dyn(jp, p, &log->source, &source_alloc, NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse log source from row \"%s\"", jp->start);
		return FAIL;
	}

	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse log severity from row \"%s\"", jp->start);
		return FAIL;
	}
	log->severity = atoi(buf);

	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse log eventid from row \"%s\"", jp->start);
		return FAIL;
	}
	log->logeventid = atoi(buf);

	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse log timestamp from row \"%s\"", jp->start);
		return FAIL;
	}
	log->timestamp = atoi(buf);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse single row of ClickHouse response and add to records vector *
 *                                                                            *
 * Parameters:                                                                *
 *     jp         - [IN] row with log data as JSON array of values            *
 *     value_type - [IN] value type (ITEM_VALUE_TYPE_*)                       *
 *     records    - [OUT] vector to store parsed history records              *
 *                                                                            *
 ******************************************************************************/
static void	history_clickhouse_parse_row(const struct zbx_json_parse *jp, unsigned char value_type,
		zbx_vector_history_record_t *records)
{
	char			timestamp[MAX_ID_LEN * 2], *ptr, *buf = NULL;
	const char		*p;
	zbx_history_record_t	record;
	size_t			buf_alloc = 0;

	if (NULL == (p = zbx_json_next_value(jp, NULL, timestamp, sizeof(timestamp), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse timestamp from row \"%s\"", jp->start);
		return;
	}

	if (NULL == (ptr = strchr(timestamp, '.')))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid timestamp format \"%s\"", timestamp);
		return;
	}

	*ptr++ = '\0';

	if (FAIL == zbx_is_uint32(timestamp, &record.timestamp.sec))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid timestamp seconds value \"%s\"", timestamp);
		return;
	}

	if (FAIL == zbx_is_uint32(ptr, &record.timestamp.ns))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid timestamp nanoseconds value \"%s\"", ptr);
		return;
	}

	if (NULL == (p = zbx_json_next_value_dyn(jp, p, &buf, &buf_alloc, NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse value from row \"%s\"", jp->start);
		return;
	}

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (FAIL == zbx_is_double(buf, &record.value.dbl))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse floating value \"%s\"", buf);
				zbx_free(buf);
				return;
			}
			zbx_free(buf);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (FAIL == zbx_is_uint64(buf, &record.value.ui64))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse unsigned 64-bit value \"%s\"", buf);
				zbx_free(buf);
				return;
			}
			zbx_free(buf);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			record.value.str = buf;
			break;
		case ITEM_VALUE_TYPE_LOG:
			record.value.log = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));
			record.value.log->value = buf;
			record.value.log->source = NULL;

			if (FAIL == history_clickhouse_parse_log_value(jp, p, record.value.log))
			{
				zbx_history_record_clear(&record, ITEM_VALUE_TYPE_LOG);
				return;
			}
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return;
	}

	zbx_vector_history_record_append(records, record);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse ClickHouse response and extract history records             *
 *                                                                            *
 * Parameters:                                                                *
 *     response   - [IN] ClickHouse response as string                        *
 *     value_type - [IN] value type (ITEM_VALUE_TYPE_*)                       *
 *     values     - [OUT] array of parsed history records                     *
 *                                                                            *
 * Return value: number of parsed records                                     *
 *                                                                            *
 ******************************************************************************/
static int	history_clickhouse_parse_response(char *response, unsigned char value_type,
		zbx_history_record_t **values)
{
	zbx_vector_history_record_t	records;
	char				*start, *end;

	zbx_vector_history_record_create(&records);

	for (start = response;; start = end + 1)
	{
		struct zbx_json_parse	jp;

		if (NULL != (end = strchr(start, '\n')))
			*end = '\0';

		if ('\0' != *start)
		{
			if (FAIL != zbx_json_open(start, &jp))
				history_clickhouse_parse_row(&jp, value_type, &records);
			else
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse row: %s", start);
		}

		if (NULL == end)
			break;

		*end = '\n';
	}

	*values = records.values;

	return records.values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: send POST request to ClickHouse                                   *
 *                                                                            *
 * Parameters: conn    - [IN] ClickHouse connection                           *
 *             headers - [IN] HTTP headers for the request                    *
 *             url     - [IN] URL for the request                             *
 *             data    - [IN] POST data to send                               *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - request sent successfully                          *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
static int	clickhouse_conn_post(zbx_clickhouse_conn_t *conn, struct curl_slist *headers, const char *url,
		const char *data, char **error)
{
	CURLcode	err;
	int		ret = FAIL;
	long		status;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() data:%s", data, __func__);

	if (NULL == conn->handle && SUCCEED != history_clickhouse_conn_init(conn, headers, error))
		return FAIL;

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_URL, url)))
	{
		*error = zbx_dsprintf(NULL, "cannot set URL option: %s", curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_POSTFIELDS, data)))
	{
		*error = zbx_dsprintf(NULL, "cannot set POSTFIELDS option: %s", curl_easy_strerror(err));
		goto out;
	}

	if (0 != conn->resp.page.alloc)
	{
		conn->resp.page.offset = 0;
		*conn->resp.page.data = '\0';
	}
	*conn->resp.errbuf = '\0';

	if (CURLE_OK != (err = curl_easy_perform(conn->handle)))
	{
		if ('\0' != *conn->resp.errbuf)
			*error = zbx_strdup(NULL, conn->resp.errbuf);
		else
			*error = zbx_strdup(NULL, curl_easy_strerror(err));

		goto out;
	}

	if (CURLE_OK != (err = curl_easy_getinfo(conn->handle, CURLINFO_RESPONSE_CODE, &status)))
	{
		*error = zbx_dsprintf(NULL, "cannot obtain HTTP response code: %s", curl_easy_strerror(err));
		goto out;
	}

	if (400 <= status)
	{
		*error = zbx_strdup(NULL, conn->resp.page.data);
		goto out;
	}

	zabbix_log(LOG_LEVEL_TRACE, "result: %s", conn->resp.page.data);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s() ret:%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch history data from ClickHouse                                *
 *                                                                            *
 * Parameters:                                                                *
 *     data        - [IN] internal ClickHouse data                            *
 *     itemid      - [IN] item identifier                                     *
 *     value_type  - [IN] value type (ITEM_VALUE_TYPE_*)                      *
 *     start       - [IN] period start time (0 - ignored)                     *
 *     end         - [IN] period end time  (0 - ignored)                      *
 *     count       - [IN] number of values to fetch (0 - ignored)             *
 *     values      - [OUT] array of fetched history records                   *
 *     error       - [OUT] error message                                      *
 *                                                                            *
 * Return value: number of fetched records or FAIL                            *
 *                                                                            *
 ******************************************************************************/
static int	history_clickhouse_fetch(void *data, zbx_uint64_t itemid, unsigned char value_type, time_t start,
		time_t end, int count, zbx_history_record_t **values, char **error)
{
	zbx_clickhouse_data_t	*d = (zbx_clickhouse_data_t *)data;
	zbx_clickhouse_conn_t	*conn;
	int			ret = FAIL;
	char			*query = NULL, *url = NULL, *errmsg = NULL;
	size_t			query_alloc = 0, query_offset = 0, url_alloc = 0, url_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() start:" ZBX_FS_TIME_T " end:" ZBX_FS_TIME_T " count:%d", __func__,
			start, end, count);

	conn = history_clickhouse_get_conn(d, value_type);

	zbx_snprintf_alloc(&url, &url_alloc, &url_offset, "%s?date_time_output_format=unix_timestamp", d->base_url);

	zbx_strcpy_alloc(&query, &query_alloc, &query_offset, "select timestamp,value");
	if (ITEM_VALUE_TYPE_LOG == value_type)
		zbx_strcpy_alloc(&query, &query_alloc, &query_offset, ",source,severity,logeventid,log_time");

	zbx_snprintf_alloc(&query, &query_alloc, &query_offset, " from %s.%s where itemid=" ZBX_FS_UI64,
			d->db, clickhouse_history_tables[value_type], itemid);

	if (0 != start)
	{
		zbx_snprintf_alloc(&query, &query_alloc, &query_offset, " and timestamp>='" ZBX_FS_TIME_T ".0'",
				start + 1);
	}

	if (0 != end)
	{
		zbx_snprintf_alloc(&query, &query_alloc, &query_offset, " and timestamp<'" ZBX_FS_TIME_T ".0'",
				end + 1);
	}

	zbx_strcpy_alloc(&query, &query_alloc, &query_offset, " order by itemid,timestamp desc");

	if (0 != count)
		zbx_snprintf_alloc(&query, &query_alloc, &query_offset, " LIMIT %d", count);

	zbx_strcpy_alloc(&query, &query_alloc, &query_offset, " format JSONCompactEachRow");

	zabbix_log(LOG_LEVEL_DEBUG, "query: %s", query);

	if (SUCCEED != clickhouse_conn_post(conn, d->curl_headers, url, query, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot fetch history from ClickHouse: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	ret = history_clickhouse_parse_response(conn->resp.page.data, value_type, values);
out:
	zbx_free(query);
	zbx_free(url);
	history_clickhouse_release_conn(d, conn);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() values_num:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve information about ClickHouse history module              *
 *                                                                            *
 * Parameters: data  - [IN] internal ClickHouse data                          *
 *             info  - [OUT] history module information structure             *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - information retrieved successfully                 *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
static int	history_clickhouse_get_info(void *data, zbx_history_provider_info_t *info, char **error)
{
#define HISTORY_CLICKHOUSE_MIN_VERSION			24000000
#define HISTORY_CLICKHOUSE_MIN_VERSION_STR		"24.x.x.x"
#define HISTORY_CLICKHOUSE_MAX_VERSION			24999999
#define HISTORY_CLICKHOUSE_MAX_VERSION_STR		"25.x.x.x"

	zbx_clickhouse_data_t	*d = (zbx_clickhouse_data_t *)data;
	int			ret = FAIL, v1, v2, v3, v4;
	zbx_clickhouse_conn_t	*conn;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* value type is used only for flush operations */
	conn = history_clickhouse_get_conn(d, ITEM_VALUE_TYPE_NONE);
	if (FAIL == clickhouse_conn_post(conn, d->curl_headers, d->base_url, "select version()", error))
		goto out;

	memset(info, 0, sizeof(zbx_history_provider_info_t));

	zbx_rtrim(conn->resp.page.data, "\n\r");
	if (4 != sscanf(conn->resp.page.data, "%d.%d.%d.%d", &v1, &v2, &v3, &v4))
	{
		*error = zbx_dsprintf(NULL, "unknown ClickHouse version: %s", conn->resp.page.data);
		goto out;
	}

	info->database = zbx_strdup(NULL, "ClickHouse");
	info->current_version = v1 * 1000000 + v2 * 1000 + v3;
	info->min_version = HISTORY_CLICKHOUSE_MIN_VERSION;
	info->max_version = HISTORY_CLICKHOUSE_MAX_VERSION;
	info->min_supported_version = ZBX_DBVERSION_UNDEFINED;

	info->friendly_current_version = zbx_strdup(NULL, conn->resp.page.data);
	info->friendly_min_version = zbx_strdup(NULL, HISTORY_CLICKHOUSE_MIN_VERSION_STR);
	info->friendly_max_version = zbx_strdup(NULL, HISTORY_CLICKHOUSE_MAX_VERSION_STR);
	info->friendly_min_supported_version = zbx_strdup(NULL, HISTORY_CLICKHOUSE_MIN_VERSION_STR);

	ret = SUCCEED;
out:
	history_clickhouse_release_conn(d, conn);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: open ClickHouse history provider                                  *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] array of history storage options                    *
 *     options_num - [IN] number of elements in options array                 *
 *     error       - [OUT] error message                                      *
 *                                                                            *
 * Return value: pointer to history provider or NULL if failed                *
 *                                                                            *
 ******************************************************************************/
zbx_history_provider_t	*history_clickhouse_open(const zbx_history_option_t *options, int options_num, char **error)
{
	zbx_history_provider_t	*provider;
	void			*data;

	if (NULL == (data = history_clickhouse_create_data(options, options_num, error)))
		return NULL;

	provider = (zbx_history_provider_t *)zbx_malloc(NULL, sizeof(zbx_history_provider_t));

	provider->name = zbx_strdup(NULL, HISTORY_PROVIDER_CLICKHOUSE);
	/* TODO: remove ZBX_HISTORY_TRAIT_REQUIRES_TRENDS if frontend can fetch aggregated data */
	provider->traits = ZBX_HISTORY_TRAIT_REQUIRES_TRENDS | ZBX_HISTORY_TRAIT_TYPES_NOBIN;
	provider->impl.write = history_clickhouse_write;
	provider->impl.flush = history_clickhouse_flush;
	provider->impl.fetch = history_clickhouse_fetch;
	provider->impl.close = history_clickhouse_close;
	provider->impl.get_info = history_clickhouse_get_info;
	provider->data = data;

	return provider;
}
#else
zbx_history_provider_t	*history_clickhouse_open(const zbx_history_option_t *options, int options_num, char **error)
{
	ZBX_UNUSED(options);
	ZBX_UNUSED(options_num);

	*error = zbx_strdup(*error, "Zabbix must be compiled with cURL library for ClickHouse history provider");

	return NULL;
}
#endif
