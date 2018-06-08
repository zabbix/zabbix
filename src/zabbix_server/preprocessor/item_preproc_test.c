/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "../../libs/zbxcunit/zbxcunit.h"

static void	cu_xml_error_func(void *ctx, const char *msg, ...)
{
	ZBX_UNUSED(ctx);
	ZBX_UNUSED(msg);
}

static xmlGenericErrorFunc cu_xml_error_func_ptr = cu_xml_error_func;

static int	cu_init_empty()
{
	initGenericErrorDefaultFunc(&cu_xml_error_func_ptr);
	return CUE_SUCCESS;
}

static int	cu_clean_empty()
{
	initGenericErrorDefaultFunc(NULL);
	return CUE_SUCCESS;
}

static void	cu_test_xpath_preproc(const char *xml, const char *xpath, const char *result, int success)
{
	zbx_variant_t	value;
	char		*errmsg = NULL;
	int		ret;
	char		description[MAX_STRING_LEN];

	zbx_snprintf(description, sizeof(description), "XML:%s, xpath:%s", xml, xpath);
	zbx_variant_set_str(&value, zbx_strdup(NULL, xml));

	ret = item_preproc_xpath(&value, xpath, &errmsg);
	ZBX_CU_ASSERT_INT_EQ_FATAL(description, ret, success);

	if (FAIL == ret)
	{
		ZBX_CU_ASSERT_PTR_NOT_NULL_FATAL(description, errmsg);
		zbx_variant_clear(&value);
		zbx_free(errmsg);
		return;
	}

	ZBX_CU_ASSERT_STRING_EQ_FATAL(description, value.data.str, result);
	zbx_variant_clear(&value);
}

static void	test_item_preproc_xpath()
{
	struct zbx_cu_xml_input_t
	{
		const char	*xml;
		const char	*xpath;
		const char	*result;
		int		success;	/* expected return value */
	};

	size_t				i;
	struct zbx_cu_xml_input_t	data[] = {
			{"", "", "", FAIL},
			{"<a>", "", "", FAIL},
			{"<a/>", "", "", FAIL},
			{"<a/>", "/a[\"]", "", FAIL},
			{"<a/>", "1 div 0", "", FAIL},
			{"<a/>", "-a", "", FAIL},
			{"<a/>", "/b", "", SUCCEED},
			{"<a/>", "3 div 2", "1.5", SUCCEED},
			{"<a/>", "/a", "<a/>", SUCCEED},
			{"<a>1</a>", "/a/text()", "1", SUCCEED},
			{"<a>1</a>", "string(/a)", "1", SUCCEED},
			{"<a b=\"10\">1</a>", "string(/a/@b)", "10", SUCCEED},
			{"<a><b x=\"1\"/><c x=\"2\"/><d x=\"1\"/></a>", "//*[@x=\"1\"]", "<b x=\"1\"/><d x=\"1\"/>",
					SUCCEED},
			};

	for (i = 0; ARRSIZE(data) > i; i++)
		cu_test_xpath_preproc(data[i].xml, data[i].xpath, data[i].result, data[i].success);
}

int	ZBX_CU_DECLARE(item_preproc)
{
	CU_pSuite	suite = NULL;

	/* test suite: zbx_user_macro_parse() */
	if (NULL == (suite = CU_add_suite("test_item_preproc_xpath", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, test_item_preproc_xpath);

	return CUE_SUCCESS;
}
