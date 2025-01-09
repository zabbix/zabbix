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

#include "zbxmocktest.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "../../../include/zbxsysinfo.h"
#include "../../../src/libs/zbxsysinfo/sysinfo.h"

int	__wrap_gethostname(char *name, size_t len);
int	__wrap_getaddrinfo(const char *node, const char *service, const struct addrinfo *hints, struct addrinfo **res);
void	__wrap_freeaddrinfo(struct addrinfo *res);

int	__wrap_gethostname(char *name, size_t len)
{
	const char	*gethostname_str;
	size_t		length = 0;

	gethostname_str = zbx_mock_get_parameter_string("in.gethostname");

	if (NULL == gethostname_str || '\0' == *gethostname_str)
		return 1;

	length = (size_t)strlen(gethostname_str);

	for (size_t i = 0; i < len && i < length; i++)
		name[i] = gethostname_str[i];

	name[len > length ? length : len - 1] = '\0';

	return 0;
}

int	__wrap_getaddrinfo(const char *node, const char *service, const struct addrinfo *hints, struct addrinfo **res)
{
	const char	*fqdn_str;

	ZBX_UNUSED(node);
	ZBX_UNUSED(service);
	ZBX_UNUSED(hints);

	fqdn_str = zbx_mock_get_parameter_string("in.fqdn");


	if (NULL == fqdn_str || '\0' == *fqdn_str)
		return 1;

	*res = zbx_malloc(NULL, sizeof(struct addrinfo));
	(*res)->ai_canonname = zbx_strdup(NULL, fqdn_str);

	return 0;
}

void	__wrap_freeaddrinfo(struct addrinfo *res)
{
	if (NULL != res)
	{
		if (NULL != res->ai_canonname)
			zbx_free(res->ai_canonname);

		zbx_free(res);
	}
}

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST	request;
	AGENT_RESULT	result;
	int		returned_code, expected_code;
	char		*hostname;
	const char	*expected_fqdn;

	ZBX_UNUSED(state);

	expected_code = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	expected_fqdn = zbx_mock_get_parameter_string("out.result");
	hostname = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.hostname"));

	zbx_init_agent_result(&result);
	zbx_init_agent_request(&request);

	zbx_parse_item_key("system.hostname[fqdn]", &request);
	returned_code = hostname_handle_params(&request, &result, &hostname);

	if (FAIL == returned_code)
		zbx_free(hostname);

	zbx_mock_assert_result_eq("Return value", expected_code, returned_code);

	if (SUCCEED == returned_code)
	{
		zbx_mock_assert_int_eq("Result value type", AR_STRING, result.type & AR_STRING);
		zbx_mock_assert_str_eq("FQDN string", expected_fqdn, result.str);
	}

	zbx_free_agent_result(&result);
	zbx_free_agent_request(&request);
}
