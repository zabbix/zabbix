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

/* Get DATA from <tag>DATA</tag> */
int xml_get_data(char *xml,char *tag, char *data, int maxlen)
{
	int ret = SUCCEED;
	char *start, *end;
	char tag_open[MAX_STRING_LEN];
	char tag_close[MAX_STRING_LEN];
	int len;

	zbx_snprintf(tag_open, sizeof(tag_open),"<%s>",tag);
	zbx_snprintf(tag_close, sizeof(tag_close),"</%s>",tag);

	if(NULL==(start=strstr(xml,tag_open)))
	{
		ret = FAIL;
	}

	if(NULL==(end=strstr(xml,tag_close)))
	{
		ret = FAIL;
	}

	if(ret == SUCCEED)
	{
		if(end<start)
		{
			ret = FAIL;
		}
	}

	if(ret == SUCCEED)
	{
		len=end-(start+strlen(tag_open));

		if(len>maxlen)	len=maxlen;

		strncpy(data, start+strlen(tag_open),len);
	}

	return ret;
}
