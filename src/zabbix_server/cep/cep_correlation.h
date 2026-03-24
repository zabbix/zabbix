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

#ifndef ZABBIX_CEP_CORRELATION_H
#define ZABBIX_CEP_CORRELATION_H

#include "zbxmw.h"
#include "zbxdbhigh.h"

#define CORRELATION_RESULT_NONE		0x00
#define CORRELATION_RESULT_CLOSE_NEW	0x01
#define CORRELATION_RESULT_CLOSE_OLD	0x02

int	cep_correlate_db_event(const zbx_db_event *db_event, zbx_dbconn_pool_t *dbpool,
		zbx_vector_mw_task_ptr_t *tasks);

#endif
