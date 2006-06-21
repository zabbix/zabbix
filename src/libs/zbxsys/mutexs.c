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

#include "mutexs.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_create                                                 *
 *                                                                            *
 * Purpose: Create the mutex                                                  *
 *                                                                            *
 * Parameters:  mutex - handle of mutex                                       *
 *                                                                            *
 * Return value: If the function succeeds, the return ZBX_MUTEX_OK,           *
 *               ZBX_MUTEX_ERROR on an error                                  *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_mutex_create(ZBX_MUTEX *mutex)
{
#if defined(WIN32)	

	if(NULL == ((*mutex) = CreateMutex(NULL, FALSE, NULL)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error on mutex creating. [%s]", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}

#else /* not WIN32 */

	if(pthread_mutex_init(mutexm, NULL) < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error on mutex creating.");
		return ZBX_MUTEX_ERROR;
	}

#endif /* WIN32 */

	return ZBX_MUTEX_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_lock                                                   *
 *                                                                            *
 * Purpose: Waits until the mutex is in the signaled state                    *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 * Return value: If the function succeeds, the return 1, 0 on an error        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_mutex_lock(ZBX_MUTEX *mutex)
{
#if defined(WIN32)	

	if(WaitForSingleObject(*mutex, INFINITE) != WAIT_OBJECT_0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error on mutex locking. [%s]", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}

#else /* not WIN32 */

	if(pthread_mutex_lock(mutex) < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error on mutex locking.");
		return ZBX_MUTEX_ERROR;
	}

#endif /* WIN32 */

	return ZBX_MUTEX_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_unlock                                                 *
 *                                                                            *
 * Purpose: Unlock the mutex                                                  *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 * Return value: If the function succeeds, the return 1, 0 on an error        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_mutex_unlock(ZBX_MUTEX *mutex)
{
#if defined(WIN32)	

	if(ReleaseMutex(*mutex) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error on mutex UNlocking. [%s]", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}

#else /* not WIN32 */

	if(pthread_mutex_unlock(mutex) < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error on mutex UNlocking.");
		return ZBX_MUTEX_ERROR;
	}

#endif /* WIN32 */

	return ZBX_MUTEX_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_destroy                                                *
 *                                                                            *
 * Purpose: Destroy the mutex                                                 *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 * Return value: If the function succeeds, the return 1, 0 on an error        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_mutex_destroy(ZBX_MUTEX *mutex)
{
#if defined(WIN32)	

	if(CloseHandle(*mutex) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error on mutex destroying. [%s]", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}

#else /* not WIN32 */

	if(pthread_mutex_destroy(mutex) < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error on mutex destroying.");
		return ZBX_MUTEX_ERROR;
	}

#endif /* WIN32 */

	return ZBX_MUTEX_OK;
}

