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
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_proxyconfig_table(zbx_uint64_t proxyid, struct zbx_json *j, ZBX_TABLE *table, const char *reltable, const char *relfield)
{
	char		sql[MAX_STRING_LEN];
	int		offset = 0, f, fld;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_proxyconfig_table() [proxyid:"ZBX_FS_UI64"] [table:%s]",
			proxyid,
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

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s t",
			table->table);

	if (NULL == reltable)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " where t.proxyid="ZBX_FS_UI64,
				proxyid);
	else
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ", %1$s r where t.%2$s=r.%2$s and r.proxyid="ZBX_FS_UI64_NO(3),
				reltable,
				relfield,
				proxyid);

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
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_proxyconfig_data(zbx_uint64_t proxyid, struct zbx_json *j)
{
	struct proxytable_t {
		const char	*table;
		const char	*reltable;
		const char	*relfield;
	};

	static const struct proxytable_t pt[]={
		{"hosts",	NULL,		NULL},
		{"items",	"hosts",	"hostid"},
		{"drules",	NULL,		NULL},
		{"dchecks",	"drules",	"druleid"},
		{NULL}
	};
	int	t, p, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_proxyconfig_data() [proxyid:"ZBX_FS_UI64"]",
			proxyid);

	for (t = 0; tables[t].table != 0; t++) {
		for (p = 0; pt[p].table != NULL; p++) {
			if (0 != strcmp(tables[t].table, pt[p].table))
				continue;

			ret = get_proxyconfig_table(proxyid, j, &tables[t], pt[p].reltable, pt[p].relfield);
		}
	}

/*	fprintf(stderr, "----- [%zd]\n", strlen(j->buffer));*/
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: send_proxyconfig                                                 *
 *                                                                            *
 * Purpose: send all configuration tables to the proxy                        *
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
int	send_proxyconfig(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	char		hostname[MAX_STRING_LEN],
			host_esc[MAX_STRING_LEN];
	const char	*p;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	proxyid;
	struct zbx_json	j;
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_proxyconfig()");

	if (NULL == (p = zbx_json_pair_by_name(jp, "host")))
		return res;

	if (NULL == zbx_json_decodevalue(p, hostname, sizeof(hostname)))
		return res;

	DBescape_string(hostname, host_esc, MAX_STRING_LEN);
	result = DBselect("select proxyid from proxies where name='%s'" DB_NODE,
		host_esc,
		DBnode_local("proxyid"));

	if (NULL != (row = DBfetch(result))) {
		ZBX_STR2UINT64(proxyid, row[0]);

		zbx_json_init(&j, 512*1024);
		if (SUCCEED == get_proxyconfig_data(proxyid, &j)) {
			zabbix_log(LOG_LEVEL_WARNING, "Sending configuration data to proxy \"%s\" datalen %zd",
					hostname,
					j.buffer_size);

			if (FAIL == zbx_tcp_send(sock, j.buffer))
				zabbix_log(LOG_LEVEL_WARNING, "Error while sending configuration to the \"%s\" [%s]",
						hostname,
						zbx_tcp_strerror());
		}
		zbx_json_free(&j);
	}
	DBfree_result(result);

	return res;
}

