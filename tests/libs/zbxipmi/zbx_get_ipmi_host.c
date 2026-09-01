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

/* zbx_get_ipmi_host(), zbx_allocate_ipmi_host() and the 'hostset' hashset they share are static, so the */
/* source file is included directly to get access to them (ZBX-25759 regression coverage). */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

static zbx_ipmi_host_t	*allocate_from_yaml(const char *prefix)
{
	char	path[MAX_STRING_LEN];

	zbx_snprintf(path, sizeof(path), "%s.ip", prefix);
	const char	*ip = zbx_mock_get_parameter_string(path);

	zbx_snprintf(path, sizeof(path), "%s.port", prefix);
	int	port = zbx_mock_get_parameter_int(path);

	zbx_snprintf(path, sizeof(path), "%s.authtype", prefix);
	int	authtype = zbx_mock_get_parameter_int(path);

	zbx_snprintf(path, sizeof(path), "%s.privilege", prefix);
	int	privilege = zbx_mock_get_parameter_int(path);

	zbx_snprintf(path, sizeof(path), "%s.username", prefix);
	const char	*username = zbx_mock_get_parameter_string(path);

	zbx_snprintf(path, sizeof(path), "%s.password", prefix);
	const char	*password = zbx_mock_get_parameter_string(path);

	return zbx_allocate_ipmi_host(ip, port, authtype, privilege, username, password);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t	*host_a, *host_b, *found;
	const char	*exp_found;

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	/* two hosts are registered so a lookup can be shown to return the correctly matching struct */
	/* (not garbage read past an undersized hashset entry, and not a neighbouring host's data)   */
	host_a = allocate_from_yaml("in.host_a");
	host_b = allocate_from_yaml("in.host_b");

	found = zbx_get_ipmi_host(
			zbx_mock_get_parameter_string("in.lookup.ip"),
			zbx_mock_get_parameter_int("in.lookup.port"),
			zbx_mock_get_parameter_int("in.lookup.authtype"),
			zbx_mock_get_parameter_int("in.lookup.privilege"),
			zbx_mock_get_parameter_string("in.lookup.username"),
			zbx_mock_get_parameter_string("in.lookup.password"));

	exp_found = zbx_mock_get_parameter_string("out.found");

	if (0 == strcmp(exp_found, "yes"))
	{
		if (NULL == found)
			fail_msg("expected a matching host to be found, got NULL");

		zbx_mock_assert_str_eq("found host ip", zbx_mock_get_parameter_string("out.ip"), found->ip);
		zbx_mock_assert_int_eq("found host port", zbx_mock_get_parameter_int("out.port"), found->port);
		zbx_mock_assert_str_eq("found host username", zbx_mock_get_parameter_string("out.username"),
				found->username);
		zbx_mock_assert_str_eq("found host password", zbx_mock_get_parameter_string("out.password"),
				found->password);
	}
	else
		zbx_mock_assert_ptr_eq("no host should be found", NULL, found);

	zbx_free_ipmi_host(host_a);
	zbx_free_ipmi_host(host_b);
	zbx_hashset_destroy(&hostset);
}
