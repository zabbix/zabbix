/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_MW_H
#define ZABBIX_MW_H

#include "zbxcommon.h"
#include "zbxipcservice.h"
#include "zbxthreads.h"
#include "zbxtimekeeper.h"
#include "zbxtypes_ext.h"
#include "zbxalgo.h"

typedef struct zbx_mw_task
{
	int	type;
	void	(*free_func)(void *);
}
zbx_mw_task_t;

ZBX_PTR_VECTOR_DECL(mw_task_ptr, zbx_mw_task_t *)

typedef struct
{
	/* tasks to be processed first */
	zbx_queue_ptr_t	priority;

	/* tasks to be processed after priority tasks */
	zbx_queue_ptr_t	normal;

	/* processed tasks */
	zbx_queue_ptr_t	completed;

	/* total number of pending tasks in priority and normal queues */
	int		pending_num;

	/* number of tasks being processed */
	int		processing_num;

	pthread_mutex_t	lock;
	pthread_cond_t	notify;
}
zbx_mw_queue_t;

typedef struct
{
	int			id;
	zbx_timekeeper_t	*timekeeper;
	zbx_log_component_t	logger;
	zbx_mw_queue_t		*queue;		/* task queue, optional */
	zbx_ipc_service_t	*service;	/* only for sending service alerts to wake up manager, optional */

	pthread_t		thread;
	zbx_atomic_uint32_t	state;

	char			name[ZBX_MAX_PROCNAME_LEN + 1];
}
zbx_mw_worker_t;

typedef void (*zbx_mw_process_task_f)(zbx_mw_worker_t *worker, zbx_mw_task_t *task);

typedef struct
{
	zbx_mw_worker_t		base;
	zbx_mw_process_task_f	process_task;
}
zbx_mw_task_worker_t;

typedef enum
{
	MW_WORKER_POOL_IDLE,
	MW_WORKER_POOL_GROWING,	/* worker thread(s) are being started */
	MW_WORKER_POOL_SHRINKING	/* worker thread(s) are being stopped */
}
zbx_worker_pool_state_t;

typedef struct
{
	zbx_ipc_service_t	*service;

	zbx_mw_worker_t		**workers;

	zbx_mw_queue_t		*queue;

	/* actual number of active workers */
	int			workers_num;

	/* number of workers being started/stopped depending on pool_state */
	int			workers_diff;

	int			workers_max;

	unsigned char		worker_process_type;

	/* number of 10s ticks processors had less than 50% load */
	int			low_load_ticks;

	zbx_worker_pool_state_t	worker_pool_state;

	zbx_timekeeper_t	*timekeeper;

	const zbx_thread_info_t	*thread_info;

	void			*(*worker_entry)(void *);
}
zbx_mw_manager_t;

zbx_mw_task_t	*zbx_mw_task_create(int type, void (*task_free)(void *a), size_t size);

void	zbx_mw_queue_notify(zbx_mw_queue_t *queue);
void	zbx_mw_queue_notify_all(zbx_mw_queue_t *queue);
int	zbx_mw_queue_wait(zbx_mw_queue_t *queue, char **error);

void	zbx_mw_queue_push_priority(zbx_mw_queue_t *queue, zbx_mw_task_t *task);
void	zbx_mw_queue_push_normal(zbx_mw_queue_t *queue, zbx_mw_task_t *tasks);

void	zbx_mw_queue_lock(zbx_mw_queue_t *queue);
void	zbx_mw_queue_unlock(zbx_mw_queue_t *queue);

zbx_mw_task_t	*zbx_mw_queue_pop(zbx_mw_queue_t *queue);
void	zbx_mw_queue_push_completed(zbx_mw_queue_t *queue, zbx_mw_task_t *task);
void	zbx_mw_queue_push_completed_direct(zbx_mw_queue_t *queue, zbx_mw_task_t *task);
zbx_mw_task_t	*zbx_mw_queue_pop_completed(zbx_mw_queue_t *queue);
int	zbx_mw_queue_drain_completed(zbx_mw_queue_t *queue, zbx_vector_ptr_t *tasks);

int	zbx_mw_manager_init(zbx_mw_manager_t *manager, const zbx_thread_info_t *info, const char *service,
		unsigned char worker_process_type, zbx_mw_worker_t **workers, int workers_max, int workers_num,
		void *(*worker_entry)(void *), zbx_mw_queue_t *queue, char **error);
void	zbx_mw_manager_clear(zbx_mw_manager_t *manager);

double	zbx_mw_manager_recv(zbx_mw_manager_t *manager, zbx_ipc_client_t **client, zbx_ipc_message_t **message);

int	zbx_mw_worker_is_running(zbx_mw_worker_t *worker);
void	zbx_mw_worker_stop(zbx_mw_worker_t *worker);
void	zbx_mw_worker_report_busy(zbx_mw_worker_t *worker);
void	zbx_mw_worker_report_idle(zbx_mw_worker_t *worker);

void	zbx_mw_worker_notify(zbx_mw_worker_t *worker);

int	zbx_mw_get_worker_count(const char *service, int *workers_num, char **error);
int	zbx_mw_get_worker_load(const char *service, zbx_vector_dbl_t *usage, int *count, char **error);

void	zbx_mw_task_worker_init(zbx_mw_task_worker_t *worker, zbx_mw_process_task_f process_task);
void	*zbx_mw_task_worker_entry(void *arg);

#endif
