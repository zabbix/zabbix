#ifndef ZABBIX_ASYNC_QUEUE_H
#define ZABBIX_ASYNC_QUEUE_H
#include "pthread.h"
#include "zbxtypes.h"
#include "zbxcacheconfig.h"
#include "async_manager.h"

typedef struct
{
	zbx_uint32_t			init_flags;
	int				workers_num;
	zbx_uint64_t			pending_num;
	zbx_uint64_t			finished_num;
	zbx_uint64_t			processing_num;

	zbx_uint64_t			processing_max;
	unsigned char			poller_type;
	int				config_timeout;
	int				config_unavailable_delay;
	int				config_unreachable_delay;
	int				config_unreachable_period;

	zbx_vector_poller_item_t	poller_items;
	zbx_vector_interface_status_t	interfaces;
	zbx_vector_uint64_t		itemids;
	zbx_vector_int32_t		errcodes;
	zbx_vector_int32_t		lastclocks;
	unsigned char			check_queue;

	pthread_mutex_t			lock;
	pthread_cond_t			event;
}
zbx_async_queue_t;

int	async_task_queue_init(zbx_async_queue_t *queue, zbx_thread_poller_args *poller_args_in, char **error);
void	async_task_queue_destroy(zbx_async_queue_t *queue);
void	async_task_queue_lock(zbx_async_queue_t *queue);
void	async_task_queue_unlock(zbx_async_queue_t *queue);
void	async_task_queue_register_worker(zbx_async_queue_t *queue);
void	async_task_queue_deregister_worker(zbx_async_queue_t *queue);
int	async_task_queue_wait(zbx_async_queue_t *queue, char **error);
void	async_task_queue_notify_all(zbx_async_queue_t *queue);
void	async_task_queue_notify(zbx_async_queue_t *queue);
#endif
