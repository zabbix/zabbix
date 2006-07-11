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

#ifndef ZABBIX_MUTEXS_H
#define ZABBIX_MUTEXS_H

#if defined(WIN32)

	#define ZBX_MUTEX		HANDLE
	#define ZBX_MUTEX_ERROR		(0)
	#define ZBX_MUTEX_OK		(1)

#else /* not WIN32 */


	#define ZBX_MUTEX		int

	#define ZBX_MUTEX_ERROR		(-1)
	#define ZBX_MUTEX_OK		(1)

#endif /* WIN32 */

int zbx_mutex_create(ZBX_MUTEX	*mutex, char *name);
int zbx_mutex_lock(ZBX_MUTEX	*mutex);
int zbx_mutex_unlock(ZBX_MUTEX	*mutex);
int zbx_mutex_destroy(ZBX_MUTEX	*mutex);

#endif /* ZABBIX_MUTEXS_H */
