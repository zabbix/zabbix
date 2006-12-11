/* 
** ZabbixW32 - Win32 agent for Zabbix
** Copyright (C) 2002,2003 Victor Kirhenshtein
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
** $module: network.cpp
**
**/

#include "zabbixw32.h"


//
// Check if TCP port accepts connections
// Parameter has two forms:
//   check_port[nnn] - check port nnn on localhost (127.0.0.1)
//   check_port[xxxx,nnn] - check port nnn on host xxxx
//

LONG H_CheckTcpPort(char *cmd,char *arg,double *value)
{
   SOCKET sock;
   struct sockaddr_in sa;
   struct hostent *hs;
   char 
	   param[MAX_STRING_LEN],
	   host[MAX_STRING_LEN],
	   str_port[15];
   int port;

   // Parse arguments
   GetParameterInstance(cmd,param,256);

    if(num_param(param) != 2)
    {
            return SYSINFO_RC_NOTSUPPORTED;
    }

    if(get_param(param, 1, host, MAX_STRING_LEN) != 0)
    {
            return SYSINFO_RC_NOTSUPPORTED;
    }
    if(host[0] == '\0')
    {
            /* default parameter */
            sprintf(host, "127.0.0.1");
    }

    if(get_param(param, 2, str_port, 15) != 0)
    {
            str_port[0] = '\0';
    }
    if(str_port[0] == '\0')
    {
            return SYSINFO_RC_NOTSUPPORTED;
    }

   port=atoi(str_port);
   if ((port<1)||(port>655535))
      return SYSINFO_RC_NOTSUPPORTED;

   // Fill in server address structure
   memset(&sa,0,sizeof(sa));
   sa.sin_family=AF_INET;
   sa.sin_port=htons((unsigned short)port);

   // Get host address
   hs=gethostbyname(host);
   if (hs==NULL)
   {
      sa.sin_addr.s_addr=inet_addr(host);
      if (sa.sin_addr.s_addr==INADDR_NONE)
      {
         WriteLog(MSG_DNS_LOOKUP_FAILED,EVENTLOG_ERROR_TYPE,"s",host);
         return SYSINFO_RC_NOTSUPPORTED;
      }
   }
   else
   {
      memcpy(&sa.sin_addr,hs->h_addr,hs->h_length);
   }

   // Create socket
   sock=socket(AF_INET,SOCK_STREAM,0);   
   if (sock==-1)
   {
printf("error1 %e\n",WSAGetLastError());
      WriteLog(MSG_SOCKET_ERROR,EVENTLOG_ERROR_TYPE,"e",WSAGetLastError());
      return SYSINFO_RC_ERROR;
   }
   
   // Establish connection
   if (connect(sock,(struct sockaddr *)&sa,sizeof(sa))!=0)
   {
      DWORD dwError=WSAGetLastError();

      closesocket(sock);

      if ((dwError==WSAECONNREFUSED)||(dwError==WSAENETUNREACH)||(dwError==WSAETIMEDOUT))
      {
         *value=0;      // Port unreacheable
         return SYSINFO_RC_SUCCESS;
      }
printf("error2\n");
      return SYSINFO_RC_ERROR;
   }

   closesocket(sock);
   *value=1;      // Connection successful

   return SYSINFO_RC_SUCCESS;
}
