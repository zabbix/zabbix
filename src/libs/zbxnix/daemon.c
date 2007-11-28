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
#include "daemon.h"

#include "mutexs.h"
#include "pid.h"
#include "cfg.h"
#include "log.h"

char	*APP_PID_FILE	= NULL;

static int	parent = 0;

#define uninit() { if(parent == 1) zbx_on_exit(); }

void	child_signal_handler(int sig)
{
	switch(sig)
	{
	case SIGALRM:
		signal(SIGALRM , child_signal_handler);
		zabbix_log( LOG_LEVEL_WARNING, "Timeout while answering request");
		break;
	case SIGQUIT:
	case SIGINT:
	case SIGTERM:
		zabbix_log( LOG_LEVEL_DEBUG, "Got signal. Exiting ...");
		uninit();
		exit( FAIL );
		break;
	case SIGPIPE:
		zabbix_log( LOG_LEVEL_WARNING, "Got SIGPIPE. Where it came from???");
		break;
	default:
		zabbix_log( LOG_LEVEL_WARNING, "Got signal [%d]. Ignoring ...", sig);
	}
}

static void	parent_signal_handler(int sig)
{
	switch(sig)
	{
	case SIGCHLD:
		zabbix_log( LOG_LEVEL_WARNING, "One child process died. Exiting ...");
		uninit();
		exit( FAIL );
		break;
	default:
		child_signal_handler(sig);
	}
}


/******************************************************************************
 *                                                                            *
 * Function: daemon_start                                                     *
 *                                                                            *
 * Purpose: init process as daemon                                            *
 *                                                                            *
 * Parameters: allow_root - allow root permision for application              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: it doesn't allow running under 'root' if allow_root is zerro     *
 *                                                                            *
 ******************************************************************************/

int	daemon_start(int allow_root)
{
	pid_t   		pid;
	struct passwd		*pwd;
	struct sigaction	phan;
	char			user[7] = "zabbix";

	/* running as root ?*/
	if((0 == allow_root) && (0 == getuid() || 0 == getgid()))
	{
		pwd = getpwnam(user);
		if (NULL == pwd)
		{
			zbx_error("User %s does not exist.",
				user);
			zbx_error("Cannot run as root !");
			exit(FAIL);
		}
		if(setgid(pwd->pw_gid) ==-1)
		{
			zbx_error("Cannot setgid to %s [%s].",
				user,
				strerror(errno));
			exit(FAIL);
		}
#ifdef HAVE_FUNCTION_SETEUID
		if(initgroups(user, pwd->pw_gid) == -1) 
		{
			zbx_error("Cannot initgroups to %s [%s].",
				user,
				strerror(errno));
			exit(FAIL);
		}
#endif /* HAVE_FUNCTION_INITGROUPS */
		if(setuid(pwd->pw_uid) == -1)
		{
			zbx_error("Cannot setuid to %s [%s].",
				user,
				strerror(errno));
			exit(FAIL);
		}

#ifdef HAVE_FUNCTION_SETEUID
		if( (setegid(pwd->pw_gid) ==-1) || (seteuid(pwd->pw_uid) == -1) )
		{
			zbx_error("Cannot setegid or seteuid to zabbix [%s].", strerror(errno));
			exit(FAIL);
		}
#endif /* HAVE_FUNCTION_SETEUID */

	}

	if( (pid = zbx_fork()) != 0 )	
	{				
		exit( 0 );		
	}				

	setsid();
	
	signal( SIGHUP, SIG_IGN );

	if( (pid = zbx_fork()) !=0 )	
	{				
		exit( 0 );		
	}				

	/* This is to eliminate warning: ignoring return value of chdir */
	if(-1 == chdir("/"))
	{
		assert(0);
	}
	umask(0002);

	redirect_std(CONFIG_LOG_FILE);

#ifdef HAVE_SYS_RESOURCE_SETPRIORITY

	if(setpriority(PRIO_PROCESS,0,5)!=0)
	{
		zbx_error("Unable to set process priority to 5. Leaving default.");
	}

#endif /* HAVE_SYS_RESOURCE_SETPRIORITY */

/*------------------------------------------------*/

	if( FAIL == create_pid_file(APP_PID_FILE))
	{
		exit(FAIL);
	}

	phan.sa_handler = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;

	sigaction(SIGINT,	&phan, NULL);
	sigaction(SIGQUIT,	&phan, NULL);
	sigaction(SIGTERM,	&phan, NULL);
	sigaction(SIGPIPE,	&phan, NULL);

	zbx_setproctitle("main process");

	return MAIN_ZABBIX_ENTRY();
}

void	daemon_stop(void)
{	
	drop_pid_file(APP_PID_FILE);
}

void	init_main_process(void)
{
	struct sigaction	phan;
	
	parent = 1; /* signalize signal_handler what this process isi a PARENT process */
	
	phan.sa_handler = parent_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;

	/* For parent only. To avoid problems with EXECUTE_INT */
	sigaction(SIGCHLD,	&phan, NULL);
}

