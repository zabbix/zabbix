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

#include "../zbxcunit/zbxcunit.h"

static int	cu_init_empty()
{
	return CUE_SUCCESS;
}

static int	cu_clean_empty()
{
	return CUE_SUCCESS;
}

static void	cu_test_is_item_processed_by_server(unsigned char type, const char *key, int success)
{
	int	ret;
	char	description[ZBX_KIBIBYTE * 64];

	zbx_snprintf(description, sizeof(description), "type %u key '%s'", type, key);

	ret = is_item_processed_by_server(type, key);
	ZBX_CU_ASSERT_INT_EQ_FATAL(description, ret, success);
}

static void	test_is_item_processed_by_server(void)
{
	struct is_item_processed_by_server_test_data_t
	{
		unsigned char	type;
		const char	*key;
		int		success;	/* expected return value */
	};

	size_t	i;
	struct is_item_processed_by_server_test_data_t	test_cases[] = {
			{ ITEM_TYPE_ZABBIX, "key1[]", FAIL },
			{ ITEM_TYPE_ZABBIX, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_SNMPv1, "key1[]", FAIL },
			{ ITEM_TYPE_SNMPv1, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_TRAPPER, "key1[]", FAIL },
			{ ITEM_TYPE_TRAPPER, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_SIMPLE, "key1[]", FAIL },
			{ ITEM_TYPE_SIMPLE, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_SNMPv2c, "key1[]", FAIL },
			{ ITEM_TYPE_SNMPv2c, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_INTERNAL, "key[]1", FAIL },
			{ ITEM_TYPE_INTERNAL, "zabbix[host,,items]", SUCCEED },
			{ ITEM_TYPE_INTERNAL, "zabbix1[host,,items]", FAIL },
			{ ITEM_TYPE_INTERNAL, "zabbi1[host,,items]", FAIL },
			{ ITEM_TYPE_INTERNAL, "zabbix[host,discovery,items]", FAIL },
			{ ITEM_TYPE_INTERNAL, "zabbix[host,,items_unsupported]", SUCCEED },
			{ ITEM_TYPE_INTERNAL, "zabbix[host,discovery,items_unsupported]", FAIL },
			{ ITEM_TYPE_INTERNAL, "zabbix[host,discovery,interfaces]", SUCCEED },
			{ ITEM_TYPE_INTERNAL, "zabbix[host,,interfaces]", FAIL },
			{ ITEM_TYPE_INTERNAL, "zabbix[host,,maintenance]", SUCCEED },
			{ ITEM_TYPE_INTERNAL, "zabbix[host,discovery,,maintenance]", FAIL },
			{ ITEM_TYPE_INTERNAL, "zabbix[proxy,proxy1,lastaccess]", SUCCEED },
			{ ITEM_TYPE_INTERNAL, "zabbix[proxy,,lastaccess]", SUCCEED },
			{ ITEM_TYPE_INTERNAL, "zabbix1[proxy,,lastaccess]", FAIL },
			{ ITEM_TYPE_INTERNAL, "zabbix[proxy,,items]", FAIL },
			{ ITEM_TYPE_SNMPv3, "key1[]", FAIL },
			{ ITEM_TYPE_SNMPv3, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_ZABBIX_ACTIVE, "key1[]", FAIL },
			{ ITEM_TYPE_ZABBIX_ACTIVE, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_AGGREGATE, "key1[]", SUCCEED },
			{ ITEM_TYPE_AGGREGATE, "zabbix[host,,items]", SUCCEED },
			{ ITEM_TYPE_HTTPTEST, "key1[]", FAIL },
			{ ITEM_TYPE_HTTPTEST, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_EXTERNAL, "key1[]", FAIL },
			{ ITEM_TYPE_EXTERNAL, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_DB_MONITOR, "key1[]", FAIL },
			{ ITEM_TYPE_DB_MONITOR, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_IPMI, "key1[]", FAIL },
			{ ITEM_TYPE_IPMI, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_SSH, "key1[]", FAIL },
			{ ITEM_TYPE_SSH, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_TELNET, "key1[]", FAIL },
			{ ITEM_TYPE_TELNET, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_CALCULATED, "key1[]", SUCCEED },
			{ ITEM_TYPE_CALCULATED, "zabbix[host,,items]", SUCCEED },
			{ ITEM_TYPE_JMX, "key1[]", FAIL },
			{ ITEM_TYPE_JMX, "zabbix[host,,items]", FAIL },
			{ ITEM_TYPE_SNMPTRAP, "key1[]", FAIL },
			{ ITEM_TYPE_SNMPTRAP, "zabbix[host,,items]", FAIL },
			{ 255, "key1[]", FAIL },		/* non-existing item type */
			{ 255, "zabbix[host,,items]", FAIL }
			};

	ZBX_CU_LEAK_CHECK_START();

	for (i = 0; ARRSIZE(test_cases) > i; i++)
		cu_test_is_item_processed_by_server(test_cases[i].type, test_cases[i].key, test_cases[i].success);

	ZBX_CU_LEAK_CHECK_END();
}

int	ZBX_CU_DECLARE(dbconfig_test)
{
	CU_pSuite	suite = NULL;

	/* test suite: is_item_processed_by_server() */
	if (NULL == (suite = CU_add_suite("is_item_processed_by_server", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, test_is_item_processed_by_server);

	return CUE_SUCCESS;
}
