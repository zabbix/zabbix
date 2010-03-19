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

#include <sysinc.h>

static FILE	*fpid = NULL;
static int	fdpid = -1;

int	create_pid_file(const char *pidfile)
{
	struct  stat    buf;
	int fd = 0;

#ifdef HAVE_FCNTL_H
	struct flock fl;

	fl.l_type   = F_WRLCK;  /* F_RDLCK, F_WRLCK, F_UNLCK    */
	fl.l_whence = SEEK_SET; /* SEEK_SET, SEEK_CUR, SEEK_END */
	fl.l_start  = 0;        /* Offset from l_whence         */
	fl.l_len    = 0;        /* length, 0 = to EOF           */
	fl.l_pid    = zbx_get_thread_id(); /* our PID           */

#endif /* HAVE_FCNTL_H */

	/* check if pid file already exists */
	if(stat(pidfile,&buf) == 0)
	{
#ifdef HAVE_FCNTL_H
		if( -1 == (fd = open(pidfile, O_WRONLY | O_APPEND)))
#else
		if( -1 == (fd = open(pidfile, O_APPEND)))
#endif /* HAVE_FCNTL_H */
		{
			zbx_error("Cannot open PID file [%s] [%s]", pidfile, strerror(errno));
			zabbix_log( LOG_LEVEL_CRIT, "Cannot open PID file [%s] [%s]", pidfile, strerror(errno));
			return FAIL;
		}
#ifdef HAVE_FCNTL_H
		if(-1 == fcntl(fd, F_SETLK, &fl) && EAGAIN == errno)
#else
		if(-1 == flock(fd, LOCK_EX | LOCK_NB) && EWOULDBLOCK == errno)
#endif /* HAVE_FCNTL_H */
		{
			zbx_error("File [%s] exists and is locked. Is this process already running ?", pidfile);
			zabbix_log( LOG_LEVEL_CRIT, "File [%s] exists and is locked. Is this process already running ?", pidfile);
			close(fd);
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
#ifdef HAVE_FCNTL_H
	if(-1 != fdpid) fcntl(fdpid, F_SETLK, &fl);
#else
	if(-1 != fdpid) flock(fdpid, LOCK_EX);
#endif /* HAVE_FCNTL_H */

	/* frite pid to file */
	fprintf(fpid, "%li", zbx_get_thread_id());
	fflush(fpid);

	return SUCCEED;
}

void	drop_pid_file(const char *pidfile)
{
#ifdef HAVE_FCNTL_H
	struct flock fl;

	fl.l_type   = F_UNLCK;  /* tell it to unlock the region */
	fl.l_whence = SEEK_SET; /* SEEK_SET, SEEK_CUR, SEEK_END */
	fl.l_start  = 0;        /* Offset from l_whence         */
	fl.l_len    = 0;        /* length, 0 = to EOF           */
	fl.l_pid    = zbx_get_thread_id(); /* our PID           */

#endif /* HAVE_FCNTL_H */

	/* unlock file */
#ifdef HAVE_FCNTL_H
	if(-1 != fdpid) fcntl(fdpid, F_SETLK, &fl);
#else
	if(-1 != fdpid) flock(fdpid, LOCK_UN);
#endif /* HAVE_FCNTL_H */

	/* close pid file */
	zbx_fclose(fpid);

	if(-1 == unlink(pidfile))
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Cannot remove PID file [%s] [%s]", pidfile, strerror(errno));
	}
}
