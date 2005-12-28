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
	char	s[MAX_STRING_LEN];
	char	param[MAX_STRING_LEN];
	char	error[MAX_STRING_LEN];
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
			snprintf(c,sizeof(c)-1,"net.tcp.service[%s]",item->key);
		else
		{
			strncpy( param,l+1, r-l-1);
			param[r-l-1]=0;
			if(item->useip==1)
			{
				snprintf(c,sizeof(c)-1,"net.tcp.service[%s,%s]",item->key,item->ip);
			}
			else
			{
				snprintf(c,sizeof(c)-1,"net.tcp.service[%s,%s]",item->key,item->host);
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
				snprintf(c,sizeof(c)-1,"%s",item->key);
			else
			{
				strncpy( param,l+1, r-l-1);
				param[r-l-1]=0;
/*				snprintf(c,sizeof(c)-1,"dns[%s,%s]",item->ip,param);*/
				snprintf(c,sizeof(c)-1,"dns[%s]",param);
			}
		}
		else
		{
			snprintf(error,MAX_STRING_LEN-1,"You must use IP address in Host %s definition", item->host);
			zabbix_log( LOG_LEVEL_WARNING, error);
			result->str=strdup(error);
			return NOTSUPPORTED;
		}
	}
	else if(NULL == strstr(item->key,"_perf"))
	{
		if(item->useip==1)
		{
			snprintf(c,sizeof(c)-1,"net.tcp.service[%s,%s]",item->key,item->ip);
		}
		else
		{
			snprintf(c,sizeof(c)-1,"net.tcp.service[%s,%s]",item->key,item->host);
		}
	}
	else
	{
		strscpy(s,item->key);
		t=strstr(s,"_perf");
		t[0]=0;
		
		if(item->useip==1)
		{
			snprintf(c,sizeof(c)-1,"net.tcp.service.perf[%s,%s]",s,item->ip);
		}
		else
		{
			snprintf(c,sizeof(c)-1,"net.tcp.service.perf[%s,%s]",s,item->host);
		}
	}

	if(process(c, 0, result) == NOTSUPPORTED)
	{
		snprintf(error,MAX_STRING_LEN-1,"Simple check [%s] is not supported", c);
		zabbix_log( LOG_LEVEL_WARNING, error);
		result->str=strdup(error);
		ret = NOTSUPPORTED;
	}

	return ret;
}
