#ifndef ZABBIX_ASYNC_MANAGER_H
#define ZABBIX_ASYNC_MANAGER_H
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "poller.h"

typedef struct zbx_async_manager	zbx_async_manager_t;
typedef void (*zbx_async_notify_cb_t)(void *data);

typedef struct
{
	zbx_dc_item_t	*items;
	AGENT_RESULT	*results;
	int		*errcodes;
	int		num;
}
zbx_poller_item_t;

ZBX_VECTOR_DECL(poller_item, zbx_poller_item_t)

typedef struct
{
	zbx_dc_interface_t	interface;
	int			errcode;
	char			*error;
	zbx_uint64_t		itemid;
	char			host[ZBX_HOSTNAME_BUF_LEN];
	char			*key_orig;
}
zbx_interface_status_t;

ZBX_PTR_VECTOR_DECL(interface_status, zbx_interface_status_t *)

zbx_async_manager_t	*zbx_async_manager_create(int workers_num, zbx_async_notify_cb_t finished_cb,
			void *finished_data, zbx_thread_poller_args *poller_args_in, char **error);
void			zbx_async_manager_free(zbx_async_manager_t *manager);
void			zbx_async_manager_queue_sync(zbx_async_manager_t *manager);
void			zbx_async_manager_queue_get(zbx_async_manager_t *manager, zbx_vector_poller_item_t *poller_items);
void			zbx_async_manager_requeue(zbx_async_manager_t *manager, zbx_uint64_t itemid, int errcode,
		int lastclock);
void			zbx_async_manager_requeue_flush(zbx_async_manager_t *manager);
void			zbx_async_manager_interfaces_flush(zbx_async_manager_t *manager, zbx_hashset_t *interfaces);
void			zbx_interface_status_clean(zbx_interface_status_t *interface_status);


#endif
