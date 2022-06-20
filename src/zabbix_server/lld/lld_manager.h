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

#ifndef ZABBIX_LLD_MANAGER_H
#define ZABBIX_LLD_MANAGER_H

#include "zbxthreads.h"

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

ZBX_THREAD_ENTRY(lld_manager_thread, args);

#endif
