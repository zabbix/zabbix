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

#include "comms.h"
#include "log.h"
#include "cfg.h"

#include "net.h"

/* 
 * 0 - NOT OK
 * 1 - OK
 * */
int	tcp_expect(
		   const char		*host, 
		   unsigned short	port,
		   const char		*request, 
		   const char		*expect, 
		   const char		*sendtoclose, 
		   int				*value_int
	   )
{
	zbx_sock_t	s;
	char	*buf;
	int	ret;

	assert(value_int);

	*value_int = 0;

	if (SUCCEED == (ret = zbx_tcp_connect(&s, host, port, 0))) {
		if( NULL == request )
		{
			*value_int = 1;
		}
		else if( SUCCEED == (ret = zbx_tcp_send_raw(&s, request)) )
		{
			if( NULL == expect )
			{
				*value_int = 1;
			}
			else if( SUCCEED == (ret = zbx_tcp_recv(&s, &buf)) )
			{
				if( 0 == strncmp(buf, expect, strlen(expect)) )
				{
					*value_int = 1;
				}
			}

			if(SUCCEED == ret && NULL != sendtoclose)
			{
				/* ret = (skip errors) */ zbx_tcp_send_raw(&s, sendtoclose);
			}
		}
	}
	zbx_tcp_close(&s);

	if( FAIL == ret )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "TCP expect error: %s", zbx_tcp_strerror());
	}

	return SYSINFO_RET_OK;
}

int	TCP_LISTEN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#ifdef HAVE_PROC
	FILE	*f = NULL;
	char	c[MAX_STRING_LEN];
	char	porthex[MAX_STRING_LEN];
	char	pattern[MAX_STRING_LEN];
	int	ret = SYSINFO_RET_FAIL;

        assert(result);

        init_result(result);	

        if(num_param(param) > 1)
        {
                return SYSINFO_RET_FAIL;
        }

        if(get_param(param, 1, porthex, MAX_STRING_LEN) != 0)
        {
                return SYSINFO_RET_FAIL;
        }	
	
	strscpy(pattern,porthex);
	zbx_strlcat(pattern," 00000000:0000 0A", MAX_STRING_LEN);

	if(NULL == (f = fopen("/proc/net/tcp","r")))
	{
		return	SYSINFO_RET_FAIL;
	}

	while (NULL != fgets(c,MAX_STRING_LEN,f))
	{
		if(NULL != strstr(c,pattern))
		{
			SET_UI64_RESULT(result, 1);
			ret = SYSINFO_RET_OK;
			break;
		}
	}
	zbx_fclose(f);

	SET_UI64_RESULT(result, 0);
	
	return ret;
#else
	return	SYSINFO_RET_FAIL;
#endif
}

int	CHECK_PORT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	short	port=0;
	int	value_int;
	int	ret;
	char	ip[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN];

        assert(result);

	init_result(result);
		
    if(num_param(param) > 2)
    {
        return SYSINFO_RET_FAIL;
    }
        
	if(get_param(param, 1, ip, MAX_STRING_LEN) != 0)
    {
           ip[0] = '\0';
    }
	
	if(ip[0] == '\0')
	{
		strscpy(ip, "127.0.0.1");
	}

	if(get_param(param, 2, port_str, MAX_STRING_LEN) != 0)
    {
        port_str[0] = '\0';
    }

	if(port_str[0] == '\0')
	{
		return SYSINFO_RET_FAIL;
	}

	port=atoi(port_str);

	ret = tcp_expect(ip,port,NULL,NULL,"",&value_int);
		
	if(ret == SYSINFO_RET_OK)
	{
		SET_UI64_RESULT(result, value_int);
	}
	
	return ret;
}


int	CHECK_DNS(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#if !defined(_WINDOWS)
#ifdef HAVE_RES_QUERY

#if !defined(PACKETSZ)
#	define PACKETSZ 512
#endif /* PACKETSZ */

#if !defined(C_IN) 
#	define C_IN 	ns_c_in
#endif /* C_IN */

#if !defined(T_SOA)
#	define T_SOA	ns_t_soa
#endif /* T_SOA */


	int	res;
	char	ip[MAX_STRING_LEN];
	char	zone[MAX_STRING_LEN];
#ifdef	PACKETSZ
	char	respbuf[PACKETSZ];
#else
	char	respbuf[NS_PACKETSZ];
#endif
	struct	in_addr in;

	/* extern char *h_errlist[]; */

        assert(result);

        init_result(result);
	
        if(num_param(param) > 2)
        {
                return SYSINFO_RET_FAIL;
        }
        
	if(get_param(param, 1, ip, MAX_STRING_LEN) != 0)
        {
               ip[0] = '\0';
        }
	
	if(ip[0] == '\0')
	{
		strscpy(ip, "127.0.0.1");
	}

	if(get_param(param, 2, zone, MAX_STRING_LEN) != 0)
        {
                zone[0] = '\0';
        }

	if(zone[0] == '\0')
	{
		strscpy(zone, "localhost");
	}

	res = inet_aton(ip, &in);
	if(res != 1)
	{
		SET_UI64_RESULT(result,0);
		return SYSINFO_RET_FAIL;
	}

	res_init();

	res = res_query(zone, C_IN, T_SOA, (unsigned char *)respbuf, sizeof(respbuf));

	SET_UI64_RESULT(result, res != -1 ? 1 : 0);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* not HAVE_RES_QUERY */
	return SYSINFO_RET_FAIL;
#else
	return SYSINFO_RET_FAIL;
#endif /* not WINDOWS */
}
