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
#include "cfg.h"
#include "zbxconf.h"
#include "stats.h"
#include "sysinfo.h"
#include "log.h"
#include "zbxsecurity.h"

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON) /* ZABBIX_SERVICE */
#	include "daemon.h"
#endif /* ZABBIX_DAEMON */

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
		zabbix_log(LOG_LEVEL_DEBUG, "Error receiving data from socket: %s", strerror(errno));
		return;
	}
	else if(ret == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Read timeout");
		return;
	}

	/*command[ret-2] = '\0'; *//* remove '\r\n' sumbols from recived command (_WINDOWS) !!!TODO!!! correct win32 agent !!!TODO!!! */
	command[ret-1] = '\0'; /* remove '\n' sumbols from recived command (LINUX) !!!TODO!!! */

	zabbix_log(LOG_LEVEL_DEBUG, "Requested [%s]", command);

	process(command, 0, &result);

        if(result.type & AR_DOUBLE)		zbx_snprintf(value, sizeof(value), "%f", result.dbl);
        else if(result.type & AR_UINT64)	zbx_snprintf(value, sizeof(value), ZBX_FS_UI64, result.ui64);
        else if(result.type & AR_STRING)	zbx_snprintf(value, sizeof(value), "%s", result.str);
        else if(result.type & AR_TEXT)		zbx_snprintf(value, sizeof(value), "%s", result.text);
        else if(result.type & AR_MESSAGE)	zbx_snprintf(value, sizeof(value), "%s", result.msg);

        free_result(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "Sending back [%s]", value);

	ret = zbx_sock_write(sock, value, (int)strlen(value));

	if(ret == SOCKET_ERROR)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Error writing to socket [%s]", strerror(errno));
	}
}

ZBX_THREAD_ENTRY(listener_thread, pSock)
{
	int local_request_failed = 0;

	ZBX_SOCKET	sock, accept_sock;
	ZBX_SOCKADDR	serv_addr;
	socklen_t nlen = 0;

	assert(pSock);

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd listener started");

	sock = *((ZBX_SOCKET *)pSock);

	while(ZBX_IS_RUNNING)
	{
		accept_sock = SOCKET_ERROR;
		nlen = sizeof(ZBX_SOCKADDR);
		if(SOCKET_ERROR == (accept_sock = (ZBX_SOCKET)accept(sock, (struct sockaddr *)&serv_addr, &nlen)))
		{
			if(!ZBX_IS_RUNNING) break;

			if (EINTR != zbx_sock_last_error())
			{
				zabbix_log( LOG_LEVEL_WARNING, "Unable to accept incoming connection: [%s]", strerror_from_system(zbx_sock_last_error()));
			}

			local_request_failed++;

			if (local_request_failed > 1000)
			{
				zabbix_log( LOG_LEVEL_WARNING, "Too many consecutive errors on accept() call.");
				local_request_failed = 0;
			}
			zbx_sleep(1);
			continue;
		}
		if(!ZBX_IS_RUNNING) break;

		local_request_failed = 0;     /* Reset consecutive errors counter */
		
		zbx_setproctitle("processing request");

		zabbix_log(LOG_LEVEL_DEBUG, "Processing request.");

		if(SUCCEED == check_security(accept_sock, CONFIG_HOSTS_ALLOWED, 0))
		{
			process_listener(accept_sock);
		}

		shutdown(accept_sock,2);

		zbx_sock_close(accept_sock);
	}

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd listener stopped");

	ZBX_DO_EXIT();

	zbx_tread_exit(0);
}
