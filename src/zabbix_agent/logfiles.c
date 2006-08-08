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

#include <errno.h>
#include <stdio.h>
#include <string.h>

#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>

#include "common.h"

#include "log.h"
#include "logfiles.h"

int   process_log(char *filename,long *lastlogsize, char *value)
{
	FILE	*f;
	struct stat	buf;

	zabbix_log( LOG_LEVEL_DEBUG, "In process log (%s,%ld)", filename, *lastlogsize);

	/* Handling of file shrinking */
	if(stat(filename,&buf) == 0)
	{
		if(buf.st_size<*lastlogsize)
		{
			*lastlogsize=0;
		}
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot open [%s] [%s]", filename, strerror(errno));
		snprintf(value,MAX_STRING_LEN-1,"%s","ZBX_NOTSUPPORTED\n");
		return 1;
	}

	f=fopen(filename,"r");
	if(NULL == f)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot open [%s] [%s]", filename, strerror(errno));
		snprintf(value,MAX_STRING_LEN-1,"%s","ZBX_NOTSUPPORTED\n");
		return 1;
	}

	if(-1 == fseek(f,*lastlogsize,SEEK_SET))
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot set postition to [%ld] for [%s] [%s]", *lastlogsize, filename, strerror(errno));
		snprintf(value,MAX_STRING_LEN-1,"%s","ZBX_NOTSUPPORTED\n");
		fclose(f);
		return 1;
	}

	if(NULL == fgets(value, MAX_STRING_LEN-1, f))
	{
		/* EOF */
		fclose(f);
		return 1;
	}
	fclose(f);

	*lastlogsize+=strlen(value);

	return 0;
}
