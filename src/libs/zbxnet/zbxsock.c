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
#include "zbxsock.h"

#include "log.h"

#if !defined(WIN32)

static void	sock_signal_handler(int sig)
{
	switch(sig)
	{
	case SIGALRM:
		signal(SIGALRM , sock_signal_handler);
		zabbix_log( LOG_LEVEL_WARNING, "Timeout while answering request");
		break;
	default:
		zabbix_log( LOG_LEVEL_WARNING, "Sock handler: Got signal [%d]. Ignoring ...", sig);
	}
}

#endif /* not WIN32 */

int zbx_sock_read(ZBX_SOCKET sock, void *buf, int buflen, int timeout)
{
#if defined (WIN32)

	TIMEVAL		time = {0,0};
	FD_SET		rdfs;

	int rc = 0;

	/* Wait for command from server */
	FD_ZERO(&rdfs);
	FD_SET(sock, &rdfs);		/* ignore WARNING '...whle(0)' */

	time.tv_sec	= timeout;
	time.tv_usec	= 0;

	rc = select(sock+1, &rdfs, (fd_set *)NULL, (fd_set *)NULL, &time);

	if (rc == SOCKET_ERROR)
	{
		return (SOCKET_ERROR);
	}
	else if(rc == 0)
	{
		return (0); /* time out */
	}

	return (int)recv(sock, buf, buflen, 0);


#else /* not WIN32 */

        static struct  	sigaction phan;
	int nread = 0;
	
	phan.sa_handler = sock_signal_handler; /* set up sig handler using sigaction() */
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;

	sigaction(SIGALRM, &phan, NULL);

	alarm(timeout);

	if( (nread = read(sock, buf, MAX_STRING_LEN)) == SOCKET_ERROR)
	{
		return (SOCKET_ERROR);
	}
	alarm(0);

	return nread;

#endif /* WIN32 */

	/* normal case the program will never reach this point. */
	return SOCKET_ERROR;
}

int zbx_sock_write(ZBX_SOCKET sock, void *buf, int buflen)
{
#if defined (WIN32)

	return send(sock, buf, buflen,0);

#else /* not WIN32 */

	return write(sock, buf, buflen);

#endif /* WIN32 */

}
