/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#ifndef ZABBIX_IPC_H
#define ZABBIX_IPC_H

#if defined(_WINDOWS)
#	error "This module allowed only for Unix OS"
#endif

#include "mutexs.h"

#define ZBX_NONEXISTENT_SHMID		(-1)

#define ZBX_IPC_CONFIG_ID		'g'
#define ZBX_IPC_HISTORY_ID		'h'
#define ZBX_IPC_HISTORY_INDEX_ID	'H'
#define ZBX_IPC_TREND_ID		't'
#define ZBX_IPC_STRPOOL_ID		's'
#define ZBX_IPC_COLLECTOR_ID		'l'
#define ZBX_IPC_COLLECTOR_DISKSTAT	'm'
#define ZBX_IPC_SELFMON_ID		'S'
#define ZBX_IPC_VALUECACHE_ID		'v'
#define ZBX_IPC_VMWARE_ID		'w'
#define ZBX_IPC_COLLECTOR_PROC_ID	'p'

key_t	zbx_ftok(char *path, int id);
int	zbx_shmget(key_t key, size_t size);

/* data copying callback function prototype */
typedef void (*zbx_shm_copy_func_t)(void *dst, size_t size_dst, const void *src);

/* dynamic shared memory data structure */
typedef struct
{
	/* shared memory segment identifier */
	int			shmid;

	/* project id used to generate shared memory key */
	int			proj_id;

	/* allocated size */
	size_t			size;

	/* callback function to copy data after shared memory reallocation */
	zbx_shm_copy_func_t	copy_func;

	ZBX_MUTEX		lock;
}
zbx_dshm_t;

/* local process reference to dynamic shared memory data */
typedef struct
{
	/* shared memory segment identifier */
	int	shmid;

	/* shared memory base address */
	void	*addr;
}
zbx_dshm_ref_t;

int	zbx_dshm_create(zbx_dshm_t *shm, int proj_id, size_t shm_size, ZBX_MUTEX_NAME mutex,
		zbx_shm_copy_func_t copy_func, char **errmsg);

int	zbx_dshm_destroy(zbx_dshm_t *shm, char **errmsg);

int	zbx_dshm_realloc(zbx_dshm_t *shm, size_t size, char **errmsg);

int	zbx_dshm_validate_ref(const zbx_dshm_t *shm, zbx_dshm_ref_t *shm_ref, char **errmsg);

void	zbx_dshm_lock(zbx_dshm_t *shm);
void	zbx_dshm_unlock(zbx_dshm_t *shm);

#endif
