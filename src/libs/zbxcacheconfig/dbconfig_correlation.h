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

#ifndef ZABBIX_DBCONFIG_CORRELATION_H
#define ZABBIX_DBCONFIG_CORRELATION_H

#include "dbconfig.h"
#include "zbxcacheconfig.h"
#include "zbxtypes_ext.h"
#include "zbxalgo.h"

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

	zbx_correlation_config_handle_t	handle;

	pthread_mutex_t			lock;
}
zbx_correlation_config_t;

zbx_correlation_config_t	*correlation_config_create(void);
void	correlation_config_destroy(zbx_correlation_config_t *cache);

void	correlation_config_sync(zbx_dbsync_t *correlation_sync, zbx_dbsync_t *corr_operation_sync,
	zbx_dbsync_t *corr_condition_sync);

void	correlation_config_dump(void);

#endif
