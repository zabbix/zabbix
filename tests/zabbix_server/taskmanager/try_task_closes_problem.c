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

#include "../zbxtests.h"

#include "../zabbix_server/taskmanager/taskmanager.h"

int	CONFIG_PREPROCMAN_FORKS		= 1;
int	CONFIG_PREPROCESSOR_FORKS	= 3;

char	*curr_tested_function = NULL;
char	*curr_wrapped_function = NULL;
char	*curr_case_name = NULL;

void	__wrap_DCconfig_lock_triggers_by_triggerids(zbx_vector_uint64_t *triggerids_in,
		zbx_vector_uint64_t *triggerids_out)
{
	int		i;
	zbx_uint64_t 	out_func_param1;

	curr_wrapped_function = "DCconfig_lock_triggers_by_triggerids";

	for (i = 0; i < cases[curr_case_idx].function_num; i++)
	{
		if (0 == strcmp(cases[curr_case_idx].functions[i].name, "DCconfig_lock_triggers_by_triggerids") &&
				cases[curr_case_idx].functions[i].data.data_num > 0)
		{
			ZBX_STR2UINT64(out_func_param1, cases[curr_case_idx].functions[i].data.values[0]);
			zbx_vector_uint64_append(triggerids_out, out_func_param1);

			break;
		}
	}
}

void	__wrap_DCconfig_unlock_triggers(const zbx_vector_uint64_t *triggerids)
{
}

void test_try_task_closes_problem()
{
	int	i, ret, param1, res, executed_num = 0;

	curr_tested_function = "try_task_closes_problem";

	for (i = 0; i < case_num; i++)
	{
		if (0 == strcmp(cases[i].tested_function, curr_tested_function))
		{
			curr_case_name = cases[curr_case_idx].case_name;

			param1 = atoi(get_in_param_by_index(0));

			ret = tm_try_task_close_problem(param1);

			res = atoi(get_out_param_by_index(0));

			assert_int_equal(ret, res);

			executed_num++;
		}
	}

	if (0 == executed_num)
		fail_msg("Test was not executed");
}
