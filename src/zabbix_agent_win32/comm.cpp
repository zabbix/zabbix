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
// Client communication thread
//

static void CommThread(void *param)
{
   SOCKET sock;
   int rc;
   char cmd[MAX_ZABBIX_CMD_LEN],result[MAX_STRING_LEN];

   TlsSetValue(dwTlsLogPrefix,"CommThread: ");   // Set log prefix for communication thread
   sock=(SOCKET)param;

   rc=recv(sock,cmd,MAX_ZABBIX_CMD_LEN,0);
   if (rc<=0)
   {
      WriteLog("recv() failed [%s]\r\n",strerror(errno));
      goto end_session;
   }

   cmd[rc-1]=0;
   ProcessCommand(cmd,result);
   send(sock,result,strlen(result),0);

   // Terminate session
end_session:
   shutdown(sock,2);
   closesocket(sock);
}


//
// TCP/IP Listener
//

void ListenerThread(void *)
{
   SOCKET sock,sockClient;
   struct sockaddr_in servAddr;
   int iSize;

   TlsSetValue(dwTlsLogPrefix,"Listener: ");   // Set log prefix for listener thread

   // Create socket
   if ((sock=socket(AF_INET,SOCK_STREAM,0))==-1)
   {
      WriteLog("Cannot open socket: %s\r\n",GetSystemErrorText(WSAGetLastError()));
      exit(1);
   }

   // Fill in local address structure
   memset(&servAddr,0,sizeof(struct sockaddr_in));
   servAddr.sin_family=AF_INET;
   servAddr.sin_addr.s_addr=htonl(INADDR_ANY);
   servAddr.sin_port=htons(confListenPort);

   // Bind socket
   if (bind(sock,(struct sockaddr *)&servAddr,sizeof(struct sockaddr_in))!=0)
   {
      WriteLog("Cannot bind socket: %s\r\n",GetSystemErrorText(WSAGetLastError()));
      exit(1);
   }

   // Set up queue
   listen(sock,SOMAXCONN);
   WriteLog("Accepting connections on port %d from %d.%d.%d.%d\r\n",
            confListenPort,confServerAddr & 255,(confServerAddr >> 8) & 255,
            (confServerAddr >> 16) & 255,confServerAddr >> 24);

   // Wait for connection requests
   while(1)
   {
      iSize=sizeof(struct sockaddr_in);
      if ((sockClient=accept(sock,(struct sockaddr *)&servAddr,&iSize))==-1)
      {
         WriteLog("accept() error: %s\n",GetSystemErrorText(WSAGetLastError()));
         closesocket(sock);
         exit(1);
      }
      if (servAddr.sin_addr.S_un.S_addr==confServerAddr)
      {
         _beginthread(CommThread,0,(void *)sockClient);
      }
      else     // Unauthorized connection
      {
         shutdown(sockClient,2);
         closesocket(sockClient);
      }
   }
}
