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

/* get_value_ipmi(), get_discovery_ipmi() and zbx_set_ipmi_control_value() are the three public entry      */
/* points into this file, and all three start with the same guard: "if (NULL == os_hnd) { ...; return }".  */
/* This test binary never calls zbx_init_ipmi(), so the static file-scope 'os_hnd' stays NULL (its default  */
/* zero-init value) for the whole process, exercising that guard for real on all three functions without    */
/* needing any IPMI connection at all. checks_ipmi.c is included directly to reach 'os_hnd' and confirm it  */
/* really is NULL going in (defensive - a stray zbx_init_ipmi() call anywhere else in this TU would silently */
/* invalidate the whole premise of this test).                                                              */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*func = zbx_mock_get_parameter_string("in.func");
	char		*value = NULL;
	int		ret;

	ZBX_UNUSED(state);

	if (NULL != os_hnd)
		fail_msg("test premise violated: os_hnd is not NULL");

	if (0 == strcmp(func, "get_value_ipmi"))
	{
		ret = get_value_ipmi(1, "127.0.0.1", 623, 2, 4, "user", "pass", "sensor", &value);
		zbx_mock_assert_int_eq("get_value_ipmi() return value", CONFIG_ERROR, ret);
	}
	else if (0 == strcmp(func, "get_discovery_ipmi"))
	{
		ret = get_discovery_ipmi(1, "127.0.0.1", 623, 2, 4, "user", "pass", &value);
		zbx_mock_assert_int_eq("get_discovery_ipmi() return value", CONFIG_ERROR, ret);
	}
	else if (0 == strcmp(func, "zbx_set_ipmi_control_value"))
	{
		ret = zbx_set_ipmi_control_value(1, "127.0.0.1", 623, 2, 4, "user", "pass", "sensor", 1, &value);
		zbx_mock_assert_int_eq("zbx_set_ipmi_control_value() return value", NOTSUPPORTED, ret);
	}
	else
	{
		fail_msg("unknown 'in.func' value: %s", func);
		return;
	}

	if (NULL == value || '\0' == *value)
		fail_msg("the 'not initialised' message should have been set, got %s", NULL == value ? "NULL" :
				"an empty string");

	zbx_free(value);
}
