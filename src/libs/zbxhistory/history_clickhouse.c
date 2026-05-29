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

#include "history_clickhouse.h"
#include "history.h"
#include "zbxcommon.h"

#if defined(HAVE_LIBCURL)

#include "zbxhistory.h"
#include "history_curl.h"
#include "history_option.h"

#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxcurl.h"
#include "zbxexpr.h"
#include "zbxjson.h"
#include "zbxtypes.h"
#include "zbxhttp.h"
#include "zbxdb.h"
#include "zbxcrypto.h"
#include "zbxcacheconfig.h"
#include "zbxregexp.h"
#include "zbxtime.h"
#include "zbx_dbversion_constants.h"

typedef struct
{
	unsigned char		value_type;
	int			status;
	char			*post_data;
	CURL			*handle;

	zbx_curl_response_t	resp;
}
zbx_clickhouse_conn_t;

ZBX_PTR_VECTOR_DECL(clickhouse_conn_ptr, zbx_clickhouse_conn_t *)
ZBX_PTR_VECTOR_IMPL(clickhouse_conn_ptr, zbx_clickhouse_conn_t *)

typedef enum
{
	CLICKHOUSE_RETRIES_OFF,
	CLICKHOUSE_RETRIES_ON
}
zbx_clickhouse_retries_t;

typedef struct
{
	char					*db;
	char					*fetch_url;

	struct curl_slist			*curl_headers;

	zbx_vector_clickhouse_conn_ptr_t	conns;
	zbx_vector_clickhouse_conn_ptr_t	active_conns;

	CURLM					*mhandle;

	int					log_slow_queries;
	int					ssl_verify_peer;
	int					ssl_verify_host;

	zbx_uint64_t				value_type_flags;

	const char				*base_url;
	const char				*username;
	const char				*password;
	const char				*ssl_cert_file;
	const char				*ssl_key_file;
	const char				*ssl_key_password;

	const char				*source_ip;
	const char				*ssl_ca_location;
	const char				*ssl_cert_location;
	const char				*ssl_key_location;
}
zbx_clickhouse_data_t;

/* history_bin is not used */
static char	*clickhouse_history_tables[] = {"history", "history_str", "history_log", "history_uint", "history_text",
					"history_bin", "history_json", "unsupported"};

static void	clickhouse_conn_free(zbx_clickhouse_conn_t *conn)
{
	curl_easy_cleanup(conn->handle);
	zbx_free(conn->post_data);

	zbx_free(conn->resp.page.data);
	zbx_free(conn);
}

static void	history_clickhouse_data_free(zbx_clickhouse_data_t *data)
{
	zbx_vector_clickhouse_conn_ptr_destroy(&data->active_conns);
	zbx_vector_clickhouse_conn_ptr_clear_ext(&data->conns, clickhouse_conn_free);
	zbx_vector_clickhouse_conn_ptr_destroy(&data->conns);

	curl_slist_free_all(data->curl_headers);
	curl_multi_cleanup(data->mhandle);

	zbx_free(data->db);
	zbx_free(data->fetch_url);

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

	if (NULL == data->mhandle)
	{
		if (NULL == (data->mhandle = curl_multi_init()))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot initialize curl multi session");
			exit(EXIT_FAILURE);
		}
	}

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
	conn->status = FAIL;

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
 * Purpose: validate configuration options for ClickHouse history provider    *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] configuration options                               *
 *     options_num - [IN] number of configuration options                     *
 *                                                                            *
 ******************************************************************************/
static void	history_clickhouse_validate_options(const zbx_history_option_t *options, int options_num)
{
	const char	*supported_options = ""
				HISTORY_PROVIDER_OPTION_NAME ","
				HISTORY_PROVIDER_OPTION_URL ","
				HISTORY_PROVIDER_OPTION_USERNAME ","
				HISTORY_PROVIDER_OPTION_PASSWORD ","
				HISTORY_PROVIDER_OPTION_DB ","
				HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES ","
				HISTORY_PROVIDER_OPTION_VALUE_TYPES ","
				HISTORY_PROVIDER_OPTION_SOURCE_IP ","
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
			zabbix_log(LOG_LEVEL_WARNING, "Unsupported ClickHouse history provider option: %s=%s",
					options[i].name, options[i].value);
		}
	}
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
	const char		*url, *username, *password, *db, *value;

	if (sizeof(zbx_uint64_t) != sizeof(double))
	{
		*error = zbx_dsprintf(NULL, "unsupported size of double:" ZBX_FS_SIZE_T, sizeof(double));
		return NULL;
	}

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

	data->username = username;
	data->password = password;

	data->ssl_cert_file = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_SSL_CERT_FILE);
	data->ssl_key_file = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_SSL_KEY_FILE);
	data->ssl_key_password = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_SSL_KEY_PASSWORD);
	data->ssl_ca_location = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_SSL_CA_LOCATION);
	data->ssl_cert_location = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_SSL_CERT_LOCATION);
	data->ssl_key_location = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_SSL_KEY_LOCATION);

	if (NULL != (value = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_SSL_VERIFY_PEER)))
		data->ssl_verify_peer = atoi(value);
	else
		data->ssl_verify_peer = 1;

	if (NULL != (value = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_SSL_VERIFY_HOST)))
		data->ssl_verify_host = atoi(value);
	else
		data->ssl_verify_host = 1;

	if (NULL != (value = history_option_value(options, options_num, HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES)))
		data->log_slow_queries = atoi(value);

	zbx_vector_clickhouse_conn_ptr_create(&data->conns);
	zbx_vector_clickhouse_conn_ptr_create(&data->active_conns);

	data->base_url = url;
	data->fetch_url = zbx_dsprintf(NULL, "%s?database=%s&date_time_output_format=unix_timestamp", url, db);

	data->value_type_flags = history_options_type_mask(options, options_num);

	zbx_url_encode(db, &data->db);

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
 *     d       - [IN] internal ClickHouse data                                *
 *     error   - [OUT] error message                                          *
 *                                                                            *
 * Return value: SUCCEED - connection initialized successfully                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	history_clickhouse_conn_init(zbx_clickhouse_conn_t *conn, zbx_clickhouse_data_t *d, char **error)
{
	CURLoption	opt;
	CURLcode	err;

	if (NULL == (conn->handle = curl_easy_init()))
	{
		*error = zbx_strdup(NULL, "cannot initialize curl session");
		return FAIL;
	}

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_POST, 1L)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_HTTPHEADER, d->curl_headers)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEFUNCTION, history_curl_recv)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_WRITEDATA, &conn->resp.page)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_ERRORBUFFER, conn->resp.errbuf)) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_ACCEPT_ENCODING, "")) ||
		CURLE_OK != (err = curl_easy_setopt(conn->handle, opt = CURLOPT_PRIVATE, conn)))
	{
		*error = zbx_dsprintf(NULL, "cannot set curl option %d: %s", (int)opt, curl_easy_strerror(err));
		return FAIL;
	}

	if (SUCCEED != zbx_curl_setopt_https(conn->handle, error))
		return FAIL;

	/* either username and password both has been set or both are NULL */
	if (NULL != d->username)
	{
		if (SUCCEED != zbx_http_prepare_auth(conn->handle, CURLAUTH_BASIC, d->username, d->password, NULL,
				error))
		{
			return FAIL;
		}
	}

	if (SUCCEED != zbx_http_prepare_ssl(conn->handle, d->ssl_cert_file, d->ssl_key_file, d->ssl_key_password,
			d->ssl_verify_peer, d->ssl_verify_host, d->source_ip, d->ssl_ca_location, d->ssl_cert_location,
			d->ssl_key_location, error))
	{
		return FAIL;
	}

	return SUCCEED;
}

static int	encode_leb128(zbx_uint64_t value, unsigned char *out)
{
	int	len = 0;

	do
	{
		unsigned char	byte = value & 0x7f;

		value >>= 7;

		if (0 != value)
			byte |= 0x80;

		out[len++] = byte;
	}
	while (0 != value);

	return len;
}

static void	history_clickhouse_write_text(const char *str, char **post_data, size_t *post_data_alloc,
		size_t *post_data_offset)
{
	zbx_uint64_t	leb_len, len;
	unsigned char	leb[10];

	if (NULL != str)
		len = strlen(str);
	else
		len = 0;

	leb_len = encode_leb128(len, leb);
	zbx_str_memcpy_alloc(post_data, post_data_alloc, post_data_offset, (const char *)leb, leb_len);

	if (0 != len)
		zbx_str_memcpy_alloc(post_data, post_data_alloc, post_data_offset, str, len);
}

static void	history_clickhouse_write_uint64(zbx_uint64_t ui64, char **post_data, size_t *post_data_alloc,
		size_t *post_data_offset)
{
	zbx_uint64_t	number = zbx_htole_uint64(ui64);

	zbx_str_memcpy_alloc(post_data, post_data_alloc, post_data_offset, (const char *)&number, sizeof(number));
}

static void	history_clickhouse_write_dbl(double dbl, char **post_data, size_t *post_data_alloc,
		size_t *post_data_offset)
{
	zbx_uint64_t	number;

	memcpy(&number, &dbl, sizeof(number));

	history_clickhouse_write_uint64(number, post_data, post_data_alloc, post_data_offset);
}

static void	history_clickhouse_write_uint32(zbx_uint32_t ui32, char **post_data, size_t *post_data_alloc,
		size_t *post_data_offset)
{
	zbx_uint32_t	number = zbx_htole_uint32(ui32);

	zbx_str_memcpy_alloc(post_data, post_data_alloc, post_data_offset, (const char *)&number, sizeof(number));
}

static int	is_json_object(const char *json_str)
{
	if (NULL == json_str)
		return FAIL;

	while ('\0' != *json_str && 0 != isspace((unsigned char)*json_str))
		json_str++;

	if ('{' == *json_str)
		return SUCCEED;

	return FAIL;
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
#define	ZBX_CLICKHOUSE_ASYNC_INSERT "&async_insert=1&wait_for_async_insert=0"
	zbx_clickhouse_data_t	*d = (zbx_clickhouse_data_t *)data;
	zbx_clickhouse_conn_t	*conn;
	char			*error = NULL, url[MAX_STRING_LEN], *post_data = NULL;
	size_t			post_data_alloc = 0, post_data_offset = 0;
	CURLcode		err;
	int			ret = FAIL;

	/* bin is not supported */
	if (ITEM_VALUE_TYPE_BIN == value_type)
	{
		zabbix_log(LOG_LEVEL_WARNING, "bin item value type is not supported in ClickHouse ");
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	conn = history_clickhouse_get_conn(d, value_type);

	if (NULL == conn->handle && SUCCEED != history_clickhouse_conn_init(conn, d, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot write data to ClickHouse: %s", error);
		zbx_free(error);

		goto out;
	}

	if (ITEM_VALUE_TYPE_JSON == value_type)
	{
		zbx_snprintf(url, sizeof(url), "%s?database=%s"
			"&query=INSERT%%20INTO%%20%s%%20FORMAT%%20RowBinary&input_format_binary_read_json_as_string=1"
			ZBX_CLICKHOUSE_ASYNC_INSERT, d->base_url, d->db, clickhouse_history_tables[value_type]);
	}
	else
	{
		zbx_snprintf(url, sizeof(url), "%s?database=%s"
			"&query=INSERT%%20INTO%%20%s%%20FORMAT%%20RowBinary" ZBX_CLICKHOUSE_ASYNC_INSERT, d->base_url,
			d->db, clickhouse_history_tables[value_type]);
	}

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_URL, url)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot write data to ClickHouse: cannot set curl option %d: %s",
				(int)CURLOPT_URL, curl_easy_strerror(err));
		goto out;
	}

	post_data_alloc = entries_num * (sizeof(zbx_uint64_t) * 3) + 8;
	post_data = zbx_malloc(NULL, post_data_alloc);

	for (int i = 0; i < entries_num; i++)
	{
		const zbx_history_entry_t	*entry = entries[i];

		history_clickhouse_write_uint64(entry->itemid, &post_data, &post_data_alloc, &post_data_offset);
		history_clickhouse_write_uint64((zbx_uint64_t)entry->ts.sec * 1000000000ULL + entry->ts.ns,
				&post_data, &post_data_alloc, &post_data_offset);
		switch (value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				history_clickhouse_write_dbl(entry->value.dbl, &post_data, &post_data_alloc,
						&post_data_offset);
				break;
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				history_clickhouse_write_text(entry->value.str, &post_data, &post_data_alloc,
						&post_data_offset);
				break;
			case ITEM_VALUE_TYPE_JSON:
				if (SUCCEED == is_json_object(entry->value.str))
				{
					history_clickhouse_write_text(entry->value.str, &post_data, &post_data_alloc,
							&post_data_offset);
					history_clickhouse_write_text("", &post_data, &post_data_alloc,
							&post_data_offset);
				}
				else
				{
					history_clickhouse_write_text("null", &post_data, &post_data_alloc,
							&post_data_offset);
					history_clickhouse_write_text(entry->value.str, &post_data, &post_data_alloc,
							&post_data_offset);
				}
				break;
			case ITEM_VALUE_TYPE_LOG:
				history_clickhouse_write_text(entry->value.log->value, &post_data, &post_data_alloc,
						&post_data_offset);
				history_clickhouse_write_text(entry->value.log->source, &post_data, &post_data_alloc,
						&post_data_offset);
				history_clickhouse_write_uint32((zbx_uint32_t)entry->value.log->severity, &post_data,
						&post_data_alloc, &post_data_offset);
				history_clickhouse_write_uint32((zbx_uint32_t)entry->value.log->logeventid, &post_data,
						&post_data_alloc, &post_data_offset);
				history_clickhouse_write_uint64(entry->value.log->timestamp, &post_data,
						&post_data_alloc, &post_data_offset);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				history_clickhouse_write_uint64(entry->value.ui64, &post_data, &post_data_alloc,
						&post_data_offset);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN_MSG("unexpected value type %u", (unsigned char)value_type);
				break;
		}
	}

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_POSTFIELDSIZE, post_data_offset)))
	{
		zbx_free(post_data);
		zabbix_log(LOG_LEVEL_WARNING, "cannot write data to ClickHouse: cannot set curl option %d: %s",
				(int)CURLOPT_URL, curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_POSTFIELDS, post_data)))
	{
		zbx_free(post_data);
		zabbix_log(LOG_LEVEL_WARNING, "cannot write data to ClickHouse: cannot set curl option %d: %s",
				(int)CURLOPT_URL, curl_easy_strerror(err));
		goto out;
	}

	if (NULL != conn->post_data)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("ClickHouse connection buffer has not been freed before write");
		zbx_free(conn->post_data);
	}

	conn->post_data = post_data;

	if (0 != conn->resp.page.alloc)
	{
		conn->resp.page.offset = 0;
		*conn->resp.page.data = '\0';
	}
	*conn->resp.errbuf = '\0';

	ret = SUCCEED;
out:
	if (SUCCEED == ret)
		zbx_vector_clickhouse_conn_ptr_append(&d->active_conns, conn);
	else
		history_clickhouse_release_conn(d, conn);
#undef ZBX_CLICKHOUSE_ASYNC_INSERT
}

/******************************************************************************
 *                                                                            *
 * Purpose: append formatted error message to existing error string,          *
 *          separating multiple errors with commas                            *
 *                                                                            *
 * Parameters: err    - [IN/OUT] pointer to error string (can be NULL)        *
 *             format - [IN] format string for error message                  *
 *             ...    - [IN] variable arguments for format string             *
 *                                                                            *
 ******************************************************************************/
static void	history_clickhouse_add_error(char **err, const char *format, ...)
{
	va_list	args;
	char	*str = NULL;
	size_t	str_len = 0, str_offset = 0;

	if (NULL != *err)
		zbx_strcpy_alloc(&str, &str_len, &str_offset, ", ");

	va_start(args, format);
	zbx_vsnprintf_alloc(&str, &str_len, &str_offset, format, args);
	va_end(args);

	*err = zbx_strdcat(*err, str);
	zbx_free(str);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush active ClickHouse connections                               *
 *                                                                            *
 * Parameters:                                                                *
 *     d       - [IN] ClickHouse data                                         *
 *     mhandle - [IN] curl multi handle                                       *
 *     error   - [OUT] error message                                          *
 *                                                                            *
 * Return value: Number of connections to be retried. Those connections will  *
 *               be re-added to the multi handle.                             *
 *                                                                            *
 ******************************************************************************/
static int	history_clickhouse_flush_conns(zbx_clickhouse_data_t *d, CURLM *mhandle, char **error)
{
	CURLMcode		code;
	int			running = 0;
	CURLMsg			*msg;
	int			msg_num, long_query_limit, retries_num;
	zbx_vector_ptr_t	retries;
	double			ts_now, ts_last;

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
			*error = zbx_dsprintf(*error, "cannot perform on curl multi handle: %s",
					curl_multi_strerror(code));
			goto out;
		}

		if (CURLM_OK != (code = zbx_curl_multi_wait(mhandle, ZBX_HISTORY_STORAGE_TIMEOUT_MS, NULL)))
		{
			*error = zbx_dsprintf(*error, "cannot wait on curl multi handle: %s",
					curl_multi_strerror(code));
			goto out;
		}

		if (0 == running)
			break;

		ts_now = zbx_time();
		if (ts_now - ts_last >= long_query_limit)
		{
			zabbix_log(LOG_LEVEL_WARNING, "waiting for ClickHouse response " ZBX_FS_DBL "sec",
					ts_now - ts_last);
			ts_last = ts_now;
		}
	}
	while (0 != running);

	while (NULL != (msg = curl_multi_info_read(mhandle, &msg_num)))
	{
		zbx_clickhouse_conn_t	*conn = NULL;

		if (CURLE_OK != curl_easy_getinfo(msg->easy_handle, CURLINFO_PRIVATE, (char **)&conn) || NULL == conn)
		{
			history_clickhouse_add_error(error, "cannot obtain internal ClickHouse data conn");
			break;
		}

		if (CURLE_OK != msg->data.result)
		{
			if ('\0' != *conn->resp.errbuf)
			{
				history_clickhouse_add_error(error, "cannot send query to ClickHouse: %s",
						conn->resp.errbuf);
			}
			else
			{
				history_clickhouse_add_error(error, "cannot send query to ClickHouse: %s",
						curl_easy_strerror(msg->data.result));
			}

			/* If the error is due to curl internal problems or unrelated */
			/* problems with HTTP, we put the handle in a retry list and */
			/* remove it from the current execution loop */
			zbx_vector_ptr_append(&retries, msg->easy_handle);

			if (CURLM_OK != (code = curl_multi_remove_handle(mhandle, msg->easy_handle)))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot remove handle from curl handle: %s",
						curl_multi_strerror(code));
			}

			continue;
		}

		long		status;
		CURLcode	err;

		if (CURLE_OK != (err = curl_easy_getinfo(msg->easy_handle, CURLINFO_RESPONSE_CODE, &status)))
		{
			history_clickhouse_add_error(error, "cannot obtain HTTP response code: %s",
					curl_easy_strerror(err));
			continue;
		}

		if (400 <= status)
		{
			history_clickhouse_add_error(error, "cannot send query to ClickHouse,"
				" HTTP response code: %ld",
				status);

			if (NULL != conn->resp.page.data)
			{
				zbx_rtrim(conn->resp.page.data, "\n");

				char	*str = zbx_str_printable_dyn(conn->resp.page.data);

				history_clickhouse_add_error(error, "ClickHouse error: %s",
						str);

				zbx_free(str);
			}

			continue;
		}

		conn->status = SUCCEED;
	}
out:
	retries_num = 0;

	for (int i = 0; i < retries.values_num; i++)
	{
		if (CURLM_OK != (code = curl_multi_add_handle(mhandle, retries.values[i])))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot add handle to curl multi handle: %s",
						curl_multi_strerror(code));
		}
		else retries_num++;
	}

	zbx_vector_ptr_destroy(&retries);

	return retries_num;
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
	zbx_uint64_t		flush_err = 0;
	int			attempts_num = 0;
	CURLMcode		code;
	CURLcode		err;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() active connections:%d", __func__, d->active_conns.values_num);

	if (0 == d->active_conns.values_num)
		goto out;

	for (int i = 0; i < d->active_conns.values_num; i++)
	{
		zbx_clickhouse_conn_t	*conn = d->active_conns.values[i];

		if (CURLM_OK != (code = curl_multi_add_handle(d->mhandle, conn->handle)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot add handle to curl multi handle: %s",
						curl_multi_strerror(code));
		}

		zabbix_log(LOG_LEVEL_TRACE, "posting history to ClickHouse for value_type %d: %s", conn->value_type,
				conn->post_data);
	}

	while (1)
	{
		char	*error = NULL;
		int	retries_num;

		attempts_num++;
		retries_num = history_clickhouse_flush_conns(d, d->mhandle, &error);

		if (NULL != error)
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to upload data to ClickHouse: %s", error);
			zbx_free(error);
		}

		if (0 == retries_num)
			break;

		zabbix_log(LOG_LEVEL_ERR, "ClickHouse database is down: reconnecting in %d seconds",
				ZBX_HISTORY_STORAGE_DOWN_DELAY);

		sleep(ZBX_HISTORY_STORAGE_DOWN_DELAY);
	}

	if (1 < attempts_num)
		zabbix_log(LOG_LEVEL_ERR, "ClickHouse database connection re-established");

out:
	for (int i = 0; i < d->active_conns.values_num; i++)
	{
		zbx_clickhouse_conn_t	*conn = d->active_conns.values[i];

		if (SUCCEED != conn->status)
			flush_err |= history_make_flush_error(ZBX_HISTORY_FLUSH_FAIL, conn->value_type);

		if (CURLM_OK != (code = curl_multi_remove_handle(d->mhandle, conn->handle)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot remove handle from curl multi handle: %s",
					curl_multi_strerror(code));
		}

		if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_POSTFIELDSIZE, 0L)))
			zabbix_log(LOG_LEVEL_WARNING, "cannot remove post fields: %s", curl_easy_strerror(err));

		zbx_free(conn->post_data);
		zbx_free(conn->resp.page.data);
		conn->resp.page.alloc = 0;

		history_clickhouse_release_conn(d, conn);
	}

	zbx_vector_clickhouse_conn_ptr_clear(&d->active_conns);

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
 * Return value: pointer to next position in JSON data or NULL on failure     *
 *                                                                            *
 ******************************************************************************/
static const char	*history_clickhouse_parse_log_value(const struct zbx_json_parse *jp, const char *p,
		zbx_log_value_t *log)
{
	char	buf[MAX_ID_LEN] = {0};
	size_t	source_alloc = 0;

	if (NULL == (p = zbx_json_next_value_dyn(jp, p, &log->source, &source_alloc, NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse log source from row \"%s\"", jp->start);
		return NULL;
	}

	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse log severity from row \"%s\"", jp->start);
		return NULL;
	}
	log->severity = atoi(buf);

	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse log eventid from row \"%s\"", jp->start);
		return NULL;
	}
	log->logeventid = atoi(buf);

	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse log timestamp from row \"%s\"", jp->start);
		return NULL;
	}
	log->timestamp = atoi(buf);

	return p;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse numeric value (float or uint64) from JSON row               *
 *                                                                            *
 * Parameters:                                                                *
 *     jp         - [IN] row with data as JSON array of values                *
 *     p          - [IN] pointer to current position in JSON data             *
 *     value_type - [IN] value type (ITEM_VALUE_TYPE_FLOAT or                 *
 *                       ITEM_VALUE_TYPE_UINT64)                              *
 *     record     - [OUT] history record structure to fill                    *
 *                                                                            *
 * Return value: pointer to next position in JSON data or NULL on failure     *
 *                                                                            *
 ******************************************************************************/
static const char	*history_clickhouse_parse_numeric_value(const struct zbx_json_parse *jp, const char *p,
		unsigned char value_type, zbx_history_record_t *record)
{
	char	buf[ZBX_MAX_DOUBLE_LEN + 1];

	if (NULL == (p = zbx_json_next_value(jp, p, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse value from row \"%s\"", jp->start);
		return NULL;
	}

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			if (FAIL == zbx_is_double(buf, &record->value.dbl))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse floating value \"%s\"", buf);
				return NULL;
			}
			break;
		case ITEM_VALUE_TYPE_UINT64:
			if (FAIL == zbx_is_uint64(buf, &record->value.ui64))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot parse unsigned 64-bit value \"%s\"", buf);
				return NULL;
			}
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return NULL;
	}

	return p;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse non-numeric value (str, text or log) from JSON row          *
 *                                                                            *
 * Parameters:                                                                *
 *     jp         - [IN] row with data as JSON array of values                *
 *     p          - [IN] pointer to current position in JSON data             *
 *     value_type - [IN] item value type                                      *
 *     record     - [OUT] history record structure to fill                    *
 *                                                                            *
 * Return value: pointer to next position in JSON data or NULL on failure     *
 *                                                                            *
 ******************************************************************************/
static const char	*history_clickhouse_parse_value(const struct zbx_json_parse *jp, const char *p,
		unsigned char value_type, zbx_history_record_t *record)
{
	char	*buf = NULL;
	size_t	buf_alloc = 0;

	if (NULL == (p = zbx_json_next_value_dyn(jp, p, &buf, &buf_alloc, NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse value from row \"%s\"", jp->start);
		zbx_free(buf);
		return NULL;
	}

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			record->value.str = buf;
			break;
		case ITEM_VALUE_TYPE_LOG:
			record->value.log = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));
			record->value.log->value = buf;
			record->value.log->source = NULL;

			if (NULL == (p = history_clickhouse_parse_log_value(jp, p, record->value.log)))
			{
				zbx_history_record_clear(record, ITEM_VALUE_TYPE_LOG);
				return NULL;
			}
			break;
		default:
			zbx_free(buf);
			THIS_SHOULD_NEVER_HAPPEN;
			return NULL;
	}

	return p;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse item identifier from JSON row                               *
 *                                                                            *
 * Parameters: jp     - [IN] row with data as JSON array of values            *
 *             p      - [IN] pointer to current position in JSON data         *
 *             itemid - [OUT] parsed item identifier                          *
 *                                                                            *
 * Return value: pointer to next position in JSON data or NULL on failure     *
 *                                                                            *
 ******************************************************************************/
static const char	*history_clickhouse_parse_itemid(const struct zbx_json_parse *jp, const char *p,
		zbx_uint64_t *itemid)
{
	char		buf[MAX_ID_LEN + 1];

	if (NULL == (p = zbx_json_next_value(jp, NULL, buf, sizeof(buf), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse itemid from row \"%s\"", jp->start);
		return NULL;
	}

	if (SUCCEED != zbx_is_uint64(buf, itemid))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid itemid starting with \"%s\"", jp->start);
		return NULL;
	}

	return p;
}


/******************************************************************************
 *                                                                            *
 * Purpose: parse single row of ClickHouse response and add to records vector *
 *                                                                            *
 * Parameters:                                                                *
 *     jp         - [IN] row with log data as JSON array of values            *
 *     p          - [IN] pointer to current position in JSON data             *
 *     value_type - [IN] value type (ITEM_VALUE_TYPE_*)                       *
 *     record     - [OUT] parsed history record                               *
 *                                                                            *
 * Return value: pointer to next position in JSON data or NULL on failure     *
 *                                                                            *
 ******************************************************************************/
static const char	*history_clickhouse_parse_row(const struct zbx_json_parse *jp, const char *p,
		unsigned char value_type, zbx_history_record_t *record)
{
	char	clock_ns[MAX_ID_LEN * 2], *ptr;

	if (NULL == (p = zbx_json_next_value(jp, p, clock_ns, sizeof(clock_ns), NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse value timestamp from row \"%s\"", jp->start);
		return NULL;
	}

	if (NULL == (ptr = strchr(clock_ns, '.')))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid value timestamp format \"%s\"", clock_ns);
		return NULL;
	}

	*ptr++ = '\0';

	if (FAIL == zbx_is_uint32(clock_ns, &record->timestamp.sec))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid value timestamp seconds value \"%s\"", clock_ns);
		return NULL;
	}

	if (FAIL == zbx_is_uint32(ptr, &record->timestamp.ns))
	{
		zabbix_log(LOG_LEVEL_WARNING, "invalid value timestamp nanoseconds value \"%s\"", ptr);
		return NULL;
	}

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			return history_clickhouse_parse_numeric_value(jp, p, value_type, record);
		default:
			return history_clickhouse_parse_value(jp, p, value_type, record);
	}

	return NULL;
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

	if (NULL == response)
		return 0;

	zbx_vector_history_record_create(&records);

	for (start = response;; start = end + 1)
	{
		struct zbx_json_parse	jp;

		if (NULL != (end = strchr(start, '\n')))
			*end = '\0';

		if ('\0' != *start)
		{
			if (FAIL != zbx_json_open(start, &jp))
			{
				const char		*p = NULL;
				zbx_history_record_t	record = {0};

				if (NULL != history_clickhouse_parse_row(&jp, p, value_type, &record))
					zbx_vector_history_record_append(&records, record);
			}
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
 * Parameters: conn       - [IN] ClickHouse connection                        *
 *             d          - [IN] ClickHouse data                              *
 *             mhandle    - [IN] CURL multi handle                            *
 *             url        - [IN] URL for the request                          *
 *             data       - [IN] POST data to send                            *
 *             retry_mode - [IN] enable/disable retries:                      *
 *                               CLICKHOUSE_RETRIES_ON                        *
 *                               CLICKHOUSE_RETRIES_OFF                       *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - request sent successfully                          *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
static int	clickhouse_conn_post(zbx_clickhouse_conn_t *conn, zbx_clickhouse_data_t *d, CURLM *mhandle,
		const char *url, const char *data, zbx_clickhouse_retries_t retry_mode, char **error)
{
	CURLcode	err;
	CURLMcode	code;
	int		ret = FAIL, attempts_num = 0;
	char		*errmsg = NULL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() data:%s", __func__, data);

	if (NULL == conn->handle && SUCCEED != history_clickhouse_conn_init(conn, d, error))
		goto out;

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_URL, url)))
	{
		*error = zbx_dsprintf(NULL, "cannot set URL option: %s", curl_easy_strerror(err));
		goto out;
	}

	if (CURLE_OK != (err = curl_easy_setopt(conn->handle, CURLOPT_POSTFIELDSIZE, strlen(data))))
	{
		*error = zbx_dsprintf(NULL, "cannot set CURLOPT_POSTFIELDSIZE option: %s", curl_easy_strerror(err));
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

	if (CURLM_OK != (code = curl_multi_add_handle(mhandle, conn->handle)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot add handle to curl multi handle: %s", curl_multi_strerror(code));
	}

	while (1)
	{
		int	retries_num;

		attempts_num++;
		retries_num = history_clickhouse_flush_conns(d, mhandle, &errmsg);

		if (FAIL == conn->status && NULL == errmsg)
			errmsg = zbx_strdup(NULL, "unknown error");

		if (0 == retries_num || CLICKHOUSE_RETRIES_OFF == retry_mode)
			break;

		if (NULL != errmsg)
			zabbix_log(LOG_LEVEL_WARNING, "failed to fetch data from ClickHouse: %s", errmsg);

		zbx_free(errmsg);

		zabbix_log(LOG_LEVEL_ERR, "ClickHouse database is down: reconnecting in %d seconds",
				ZBX_HISTORY_STORAGE_DOWN_DELAY);

		sleep(ZBX_HISTORY_STORAGE_DOWN_DELAY);
	}

	if (NULL != errmsg)
	{
		*error = errmsg;
	}
	else
	{
		if (1 < attempts_num)
			zabbix_log(LOG_LEVEL_ERR, "ClickHouse database connection re-established");

		zabbix_log(LOG_LEVEL_TRACE, "result: %s", ZBX_NULL2STR(conn->resp.page.data));

		ret = SUCCEED;
	}

	if (CURLM_OK != (code = curl_multi_remove_handle(mhandle, conn->handle)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot remove handle from curl multi handle: %s",
					curl_multi_strerror(code));
	}
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
 *     itemid      - [IN]                                                     *
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
	char			*query = NULL, *errmsg = NULL;
	size_t			query_alloc = 0, query_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() start:" ZBX_FS_TIME_T " end:" ZBX_FS_TIME_T " count:%d", __func__,
			start, end, count);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	start_str[32], end_str[32];

		strftime(start_str, sizeof(start_str), "%Y-%m-%d %H:%M:%S", zbx_localtime(&start, NULL));
		strftime(end_str, sizeof(end_str), "%Y-%m-%d %H:%M:%S", zbx_localtime(&end, NULL));

		zabbix_log(LOG_LEVEL_DEBUG, "In %s() window:(%s, %s] age: %s count:%d", __func__, start_str, end_str,
				zbx_age2str(end - start), count);
	}

	conn = history_clickhouse_get_conn(d, value_type);

	zbx_strcpy_alloc(&query, &query_alloc, &query_offset, "select clock_ns,value");
	if (ITEM_VALUE_TYPE_LOG == value_type)
		zbx_strcpy_alloc(&query, &query_alloc, &query_offset, ",source,severity,logeventid,timestamp");

	zbx_snprintf_alloc(&query, &query_alloc, &query_offset, " from %s where itemid=" ZBX_FS_UI64,
			clickhouse_history_tables[value_type], itemid);

	if (0 != start)
	{
		zbx_recalc_time_period(&start, ZBX_RECALC_TIME_PERIOD_HISTORY, value_type);
		zbx_snprintf_alloc(&query, &query_alloc, &query_offset, " and clock_ns>='" ZBX_FS_TIME_T ".0'",
				start + 1);
	}

	if (0 != end)
	{
		zbx_snprintf_alloc(&query, &query_alloc, &query_offset, " and clock_ns<'" ZBX_FS_TIME_T ".0'",
				end + 1);
	}

	zbx_strcpy_alloc(&query, &query_alloc, &query_offset, " order by clock_ns desc");

	if (0 != count)
		zbx_snprintf_alloc(&query, &query_alloc, &query_offset, " LIMIT %d", count);

	zbx_strcpy_alloc(&query, &query_alloc, &query_offset, " format JSONCompactEachRow");

	zabbix_log(LOG_LEVEL_DEBUG, "query: %s", query);

	if (SUCCEED != clickhouse_conn_post(conn, d, d->mhandle, d->fetch_url, query, CLICKHOUSE_RETRIES_ON, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot fetch history from ClickHouse: %s", errmsg);
		zbx_free(errmsg);
	}
	else
		ret = history_clickhouse_parse_response(conn->resp.page.data, value_type, values);

	zbx_free(query);
	history_clickhouse_release_conn(d, conn);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() values_num:%d", __func__, ret);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse ClickHouse batch response and populate item history results *
 *                                                                            *
 * Parameters:                                                                *
 *     response   - [IN] ClickHouse response as string                        *
 *     value_type - [IN] value type (ITEM_VALUE_TYPE_*)                       *
 *     results    - [IN/OUT] vector of item history structures to fill        *
 *                                                                            *
 * Return value: number of items with history data received                   *
 *                                                                            *
 ******************************************************************************/
static int	history_clickhouse_parse_batch_response(char *response, unsigned char value_type,
		zbx_vector_item_history_t *results)
{
	char			*start, *end;
	zbx_item_history_t	*hist = NULL;
	int			batches_num = 0;

	if (NULL == response)
		return 0;

	for (start = response;; start = end + 1)
	{
		struct zbx_json_parse	jp;

		if ('\0' == *start)
			break;

		if (NULL != (end = strchr(start, '\n')))
			*end = '\0';

		if (FAIL != zbx_json_open(start, &jp))
		{
			const char		*p = NULL;
			zbx_uint64_t		itemid;
			zbx_history_record_t	record;

			if (NULL != (p = history_clickhouse_parse_itemid(&jp, p, &itemid)) &&
					NULL != history_clickhouse_parse_row(&jp, p, value_type, &record))
			{
				if (NULL == hist || hist->itemid != itemid)
				{
					int			index;
					zbx_item_history_t	hist_local;

					hist_local.itemid = itemid;

					index = zbx_vector_item_history_bsearch(results, hist_local,
							zbx_item_history_compare_by_itemid);

					if (FAIL == index)
					{
						THIS_SHOULD_NEVER_HAPPEN;
						zbx_history_record_clear(&record, value_type);
						break;
					}

					hist = &results->values[index];
					batches_num++;
				}

				zbx_vector_history_record_append(&hist->rows, record);
			}
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse row: %s", start);

		if (NULL == end)
			break;

		*end = '\n';
	}

	return batches_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch history data for multiple items from ClickHouse             *
 *                                                                            *
 * Parameters:                                                                *
 *     data       - [IN] internal ClickHouse data                             *
 *     results    - [IN/OUT] vector of item history structures to fill        *
 *     value_type - [IN] value type (ITEM_VALUE_TYPE_*)                       *
 *     start      - [IN] period start time                                    *
 *     limit      - [IN] maximum number of values to read                     *
 *     error      - [OUT] error message                                       *
 *                                                                            *
 * Return value: number of fetched batches or FAIL                            *
 *                                                                            *
 ******************************************************************************/
static int	history_clickhouse_fetch_batch(void *data, zbx_vector_item_history_t *results,
		unsigned char value_type, time_t start, int limit, char **error)
{
	zbx_clickhouse_data_t	*d = (zbx_clickhouse_data_t *)data;
	char			*sql = NULL, *errmsg = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_clickhouse_conn_t	*conn;
	int			ret = FAIL;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	start_str[32], end_str[32];
		time_t	end = time(NULL);

		strftime(start_str, sizeof(start_str), "%Y-%m-%d %H:%M:%S", zbx_localtime(&start, NULL));
		strftime(end_str, sizeof(end_str), "%Y-%m-%d %H:%M:%S", zbx_localtime(&end, NULL));

		zabbix_log(LOG_LEVEL_DEBUG, "In %s() window:(%s, %s] age: %s count:%d", __func__, start_str, end_str,
				zbx_age2str(end - start), 0);
	}

	zbx_vector_uint64_create(&itemids);
	for (int i = 0; i < results->values_num; i++)
	{
		zbx_vector_uint64_append(&itemids, results->values[i].itemid);
		zbx_vector_history_record_reserve(&results->values[i].rows, MIN(limit, 32));
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select itemid,clock_ns,value");
	if (ITEM_VALUE_TYPE_LOG == value_type)
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ",source,severity,logeventid,timestamp");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s where",
			clickhouse_history_tables[value_type]);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);
	zbx_vector_uint64_destroy(&itemids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and clock_ns>='" ZBX_FS_TIME_T ".0'", start + 1);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " order by itemid,clock_ns desc limit %d by itemid"
			" format JSONCompactEachRow", limit);

	zabbix_log(LOG_LEVEL_DEBUG, "batch query: %s", sql);

	conn = history_clickhouse_get_conn(d, value_type);

	if (SUCCEED != clickhouse_conn_post(conn, d, d->mhandle, d->fetch_url, sql, CLICKHOUSE_RETRIES_ON, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot fetch history batch from ClickHouse: %s", errmsg);
		zbx_free(errmsg);
	}
	else
		ret = history_clickhouse_parse_batch_response(conn->resp.page.data, value_type, results);

	zbx_free(sql);
	history_clickhouse_release_conn(d, conn);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() batches_num:%d", __func__, ret);

	return ret;
}

static void	history_clickhouse_add_value_type_info(zbx_history_provider_info_t *info, unsigned char value_type,
		const char *schema)
{
	zbx_history_provider_value_type_info_t	vti = {.value_type = value_type};
	char					*ttl = NULL;

	if (SUCCEED == zbx_mregexp_sub(schema, "TTL +clock_ns +\\+ +toInterval(\\w+)\\((\\d+)\\)", "\\1:\\2",
			ZBX_REGEXP_GROUP_CHECK_DISABLE, &ttl) && NULL != ttl)
	{
		char	*value;

		if (NULL != (value = strchr(ttl, ':')))
		{
			*value++ = '\0';
			vti.ttl = (time_t)atol(value);

			if (0 == strcmp(ttl, "Day"))
				vti.ttl *= SEC_PER_DAY;
			else if (0 == strcmp(ttl, "Week"))
				vti.ttl *= SEC_PER_WEEK;
			else if (0 == strcmp(ttl, "Month"))
				vti.ttl *= SEC_PER_MONTH;
			else if (0 == strcmp(ttl, "Quarter"))
				vti.ttl *= SEC_PER_MONTH * 3;
			else if (0 == strcmp(ttl, "Year"))
				vti.ttl *= SEC_PER_YEAR;
		}
		zbx_free(ttl);
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse TTL information from ClickHouse schema: %s", schema);

	zbx_vector_history_provider_value_type_info_append(&info->value_types, vti);
}

static void	history_clickhouse_get_value_type_data(zbx_clickhouse_data_t *d, CURLM *mhandle,
		zbx_clickhouse_conn_t *conn, zbx_history_provider_info_t *info)
{
	char	*error = NULL;
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset;

	zbx_vector_history_provider_value_type_info_reserve(&info->value_types, ITEM_VALUE_TYPE_COUNT);

	for (unsigned char i = 0; i < ITEM_VALUE_TYPE_NONE; i++)
	{
		if (FAIL == ZBX_HISTORY_CHECK_TYPE_FLAGS(d->value_type_flags, i))
			continue;

		sql_offset = 0;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "show create table %s", clickhouse_history_tables[i]);

		if (FAIL == clickhouse_conn_post(conn, d, mhandle, d->fetch_url, sql, CLICKHOUSE_RETRIES_OFF, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get TTL information for table %s from ClickHouse: %s",
					clickhouse_history_tables[i], error);
			zbx_free(error);
			continue;
		}

		if (NULL == conn->resp.page.data)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get TTL information for table %s:"
					" empty response received from ClickHouse", clickhouse_history_tables[i]);
			continue;
		}

		history_clickhouse_add_value_type_info(info, i, conn->resp.page.data);
	}

	zbx_free(sql);
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
	zbx_clickhouse_data_t	*d = (zbx_clickhouse_data_t *)data;
	int			ret = FAIL, v1, v2, v3, v4;
	zbx_clickhouse_conn_t	*conn;
	CURLM			*mhandle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	conn = (zbx_clickhouse_conn_t *)zbx_malloc(NULL, sizeof(zbx_clickhouse_conn_t));
	memset(conn, 0, sizeof(zbx_clickhouse_conn_t));

	if (NULL == (mhandle = curl_multi_init()))
	{
		*error = zbx_strdup(NULL, "Cannot initialize curl multi session");
		goto out;
	}

	/* value type is used only for flush operations */
	if (FAIL == clickhouse_conn_post(conn, d, mhandle, d->base_url, "select version()", CLICKHOUSE_RETRIES_OFF,
			error))
	{
		goto out;
	}

	memset(info, 0, sizeof(zbx_history_provider_info_t));

	if (NULL == conn->resp.page.data)
	{
		*error = zbx_dsprintf(NULL, "empty version data received from ClickHouse");
		goto out;
	}

	zbx_rtrim(conn->resp.page.data, "\n\r");
	if (4 != sscanf(conn->resp.page.data, "%d.%d.%d.%d", &v1, &v2, &v3, &v4))
	{
		*error = zbx_dsprintf(NULL, "unknown ClickHouse version: %s", conn->resp.page.data);
		goto out;
	}

	info->database = zbx_strdup(NULL, "ClickHouse");
	info->provider = zbx_strdup(NULL, HISTORY_PROVIDER_CLICKHOUSE);
	info->current_version = v1 * 1000000 + v2 * 1000 + v3;
	info->min_version = ZBX_CLICKHOUSE_MIN_VERSION;
	info->max_version = ZBX_CLICKHOUSE_MAX_VERSION;
	info->min_supported_version = ZBX_DBVERSION_UNDEFINED;

	info->friendly_current_version = zbx_strdup(NULL, conn->resp.page.data);
	info->friendly_min_version = zbx_strdup(NULL, ZBX_CLICKHOUSE_MIN_VERSION_STR);
	info->friendly_max_version = zbx_strdup(NULL, ZBX_CLICKHOUSE_MAX_VERSION_STR);
	info->friendly_min_supported_version = zbx_strdup(NULL, ZBX_CLICKHOUSE_MIN_VERSION_STR);

	zbx_vector_history_provider_value_type_info_create(&info->value_types);
	history_clickhouse_get_value_type_data(d, mhandle, conn, info);

	ret = SUCCEED;
out:
	curl_multi_cleanup(mhandle);
	clickhouse_conn_free(conn);

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

	zbx_curl_init();

	history_clickhouse_validate_options(options, options_num);

	if (NULL == (data = history_clickhouse_create_data(options, options_num, error)))
		return NULL;


	provider = (zbx_history_provider_t *)zbx_malloc(NULL, sizeof(zbx_history_provider_t));

	provider->name = zbx_strdup(NULL, HISTORY_PROVIDER_CLICKHOUSE);

	provider->traits = ZBX_HISTORY_TRAIT_TYPES_NOBIN | history_options_precache(options, options_num);
	provider->impl.write = history_clickhouse_write;
	provider->impl.flush = history_clickhouse_flush;
	provider->impl.fetch = history_clickhouse_fetch;
	provider->impl.fetch_batch = history_clickhouse_fetch_batch;
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
