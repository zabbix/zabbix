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

#include "checks_simple.h"

int	get_value_simple(DB_ITEM *item, AGENT_RESULT *result)
{
	char	*t;
	char	c[MAX_STRING_LEN];
	char	param[MAX_STRING_LEN];
	char	error[MAX_STRING_LEN];
	char	service[MAX_STRING_LEN];
	char	service_sysinfo[MAX_STRING_LEN];
	char	ip[MAX_STRING_LEN];
	char	port[MAX_STRING_LEN];
	int	port_int=0;
	int	ret = SUCCEED;
	char	*l,*r;
	/* Assumption: host name does not contain '_perf'	*/

	init_result(result);

	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_simple([%s]", item->key);

	if(0 == strncmp(item->key,"service.ntp",11))
	{
		l=strstr(item->key,"[");
		r=strstr(item->key,"]");
		if(l==NULL || r==NULL)
			zbx_snprintf(c,sizeof(c),"net.tcp.service[%s]",item->key);
		else
		{
			strncpy( param,l+1, r-l-1);
			param[r-l-1]=0;
			if(item->useip==1)
			{
				zbx_snprintf(c,sizeof(c),"net.tcp.service[%s,%s]",item->key,item->ip);
			}
			else
			{
				zbx_snprintf(c,sizeof(c),"net.tcp.service[%s,%s]",item->key,item->host);
			}
		}
	}
	else if(0 == strncmp(item->key,"dns",3))
	{
		if(item->useip==1)
		{
			l=strstr(item->key,"[");
			r=strstr(item->key,"]");
			if(l==NULL || r==NULL)
				zbx_snprintf(c,sizeof(c),"%s",item->key);
			else
			{
				strncpy( param,l+1, r-l-1);
				param[r-l-1]=0;
/*				zbx_snprintf(c,sizeof(c),"dns[%s,%s]",item->ip,param);*/
				zbx_snprintf(c,sizeof(c),"dns[%s]",param);
			}
		}
		else
		{
			zbx_snprintf(error,sizeof(error),"You must use IP address in Host %s definition", item->host);
			zabbix_log( LOG_LEVEL_WARNING, error);
			result->str=strdup(error);
			return NOTSUPPORTED;
		}
	}
	else
	{
		ip[0]=0;
		port[0]=0;
		service[0]=0;
		if(num_param(item->key) == 1)
		{
			if(get_param(item->key, 1, service, MAX_STRING_LEN) != 0)
			{
				ret = NOTSUPPORTED;
			}
		}
		else if(num_param(item->key) == 2)
		{
			if(get_param(item->key, 1, service, MAX_STRING_LEN) != 0)
			{
				ret = NOTSUPPORTED;
			}
			if(get_param(item->key, 2, port, MAX_STRING_LEN) != 0)
			{
				ret = NOTSUPPORTED;
			}
			else if(is_uint(port)==SUCCEED)
			{
				port_int=atoi(port);
			}
			else
			{
				zbx_snprintf(error,sizeof(error),"Port number must be numeric in [%s]", item->key);
				zabbix_log( LOG_LEVEL_WARNING, error);
				result->str=strdup(error);
				ret = NOTSUPPORTED;
			}
		}
		else
		{
			zbx_snprintf(error,sizeof(error),"Too many parameters in [%s]", item->key);
			zabbix_log( LOG_LEVEL_WARNING, error);
			result->str=strdup(error);
			ret = NOTSUPPORTED;
		}

		if(ret == SUCCEED)
		{
			if(item->useip==1)
			{
				strscpy(ip,item->ip);
			}
			else
			{
				strscpy(ip,item->host);
			}

			t = strstr(service,"_perf");
			if(t != NULL)
			{
				t[0]=0;
				strscpy(service_sysinfo,"net.tcp.service.perf");
			}
			else	strscpy(service_sysinfo,"net.tcp.service");

			if(port_int == 0)
			{
				zbx_snprintf(c,sizeof(c),"%s[%s,%s]",service_sysinfo,service,ip);
			}
			else
			{
				zbx_snprintf(c,sizeof(c),"%s[%s,%s,%d]",service_sysinfo,service,ip,port_int);
			}
			zabbix_log( LOG_LEVEL_DEBUG, "Sysinfo [%s]", c);
		}
		else
		{
			return ret;
		}
	}
/*
	else if(NULL == strstr(item->key,"_perf"))
	{
		if(item->useip==1)
		{
			zbx_snprintf(c,sizeof(c),"net.tcp.service[%s,%s]",item->key,item->ip);
		}
		else
		{
			zbx_snprintf(c,sizeof(c),"net.tcp.service[%s,%s]",item->key,item->host);
		}
	}
	else
	{
		strscpy(s,item->key);
		t=strstr(s,"_perf");
		t[0]=0;
		
		if(item->useip==1)
		{
			zbx_snprintf(c,sizeof(c),"net.tcp.service.perf[%s,%s]",s,item->ip);
		}
		else
		{
			zbx_snprintf(c,sizeof(c),"net.tcp.service.perf[%s,%s]",s,item->host);
		}
	}
*/

	if(process(c, 0, result) == NOTSUPPORTED)
	{
		zbx_snprintf(error,sizeof(error),"Simple check [%s] is not supported", c);
		zabbix_log( LOG_LEVEL_WARNING, error);
		result->str=strdup(error);
		ret = NOTSUPPORTED;
	}

	return ret;
}
