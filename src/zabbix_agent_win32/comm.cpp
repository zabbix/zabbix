/* 
** ZabbixW32 - Win32 agent for Zabbix
** Copyright (C) 2002 Victor Kirhenshtein
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
**
** $module: comm.cpp
**
**/

#include "zabbixw32.h"

//
// Global data
//

double statAcceptedRequests=0;
double statRejectedRequests=0;
double statTimedOutRequests=0;
double statAcceptErrors=0;


//
// Validates server's address
//

static BOOL IsValidServerAddr(DWORD addr)
{
   DWORD i;
   BOOL ret= FALSE;

INIT_CHECK_MEMORY(main);
   for(i=0;i<confServerCount;i++)
      if (addr==confServerAddr[i])
         ret = TRUE;

CHECK_MEMORY(main, "IsValidServerAddr", "end");
   return ret;
}


//
// Client communication
//

void Communicate(SOCKET sock)
{
	TIMEVAL		timeout = {0,0};
	FD_SET		rdfs;
	REQUEST		rq;

	int rc = 0;

LOG_FUNC_CALL("In Communicate()");
INIT_CHECK_MEMORY(main);

	// Wait for command from server
	FD_ZERO(&rdfs);
	FD_SET(sock,&rdfs);							// ignore WARNING '...whle(0)'

	timeout.tv_sec	= COMMAND_TIMEOUT;
	timeout.tv_usec	= 0;
	rc = select(sock+1, &rdfs, (fd_set *)NULL, (fd_set *)NULL, &timeout);
	if (rc == SOCKET_ERROR)
	{
		WriteLog(MSG_SELECT_ERROR,EVENTLOG_ERROR_TYPE,"e",WSAGetLastError());
		goto end_session;
	}
	if(rc == 0)
	{
		WriteLog(MSG_COMMAND_TIMEOUT,EVENTLOG_WARNING_TYPE,NULL);
		goto end_session;
	}

	// Init REQUEST
	memset(&rq, 0, sizeof(REQUEST));
	rc = recv(sock,rq.cmd,MAX_ZABBIX_CMD_LEN-1,0);

	if(rc <= 0)
	{
		WriteLog(MSG_RECV_ERROR,EVENTLOG_ERROR_TYPE,"s",strerror(errno));
		goto end_session;
	}
	rq.cmd[rc-1]=0;

	ProcessCommand(rq.cmd,rq.result);
	goto send_result;

end_session:
	sprintf(rq.result,"ERROR\n");

send_result:
	send(sock,rq.result,strlen(rq.result),0);

CHECK_MEMORY(main, "Communicate", "end");
LOG_FUNC_CALL("End of Communicate()");
}

//
// Client connector thread
//

unsigned int __stdcall AcceptThread(void *arg)
{
	SOCKET sock = (SOCKET)arg;
	struct sockaddr_in servAddr;
	int iSize=0,errorCount=0;

	LOG_DEBUG_INFO("s", "In AcceptThread()");
	INIT_CHECK_MEMORY(main);

	// Wait for connection requests
	for(;;)
	{
		INIT_CHECK_MEMORY(while);
		SOCKET sockClient;

		iSize = sizeof(struct sockaddr_in);
		if ((sockClient=accept(sock,(struct sockaddr *)&servAddr,&iSize)) < 0)
		{
			int error = WSAGetLastError();

			if (error!=WSAEINTR)
				WriteLog(MSG_ACCEPT_ERROR,EVENTLOG_ERROR_TYPE,"e",error);

			errorCount++;
			statAcceptErrors++;
			if (errorCount>1000)
			{
				WriteLog(MSG_TOO_MANY_ERRORS,EVENTLOG_WARNING_TYPE,NULL);
				errorCount=0;
			}
			Sleep(500);
			continue;
		}

		errorCount=0;     /* Reset consecutive errors counter */

		if (IsValidServerAddr(servAddr.sin_addr.S_un.S_addr))
		{
			statAcceptedRequests++;
			Communicate(sockClient);
		} else {
			statRejectedRequests++;
		}

		shutdown(sockClient,2);
		closesocket(sockClient);

		CHECK_MEMORY(while, "AcceptThread", "while");
	}
	CHECK_MEMORY(main, "AcceptThread", "end");
	LOG_DEBUG_INFO("s", "End of AcceptThread()");

	_endthreadex(0);
	return 0;
}


//
// TCP/IP Listener
//

void ListenerThread(void *)
{
#define MAX_LISTENERS_COUNT 10

	HANDLE hThread[MAX_LISTENERS_COUNT];
	unsigned int tid[MAX_LISTENERS_COUNT];

	SOCKET sock;
	struct sockaddr_in servAddr;
	//int iSize=0, errorCount=0;

	int i=0;

	LOG_DEBUG_INFO("s", "In ListenerThread()");
	INIT_CHECK_MEMORY(main);

	// Create socket
	if ((sock=socket(AF_INET,SOCK_STREAM,0)) == INVALID_SOCKET)
	{
		WriteLog(MSG_SOCKET_ERROR,EVENTLOG_ERROR_TYPE,"e",WSAGetLastError());
		LOG_DEBUG_INFO("s", "End of ListenerThread() Error: 1");
		_endthread();
		exit(1);
	}

	// Fill in local address structure
	memset(&servAddr,0,sizeof(struct sockaddr_in));
	servAddr.sin_family			= AF_INET;
	servAddr.sin_addr.s_addr	= htonl(INADDR_ANY);
	servAddr.sin_port			= htons(confListenPort);

	// Bind socket
	if (bind(sock,(struct sockaddr *)&servAddr,sizeof(struct sockaddr_in)) == SOCKET_ERROR)
	{
		WriteLog(MSG_BIND_ERROR,EVENTLOG_ERROR_TYPE,"e",WSAGetLastError());
		LOG_DEBUG_INFO("s", "End of ListenerThread() Error: 2");
		_endthread();
		exit(1);
	}

	// Set up queue
	if(listen(sock,SOMAXCONN) == SOCKET_ERROR)
	{
		WriteLog(MSG_LISTEN_ERROR,EVENTLOG_ERROR_TYPE,"e",WSAGetLastError());
		LOG_DEBUG_INFO("s", "End of ListenerThread() Error: 2");
		_endthread();
		exit(1);
	}

	for(i = 0; i < MAX_LISTENERS_COUNT; i++)
	{
		hThread[i] = (HANDLE)_beginthreadex(NULL,0,AcceptThread,(void *)sock,0,&(tid[i]));
		if(hThread[i] >=0 )
			WriteLog(MSG_INFORMATION,EVENTLOG_INFORMATION_TYPE,"ds", tid[i], ": Listen thread is Started.");
	}

	for(i = 0; i < MAX_LISTENERS_COUNT; i++)
	{
		if(WaitForSingleObject(hThread[i], INFINITE) == WAIT_OBJECT_0)
			WriteLog(MSG_INFORMATION,EVENTLOG_INFORMATION_TYPE,"ds", tid[i], ": Listen thread is Terminated.");

		CloseHandle( hThread );
	}

	CHECK_MEMORY(main, "ListenerThread", "end");

	LOG_DEBUG_INFO("s", "End of ListenerThread()");
	_endthread();
}
