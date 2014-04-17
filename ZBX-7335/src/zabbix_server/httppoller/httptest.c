/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifdef HAVE_LIBCURL

static zbx_httppage_t	page;

static size_t	WRITEFUNCTION2(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

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
	int i;

	for (i = 0; i < httptest->macros.values_num; i++)
	{
		zbx_ptr_pair_t	*pair = &httptest->macros.values[i];

		zbx_free(pair->first);
		zbx_free(pair->second);
	}
	httptest->macros.values_num = 0;
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
	int		lastfailedstep;
	zbx_timespec_t	ts;
	zbx_httpstat_t	stat;
	double		speed_download = 0;
	int		speed_download_num = 0;
#ifdef HAVE_LIBCURL
	int		err;
	char		auth[HTTPTEST_HTTP_USER_LEN_MAX + HTTPTEST_HTTP_PASSWORD_LEN_MAX];
	CURL            *easyhandle = NULL;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() httptestid:" ZBX_FS_UI64 " name:'%s'",
			__function_name, httptest->httptest.httptestid, httptest->httptest.name);

	lastfailedstep = 0;

	result = DBselect(
			"select httpstepid,no,name,url,timeout,posts,required,status_codes,variables"
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
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_SSL_VERIFYHOST, 0L)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		goto clean;
	}

	while (NULL != (row = DBfetch(result)))
	{
		/* NOTE: do not break or return from this block! */
		/*       process_step_data() call is required! */

		ZBX_STR2UINT64(httpstep.httpstepid, row[0]);
		httpstep.httptestid = httptest->httptest.httptestid;
		httpstep.no = atoi(row[1]);
		httpstep.name = row[2];

		httpstep.url = zbx_strdup(NULL, row[3]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL,
				&httpstep.url, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httpstep.timeout = atoi(row[4]);

		httpstep.posts = zbx_strdup(NULL, row[5]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL,
				&httpstep.posts, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httpstep.required = zbx_strdup(NULL, row[6]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, host, NULL,
				&httpstep.required, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httpstep.status_codes = zbx_strdup(NULL, row[7]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host->hostid, NULL, NULL,
				&httpstep.status_codes, MACRO_TYPE_COMMON, NULL, 0);

		httpstep.variables = row[8];

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

			zbx_snprintf(auth, sizeof(auth), "%s:%s", httptest->httptest.http_user,
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
		}
		while (0 != --httptest->httptest.retries);

		if (CURLE_OK == err)
		{
			char	*var_err_str = NULL;

			/* first get the data that is needed even if step fails */
			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, &stat.rspcode)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else if ('\0' != *httpstep.status_codes &&
					FAIL == int_in_list(httpstep.status_codes, stat.rspcode))
			{
				err_str = zbx_strdup(err_str, "status code did not match");
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

			/* required pattern */
			if (NULL == err_str && '\0' != *httpstep.required && NULL == zbx_regexp_match(page.data,
					httpstep.required, NULL))
			{
				err_str = zbx_strdup(err_str, "required pattern not found");
			}

			/* variables defined in scenario */
			if (NULL == err_str && FAIL == http_process_variables(httptest, httptest->httptest.variables,
					page.data, &var_err_str))
			{
				char	*variables;

				variables = string_replace(httptest->httptest.variables, "\r\n", " ");
				err_str = zbx_dsprintf(err_str, "error in scenario variables \"%s\": %s",
						variables, var_err_str);
				zbx_free(variables);
			}

			/* variables defined in a step */
			if (NULL == err_str && FAIL == http_process_variables(httptest, httpstep.variables, page.data,
					&var_err_str))
			{
				char	*variables;

				variables = string_replace(httpstep.variables, "\r\n", " ");
				err_str = zbx_dsprintf(err_str, "error in step variables \"%s\": %s",
						variables, var_err_str);
				zbx_free(variables);
			}

			zbx_free(var_err_str);
		}
		else
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));

		zbx_free(page.data);
httpstep_error:
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

			if (NULL != (row = DBfetch(result)))
			{
				ZBX_STR2UINT64(httpstep.httpstepid, row[0]);
				httpstep.name = row[2];

				memset(&stat, 0, sizeof(stat));

				process_step_data(httpstep.httpstepid, &stat, &ts);
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;
		}

		zabbix_log(LOG_LEVEL_WARNING, "cannot process step \"%s\" of web scenario \"%s\" on host \"%s\": %s",
				httpstep.name, httptest->httptest.name, host->name, err_str);
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
 * Return value:                                                              *
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
			"select h.hostid,h.host,h.name,t.httptestid,t.name,t.variables,t.agent,"
				"t.authentication,t.http_user,t.http_password,t.http_proxy,t.retries"
			" from httptest t,hosts h"
			" where t.hostid=h.hostid"
				" and t.nextcheck<=%d"
				" and " ZBX_SQL_MOD(t.httptestid,%d) "=%d"
				" and t.status=%d"
				" and h.proxy_hostid is null"
				" and h.status=%d"
				" and (h.maintenance_status=%d or h.maintenance_type=%d)"
				ZBX_SQL_NODE,
			now,
			CONFIG_HTTPPOLLER_FORKS, httppoller_num - 1,
			HTTPTEST_STATUS_MONITORED,
			HOST_STATUS_MONITORED,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
			DBand_node_local("t.httptestid"));

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(host.hostid, row[0]);
		strscpy(host.host, row[1]);
		strscpy(host.name, row[2]);

		ZBX_STR2UINT64(httptest.httptest.httptestid, row[3]);
		httptest.httptest.name = row[4];

		httptest.httptest.variables = zbx_strdup(NULL, row[5]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, &host, NULL,
				&httptest.httptest.variables, MACRO_TYPE_HTTPTEST_FIELD, NULL, 0);

		httptest.httptest.agent = zbx_strdup(NULL, row[6]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL,
				&httptest.httptest.agent, MACRO_TYPE_COMMON, NULL, 0);

		if (HTTPTEST_AUTH_NONE != (httptest.httptest.authentication = atoi(row[7])))
		{
			httptest.httptest.http_user = zbx_strdup(NULL, row[8]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL,
					&httptest.httptest.http_user, MACRO_TYPE_COMMON, NULL, 0);

			httptest.httptest.http_password = zbx_strdup(NULL, row[9]);
			substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL,
					&httptest.httptest.http_password, MACRO_TYPE_COMMON, NULL, 0);
		}

		httptest.httptest.http_proxy = zbx_strdup(NULL, row[10]);
		substitute_simple_macros(NULL, NULL, NULL, NULL, &host.hostid, NULL, NULL,
				&httptest.httptest.http_proxy, MACRO_TYPE_COMMON, NULL, 0);

		httptest.httptest.retries = atoi(row[11]);

		/* add httptest varriables to the current test macro cache */
		http_process_variables(&httptest, httptest.httptest.variables, NULL, NULL);

		process_httptest(&host, &httptest);

		zbx_free(httptest.httptest.http_proxy);
		if (HTTPTEST_AUTH_NONE != httptest.httptest.authentication)
		{
			zbx_free(httptest.httptest.http_password);
			zbx_free(httptest.httptest.http_user);
		}
		zbx_free(httptest.httptest.agent);
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
