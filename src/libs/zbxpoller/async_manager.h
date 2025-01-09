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

#ifndef ZABBIX_ASYNC_MANAGER_H
#define ZABBIX_ASYNC_MANAGER_H

#include "zbxpoller.h"

#include "zbxalgo.h"
#include "zbxcacheconfig.h"

typedef void (*zbx_async_notify_cb_t)(void *data);

typedef struct
{
	zbx_dc_item_t	*items;
	AGENT_RESULT	*results;
	int		*errcodes;
	int		num;
}
zbx_poller_item_t;

ZBX_PTR_VECTOR_DECL(poller_item, zbx_poller_item_t *)

typedef struct
{
	zbx_dc_interface_t	interface;
	int			errcode;
	char			*error;
	zbx_uint64_t		itemid;
	char			host[ZBX_HOSTNAME_BUF_LEN];
	char			*key_orig;
	int			version;
}
zbx_interface_status_t;

ZBX_PTR_VECTOR_DECL(interface_status, zbx_interface_status_t *)

zbx_async_manager_t	*zbx_async_manager_create(int workers_num, zbx_async_notify_cb_t finished_cb,
					void *finished_data, zbx_thread_poller_args *poller_args_in, char **error);
void			zbx_async_manager_free(zbx_async_manager_t *manager);
void			zbx_async_manager_queue_sync(zbx_async_manager_t *manager);
void			zbx_async_manager_queue_get(zbx_async_manager_t *manager,
					zbx_vector_poller_item_t *poller_items);
void			zbx_async_manager_requeue(zbx_async_manager_t *manager, zbx_uint64_t itemid, int errcode,
					int lastclock);
void			zbx_async_manager_requeue_flush(zbx_async_manager_t *manager);
void			zbx_async_manager_interfaces_flush(zbx_async_manager_t *manager, zbx_hashset_t *interfaces);
void			zbx_interface_status_clean(zbx_interface_status_t *interface_status);
void			zbx_interface_status_free(zbx_interface_status_t *interface_status);
void			zbx_poller_item_free(zbx_poller_item_t *poller_item);

#endif
