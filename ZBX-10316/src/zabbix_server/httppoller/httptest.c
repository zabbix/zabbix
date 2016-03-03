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

#include "httpmacro.h"
#include "httptest.h"

typedef struct
{
	char	*data;
	size_t	allocated;
	size_t	offset;
}
ZBX_HTTPPAGE;

typedef struct
{
	long   	rspcode;
	double 	total_time;
	double 	speed_download;
	double	test_total_time;
	int	test_last_step;
}
ZBX_HTTPSTAT;

extern int	CONFIG_HTTPPOLLER_FORKS;

#ifdef HAVE_LIBCURL

static ZBX_HTTPPAGE	page;

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

static void	process_test_data(zbx_uint64_t httptestid, int lastfailedstep, double speed_download,
		const char *err_str, zbx_timespec_t *ts)
{
	const char	*__function_name = "process_test_data";

	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	types[3], statuses[3];
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

		items[i].status = ITEM_STATUS_ACTIVE;
		dc_add_history(items[i].itemid, items[i].value_type, 0, &value, ts,
				items[i].status, NULL, 0, NULL, 0, 0, 0, 0);

		statuses[i] = items[i].status;
		lastclocks[i] = ts->sec;

		free_result(&value);
	}

	DCrequeue_items(itemids, statuses, lastclocks, errcodes, num);

	DCconfig_clean_items(items, errcodes, num);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	process_step_data(zbx_uint64_t httpstepid, ZBX_HTTPSTAT *stat, zbx_timespec_t *ts)
{
	const char	*__function_name = "process_step_data";

	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	types[3], statuses[3];
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

		items[i].status = ITEM_STATUS_ACTIVE;
		dc_add_history(items[i].itemid, items[i].value_type, 0, &value, ts,
				items[i].status, NULL, 0, NULL, 0, 0, 0, 0);

		statuses[i] = items[i].status;
		lastclocks[i] = ts->sec;

		free_result(&value);
	}

	DCrequeue_items(itemids, statuses, lastclocks, errcodes, num);

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
static void	process_httptest(DB_HTTPTEST *httptest)
{
	const char	*__function_name = "process_httptest";

	DB_RESULT	result;
	DB_ROW		row;
	DB_HTTPSTEP	httpstep;
	char		*err_str = NULL;
	int		lastfailedstep;
	zbx_timespec_t	ts;
	ZBX_HTTPSTAT	stat;
	double		speed_download = 0;
	int		speed_download_num = 0;
#ifdef HAVE_LIBCURL
	int		err, opt;
	char		auth[HTTPTEST_HTTP_USER_LEN_MAX + HTTPTEST_HTTP_PASSWORD_LEN_MAX];
	CURL            *easyhandle = NULL;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() httptestid:" ZBX_FS_UI64 " name:'%s'",
			__function_name, httptest->httptestid, httptest->name);

	lastfailedstep = 0;

	result = DBselect(
			"select httpstepid,no,name,url,timeout,posts,required,status_codes"
			" from httpstep"
			" where httptestid=" ZBX_FS_UI64
			" order by no",
			httptest->httptestid);

#ifdef HAVE_LIBCURL
	if (NULL == (easyhandle = curl_easy_init()))
	{
		err_str = zbx_strdup(err_str, "could not init cURL library");
		zabbix_log(LOG_LEVEL_ERR, "web scenario \"%s\" error: %s", httptest->name, err_str);
		goto clean;
	}

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_COOKIEFILE, "")) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_USERAGENT, httptest->agent)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_FOLLOWLOCATION, 1L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_WRITEFUNCTION, WRITEFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HEADERFUNCTION, HEADERFUNCTION2)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYPEER, 0L)) ||
			CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_SSL_VERIFYHOST, 0L)))
	{
		err_str = zbx_strdup(err_str, curl_easy_strerror(err));
		zabbix_log(LOG_LEVEL_ERR, "web scenario \"%s\" error: could not set cURL option [%d]: %s",
				httptest->name, opt, err_str);
		goto clean;
	}

	while (NULL != (row = DBfetch(result)))
	{
		/* NOTE: do not break or return from this block! */
		/*       process_step_data() call is required! */

		ZBX_STR2UINT64(httpstep.httpstepid, row[0]);
		httpstep.httptestid = httptest->httptestid;
		httpstep.no = atoi(row[1]);
		httpstep.name = row[2];
		httpstep.url = zbx_strdup(NULL, row[3]);
		httpstep.timeout = atoi(row[4]);
		httpstep.posts = zbx_strdup(NULL, row[5]);
		httpstep.required = row[6];
		httpstep.status_codes = row[7];

		memset(&stat, 0, sizeof(stat));

		http_substitute_macros(httptest->macros, &httpstep.url);
		http_substitute_macros(httptest->macros, &httpstep.posts);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() use step \"%s\"", __function_name, httpstep.name);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POSTFIELDS, httpstep.posts)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			zabbix_log(LOG_LEVEL_ERR, "web scenario \"%s:%s\" error:"
					" could not set cURL option [%d]: %s",
					httptest->name, httpstep.name, opt, err_str);
			goto httpstep_error;
		}

		if ('\0' != *httpstep.posts)
			zabbix_log(LOG_LEVEL_DEBUG, "%s() use post \"%s\"", __function_name, httpstep.posts);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_POST,
				'\0' != *httpstep.posts ? 1L : 0L)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			zabbix_log(LOG_LEVEL_ERR, "web scenario \"%s:%s\" error:"
					" could not set cURL option [%d]: %s",
					httptest->name, httpstep.name, opt, err_str);
			goto httpstep_error;
		}

		if (HTTPTEST_AUTH_NONE != httptest->authentication)
		{
			long	curlauth = 0;

			zabbix_log(LOG_LEVEL_DEBUG, "%s() setting HTTPAUTH [%d]",
					__function_name, httptest->authentication);
			zabbix_log(LOG_LEVEL_DEBUG, "%s() setting USERPWD for authentication", __function_name);

			switch (httptest->authentication)
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

			zbx_snprintf(auth, sizeof(auth), "%s:%s", httptest->http_user, httptest->http_password);

			if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPAUTH, curlauth)) ||
					CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_USERPWD, auth)))
			{
				err_str = zbx_strdup(err_str, curl_easy_strerror(err));
				zabbix_log(LOG_LEVEL_ERR, "web scenario step \"%s:%s\" error:"
						" could not set cURL option [%d]: %s",
						httptest->name, httpstep.name, opt, err_str);
				goto httpstep_error;
			}
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() go to URL \"%s\"", __function_name, httpstep.url);

		if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_URL, httpstep.url)) ||
				CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_TIMEOUT, (long)httpstep.timeout)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			zabbix_log(LOG_LEVEL_ERR, "web scenario step \"%s:%s\" error:"
					" could not set cURL option [%d]: %s",
					httptest->name, httpstep.name, opt, err_str);
			goto httpstep_error;
		}

		memset(&page, 0, sizeof(page));

		if (CURLE_OK != (err = curl_easy_perform(easyhandle)))
		{
			err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			zabbix_log(LOG_LEVEL_ERR, "web scenario step \"%s:%s\" error:"
					" error doing curl_easy_perform: %s",
					httptest->name, httpstep.name, err_str);
		}
		else
		{
			if ('\0' != *httpstep.required &&
					NULL == zbx_regexp_match(page.data, httpstep.required, NULL))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() required pattern \"%s\" not found on %s",
						__function_name, httpstep.required, httpstep.url);
				err_str = zbx_strdup(err_str, "Required pattern not found");
			}

			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_RESPONSE_CODE, &stat.rspcode)))
			{
				zabbix_log(LOG_LEVEL_ERR, "web scenario step \"%s:%s\" error:"
						" error getting CURLINFO_RESPONSE_CODE: %s",
						httptest->name, httpstep.name, curl_easy_strerror(err));
				if (NULL == err_str)
					err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else if ('\0' != *httpstep.status_codes &&
					FAIL == int_in_list(httpstep.status_codes, stat.rspcode))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() status code did not match [%s]",
						__function_name, httpstep.status_codes);
				if (NULL == err_str)
					err_str = zbx_strdup(err_str, "Status code did not match");
			}

			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_TOTAL_TIME, &stat.total_time)))
			{
				zabbix_log(LOG_LEVEL_ERR, "web scenario step \"%s:%s\" error:"
						" error getting CURLINFO_TOTAL_TIME: %s",
						httptest->name, httpstep.name, curl_easy_strerror(err));
				if (NULL == err_str)
					err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}

			if (CURLE_OK != (err = curl_easy_getinfo(easyhandle, CURLINFO_SPEED_DOWNLOAD, &stat.speed_download)))
			{
				zabbix_log(LOG_LEVEL_ERR, "web scenario step \"%s:%s\" error:"
						" error getting CURLINFO_SPEED_DOWNLOAD: %s",
						httptest->name, httpstep.name, curl_easy_strerror(err));
				if (NULL == err_str)
					err_str = zbx_strdup(err_str, curl_easy_strerror(err));
			}
			else
			{
				speed_download += stat.speed_download;
				speed_download_num++;
			}
		}

		zbx_free(page.data);
httpstep_error:
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

	if (0 == lastfailedstep && NULL != err_str)
	{
		/* we are here either because cURL initialization failed */
		/* or we have been compiled without cURL library */

		lastfailedstep = 1;

		if (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(httpstep.httpstepid, row[0]);

			memset(&stat, 0, sizeof(stat));

			process_step_data(httpstep.httpstepid, &stat, &ts);
		}
		else
			THIS_SHOULD_NEVER_HAPPEN;
	}
	DBfree_result(result);

	DBexecute("update httptest set nextcheck=%d+delay where httptestid=" ZBX_FS_UI64,
			ts.sec, httptest->httptestid);

	if (0 != speed_download_num)
		speed_download /= speed_download_num;

	process_test_data(httptest->httptestid, lastfailedstep, speed_download, err_str, &ts);

	zbx_free(err_str);

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
void	process_httptests(int httppoller_num, int now)
{
	const char	*__function_name = "process_httptests";

	DB_RESULT	result;
	DB_ROW		row;
	DB_HTTPTEST	httptest;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select t.httptestid,t.name,t.macros,t.agent,t.authentication,t.http_user,t.http_password"
			" from httptest t,applications a,hosts h"
			" where t.applicationid=a.applicationid"
				" and a.hostid=h.hostid"
				" and t.nextcheck<=%d"
				" and " ZBX_SQL_MOD(t.httptestid,%d) "=%d"
				" and t.status=%d"
				" and h.proxy_hostid is null"
				" and h.status=%d"
				" and (h.maintenance_status=%d or h.maintenance_type=%d)"
				DB_NODE,
			now,
			CONFIG_HTTPPOLLER_FORKS, httppoller_num - 1,
			HTTPTEST_STATUS_MONITORED,
			HOST_STATUS_MONITORED,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
			DBnode_local("t.httptestid"));

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(httptest.httptestid, row[0]);
		httptest.name = row[1];
		httptest.macros = row[2];
		httptest.agent = row[3];
		httptest.authentication = atoi(row[4]);
		httptest.http_user = row[5];
		httptest.http_password = row[6];

		process_httptest(&httptest);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
