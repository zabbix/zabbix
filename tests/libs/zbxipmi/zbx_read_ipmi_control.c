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

/* zbx_read_ipmi_control() and zbx_set_ipmi_control() are static, so the source file is included directly   */
/* to reach them. See zbx_read_ipmi_sensor.c for the full rationale - this is the same regression, on the   */
/* zbx_ipmi_control_t side: only zbx_ipmi_control_t.control_id may be used to issue a new read/write from   */
/* outside a callback, via ipmi_control_id_get_val()/ipmi_control_id_set_val() (which internally            */
/* revalidate the id). is_readable/is_settable are a separate, static property captured once in             */
/* zbx_allocate_ipmi_control() - simulated here as already-cached (both true), so the test actually         */
/* reaches the id-based call being verified, rather than short-circuiting on the capability check.          */
/*                                                                                                          */
/* An IPMI_CONTROL_ID_INVALID control_id stands in for a control OpenIPMI has since destroyed. Real         */
/* OpenIPMI (not mocked) is exercised throughout. 'out.err_contains' pins down the exact wording each       */
/* branch produces, so a case can only pass if the matching branch actually ran - not just any branch       */
/* that happens to also set h->ret to NOTSUPPORTED.                                                         */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t		h;
	zbx_ipmi_control_t	c;
	int			val = 0;
	const char		*op = zbx_mock_get_parameter_string("in.op");
	const char		*err_contains = zbx_mock_get_parameter_string("out.err_contains");

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	memset(&h, 0, sizeof(h));
	h.ip = "127.0.0.1";
	h.port = 623;

	memset(&c, 0, sizeof(c));
	c.c_name = "test control";
	c.num_values = 1;
	c.val = &val;
	/* as if cached at allocation time - see the file header comment */
	c.is_readable = zbx_mock_get_parameter_int("in.is_readable");
	c.is_settable = zbx_mock_get_parameter_int("in.is_settable");
	/* stands in for a control OpenIPMI has already destroyed - see the file header comment */
	c.control_id = (ipmi_control_id_t)IPMI_CONTROL_ID_INVALID;

	if (0 == strcmp(op, "read"))
		zbx_read_ipmi_control(&h, &c);
	else if (0 == strcmp(op, "set"))
		zbx_set_ipmi_control(&h, &c, 1);
	else
		fail_msg("unknown 'in.op' value: %s", op);

	zbx_mock_assert_int_eq("h.ret", NOTSUPPORTED, h.ret);

	if (NULL == h.err || NULL == strstr(h.err, err_contains))
	{
		fail_msg("h.err should mention '%s' (proving the matching branch actually ran), got: %s",
				err_contains, NULL == h.err ? "NULL" : h.err);
	}

	zbx_free(h.err);
	zbx_hashset_destroy(&hostset);
}
