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
#include "pid.h"

#include "log.h"
#include "threads.h"

int	create_pid_file(const char *pidfile)
{
	FILE	*f = NULL;

	/* check if pid file already exists */
	if( NULL != (f = fopen(pidfile, "r")) )
	{
		zbx_error("File [%s] exists. Is this process already running ?", pidfile);
		zabbix_log( LOG_LEVEL_CRIT, "File [%s] exists. Is this process already running ?", pidfile);
		
		zbx_fclose(f);

		return FAIL;
	}

	/* open pid file */
	if( NULL == (f = fopen(pidfile, "w")))
	{
		zbx_error("Cannot create PID file [%s] [%s]", pidfile, strerror(errno));
		zabbix_log( LOG_LEVEL_CRIT, "Cannot create PID file [%s] [%s]", pidfile, strerror(errno));

		return FAIL;
	}

	/* frite pid to file */
	fprintf(f, "%li", zbx_get_thread_id());

	/* close pid file */
	zbx_fclose(f);

	return SUCCEED;
}

void	drop_pid_file(const char *pidfile)
{
	if(unlink(pidfile) != 0)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Cannot remove PID file [%s]", pidfile);
	}
}
