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

#include "httptest.h"

#include "zbxnix.h"
#include "zbxexpression.h"
#include "zbxhttp.h"
#include "httpmacro.h"
#include "zbxnum.h"
#include "zbx_host_constants.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbx_expression_constants.h"
#include "zbxexpr.h"

#ifdef HAVE_LIBCURL

#include "zbxregexp.h"

#include "zbxcurl.h"

typedef struct
{
	long		rspcode;
	double		total_time;
	curl_off_t	speed_download;
}
zbx_httpstat_t;

#endif	/* HAVE_LIBCURL */

/******************************************************************************
 *                                                                            *
 * Purpose: removes all macro variables cached during HTTP test execution     *
 *                                                                            *
 * Parameters: httptest - [IN] HTTP test data                                 *
 *                                                                            *
 ******************************************************************************/
static void	httptest_remove_macros(zbx_httptest_t *httptest)
{
	for (int i = 0; i < httptest->macros.values_num; i++)
	{
		zbx_ptr_pair_t	*pair = &httptest->macros.values[i];

		zbx_free(pair->first);
		zbx_free(pair->second);
	}

	zbx_vector_ptr_pair_clear(&httptest->macros);
}

/* HTTP item types */
#define ZBX_HTTPITEM_TYPE_RSPCODE	0
#define ZBX_HTTPITEM_TYPE_TIME		1
#define ZBX_HTTPITEM_TYPE_SPEED		2
#define ZBX_HTTPITEM_TYPE_LASTSTEP	3
#define ZBX_HTTPITEM_TYPE_LASTERROR	4

static void	process_test_data(zbx_uint64_t httptestid, int lastfailedstep, double speed_download,
		const char *err_str, zbx_timespec_t *ts)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	unsigned char	types[3];
	zbx_dc_item_t	items[3];
	zbx_uint64_t	itemids[3];
	int		errcodes[3];
	size_t		num = 0;
	AGENT_RESULT	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select("select type,itemid from httptestitem where httptestid=" ZBX_FS_UI64, httptestid);

	while (NULL != (row = zbx_db_fetch(result)))
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
	zbx_db_free_result(result);

	if (0 < num)
	{
		zbx_dc_config_get_items_by_itemids(items, itemids, errcodes, num);

		for (size_t i = 0; i < num; i++)
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

			zbx_init_agent_result(&value);

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
			zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type, 0, &value,
					ts, items[i].state, NULL);

			zbx_free_agent_result(&value);
		}

		zbx_dc_config_clean_items(items, errcodes, num);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
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
	for (int p = 0; p < pairs->values_num; p++)
	{
		char	*key = (char *)pairs->values[p].first, *value = (char *)pairs->values[p].second;

		if (0 != p)
			zbx_strcpy_alloc(str, alloc_len, offset, pair_delimiter);

		zbx_strcpy_alloc(str, alloc_len, offset, key);
		zbx_strcpy_alloc(str, alloc_len, offset, value_delimiter);
		zbx_strcpy_alloc(str, alloc_len, offset, value);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees memory allocated for vector of pairs                        *
 *                                                                            *
 * Parameters: pairs - [IN] vector of pairs                                   *
 *                                                                            *
 ******************************************************************************/
static void	httppairs_free(zbx_vector_ptr_pair_t *pairs)
{
	for (int p = 0; p < pairs->values_num; p++)
	{
		zbx_free(pairs->values[p].first);
		zbx_free(pairs->values[p].second);
	}

	zbx_vector_ptr_pair_destroy(pairs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolves macros in HTTP test field context                        *
 *                                                                            *
 * Parameters: p            - [IN] macro resolver data structure              *
 *             args         - [IN] list of variadic parameters                *
 *                                 Expected content:                          *
 *                                  - zbx_dc_um_handle_t *um_handle: user     *
 *                                      macro cache handle                    *
 *                                  - const zbx_dc_host_t *dc_host: host      *
 *                                      information                           *
 *             replace_with - [OUT] pointer to value to replace macro with    *
 *             data         - [IN/OUT] pointer to original input raw string   *
 *                                  (for macro in macro resolving), not used  *
 *             error        - [OUT] pointer to pre-allocated error message    *
 *                                  buffer (can be NULL), not used            *
 *             maxerrlen    - [IN] size of error message buffer (can be 0 if  *
 *                                 'error' is NULL), not used                 *
 *                                                                            *
 * Return value: SUCCEED if macro data were resolved successfully.            *
 *               Otherwise FAIL.                                              *
 *                                                                            *
 ******************************************************************************/
static int	macro_httptest_field_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_with, char **data,
		char *error, size_t maxerrlen)
{
	int			ret = SUCCEED;
	zbx_dc_interface_t	interface;

	/* Passed arguments */
	zbx_dc_um_handle_t	*um_handle = va_arg(args, zbx_dc_um_handle_t *);
	const zbx_dc_host_t	*dc_host = va_arg(args, const zbx_dc_host_t *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (ZBX_TOKEN_USER_MACRO == p->token.type || (ZBX_TOKEN_USER_FUNC_MACRO == p->token.type &&
				0 == strncmp(p->macro, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
	{
		zbx_dc_get_user_macro(um_handle, p->macro, &dc_host->hostid, 1, replace_with);
		p->pos = p->token.loc.r;
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
	{
		*replace_with = zbx_strdup(*replace_with, dc_host->host);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
	{
		*replace_with = zbx_strdup(*replace_with, dc_host->name);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
	{
		if (SUCCEED == (ret = zbx_dc_config_get_interface(&interface, dc_host->hostid, 0)))
			*replace_with = zbx_strdup(*replace_with, interface.ip_orig);
	}
	else if	(0 == strcmp(p->macro, MVAR_HOST_DNS))
	{
		if (SUCCEED == (ret = zbx_dc_config_get_interface(&interface, dc_host->hostid, 0)))
			*replace_with = zbx_strdup(*replace_with, interface.dns_orig);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
	{
		if (SUCCEED == (ret = zbx_dc_config_get_interface(&interface, dc_host->hostid, 0)))
			*replace_with = zbx_strdup(*replace_with, interface.addr);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
	{
		if (SUCCEED == (ret = zbx_dc_config_get_interface(&interface, dc_host->hostid, 0)))
			*replace_with = zbx_strdup(*replace_with, interface.port_orig);
	}

	return ret;
}

#ifdef HAVE_LIBCURL
static void	process_step_data(zbx_uint64_t httpstepid, zbx_httpstat_t *stat, zbx_timespec_t *ts)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	unsigned char	types[3];
	zbx_dc_item_t	items[3];
	zbx_uint64_t	itemids[3];
	int		errcodes[3];
	size_t		num = 0;
	AGENT_RESULT	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rspcode:%ld time:" ZBX_FS_DBL " speed:%" CURL_FORMAT_CURL_OFF_T,
			__func__, stat->rspcode, stat->total_time, stat->speed_download);

	result = zbx_db_select("select type,itemid from httpstepitem where httpstepid=" ZBX_FS_UI64, httpstepid);

	while (NULL != (row = zbx_db_fetch(result)))
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
	zbx_db_free_result(result);

	if (0 < num)
	{
		zbx_dc_config_get_items_by_itemids(items, itemids, errcodes, num);

		for (size_t i = 0; i < num; i++)
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

			zbx_init_agent_result(&value);

			switch (types[i])
			{
				case ZBX_HTTPITEM_TYPE_RSPCODE:
					SET_UI64_RESULT(&value, stat->rspcode);
					break;
				case ZBX_HTTPITEM_TYPE_TIME:
					SET_DBL_RESULT(&value, stat->total_time);
					break;
				case ZBX_HTTPITEM_TYPE_SPEED:
					SET_DBL_RESULT(&value, (double)stat->speed_download);
					break;
			}

			items[i].state = ITEM_STATE_NORMAL;
			zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type, 0, &value,
					ts, items[i].state, NULL);

			zbx_free_agent_result(&value);
		}

		zbx_dc_config_clean_items(items, errcodes, num);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: loads HTTP fields of web scenario step                            *
 *                                                                            *
 * Parameters: host     - [IN] host to be used in macro expansion             *
 *             httpstep - [IN/OUT] web scenario step                          *
 *                                                                            *
 * Return value: SUCCEED if HTTP fields were loaded and macro expansion was   *
 *               successful. FAIL on error.                                   *
 *                                                                            *
 ******************************************************************************/
static int	httpstep_load_pairs(zbx_dc_host_t *host, zbx_httpstep_t *httpstep)
{
	int			type, ret = SUCCEED;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	size_t			alloc_len = 0, offset;
	zbx_ptr_pair_t		pair;
	zbx_vector_ptr_pair_t	*vector, headers, query_fields, post_fields;
	char			*key, *value, *url = NULL, query_delimiter = '?';

	httpstep->url = NULL;
	httpstep->posts = NULL;
	httpstep->headers = NULL;

	zbx_vector_ptr_pair_create(&headers);
	zbx_vector_ptr_pair_create(&query_fields);
	zbx_vector_ptr_pair_create(&post_fields);
	zbx_vector_ptr_pair_create(&httpstep->variables);

	result = zbx_db_select(
			"select name,value,type"
			" from httpstep_field"
			" where httpstepid=" ZBX_FS_UI64
			" order by httpstep_fieldid",
			httpstep->httpstep->httpstepid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		type = atoi(row[2]);

		value = zbx_strdup(NULL, row[1]);

		/* from now on variable values can contain macros so proper URL encoding can be performed */

		zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros_secure();
		ret = zbx_substitute_macros(&value, NULL, 0, &macro_httptest_field_resolv, um_handle, host);
		zbx_dc_close_user_macros(um_handle);

		if (SUCCEED != ret)
		{
			zbx_free(value);
			goto out;
		}

		key = zbx_strdup(NULL, row[0]);

		/* variable names cannot contain macros, and both variable names and variable values cannot contain */
		/* another variables */
		if (ZBX_HTTPFIELD_VARIABLE != type)
		{
			um_handle = zbx_dc_open_user_macros();

			if ((SUCCEED != (ret = zbx_substitute_macros(&key, NULL, 0, &macro_httptest_field_resolv,
					um_handle, host)) ||
					SUCCEED != (ret = http_substitute_variables(httpstep->httptest, &key)) ||
					SUCCEED != (ret = http_substitute_variables(httpstep->httptest, &value))))
			{
				zbx_dc_close_user_macros(um_handle);
				httppairs_free(&httpstep->variables);
				zbx_free(key);
				zbx_free(value);
				goto out;
			}

			zbx_dc_close_user_macros(um_handle);
		}

		/* keys and values of query fields / post fields should be encoded */
		if (ZBX_HTTPFIELD_QUERY_FIELD == type || ZBX_HTTPFIELD_POST_FIELD == type)
		{
			zbx_url_encode(key, &key);
			zbx_url_encode(value, &value);
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
		/* URL can contain '?' so proper delimiter should be selected */
		if (NULL != strchr(url, '?'))
			query_delimiter = '&';

		zbx_chrcpy_alloc(&url, &alloc_len, &offset, query_delimiter);
		httpstep_pairs_join(&url, &alloc_len, &offset, "=", "&", &query_fields);
	}

	if (SUCCEED != (ret = zbx_http_punycode_encode_url(&url)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot encode unicode URL into punycode");
		httppairs_free(&httpstep->variables);
		zbx_free(url);
		goto out;
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
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds HTTP headers to curl_slist and prepares cookie header string *
 *                                                                            *
 * Parameters: headers       - [IN] HTTP headers as string                    *
 *             headers_slist - [IN/OUT] curl_slist                            *
 *             header_cookie - [IN/OUT] cookie header as string               *
 *                                                                            *
 ******************************************************************************/
static void	add_http_headers(char *headers, struct curl_slist **headers_slist, char **header_cookie)
{
#define COOKIE_HEADER_STR	"Cookie:"
#define COOKIE_HEADER_STR_LEN	ZBX_CONST_STRLEN(COOKIE_HEADER_STR)
	char	*line;

	while (NULL != (line = zbx_http_parse_header(&headers)))
	{
		if (0 == strncmp(COOKIE_HEADER_STR, line, COOKIE_HEADER_STR_LEN))
			*header_cookie = zbx_strdup(*header_cookie, line + COOKIE_HEADER_STR_LEN);
		else
			*headers_slist = curl_slist_append(*headers_slist, line);

		zbx_free(line);
	}
#undef COOKIE_HEADER_STR
#undef COOKIE_HEADER_STR_LEN
}
#endif

#undef ZBX_HTTPITEM_TYPE_RSPCODE
#undef ZBX_HTTPITEM_TYPE_TIME
#undef ZBX_HTTPITEM_TYPE_SPEED
#undef ZBX_HTTPITEM_TYPE_LASTSTEP
#undef ZBX_HTTPITEM_TYPE_LASTERROR

/******************************************************************************
 *                                                                            *
 * Purpose: loads HTTP fields of web scenario                                 *
 *                                                                            *
 * Parameters: host     - [IN] host to be used in macro expansion             *
 *             httptest - [IN/OUT] web scenario                               *
 *                                                                            *
 * Return value: SUCCEED if HTTP fields were loaded and macro expansion was   *
 *               successful. FAIL on error.                                   *
 *                                                                            *
 ******************************************************************************/
static int	httptest_load_pairs(zbx_dc_host_t *host, zbx_httptest_t *httptest)
{
	int			type, ret = SUCCEED;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	size_t			alloc_len = 0, offset;
	zbx_ptr_pair_t		pair;
	zbx_vector_ptr_pair_t	*vector, headers;
	char			*key, *value;

	zbx_vector_ptr_pair_create(&headers);
	zbx_vector_ptr_pair_create(&httptest->variables);

	httptest->headers = NULL;
	result = zbx_db_select(
			"select name,value,type"
			" from httptest_field"
			" where httptestid=" ZBX_FS_UI64
			" order by httptest_fieldid",
			httptest->httptest.httptestid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		type = atoi(row[2]);
		value = zbx_strdup(NULL, row[1]);

		/* from now on variable values can contain macros so proper URL encoding can be performed */
		zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros_secure();
		ret = zbx_substitute_macros(&value, NULL, 0, &macro_httptest_field_resolv, um_handle, host);
		zbx_dc_close_user_macros(um_handle);

		if (SUCCEED != ret)
		{
			zbx_free(value);
			goto out;
		}

		key = zbx_strdup(NULL, row[0]);

		/* variable names cannot contain macros, and both variable names and variable values cannot contain */
		/* another variables */
		if (ZBX_HTTPFIELD_VARIABLE != type)
		{
			um_handle = zbx_dc_open_user_macros();

			if (SUCCEED != (ret = zbx_substitute_macros(&key, NULL, 0, &macro_httptest_field_resolv,
					um_handle, host)))
			{
				zbx_dc_close_user_macros(um_handle);
				httppairs_free(&httptest->variables);
				zbx_free(key);
				zbx_free(value);
				goto out;
			}

			zbx_dc_close_user_macros(um_handle);
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
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes single scenario of HTTP test                            *
 *                                                                            *
 ******************************************************************************/
static void	process_httptest(zbx_dc_host_t *host, zbx_httptest_t *httptest, int *delay,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location)
{
	zbx_db_result_t		result;
	zbx_db_httpstep		db_httpstep;
	char			*err_str = NULL, *buffer = NULL;
	zbx_timespec_t		ts;
	double			speed_download = 0;
	int			speed_download_num = 0, lastfailedstep = 0;
#ifdef HAVE_LIBCURL
	zbx_db_row_t		row;
	zbx_httpstat_t		stat;
	char			errbuf[CURL_ERROR_SIZE];
	CURL			*easyhandle = NULL;
	CURLcode		err;
	zbx_httpstep_t		httpstep;
	zbx_http_response_t	body = {0}, header = {0};
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() httptestid:" ZBX_FS_UI64 " name:'%s'",
			__func__, httptest->httptest.httptestid, httptest->httptest.name);

	result = zbx_db_select(
			"select httpstepid,no,name,url,timeout,posts,required,status_codes,post_type,follow_redirects,"
				"retrieve_mode"
			" from httpstep"
			" where httptestid=" ZBX_FS_UI64
			" order by no",
			httptest->httptest.httptestid);

	buffer = zbx_strdup(buffer, httptest->httptest.delay);
	zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&buffer, ZBX_MACRO_TYPE_COMMON, NULL, 0);

	/* Avoid the potential usage of uninitialized values when: */
	/* 1) compile without libCURL support */
	/* 2) update interval is invalid */
	db_httpstep.name = NULL;

	if (SUCCEED != zbx_is_time_suffix(buffer, delay, ZBX_LENGTH_UNLIMITED))
	{
		err_str = zbx_dsprintf(err_str, "update interval \"%s\" is invalid", buffer);
		lastfailedstep = -1;
		*delay = ZBX_DEFAULT_INTERVAL;
		goto httptest_error;
	}

#ifdef HAVE_LIBCURL
	if (NULL == (easyhandle = curl_easy_init()))
	{
		err_str = zbx_strdup(err_str, "cannot initialize cURL library");
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_PROXY, httptest->httptest.http_proxy)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERAGENT, httptest->httptest.agent)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ACCEPT_ENCODING, "")))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != zbx_curl_setopt_https(easyhandle, &err_str))
		goto clean;

	if (SUCCEED != zbx_http_prepare_ssl(easyhandle, httptest->httptest.ssl_cert_file,
			httptest->httptest.ssl_key_file, httptest->httptest.ssl_key_password,
			httptest->httptest.verify_peer, httptest->httptest.verify_host, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &err_str))
	{
		goto clean;
	}

	httpstep.httptest = httptest;
	httpstep.httpstep = &db_httpstep;

	while (NULL != (row = zbx_db_fetch(result)) && ZBX_IS_RUNNING())
	{
		struct curl_slist	*headers_slist = NULL;
		char			*header_cookie = NULL;
		zbx_curl_cb_t		curl_body_cb, curl_header_cb;

		/* NOTE: do not break or return from this block! */
		/*       process_step_data() call is required! */

		ZBX_STR2UINT64(db_httpstep.httpstepid, row[0]);
		db_httpstep.httptestid = httptest->httptest.httptestid;
		db_httpstep.no = atoi(row[1]);
		db_httpstep.name = row[2];

		db_httpstep.url = zbx_strdup(NULL, row[3]);

		zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros_secure();
		zbx_substitute_macros(&db_httpstep.url, NULL, 0, &macro_httptest_field_resolv, um_handle, host);
		zbx_dc_close_user_macros(um_handle);

		http_substitute_variables(httptest, &db_httpstep.url);

		db_httpstep.required = zbx_strdup(NULL, row[6]);

		um_handle = zbx_dc_open_user_macros();
		zbx_substitute_macros(&db_httpstep.required, NULL, 0, &macro_httptest_field_resolv, um_handle, host);
		zbx_dc_close_user_macros(um_handle);

		db_httpstep.status_codes = zbx_strdup(NULL, row[7]);
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL, NULL, NULL,
				NULL, &db_httpstep.status_codes, ZBX_MACRO_TYPE_COMMON, NULL, 0);

		db_httpstep.post_type = atoi(row[8]);

		if (ZBX_POSTTYPE_RAW == db_httpstep.post_type)
		{
			db_httpstep.posts = zbx_strdup(NULL, row[5]);

			um_handle = zbx_dc_open_user_macros_secure();
			zbx_substitute_macros(&db_httpstep.posts, NULL, 0, &macro_httptest_field_resolv, um_handle,
					host);
			zbx_dc_close_user_macros(um_handle);

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
		zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL, NULL, NULL, NULL,
				NULL, &buffer, ZBX_MACRO_TYPE_COMMON, NULL, 0);

		if (SUCCEED != zbx_is_time_suffix(buffer, &db_httpstep.timeout, ZBX_LENGTH_UNLIMITED))
		{
			err_str = zbx_dsprintf(err_str, "timeout \"%s\" is invalid", buffer);
			goto httpstep_error;
		}
		else if (db_httpstep.timeout < 1 || SEC_PER_HOUR < db_httpstep.timeout)
		{
			err_str = zbx_dsprintf(err_str, "timeout \"%s\" is out of 1-3600 seconds bounds", buffer);
			goto httpstep_error;
		}

		db_httpstep.follow_redirects = atoi(row[9]);
		db_httpstep.retrieve_mode = atoi(row[10]);

		memset(&stat, 0, sizeof(stat));

		zabbix_log(LOG_LEVEL_DEBUG, "%s() use step \"%s\"", __func__, db_httpstep.name);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() use post \"%s\"", __func__, ZBX_NULL2EMPTY_STR(httpstep.posts));

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, httpstep.posts)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
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
			add_http_headers(httpstep.headers, &headers_slist, &header_cookie);
		else if (NULL != httptest->headers && '\0' != *httptest->headers)
			add_http_headers(httptest->headers, &headers_slist, &header_cookie);

		err = curl_easy_setopt(easyhandle, CURLOPT_COOKIE, header_cookie);
		zbx_free(header_cookie);

		if (CURLE_OK != err)
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPHEADER, headers_slist)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		switch (db_httpstep.retrieve_mode)
		{
			case ZBX_RETRIEVE_MODE_CONTENT:
				curl_header_cb = zbx_curl_ignore_cb;
				curl_body_cb = zbx_curl_write_cb;
				break;
			case ZBX_RETRIEVE_MODE_BOTH:
				curl_header_cb = curl_body_cb = zbx_curl_write_cb;
				break;
			case ZBX_RETRIEVE_MODE_HEADERS:
				curl_header_cb = zbx_curl_write_cb;
				curl_body_cb = zbx_curl_ignore_cb;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				err_str = zbx_strdup(err_str, "invalid retrieve mode");
				goto httpstep_error;
		}

		if (SUCCEED != zbx_http_prepare_callbacks(easyhandle, &header, &body, curl_header_cb, curl_body_cb,
				errbuf, &err_str))
		{
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
				httptest->httptest.http_user, httptest->httptest.http_password, NULL, &err_str))
		{
			goto httpstep_error;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() go to URL \"%s\"", __func__, httpstep.url);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT, (long)db_httpstep.timeout)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, httpstep.url)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		/* try to retrieve page several times depending on number of retries */
		do
		{
			memset(&header, 0, sizeof(header));
			memset(&body, 0, sizeof(body));
			errbuf[0] = '\0';

			if (CURLE_OK == (err = curl_easy_perform(easyhandle)))
				break;

			zbx_free(body.data);
			zbx_free(header.data);
		}
		while (0 < --httptest->httptest.retries);

		if (CURLE_OK == err)
		{
			char	*var_err_str = NULL, *data = NULL;

			if (NULL != body.data)
			{
				zbx_http_convert_to_utf8(easyhandle, &body.data, &body.offset, &body.allocated);
				data = body.data;
			}

			if (NULL != header.data)
			{
				if (NULL != body.data)
				{
					zbx_strncpy_alloc(&header.data, &header.allocated, &header.offset, body.data,
							body.offset);
				}

				data = header.data;
			}

			if (NULL == data)
				data = "";

			zabbix_log(LOG_LEVEL_TRACE, "%s() page.data from %s:'%s'", __func__, httpstep.url, data);

			/* first get the data that is needed even if step fails */
			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, &stat.rspcode)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else if ('\0' != *db_httpstep.status_codes &&
					FAIL == zbx_int_in_list(db_httpstep.status_codes, stat.rspcode))
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

			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_SPEED_DOWNLOAD_T,
					&stat.speed_download)) && NULL == err_str)
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else
			{
				speed_download += (double)stat.speed_download;
				speed_download_num++;
			}

			/* required pattern */
			if (NULL == err_str && '\0' != *db_httpstep.required &&
					NULL == zbx_regexp_match(data, db_httpstep.required, NULL))
			{
				err_str = zbx_dsprintf(err_str, "required pattern \"%s\" was not found on %s",
						db_httpstep.required, httpstep.url);
			}

			/* variables defined in scenario */
			if (NULL == err_str && FAIL == http_process_variables(httptest, &httptest->variables,
					data, &var_err_str))
			{
				char	*variables = NULL;
				size_t	alloc_len = 0, offset;

				httpstep_pairs_join(&variables, &alloc_len, &offset, "=", " ", &httptest->variables);

				err_str = zbx_dsprintf(err_str, "error in scenario variables \"%s\": %s", variables,
						var_err_str);

				zbx_free(variables);
			}

			/* variables defined in a step */
			if (NULL == err_str && FAIL == http_process_variables(httptest, &httpstep.variables, data,
					&var_err_str))
			{
				char	*variables = NULL;
				size_t	alloc_len = 0, offset;

				httpstep_pairs_join(&variables, &alloc_len, &offset, "=", " ", &httpstep.variables);

				err_str = zbx_dsprintf(err_str, "error in step variables \"%s\": %s", variables,
						var_err_str);

				zbx_free(variables);
			}

			zbx_free(var_err_str);

			zbx_timespec(&ts);
			process_step_data(db_httpstep.httpstepid, &stat, &ts);

			zbx_free(header.data);
			zbx_free(body.data);
		}
		else
			err_str = zbx_dsprintf(err_str, "%s", 0 < strlen(errbuf) ? errbuf : curl_easy_strerror(err));
httpstep_error:
		curl_slist_free_all(headers_slist);
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
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	ZBX_UNUSED(config_ssl_cert_location);
	ZBX_UNUSED(config_ssl_key_location);

	err_str = zbx_strdup(err_str, "cURL library is required for Web monitoring support");
#endif	/* HAVE_LIBCURL */

httptest_error:
	zbx_timespec(&ts);

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
	zbx_db_free_result(result);

	if (0 != speed_download_num)
		speed_download /= speed_download_num;

	process_test_data(httptest->httptest.httptestid, lastfailedstep, speed_download, err_str, &ts);

	zbx_free(buffer);
	zbx_free(err_str);
	zbx_preprocessor_flush();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Parameters: now                      - [IN] current timestamp              *
 *             config_source_ip         - [IN]                                *
 *             config_ssl_ca_location   - [IN]                                *
 *             config_ssl_cert_location - [IN]                                *
 *             config_ssl_key_location  - [IN]                                *
 *             nextcheck                - [OUT]                               *
 *                                                                            *
 * Return value: number of processed httptests                                *
 *                                                                            *
 * Comments: always SUCCEED                                                   *
 *                                                                            *
 ******************************************************************************/
int	process_httptests(int now, const char *config_source_ip, const char *config_ssl_ca_location,
		const char *config_ssl_cert_location, const char *config_ssl_key_location, time_t *nextcheck)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_uint64_t		httptestid;
	zbx_httptest_t		httptest;
	zbx_dc_host_t		host;
	int			httptests_count = 0;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_dc_httptest_next(now, &httptestid, nextcheck))
		goto out;

	/* create macro cache to use in HTTP tests */
	zbx_vector_ptr_pair_create(&httptest.macros);

	do
	{
		int	delay = 0;

		result = zbx_db_select(
				"select h.hostid,h.host,h.name,t.httptestid,t.name,t.agent,"
					"t.authentication,t.http_user,t.http_password,t.http_proxy,t.retries,"
					"t.ssl_cert_file,t.ssl_key_file,t.ssl_key_password,t.verify_peer,"
					"t.verify_host,t.delay"
				" from httptest t,hosts h"
				" where t.hostid=h.hostid"
					" and t.httptestid=" ZBX_FS_UI64,
				httptestid);

		if (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(host.hostid, row[0]);
			zbx_strscpy(host.host, row[1]);
			zbx_strlcpy_utf8(host.name, row[2], sizeof(host.name));

			ZBX_STR2UINT64(httptest.httptest.httptestid, row[3]);
			httptest.httptest.name = row[4];

			if (SUCCEED != httptest_load_pairs(&host, &httptest))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot process web scenario \"%s\" on host \"%s\": "
						"cannot load web scenario data", httptest.httptest.name, host.name);
				zbx_db_free_result(result);
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			httptest.httptest.agent = zbx_strdup(NULL, row[5]);
			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, &httptest.httptest.agent, ZBX_MACRO_TYPE_COMMON, NULL, 0);

			if (HTTPTEST_AUTH_NONE != (httptest.httptest.authentication = atoi(row[6])))
			{
				httptest.httptest.http_user = zbx_strdup(NULL, row[7]);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &httptest.httptest.http_user,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);

				httptest.httptest.http_password = zbx_strdup(NULL, row[8]);
				zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL,
						NULL, NULL, NULL, NULL, NULL, &httptest.httptest.http_password,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
			}

			if ('\0' != *row[9])
			{
				httptest.httptest.http_proxy = zbx_strdup(NULL, row[9]);
				zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
						NULL, NULL, NULL, NULL, &httptest.httptest.http_proxy,
						ZBX_MACRO_TYPE_COMMON, NULL, 0);
			}
			else
				httptest.httptest.http_proxy = NULL;

			httptest.httptest.retries = atoi(row[10]);

			httptest.httptest.ssl_cert_file = zbx_strdup(NULL, row[11]);
			httptest.httptest.ssl_key_file = zbx_strdup(NULL, row[12]);

			um_handle = zbx_dc_open_user_macros();
			zbx_substitute_macros(&httptest.httptest.ssl_cert_file, NULL, 0, &macro_httptest_field_resolv,
					um_handle, &host);
			zbx_substitute_macros(&httptest.httptest.ssl_key_file, NULL, 0, &macro_httptest_field_resolv,
					um_handle, &host);
			zbx_dc_close_user_macros(um_handle);

			httptest.httptest.ssl_key_password = zbx_strdup(NULL, row[13]);
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, &httptest.httptest.ssl_key_password,
					ZBX_MACRO_TYPE_COMMON, NULL, 0);

			httptest.httptest.verify_peer = atoi(row[14]);
			httptest.httptest.verify_host = atoi(row[15]);

			httptest.httptest.delay = row[16];

			/* add httptest variables to the current test macro cache */
			http_process_variables(&httptest, &httptest.variables, NULL, NULL);

			process_httptest(&host, &httptest, &delay, config_source_ip, config_ssl_ca_location,
					config_ssl_cert_location, config_ssl_key_location);
			zbx_dc_httptest_queue(now, httptestid, delay);

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

			/* clear the macro cache used in this HTTP test */
			httptest_remove_macros(&httptest);

			httptests_count++;	/* performance metric */
		}
		zbx_db_free_result(result);
	}
	while (ZBX_IS_RUNNING() && SUCCEED == zbx_dc_httptest_next(now, &httptestid, nextcheck));

	/* destroy the macro cache used in HTTP tests */
	zbx_vector_ptr_pair_destroy(&httptest.macros);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return httptests_count;
}
