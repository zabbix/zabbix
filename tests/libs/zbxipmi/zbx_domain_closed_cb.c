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

/* zbx_domain_closed_cb() is static and shares the RETURN_IF_CB_DATA_INVALID(cb_data) guard used by every */
/* OpenIPMI callback in this file; it is picked here as the representative one, being the only such       */
/* callback that takes no OpenIPMI-typed arguments besides cb_data. The source file is included directly  */
/* to reach it and the static 'hostset' it validates cb_data against (ZBX-25759 regression coverage).     */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*scenario = zbx_mock_get_parameter_string("in.cb_data");
	int		exp_processed = (0 == strcmp(zbx_mock_get_parameter_string("out.processed"), "yes")) ? 1 : 0;
	zbx_ipmi_host_t	*h = NULL;
	void		*cb_data = NULL;

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	if (0 != strcmp(scenario, "null"))
	{
		h = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");
		cb_data = h;

		/* "unregistered" simulates OpenIPMI delivering a callback for a host that has already been */
		/* removed from 'hostset' (e.g. by zbx_delete_inactive_ipmi_hosts()) - the exact ZBX-25759   */
		/* scenario, without literally freeing the memory so the sentinels below stay safe to read.  */
		if (0 == strcmp(scenario, "unregistered"))
			zbx_hashset_remove(&hostset, &h);

		/* sentinels: zbx_domain_closed_cb() must leave these untouched when cb_data is invalid */
		h->domain_up = 1;
		h->done = 0;
	}

	zbx_domain_closed_cb(cb_data);

	if (NULL != h)
	{
		zbx_mock_assert_int_eq("h->done", exp_processed, h->done);
		zbx_mock_assert_int_eq("h->domain_up", 0 != exp_processed ? 0 : 1, h->domain_up);

		zbx_free_ipmi_host(h);
	}

	zbx_hashset_destroy(&hostset);
}
