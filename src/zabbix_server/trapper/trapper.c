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

#include "cfg.h"
#include "comms.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "zbxjson.h"
#include "zbxserver.h"
#include "dbcache.h"

#include "../nodewatcher/nodecomms.h"
#include "../nodewatcher/nodesender.h"
#include "nodesync.h"
#include "nodehistory.h"
#include "trapper.h"
#include "active.h"
#include "nodecommand.h"
#include "proxyconfig.h"
#include "proxydiscovery.h"

#include "daemon.h"

static zbx_process_t	zbx_process;

static void	calc_timestamp(char *line,int *timestamp, char *format)
{
	int hh=0,mm=0,ss=0,yyyy=0,dd=0,MM=0;
	int hhc=0,mmc=0,ssc=0,yyyyc=0,ddc=0,MMc=0;
	int i,num;
	struct  tm      tm;
	time_t t;

	zabbix_log( LOG_LEVEL_DEBUG, "In calc_timestamp()");

	hh=mm=ss=yyyy=dd=MM=0;

	for(i=0;(format[i]!=0)&&(line[i]!=0);i++)
	{
		if(isdigit(line[i])==0)	continue;
		num=(int)line[i]-48;

		switch ((char) format[i]) {
			case 'h':
				hh=10*hh+num;
				hhc++;
				break;
			case 'm':
				mm=10*mm+num;
				mmc++;
				break;
			case 's':
				ss=10*ss+num;
				ssc++;
				break;
			case 'y':
				yyyy=10*yyyy+num;
				yyyyc++;
				break;
			case 'd':
				dd=10*dd+num;
				ddc++;
				break;
			case 'M':
				MM=10*MM+num;
				MMc++;
				break;
		}
	}

	zabbix_log( LOG_LEVEL_DEBUG, "hh [%d] mm [%d] ss [%d] yyyy [%d] dd [%d] MM [%d]",
		hh,
		mm,
		ss,
		yyyy,
		dd,
		MM);

	/* Seconds can be ignored. No ssc here. */
	if(hhc!=0&&mmc!=0&&yyyyc!=0&&ddc!=0&&MMc!=0)
	{
		tm.tm_sec=ss;
		tm.tm_min=mm;
		tm.tm_hour=hh;
		tm.tm_mday=dd;
		tm.tm_mon=MM-1;
		tm.tm_year=yyyy-1900;

		t=mktime(&tm);
		if(t>0)
		{
			*timestamp=t;
		}
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End timestamp [%d]",
		*timestamp);
}

/******************************************************************************
 *                                                                            *
 * Function: process_data                                                     *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters: sockfd - descriptor of agent-server socket connection          *
 *             server - server name                                           *
 *             key - item's key                                               *
 *             value - new value of server:key                                *
 *             lastlogsize - if key=log[*], last size of log file             *
 *                                                                            *
 * Return value: SUCCEED - new value processed sucesfully                     *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: for trapper server process                                       *
 *                                                                            *
 ******************************************************************************/
static void	process_mass_data(zbx_sock_t *sock, zbx_uint64_t proxy_hostid, AGENT_VALUE *values, int value_num,
		int *processed, time_t proxy_timediff)
{
	AGENT_RESULT	agent;
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	char		host_esc[MAX_STRING_LEN], key_esc[MAX_STRING_LEN];
	static char	*sql = NULL;
	static int	sql_allocated = 65536;
	int		sql_offset = 0, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_mass_data()");

	if (NULL == sql)
		sql = zbx_malloc(sql, sql_allocated);

	DCinit_nextchecks();

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 2048,
			"select %s where h.hostid=i.hostid and h.proxy_hostid=" ZBX_FS_UI64
			" and h.status=%d and i.status in (%d,%d)",
			ZBX_SQL_ITEM_SELECT,
			proxy_hostid,
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED);

	if (proxy_hostid == 0)
	{
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				" and i.type in (%d,%d)",
				ITEM_TYPE_TRAPPER,
				ITEM_TYPE_ZABBIX_ACTIVE);
	}
	else
	{
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 64,
				" and i.type in (%d,%d,%d,%d,%d,%d,%d,%d,%d,%d)",
				ITEM_TYPE_ZABBIX,
				ITEM_TYPE_SNMPv1,
				ITEM_TYPE_TRAPPER,
				ITEM_TYPE_SIMPLE,
				ITEM_TYPE_SNMPv2c,
				ITEM_TYPE_SNMPv3,
				ITEM_TYPE_ZABBIX_ACTIVE,
				ITEM_TYPE_HTTPTEST,
				ITEM_TYPE_EXTERNAL,
				ITEM_TYPE_IPMI);
	}

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, " and (");

	for (i = 0; i < value_num; i++)
	{
		DBescape_string(values[i].host_name, host_esc, sizeof(host_esc));
		DBescape_string(values[i].key, key_esc, sizeof(key_esc));
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 512,
				"(h.host='%s' and i.key_='%s') or ",
				host_esc,
				key_esc);
	}

	sql_offset -= 4;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 128, ")" DB_NODE,
			DBnode_local("h.hostid"));

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result))) {
		DBget_item_from_db(&item, row);

		if (item.type == ITEM_TYPE_ZABBIX_ACTIVE && FAIL == zbx_tcp_check_security(sock, item.trapper_hosts, 1))
			continue;

		if (item.maintenance_status == HOST_MAINTENANCE_STATUS_ON && item.maintenance_type == MAINTENANCE_TYPE_NODATA &&
				item.maintenance_from <= values[i].clock)
			continue;

		for (i = 0; i < value_num; i++)
		{
			if (0 == strcmp(item.host_name, values[i].host_name) && 0 == strcmp(item.key_orig, values[i].key)) {
/*				zabbix_log(LOG_LEVEL_DEBUG, "Processing [%s@%s: \"%s\"]",
						item.key,
						item.host_name,
						values[i].value);*/

				if (0 == strcmp(values[i].value, "ZBX_NOTSUPPORTED"))
				{
					zabbix_log(LOG_LEVEL_WARNING, "Active parameter [%s] is not supported by agent on host [%s]",
							item.key_orig,
							item.host_name);
					zabbix_syslog("Active parameter [%s] is not supported by agent on host [%s]",
							item.key_orig,
							item.host_name);

					DCadd_nextcheck(&item, values[i].clock, proxy_timediff, "Not supported by ZABBIX agent");

					if (NULL != processed)
						(*processed)++;
				}
				else
				{
					if (0 == strncmp(item.key, "log[", 4) || 0 == strncmp(item.key, "eventlog[", 9))
					{
						item.lastlogsize = values[i].lastlogsize;
						item.timestamp = values[i].timestamp;

						calc_timestamp(values[i].value, &item.timestamp, item.logtimefmt);

						item.eventlog_severity = values[i].severity;
						item.eventlog_source = values[i].source;

/*						zabbix_log(LOG_LEVEL_DEBUG, "Value [%s] Lastlogsize [%s] Timestamp [%s]",
								values[i].value,
								item.lastlogsize,
								item.timestamp);*/
					}

					init_result(&agent);

					if (SUCCEED == set_result_type(&agent, item.value_type, values[i].value))
					{
						if (0 == CONFIG_DBSYNCER_FORKS)
						{
							DBbegin();
							switch (zbx_process) {
							case ZBX_PROCESS_SERVER:
								process_new_value(&item, &agent, values[i].clock);
								update_triggers(item.itemid);
								break;
							case ZBX_PROCESS_PROXY:
								proxy_process_new_value(&item, &agent, values[i].clock);
								break;
							}
							DBcommit();
						}
						else
						{
							switch (zbx_process) {
							case ZBX_PROCESS_SERVER:
								process_new_value(&item, &agent, values[i].clock);
								break;
							case ZBX_PROCESS_PROXY:
								proxy_process_new_value(&item, &agent, values[i].clock);
								break;
							}
						}
						if (NULL != processed)
							(*processed)++;

						/* only for screen Administration|Queue */
						if (item.type != ITEM_TYPE_TRAPPER && item.type != ITEM_TYPE_HTTPTEST &&
								item.value_type != ITEM_VALUE_TYPE_LOG &&
								0 != strcmp(item.key, SERVER_STATUS_KEY) &&
								0 != strcmp(item.key, SERVER_ICMPPING_KEY) &&
								0 != strcmp(item.key, SERVER_ICMPPINGSEC_KEY) &&
								0 != strcmp(item.key, SERVER_ZABBIXLOG_KEY))
							DCadd_nextcheck(&item, values[i].clock, proxy_timediff, NULL);
					}
					else
					{
						zabbix_log( LOG_LEVEL_WARNING, "Type of received value [%s] is not suitable for [%s@%s]",
								values[i].value,
								item.key_orig,
								item.host_name);
						zabbix_syslog("Type of received value [%s] is not suitable for [%s@%s]",
								values[i].value,
								item.key_orig,
								item.host_name);
					}
					free_result(&agent);
			 	}
			}
		}
	}
	DBfree_result(result);

	DCflush_nextchecks();
}

/******************************************************************************
 *                                                                            *
 * Function: send_result                                                      *
 *                                                                            *
 * Purpose: send json SUCCEED or FAIL to socket along with an info message    *
 *                                                                            *
 * Parameters: result SUCCEED or FAIL                                         *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_result(zbx_sock_t *sock, int result, char *info)
{
	int	ret = SUCCEED;
	struct	zbx_json json;

	zbx_json_init(&json, 1024);

	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, SUCCEED == result ? ZBX_PROTO_VALUE_SUCCESS : ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);
	if(info != NULL && info[0]!='\0')
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, info, ZBX_JSON_TYPE_STRING);
	}
	ret = zbx_tcp_send(sock, json.buffer);

	zbx_json_free(&json);

	return ret;
}

static void clean_agent_values(AGENT_VALUE *values, int value_num)
{
	int	i;

	for (i = 0; i < value_num; i ++) {
		zbx_free(values[i].value);
		if (NULL != values[i].source)
			zbx_free(values[i].source);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: process_new_values                                               *
 *                                                                            *
 * Purpose: process values sent by active agents and senders                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_new_values(zbx_sock_t *sock, struct zbx_json_parse *jp, const zbx_uint64_t proxy_hostid)
{
	struct zbx_json_parse   jp_data, jp_row;
	const char		*p;
	char			info[MAX_STRING_LEN], tmp[MAX_STRING_LEN];
	int			ret = SUCCEED;
	int			processed = 0;
	double			sec;
	time_t			now, proxy_timediff = 0;

#define VALUES_MAX	256
	static AGENT_VALUE	*values = NULL, *av;
	int			value_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_new_values()");

	now = time(NULL);
	sec = zbx_time();

	if (NULL == values)
		values = zbx_malloc(values, VALUES_MAX * sizeof(AGENT_VALUE));

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
		proxy_timediff = now - atoi(tmp);

/* {"request":"ZBX_SENDER_DATA","data":[{"key":"system.cpu.num",...,...},{...},...]} 
 *                                     ^
 */	if (NULL == (p = zbx_json_pair_by_name(jp, ZBX_PROTO_TAG_DATA)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Can't find \"data\" pair");
		ret = FAIL;
	}

	if(SUCCEED == ret)
	{
/* {"request":"ZBX_SENDER_DATA","data":[{"key":"system.cpu.num",...,...},{...},...]} 
 *                                     ^------------------------------------------^
 */		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_data)))
			zabbix_log(LOG_LEVEL_WARNING, "Can't proceed jason request. %s",
					zbx_json_strerror());
	}

/* {"request":"ZBX_SENDER_DATA","data":[{"key":"system.cpu.num",...,...},{...},...]} 
 *                                      ^
 */	p = NULL;
	while (SUCCEED == ret && NULL != (p = zbx_json_next(&jp_data, p)))
	{
/* {"request":"ZBX_SENDER_DATA","data":[{"key":"system.cpu.num",...,...},{...},...]} 
 *                                      ^------------------------------^
 */ 		if (FAIL == (ret = zbx_json_brackets_open(p, &jp_row)))
			break;

/*		zabbix_log(LOG_LEVEL_DEBUG, "Next \"%.*s\"",
				jp_row.end - jp_row.start + 1,
				jp_row.start);*/

		av = &values[value_num];

		memset(av, 0, sizeof(AGENT_VALUE));

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_CLOCK, tmp, sizeof(tmp)))
			av->clock = atoi(tmp) + proxy_timediff;
		else
			av->clock = now;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_HOST, av->host_name, sizeof(av->host_name)))
			continue;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_KEY, av->key, sizeof(av->key)))
			continue;

		if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_VALUE, tmp, sizeof(tmp)))
			continue;

		av->value = strdup(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGLASTSIZE, tmp, sizeof(tmp))) 
			av->lastlogsize = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGTIMESTAMP, tmp, sizeof(tmp)))
			av->timestamp = atoi(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGSOURCE, tmp, sizeof(tmp)))
			av->source = strdup(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_LOGSEVERITY, tmp, sizeof(tmp)))
			av->severity = atoi(tmp);

		value_num ++;

		if (value_num == VALUES_MAX) {
			process_mass_data(sock, proxy_hostid, values, value_num, &processed, proxy_timediff);

			clean_agent_values(values, value_num);
			value_num = 0;
		}
	}

	if (value_num > 0)
		process_mass_data(sock, proxy_hostid, values, value_num, &processed, proxy_timediff);

	clean_agent_values(values, value_num);

	zbx_snprintf(info, sizeof(info), "Processed %d Failed %d Total %d Seconds spent " ZBX_FS_DBL,
			processed,
			value_num - processed,
			value_num,
			zbx_time() - sec);

	if (send_result(sock, ret, info) != SUCCEED)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error sending result back");
		zabbix_syslog("Trapper: error sending result back");
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxy_values                                             *
 *                                                                            *
 * Purpose: process values sent by proxy servers                              *
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
static int	process_proxy_values(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	zbx_uint64_t	proxy_hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_proxy_values()");

	if (FAIL == get_proxy_id(jp, &proxy_hostid))
		return FAIL;

	update_proxy_lastaccess(proxy_hostid);

	return process_new_values(sock, jp, proxy_hostid);
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxy_heartbeat                                          *
 *                                                                            *
 * Purpose: process heartbeat sent by proxy servers                           *
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
static int	process_proxy_heartbeat(zbx_sock_t *sock, struct zbx_json_parse *jp)
{
	zbx_uint64_t	proxy_hostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_proxy_heartbeat()");

	if (FAIL == get_proxy_id(jp, &proxy_hostid))
		return FAIL;

	update_proxy_lastaccess(proxy_hostid);

	if (send_result(sock, SUCCEED, NULL) != SUCCEED) {
		zabbix_log( LOG_LEVEL_WARNING, "Error sending result back");
		zabbix_syslog("Trapper: error sending result back");
	}

	return SUCCEED;
}

static int	process_trap(zbx_sock_t	*sock, char *s, int max_len)
{
	char	*line,*host;
	char	*server,*key,*value_string, *data;
	char	copy[MAX_STRING_LEN];
	char	host_dec[MAX_STRING_LEN],key_dec[MAX_STRING_LEN],value_dec[MAX_STRING_LEN];
	char	lastlogsize[MAX_STRING_LEN];
	char	timestamp[MAX_STRING_LEN];
	char	source[MAX_STRING_LEN];
	char	severity[MAX_STRING_LEN];
	int	sender_nodeid, nodeid;
	char	*answer;

	int	ret=SUCCEED, res;
	size_t	datalen;

	struct 		zbx_json_parse jp;
	char		value[MAX_STRING_LEN];
	AGENT_VALUE	av;

	zbx_rtrim(s, " \r\n\0");

	datalen = strlen(s);
	zabbix_log( LOG_LEVEL_DEBUG, "Trapper got [%s] len %zd",
		s,
		datalen);

/* Request for list of active checks */
	if (strncmp(s,"ZBX_GET_ACTIVE_CHECKS", 21) == 0) {
		line=strtok(s,"\n");
		host=strtok(NULL,"\n");
		if(host == NULL)
		{
			zabbix_log( LOG_LEVEL_WARNING, "ZBX_GET_ACTIVE_CHECKS: host is null. Ignoring.");
		}
		else
		{
			ret = send_list_of_active_checks(sock, host);
		}
/* Request for last ids */
	} else if (strncmp(s,"ZBX_GET_HISTORY_LAST_ID", 23) == 0) {
		send_history_last_id(sock, s);
		return ret;
	} else if (strncmp(s,"ZBX_GET_TRENDS_LAST_ID", 22) == 0) {
		send_trends_last_id(sock, s);
		return ret;
/* Process information sent by zabbix_sender */
	} else {
		/* Command? */
		if(strncmp(s,"Command",7) == 0)
		{
			node_process_command(sock, s);
			return ret;
		}
		/* Node data exchange? */
		if(strncmp(s,"Data",4) == 0)
		{
			node_sync_lock(0);

/*			zabbix_log( LOG_LEVEL_WARNING, "Node data received [len:%d]", strlen(s)); */
			res = node_sync(s, &sender_nodeid, &nodeid);
			if (FAIL == res)
				send_data_to_node(sender_nodeid, sock, "FAIL");
			else {
				res = calculate_checksums(nodeid, NULL, 0);
				if (SUCCEED == res && NULL != (data = get_config_data(nodeid, ZBX_NODE_SLAVE))) {
					res = send_data_to_node(sender_nodeid, sock, data);
					zbx_free(data);
					if (SUCCEED == res)
						res = recv_data_from_node(sender_nodeid, sock, &answer);
					if (SUCCEED == res && 0 == strcmp(answer, "OK"))
						res = update_checksums(nodeid, ZBX_NODE_SLAVE, SUCCEED, NULL, 0, NULL);
				}
			}

			node_sync_unlock(0);

			return ret;
		}
		/* Slave node history ? */
		if(strncmp(s,"History",7) == 0)
		{
/*			zabbix_log( LOG_LEVEL_WARNING, "Slave node history received [len:%d]", strlen(s)); */
			if (node_history(s, datalen) == SUCCEED) {
				if (zbx_tcp_send_raw(sock,"OK") != SUCCEED) {
					zabbix_log( LOG_LEVEL_WARNING, "Error sending confirmation to node");
					zabbix_syslog("Trapper: error sending confirmation to node");
				}
			}
			return ret;
		}
		/* JSON protocol? */
		else if (SUCCEED == zbx_json_open(s, &jp))
		{
			if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_REQUEST, value, sizeof(value))) {
				if (0 == strcmp(value, ZBX_PROTO_VALUE_PROXY_CONFIG) && zbx_process == ZBX_PROCESS_SERVER)
				{
					send_proxyconfig(sock, &jp);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_AGENT_DATA) ||
					0 == strcmp(value, ZBX_PROTO_VALUE_SENDER_DATA))
				{
					ret = process_new_values(sock, &jp, 0);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_HISTORY_DATA) && zbx_process == ZBX_PROCESS_SERVER)
				{
					ret = process_proxy_values(sock, &jp);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_DISCOVERY_DATA) && zbx_process == ZBX_PROCESS_SERVER)
				{
					ret = process_discovery_data(sock, &jp);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_PROXY_HEARTBEAT) && zbx_process == ZBX_PROCESS_SERVER)
				{
					ret = process_proxy_heartbeat(sock, &jp);
				}
				else if (0 == strcmp(value, ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS))
				{
					ret = send_list_of_active_checks_json(sock, &jp);
				}
				else
				{
					zabbix_log( LOG_LEVEL_WARNING, "Unknown request received [%s]",
						value);
				}
			}
			return ret;
		}
		/* New XML protocol? */
		else if(s[0]=='<')
		{
			zabbix_log( LOG_LEVEL_DEBUG, "XML received [%s]", s);

			comms_parse_response(s,host_dec,key_dec,value_dec,lastlogsize,timestamp,source,severity,sizeof(host_dec)-1);

			server=host_dec;
			value_string=value_dec;
			key=key_dec;
		}
		else
		{
			strscpy(copy,s);

			server=(char *)strtok(s,":");
			if(NULL == server)
			{
				return FAIL;
			}

			key=(char *)strtok(NULL,":");
			if(NULL == key)
			{
				return FAIL;
			}
	
			value_string=strchr(copy,':');
			value_string=strchr(value_string+1,':');

			if(NULL == value_string)
			{
				return FAIL;
			}
			/* It points to ':', so have to increment */
			value_string++;
			lastlogsize[0]=0;
			timestamp[0]=0;
			source[0]=0;
			severity[0]=0;
		}
		zabbix_log( LOG_LEVEL_DEBUG, "Value [%s]", value_string);

		av.clock = time(NULL);
		zbx_strlcpy(av.host_name, server, sizeof(av.host_name));
		zbx_strlcpy(av.key, key, sizeof(av.key));
		av.value = value_string;
		av.lastlogsize = atoi(lastlogsize);
		av.timestamp = atoi(timestamp);
		av.source = source;
		av.severity = atoi(severity);

		process_mass_data(sock, 0, &av, 1, NULL, 0);
		
		if( zbx_tcp_send_raw(sock, SUCCEED == ret ? "OK" : "NOT OK") != SUCCEED)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Error sending result back");
			zabbix_syslog("Trapper: error sending result back");
		}
		zabbix_log( LOG_LEVEL_DEBUG, "After write()");
	}	
	return ret;
}

void	process_trapper_child(zbx_sock_t *sock)
{
	char	*data;

/* suseconds_t is not defined under HP-UX */
/*	struct timeval tv;
	suseconds_t    msec;
	gettimeofday(&tv, NULL);
	msec = tv.tv_usec;*/

	alarm(ZABBIX_TRAPPER_TIMEOUT);

	if(zbx_tcp_recv(sock, &data) != SUCCEED)
	{
		alarm(0);
		return;
	}

	process_trap(sock, data, sizeof(data));
	alarm(0);

/*	gettimeofday(&tv, NULL);
	zabbix_log( LOG_LEVEL_DEBUG, "Trap processed in " ZBX_FS_DBL " seconds",
		(double)(tv.tv_usec-msec)/1000000 );*/
}

void	child_trapper_main(zbx_process_t p, zbx_sock_t *s)
{
	struct	sigaction phan;

	zabbix_log( LOG_LEVEL_DEBUG, "In child_trapper_main()");

	phan.sa_handler = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	zbx_process = p;

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;) {
		zbx_setproctitle("trapper [waiting for connection]");
		if (SUCCEED == zbx_tcp_accept(s)) {
			zbx_setproctitle("trapper [processing data]");
			process_trapper_child(s);

			zbx_tcp_unaccept(s);
		} else
			zabbix_log(LOG_LEVEL_WARNING, "Trapper failed to accept connection");
	}
	DBclose();
}

/*
pid_t	child_trapper_make(int i,int listenfd, int addrlen)
{
	pid_t	pid;

	if((pid = zbx_fork()) >0)
	{
		return (pid);
	}

	child_trapper_main(i, listenfd, addrlen);

	return 0;
}*/
