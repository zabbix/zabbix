/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
		zbx_error("cannot create IPC key for path [%s] id [%c]: %s",
			path, (char)id, zbx_strerror(errno));
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

	if (-1 != (shm_id = shmget(key, size, IPC_CREAT | IPC_EXCL | 0600)))
		return shm_id;

	/* if shared memory block exists, try to remove and re-create it */
	if (EEXIST == errno)
	{
		/* get ID of existing memory */
		if (-1 == (shm_id = shmget(key, 0 /* get reference */, 0600)))
		{
			zbx_error("cannot attach to existing shared memory: %s", zbx_strerror(errno));
			ret = FAIL;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "zbx_shmget() removing existing shm_id:%d", shm_id);

		if (SUCCEED == ret && -1 == shmctl(shm_id, IPC_RMID, 0))
		{
			zbx_error("cannot remove existing shared memory: %s", zbx_strerror(errno));
			ret = FAIL;
		}

		if (SUCCEED == ret && -1 == (shm_id = shmget(key, size, IPC_CREAT | IPC_EXCL | 0600)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory of size " ZBX_FS_SIZE_T ": %s",
					(zbx_fs_size_t)size, zbx_strerror(errno));
			ret = FAIL;
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory of size " ZBX_FS_SIZE_T ": %s",
				(zbx_fs_size_t)size, zbx_strerror(errno));
		ret = FAIL;
	}

	return (ret == SUCCEED) ? shm_id : -1;
}
