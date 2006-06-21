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

int	create_pid_file(const char *pidfile)
{
	FILE	*f;

/* Check if PID file already exists */
	f = fopen(pidfile, "r");
	if(f != NULL)
	{
		zbx_error("File [%s] exists. Is this process already running ?", pidfile);
		zabbix_log( LOG_LEVEL_CRIT, "File [%s] exists. Is this process already running ?",
			pidfile);
		if(fclose(f) != 0)
		{
			zbx_error("Cannot close file [%s] [%s]", pidfile, strerror(errno));
			zabbix_log( LOG_LEVEL_WARNING, "Cannot close file [%s] [%s]",
				pidfile, strerror(errno));
		}

		return FAIL;
	}

	f = fopen(pidfile, "w");

	if( f == NULL)
	{
		zbx_error("Cannot create PID file [%s] [%s]",
			pidfile, strerror(errno));
		zabbix_log( LOG_LEVEL_CRIT, "Cannot create PID file [%s] [%s]",
			pidfile, strerror(errno));

		return FAIL;
	}

	fprintf(f,"%d",zbx_get_thread_id);
	if(fclose(f) != 0)
	{
		zbx_error("Cannot close file [%s] [%s]", pidfile, strerror(errno));
		zabbix_log( LOG_LEVEL_WARNING, "Cannot close file [%s] [%s]",
			pidfile, strerror(errno));
	}

	return SUCCEED;
}
