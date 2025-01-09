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

#ifndef ZABBIX_LLD_MANAGER_H
#define ZABBIX_LLD_MANAGER_H

#include "zbxthreads.h"
#include "zbxtime.h"
#include "zbxalgo.h"

typedef struct zbx_lld_value
{
	/* the LLD rule id */
	zbx_uint64_t		itemid;

	char			*value;
	char			*error;
	zbx_timespec_t		ts;

	zbx_uint64_t		lastlogsize;
	int			mtime;
	unsigned char		meta;
	struct	zbx_lld_value	*prev;
	struct	zbx_lld_value	*next;
}
zbx_lld_data_t;

/* queue of values for one host */
typedef struct
{
	/* the LLD rule host id */
	zbx_uint64_t	hostid;

	/* the number of queued values */
	int		values_num;

	/* the newest value in queue */
	zbx_lld_data_t	*tail;

	/* the oldest value in queue */
	zbx_lld_data_t	*head;
}
zbx_lld_rule_t;

typedef struct
{
	/* the LLD rule item id */
	zbx_uint64_t	itemid;

	/* the number of queued values */
	int		values_num;
}
zbx_lld_rule_info_t;

ZBX_PTR_VECTOR_DECL(lld_rule_info_ptr, zbx_lld_rule_info_t*)

typedef struct
{
	zbx_get_config_forks_f	get_process_forks_cb_arg;
}
zbx_thread_lld_manager_args;

ZBX_THREAD_ENTRY(lld_manager_thread, args);

#endif
