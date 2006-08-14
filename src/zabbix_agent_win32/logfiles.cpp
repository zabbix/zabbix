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

/*
#include <errno.h>
#include <stdio.h>
#include <string.h>
*/
#include <io.h>

/*
#include <unistd.h>

#include "common.h"

#include "log.h"
#include "logfiles.h"
 */

#include "zabbixw32.h"

int   process_log(char *filename,int *lastlogsize, char *value)
{
	FILE	*f;

LOG_FUNC_CALL("In process_log()");
INIT_CHECK_MEMORY(main);

	f=fopen(filename,"r");
	if(NULL == f)
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Cannot open [%s] [%s]", filename, strerror(errno));
		sprintf(value,"%s","ZBX_NOTSUPPORTED\n");
CHECK_MEMORY(main, "process_log", "fopen");
		return 1;
	}

	if(_filelength(_fileno(f)) < *lastlogsize)
	{
		*lastlogsize=0;
	}

	if(-1 == fseek(f,*lastlogsize,SEEK_SET))
	{
//		zabbix_log( LOG_LEVEL_WARNING, "Cannot set postition to [%d] for [%s] [%s]", *lastlogsize, filename, strerror(errno));
		sprintf(value,"%s","ZBX_NOTSUPPORTED\n");
		fclose(f);
CHECK_MEMORY(main, "process_log", "fseek");
		return 1;
	}

	if(NULL == fgets(value, MAX_STRING_LEN-1, f))
	{
		/* EOF */
		fclose(f);
CHECK_MEMORY(main, "process_log", "fgets");
		return 1;
	}
	fclose(f);

	*lastlogsize += (int)strlen(value);

CHECK_MEMORY(main, "process_log", "end");
LOG_FUNC_CALL("End of process_log()");
	return 0;
}
