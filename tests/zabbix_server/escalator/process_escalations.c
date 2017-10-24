/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "../../zbxtests.h"

#include "zbxmocktest.h"

#include "../../../src/zabbix_server/escalator/escalator.h"

/* make sure that mock functions match unwrapped prototypes */

#define substitute_simple_macros	__wrap_substitute_simple_macros
#define check_action_condition		__wrap_check_action_condition

#include "zbxserver.h"
#include "../../../src/zabbix_server/actions.h"

int	__wrap_substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, const DB_EVENT *r_event,
		zbx_uint64_t *userid, const zbx_uint64_t *hostid, const DC_HOST *dc_host, DC_ITEM *dc_item,
		DB_ALERT *alert, const DB_ACKNOWLEDGE *ack, char **data, int macro_type, char *error, int maxerrlen)
{
	return SUCCEED;
}

int	__wrap_check_action_condition(const DB_EVENT *event, DB_CONDITION *condition)
{
	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	int		c, ret, param1, param2, param3;

	for (c = 0; c < case_num; c++)
	{
		if (0 == strcmp(cases[c].tested_function, "process_escalations"))
		{
			will_return_always(get_case_name, cases[c].case_name);

			param1 = atoi(cases[c].in_params.values[0]);
			param2 = atoi(cases[c].in_params.values[1]);
			param3 = atoi(cases[c].in_params.values[2]);

			ret = process_escalations(param1, &param2, (unsigned int) param3);

			assert_int_equal(ret, atoi(cases[c].out_params.values[0]));
		}
	}
}
