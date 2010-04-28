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
#include "proxyautoreg.h"

/******************************************************************************
 *                                                                            *
 * Function: process_autoreg_data                                             *
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
int	process_autoreg_data(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	char			tmp[MAX_STRING_LEN];
	struct zbx_json_parse	jp_data, jp_row;
	int			res = SUCCEED;

	const char		*p;
	char			host[HOST_HOST_LEN_MAX];
	time_t			now, hosttime, itemtime;
	zbx_uint64_t		proxy_hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_autoreg_data()");

	if (FAIL == get_proxy_id(jp, &proxy_hostid)) {
		res = FAIL;
		goto exit;
	}

	now = time(NULL);

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp))) {
		res = FAIL;
		goto exit;
	}

	hosttime = atoi(tmp);

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data)) {
		p = NULL;
		while (NULL != (p = zbx_json_next(&jp_data, p)) && SUCCEED == res) {
			if (FAIL == (res = zbx_json_brackets_open(p, &jp_row)))
				break;

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
				goto json_parse_error;
			itemtime = now - (hosttime - atoi(tmp));

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOST, host, sizeof(host)))
				goto json_parse_error;

			DBbegin();
			DBregister_host(proxy_hostid, host, itemtime);
			DBcommit();
			continue;
json_parse_error:
			zabbix_log(LOG_LEVEL_WARNING, "Invalid auto registration data. %s",
					zbx_json_strerror());
			zabbix_syslog("Invalid auto registration data. %s",
					zbx_json_strerror());
		}
	} else  {
		zabbix_log(LOG_LEVEL_WARNING, "Invalid auto registration data. %s",
				zbx_json_strerror());
		zabbix_syslog("Invalid auto registration data. %s",
				zbx_json_strerror());
	}
exit:
	if (SUCCEED != send_result(sock, res, NULL)) {
		zabbix_log(LOG_LEVEL_WARNING, "Error sending result back");
		zabbix_syslog("Trapper: error sending result back");
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of process_autoreg_data():%s",
			zbx_result_string(res));

	return res;
}
