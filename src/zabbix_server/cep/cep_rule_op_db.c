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
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcep.h"
#include "zbxcommon.h"
#include "zbxmw.h"
#include "zbxdbhigh.h"

/******************************************************************************
 *                                                                            *
 * Purpose: create db event                                                   *
 *                                                                            *
 * Parameters: origin   - [IN] CEP event origin                               *
 *             name     - [IN] event name                                     *
 *             clock    - [IN] event timestamp (seconds)                      *
 *             ns       - [IN] event timestamp (nanoseconds)                  *
 *             severity - [IN] event severity                                 *
 *             value    - [IN] event value                                    *
 *             tags     - [IN] event tags (optional)                          *
 *                                                                            *
 * Return value: pointer to the created db event                              *
 *                                                                            *
 ******************************************************************************/
static zbx_db_event	*cep_db_event_create(const zbx_cep_origin_t *origin, const char *name, int clock, int ns,
		int serverity, int value, const zbx_vector_tag_t *tags)
{
	zbx_db_event	*db_event;

	db_event = (zbx_db_event *)zbx_calloc(NULL, 1, sizeof(zbx_db_event));

	db_event->source = origin->source;
	db_event->object = origin->object;
	db_event->objectid = origin->objectid;
	db_event->clock = clock;
	db_event->ns = ns;
	db_event->severity = serverity;
	db_event->value = value;
	db_event->name = zbx_strdup(NULL, name);

	zbx_vector_tags_ptr_create(&db_event->tags);
	if (NULL != tags)
	{
		zbx_vector_tags_ptr_reserve(&db_event->tags, (size_t)tags->values_num);
		for (int i = 0; i < tags->values_num; i++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));

			tag->tag = zbx_strdup(NULL, tags->values[i].tag);
			tag->value = zbx_strdup(NULL, tags->values[i].value);

			zbx_vector_tags_ptr_append(&db_event->tags, tag);
		}
	}

	return db_event;
}

void	cep_operation_db_execute_close_event(zbx_uint64_t ruleid, zbx_cep_event_context_t *ctx,
		zbx_vector_mw_task_ptr_t *tasks)
{
	zbx_cep_event_t	*event;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != (event = cep_event_context_acquire_event(ctx)))
	{
		zbx_mw_task_t	*t;
		zbx_db_event	*db_event;

		db_event = cep_db_event_create(&event->origin, event->name, event->clock, event->ns, event->severity,
				TRIGGER_VALUE_OK, &event->tags);

		t = cep_create_task_close_event(db_event, event->eventid, 0, 0, ruleid);
		zbx_vector_mw_task_ptr_append(tasks, t);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

