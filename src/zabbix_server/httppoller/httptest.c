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

#include "db.h"
#include "log.h"
#include "dbcache.h"
#include "preproc.h"

#include "zbxserver.h"
#include "zbxregexp.h"
#include "zbxhttp.h"

#include "httptest.h"
#include "httpmacro.h"

typedef struct
{
	long	rspcode;
	double	total_time;
	double	speed_download;
}
zbx_httpstat_t;

extern int	CONFIG_HTTPPOLLER_FORKS;

#ifdef HAVE_LIBCURL

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
zbx_httppage_t;

#define ZBX_RETRIEVE_MODE_CONTENT	0
#define ZBX_RETRIEVE_MODE_HEADERS	1

static zbx_httppage_t	page;

static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	ZBX_UNUSED(userdata);

	/* first piece of data */
	if (NULL == page.data)
	{
		page.allocated = MAX(8096, r_size);
		page.offset = 0;
		page.data = (char *)zbx_malloc(page.data, page.allocated);
	}

	zbx_strncpy_alloc(&page.data, &page.allocated, &page.offset, (char *)ptr, r_size);

	return r_size;
}

static size_t	HEADERFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	ZBX_UNUSED(ptr);
	ZBX_UNUSED(userdata);

	return size * nmemb;
}

#endif	/* HAVE_LIBCURL */

/******************************************************************************
 *                                                                            *
 * Function: httptest_remove_macros                                           *
 *                                                                            *
 * Purpose: remove all macro variables cached during http test execution      *
 *                                                                            *
 * Parameters: httptest - [IN] the http test data                             *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static void	httptest_remove_macros(zbx_httptest_t *httptest)
{
	int	i;

	for (i = 0; i < httptest->macros.values_num; i++)
	{
		zbx_ptr_pair_t	*pair = &httptest->macros.values[i];

		zbx_free(pair->first);
		zbx_free(pair->second);
	}

	zbx_vector_ptr_pair_clear(&httptest->macros);
}

static void	process_test_data(zbx_uint64_t httptestid, int lastfailedstep, double speed_download,
		const char *err_str, zbx_timespec_t *ts)
{
	const char	*__function_name = "process_test_data";

	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	types[3];
	DC_ITEM		items[3];
	zbx_uint64_t	itemids[3];
	int		errcodes[3];
	size_t		i, num = 0;
	AGENT_RESULT	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select type,itemid from httptestitem where httptestid=" ZBX_FS_UI64, httptestid);

	while (NULL != (row = DBfetch(result)))
	{
		if (3 == num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			break;
		}

		switch (types[num] = (unsigned char)atoi(row[0]))
		{
			case ZBX_HTTPITEM_TYPE_SPEED:
			case ZBX_HTTPITEM_TYPE_LASTSTEP:
				break;
			case ZBX_HTTPITEM_TYPE_LASTERROR:
				if (NULL == err_str)
					continue;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
		}

		ZBX_STR2UINT64(itemids[num], row[1]);
		num++;
	}
	DBfree_result(result);

	if (0 < num)
	{
		DCconfig_get_items_by_itemids(items, itemids, errcodes, num);

		for (i = 0; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			if (ITEM_STATUS_ACTIVE != items[i].status)
				continue;

			if (HOST_STATUS_MONITORED != items[i].host.status)
				continue;

			if (HOST_MAINTENANCE_STATUS_ON == items[i].host.maintenance_status &&
					MAINTENANCE_TYPE_NODATA == items[i].host.maintenance_type)
			{
				continue;
			}

			init_result(&value);

			switch (types[i])
			{
				case ZBX_HTTPITEM_TYPE_SPEED:
					SET_UI64_RESULT(&value, speed_download);
					break;
				case ZBX_HTTPITEM_TYPE_LASTSTEP:
					SET_UI64_RESULT(&value, lastfailedstep);
					break;
				case ZBX_HTTPITEM_TYPE_LASTERROR:
					SET_STR_RESULT(&value, zbx_strdup(NULL, err_str));
					break;
			}

			items[i].state = ITEM_STATE_NORMAL;
			zbx_preprocess_item_value(items[i].itemid, items[i].value_type, 0, &value, ts, items[i].state,
					NULL);

			free_result(&value);
		}

		DCconfig_clean_items(items, errcodes, num);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: httpstep_pairs_join                                              *
 *                                                                            *
 * Purpose: performs concatenation of vector of pairs into delimited string   *
 *                                                                            *
 * Parameters: str             - [IN/OUT] result string                       *
 *             alloc_len       - [IN/OUT] allocated memory size               *
 *             offset          - [IN/OUT] offset within string                *
 *             value_delimiter - [IN] delimiter to be used between name and   *
 *                                    value                                   *
 *             pair_delimiter  - [IN] delimiter to be used between pairs      *
 *             pairs           - [IN] vector of pairs                         *
 *                                                                            *
 ******************************************************************************/
static void	httpstep_pairs_join(char **str, size_t *alloc_len, size_t *offset, const char *value_delimiter,
		const char *pair_delimiter, zbx_vector_ptr_pair_t *pairs)
{
	int	p;
	char	*key, *value;

	for (p = 0; p < pairs->values_num; p++)
	{
		key = (char *)pairs->values[p].first;
		value = (char *)pairs->values[p].second;

		if (0 != p)
			zbx_strcpy_alloc(str, alloc_len, offset, pair_delimiter);

		zbx_strcpy_alloc(str, alloc_len, offset, key);
		zbx_strcpy_alloc(str, alloc_len, offset, value_delimiter);
		zbx_strcpy_alloc(str, alloc_len, offset, value);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: httppairs_free                                                   *
 *                                                                            *
 * Purpose: frees memory allocated for vector of pairs                        *
 *                                                                            *
 * Parameters: pairs           - [IN] vector of pairs                         *
 *                                                                            *
 ******************************************************************************/
static void	httppairs_free(zbx_vector_ptr_pair_t *pairs)
{
	int	p;

	for (p = 0; p < pairs->values_num; p++)
	{
		zbx_free(pairs->values[p].first);
		zbx_free(pairs->values[p].second);
	}

	zbx_vector_ptr_pair_destroy(pairs);
}

#ifdef HAVE_LIBCURL
static void	process_step_data(zbx_uint64_t httpstepid, zbx_httpstat_t *stat, zbx_timespec_t *ts)
{
	const char	*__function_name = "process_step_data";

	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	types[3];
	DC_ITEM		items[3];
	zbx_uint64_t	itemids[3];
	int		errcodes[3];
	size_t		i, num = 0;
	AGENT_RESULT	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rspcode:%ld time:" ZBX_FS_DBL " speed:" ZBX_FS_DBL,
			__function_name, stat->rspcode, stat->total_time, stat->speed_download);

	result = DBselect("select type,itemid from httpstepitem where httpstepid=" ZBX_FS_UI64, httpstepid);

	while (NULL != (row = DBfetch(result)))
	{
		if (3 == num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			break;
		}

		if (ZBX_HTTPITEM_TYPE_RSPCODE != (types[num] = (unsigned char)atoi(row[0])) &&
				ZBX_HTTPITEM_TYPE_TIME != types[num] && ZBX_HTTPITEM_TYPE_SPEED != types[num])
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ZBX_STR2UINT64(itemids[num], row[1]);
		num++;
	}
	DBfree_result(result);

	if (0 < num)
	{
		DCconfig_get_items_by_itemids(items, itemids, errcodes, num);

		for (i = 0; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			if (ITEM_STATUS_ACTIVE != items[i].status)
				continue;

			if (HOST_STATUS_MONITORED != items[i].host.status)
				continue;

			if (HOST_MAINTENANCE_STATUS_ON == items[i].host.maintenance_status &&
					MAINTENANCE_TYPE_NODATA == items[i].host.maintenance_type)
			{
				continue;
			}

			init_result(&value);

			switch (types[i])
			{
				case ZBX_HTTPITEM_TYPE_RSPCODE:
					SET_UI64_RESULT(&value, stat->rspcode);
					break;
				case ZBX_HTTPITEM_TYPE_TIME:
					SET_DBL_RESULT(&value, stat->total_time);
					break;
				case ZBX_HTTPITEM_TYPE_SPEED:
					SET_DBL_RESULT(&value, stat->speed_download);
					break;
			}

			items[i].state = ITEM_STATE_NORMAL;
			zbx_preprocess_item_value(items[i].itemid, items[i].value_type, 0, &value, ts, items[i].state,
					NULL);

			free_result(&value);
		}

		DCconfig_clean_items(items, errcodes, num);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: httpstep_load_pairs                                              *
 *                                                                            *
 * Purpose: loads http fields of web scenario step                            *
 *                                                                            *
 * Parameters: host            - [IN] host to be used in macro expansion      *
 *             httpstep        - [IN/OUT] web scenario step                   *
 *                                                                            *
 * Return value: SUCCEED if http fields were loaded and macro expansion was   *
 *               successful. FAIL on error.                                   *
 *                                                                            *
 ******************************************************************************/
static int	httpstep_load_pairs(DC_HOST *host, zbx_httpstep_t *httpstep)
{
	int			type, ansi = 1, ret = SUCCEED;
	DB_RESULT		result;
	DB_ROW			row;
	size_t			alloc_len = 0, offset;
	zbx_ptr_pair_t		pair;
	zbx_vector_ptr_pair_t	*vector, headers, query_fields, post_fields;
	char			*key, *value, *url = NULL, query_delimiter = '?', *domain, *tmp;

	httpstep->url = NULL;
	httpstep->posts = NULL;
	httpstep->headers = NULL;

	zbx_vector_ptr_pair_create(&headers);
	zbx_vector_ptr_pair_create(&query_fields);
	zbx_vector_ptr_pair_create(&post_fields);
	zbx_vector_ptr_pair_create(&httpstep->variables);

	result = DBselect(
			"select name,value,type"
			" from httpstep_field"
			" where httpstepid=" ZBX_FS_UI64
			" order by httpstep_fieldid",
			httpstep->httpstep->httpstepid);

	while (NULL != (row = DBfetch(result)))
	{
		type = atoi(row[2]);

		value = zbx_strdup(NULL, row[1]);

		/* from now on variable values can contain macros so proper URL encoding can be performed */
		if (SUCCEED != (ret = substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL, NULL,
				&value, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0)))
		{
			zbx_free(value);
			goto out;
		}

		key = zbx_strdup(NULL, row[0]);

		/* variable names cannot contain macros, and both variable names and variable values cannot contain */
		/* another variables */
		if (ZBX_HTTPFIELD_VARIABLE != type && (SUCCEED != (ret = substitute_simple_macros(NULL, NULL, NULL,
				NULL, NULL, host, NULL, NULL, NULL, &key, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0)) ||
				SUCCEED != (ret = http_substitute_variables(httpstep->httptest, &key)) ||
				SUCCEED != (ret = http_substitute_variables(httpstep->httptest, &value))))
		{
			httppairs_free(&httpstep->variables);
			zbx_free(key);
			zbx_free(value);
			goto out;
		}

		/* keys and values of query fields / post fields should be encoded */
		if (ZBX_HTTPFIELD_QUERY_FIELD == type || ZBX_HTTPFIELD_POST_FIELD == type)
		{
			zbx_http_url_encode(key, &key);
			zbx_http_url_encode(value, &value);
		}

		switch (type)
		{
			case ZBX_HTTPFIELD_HEADER:
				vector = &headers;
				break;
			case ZBX_HTTPFIELD_VARIABLE:
				vector = &httpstep->variables;
				break;
			case ZBX_HTTPFIELD_QUERY_FIELD:
				vector = &query_fields;
				break;
			case ZBX_HTTPFIELD_POST_FIELD:
				vector = &post_fields;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				zbx_free(key);
				zbx_free(value);
				ret = FAIL;
				goto out;
		}

		pair.first = key;
		pair.second = value;

		zbx_vector_ptr_pair_append(vector, pair);
	}

	/* URL is created from httpstep->httpstep->url, query_fields and fragment */
	zbx_strcpy_alloc(&url, &alloc_len, &offset, httpstep->httpstep->url);

	value = strchr(url, '#');

	if (NULL != value)
	{
		/* URL contains fragment delimiter, so it must be dropped */

		zabbix_log(LOG_LEVEL_DEBUG, "URL contains fragment delimiter, fragment part is deleted from URL");
		*value = '\0';
		offset = value - url;
	}

	if (0 < query_fields.values_num)
	{
		/* url can contain '?' so proper delimiter should be selected */
		if (NULL != strchr(url, '?'))
			query_delimiter = '&';

		zbx_chrcpy_alloc(&url, &alloc_len, &offset, query_delimiter);
		httpstep_pairs_join(&url, &alloc_len, &offset, "=", "&", &query_fields);
	}

	if (NULL == (domain = strchr(url, '@')))
	{
		if (NULL == (domain = strstr(url, "://")))
			domain = url;
		else
			domain += ZBX_CONST_STRLEN("://");
	}
	else
		domain++;

	tmp = domain;

	while ('\0' != *tmp && ':' != *tmp && '/' != *tmp)
	{
		if (0 != ((*tmp) & 0x80))
			ansi = 0;
		tmp++;
	}

	if (0 == ansi)
	{
		/* non-ansi URL, conversion to the punicode is needed */

		char	*rest = NULL, *encoded_url = NULL, *encoded_domain = NULL, delimiter;

		delimiter = *tmp;

		if ('\0' != delimiter)
		{
			rest = tmp + 1;
			*tmp = '\0';
		}

		if (SUCCEED != (ret = zbx_http_punycode_encode(domain, &encoded_domain)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot encode unicode URL into punycode");
			httppairs_free(&httpstep->variables);
			zbx_free(url);
			goto out;
		}

		/* schema, schema separator and authority part (if any) */
		zbx_strncpy_alloc(&encoded_url, &alloc_len, &offset, url, domain - url);
		/* domain */
		zbx_strcpy_alloc(&encoded_url, &alloc_len, &offset, encoded_domain);
		zbx_free(encoded_domain);

		if ('\0' != delimiter)
		{
			/* rest of the URL (if any) */

			zbx_chrcpy_alloc(&encoded_url, &alloc_len, &offset, delimiter);
			zbx_strcpy_alloc(&encoded_url, &alloc_len, &offset, rest);
		}

		zbx_free(url);
		url = encoded_url;
	}

	httpstep->url = url;

	/* POST data can be saved as raw data or as form data */
	if (ZBX_POSTTYPE_FORM == httpstep->httpstep->post_type)
		httpstep_pairs_join(&httpstep->posts, &alloc_len, &offset, "=", "&", &post_fields);
	else
		httpstep->posts = httpstep->httpstep->posts;	/* post data in raw format */

	httpstep_pairs_join(&httpstep->headers, &alloc_len, &offset, ":", "\r\n", &headers);
out:
	httppairs_free(&headers);
	httppairs_free(&query_fields);
	httppairs_free(&post_fields);
	DBfree_result(result);

	return ret;
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: httptest_load_pairs                                              *
 *                                                                            *
 * Purpose: loads http fields of web scenario                                 *
 *                                                                            *
 * Parameters: host            - [IN] host to be used in macro expansion      *
 *             httptest        - [IN/OUT] web scenario                        *
 *                                                                            *
 * Return value: SUCCEED if http fields were loaded and macro expansion was   *
 *               successful. FAIL on error.                                   *
 *                                                                            *
 ******************************************************************************/
static int	httptest_load_pairs(DC_HOST *host, zbx_httptest_t *httptest)
{
	int			type, ret = SUCCEED;
	DB_RESULT		result;
	DB_ROW			row;
	size_t			alloc_len = 0, offset;
	zbx_ptr_pair_t		pair;
	zbx_vector_ptr_pair_t	*vector, headers;
	char			*key, *value;

	zbx_vector_ptr_pair_create(&headers);
	zbx_vector_ptr_pair_create(&httptest->variables);

	httptest->headers = NULL;
	result = DBselect(
			"select name,value,type"
			" from httptest_field"
			" where httptestid=" ZBX_FS_UI64
			" order by httptest_fieldid",
			httptest->httptest.httptestid);

	while (NULL != (row = DBfetch(result)))
	{
		type = atoi(row[2]);
		value = zbx_strdup(NULL, row[1]);

		/* from now on variable values can contain macros so proper URL encoding can be performed */
		if (SUCCEED != (ret = substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL, NULL,
				&value, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0)))
		{
			zbx_free(value);
			goto out;
		}

		key = zbx_strdup(NULL, row[0]);

		/* variable names cannot contain macros, and both variable names and variable values cannot contain */
		/* another variables */
		if (ZBX_HTTPFIELD_VARIABLE != type && SUCCEED != (ret = substitute_simple_macros(NULL, NULL, NULL,
				NULL, NULL, host, NULL, NULL, NULL, &key, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0)))
		{
			httppairs_free(&httptest->variables);
			zbx_free(key);
			zbx_free(value);
			goto out;
		}

		switch (type)
		{
			case ZBX_HTTPFIELD_HEADER:
				vector = &headers;
				break;
			case ZBX_HTTPFIELD_VARIABLE:
				vector = &httptest->variables;
				break;
			default:
				zbx_free(key);
				zbx_free(value);
				ret = FAIL;
				goto out;
		}

		pair.first = key;
		pair.second = value;

		zbx_vector_ptr_pair_append(vector, pair);
	}

	httpstep_pairs_join(&httptest->headers, &alloc_len, &offset, ":", "\r\n", &headers);
out:
	httppairs_free(&headers);
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_httptest                                                 *
 *                                                                            *
 * Purpose: process single scenario of http test                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_httptest(DC_HOST *host, zbx_httptest_t *httptest)
{
	const char	*__function_name = "process_httptest";

	DB_RESULT	result;
	DB_HTTPSTEP	db_httpstep;
	char		*err_str = NULL, *buffer = NULL;
	int		lastfailedstep = 0;
	zbx_timespec_t	ts;
	int		delay;
	double		speed_download = 0;
	int		speed_download_num = 0;
#ifdef HAVE_LIBCURL
	DB_ROW		row;
	zbx_httpstat_t	stat;
	char		errbuf[CURL_ERROR_SIZE];
	CURL		*easyhandle = NULL;
	CURLcode	err;
	zbx_httpstep_t	httpstep;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() httptestid:" ZBX_FS_UI64 " name:'%s'",
			__function_name, httptest->httptest.httptestid, httptest->httptest.name);

	result = DBselect(
			"select httpstepid,no,name,url,timeout,posts,required,status_codes,post_type,follow_redirects,"
				"retrieve_mode"
			" from httpstep"
			" where httptestid=" ZBX_FS_UI64
			" order by no",
			httptest->httptest.httptestid);

	buffer = zbx_strdup(buffer, httptest->httptest.delay);
	substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL, &buffer,
			MACRO_TYPE_COMMON, NULL, 0);

	if (SUCCEED != is_time_suffix(buffer, &delay, ZBX_LENGTH_UNLIMITED))
	{
		err_str = zbx_dsprintf(err_str, "update interval \"%s\" is invalid", buffer);
		lastfailedstep = -1;
		goto httptest_error;
	}

	/* Explicitly initialize the name. If we compile without libCURL support, */
	/* we avoid the potential usage of unititialized values. */
	db_httpstep.name = NULL;

#ifdef HAVE_LIBCURL
	if (NULL == (easyhandle = curl_easy_init()))
	{
		err_str = zbx_strdup(err_str, "cannot initialize cURL library");
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROXY, httptest->httptest.http_proxy)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERAGENT, httptest->httptest.agent)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ERRORBUFFER, errbuf)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != zbx_http_prepare_ssl(easyhandle, httptest->httptest.ssl_cert_file,
			httptest->httptest.ssl_key_file, httptest->httptest.ssl_key_password,
			httptest->httptest.verify_peer, httptest->httptest.verify_host, &err_str))
	{
		goto clean;
	}

	httpstep.httptest = httptest;
	httpstep.httpstep = &db_httpstep;

	while (NULL != (row = DBfetch(result)))
	{
		struct curl_slist	*headers_slist = NULL;

		/* NOTE: do not break or return from this block! */
		/*       process_step_data() call is required! */

		ZBX_STR2UINT64(db_httpstep.httpstepid, row[0]);
		db_httpstep.httptestid = httptest->httptest.httptestid;
		db_httpstep.no = atoi(row[1]);
		db_httpstep.name = row[2];

		db_httpstep.url = zbx_strdup(NULL, row[3]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL, NULL,
				&db_httpstep.url, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);
		http_substitute_variables(httptest, &db_httpstep.url);

		db_httpstep.post_type = atoi(row[8]);

		if (ZBX_POSTTYPE_RAW == db_httpstep.post_type)
		{
			db_httpstep.posts = zbx_strdup(NULL, row[5]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL, NULL,
					&db_httpstep.posts, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);
			http_substitute_variables(httptest, &db_httpstep.posts);
		}
		else
			db_httpstep.posts = NULL;

		if (SUCCEED != httpstep_load_pairs(host, &httpstep))
		{
			err_str = zbx_strdup(err_str, "cannot load web scenario step data");
			goto httpstep_error;
		}

		buffer = zbx_strdup(buffer, row[4]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL, &buffer,
				MACRO_TYPE_COMMON, NULL, 0);

		if (SUCCEED != is_time_suffix(buffer, &db_httpstep.timeout, ZBX_LENGTH_UNLIMITED))
		{
			err_str = zbx_dsprintf(err_str, "timeout \"%s\" is invalid", buffer);
			goto httpstep_error;
		}
		else if (SEC_PER_HOUR < db_httpstep.timeout)
		{
			err_str = zbx_dsprintf(err_str, "timeout \"%s\" exceeds 1 hour limit", buffer);
			goto httpstep_error;
		}

		db_httpstep.required = zbx_strdup(NULL, row[6]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL, NULL,
				&db_httpstep.required, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		db_httpstep.status_codes = zbx_strdup(NULL, row[7]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL,
				&db_httpstep.status_codes, MACRO_TYPE_COMMON, NULL, 0);

		db_httpstep.follow_redirects = atoi(row[9]);
		db_httpstep.retrieve_mode = atoi(row[10]);

		memset(&stat, 0, sizeof(stat));

		zabbix_log(LOG_LEVEL_DEBUG, "%s() use step \"%s\"", __function_name, db_httpstep.name);

		if (NULL != httpstep.posts && '\0' != *httpstep.posts)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() use post \"%s\"", __function_name, httpstep.posts);

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, httpstep.posts)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
				goto httpstep_error;
			}
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POST, (NULL != httpstep.posts &&
				'\0' != *httpstep.posts) ? 1L : 0L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_FOLLOWLOCATION,
				0 == db_httpstep.follow_redirects ? 0L : 1L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (0 != db_httpstep.follow_redirects)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_MAXREDIRS, ZBX_CURLOPT_MAXREDIRS)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
				goto httpstep_error;
			}
		}

		/* headers defined in a step overwrite headers defined in scenario */
		if (NULL != httpstep.headers && '\0' != *httpstep.headers)
			zbx_http_add_headers(httpstep.headers, &headers_slist);
		else if (NULL != httptest->headers && '\0' != *httptest->headers)
			zbx_http_add_headers(httptest->headers, &headers_slist);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPHEADER, headers_slist)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		/* enable/disable fetching the body */
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_NOBODY,
				ZBX_RETRIEVE_MODE_HEADERS == db_httpstep.retrieve_mode ? 1L : 0L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (SUCCEED != zbx_http_prepare_auth(easyhandle, httptest->httptest.authentication,
				httptest->httptest.http_user, httptest->httptest.http_password, &err_str))
		{
			goto httpstep_error;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() go to URL \"%s\"", __function_name, httpstep.url);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT, (long)db_httpstep.timeout)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, httpstep.url)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		/* try to retrieve page several times depending on number of retries */
		do
		{
			memset(&page, 0, sizeof(page));

			if (CURLE_OK == (err = curl_easy_perform(easyhandle)))
				break;

			zbx_free(page.data);
		}
		while (0 < --httptest->httptest.retries);

		curl_slist_free_all(headers_slist);	/* must be called after curl_easy_perform() */

		if (CURLE_OK == err)
		{
			zabbix_log(LOG_LEVEL_TRACE, "%s() page.data from %s:'%s'", __function_name, httpstep.url,
					page.data);

			/* first get the data that is needed even if step fails */
			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, &stat.rspcode)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else if ('\0' != *db_httpstep.status_codes &&
					FAIL == int_in_list(db_httpstep.status_codes, stat.rspcode))
			{
				err_str = zbx_dsprintf(err_str, "response code \"%ld\" did not match any of the"
						" required status codes \"%s\"", stat.rspcode,
						db_httpstep.status_codes);
			}

			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_TOTAL_TIME, &stat.total_time)) &&
					NULL == err_str)
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}

			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_SPEED_DOWNLOAD,
					&stat.speed_download)) && NULL == err_str)
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else
			{
				speed_download += stat.speed_download;
				speed_download_num++;
			}

			if (ZBX_RETRIEVE_MODE_CONTENT == db_httpstep.retrieve_mode)
			{
				char	*var_err_str = NULL;

				/* required pattern */
				if (NULL == err_str && '\0' != *db_httpstep.required &&
						NULL == zbx_regexp_match(page.data, db_httpstep.required, NULL))
				{
					err_str = zbx_dsprintf(err_str, "required pattern \"%s\" was not found on %s",
							db_httpstep.required, httpstep.url);
				}

				/* variables defined in scenario */
				if (NULL == err_str && FAIL == http_process_variables(httptest,
						&httptest->variables, page.data, &var_err_str))
				{
					char	*variables = NULL;
					size_t	alloc_len = 0, offset;

					httpstep_pairs_join(&variables, &alloc_len, &offset, "=", " ",
							&httptest->variables);

					err_str = zbx_dsprintf(err_str, "error in scenario variables \"%s\": %s",
							variables, var_err_str);

					zbx_free(variables);
				}

				/* variables defined in a step */
				if (NULL == err_str && FAIL == http_process_variables(httptest, &httpstep.variables,
						page.data, &var_err_str))
				{
					char	*variables = NULL;
					size_t	alloc_len = 0, offset;

					httpstep_pairs_join(&variables, &alloc_len, &offset, "=", " ",
							&httpstep.variables);

					err_str = zbx_dsprintf(err_str, "error in step variables \"%s\": %s",
							variables, var_err_str);

					zbx_free(variables);
				}

				zbx_free(var_err_str);
			}

			zbx_timespec(&ts);
			process_step_data(db_httpstep.httpstepid, &stat, &ts);

			zbx_free(page.data);
		}
		else
			err_str = zbx_dsprintf(err_str, "%s: %s", curl_easy_strerror(err), errbuf);

httpstep_error:
		zbx_free(db_httpstep.status_codes);
		zbx_free(db_httpstep.required);
		zbx_free(db_httpstep.posts);
		zbx_free(db_httpstep.url);

		httppairs_free(&httpstep.variables);

		if (ZBX_POSTTYPE_FORM == httpstep.httpstep->post_type)
			zbx_free(httpstep.posts);

		zbx_free(httpstep.url);
		zbx_free(httpstep.headers);

		if (NULL != err_str)
		{
			lastfailedstep = db_httpstep.no;
			break;
		}
	}
clean:
	curl_easy_cleanup(easyhandle);
#else
	err_str = zbx_strdup(err_str, "cURL library is required for Web monitoring support");
#endif	/* HAVE_LIBCURL */

httptest_error:
	zbx_timespec(&ts);

	if (0 > lastfailedstep)	/* update interval is invalid, delay is uninitialized */
	{
		zbx_config_t	cfg;

		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_REFRESH_UNSUPPORTED);
		DBexecute("update httptest set nextcheck=%d where httptestid=" ZBX_FS_UI64,
				(0 == cfg.refresh_unsupported || 0 > ts.sec + cfg.refresh_unsupported ?
				ZBX_JAN_2038 : ts.sec + cfg.refresh_unsupported), httptest->httptest.httptestid);
		zbx_config_clean(&cfg);
	}
	else if (0 > ts.sec + delay)
	{
		zabbix_log(LOG_LEVEL_WARNING, "nextcheck update causes overflow for web scenario \"%s\" on host \"%s\"",
				httptest->httptest.name, host->name);
		DBexecute("update httptest set nextcheck=%d where httptestid=" ZBX_FS_UI64,
				ZBX_JAN_2038, httptest->httptest.httptestid);
	}
	else
	{
		DBexecute("update httptest set nextcheck=%d where httptestid=" ZBX_FS_UI64,
				ts.sec + delay, httptest->httptest.httptestid);
	}

	if (NULL != err_str)
	{
		if (0 >= lastfailedstep)
		{
			/* we are here because web scenario update interval is invalid, */
			/* cURL initialization failed or we have been compiled without cURL library */

			lastfailedstep = 1;
		}

		if (NULL != db_httpstep.name)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot process step \"%s\" of web scenario \"%s\" on host \"%s\": "
					"%s", db_httpstep.name, httptest->httptest.name, host->name, err_str);
		}
	}
	DBfree_result(result);

	if (0 != speed_download_num)
		speed_download /= speed_download_num;

	process_test_data(httptest->httptest.httptestid, lastfailedstep, speed_download, err_str, &ts);

	zbx_free(buffer);
	zbx_free(err_str);
	zbx_preprocessor_flush();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_httptests                                                *
 *                                                                            *
 * Purpose: process httptests                                                 *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: number of processed httptests                                *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: always SUCCEED                                                   *
 *                                                                            *
 ******************************************************************************/
int	process_httptests(int httppoller_num, int now)
{
	const char	*__function_name = "process_httptests";

	DB_RESULT	result;
	DB_ROW		row;
	zbx_httptest_t	httptest;
	DC_HOST		host;
	int		httptests_count = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* create macro cache to use in http tests */
	zbx_vector_ptr_pair_create(&httptest.macros);

	result = DBselect(
			"select h.hostid,h.host,h.name,t.httptestid,t.name,t.agent,"
				"t.authentication,t.http_user,t.http_password,t.http_proxy,t.retries,t.ssl_cert_file,"
				"t.ssl_key_file,t.ssl_key_password,t.verify_peer,t.verify_host,t.delay"
			" from httptest t,hosts h"
			" where t.hostid=h.hostid"
				" and t.nextcheck<=%d"
				" and " ZBX_SQL_MOD(t.httptestid,%d) "=%d"
				" and t.status=%d"
				" and h.proxy_hostid is null"
				" and h.status=%d"
				" and (h.maintenance_status=%d or h.maintenance_type=%d)",
			now,
			CONFIG_HTTPPOLLER_FORKS, httppoller_num - 1,
			HTTPTEST_STATUS_MONITORED,
			HOST_STATUS_MONITORED,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(host.hostid, row[0]);
		strscpy(host.host, row[1]);
		strscpy(host.name, row[2]);

		ZBX_STR2UINT64(httptest.httptest.httptestid, row[3]);
		httptest.httptest.name = row[4];

		if (SUCCEED != httptest_load_pairs(&host, &httptest))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot process web scenario \"%s\" on host \"%s\": "
					"cannot load web scenario data", httptest.httptest.name, host.name);
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		httptest.httptest.agent = zbx_strdup(NULL, row[5]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL, NULL,
				&httptest.httptest.agent, MACRO_TYPE_COMMON, NULL, 0);

		if (HTTPTEST_AUTH_NONE != (httptest.httptest.authentication = atoi(row[6])))
		{
			httptest.httptest.http_user = zbx_strdup(NULL, row[7]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL, NULL,
					&httptest.httptest.http_user, MACRO_TYPE_COMMON, NULL, 0);

			httptest.httptest.http_password = zbx_strdup(NULL, row[8]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL, NULL,
					&httptest.httptest.http_password, MACRO_TYPE_COMMON, NULL, 0);
		}

		if ('\0' != *row[9])
		{
			httptest.httptest.http_proxy = zbx_strdup(NULL, row[9]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL, NULL,
					&httptest.httptest.http_proxy, MACRO_TYPE_COMMON, NULL, 0);
		}
		else
			httptest.httptest.http_proxy = NULL;

		httptest.httptest.retries = atoi(row[10]);

		httptest.httptest.ssl_cert_file = zbx_strdup(NULL, row[11]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL, NULL,
				&httptest.httptest.ssl_cert_file, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httptest.httptest.ssl_key_file = zbx_strdup(NULL, row[12]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL, NULL,
				&httptest.httptest.ssl_key_file, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httptest.httptest.ssl_key_password = zbx_strdup(NULL, row[13]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL, NULL,
				&httptest.httptest.ssl_key_password, MACRO_TYPE_COMMON, NULL, 0);

		httptest.httptest.verify_peer = atoi(row[14]);
		httptest.httptest.verify_host = atoi(row[15]);

		httptest.httptest.delay = row[16];

		/* add httptest variables to the current test macro cache */
		http_process_variables(&httptest, &httptest.variables, NULL, NULL);

		process_httptest(&host, &httptest);

		zbx_free(httptest.httptest.ssl_key_password);
		zbx_free(httptest.httptest.ssl_key_file);
		zbx_free(httptest.httptest.ssl_cert_file);
		zbx_free(httptest.httptest.http_proxy);

		if (HTTPTEST_AUTH_NONE != httptest.httptest.authentication)
		{
			zbx_free(httptest.httptest.http_password);
			zbx_free(httptest.httptest.http_user);
		}
		zbx_free(httptest.httptest.agent);
		zbx_free(httptest.headers);
		httppairs_free(&httptest.variables);

		/* clear the macro cache used in this http test */
		httptest_remove_macros(&httptest);

		httptests_count++;	/* performance metric */
	}
	/* destroy the macro cache used in http tests */
	zbx_vector_ptr_pair_destroy(&httptest.macros);

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return httptests_count;
}
