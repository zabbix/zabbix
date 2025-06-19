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
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxalgo.h"
#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	const char		*func_type = zbx_mock_get_parameter_string("in.func_type");

	ZBX_UNUSED(state);

	if (SUCCEED == zbx_strcmp_natural(func_type, "udiv128_64"))
	{
		zbx_uint128_t	result, dividend;
		zbx_uint64_t	value = zbx_mock_get_parameter_uint64("in.divider");
		zbx_uint64_t	lo = zbx_mock_get_parameter_uint64("out.lo");
		zbx_uint64_t	hi = zbx_mock_get_parameter_uint64("out.hi");

		dividend.lo = zbx_mock_get_parameter_uint64("in.lo");
		dividend.hi = zbx_mock_get_parameter_uint64("in.hi");

		zbx_udiv128_64(&result, &dividend, value);

		zbx_mock_assert_uint64_eq("return value", lo, result.lo);
		zbx_mock_assert_uint64_eq("return value", hi, result.hi);
	}

	if (SUCCEED == zbx_strcmp_natural(func_type, "uinc128_64"))
	{
		zbx_uint128_t	base;
		zbx_uint64_t	value = zbx_mock_get_parameter_uint64("in.value");
		zbx_uint64_t	exp = zbx_mock_get_parameter_uint64("out.exp_hi");

		base.lo = zbx_mock_get_parameter_uint64("in.lo");
		base.hi = zbx_mock_get_parameter_uint64("in.hi");

		zbx_uinc128_64(&base, value);

		zbx_mock_assert_uint64_eq("return value", exp, base.hi);
	}

	if (SUCCEED == zbx_strcmp_natural(func_type, "uinc128_128"))
	{
		zbx_uint128_t	base, value;
		base.lo = zbx_mock_get_parameter_uint64("in.base_lo");
		base.hi = zbx_mock_get_parameter_uint64("in.base_hi");
		value.lo = zbx_mock_get_parameter_uint64("in.value_lo");
		value.hi = zbx_mock_get_parameter_uint64("in.value_hi");

		zbx_uint64_t	lo = zbx_mock_get_parameter_uint64("out.lo");
		zbx_uint64_t	hi = zbx_mock_get_parameter_uint64("out.hi");

		zbx_uinc128_128(&base, &value);

		zbx_mock_assert_uint64_eq("lo return value", lo, base.lo);
		zbx_mock_assert_uint64_eq("hi return value", hi, base.hi);
	}

	if (SUCCEED == zbx_strcmp_natural(func_type, "umul64_64"))
	{
		zbx_uint128_t	result;
		zbx_uint64_t	value = zbx_mock_get_parameter_uint64("in.value");
		zbx_uint64_t	factor = zbx_mock_get_parameter_uint64("in.factor");
		zbx_uint64_t	lo = zbx_mock_get_parameter_uint64("out.lo");
		zbx_uint64_t	hi = zbx_mock_get_parameter_uint64("out.hi");

		zbx_umul64_64(&result, value, factor);

		zbx_mock_assert_uint64_eq("lo return value", lo, result.lo);
		zbx_mock_assert_uint64_eq("hi return value", hi, result.hi);
	}
}
