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
#include "zbxmockdata.h"
#include "../../../src/zabbix_server/taskmanager/taskmanager.h"

extern char	*curr_wrapped_function;

void	__wrap_DCconfig_lock_triggers_by_triggerids(zbx_vector_uint64_t *triggerids_in,
		zbx_vector_uint64_t *triggerids_out)
{
	zbx_uint64_t	triggerid;

	ZBX_UNUSED(triggerids_in);

	curr_wrapped_function = "DCconfig_lock_triggers_by_triggerids";

	ZBX_STR2UINT64(triggerid, get_out_func_param_by_name("triggerids"));
	zbx_vector_uint64_append(triggerids_out, triggerid);
}

void	__wrap_DCconfig_unlock_triggers(const zbx_vector_uint64_t *triggerids)
{
}

void	zbx_mock_test_entry(void **state)
{
	int	ret, taskid, res;

	ZBX_UNUSED(state);

	taskid = atoi(get_in_param_by_name("taskid"));
	ret = tm_try_task_close_problem(taskid);
	res = atoi(get_out_param_by_name("return"));

	assert_int_equal(ret, res);
}
