/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "zbxserver.h"
#include "httpmacro.h"
#include "httptest.h"
#include "zbxregexp.h"

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
zbx_httppage_t;

typedef struct
{
	long   	rspcode;
	double 	total_time;
	double 	speed_download;
	double	test_total_time;
	int	test_last_step;
}
zbx_httpstat_t;

extern int	CONFIG_HTTPPOLLER_FORKS;
extern char	*CONFIG_SOURCE_IP;

#ifdef HAVE_LIBCURL

extern char	*CONFIG_SSL_CA_LOCATION;
extern char	*CONFIG_SSL_CERT_LOCATION;
extern char	*CONFIG_SSL_KEY_LOCATION;

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
		page.data = zbx_malloc(page.data, page.allocated);
	}

	zbx_strncpy_alloc(&page.data, &page.allocated, &page.offset, ptr, r_size);

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
	unsigned char	types[3], states[3];
	DC_ITEM		items[3];
	zbx_uint64_t	itemids[3];
	int		lastclocks[3], errcodes[3];
	size_t		i, num = 0;
	AGENT_RESULT    value;

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
		dc_add_history(items[i].itemid, items[i].value_type, 0, &value, ts, items[i].state, NULL);

		states[i] = items[i].state;
		lastclocks[i] = ts->sec;

		free_result(&value);
	}

	DCrequeue_items(itemids, states, lastclocks, NULL, NULL, errcodes, num);

	DCconfig_clean_items(items, errcodes, num);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	process_step_data(zbx_uint64_t httpstepid, zbx_httpstat_t *stat, zbx_timespec_t *ts)
{
	const char	*__function_name = "process_step_data";

	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	types[3], states[3];
	DC_ITEM		items[3];
	zbx_uint64_t	itemids[3];
	int		lastclocks[3], errcodes[3];
	size_t		i, num = 0;
	AGENT_RESULT    value;

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
		dc_add_history(items[i].itemid, items[i].value_type, 0, &value, ts, items[i].state, NULL);

		states[i] = items[i].state;
		lastclocks[i] = ts->sec;

		free_result(&value);
	}

	DCrequeue_items(itemids, states, lastclocks, NULL, NULL, errcodes, num);

	DCconfig_clean_items(items, errcodes, num);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

#ifdef HAVE_LIBCURL
static void	add_headers(char *headers, struct curl_slist **headers_slist)
{
	char      *p_begin;

	p_begin = headers;

	while ('\0' != *p_begin)
	{
		char    c, *p_end, *line;

		while ('\r' == *p_begin || '\n' == *p_begin)
			p_begin++;

		p_end = p_begin;

		while ('\0' != *p_end && '\r' != *p_end && '\n' != *p_end)
			p_end++;

		if (p_begin == p_end)
			break;

		if ('\0' != (c = *p_end))
			*p_end = '\0';
		line = zbx_strdup(NULL, p_begin);
		if ('\0' != c)
			*p_end = c;

		zbx_lrtrim(line, " \t");
		if ('\0' != *line)
			*headers_slist = curl_slist_append(*headers_slist, line);
		zbx_free(line);

		p_begin = p_end;
	}
}
#endif

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
	DB_ROW		row;
	DB_HTTPSTEP	httpstep;
	char		*err_str = NULL;
	int		lastfailedstep = 0;
	zbx_timespec_t	ts;
	zbx_httpstat_t	stat;
	double		speed_download = 0;
	int		speed_download_num = 0;
#ifdef HAVE_LIBCURL
	int		err;
	char		*auth = NULL, errbuf[CURL_ERROR_SIZE];
	size_t		auth_alloc = 0, auth_offset;
	CURL            *easyhandle = NULL;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() httptestid:" ZBX_FS_UI64 " name:'%s'",
			__function_name, httptest->httptest.httptestid, httptest->httptest.name);

	result = DBselect(
			"select httpstepid,no,name,url,timeout,posts,required,status_codes,variables,follow_redirects,"
				"retrieve_mode,headers"
			" from httpstep"
			" where httptestid=" ZBX_FS_UI64
			" order by no",
			httptest->httptest.httptestid);

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
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYPEER,
					0 == httptest->httptest.verify_peer ? 0L : 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYHOST,
					0 == httptest->httptest.verify_host ? 0L : 2L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_ERRORBUFFER, errbuf)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		goto clean;
	}

	if (NULL != CONFIG_SOURCE_IP)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_INTERFACE, CONFIG_SOURCE_IP)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	if (0 != httptest->httptest.verify_peer && NULL != CONFIG_SSL_CA_LOCATION)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_CAPATH, CONFIG_SSL_CA_LOCATION)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	if ('\0' != *httptest->httptest.ssl_cert_file)
	{
		char	*file_name;

		file_name = zbx_dsprintf(NULL, "%s/%s", CONFIG_SSL_CERT_LOCATION, httptest->httptest.ssl_cert_file);
		zabbix_log(LOG_LEVEL_DEBUG, "using SSL certificate file: '%s'", file_name);

		err = curl_easy_setopt(easyhandle, CURLOPT_SSLCERT, file_name);
		zbx_free(file_name);

		if (CURLE_OK != err || CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSLCERTTYPE, "PEM")))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	if ('\0' != *httptest->httptest.ssl_key_file)
	{
		char	*file_name;

		file_name = zbx_dsprintf(NULL, "%s/%s", CONFIG_SSL_KEY_LOCATION, httptest->httptest.ssl_key_file);
		zabbix_log(LOG_LEVEL_DEBUG, "using SSL private key file: '%s'", file_name);

		err = curl_easy_setopt(easyhandle, CURLOPT_SSLKEY, file_name);
		zbx_free(file_name);

		if (CURLE_OK != err || CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSLKEYTYPE, "PEM")))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	if ('\0' != httptest->httptest.ssl_key_password)
	{
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_KEYPASSWD,
				httptest->httptest.ssl_key_password)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto clean;
		}
	}

	while (NULL != (row = DBfetch(result)))
	{
		struct curl_slist	*headers_slist = NULL;

		/* NOTE: do not break or return from this block! */
		/*       process_step_data() call is required! */

		ZBX_STR2UINT64(httpstep.httpstepid, row[0]);
		httpstep.httptestid = httptest->httptest.httptestid;
		httpstep.no = atoi(row[1]);
		httpstep.name = row[2];

		httpstep.url = zbx_strdup(NULL, row[3]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL,
				&httpstep.url, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httpstep.timeout = atoi(row[4]);

		httpstep.posts = zbx_strdup(NULL, row[5]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL,
				&httpstep.posts, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httpstep.required = zbx_strdup(NULL, row[6]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL,
				&httpstep.required, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httpstep.status_codes = zbx_strdup(NULL, row[7]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL, NULL,
				&httpstep.status_codes, MACRO_TYPE_COMMON, NULL, 0);

		httpstep.variables = row[8];
		httpstep.follow_redirects = atoi(row[9]);
		httpstep.retrieve_mode = atoi(row[10]);

		httpstep.headers = zbx_strdup(NULL, row[11]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL, NULL,
				&httpstep.headers, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		memset(&stat, 0, sizeof(stat));

		http_substitute_variables(httptest, &httpstep.url);
		http_substitute_variables(httptest, &httpstep.posts);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() use step \"%s\"", __function_name, httpstep.name);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, httpstep.posts)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if ('\0' != *httpstep.posts)
			zabbix_log(LOG_LEVEL_DEBUG, "%s() use post \"%s\"", __function_name, httpstep.posts);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POST, '\0' != *httpstep.posts ? 1L : 0L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_FOLLOWLOCATION,
				0 == httpstep.follow_redirects ? 0L : 1L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (0 != httpstep.follow_redirects)
		{
			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_MAXREDIRS, ZBX_CURLOPT_MAXREDIRS)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
				goto httpstep_error;
			}
		}

		http_substitute_variables(httptest, &httpstep.headers);

		/* headers defined in a step overwrite headers defined in scenario */
		if ('\0' != *httpstep.headers)
			add_headers(httpstep.headers, &headers_slist);
		else if ('\0' != *httptest->httptest.headers)
			add_headers(httptest->httptest.headers, &headers_slist);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPHEADER, headers_slist)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		/* enable/disable fetching the body */
		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_NOBODY,
				ZBX_RETRIEVE_MODE_HEADERS == httpstep.retrieve_mode ? 1L : 0L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			goto httpstep_error;
		}

		if (HTTPTEST_AUTH_NONE != httptest->httptest.authentication)
		{
			long	curlauth = 0;

			zabbix_log(LOG_LEVEL_DEBUG, "%s() setting HTTPAUTH [%d]",
					__function_name, httptest->httptest.authentication);
			zabbix_log(LOG_LEVEL_DEBUG, "%s() setting USERPWD for authentication", __function_name);

			switch (httptest->httptest.authentication)
			{
				case HTTPTEST_AUTH_BASIC:
					curlauth = CURLAUTH_BASIC;
					break;
				case HTTPTEST_AUTH_NTLM:
					curlauth = CURLAUTH_NTLM;
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					break;
			}

			auth_offset = 0;
			zbx_snprintf_alloc(&auth, &auth_alloc, &auth_offset, "%s:%s", httptest->httptest.http_user,
					httptest->httptest.http_password);

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HTTPAUTH, curlauth)) ||
					CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_USERPWD, auth)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
				goto httpstep_error;
			}
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() go to URL \"%s\"", __function_name, httpstep.url);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_TIMEOUT, (long)httpstep.timeout)) ||
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
			zabbix_log(LOG_LEVEL_TRACE, "%s() page.data from %s:'%s'", __function_name, httpstep.url, page.data);

			/* first get the data that is needed even if step fails */
			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, &stat.rspcode)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else if ('\0' != *httpstep.status_codes &&
					FAIL == int_in_list(httpstep.status_codes, stat.rspcode))
			{
				err_str = zbx_dsprintf(err_str, "response code \"%ld\" did not match any of the"
						" required status codes \"%s\"", stat.rspcode, httpstep.status_codes);
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

			if (ZBX_RETRIEVE_MODE_CONTENT == httpstep.retrieve_mode)
			{
				char	*var_err_str = NULL;

				/* required pattern */
				if (NULL == err_str && '\0' != *httpstep.required && NULL == zbx_regexp_match(page.data,
						httpstep.required, NULL))
				{
					err_str = zbx_dsprintf(err_str, "required pattern \"%s\" was not found on %s",
							httpstep.required, httpstep.url);
				}

				/* variables defined in scenario */
				if (NULL == err_str && FAIL == http_process_variables(httptest,
						httptest->httptest.variables, page.data, &var_err_str))
				{
					char	*variables;

					variables = string_replace(httptest->httptest.variables, "\r\n", " ");
					err_str = zbx_dsprintf(err_str, "error in scenario variables \"%s\": %s",
							variables, var_err_str);

					zbx_free(variables);
				}

				/* variables defined in a step */
				if (NULL == err_str && FAIL == http_process_variables(httptest, httpstep.variables,
						page.data, &var_err_str))
				{
					char	*variables;

					variables = string_replace(httpstep.variables, "\r\n", " ");
					err_str = zbx_dsprintf(err_str, "error in step variables \"%s\": %s",
							variables, var_err_str);

					zbx_free(variables);
				}

				zbx_free(var_err_str);
			}
			zbx_free(page.data);
		}
		else
			err_str = zbx_dsprintf(err_str, "%s: %s", curl_easy_strerror(err), errbuf);

httpstep_error:
		zbx_free(httpstep.headers);
		zbx_free(httpstep.status_codes);
		zbx_free(httpstep.required);
		zbx_free(httpstep.posts);
		zbx_free(httpstep.url);

		zbx_timespec(&ts);
		process_step_data(httpstep.httpstepid, &stat, &ts);

		if (NULL != err_str)
		{
			lastfailedstep = httpstep.no;
			break;
		}
	}

	zbx_free(auth);
clean:
	curl_easy_cleanup(easyhandle);
#else
	err_str = zbx_strdup(err_str, "cURL library is required for Web monitoring support");
#endif	/* HAVE_LIBCURL */

	zbx_timespec(&ts);

	if (NULL != err_str)
	{
		if (0 == lastfailedstep)
		{
			/* we are here either because cURL initialization failed */
			/* or we have been compiled without cURL library */

			lastfailedstep = 1;

			/* we don't have name of the step, try to fetch it */

			if (NULL != (row = DBfetch(result)))
			{
				ZBX_STR2UINT64(httpstep.httpstepid, row[0]);
				httpstep.name = row[2];

				memset(&stat, 0, sizeof(stat));

				process_step_data(httpstep.httpstepid, &stat, &ts);
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot process web scenario \"%s\" on host \"%s\": %s",
						httptest->httptest.name, host->name, err_str);
				httpstep.name = NULL;
				THIS_SHOULD_NEVER_HAPPEN;
			}
		}

		if (NULL != httpstep.name)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot process step \"%s\" of web scenario \"%s\" on host \"%s\""
					": %s", httpstep.name, httptest->httptest.name, host->name, err_str);
		}
	}
	DBfree_result(result);

	DBexecute("update httptest set nextcheck=%d+delay where httptestid=" ZBX_FS_UI64,
			ts.sec, httptest->httptest.httptestid);

	if (0 != speed_download_num)
		speed_download /= speed_download_num;

	process_test_data(httptest->httptest.httptestid, lastfailedstep, speed_download, err_str, &ts);

	zbx_free(err_str);

	dc_flush_history();

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
			"select h.hostid,h.host,h.name,t.httptestid,t.name,t.variables,t.headers,t.agent,"
				"t.authentication,t.http_user,t.http_password,t.http_proxy,t.retries,t.ssl_cert_file,"
				"t.ssl_key_file,t.ssl_key_password,t.verify_peer,t.verify_host"
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

		httptest.httptest.variables = zbx_strdup(NULL, row[5]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL,
				&httptest.httptest.variables, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httptest.httptest.headers = zbx_strdup(NULL, row[6]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL,
				&httptest.httptest.headers, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httptest.httptest.agent = zbx_strdup(NULL, row[7]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
				&httptest.httptest.agent, MACRO_TYPE_COMMON, NULL, 0);

		if (HTTPTEST_AUTH_NONE != (httptest.httptest.authentication = atoi(row[8])))
		{
			httptest.httptest.http_user = zbx_strdup(NULL, row[9]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
					&httptest.httptest.http_user, MACRO_TYPE_COMMON, NULL, 0);

			httptest.httptest.http_password = zbx_strdup(NULL, row[10]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
					&httptest.httptest.http_password, MACRO_TYPE_COMMON, NULL, 0);
		}

		if ('\0' != *row[11])
		{
			httptest.httptest.http_proxy = zbx_strdup(NULL, row[11]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
					&httptest.httptest.http_proxy, MACRO_TYPE_COMMON, NULL, 0);
		}
		else
			httptest.httptest.http_proxy = NULL;

		httptest.httptest.retries = atoi(row[12]);

		httptest.httptest.ssl_cert_file = zbx_strdup(NULL, row[13]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL,
				&httptest.httptest.ssl_cert_file, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httptest.httptest.ssl_key_file = zbx_strdup(NULL, row[14]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL, NULL,
				&httptest.httptest.ssl_key_file, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httptest.httptest.ssl_key_password = zbx_strdup(NULL, row[15]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL, NULL,
				&httptest.httptest.ssl_key_password, MACRO_TYPE_COMMON, NULL, 0);

		httptest.httptest.verify_peer = atoi(row[16]);
		httptest.httptest.verify_host = atoi(row[17]);

		/* add httptest variables to the current test macro cache */
		http_process_variables(&httptest, httptest.httptest.variables, NULL, NULL);

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
		zbx_free(httptest.httptest.headers);
		zbx_free(httptest.httptest.variables);

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
