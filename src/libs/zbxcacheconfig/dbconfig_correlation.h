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

#ifndef ZABBIX_DBCONFIG_CEP_H
#define ZABBIX_DBCONFIG_CEP_H

#include "dbconfig.h"

typedef struct
{
	zbx_uint64_t		correlationid;
	zbx_correlation_t	*correlation;
}
zbx_correlation_ref_t;

typedef struct
{
	zbx_uint64_t		conditionid;
	zbx_uint64_t		correlationid;
	zbx_corr_condition_t	*condition;
}
zbx_corr_condition_ref_t;

typedef struct
{
	zbx_hashset_t			correlations;
	zbx_hashset_t			corr_conditions;
	zbx_hashset_t			corr_operations;

	zbx_atomic_int_t		correlations_num;

	zbx_correlation_cache_handle_t	handle;

	pthread_mutex_t			lock;
}
zbx_correlation_cache_t;

zbx_correlation_cache_t	*correlation_cache_create(void);
void	correlation_cache_destroy(zbx_correlation_cache_t *cache);

void	correlation_cache_sync(zbx_dbsync_t *correlation_sync, zbx_dbsync_t *corr_operation_sync,
	zbx_dbsync_t *corr_condition_sync);

void	correlation_cache_dump(void);

#endif
