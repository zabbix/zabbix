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

#ifndef ZABBIX_OTEL_CLICKHOUSE_H
#define ZABBIX_OTEL_CLICKHOUSE_H

#include "libs/zbxhistory/history_curl.h"
#include "otel_dataset.h"
#include "zbxcommon.h"

typedef struct
{
	char	*url;
	char	*database;
	char	*username;
	char	*password;
}
zbx_otel_clickhouse_cfg_t;

typedef struct
{
	CURL			*handle;
	zbx_curl_response_t	resp;
}
zbx_otel_clickhouse_t;

int	otel_clickhouse_init(zbx_otel_clickhouse_t *conn, const zbx_otel_clickhouse_cfg_t *cfg, char **error);
void	otel_clickhouse_clear(zbx_otel_clickhouse_t *conn);
void	otel_clickhouse_commit(zbx_otel_clickhouse_t *conn, const zbx_otel_clickhouse_cfg_t *cfg,
	zbx_otel_dataset_t *ds);

#endif
