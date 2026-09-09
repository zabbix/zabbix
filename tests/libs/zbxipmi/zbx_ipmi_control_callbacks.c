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

/* zbx_got_control_reading_cb() and zbx_got_control_setting_cb() are OpenIPMI callbacks; checks_ipmi.c is */
/* included directly to reach them, alongside the 'hostset' RETURN_IF_CB_DATA_INVALID(cb_data) needs.     */
/*                                                                                                          */
/* Only branches that never touch the 'control' argument for anything but pointer-identity matching are    */
/* covered here, so a fabricated address stands in for a real ipmi_control_t*. Deliberately out of scope:  */
/* - the "control not found" and "num_values == 0" branches in both callbacks call THIS_SHOULD_NEVER_HAPPEN, */
/*   which this test harness wires to _exit(1) (see zbxmocktest.c) - triggering it would kill the test      */
/*   process rather than fail one case, and bypasses atexit() so it wouldn't even earn coverage credit.     */
/* - zbx_got_control_reading_cb()'s "values present" success path dereferences 'control' for real via       */
/*   ipmi_control_get_entity(), unconditionally - needs a real control object or a wrapped getter, neither  */
/*   of which this file uses.                                                                                */
/*                                                                                                          */
/* Both callbacks call zbx_get_ipmi_control_within_cb_only() internally, which converts the incoming        */
/* (fabricated) 'control' pointer to an ID via ipmi_control_convert_to_id() - wrapped here to echo the      */
/* pointer back into the ID instead of dereferencing it, matching the pre-registered                        */
/* zbx_ipmi_control_t.control_id below.                                                                     */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

ipmi_control_id_t	__wrap_ipmi_control_convert_to_id(ipmi_control_t *raw_control)
{
	ipmi_control_id_t	id;

	memset(&id, 0, sizeof(id));
	id.mcid.domain_id.domain = (ipmi_domain_t *)raw_control;

	return id;
}

static int	dummy_control;

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t		*h;
	zbx_ipmi_control_t	*c;
	const char		*cb = zbx_mock_get_parameter_string("in.cb");
	const char		*scenario = zbx_mock_get_parameter_string("in.scenario");
	int			val[1] = {0};
	int			err = (0 == strcmp(scenario, "err")) ? 5 : 0;

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	h = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");

	h->control_count = 1;
	h->controls = (zbx_ipmi_control_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_control_t));
	c = &h->controls[0];
	memset(c, 0, sizeof(*c));
	c->control_id = ipmi_control_convert_to_id((ipmi_control_t *)&dummy_control);
	c->c_name = zbx_strdup(NULL, "test control");
	c->num_values = 1;
	c->val = (int *)zbx_malloc(NULL, sizeof(int));
	c->val[0] = 0;

	h->done = 0;
	h->ret = SUCCEED;

	if (0 == strcmp(cb, "reading"))
		zbx_got_control_reading_cb((ipmi_control_t *)&dummy_control, err, val, h);
	else if (0 == strcmp(cb, "setting"))
		zbx_got_control_setting_cb((ipmi_control_t *)&dummy_control, err, h);
	else
		fail_msg("unknown 'in.cb' value: %s", cb);

	zbx_mock_assert_int_eq("h->done", 1, h->done);
	zbx_mock_assert_int_eq("h->ret", zbx_mock_get_parameter_int("out.ret"), h->ret);

	if (SUCCEED != h->ret && (NULL == h->err || '\0' == *h->err))
		fail_msg("h->err should be set when h->ret indicates failure");

	zbx_free(h->err);
	zbx_free(c->c_name);
	zbx_free(c->val);
	zbx_free(h->controls);
	zbx_hashset_remove(&hostset, &h);
	zbx_free(h->ip);
	zbx_free(h->username);
	zbx_free(h->password);
	zbx_free(h);
	zbx_hashset_destroy(&hostset);
}
