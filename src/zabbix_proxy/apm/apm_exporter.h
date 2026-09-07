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

#ifndef ZABBIX_APM_EXPORTER_H
#define ZABBIX_APM_EXPORTER_H

#include "apm_dataset.h"
#include "apm_clickhouse.h"
#include "zbxcfg.h"
#include "zbxcommon.h"
#include "zbxalgo.h"

typedef enum
{
	APM_EXPORTER_UNKNOWN,
	APM_EXPORTER_GLOBAL,
	APM_EXPORTER_CLICKHOUSE
}
zbx_apm_exporter_type_t;

typedef union
{
	zbx_apm_clickhouse_cfg_t	clickhouse;
}
zbx_apm_exporter_cfg_data_t;

typedef struct
{
	zbx_apm_exporter_type_t	type;
	zbx_vector_config_option_t	options;
	zbx_apm_exporter_cfg_data_t	data;
}
zbx_apm_exporter_cfg_t;

typedef union
{
	zbx_apm_clickhouse_t	clickhouse;
}
zbx_apm_exporter_conn_t;

typedef struct
{
	zbx_apm_exporter_cfg_t	cfg;
	zbx_apm_exporter_conn_t	conn;
}
zbx_apm_exporter_t;

ZBX_PTR_VECTOR_LITE_DECL(apm_exporter_ptr, zbx_apm_exporter_t *)

typedef struct
{
	zbx_apm_exporter_cfg_t		cfg;

	zbx_vector_apm_exporter_ptr_t	exporters;

	pthread_mutex_t			lock;
}
zbx_apm_exporter_pool_t;

zbx_apm_exporter_pool_t	*apm_exporter_pool_create(zbx_apm_exporter_cfg_t *cfg, char **error);
void	apm_exporter_pool_destroy(zbx_apm_exporter_pool_t *pool);

zbx_apm_exporter_t	*apm_exporter_acquire(zbx_apm_exporter_pool_t *pool);
void	apm_exporter_release(zbx_apm_exporter_pool_t *pool, zbx_apm_exporter_t *exporter);
int	apm_exporter_cfg_init(zbx_apm_exporter_cfg_t *cfg, const char *options, char **error);

#define APM_COMMIT_OK		0x00
#define APM_COMMIT_ERR		0x01
#define APM_COMMIT_RETRY	0x02

int	apm_exporter_commit(zbx_apm_exporter_t *exporter, zbx_apm_dataset_t *ds);


#endif

