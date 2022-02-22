/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

/*
** Since there is a lot of permutations of parameters for DCitem_poller_type_update() to test we
** organize test data in YAML in a different way. Namely, there are only two test cases - direct
** and by proxy. Each test case consists of test parameter sets. Each test set has parameters like
** item type, item key, poller etc. These are used as arguments to call DCitem_poller_type_update().
** There is also "ref" parameter in test case used to identify set in case of set failure.
**/

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "mutexs.h"
#include "dbcache.h"
#include "dbconfig.h"
#include "dc_item_poller_type_update_test.h"

/* defines from dbconfig.c */
#define ZBX_ITEM_COLLECTED		0x01
#define ZBX_HOST_UNREACHABLE		0x02

/* YAML fields for test set */
#define PARAM_MONITORED	("access")
#define PARAM_TYPE	("type")
#define PARAM_KEY	("key")
#define PARAM_POLLER	("poller")
#define PARAM_FLAGS	("flags")
#define PARAM_RESULT	("result")
#define PARAM_REF	("ref")

typedef struct
{
	enum { DIRECT, PROXY }	monitored;
	zbx_item_type_t		type;
	const char		*key;
	unsigned char		poller_type;
	unsigned char		flags;
	unsigned char		result_poller_type;
	zbx_uint32_t		test_number;
}
test_config_t;

typedef struct
{
	zbx_uint64_t	val;
	const char	*str;
}
str_map_t;

static void init_test(void)
{
	while (0 == CONFIG_PINGER_FORKS)
		CONFIG_PINGER_FORKS = rand();

	while (0 == CONFIG_POLLER_FORKS)
		CONFIG_POLLER_FORKS = rand();

	while (0 == CONFIG_IPMIPOLLER_FORKS)
		CONFIG_IPMIPOLLER_FORKS = rand();

	while (0 == CONFIG_JAVAPOLLER_FORKS)
		CONFIG_JAVAPOLLER_FORKS = rand();
}

#define _ZBX_MKMAP(c) { c,#c }

static unsigned char str2pollertype(const char *str)
{
	str_map_t	*e;
	str_map_t	map[] =
	{
		_ZBX_MKMAP(ZBX_NO_POLLER),			_ZBX_MKMAP(ZBX_POLLER_TYPE_NORMAL),
		_ZBX_MKMAP(ZBX_POLLER_TYPE_UNREACHABLE),	_ZBX_MKMAP(ZBX_POLLER_TYPE_IPMI),
		_ZBX_MKMAP(ZBX_POLLER_TYPE_PINGER),		_ZBX_MKMAP(ZBX_POLLER_TYPE_JAVA),
		_ZBX_MKMAP(ZBX_POLLER_TYPE_HISTORY), _ZBX_MKMAP(ZBX_POLLER_TYPE_ODBC),
		{ 0 }
	};

	for (e = &map[0]; NULL != e->str; e++)
	{
		if (0 == strcmp(e->str, str))
			return (unsigned char)e->val;
	}

	fail_msg("Cannot find string %s", str);

	return 0;
}

static int str2flags(const char *str)
{
	int		flags = 0;
	str_map_t	*e;

	str_map_t map[] =
	{
		{ 0, "0" },
		_ZBX_MKMAP(ZBX_ITEM_COLLECTED),
		_ZBX_MKMAP(ZBX_HOST_UNREACHABLE),
		{ 0 }
	};

	for (e = &map[0]; NULL != e->str; e++)
	{
		if (0 == strcmp(e->str, str))
			return e->val;
	}

	if (NULL != strstr(str, "ZBX_ITEM_COLLECTED"))
		flags |= ZBX_ITEM_COLLECTED;

	if (NULL != strstr(str, "ZBX_HOST_UNREACHABLE"))
		flags |= ZBX_HOST_UNREACHABLE;

	return flags;
}

static const char	*read_string(const zbx_mock_handle_t *handle, const char *read_str)
{
	const char		*str;
	zbx_mock_handle_t	string_handle;

	zbx_mock_assert_int_eq("Failed to access object member", ZBX_MOCK_SUCCESS,
			zbx_mock_object_member(*handle, read_str, &string_handle));

	zbx_mock_assert_int_eq("Failed to extract string", ZBX_MOCK_SUCCESS,
			zbx_mock_string(string_handle, &str));

	return str;
}

static void	read_test(const zbx_mock_handle_t *handle, test_config_t *test_config)
{
	const char	*str;

	str = read_string(handle, PARAM_MONITORED);
	test_config->monitored = 0 == strcmp(str, "DIRECT") ? DIRECT : PROXY;

	str = read_string(handle, PARAM_TYPE);
	test_config->type = zbx_mock_str_to_item_type(str);

	str = read_string(handle, PARAM_KEY);
	test_config->key = str;

	str = read_string(handle, PARAM_POLLER);
	test_config->poller_type = str2pollertype(str);

	/* only ZBX_HOST_UNREACHABLE and ZBX_ITEM_COLLECTED flags are used */
	str = read_string(handle, PARAM_FLAGS);
	test_config->flags = str2flags(str);

	str = read_string(handle, PARAM_RESULT);
	test_config->result_poller_type = str2pollertype(str);

	/* test number is for reference only */
	str = read_string(handle, PARAM_REF);
	test_config->test_number = (zbx_uint32_t)strtol(str, NULL, 10);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	mock_error;
	zbx_mock_handle_t	handle, elem_handle;
	test_config_t		test_config;
	ZBX_DC_ITEM		item;
	ZBX_DC_HOST		host;
	char			buffer[MAX_STRING_LEN];

	ZBX_UNUSED(state);

	mock_error = zbx_mock_in_parameter("sets", &handle);
	if (ZBX_MOCK_SUCCESS != mock_error)
		fail_msg("Invalid input path, %d", mock_error);

	init_test();

	while (ZBX_MOCK_SUCCESS == (mock_error = zbx_mock_vector_element(handle, &elem_handle)))
	{
		read_test(&elem_handle, &test_config);

		memset((void*)&host, 0, sizeof(host));
		memset((void*)&item, 0, sizeof(item));

		item.type = test_config.type;
		item.key = test_config.key;
		item.poller_type = test_config.poller_type;

		if (PROXY == test_config.monitored)
		{
			while (0 == host.proxy_hostid)
				host.proxy_hostid = rand();
		}

		zbx_snprintf(buffer, sizeof(buffer), "host is monitored %s and is %sreachable, item type is %d, "
				"item key is %s, poller type is %d, flags %d, ref %d",
				PROXY == test_config.monitored ? "by proxy" : "directly",
				test_config.flags & ZBX_HOST_UNREACHABLE ? "un" : "",
				(int)test_config.type, test_config.key, (int)test_config.poller_type,
				(int)test_config.flags, (int)test_config.test_number);

		DCitem_poller_type_update_test(&item, &host, test_config.flags);

		zbx_mock_assert_int_eq(buffer, test_config.result_poller_type, item.poller_type);
	}
}
