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

#include "config.h"

#include "common.h"
#include "sysinfo.h"
#include "log.h"

int	get_http_page(char *hostname, char *param, int port, char *buffer, int max_buf_len)
{
	char	*haddr;
	char	c[1024];
	
	int	s;
	struct	sockaddr_in addr;
	int	addrlen, n, total;


	struct hostent *host;

	host = gethostbyname(hostname);
	if(host == NULL)
	{
		return SYSINFO_RET_OK;
	}

	haddr=host->h_addr;

	addrlen = sizeof(addr);
	memset(&addr, 0, addrlen);
	addr.sin_port = htons(port);
	addr.sin_family = AF_INET;
	bcopy(haddr, (void *) &addr.sin_addr.s_addr, 4);

	s = socket(AF_INET, SOCK_STREAM, 0);
	if (s == -1)
	{
		close(s);
		return SYSINFO_RET_OK;
	}

	if (connect(s, (struct sockaddr *) &addr, addrlen) == -1)
	{
		close(s);
		return SYSINFO_RET_OK;
	}

	snprintf(c,1024-1,"GET /%s HTTP/1.1\nHost: %s\nConnection: close\n\n", param, hostname);

	write(s,c,strlen(c));

	memset(buffer, 0, max_buf_len);

	total=0;
	while((n=read(s, buffer+total, max_buf_len-1))>0)
	{
		total+=n;
		printf("Read %d bytes\n", total);
	}

	close(s);
	return SYSINFO_RET_OK;
}
#define ZABBIX_TEST

#ifdef ZABBIX_TEST
int main()
{
	char buffer[100*1024];

	get_http_page("www.zabbix.com", "", 80, buffer, 100*1024);

	printf("Back [%d] [%s]\n", strlen(buffer), buffer);
}
#endif
