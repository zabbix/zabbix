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

#ifndef ZABBIX_ZBXNIX_H
#define ZABBIX_ZBXNIX_H

#include "zbxsysinc.h"

#include "zbxthreads.h"

/* IPC start */
#include "zbxtypes.h"
#include "zbxmutexs.h"
/* IPC end */

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
int	zbx_coredump_disable(void);
#endif

/* daemon start */
#if defined(_WINDOWS)
#	error "This module allowed only for Unix OS"
#endif

typedef int	(*zbx_get_process_info_by_thread_f)(int local_server_num, unsigned char *local_process_type,
		int *local_process_num);

void	zbx_init_library_nix(zbx_get_progname_f get_progname_cb, zbx_get_process_info_by_thread_f
		get_process_info_by_thread_cb);

typedef void	(*zbx_on_exit_t)(int, void*);
typedef void	(*zbx_signal_handler_f)(int flags);
typedef void	(*zbx_signal_redirect_f)(int flags, zbx_signal_handler_f signal_handler_cb);
typedef	ZBX_THREAD_HANDLE *(*zbx_get_threads_f)(void);

void	zbx_set_exiting_with_fail(void);
void	zbx_set_exiting_with_succeed(void);
int	ZBX_IS_RUNNING(void);
int	ZBX_EXIT_STATUS(void);

int	zbx_daemon_start(int allow_root, const char *user, unsigned int flags,
		zbx_get_config_str_f get_pid_file_cb, zbx_on_exit_t zbx_on_exit_cb_arg, int config_log_type,
		const char *config_log_file, zbx_signal_redirect_f signal_redirect_cb,
		zbx_get_threads_f get_threads_cb,  zbx_get_config_int_f get_threads_num_cb);
void	zbx_daemon_stop(void);

int	zbx_sigusr_send(int flags, const char *pid_file_pathname);
void	zbx_set_sigusr_handler(void (*handler)(int flags));

void	zbx_signal_process_by_type(int proc_type, int proc_num, int flags, char **out);
void	zbx_signal_process_by_pid(int pid, int flags, char **out);
/* daemon end */

/* IPC start */
#define ZBX_NONEXISTENT_SHMID		(-1)

int	zbx_shm_create(size_t size);
int	zbx_shm_destroy(int shmid);

/* data copying callback function prototype */
typedef void	(*zbx_shm_copy_func_t)(void *dst, size_t size_dst, const void *src);

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
void	zbx_set_common_signal_handlers(zbx_on_exit_t zbx_on_exit_cb_arg);
void	zbx_set_child_signal_handler(void);
void	zbx_unset_child_signal_handler(void);
void	zbx_set_metric_thread_signal_handler(void);
void	zbx_block_signals(sigset_t *orig_mask);
void	zbx_unblock_signals(const sigset_t *orig_mask);

void	zbx_set_exit_on_terminate(void);
void	zbx_unset_exit_on_terminate(void);

void	zbx_log_exit_signal(void);
void	zbx_set_on_exit_args(void *args);
void	zbx_set_child_pids(const pid_t *pids, size_t pid_num);
/* sighandler end */

int	zbx_parse_rtc_options(const char *opt, int *message);

void	zbx_backtrace(void);
#endif	/* ZABBIX_ZBXNIX_H */
