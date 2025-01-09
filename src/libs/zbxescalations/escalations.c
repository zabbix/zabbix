/*
** Copyright (C) 2001-2025 Zabbix SIA
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
	zbx_vector_uint64_t	*escalator_escalationids;

	escalator_escalationids = (zbx_vector_uint64_t *)zbx_malloc(NULL, (size_t)escalators_number *
			sizeof(zbx_vector_uint64_t));

	for (int i = 0; i < escalators_number; i++)
		zbx_vector_uint64_create(&escalator_escalationids[i]);

	for (int i = 0; i < escalations->values_num; i++)
	{
		zbx_escalation_new_t	*escalation = escalations->values[i];

		if (EVENT_OBJECT_TRIGGER == escalation->event->object)
		{
			int	escalator_process_num;

			escalator_process_num = (int)(escalation->event->objectid % (uint64_t)escalators_number + 1);
			zbx_vector_uint64_append(&escalator_escalationids[escalator_process_num - 1],
					escalation->escalationid);
		}
	}

	for (int i = 0; i < escalators_number; i++)
	{
		if (0 != escalator_escalationids[i].values_num)
		{
			int		escalations_per_process = escalator_escalationids[i].values_num;
			zbx_uint32_t	notify_size = (zbx_uint32_t)(sizeof(int) +
					(size_t)escalations_per_process * sizeof(zbx_uint64_t));
			unsigned char	*notify_data = zbx_malloc(NULL, notify_size), *ptr = notify_data;

			ptr += zbx_serialize_value(ptr, escalations_per_process);
			for (int j = 0; j < escalations_per_process; j++)
				ptr += zbx_serialize_value(ptr, escalator_escalationids[i].values[j]);

			rtc_notify_generic_cb(rtc, ZBX_PROCESS_TYPE_ESCALATOR, i + 1, ZBX_RTC_ESCALATOR_NOTIFY,
					(char *)notify_data, notify_size);
			zbx_free(notify_data);
		}
	}

	for (int i = 0; i < escalators_number; i++)
		zbx_vector_uint64_destroy(&escalator_escalationids[i]);

	zbx_free(escalator_escalationids);
}

void	zbx_escalation_new_ptr_free(zbx_escalation_new_t *escalation)
{
	if (0 == escalation->actionid)
		zbx_free(escalation->event);

	zbx_free(escalation);
}
