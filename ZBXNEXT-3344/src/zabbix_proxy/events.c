/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "common.h"
#include "zbxalgo.h"
#include "db.h"

int	add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag)
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

	return FAIL;
}

int	close_event(zbx_uint64_t eventid, unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *ts, zbx_uint64_t userid, zbx_uint64_t correlationid, zbx_uint64_t c_eventid,
		const char *trigger_description, const char *trigger_expression,
		const char *trigger_recovery_expression, unsigned char trigger_priority, unsigned char trigger_type,
		const zbx_vector_ptr_t *trigger_tags, unsigned char trigger_correlation_mode,
		const char *trigger_correlation_tag)
{
	ZBX_UNUSED(eventid);
	ZBX_UNUSED(source);
	ZBX_UNUSED(object);
	ZBX_UNUSED(objectid);
	ZBX_UNUSED(ts);
	ZBX_UNUSED(userid);
	ZBX_UNUSED(correlationid);
	ZBX_UNUSED(c_eventid);
	ZBX_UNUSED(trigger_description);
	ZBX_UNUSED(trigger_expression);
	ZBX_UNUSED(trigger_recovery_expression);
	ZBX_UNUSED(trigger_priority);
	ZBX_UNUSED(trigger_type);
	ZBX_UNUSED(trigger_tags);
	ZBX_UNUSED(trigger_correlation_mode);
	ZBX_UNUSED(trigger_correlation_tag);

	return FAIL;
}

int	process_events()
{
	return 0;
}

int	process_trigger_events(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock, int mode)
{
	ZBX_UNUSED(trigger_diff);
	ZBX_UNUSED(triggerids_lock);
	ZBX_UNUSED(mode);

	return 0;
}

int	flush_correlated_events()
{
	return 0;
}
