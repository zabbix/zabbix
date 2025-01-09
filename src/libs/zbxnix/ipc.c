/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxnix.h"

#include "zbxcommon.h"

/******************************************************************************
 *                                                                            *
 * Purpose: Create block of shared memory                                     *
 *                                                                            *
 * Parameters:  size - size                                                   *
 *                                                                            *
 * Return value: If the function succeeds, then return SHM ID                 *
 *               -1 on an error                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_shm_create(size_t size)
{
	int	shm_id;

	if (-1 == (shm_id = shmget(IPC_PRIVATE, size, IPC_CREAT | IPC_EXCL | 0600)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate shared memory of size " ZBX_FS_SIZE_T ": %s",
				(zbx_fs_size_t)size, zbx_strerror(errno));
		return -1;
	}

	return shm_id;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Destroy block of shared memory                                    *
 *                                                                            *
 * Parameters:  shmid - Shared memory identifier                              *
 *                                                                            *
 * Return value: If the function succeeds, then return 0                      *
 *               -1 on an error                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_shm_destroy(int shmid)
{
	if (-1 == shmctl(shmid, IPC_RMID, 0))
	{
		zbx_error("cannot remove existing shared memory: %s", zbx_strerror(errno));
		return -1;
	}

	return 0;
}
