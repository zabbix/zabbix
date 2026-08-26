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

#ifndef ZABBIX_EVENTS_H
#define ZABBIX_EVENTS_H

#include "zbxdbhigh.h"
#include "zbxexport.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"

void	zbx_initialize_events(void);
void	zbx_uninitialize_events(void);
void	zbx_add_event(zbx_db_event *event);

int	zbx_close_problem(zbx_uint64_t triggerid, zbx_uint64_t eventid, zbx_uint64_t userid);

int	zbx_process_events(void);
void	zbx_clean_events(void);
void	zbx_export_events(zbx_dbconn_t *db, const zbx_vector_db_event_t *problems,
		const zbx_vector_db_event_recovery_t *recovery, zbx_export_file_t *problem_export,
		zbx_vector_connector_filter_t *connector_filters, unsigned char **data, size_t *data_alloc,
		size_t *data_offset);

zbx_db_event	*zbx_create_trigger_event(const zbx_dc_trigger_t *dc_trigger, int clock, int ns, int value);
zbx_db_event	*zbx_create_internal_event(unsigned char object, zbx_uint64_t objectid, int clock, int ns,
	int value, const char *error, zbx_dc_trigger_t *dc_trigger);

#endif
