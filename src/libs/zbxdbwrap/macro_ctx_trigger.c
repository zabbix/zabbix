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

#include "zbxdbwrap.h"

#include "zbxexpr.h"
#include "zbxdbhigh.h"
#include "zbx_expression_constants.h"

/******************************************************************************
 *                                                                            *
 * Purpose: resolves macros in trigger expression context                     *
 *                                                                            *
 * Parameters: p          - [IN] macro resolver data structure                *
 *             args       - [IN] list of variadic parameters                  *
 *                               Mandatory content:                           *
 *                                - zbx_db_event *event: trigger event        *
 *             replace_to - [OUT] pointer to value to replace macro with      *
 *             data       - [IN/OUT] pointer to input data string             *
 *             error      - [OUT] pointer to pre-allocated error message      *
 *                                buffer                                      *
 *             maxerrlen  - [IN] size of error message buffer                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_macro_event_trigger_expr_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to,
		char **data, char *error, size_t maxerrlen)
{
	// Passed arguments
	const zbx_db_event	*event = va_arg(args, const zbx_db_event *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		if (0 == strcmp(p->macro, MVAR_TRIGGER_VALUE))
			*replace_to = zbx_dsprintf(*replace_to, "%d", event->value);
	}

	return SUCCEED;
}
