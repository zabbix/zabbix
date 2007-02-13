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

static FILE	*fpid = NULL;
static int	fdpid = -1;

int	create_pid_file(const char *pidfile)
{
	struct  stat    buf;
	int fd = 0;

	/* check if pid file already exists */
	if(stat(pidfile,&buf) == 0)
	{
		if( -1 == (fd = open(pidfile, O_APPEND)))
		{
			zbx_error("Cannot open PID file [%s] [%s]", pidfile, strerror(errno));
			zabbix_log( LOG_LEVEL_CRIT, "Cannot open PID file [%s] [%s]", pidfile, strerror(errno));
			return FAIL;
		}
		if(-1 == flock(fd, LOCK_EX | LOCK_NB) && EWOULDBLOCK == errno)
		{
			zbx_error("File [%s] exists and locked. Is this process already running ?", pidfile);
			zabbix_log( LOG_LEVEL_CRIT, "File [%s] exists and locked. Is this process already running ?", pidfile);
			return FAIL;
		}
		close(fd);
	}

	/* open pid file */
	if( NULL == (fpid = fopen(pidfile, "w")))
	{
		zbx_error("Cannot create PID file [%s] [%s]", pidfile, strerror(errno));
		zabbix_log( LOG_LEVEL_CRIT, "Cannot create PID file [%s] [%s]", pidfile, strerror(errno));

		return FAIL;
	}

	/* lock file */
	fdpid = fileno(fpid);
	if(-1 != fdpid) flock(fdpid, LOCK_EX);

	/* frite pid to file */
	fprintf(fpid, "%li", zbx_get_thread_id());
	fflush(fpid);

	return SUCCEED;
}

void	drop_pid_file(const char *pidfile)
{
	/* unlock file */
	if(-1 != fdpid) flock(fdpid, LOCK_UN);

	/* close pid file */
	zbx_fclose(fpid);

	if(-1 == unlink(pidfile))
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Cannot remove PID file [%s] [%s]", pidfile, strerror(errno));
	}
}
