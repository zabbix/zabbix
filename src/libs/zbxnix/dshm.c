/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

extern char	*CONFIG_FILE;

/******************************************************************************
 *                                                                            *
 * Function: zbx_dshm_create                                                  *
 *                                                                            *
 * Purpose: creates dynamic shared memory segment                             *
 *                                                                            *
 * Parameters: shm       - [OUT] the dynamic shared memory data               *
 *             shm_size  - [IN] the inital size (can be 0)                    *
 *             mutex     - [IN] the name of mutex used to synchronize memory  *
 *                              access                                        *
 *             copy_func - [IN] the function used to copy shared memory       *
 *                              contents during reallocation                  *
 *             errmsg    - [OUT] the error message                            *
 *                                                                            *
 * Return value: SUCCEED - the dynamic shared memory segment was created      *
 *                         successfully.                                      *
 *               FAIL    - otherwise. The errmsg contains error message and   *
 *                         must be freed by the caller.                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_dshm_create(zbx_dshm_t *shm, size_t shm_size, zbx_mutex_name_t mutex,
		zbx_shm_copy_func_t copy_func, char **errmsg)
{
	const char	*__function_name = "zbx_dshm_create";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() size:" ZBX_FS_SIZE_T, __function_name,
			(zbx_fs_size_t)shm_size);

	if (SUCCEED != zbx_mutex_create(&shm->lock, mutex, errmsg))
		goto out;

	if (0 < shm_size)
	{
		if (-1 == (shm->shmid = zbx_shm_create(shm_size)))
		{
			*errmsg = zbx_strdup(*errmsg, "cannot allocate shared memory");
			goto out;
		}
	}
	else
		shm->shmid = ZBX_NONEXISTENT_SHMID;

	shm->size = shm_size;
	shm->copy_func = copy_func;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s shmid:%d", __function_name, zbx_result_string(ret), shm->shmid);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dshm_destroy                                                 *
 *                                                                            *
 * Purpose: destroys dynamic shared memory segment                            *
 *                                                                            *
 * Parameters: shm    - [IN] the dynamic shared memory data                   *
 *             errmsg - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the dynamic shared memory segment was destroyed    *
 *                         successfully.                                      *
 *               FAIL    - otherwise. The errmsg contains error message and   *
 *                         must be freed by the caller.                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_dshm_destroy(zbx_dshm_t *shm, char **errmsg)
{
	const char	*__function_name = "zbx_dshm_destroy";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() shmid:%d", __function_name, shm->shmid);

	zbx_mutex_destroy(&shm->lock);

	if (ZBX_NONEXISTENT_SHMID != shm->shmid)
	{
		if (-1 == shmctl(shm->shmid, IPC_RMID, NULL))
		{
			*errmsg = zbx_dsprintf(*errmsg, "cannot remove shared memory: %s", zbx_strerror(errno));
			goto out;
		}
		shm->shmid = ZBX_NONEXISTENT_SHMID;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dshm_lock                                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_dshm_lock(zbx_dshm_t *shm)
{
	zbx_mutex_lock(shm->lock);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dshm_unlock                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_dshm_unlock(zbx_dshm_t *shm)
{
	zbx_mutex_unlock(shm->lock);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dshm_validate_ref                                            *
 *                                                                            *
 * Purpose: validates local reference to dynamic shared memory segment        *
 *                                                                            *
 * Parameters: shm     - [IN] the dynamic shared memory data                  *
 *             shm_ref - [IN/OUT] a local reference to dynamic shared memory  *
 *                                segment                                     *
 *             errmsg  - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - the local reference to dynamic shared memory       *
 *                         segment was validated successfully and contains    *
 *                         corret dynamic shared memory segment address       *
 *               FAIL    - otherwise. The errmsg contains error message and   *
 *                         must be freed by the caller.                       *
 *                                                                            *
 * Comments: This function should be called before accessing the dynamic      *
 *           shared memory to ensure that the local reference has correct     *
 *           address after shared memory allocation/reallocation.             *
 *                                                                            *
 ******************************************************************************/
int	zbx_dshm_validate_ref(const zbx_dshm_t *shm, zbx_dshm_ref_t *shm_ref, char **errmsg)
{
	const char	*__function_name = "zbx_dshm_validate_ref";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() shmid:%d refid:%d", __function_name, shm->shmid, shm_ref->shmid);

	if (shm->shmid != shm_ref->shmid)
	{
		if (ZBX_NONEXISTENT_SHMID != shm_ref->shmid)
		{
			if (-1 == shmdt((void *)shm_ref->addr))
			{
				*errmsg = zbx_dsprintf(*errmsg, "cannot detach shared memory: %s", zbx_strerror(errno));
				goto out;
			}
			shm_ref->addr = NULL;
			shm_ref->shmid = ZBX_NONEXISTENT_SHMID;
		}

		if ((void *)(-1) == (shm_ref->addr = shmat(shm->shmid, NULL, 0)))
		{
			*errmsg = zbx_dsprintf(*errmsg, "cannot attach shared memory: %s", zbx_strerror(errno));
			shm_ref->addr = NULL;
			goto out;
		}

		shm_ref->shmid = shm->shmid;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_TRACE, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dshm_realloc                                                 *
 *                                                                            *
 * Purpose: reallocates dynamic shared memory segment                         *
 *                                                                            *
 * Parameters: shm      - [IN/OUT] the dynamic shared memory data             *
 *             size     - [IN] the new segment size                           *
 *             errmsg   - [OUT] the error message                             *
 *                                                                            *
 * Return value:                                                              *
 *    SUCCEED - the shared memory segment was successfully reallocated.       *
 *    FAIL    - otherwise. The errmsg contains error message and must be      *
 *              freed by the caller.                                          *
 *                                                                            *
 * Comments: The shared memory segment is reallocated by simply creating      *
 *           a new segment and copying the data from old segment by calling   *
 *           the copy_data callback function.                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_dshm_realloc(zbx_dshm_t *shm, size_t size, char **errmsg)
{
	const char	*__function_name = "zbx_dshm_realloc";
	int		shmid, ret = FAIL;
	void		*addr, *addr_old = NULL;
	size_t		shm_size;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() shmid:%d size:" ZBX_FS_SIZE_T, __function_name, shm->shmid,
			(zbx_fs_size_t)size);

	shm_size = ZBX_SIZE_T_ALIGN8(size);

	/* attach to the old segment if possible */
	if (ZBX_NONEXISTENT_SHMID != shm->shmid && (void *)(-1) == (addr_old = shmat(shm->shmid, NULL, 0)))
	{
		*errmsg = zbx_dsprintf(*errmsg, "cannot attach current shared memory: %s", zbx_strerror(errno));
		goto out;
	}

	if (-1 == (shmid = zbx_shm_create(shm_size)))
	{
		*errmsg = zbx_strdup(NULL, "cannot allocate shared memory");
		goto out;
	}

	if ((void *)(-1) == (addr = shmat(shmid, NULL, 0)))
	{
		if (NULL != addr_old)
			(void)shmdt(addr_old);

		*errmsg = zbx_dsprintf(*errmsg, "cannot attach new shared memory: %s", zbx_strerror(errno));
		goto out;
	}

	/* copy data from the old segment */
	shm->copy_func(addr, shm_size, addr_old);

	if (-1 == shmdt((void *)addr))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot detach from new shared memory");
		goto out;
	}

	/* delete the old segment */
	if (NULL != addr_old && -1 == zbx_shm_destroy(shm->shmid))
	{
		*errmsg = zbx_strdup(*errmsg, "cannot detach from old shared memory");
		goto out;
	}

	shm->size = shm_size;
	shm->shmid = shmid;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s shmid:%d", __function_name, zbx_result_string(ret), shm->shmid);

	return ret;
}
