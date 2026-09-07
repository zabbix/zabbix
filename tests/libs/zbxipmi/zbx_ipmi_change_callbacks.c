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

/* zbx_control_change_cb() and zbx_entity_change_cb() are OpenIPMI callbacks; checks_ipmi.c is included    */
/* directly to reach them and the 'hostset' RETURN_IF_CB_DATA_INVALID(cb_data) needs.                      */
/*                                                                                                          */
/* zbx_control_change_cb(): IPMI_ADDED only calls zbx_allocate_ipmi_control() (a real dereference) when the */
/* control isn't already known, so the ADDED case here pre-registers the same pointer to take the "already */
/* exists" path instead. IPMI_DELETED/IPMI_CHANGED never dereference 'control' at all. The 'default' case  */
/* (an invalid op) is out of scope: it calls THIS_SHOULD_NEVER_HAPPEN, which this test harness wires to     */
/* _exit(1) (see zbxmocktest.c).                                                                            */
/*                                                                                                          */
/* zbx_entity_change_cb(): IPMI_ADDED unconditionally registers real OpenIPMI handlers on 'entity' - out of */
/* scope. For any other op, the only thing that could touch 'entity' for real is a debug-level log line     */
/* (ipmi_entity_get_name()), so the mock log level is lowered below LOG_LEVEL_DEBUG here to skip it -       */
/* zbx_mock_set_log_level() is the same mechanism the test harness itself uses to turn logging on.          */
/*                                                                                                          */
/* zbx_control_change_cb()'s "already exists" path calls zbx_get_ipmi_control() internally, which converts  */
/* the incoming (fabricated) 'control' pointer to an ID via ipmi_control_convert_to_id() - wrapped here to  */
/* echo the pointer back into the ID instead of dereferencing it, matching the pre-registered               */
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

static int	dummy_control, dummy_entity, dummy_domain;

static enum ipmi_update_e	op_by_name(const char *name)
{
	if (0 == strcmp(name, "added"))
		return IPMI_ADDED;
	if (0 == strcmp(name, "deleted"))
		return IPMI_DELETED;
	if (0 == strcmp(name, "changed"))
		return IPMI_CHANGED;

	fail_msg("unknown 'in.op' value: %s", name);
	return IPMI_CHANGED;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t	*h;
	const char	*cb = zbx_mock_get_parameter_string("in.cb");
	enum ipmi_update_e	op = op_by_name(zbx_mock_get_parameter_string("in.op"));

	ZBX_UNUSED(state);

	zbx_hashset_create(&hostset, 0, ZBX_DEFAULT_PTR_HASH_FUNC, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	h = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");

	if (0 == strcmp(cb, "control_change"))
	{
		zbx_ipmi_control_t	*c;
		int			exp_count = zbx_mock_get_parameter_int("out.control_count");

		h->control_count = 1;
		h->controls = (zbx_ipmi_control_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_control_t));
		c = &h->controls[0];
		memset(c, 0, sizeof(*c));
		c->control_id = ipmi_control_convert_to_id((ipmi_control_t *)&dummy_control);
		c->c_name = zbx_strdup(NULL, "test control");
		c->full_name = zbx_strdup(NULL, "test control");
		c->num_values = 1;
		c->val = (int *)zbx_malloc(NULL, sizeof(int));

		zbx_control_change_cb(op, (ipmi_entity_t *)&dummy_entity, (ipmi_control_t *)&dummy_control, h);

		zbx_mock_assert_int_eq("control_count", exp_count, h->control_count);

		if (0 < h->control_count)
		{
			zbx_free(h->controls[0].c_name);
			zbx_free(h->controls[0].full_name);
			zbx_free(h->controls[0].val);
		}
		zbx_free(h->controls);
	}
	else if (0 == strcmp(cb, "entity_change"))
	{
		/* skip the debug-only ipmi_entity_get_name() call - see the file header comment */
		zbx_mock_set_log_level(LOG_LEVEL_WARNING);

		zbx_entity_change_cb(op, (ipmi_domain_t *)&dummy_domain, (ipmi_entity_t *)&dummy_entity, h);

		zbx_mock_set_log_level(LOG_LEVEL_TRACE);

		/* neither branch covered here touches h->err */
		if (NULL != h->err)
			fail_msg("h->err should not have been touched, got: %s", h->err);
	}
	else
		fail_msg("unknown 'in.cb' value: %s", cb);

	zbx_hashset_remove(&hostset, &h);
	zbx_free(h->ip);
	zbx_free(h->username);
	zbx_free(h->password);
	zbx_free(h);
	zbx_hashset_destroy(&hostset);
}
