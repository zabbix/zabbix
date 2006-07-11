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

#ifndef ZABBIX_ZBXSOCK_H
#define ZABBIX_ZBXSOCK_H


#if !defined(INVALID_SOCKET)
#	define INVALID_SOCKET (-1)
#endif /* INVALID_SOCKET */

#if !defined(SOCKET_ERROR)
#	define SOCKET_ERROR (-1)
#endif /* SOCKET_ERROR */

#if !defined (EINTR)
#	define EINTR		WSAETIMEDOUT
#endif /* EINTR */

#if !defined(EHOSTUNREACH)
#	define EHOSTUNREACH	WSAEHOSTUNREACH
#endif /* EHOSTUNREACH */

#if !defined(ECONNRESET)
#	define ECONNRESET	WSAECONNRESET
#endif /* ECONNRESET */

#if !defined(SOMAXCONN)
#	define SOMAXCONN	1024
#endif /* SOMAXCONN */

#if defined(SOCKET)

	typedef SOCKET ZBX_SOCKET;

#else /* not SOCKET */

	typedef int ZBX_SOCKET;

#endif /* SOCKET */

typedef struct sockaddr_in ZBX_SOCKADDR;

int zbx_sock_read(ZBX_SOCKET sock, void *buf, int buflen, int timeout);
int zbx_sock_write(ZBX_SOCKET sock, void *buf, int buflen);

#if defined (WIN32)
#	define	zbx_sock_close(sock)	closesocket(sock)
#	define  zbx_sock_last_error()	WSAGetLastError()
#else /* not WIN32 */
#	define	zbx_sock_close(sock)	close(sock)
#	define  zbx_sock_last_error()	errno
#endif /* WIN32 */


#endif /* ZABBIX_ZBXSOCK_H */
