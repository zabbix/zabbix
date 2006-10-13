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

static int	get_http_page(char *hostname, char *param, int port, char *buffer, int max_buf_len)
{
	char	*haddr;
	char	c[MAX_STRING_LEN];
	
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
		return SYSINFO_RET_FAIL;
	}

	if (connect(s, (struct sockaddr *) &addr, addrlen) == -1)
	{
		close(s);
		return SYSINFO_RET_FAIL;
	}

	snprintf(c,MAX_STRING_LEN-1,"GET /%s HTTP/1.1\nHost: %s\nConnection: close\n\n", param, hostname);

	write(s,c,strlen(c));

	memset(buffer, 0, max_buf_len);

	total=0;
	while((n=read(s, buffer+total, max_buf_len-1-total))>0)
	{
		total+=n;
	}

	close(s);
	return SYSINFO_RET_OK;
}

int	WEB_PAGE_GET(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define	ZABBIX_MAX_WEBPAGE_SIZE	100*1024
	char	hostname[MAX_STRING_LEN];
	char	path[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN];

	char	*buffer;

	int	ret;


        assert(result);

        init_result(result);
	
        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }
        
	if(get_param(param, 1, hostname, MAX_STRING_LEN) != 0)
	{
                return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, path, MAX_STRING_LEN) != 0)
	{
		path[0]='\0';
	}

	if(get_param(param, 3, port_str, MAX_STRING_LEN) != 0)
	{
		strscpy(port_str, "80");
	}

	if(port_str[0]=='\0')
	{
		strscpy(port_str, "80");
	}

	buffer=malloc(ZABBIX_MAX_WEBPAGE_SIZE);
	if(get_http_page(hostname, path, atoi(port_str), buffer, ZABBIX_MAX_WEBPAGE_SIZE) == SYSINFO_RET_OK)
	{
		SET_TEXT_RESULT(result, buffer);
	}
	else
	{
		free(buffer);
		SET_TEXT_RESULT(result, strdup("EOF"));
	}

	return ret;
}

int	WEB_PAGE_PERF(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define	ZABBIX_MAX_WEBPAGE_SIZE	100*1024
	char	hostname[MAX_STRING_LEN];
	char	path[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN];

	char	*buffer;

	int	ret = SYSINFO_RET_OK;

	struct timeval t1,t2;
	struct timezone tz1,tz2;

	long	exec_time;

        assert(result);

        init_result(result);

	gettimeofday(&t1,&tz1);
	
        if(num_param(param) > 3)
        {
                return SYSINFO_RET_FAIL;
        }
        
	if(get_param(param, 1, hostname, MAX_STRING_LEN) != 0)
	{
                return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, path, MAX_STRING_LEN) != 0)
	{
		path[0]='\0';
	}

	if(get_param(param, 3, port_str, MAX_STRING_LEN) != 0)
	{
		strscpy(port_str, "80");
	}

	if(port_str[0]=='\0')
	{
		strscpy(port_str, "80");
	}

	buffer=malloc(ZABBIX_MAX_WEBPAGE_SIZE);
	if(get_http_page(hostname, path, atoi(port_str), buffer, ZABBIX_MAX_WEBPAGE_SIZE) == SYSINFO_RET_OK)
	{
		gettimeofday(&t2,&tz2);
   		exec_time=(t2.tv_sec - t1.tv_sec) * 1000000 + (t2.tv_usec - t1.tv_usec);

		SET_DBL_RESULT(result, exec_time / 1000000.0);
	}
	else
	{
		SET_DBL_RESULT(result, 0.0);
	}
	free(buffer);

	return ret;
}

int	WEB_PAGE_REGEXP(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define	ZABBIX_MAX_WEBPAGE_SIZE	100*1024
	char	hostname[MAX_STRING_LEN];
	char	path[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN];
	char	regexp[MAX_STRING_LEN];
	char	len_str[MAX_STRING_LEN];
	char	back[MAX_STRING_LEN];

	char	*buffer;
	char	*found;

	int	l;

	int	ret = SYSINFO_RET_OK;

        assert(result);

        init_result(result);

        if(num_param(param) > 5)
        {
                return SYSINFO_RET_FAIL;
        }
        
	if(get_param(param, 1, hostname, MAX_STRING_LEN) != 0)
	{
                return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 2, path, MAX_STRING_LEN) != 0)
	{
		path[0]='\0';
	}

	if(get_param(param, 3, port_str, MAX_STRING_LEN) != 0)
	{
		strscpy(port_str, "80");
	}

	if(port_str[0]=='\0')
	{
		strscpy(port_str, "80");
	}

	if(get_param(param, 4, regexp, MAX_STRING_LEN) != 0)
	{
                return SYSINFO_RET_FAIL;
	}

	if(get_param(param, 5, len_str, MAX_STRING_LEN) != 0)
	{
                return SYSINFO_RET_FAIL;
	}

	buffer=malloc(ZABBIX_MAX_WEBPAGE_SIZE);
	if(get_http_page(hostname, path, atoi(port_str), buffer, ZABBIX_MAX_WEBPAGE_SIZE) == SYSINFO_RET_OK)
	{
		found = zbx_regexp_match(buffer,regexp,&l);
		if(found!=NULL)
		{
			zbx_strlcpy(back,found, l);
			SET_STR_RESULT(result, strdup(back));
		}
		else	SET_STR_RESULT(result, strdup("EOF"));
	}
	else
	{
		SET_STR_RESULT(result, strdup("EOF"));
	}
	free(buffer);

	return ret;
}

/*#define ZABBIX_TEST*/

#ifdef ZABBIX_TEST
int main()
{
	char buffer[100*1024];

	get_http_page("www.zabbix.com", "", 80, buffer, 100*1024);

	printf("Back [%d] [%s]\n", strlen(buffer), buffer);
}
#endif
