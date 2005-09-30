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
#include "config.h"

#include <string.h>
#include <stdio.h>

int	get_param(const char *param, int num, char *buf, int maxlen)
{
	char	tmp[MAX_STRING_LEN];
	char	*s;
	int	ret = 1;
	int	i=0;

	strscpy(tmp,param);
	s=(char *)strtok(tmp,",");
	while(s!=NULL)
	{
		i++;
		if(i == num)
		{
			strncpy(buf,s,maxlen);
			ret = 0;
			break;
		}
		s=(char *)strtok(NULL,";");
	}

	return ret;
}
