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

#include "../functions.h"
#include "../expression.h"

#include "../nodewatcher/nodecomms.h"
#include "../nodewatcher/nodewatcher.h"
#include "nodesync.h"
#include "nodeevents.h"
#include "nodehistory.h"
#include "trapper.h"
#include "active.h"
#include "nodecommand.h"

#include "daemon.h"

static int	process_trap(zbx_sock_t	*sock,char *s, int max_len)
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
	char	*result = NULL, *answer;

	int	ret=SUCCEED, res;

	zbx_rtrim(s, " \r\n\0");

	zabbix_log( LOG_LEVEL_DEBUG, "Trapper got [%s] len %d",
		s,
		strlen(s));

/* Request for list of active checks */
	if(strncmp(s,"ZBX_GET_ACTIVE_CHECKS", strlen("ZBX_GET_ACTIVE_CHECKS")) == 0)
	{
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
	}
/* Process information sent by zabbix_sender */
	else
	{
		/* Command? */
		if(strncmp(s,"Command",7) == 0)
		{
			if(SUCCEED == node_process_command(s, &result))
			{
/*				if( zbx_tcp_send_ext(sock,result,ZBX_TCP_READ_UNTIL_CLOSE) != SUCCEED)*/
				if( zbx_tcp_send_raw(sock,result) != SUCCEED)
				{
					zabbix_log( LOG_LEVEL_WARNING, "Error sending result to node");
					zabbix_syslog("Trapper: error sending result to node");
				}
				zbx_free(result);
			}
		}
		/* Node data exchange? */
		if(strncmp(s,"Data",4) == 0)
		{
			node_sync_lock(0);

/*			zabbix_log( LOG_LEVEL_WARNING, "Node data received [len:%d]", strlen(s)); */
			res = node_sync(s, &sender_nodeid, &nodeid);
			if (nodeid == CONFIG_NODEID || FAIL == res)
				send_data_to_node(sender_nodeid, sock, SUCCEED == res ? "OK" : "FAIL");

			if (nodeid != CONFIG_NODEID && SUCCEED == res) {
				res = calculate_checksums(nodeid, NULL, 0);
				if (SUCCEED == res && NULL != (data = get_config_data(nodeid, ZBX_NODE_SLAVE))) {
					if (SUCCEED == res)
						res = send_data_to_node(sender_nodeid, sock, data);
					if (SUCCEED == res)
						res = recv_data_from_node(sender_nodeid, sock, &answer);
					if (SUCCEED == res && 0 == strcmp(answer, "OK"))
						res = update_checksums(nodeid, ZBX_NODE_SLAVE, SUCCEED, NULL, 0, NULL);
					zbx_free(data);
				}
			}

			node_sync_unlock(0);

			return ret;
		}
		/* Slave node events? */
		if(strncmp(s,"Events",6) == 0)
		{
/*			zabbix_log( LOG_LEVEL_WARNING, "Slave node events received [len:%d]", strlen(s)); */
			if(node_events(s) == SUCCEED)
			{
				if( zbx_tcp_send_raw(sock,"OK") != SUCCEED)
				{
					zabbix_log( LOG_LEVEL_WARNING, "Error sending confirmation to node");
					zabbix_syslog("Trapper: error sending confirmation to node");
				}
			}
			return ret;
		}
		/* Slave node history ? */
		if(strncmp(s,"History",7) == 0)
		{
/*			zabbix_log( LOG_LEVEL_WARNING, "Slave node history received [len:%d]", strlen(s)); */
			if(node_history(s) == SUCCEED)
			{
				if( zbx_tcp_send_raw(sock,"OK") != SUCCEED)
				{
					zabbix_log( LOG_LEVEL_WARNING, "Error sending confirmation to node]");
					zabbix_syslog("Trapper: error sending confirmation to node");
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

		DBbegin();
		ret=process_data(sock,server,key,value_string,lastlogsize,timestamp,source,severity);
		DBcommit();
		
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

/*	alarm(CONFIG_TIMEOUT);*/

	if(zbx_tcp_recv(sock, &data) != SUCCEED)
	{
/*		alarm(0);*/
		return;
	}

	process_trap(sock, data, sizeof(data));
/*	alarm(0);*/

/*	gettimeofday(&tv, NULL);
	zabbix_log( LOG_LEVEL_DEBUG, "Trap processed in " ZBX_FS_DBL " seconds",
		(double)(tv.tv_usec-msec)/1000000 );*/
}

void	child_trapper_main(int i, zbx_sock_t *s)
{
	zabbix_log( LOG_LEVEL_DEBUG, "In child_trapper_main()");

	zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Trapper]", i);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for(;;)
	{
		zbx_setproctitle("waiting for connection");
		zbx_tcp_accept(s);

		zbx_setproctitle("processing data");
		process_trapper_child(s);

		zbx_tcp_unaccept(s);
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
