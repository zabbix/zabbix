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

/* Same rationale as zbx_ipmi_sensor_lookup.c, for the zbx_ipmi_control_t side:                             */
/* zbx_get_ipmi_control_within_cb_only(), zbx_get_ipmi_control_by_name(),                                   */
/* zbx_get_ipmi_control_by_full_name() and zbx_delete_ipmi_control_within_cb_only() only ever scan          */
/* h->controls[] by ID identity (ipmi_cmp_control_id(), a plain value comparison) or string comparison.     */
/* ipmi_control_convert_to_id() is wrapped to echo the fabricated pointer back into the ID, preserving      */
/* pointer-identity semantics without dereferencing it - see zbx_ipmi_sensor_lookup.c.                      */
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

static int	dummy_a, dummy_b, dummy_c, dummy_d;

static void	add_control(zbx_ipmi_host_t *h, ipmi_control_t *raw_control, const char *c_name, const char *full_name)
{
	zbx_ipmi_control_t	*c;

	h->control_count++;
	h->controls = (zbx_ipmi_control_t *)zbx_realloc(h->controls,
			(size_t)h->control_count * sizeof(zbx_ipmi_control_t));

	c = &h->controls[h->control_count - 1];
	memset(c, 0, sizeof(*c));
	c->control_id = ipmi_control_convert_to_id(raw_control);
	c->c_name = zbx_strdup(NULL, c_name);
	c->full_name = zbx_strdup(NULL, full_name);
	c->num_values = 1;
	c->val = (int *)zbx_malloc(NULL, sizeof(int));
	c->val[0] = 0;
}

static void	free_controls(zbx_ipmi_host_t *h)
{
	for (int i = 0; i < h->control_count; i++)
	{
		zbx_free(h->controls[i].c_name);
		zbx_free(h->controls[i].full_name);
		zbx_free(h->controls[i].val);
	}
	zbx_free(h->controls);
}

static ipmi_control_t	*control_by_name(const char *name)
{
	if (0 == strcmp(name, "a"))
		return (ipmi_control_t *)&dummy_a;
	if (0 == strcmp(name, "b"))
		return (ipmi_control_t *)&dummy_b;
	if (0 == strcmp(name, "c"))	/* never registered in the host - simulates "not found" */
		return (ipmi_control_t *)&dummy_c;

	fail_msg("unknown control name: %s", name);
	return NULL;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t		h;
	const char		*func = zbx_mock_get_parameter_string("in.func");
	zbx_ipmi_control_t	*c = NULL;

	ZBX_UNUSED(state);

	memset(&h, 0, sizeof(h));
	h.ip = "127.0.0.1";
	h.port = 623;
	add_control(&h, (ipmi_control_t *)&dummy_a, "ctrl-a", "Control A");
	add_control(&h, (ipmi_control_t *)&dummy_b, "ctrl-b", "Control B");

	if (0 == strcmp(func, "by_pointer"))
	{
		c = zbx_get_ipmi_control_within_cb_only(&h,
				control_by_name(zbx_mock_get_parameter_string("in.lookup")));
	}
	else if (0 == strcmp(func, "by_name"))
	{
		c = zbx_get_ipmi_control_by_name(&h, zbx_mock_get_parameter_string("in.lookup"));
	}
	else if (0 == strcmp(func, "by_full_name"))
	{
		c = zbx_get_ipmi_control_by_full_name(&h, zbx_mock_get_parameter_string("in.lookup"));
	}
	else if (0 == strcmp(func, "delete"))
	{
		int	exp_count = zbx_mock_get_parameter_int("out.control_count");

		/* a third control lets 'delete the first of three' exercise a memmove() of more than one */
		/* element, unlike the two-control cases below */
		if (0 == strcmp(zbx_mock_get_parameter_string("in.three_controls"), "yes"))
			add_control(&h, (ipmi_control_t *)&dummy_d, "ctrl-d", "Control D");

		zbx_delete_ipmi_control_within_cb_only(&h, control_by_name(zbx_mock_get_parameter_string("in.lookup")));
		zbx_mock_assert_int_eq("control_count after delete", exp_count, h.control_count);

		if (0 < h.control_count)
		{
			zbx_mock_assert_str_eq("surviving control full_name",
					zbx_mock_get_parameter_string("out.surviving_full_name"),
					h.controls[0].full_name);
		}

		free_controls(&h);
		return;
	}
	else
		fail_msg("unknown 'in.func' value: %s", func);

	if (0 == strcmp(zbx_mock_get_parameter_string("out.found"), "yes"))
	{
		if (NULL == c)
			fail_msg("expected a matching control to be found, got NULL");
		zbx_mock_assert_str_eq("found control full_name", zbx_mock_get_parameter_string("out.full_name"),
				c->full_name);
	}
	else
		zbx_mock_assert_ptr_eq("no control should be found", NULL, c);

	free_controls(&h);
}
