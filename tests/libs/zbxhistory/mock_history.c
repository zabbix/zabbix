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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxmockdb.h"

#include "mock_history.h"

zbx_uint64_t	__wrap_zbx_dc_get_nextid(const char *table_name, int num)
{
	ZBX_UNUSED(table_name);
	ZBX_UNUSED(num);

	return 0;
}

int	__wrap_zbx_interface_availability_is_set(const zbx_interface_availability_t *ha)
{
	ZBX_UNUSED(ha);

	return SUCCEED;
}

int	__wrap_zbx_add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag,
		unsigned char trigger_value, const char *trigger_opdata, const char *error)
{
	ZBX_UNUSED(source);
	ZBX_UNUSED(object);
	ZBX_UNUSED(objectid);
	ZBX_UNUSED(timespec);
	ZBX_UNUSED(value);
	ZBX_UNUSED(trigger_description);
	ZBX_UNUSED(trigger_expression);
	ZBX_UNUSED(trigger_recovery_expression);
	ZBX_UNUSED(trigger_priority);
	ZBX_UNUSED(trigger_type);
	ZBX_UNUSED(trigger_tags);
	ZBX_UNUSED(trigger_correlation_mode);
	ZBX_UNUSED(trigger_correlation_tag);
	ZBX_UNUSED(trigger_value);
	ZBX_UNUSED(trigger_opdata);
	ZBX_UNUSED(error);

	return SUCCEED;
}

int	__wrap_zbx_process_events(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock)
{
	ZBX_UNUSED(trigger_diff);
	ZBX_UNUSED(triggerids_lock);

	return SUCCEED;
}

void	__wrap_zbx_clean_events(void)
{
}

void	__wrap_zbx_recalc_time_period(time_t *ts_from, int table_group)
{
	ZBX_UNUSED(ts_from);
	ZBX_UNUSED(table_group);
}

void	__wrap_zbx_sleep_loop(int sleeptime)
{
	ZBX_UNUSED(sleeptime);
}
