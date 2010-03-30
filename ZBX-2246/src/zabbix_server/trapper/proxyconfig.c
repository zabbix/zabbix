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

#include "proxyconfig.h"

/******************************************************************************
 *                                                                            *
 * Function: get_proxyconfig_table                                            *
 *                                                                            *
 * Purpose: prepare proxy configuration data                                  *
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
static int	get_proxyconfig_table(zbx_uint64_t proxy_hostid, struct zbx_json *j, const ZBX_TABLE *table,
		const char *condition)
{
	char		sql[MAX_STRING_LEN];
	int		offset = 0, f, fld;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_proxyconfig_table() [proxy_hostid:" ZBX_FS_UI64 "] [table:%s]",
			proxy_hostid,
			table->table);

	zbx_json_addobject(j, table->table);
	zbx_json_addarray(j, "fields");

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select t.%s",
			table->recid);

	zbx_json_addstring(j, NULL, table->recid, ZBX_JSON_TYPE_STRING);

	for (f = 0; table->fields[f].name != 0; f ++) {
		if ((table->fields[f].flags & ZBX_PROXY) == 0)
			continue;

		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",t.%s",
				table->fields[f].name);

		zbx_json_addstring(j, NULL, table->fields[f].name, ZBX_JSON_TYPE_STRING);
	}

	zbx_json_close(j);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s t%s",
			table->table,
			condition);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " order by t.%s",
			table->recid);

	zbx_json_addarray(j, "data");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result))) {
		fld = 0;
		zbx_json_addarray(j, NULL);
		zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_INT);

		for (f = 0; table->fields[f].name != 0; f ++) {
			if ((table->fields[f].flags & ZBX_PROXY) == 0)
				continue;

			switch (table->fields[f].type) {
			case ZBX_TYPE_INT:
			case ZBX_TYPE_UINT:
			case ZBX_TYPE_ID:
				zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_INT);
				break;
			default:
				zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_STRING);
				break;
			}
		}
		zbx_json_close(j);
	}
	DBfree_result(result);

	zbx_json_close(j);
	zbx_json_close(j);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_proxyconfig_data                                             *
 *                                                                            *
 * Purpose: prepare proxy configuration data                                  *
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
static int	get_proxyconfig_data(zbx_uint64_t proxy_hostid, struct zbx_json *j)
{
	struct proxytable_t {
		const char	*table;
	};

	static const struct proxytable_t pt[]={
		{"hosts"},
		{"items"},
		{"hosts_templates"},
		{"globalmacro"},
		{"hostmacro"},
		{"drules"},
		{"dchecks"},
		{NULL}
	};
	int		i, ret = SUCCEED;
	const ZBX_TABLE	*table;
	char		condition[512];

	zabbix_log(LOG_LEVEL_DEBUG, "In get_proxyconfig_data() [proxy_hostid:" ZBX_FS_UI64 "]",
			proxy_hostid);

	for (i = 0; pt[i].table != NULL; i++)
	{
		if (NULL == (table = DBget_table(pt[i].table)))
			continue;

		if (0 == strcmp(pt[i].table, "hosts"))
		{
			zbx_snprintf(condition, sizeof(condition),
					" where t.proxy_hostid=" ZBX_FS_UI64
						" and t.status=%d",
					proxy_hostid,
					HOST_STATUS_MONITORED);
		}
		else if (0 == strcmp(pt[i].table, "items"))
		{
			zbx_snprintf(condition, sizeof(condition),
					", hosts r where t.hostid=r.hostid"
						" and r.proxy_hostid=" ZBX_FS_UI64
						" and r.status=%d"
						" and t.status in (%d,%d)"
						" and t.type in (%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)",
					proxy_hostid,
					HOST_STATUS_MONITORED,
					ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED,
					ITEM_TYPE_ZABBIX, ITEM_TYPE_ZABBIX_ACTIVE,
					ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3,
					ITEM_TYPE_IPMI, ITEM_TYPE_TRAPPER, ITEM_TYPE_SIMPLE,
					ITEM_TYPE_HTTPTEST, ITEM_TYPE_EXTERNAL, ITEM_TYPE_DB_MONITOR,
					ITEM_TYPE_SSH, ITEM_TYPE_TELNET);
		}
		else if (0 == strcmp(pt[i].table, "hosts_templates"))
		{
			zbx_snprintf(condition, sizeof(condition),
					", hosts r where t.hostid=r.hostid"
						" and r.proxy_hostid=" ZBX_FS_UI64
						" and r.status=%d",
					proxy_hostid,
					HOST_STATUS_MONITORED);
		}
		else if (0 == strcmp(pt[i].table, "drules"))
		{
			zbx_snprintf(condition, sizeof(condition),
					" where t.proxy_hostid=" ZBX_FS_UI64
						" and t.status=%d",
					proxy_hostid,
					DRULE_STATUS_MONITORED);
		}
		else if (0 == strcmp(pt[i].table, "dchecks"))
		{
			zbx_snprintf(condition, sizeof(condition),
					", drules r where t.druleid=r.druleid"
						" and r.proxy_hostid=" ZBX_FS_UI64
						" and r.status=%d",
					proxy_hostid,
					DRULE_STATUS_MONITORED);
		}
		else if (0 == strcmp(pt[i].table, "hostmacro"))
		{
			zbx_snprintf(condition, sizeof(condition),
					" where t.hostid in ("
							"select hostid"
							" from hosts"
							" where proxy_hostid=" ZBX_FS_UI64
								" and status=%d"
							")"
						" or t.hostid in ("
							"select t.templateid"
							" from hosts_templates t,hosts h"
							" where h.hostid=t.hostid"
								" and h.proxy_hostid=" ZBX_FS_UI64
								" and h.status=%d"
							")",
					proxy_hostid,
					HOST_STATUS_MONITORED,
					proxy_hostid,
					HOST_STATUS_MONITORED);
		}
		else
			*condition = '\0';

		ret = get_proxyconfig_table(proxy_hostid, j, table, condition);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_proxy_id                                                     *
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
int	get_proxy_id(struct zbx_json_parse *jp, zbx_uint64_t *hostid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		host[HOST_HOST_LEN_MAX], host_esc[MAX_STRING_LEN];
	int		res = FAIL;

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOST, host, sizeof(host))) {
		DBescape_string(host, host_esc, sizeof(host_esc));

		result = DBselect("select hostid from hosts where host='%s'"
				" and status in (%d)" DB_NODE,
				host_esc,
				HOST_STATUS_PROXY,
				DBnode_local("hostid"));

		if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0])) {
			*hostid	= zbx_atoui64(row[0]);
			res	= SUCCEED;
		} else
			zabbix_log(LOG_LEVEL_WARNING, "Unknown proxy \"%s\"",
					host);

		DBfree_result(result);
	} else {
		zabbix_log(LOG_LEVEL_WARNING, "Incorrect data. %s",
				zbx_json_strerror());
		zabbix_syslog("Incorrect data. %s",
				zbx_json_strerror());
	}

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: update_proxy_lastaccess                                          *
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
void	update_proxy_lastaccess(const zbx_uint64_t hostid)
{
	DBexecute("update hosts set lastaccess=%d where hostid=" ZBX_FS_UI64,
			time(NULL),
			hostid);
}

/******************************************************************************
 *                                                                            *
 * Function: send_proxyconfig                                                 *
 *                                                                            *
 * Purpose: send configuration tables to the proxy                            *
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
int	send_proxyconfig(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	zbx_uint64_t	proxy_hostid;
	struct zbx_json	j;
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_proxyconfig()");

	if (FAIL == get_proxy_id(jp, &proxy_hostid))
		goto exit;

	update_proxy_lastaccess(proxy_hostid);

	zbx_json_init(&j, 512*1024);
	if (SUCCEED == (res = get_proxyconfig_data(proxy_hostid, &j))) {
		zabbix_log(LOG_LEVEL_WARNING, "Sending configuration data to proxy. Datalen %d",
				(int)j.buffer_size);
		zabbix_log(LOG_LEVEL_DEBUG, "%s",
				j.buffer);

		alarm(CONFIG_TIMEOUT);
		if (FAIL == (res = zbx_tcp_send(sock, j.buffer)))
			zabbix_log(LOG_LEVEL_WARNING, "Error while sending configuration. %s",
					zbx_tcp_strerror());
		alarm(0);
	}
	zbx_json_free(&j);
exit:
	return res;
}
