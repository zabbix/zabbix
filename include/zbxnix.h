/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_ZBXNIX_H
#define ZABBIX_ZBXNIX_H

#include "zbxsysinc.h"

/* IPC start */
#include "zbxtypes.h"
#include "mutexs.h"
/* IPC end */

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
int	zbx_coredump_disable(void);
#endif

/* daemon start */

#if defined(_WINDOWS)
#	error "This module allowed only for Unix OS"
#endif

extern char			*CONFIG_PID_FILE;
extern volatile sig_atomic_t	sig_exiting;

#define ZBX_EXIT_NONE		0
#define ZBX_EXIT_SUCCESS	1
#define ZBX_EXIT_FAILURE	2

int	zbx_daemon_start(int allow_root, const char *user, unsigned int flags);
void	zbx_daemon_stop(void);

int	zbx_sigusr_send(int flags);
void	zbx_set_sigusr_handler(void (*handler)(int flags));

#define ZBX_IS_RUNNING()	(ZBX_EXIT_NONE == sig_exiting)
#define ZBX_EXIT_STATUS()	(ZBX_EXIT_SUCCESS == sig_exiting ? SUCCEED : FAIL)

#define ZBX_DO_EXIT()

#define ZBX_START_MAIN_ZABBIX_ENTRY(allow_root, user, flags)	zbx_daemon_start(allow_root, user, flags)

void	zbx_signal_process_by_type(int proc_type, int proc_num, int flags, char **out);
void	zbx_signal_process_by_pid(int pid, int flags, char **out);
/* daemon end */

/* IPC start */
#define ZBX_NONEXISTENT_SHMID		(-1)

int	zbx_shm_create(size_t size);
int	zbx_shm_destroy(int shmid);

/* data copying callback function prototype */
typedef void (*zbx_shm_copy_func_t)(void *dst, size_t size_dst, const void *src);

/* dynamic shared memory data structure */
typedef struct
{
	/* shared memory segment identifier */
	int			shmid;

	/* allocated size */
	size_t			size;

	/* callback function to copy data after shared memory reallocation */
	zbx_shm_copy_func_t	copy_func;

	zbx_mutex_t		lock;
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

int	zbx_dshm_create(zbx_dshm_t *shm, size_t shm_size, zbx_mutex_name_t mutex,
		zbx_shm_copy_func_t copy_func, char **errmsg);

int	zbx_dshm_destroy(zbx_dshm_t *shm, char **errmsg);

int	zbx_dshm_realloc(zbx_dshm_t *shm, size_t size, char **errmsg);

int	zbx_dshm_validate_ref(const zbx_dshm_t *shm, zbx_dshm_ref_t *shm_ref, char **errmsg);

void	zbx_dshm_lock(zbx_dshm_t *shm);
void	zbx_dshm_unlock(zbx_dshm_t *shm);
/* IPC end*/

/* sighandler start */
void	zbx_set_common_signal_handlers(void);
void	zbx_set_child_signal_handler(void);
void	zbx_unset_child_signal_handler(void);
void	zbx_set_metric_thread_signal_handler(void);
void	zbx_block_signals(sigset_t *orig_mask);
void	zbx_unblock_signals(const sigset_t *orig_mask);

void	zbx_set_exit_on_terminate(void);
void	zbx_unset_exit_on_terminate(void);
/* sighandler end */

int	zbx_parse_rtc_options(const char *opt, int *message);
#endif	/* ZABBIX_ZBXNIX_H */
