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
#include "ipc.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_ftok                                                         *
 *                                                                            *
 * Purpose: Create IPC id                                                     *
 *                                                                            *
 * Parameters:  path - filename                                               *
 *              id - user selectable ID                                       *
 *                                                                            *
 * Return value: If the function succeeds, then return unique ID              *
 *               -1 on an error                                               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
key_t	zbx_ftok(char *path, int id)
{
	key_t	ipc_key;

	if (-1 == (ipc_key = ftok(path, id)))
	{
		zbx_error("Cannot create IPC key for path [%s] id [%c] error [%s]",
			path, (char)id, strerror(errno));
	}

	return ipc_key;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_shmget                                                       *
 *                                                                            *
 * Purpose: Create block of shared memory                                     *
 *                                                                            *
 * Parameters:  key - IPC key                                                 *
 *              size - size                                                   *
 *                                                                            *
 * Return value: If the function succeeds, then return SHM ID                 *
 *               -1 on an error                                               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_shmget(key_t key, size_t size)
{
	int	shm_id, ret = SUCCEED;

	if (-1 != (shm_id = shmget(key, size, IPC_CREAT | IPC_EXCL | 0666)))
		return shm_id;

	/* If shared memory block exists, try to remove and re-create it */
	if (EEXIST == errno)
	{
		/* Get ID of existing memory */
		if (-1 == (shm_id = shmget(key, 0 /* get reference */, 0666)))
		{
			zbx_error("Cannot attach to existing shared memory [%s]", strerror(errno));
			ret = FAIL;
		}

		if (SUCCEED == ret && -1 == shmctl(shm_id, IPC_RMID, 0))
		{
			zbx_error("Cannot remove existing shared memory [%s]", strerror(errno));
			ret = FAIL;
		}

		if (SUCCEED == ret && -1 == (shm_id = shmget(key, size, IPC_CREAT | IPC_EXCL | 0666)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "Cannot allocate shared memory of size %lu [%s]",
				size, strerror(errno));
			ret = FAIL;
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot allocate shared memory of size %lu [%s]",
			size, strerror(errno));
		ret = FAIL;
	}

	return (ret == SUCCEED) ? shm_id : -1;
}
