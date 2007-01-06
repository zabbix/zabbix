/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "config.h"

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "common.h"
#include "httptest.h"

/******************************************************************************
 *                                                                            *
 * Function: process_httptest                                                 *
 *                                                                            *
 * Purpose: process single scenario of http test                              *
 *                                                                            *
 * Parameters: httptestid - ID of http test                                   *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: SUCCEED or FAIL                                                  *
 *                                                                            *
 ******************************************************************************/
int	process_httptest(zbx_uint64_t httptestid)
{
	DB_RESULT	result;
	DB_ROW	row;
	int	ret = SUCCEED;
	int	err;

	long	rspcode;
	double	total_time;
	double	speed_download;

	CURL            *easyhandle = NULL;

	zabbix_log(LOG_LEVEL_WARNING, "In process_httptest(httptestid:" ZBX_FS_UI64 ")", httptestid);

	easyhandle = curl_easy_init();
	if(easyhandle == NULL)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot init CURL");

		return FAIL;
	}

	result = DBselect("select httpstepid,no,name,url,timeout,posts from httpstep where httptestid=" ZBX_FS_UI64 " order by no",
				httptestid);

	while((row=DBfetch(result)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Processing step %s [%s]", row[1], row[3]);
		if(row[5][0] != 0)
		{
			if(CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_POSTFIELDS, row[5])))
			{
				zabbix_log(LOG_LEVEL_ERR, "Cannot set POST vars [%s]", curl_easy_strerror(err));
				ret = FAIL;
				break;
			}
		}
		if(CURLE_OK != (err = curl_easy_setopt(easyhandle, CURLOPT_URL, row[3])))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot set URL [%s]", curl_easy_strerror(err));
			ret = FAIL;
			break;
		}
		if(CURLE_OK != (err = curl_easy_perform(easyhandle)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Error doing curl_easy_perform [%s]", curl_easy_strerror(err));
			ret = FAIL;
			break;
		}
		if(CURLE_OK != (err = curl_easy_getinfo(easyhandle,CURLINFO_RESPONSE_CODE ,&rspcode)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Error doing curl_easy_perform [%s]", curl_easy_strerror(err));
			ret = FAIL;
			break;
		}
		if(CURLE_OK != (err = curl_easy_getinfo(easyhandle,CURLINFO_TOTAL_TIME ,&total_time)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Error doing curl_easy_perform [%s]", curl_easy_strerror(err));
			ret = FAIL;
			break;
		}
		if(CURLE_OK != (err = curl_easy_getinfo(easyhandle,CURLINFO_SPEED_DOWNLOAD ,&speed_download)))
		{
			zabbix_log(LOG_LEVEL_ERR, "Error doing curl_easy_perform [%s]", curl_easy_strerror(err));
			ret = FAIL;
			break;
		}
		zabbix_log(LOG_LEVEL_WARNING, "RSPCODE [%d]", rspcode);
		zabbix_log(LOG_LEVEL_WARNING, "Time [%f]", total_time);
		zabbix_log(LOG_LEVEL_WARNING, "Speed download [%f]", speed_download);
	}
	DBfree_result(result);

	(void)curl_easy_cleanup(easyhandle);

	return ret;
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
void process_httptests(int now)
{
	DB_RESULT	result;
	DB_ROW	row;

	zbx_uint64_t httptestid;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_httptests");

	result = DBselect("select t.httptestid from httptest t where t.status=%d and t.nextcheck<=%d and " ZBX_SQL_MOD(t.httptestid,%d) "=%d and " ZBX_COND_NODEID, HTTPTEST_STATUS_MONITORED, now, CONFIG_HTTPPOLLER_FORKS, httppoller_num-1, LOCAL_NODE("t.httptestid"));
	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(httptestid, row[0]);
		process_httptest(httptestid);

		DBexecute("update httptest set nextcheck=%d+delay where httptestid=" ZBX_FS_UI64, now, httptestid);
	}
	DBfree_result(result);
}
