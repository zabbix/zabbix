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

#include "zbxcommon.h"
#include "zbxtime.h"

void	__wrap_zbx_preprocess_item_value(zbx_uint64_t itemid, unsigned char item_value_type, unsigned char item_flags,
		AGENT_RESULT *result, zbx_timespec_t *ts, unsigned char state, char *error);

void	__wrap_zbx_preprocessor_flush(void);

void	__wrap_zbx_preprocess_item_value(zbx_uint64_t itemid, unsigned char item_value_type, unsigned char item_flags,
		AGENT_RESULT *result, zbx_timespec_t *ts, unsigned char state, char *error)
{
	ZBX_UNUSED(itemid);
	ZBX_UNUSED(item_value_type);
	ZBX_UNUSED(item_flags);
	ZBX_UNUSED(result);
	ZBX_UNUSED(ts);
	ZBX_UNUSED(state);
	ZBX_UNUSED(error);
}

void	__wrap_zbx_preprocessor_flush(void)
{
}
