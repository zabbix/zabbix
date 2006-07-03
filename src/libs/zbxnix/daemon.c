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

#include "pid.h"
#include "log.h"
#include "cfg.h"

static int	parent=0;

static void	uninit(void)
{
	int i;

	if(parent == 1)
	{
		on_exit();
	}
}

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
		zabbix_log( LOG_LEVEL_WARNING, "Got signal. Exiting ...");
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



void    init_daemon(void)
{
	int     i;
	pid_t   pid;
	struct passwd	*pwd;
	struct sigaction	phan;

	/* running as root ?*/
	if((0 == CONFIG_ALLOW_ROOT_PERMISSION) && (0 == getuid() || 0 == getgid()))
	{
		pwd = getpwnam("zabbix");
		if (NULL == pwd)
		{
			zbx_error("User zabbix does not exist.");
			zbx_error("Cannot run as root !");
			exit(FAIL);
		}
		if( (setgid(pwd->pw_gid) ==-1) || (setuid(pwd->pw_uid) == -1) )
		{
			zbx_error("Cannot setgid or setuid to zabbix [%s].", strerror(errno));
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

	if( (pid = fork()) != 0 )	/* ???? Why and Why "!= 0" possiable " < 0" ???? - by Eugene */
	{				/* ???? Why and Why "!= 0" possiable " < 0" ???? - by Eugene */
		exit( 0 );		/* ???? Why and Why "!= 0" possiable " < 0" ???? - by Eugene */
	}				/* ???? Why and Why "!= 0" possiable " < 0" ???? - by Eugene */

	setsid();
	
	signal( SIGHUP, SIG_IGN );

	if( (pid = fork()) !=0 )	/* ???? Why and Why "!= 0" possiable " < 0" ???? - by Eugene */
	{				/* ???? Why and Why "!= 0" possiable " < 0" ???? - by Eugene */
		exit( 0 );		/* ???? Why and Why "!= 0" possiable " < 0" ???? - by Eugene */
	}				/* ???? Why and Why "!= 0" possiable " < 0" ???? - by Eugene */

	chdir("/");
	umask(022);

	for(i=0; i<MAXFD; i++)
	{
		/* Do not close stderr */
		if(i != fileno(stderr)) close(i);
	}

/*	openlog("zabbix_agentd",LOG_LEVEL_PID,LOG_USER);
	setlogmask(LOG_UPTO(LOG_WARNING));*/


#ifdef HAVE_SYS_RESOURCE_SETPRIORITY
	if(setpriority(PRIO_PROCESS,0,5)!=0)
	{
		zbx_error("Unable to set process priority to 5. Leaving default.");
	}
#endif /* HAVE_SYS_RESOURCE_SETPRIORITY */


//------------------------------------------------

	if( FAIL == create_pid_file(CONFIG_PID_FILE))
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

	MAIN_ZABBIX_ENTRY();
}

void	init_parent_process(void)
{
	struct sigaction	phan;
	
	parent = 1; /* signalize signal_handler what this process isi a PARENT process */
	
	phan.sa_handler = parent_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;

	/* For parent only. To avoid problems with EXECUTE_INT */
	sigaction(SIGCHLD,	&phan, NULL);
}

