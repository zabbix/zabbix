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

#ifndef ZABBIX_OTEL_EXPORTER_H
#define ZABBIX_OTEL_EXPORTER_H

#include "otel_dataset.h"
#include "otel_clickhouse.h"
#include "zbxcommon.h"
#include "zbxalgo.h"

typedef enum
{
	OTEL_EXPORTER_CLICKHOUSE
}
zbx_otel_exporter_type_t;

typedef union
{
	zbx_otel_clickhouse_cfg_t	clickhouse;
}
zbx_otel_exporter_cfg_data_t;

typedef struct
{
	zbx_otel_exporter_type_t	type;
	zbx_otel_exporter_cfg_data_t	data;
}
zbx_otel_exporter_cfg_t;

typedef union
{
	zbx_otel_clickhouse_t	clickhouse;
}
zbx_otel_exporter_conn_t;

typedef struct
{
	const zbx_otel_exporter_cfg_t	*cfg;
	zbx_otel_exporter_conn_t	conn;
}
zbx_otel_exporter_t;

ZBX_PTR_VECTOR_LITE_DECL(otel_exporter_ptr, zbx_otel_exporter_t *)

typedef struct
{
	zbx_otel_exporter_cfg_t		cfg;

	zbx_vector_otel_exporter_ptr_t	exporters;

	pthread_mutex_t			lock;
}
zbx_otel_exporter_pool_t;

zbx_otel_exporter_pool_t	*otel_exporter_pool_create(zbx_otel_exporter_cfg_t *cfg, char **error);
void	otel_exporter_pool_destroy(zbx_otel_exporter_pool_t *pool);

zbx_otel_exporter_t	*otel_exporter_acquire(zbx_otel_exporter_pool_t *pool);
void	otel_exporter_release(zbx_otel_exporter_pool_t *pool, zbx_otel_exporter_t *exporter);
int	otel_exporter_cfg_init(zbx_otel_exporter_cfg_t *cfg, const char *options, char **error);

#define OTEL_COMMIT_OK		0x00
#define OTEL_COMMIT_ERR		0x01
#define OTEL_COMMIT_RETRY	0x02

int	otel_exporter_commit(zbx_otel_exporter_t *exporter, zbx_otel_dataset_t *ds);


#endif

