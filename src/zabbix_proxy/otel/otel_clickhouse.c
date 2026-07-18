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

#include "otel_clickhouse.h"
#include "otel_dataset.h"
#include "zbxlog.h"

int	otel_clickhouse_init(zbx_otel_clickhouse_t *conn, const zbx_otel_clickhouse_cfg_t *cfg, char **error)
{
	if (NULL == (conn->mhandle = curl_multi_init()))
	{
		*error = zbx_strdup(NULL, "cannot initialize curl multi session");
		return FAIL;
	}

	return SUCCEED;
}

void	otel_clickhouse_clear(zbx_otel_clickhouse_t *conn)
{
	curl_multi_cleanup(conn->mhandle);
}

void	otel_clickhouse_commit(zbx_otel_clickhouse_t *conn, const zbx_otel_clickhouse_cfg_t *cfg,
		zbx_otel_dataset_t *ds)
{

	/* TODO: remove forced trace loglevel */
	int	loglevel = zbx_set_log_level(LOG_LEVEL_TRACE);

	otel_dataset_dump(ds);

	zbx_set_log_level(loglevel);

	otel_dataset_clear(ds);
}
