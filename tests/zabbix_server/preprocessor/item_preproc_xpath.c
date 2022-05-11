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
#include <stdio.h>
#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"
#include "common.h"
#include "zbxvariant.h"

#include "item_preproc_test.h"
#include "zbxembed.h"

zbx_es_t	es_engine;

void	zbx_mock_test_entry(void **state)
{
	zbx_variant_t	value;
	const char	*xml;
	const char	*xpath, *exp_xml;
	char		*errmsg = NULL;
	int		act_ret, exp_ret;

	ZBX_UNUSED(state);

	xml = zbx_mock_get_parameter_string("in.xml");
	xpath = zbx_mock_get_parameter_string("in.xpath");
	exp_xml = zbx_mock_get_parameter_string("out.result");
	zbx_variant_set_str(&value, zbx_strdup(NULL, xml));

	act_ret = zbx_item_preproc_xpath(&value, xpath, &errmsg);

	exp_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_int_eq("return value", exp_ret, act_ret);

	if (FAIL == act_ret)
	{
		zbx_mock_assert_ptr_ne("error message", NULL, errmsg);
		zbx_free(errmsg);
	}
	else
		zbx_mock_assert_str_eq("result", exp_xml, value.data.str);

	zbx_variant_clear(&value);
}
