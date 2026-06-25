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

#include "cep_rule_op_db.h"
#include "cep_rule.h"
#include "cep_task.h"
#include "zbx_cep.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "zbxmw.h"
#include "zbxdbhigh.h"

void	cep_operation_db_execute_close_event(zbx_uint64_t ruleid, zbx_cep_event_context_t *ctx,
		zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_event_t	*event;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != (event = cep_event_context_get_event(ctx)))
	{
		zbx_mw_task_t	*t;
		zbx_db_event	*db_event;

		db_event = cep_db_event_create(&event->origin, event->name, event->clock, event->ns, event->severity,
				TRIGGER_VALUE_OK, &event->tags);

		cep_event_expect(db_event);

		t = cep_create_task_close_event(db_event, event->eventid, 0, 0, ruleid);
		zbx_vector_mw_task_ptr_append(tasks, t);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

