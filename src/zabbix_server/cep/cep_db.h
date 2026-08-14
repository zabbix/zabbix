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

#ifndef ZABBIX_CEP_DB_H
#define ZABBIX_CEP_DB_H

#include "zbxmw.h"
#include "zbxexport.h"
#include "zbxtypes.h"
#include "zbxipcservice.h"

void	cep_db_flush_events(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks);
void	cep_db_process_actions(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks,
		zbx_ipc_async_socket_t *rtc);
void	cep_db_export_events(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks,
		zbx_export_file_t *problem_export);
void	cep_db_add_tags(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks);
void	cep_db_sync_events(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks);
void	cep_db_add_acknowledges(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks);
void	cep_db_update_rule_errors(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks);
void	cep_db_sync_windows(zbx_dbconn_pool_t *dbpool, const zbx_vector_mw_task_ptr_t *tasks);

#endif
