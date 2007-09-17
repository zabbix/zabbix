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
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: get_programm_name                                                *
 *                                                                            *
 * Purpose: return program name without path                                  *
 *                                                                            *
 * Parameters: path                                                           *
 *                                                                            *
 * Return value: program name without path                                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 *  Comments:                                                                 *
 *                                                                            *
 ******************************************************************************/
char* get_programm_name(char *path)
{
	char	*filename = NULL;

	for(filename = path; path && *path; path++)
		if(*path == '\\' || *path == '/')
			filename = path+1;

	return filename;
}

/******************************************************************************
 *                                                                            *
 * Function: get_nodeid_by_id                                                 *
 *                                                                            *
 * Purpose: Get Node ID by resource ID                                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: Node ID                                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 *  Comments:                                                                 *
 *                                                                            *
 ******************************************************************************/
int get_nodeid_by_id(zbx_uint64_t id)
{
	return (int)(id/__UINT64_C(100000000000000))%1000;

}

/******************************************************************************
 *                                                                            *
 * Function: zbx_time                                                         *
 *                                                                            *
 * Purpose: Gets the current time.                                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: Time in seconds                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 *  Comments: Time in seconds since midnight (00:00:00),                      *
 *            January 1, 1970, coordinated universal time (UTC).              *
 *                                                                            *
 ******************************************************************************/
double	zbx_time(void)
{

#if defined(_WINDOWS)

	struct _timeb current;

	_ftime(&current);

	return (((double)current.time) + 1.0e-6 * ((double)current.millitm));

#else /* not _WINDOWS */

	struct timeval current;

	gettimeofday(&current,NULL);

	return (((double)current.tv_sec) + 1.0e-6 * ((double)current.tv_usec));

#endif /* _WINDOWS */

}

/******************************************************************************
 *                                                                            *
 * Function: zbx_current_time                                                 *
 *                                                                            *
 * Purpose: Gets the current time include UTC offset                          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: Time in seconds                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/

double zbx_current_time (void)
{
	return (zbx_time() + ZBX_JAN_1970_IN_SEC);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_setproctitle                                                 *
 *                                                                            *
 * Purpose: set process title                                                 *
 *                                                                            *
 * Parameters: title - item's refresh rate in sec                             *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	__zbx_zbx_setproctitle(const char *fmt, ...)
{
#ifdef HAVE_FUNCTION_SETPROCTITLE

	char	title[MAX_STRING_LEN];

	va_list args;

	va_start(args, fmt);
	vsnprintf(title, MAX_STRING_LEN-1, fmt, args);
	va_end(args);

	setproctitle(title);

#endif /* HAVE_FUNCTION_SETPROCTITLE */
}

/******************************************************************************
 *                                                                            *
 * Function: calculate_item_nextcheck                                         *
 *                                                                            *
 * Purpose: calculate nextcheck timespamp for item                            *
 *                                                                            *
 * Parameters: delay - item's refresh rate in sec                             *
 *             now - current timestamp                                        *
 *                                                                            *
 * Return value: nextcheck value                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: Old algorithm: now+delay                                         *
 *           New one: preserve period, if delay==5, nextcheck = 0,5,10,15,... *
 *                                                                            *
 ******************************************************************************/
int	calculate_item_nextcheck(zbx_uint64_t itemid, int item_type, int delay, char *delay_flex, time_t now)
{
	int	i;
	char	*p;
	char	delay_period[30];
	int	delay_val;

	zabbix_log( LOG_LEVEL_DEBUG, "In calculate_item_nextcheck (" ZBX_FS_UI64 ",%d,%s,%d)",
		itemid,delay,delay_flex,now);

/* Special processing of active items to see better view in queue */
	if(item_type == ITEM_TYPE_ZABBIX_ACTIVE)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "End calculate_item_nextcheck (result:%d)",
			(((int)now)+delay));
		return (((int)now)+delay);
	}


	if(delay_flex && *delay_flex)
	{
		do
		{
			p = strchr(delay_flex, ';');
			if(p) *p = '\0';
			
			zabbix_log( LOG_LEVEL_DEBUG, "Delay period [%s]", delay_flex);

			if(sscanf(delay_flex, "%d/%29s",&delay_val,delay_period) == 2)
			{
				zabbix_log( LOG_LEVEL_DEBUG, "%d sec at %s",delay_val,delay_period);
				if(check_time_period(delay_period, now))
				{
					delay = delay_val;
					break;
				}
			}
			else
			{
				zabbix_log( LOG_LEVEL_ERR, "Delay period format is wrong [%s]",delay_flex);
			}
			if(p)
			{
				*p = ';'; /* restore source string */
				delay_flex = p+1;
			}
		}while(p);
		
	}

/*	Old algorithm */
/*	i=delay*(int)(now/delay);*/
	
	i=delay*(int)(now/(time_t)delay)+(int)(itemid % (zbx_uint64_t)delay);

	while(i<=now)	i+=delay;

	zabbix_log( LOG_LEVEL_DEBUG, "End calculate_item_nextcheck (result:%d)", i);
	return i;
}

#if defined(HAVE_IPV6)
/******************************************************************************
 *                                                                            *
 * Function: expand_ipv6                                                      *
 *                                                                            *
 * Purpose: convert short ipv6 addresses to expanded type                     *
 *                                                                            *
 * Parameters: ip - IPv6 IPs [12fc::2]                                        *
 *             buf - result value [12fc:0000:0000:0000:0000:0000:0000:0002]   *
 *                                                                            *
 * Return value: FAIL - invlid IP address, SUCCEED - conversion OK            *
 *                                                                            *
 * Author: Alksander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	expand_ipv6(const char *ip, char *str, size_t str_len )
{
	unsigned int	i[8]; /* x:x:x:x:x:x:x:x */
	char		buf[5], *ptr;
	int		c, dc, pos = 0, j, len, ip_len, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In expand_ipv6(ip:%s)", ip);

	c = 0; /* colons count */
	for(ptr = strchr(ip, ':'); ptr != NULL; ptr = strchr(ptr + 1, ':'))
	{
		c ++;
	}

	if(c < 2 || c > 7)
	{
		goto out;
	}

	ip_len = strlen(ip);
	if((ip[0] == ':' && ip[1] != ':') || (ip[ip_len - 1] == ':' && ip[ip_len - 2] != ':'))
	{
		goto out;
	}

	memset(i, 0x00, sizeof(i));

	dc  = 0; /* double colon flag */
	len = 0;
	for(j = 0; j<ip_len; j++)
	{
		if((ip[j] >= '0' && ip[j] <= '9') || (ip[j] >= 'A' && ip[j] <= 'F') || (ip[j] >= 'a' && ip[j] <= 'f'))
		{
			if(len > 3)
			{
				goto out;
			}
			buf[len ++] = ip[j];
		}
		else if(ip[j] != ':')
		{
			goto out;
		}

		if(ip[j] == ':' || ip[j + 1] == '\0')
		{
			if(len)
			{
				buf[len] = 0x00;
				sscanf(buf, "%x", &i[pos]);
				pos ++;
				len = 0;
			}

			if(ip[j + 1] == ':')
			{
				if(dc == 0)
				{
					dc = 1;
					pos = ( 8 - c ) + pos + (j == 0 ? 1 : 0);
				}
				else
				{
					goto out;
				}
			}
		}
	}
	zbx_snprintf(str, str_len, "%04x:%04x:%04x:%04x:%04x:%04x:%04x:%04x", i[0], i[1], i[2], i[3], i[4], i[5], i[6], i[7]);
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End expand_ipv6(ip:%s,str:%s,ret:%s)", ip, str, ret == SUCCEED ? "SUCCEED" : "FAIL");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: ip_in_list_ipv6                                                  *
 *                                                                            *
 * Purpose: check if ip matches range of ip addresses                         *
 *                                                                            *
 * Parameters: list -  IPs [12fc::2-55,::45]                                  *
 *                                                                            *
 * Return value: FAIL - out of range, SUCCEED - within the range              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	ip_in_list_ipv6(char *list, char *ip)
{
	char	*start, *comma = NULL, *dash = NULL, buffer[MAX_STRING_LEN];
	int	i[8], j[9], ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In ip_in_list(list:%s,ip:%s)", list, ip);

	if(FAIL == expand_ipv6(ip, buffer, sizeof(buffer)))
	{
		goto out;
	}

	if(sscanf(buffer, "%x:%x:%x:%x:%x:%x:%x:%x", &i[0], &i[1], &i[2], &i[3], &i[4], &i[5], &i[6], &i[7]) != 8)
	{
		goto out;
	}

	for(start = list; start[0] != '\0';)
	{

		if(NULL != (comma = strchr(start, ',')))
		{
			comma[0] = '\0';
		}

		if(NULL != (dash = strchr(start, '-')))
		{
			dash[0] = '\0';
			if(sscanf(dash + 1, "%x", &j[8]) != 1)
			{
				goto next;
			}
		}

		if(FAIL == expand_ipv6(start, buffer, sizeof(buffer)))
		{
			goto next;
		}

		if(sscanf(buffer, "%x:%x:%x:%x:%x:%x:%x:%x", &j[0], &j[1], &j[2], &j[3], &j[4], &j[5], &j[6], &j[7]) != 8)
		{
			goto next;
		}

		if(dash == NULL)
		{
			j[8] = j[7];
		}

		if(i[0] == j[0] && i[1] == j[1] && i[2] == j[2] && i[3] == j[3] && 
		   i[4] == j[4] && i[5] == j[5] && i[6] == j[6] && 
		   i[7] >= j[7] && i[7] <= j[8])
		{
			ret = SUCCEED;
			break;
		}
next:
		if(dash != NULL)
		{
			dash[0] = '-';
			dash = NULL;
		}

		if(comma != NULL)
		{
			comma[0] = ',';
			start = comma + 1;
			comma = NULL;
		}
		else
		{
			break;
		}
	}
out:
	if(dash != NULL)
	{
		dash[0] = '-';
	}

	if(comma != NULL)
	{
		comma[0] = ',';
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End ip_in_list(ret:%s)", ret == SUCCEED ? "SUCCEED" : "FAIL");
	return ret;
}
#endif /*HAVE_IPV6*/
/******************************************************************************
 *                                                                            *
 * Function: ip_in_list                                                       *
 *                                                                            *
 * Purpose: check if ip matches range of ip addresses                         *
 *                                                                            *
 * Parameters: list -  IPs [192.168.1.1-244,192.168.1.250]                    *
 *                                                                            *
 * Return value: FAIL - out of range, SUCCEED - within the range              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	ip_in_list(char *list, char *ip)
{
	int	i[4], j[5];
	int	ret = FAIL;
	char	*start = NULL, *comma = NULL, *dash = NULL;

	zabbix_log( LOG_LEVEL_DEBUG, "In ip_in_list(list:%s,ip:%s)", list, ip);

	if(sscanf(ip, "%d.%d.%d.%d", &i[0], &i[1], &i[2], &i[3]) != 4)
	{
#if defined(HAVE_IPV6)
		ret = ip_in_list_ipv6(list, ip);
#endif /*HAVE_IPV6*/
		goto out;
	}

	for(start = list; start[0] != '\0';)
	{
		if(NULL != (comma = strchr(start, ',')))
		{
			comma[0] = '\0';
		}

		if(NULL != (dash = strchr(start, '-')))
		{
			dash[0] = '\0';
			if(sscanf(dash + 1, "%d", &j[4]) != 1)
			{
				goto next;
			}
		}

		if(sscanf(start, "%d.%d.%d.%d", &j[0], &j[1], &j[2], &j[3]) != 4)
		{
			goto next;
		}

		if(dash == NULL)
		{
			j[4] = j[3];
		}

		if(i[0] == j[0] && i[1] == j[1] && i[2] == j[2] && i[3] >= j[3] && i[3] <= j[4])
		{
			ret = SUCCEED;
			break;
		}
next:
		if(dash != NULL)
		{
			dash[0] = '-';
			dash = NULL;
		}

		if(comma != NULL)
		{
			comma[0] = ',';
			start = comma + 1;
			comma = NULL;
		}
		else
		{
			break;
		}
	}

out:
	if(dash != NULL)
	{
		dash[0] = '-';
	}

	if(comma != NULL)
	{
		comma[0] = ',';
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End ip_in_list(ret:%s)", ret == SUCCEED ? "SUCCEED" : "FAIL");
	return ret;
}
/******************************************************************************
 *                                                                            *
 * Function: int_in_list                                                      *
 *                                                                            *
 * Purpose: check if integer matches a list of integers                       *
 *                                                                            *
 * Parameters: list -  integers [i1-i2,i3,i4,i5-i6] (10-25,45,67-699          *
 *             value-  value                                                  *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the period            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	int_in_list(char *list, int value)
{
	char	*start = NULL, *end = NULL;
	int	i1,i2;
	int	ret = FAIL;
	char	c = '\0';

	zabbix_log( LOG_LEVEL_DEBUG, "In int_in_list(list:%s,value:%d)", list, value);

	for(start = list; start[0] != '\0';)
	{
		end=strchr(start, ',');

		if(end != NULL)
		{	
			c=end[0];
			end[0]='\0';
		}
		
		if(sscanf(start,"%d-%d",&i1,&i2) == 2)
		{
			if(value>=i1 && value<=i2)
			{
				ret = SUCCEED;
				break;
			}
		}
		else
		{
			if(atoi(start) == value)
			{
				ret = SUCCEED;
				break;
			}
		}

		if(end != NULL)
		{
			end[0]=c;
			start=end+1;
		}
		else
		{
			break;
		}
	}

	if(end != NULL)
	{
		end[0]=c;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End int_in_list(ret:%s)", ret == SUCCEED?"SUCCEED":"FAIL");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: check_time_period                                                *
 *                                                                            *
 * Purpose: check if current time is within given period                      *
 *                                                                            *
 * Parameters: period - time period in format [d1-d2,hh:mm-hh:mm]*            *
 *             now    - timestamp for comporation                             *
 *                      if NULL - use current timestamp.                      *
 *                                                                            *
 * Return value: 0 - out of period, 1 - within the period                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	check_time_period(const char *period, time_t now)
{
	char	tmp[MAX_STRING_LEN];
	char	*s;
	int	d1,d2,h1,h2,m1,m2;
	int	day, hour, min;
	struct  tm      *tm;
	int	ret = 0;


	zabbix_log( LOG_LEVEL_DEBUG, "In check_time_period(%s)",period);

	if(now == (time_t)NULL)	now = time(NULL);
	
	tm = localtime(&now);

	day=tm->tm_wday;
	if(0 == day)	day=7;
	hour = tm->tm_hour;
	min = tm->tm_min;

	strscpy(tmp,period);
       	s=(char *)strtok(tmp,";");
	while(s!=NULL)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Period [%s]",s);

		if(sscanf(s,"%d-%d,%d:%d-%d:%d",&d1,&d2,&h1,&m1,&h2,&m2) == 6)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "%d-%d,%d:%d-%d:%d",d1,d2,h1,m1,h2,m2);
			if( (day>=d1) && (day<=d2) && (60*hour+min>=60*h1+m1) && (60*hour+min<=60*h2+m2))
			{
				ret = 1;
				break;
			}
		}
		else
		{
			zabbix_log( LOG_LEVEL_ERR, "Time period format is wrong [%s]",period);
		}

       		s=(char *)strtok(NULL,";");
	}
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: cmp_double                                                       *
 *                                                                            *
 * Purpose: compares two double values                                        *
 *                                                                            *
 * Parameters: a,b - doubled to compare                                       *
 *                                                                            *
 * Return value:  0 - the values are equal                                    *
 *                1 - otherwise                                               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: equal == differs less than 0.000001                              *
 *                                                                            *
 ******************************************************************************/
int	cmp_double(double a,double b)
{
	if(fabs(a-b)<0.000001)
	{
		return	0;
	}
	return	1;
}

/******************************************************************************
 *                                                                            *
 * Function: is_double_prefix                                                 *
 *                                                                            *
 * Purpose: check if the string is double                                     *
 *                                                                            *
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the string is double                              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: the functions support prefixes K,M,G                             *
 *                                                                            *
 ******************************************************************************/
int	is_double_prefix(char *c)
{
	int i;
	int dot=-1;

	for(i=0;c[i]!=0;i++)
	{
		/* Negative number? */
		if(c[i]=='-' && i==0)
		{
			continue;
		}

		if((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}

		if((c[i]=='.')&&(dot==-1))
		{
			dot=i;

			if((dot!=0)&&(dot!=(int)strlen(c)-1))
			{
				continue;
			}
		}
		/* Last digit is prefix 'K', 'M', 'G' */
		if( ((c[i]=='K')||(c[i]=='M')||(c[i]=='G')) && (i == (int)strlen(c)-1))
		{
			continue;
		}

		return FAIL;
	}
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_double                                                        *
 *                                                                            *
 * Purpose: check if the string is double                                     *
 *                                                                            *
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the string is double                              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
/*int is_double(char *str)
{
	const char *endstr = str + strlen(str);
	char *endptr = NULL;
	double x;
       
	x = strtod(str, &endptr);

	if(endptr == str || errno != 0)
		return FAIL;
	if (endptr == endstr)
		return SUCCEED;
	return FAIL;
}*/

int	is_double(char *c)
{
	int i;
	int dot=-1;
	int len;

	for(i=0; c[i]==' ' && c[i]!=0;i++); /* trim left spaces */

	for(len=0; c[i]!=0; i++, len++)
	{
		/* Negative number? */
		if(c[i]=='-' && i==0)
		{
			continue;
		}

		if((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}

		if((c[i]=='.')&&(dot==-1))
		{
			dot=i;
			continue;
		}

		if(c[i]==' ') /* check right spaces */
		{
			for( ; c[i]==' ' && c[i]!=0;i++); /* trim right spaces */
			
			if(c[i]==0) break; /* SUCCEED */
		}

		return FAIL;
	}
	
	if(len <= 0) return FAIL;

	if(len == 1 && dot!=-1) return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: is_uint                                                          *
 *                                                                            *
 * Purpose: check if the string is unsigned integer                           *
 *                                                                            *
 * Parameters: c - string to check                                            *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	is_uint(char *c)
{
	int	i;
	int	len;

	for(i=0; c[i]==' ' && c[i]!=0;i++); /* trim left spaces */
	
	for(len=0; c[i]!=0; i++,len++)
	{
		if((c[i]>='0')&&(c[i]<='9'))
		{
			continue;
		}
		
		if(c[i]==' ') /* check right spaces */
		{
			for( ; c[i]==' ' && c[i]!=0;i++); /* trim right spaces */
			
			if(c[i]==0) break; /* SUCCEED */
		}
		return FAIL;
	}

	if(len <= 0) return FAIL;
	
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: uint64_in_list                                                   *
 *                                                                            *
 * Purpose: check if uin64 integer matches a list of integers                 *
 *                                                                            *
 * Parameters: list -  integers [i1-i2,i3,i4,i5-i6] (10-25,45,67-699          *
 *             value-  value                                                  *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the list              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	uint64_in_list(char *list, zbx_uint64_t value)
{
	char		*start = NULL, *end = NULL;
	zbx_uint64_t	i1,i2,tmp_uint64;
	int		ret = FAIL;
	char		c = '\0';

	zabbix_log( LOG_LEVEL_DEBUG, "In int_in_list(list:%s,value:" ZBX_FS_UI64 ")", list, value);

	for(start = list; start[0] != '\0';)
	{
		end=strchr(start, ',');

		if(end != NULL)
		{	
			c=end[0];
			end[0]='\0';
		}
		
		if(sscanf(start,ZBX_FS_UI64 "-" ZBX_FS_UI64,&i1,&i2) == 2)
		{
			if(value>=i1 && value<=i2)
			{
				ret = SUCCEED;
				break;
			}
		}
		else
		{
			ZBX_STR2UINT64(tmp_uint64,start);
			if(tmp_uint64 == value)
			{
				ret = SUCCEED;
				break;
			}
		}

		if(end != NULL)
		{
			end[0]=c;
			start=end+1;
		}
		else
		{
			break;
		}
	}

	if(end != NULL)
	{
		end[0]=c;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End int_in_list(ret:%s)", ret == SUCCEED?"SUCCEED":"FAIL");

	return ret;
}
