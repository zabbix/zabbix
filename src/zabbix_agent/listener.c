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
#include "listener.h"

#include "zbxsock.h"
#include "zbxconf.h"
#include "stats.h"
#include "sysinfo.h"
#include "log.h"
#include "zbxsecurity.h"

static void	process_listener(ZBX_SOCKET sock)
{
	AGENT_RESULT	result;

	char	command[MAX_STRING_LEN];
	char	value[MAX_STRING_LEN];
	int	ret = 0;

	init_result(&result);

	memset(&command, 0, MAX_STRING_LEN);

	ret = zbx_sock_read(sock, (void *)command, MAX_STRING_LEN, CONFIG_TIMEOUT);

	if(ret == SOCKET_ERROR)
	{
//		WriteLog(MSG_RECV_ERROR,EVENTLOG_ERROR_TYPE,"s",strerror(errno));
		zabbix_log( LOG_LEVEL_DEBUG, "read() failed.");
	}
	else if(ret == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Read timeout");
	}
	command[ret-1] = '\0';

	process(command, 0, &result);

        if(result.type & AR_DOUBLE)
                 snprintf(value, MAX_STRING_LEN-1, "%f", result.dbl);
        else if(result.type & AR_UINT64)
                 snprintf(value, MAX_STRING_LEN-1, ZBX_FS_UI64, result.ui64);
        else if(result.type & AR_STRING)
                 snprintf(value, MAX_STRING_LEN-1, "%s", result.str);
        else if(result.type & AR_TEXT)
                 snprintf(value, MAX_STRING_LEN-1, "%s", result.text);
        else if(result.type & AR_MESSAGE)
                 snprintf(value, MAX_STRING_LEN-1, "%s", result.msg);
        free_result(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "Sending back:%s", value);

	ret = zbx_sock_write(sock, value, strlen(value));

	if(ret == SOCKET_ERROR)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error writing to socket [%s]",
			strerror(errno));
	}
}

ZBX_THREAD_ENTRY(ListenerThread, pSock)
{
	int local_request_failed = 0;

	ZBX_SOCKET	sock, accept_sock;
	ZBX_SOCKADDR	serv_addr;
	int nlen = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In ListenerThread()");

	assert(pSock);

	zabbix_log( LOG_LEVEL_WARNING, "zabbix_agentd listener %ld started",(long)getpid());

	sock = *((ZBX_SOCKET *)pSock);

	// Wait for connection requests
	for(;;)
	{
		zbx_setproctitle("waiting for connection. Requests [%d]", stats_request++);

		accept_sock = SOCKET_ERROR;
		nlen = sizeof(ZBX_SOCKADDR);
		if (accept_sock = accept(sock, (struct sockaddr *)&serv_addr, &nlen) == SOCKET_ERROR)
		{
#if defined (WIN32)
			int error = WSAGetLastError();

			if (error!=WSAEINTR)
			{
				//WriteLog(MSG_ACCEPT_ERROR,EVENTLOG_ERROR_TYPE,"e",error);
				zabbix_log( LOG_LEVEL_WARNING, "Accept error");
			}
#endif /* WIN32 */

			local_request_failed++;
			stats_request_failed++;
			if (local_request_failed > 1000)
			{
//				WriteLog(MSG_TOO_MANY_ERRORS,EVENTLOG_WARNING_TYPE,NULL);
				zabbix_log( LOG_LEVEL_WARNING, "Too many errors on requests");
				local_request_failed = 0;
			}
			zbx_sleep(1);
			continue;
		}
		sock = accept_sock;

		local_request_failed = 0;     /* Reset consecutive errors counter */
		
		zbx_setproctitle("processing request");

		//Win32 - IsValidServerAddr
		if( check_security(serv_addr.sin_addr.S_un.S_addr, CONFIG_HOSTS_ALLOWED, 0) == SUCCEED)
		{
			stats_request_accepted++;
			process_listener(sock);
		} else {
			stats_request_rejected++;
		}

		shutdown(sock,2);

		zbx_sock_close(sock);
	}

	zbx_tread_exit(0);
}
