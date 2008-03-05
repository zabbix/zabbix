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
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	process_discovery_data(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	char			tmp[MAX_STRING_LEN],
				host_esc[MAX_STRING_LEN];
	DB_RESULT		result;
	DB_DCHECK		check;
	DB_DHOST		host;
	DB_DSERVICE		service;
	struct zbx_json_parse	jp_data, jp_row;
	int			res = SUCCEED;

	int			port;
	const char		*p;
	char			ip[HOST_IP_LEN_MAX],
				key_[ITEM_KEY_LEN_MAX];
	time_t			now, hosttime, itemtime;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_discovery_data()");

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PROXY, tmp, sizeof(tmp))) {
		zabbix_log(LOG_LEVEL_WARNING, "Invalid discovery data. %s",
				zbx_json_strerror());
		zabbix_syslog("Invalid discovery data. %s",
				zbx_json_strerror());

		res = FAIL;
		goto exit;
	}

	now = time(NULL);
	
	DBescape_string(tmp, host_esc, MAX_STRING_LEN);
	result = DBselect("select proxyid from proxies where name='%s'" DB_NODE,
		host_esc,
		DBnode_local("proxyid"));

	if (NULL == DBfetch(result)) {
		zabbix_log(LOG_LEVEL_WARNING, "Hostname \"%s\" is unknown",
				tmp);
		zabbix_syslog("Hostname \"%s\" is unknown",
				tmp);

		res = FAIL;
	}
	DBfree_result(result);

	if (FAIL == res)
		goto exit;

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

			memset(&host, 0, sizeof(host));
			memset(&check, 0, sizeof(check));
			memset(&service, 0, sizeof(service));

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
				goto json_parse_error;
			itemtime = now - (hosttime - atoi(tmp));

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_DRULE, tmp, sizeof(tmp)))
				goto json_parse_error;
			check.druleid = zbx_atoui64(tmp);

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_TYPE, tmp, sizeof(tmp)))
				goto json_parse_error;
			check.type = atoi(tmp);

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_IP, ip, sizeof(ip)))
				goto json_parse_error;

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_PORT, tmp, sizeof(tmp)))
				goto json_parse_error;
			port = atoi(tmp);

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, key_, sizeof(key_)))
				goto json_parse_error;
			check.key_ = key_;

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_VALUE, check.value, sizeof(check.value)))
				goto json_parse_error;

			if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_STATUS, tmp, sizeof(tmp)))
				goto json_parse_error;
			check.status = atoi(tmp);

			register_host(&host, &check, ip);

			if (host.dhostid > 0)
				register_service(&service, &check, host.dhostid, ip, port);

			if (service.dserviceid == 0)
				continue;

			host.status	= check.status;
			service.status	= check.status;
			if (check.status == DOBJECT_STATUS_UP) {
				/* Update host status */
				if (host.status == DOBJECT_STATUS_DOWN || host.lastup == 0) {
					host.lastdown		= 0;
					host.lastup		= itemtime;
					update_dhost(&host);
				}
				/* Update service status */
				if (service.status == DOBJECT_STATUS_DOWN || service.lastup == 0) {
					service.lastdown	= 0;
					service.lastup		= itemtime;
					update_dservice(&service);
				}
			} else { /* DOBJECT_STATUS_DOWN */
				if (host.status == DOBJECT_STATUS_UP || host.lastdown == 0) {
					host.lastdown		= itemtime;
					host.lastup		= 0;
					update_dhost(&host);
				}
				/* Update service status */
				if (service.status == DOBJECT_STATUS_UP || service.lastdown == 0) {
					service.lastdown	= itemtime;
					service.lastup		= 0;
					update_dservice(&service);
				}
			}
			add_service_event(&service);

			continue;
json_parse_error:
			zabbix_log(LOG_LEVEL_WARNING, "Invalid discovery data. %s",
					zbx_json_strerror());
			zabbix_syslog("Invalid discovery data. %s",
					zbx_json_strerror());
		}
	} else  {
		zabbix_log(LOG_LEVEL_WARNING, "Invalid discovery data. %s",
				zbx_json_strerror());
		zabbix_syslog("Invalid discovery data. %s",
				zbx_json_strerror());
	}
exit:
	if (SUCCEED != send_result(sock, res, NULL)) {
		zabbix_log(LOG_LEVEL_WARNING, "Error sending result back");
		zabbix_syslog("Trapper: error sending result back");
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of process_discovery_data():%s",
			res == SUCCEED ? "SUCCEED" : "FAIL");
	
	return res;
}

