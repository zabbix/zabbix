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

#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "trapper.h"
#include "proxyconfig.h"
#include "proxydiscovery.h"
#include "../discoverer/discoverer.h"

/******************************************************************************
 *                                                                            *
 * Function: process_discovery_data                                           *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	process_discovery_data(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	const char		*__function_name = "process_discovery_data";
	char			tmp[MAX_STRING_LEN];
	zbx_uint64_t		last_druleid;
	DB_DRULE		drule;
	DB_DCHECK		dcheck;
	DB_DHOST		dhost;
	struct zbx_json_parse	jp_data, jp_row;
	int			res = SUCCEED;

	int			port, status;
	const char		*p;
	char			last_ip[HOST_IP_LEN_MAX], ip[HOST_IP_LEN_MAX],
				key_[ITEM_KEY_LEN_MAX],
				value[DSERVICE_VALUE_LEN_MAX];
	time_t			now, hosttime, itemtime;
	zbx_uint64_t		proxy_hostid;
	DB_RESULT		result;
	DB_ROW			row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == get_proxy_id(jp, &proxy_hostid))
	{
		res = FAIL;
		goto exit;
	}

	now = time(NULL);

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
	{
		res = FAIL;
		goto exit;
	}

	hosttime = atoi(tmp);
	memset(&drule, 0, sizeof(drule));
	last_druleid = 0;
	*last_ip = '\0';

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		p = NULL;
		while (NULL != (p = zbx_json_next(&jp_data, p)) && SUCCEED == res)
		{
			if (FAIL == (res = zbx_json_brackets_open(p, &jp_row)))
				break;

			memset(&dcheck, 0, sizeof(dcheck));
			*key_ = '\0';
			*value = '\0';
			port = 0;
			status = 0;
			dcheck.key_ = key_;

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
				goto json_parse_error;
			itemtime = now - (hosttime - atoi(tmp));

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DRULE, tmp, sizeof(tmp)))
				goto json_parse_error;
			ZBX_STR2UINT64(drule.druleid, tmp);

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DCHECK, tmp, sizeof(tmp)))
				goto json_parse_error;
			ZBX_STR2UINT64(dcheck.dcheckid, tmp);

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_TYPE, tmp, sizeof(tmp)))
				goto json_parse_error;
			dcheck.type = atoi(tmp);

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
				goto json_parse_error;

			if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
				port = atoi(tmp);

			zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, key_, sizeof(key_));
			zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_VALUE, value, sizeof(value));

			if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_STATUS, tmp, sizeof(tmp)))
				status = atoi(tmp);

			if (0 == last_druleid || drule.druleid != last_druleid)
			{
				result = DBselect(
						"select unique_dcheckid"
						" from drules"
						" where druleid=" ZBX_FS_UI64,
						drule.druleid);

				if (NULL != (row = DBfetch(result)))
					ZBX_STR2UINT64(drule.unique_dcheckid, row[0]);
				DBfree_result(result);

				last_druleid = drule.druleid;
			}

			if ('\0' == *last_ip || 0 != strcmp(ip, last_ip))
			{
				memset(&dhost, 0, sizeof(dhost));
				zbx_strlcpy(last_ip, ip, HOST_IP_LEN_MAX);
			}

			zabbix_log(LOG_LEVEL_DEBUG, "%s() druleid:" ZBX_FS_UI64 " dcheckid:" ZBX_FS_UI64  " unique_dcheckid:" ZBX_FS_UI64
					" type:%d time:'%s %s' ip:'%s' port:%d key:'%s' value:'%s'",
					__function_name, drule.druleid, dcheck.dcheckid, drule.unique_dcheckid, dcheck.type,
					zbx_date2str(itemtime), zbx_time2str(itemtime),
					ip, port, dcheck.key_, value);

			DBbegin();
			if (dcheck.type == -1)
				update_host(&dhost, ip, status, itemtime);
			else
				update_service(&drule, &dcheck, &dhost, ip, port, status, value, itemtime);
			DBcommit();

			continue;
json_parse_error:
			zabbix_log(LOG_LEVEL_WARNING, "Invalid discovery data. %s",
					zbx_json_strerror());
			zabbix_syslog("Invalid discovery data. %s",
					zbx_json_strerror());
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Invalid discovery data. %s",
				zbx_json_strerror());
		zabbix_syslog("Invalid discovery data. %s",
				zbx_json_strerror());
	}
exit:
	if (SUCCEED != send_result(sock, res, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Error sending result back");
		zabbix_syslog("Trapper: error sending result back");
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}
