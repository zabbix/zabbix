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
#include "sysinfo.h"

#include "log.h"
#include "zbxsock.h"
#include "cfg.h"

static int	get_http_page(char *hostname, char *param, unsigned short port, char *buffer, int max_buf_len)
{
	char	request[MAX_STRING_LEN];
	
	ZBX_SOCKET	s;
	ZBX_SOCKADDR	servaddr_in;
	int	
		n, 
		total, 
		ret = SYSINFO_RET_FAIL;

	struct hostent *hp;

	if(NULL == (hp = gethostbyname(hostname)) )
	{
		zabbix_log(
			LOG_LEVEL_DEBUG,
			"get_http_page - gethostbyname() failed for host '%s' [%s]", 
			hostname, 
#ifdef	HAVE_HSTRERROR		
			(char*)hstrerror((int)h_errno)
#else
			strerror_from_system(h_errno)
#endif
			);

		return SYSINFO_RET_FAIL;
	}

	memset(&servaddr_in, 0, sizeof(ZBX_SOCKADDR));

	servaddr_in.sin_family		= AF_INET;
	servaddr_in.sin_addr.s_addr	= ((struct in_addr *)(hp->h_addr))->s_addr;
	servaddr_in.sin_port		= htons(port);


	if(INVALID_SOCKET == (s = (ZBX_SOCKET)socket(AF_INET,SOCK_STREAM,0)))
	{
		zabbix_log( LOG_LEVEL_DEBUG, "get_http_page - Error in socket() [%s:%u] [%s]", hostname, port, strerror_from_system(errno));
		return SYSINFO_RET_FAIL;
	}

	if(SOCKET_ERROR == connect(s,(struct sockaddr *)&servaddr_in,sizeof(ZBX_SOCKADDR)))
	{
		zabbix_log( LOG_LEVEL_WARNING, "get_http_page - Error in connect() [%s:%u] [%s]",hostname, port, strerror_from_system(errno));
		zbx_sock_close(s);
		return SYSINFO_RET_FAIL;
	}

	zbx_snprintf(request, sizeof(request), "GET /%s HTTP/1.1\nHost: %s\nConnection: close\n\n", param, hostname);

	if(SOCKET_ERROR == zbx_sock_write(s, (void *)request, (int)strlen(request)))
	{
		zabbix_log( LOG_LEVEL_DEBUG, "get_http_page - Error during sending [%s:%u] [%s]",hostname, port, strerror_from_system(errno));
		zbx_sock_close(s);
		return SYSINFO_RET_FAIL;
	}

	memset(buffer, 0, max_buf_len);

	for(total=0; (n = zbx_sock_read(s, buffer+total, max_buf_len-1-total, CONFIG_TIMEOUT)) > 0; total+=n);

	ret = SYSINFO_RET_OK;

	zbx_sock_close(s);
	return ret;
}

int	WEB_PAGE_GET(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define	ZABBIX_MAX_WEBPAGE_SIZE	100*1024
	char	hostname[MAX_STRING_LEN];
	char	path[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN];

	char	*buffer;

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

	buffer = calloc(1, ZABBIX_MAX_WEBPAGE_SIZE);
	if(SYSINFO_RET_OK == get_http_page(hostname, path, (unsigned short)atoi(port_str), buffer, ZABBIX_MAX_WEBPAGE_SIZE))
	{
		SET_TEXT_RESULT(result, buffer);
	}
	else
	{
		free(buffer);
		SET_TEXT_RESULT(result, strdup("EOF"));
	}

	return SYSINFO_RET_OK;
}

int	WEB_PAGE_PERF(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define	ZABBIX_MAX_WEBPAGE_SIZE	100*1024
	char	hostname[MAX_STRING_LEN];
	char	path[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN];

	char	*buffer;

	double	start_time;

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

	start_time = zbx_time();

	buffer = calloc(1, ZABBIX_MAX_WEBPAGE_SIZE);
	if(get_http_page(hostname, path, (unsigned short)atoi(port_str), buffer, ZABBIX_MAX_WEBPAGE_SIZE) == SYSINFO_RET_OK)
	{
		SET_DBL_RESULT(result, zbx_time() - start_time);
	}
	else
	{
		SET_DBL_RESULT(result, 0.0);
	}
	free(buffer);

	return SYSINFO_RET_OK;
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
		len_str[0] = '\0';
                /* return SYSINFO_RET_FAIL;  */ /* TODO develope !!! */
	}

	buffer = calloc(1, ZABBIX_MAX_WEBPAGE_SIZE);
	if(SYSINFO_RET_OK == get_http_page(hostname, path, (unsigned short)atoi(port_str), buffer, ZABBIX_MAX_WEBPAGE_SIZE))
	{
		found = zbx_regexp_match(buffer,regexp,&l);
		if(NULL != found)
		{
			zbx_strlcpy(back,found, l+1);
			SET_STR_RESULT(result, strdup(back));
		}
		else	
		{
			SET_STR_RESULT(result, strdup("EOF"));
		}
	}
	else
	{
		SET_STR_RESULT(result, strdup("EOF"));
	}
	free(buffer);

	return ret;
}
