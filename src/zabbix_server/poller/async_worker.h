#ifndef ZABBIX_ASYNC_WORKER_H
#define ZABBIX_ASYNC_WORKER_H
#include "zbxalgo.h"
#include "async_manager.h"
#include "async_queue.h"
#include "zbxtimekeeper.h"

typedef struct
{
	int				id;

	zbx_uint32_t			init_flags;
	int				stop;

	zbx_async_queue_t		*queue;
	pthread_t			thread;

	//zbx_pp_context_t		execute_ctx;

	zbx_timekeeper_t		*timekeeper;

	zbx_async_notify_cb_t		finished_cb;

	void				*finished_data;

	zbx_log_component_t		logger;
}
zbx_async_worker_t;

int	async_worker_init(zbx_async_worker_t *worker, int id, zbx_async_queue_t *queue, zbx_timekeeper_t *timekeeper,
		char **error);
void	async_worker_stop(zbx_async_worker_t *worker);
void	async_worker_destroy(zbx_async_worker_t *worker);
void	async_worker_set_finished_cb(zbx_async_worker_t *worker, zbx_async_notify_cb_t finished_cb, void *finished_data);
#endif
