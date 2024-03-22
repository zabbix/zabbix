/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "zbxescalations.h"

#include "zbxalgo.h"
#include "zbxdbhigh.h"
#include "zbxipcservice.h"
#include "zbx_rtc_constants.h"
#include "zbxserialize.h"

static int				escalators_number;
static zbx_rtc_notify_generic_cb_t	rtc_notify_generic_cb;

void	zbx_init_escalations(int escalators_num, zbx_rtc_notify_generic_cb_t rtc_notify_cb)
{
	escalators_number = escalators_num;
	rtc_notify_generic_cb = rtc_notify_cb;
}

void	zbx_start_escalations(zbx_ipc_async_socket_t *rtc, zbx_vector_escalation_new_ptr_t *escalations)
{
	zbx_vector_uint64_pair_t	*trigger_escalations;
	zbx_uint64_pair_t		pair;

	trigger_escalations = (zbx_vector_uint64_pair_t *)zbx_malloc(NULL, (size_t)escalators_number *
			sizeof(zbx_vector_uint64_pair_t));

	for (int i = 0; i < escalators_number; i++)
		zbx_vector_uint64_pair_create(&trigger_escalations[i]);

	for (int i = 0; i < escalations->values_num; i++)
	{
		zbx_escalation_new_t	*escalation = escalations->values[i];

		if (EVENT_OBJECT_TRIGGER == escalation->event->object)
		{
			zbx_uint64_t	triggerid = escalation->event->objectid;
			int		escalator_process_num;

			pair.first = escalation->escalationid;
			pair.second = triggerid;
			escalator_process_num = (int)(triggerid % (uint64_t)escalators_number + 1);
			zbx_vector_uint64_pair_append_ptr(&trigger_escalations[escalator_process_num - 1], &pair);
		}
	}

	for (int i = 0; i < escalators_number; i++)
	{
		if (0 != trigger_escalations[i].values_num)
		{
			int		escalations_per_process = trigger_escalations[i].values_num;
			zbx_uint32_t	notify_size = (zbx_uint32_t)(sizeof(zbx_uint32_t) +
					2 * (size_t)escalations_per_process * sizeof(zbx_uint64_t));
			unsigned char	*notify_data = zbx_malloc(NULL, notify_size), *ptr = notify_data;

			ptr += zbx_serialize_value(ptr, escalations_per_process);
			for (int j = 0; j < escalations_per_process; j++)
			{
				pair = trigger_escalations[i].values[j];
				ptr += zbx_serialize_value(ptr, pair.first);
				ptr += zbx_serialize_value(ptr, pair.second);
			}

			rtc_notify_generic_cb(rtc, ZBX_PROCESS_TYPE_ESCALATOR, i + 1, ZBX_RTC_ESCALATOR_NOTIFY,
					(char *)notify_data, notify_size);
			zbx_free(notify_data);
		}
	}

	for (int i = 0; i < escalators_number; i++)
		zbx_vector_uint64_pair_destroy(&trigger_escalations[i]);

	zbx_free(trigger_escalations);
}

typedef struct
{
	zbx_uint64_t	actionid;
	zbx_uint64_t	escalationid;
	zbx_db_event	*event;
}
zbx_escalation_new2_t;

void	zbx_escalation_new_ptr_free(zbx_escalation_new_t *data)
{
	zbx_escalation_new2_t	*escalation = (zbx_escalation_new2_t *)data;

	if (0 == escalation->actionid)
		zbx_free(escalation->event);
	zbx_free(data);
}
