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

/* zbx_control_change_cb(), zbx_sensor_change_cb(), zbx_entity_change_cb() and zbx_connection_change_cb()  */
/* are OpenIPMI callbacks; checks_ipmi.c is included directly to reach them and the 'hostset'              */
/* RETURN_IF_CB_DATA_INVALID(cb_data) needs.                                                                */
/*                                                                                                          */
/* zbx_control_change_cb()/zbx_sensor_change_cb(): IPMI_ADDED only calls zbx_allocate_ipmi_control()/       */
/* zbx_allocate_ipmi_sensor() (a real dereference) when the control/sensor isn't already known, so the      */
/* ADDED case here pre-registers the same pointer to take the "already exists" path instead. IPMI_DELETED/  */
/* IPMI_CHANGED never dereference 'control'/'sensor' at all. The 'default' case (an invalid op) is out of   */
/* scope: it calls THIS_SHOULD_NEVER_HAPPEN, which this test harness wires to _exit(1) (see                */
/* zbxmocktest.c). zbx_sensor_change_cb() additionally gates everything on ipmi_sensor_get_is_readable()    */
/* first, unconditionally - wrapped below via a controllable flag so both the readable and non-readable     */
/* paths can be exercised without a real sensor.                                                            */
/*                                                                                                          */
/* zbx_entity_change_cb(): IPMI_ADDED unconditionally registers real OpenIPMI handlers on 'entity' - out of */
/* scope. For any other op, the only thing that could touch 'entity' for real is a debug-level log line     */
/* (ipmi_entity_get_name()), so the mock log level is lowered below LOG_LEVEL_DEBUG here to skip it -       */
/* zbx_mock_set_log_level() is the same mechanism the test harness itself uses to turn logging on.          */
/*                                                                                                          */
/* zbx_connection_change_cb() never dereferences 'domain' itself - it only ever passes it opaquely to       */
/* ipmi_domain_close() (err branch) or ipmi_domain_add_entity_update_handler() (success branch), both real  */
/* OpenIPMI calls that would dereference it for real, so both are wrapped below with call counters instead. */
/*                                                                                                          */
/* zbx_control_change_cb()'s/zbx_sensor_change_cb()'s "already exists" path calls zbx_get_ipmi_control()/   */
/* zbx_get_ipmi_sensor() internally, which convert the incoming (fabricated) pointer to an ID via           */
/* ipmi_control_convert_to_id()/ipmi_sensor_convert_to_id() - wrapped here to echo the pointer back into    */
/* the ID instead of dereferencing it, matching the pre-registered control_id/sensor_id below.              */
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

ipmi_sensor_id_t	__wrap_ipmi_sensor_convert_to_id(ipmi_sensor_t *raw_sensor)
{
	ipmi_sensor_id_t	id;

	memset(&id, 0, sizeof(id));
	id.mcid.domain_id.domain = (ipmi_domain_t *)raw_sensor;

	return id;
}

static int	sensor_readable = 1;

int	__wrap_ipmi_sensor_get_is_readable(ipmi_sensor_t *raw_sensor)
{
	ZBX_UNUSED(raw_sensor);

	return sensor_readable;
}

static int	domain_close_calls, domain_close_ret;

int	__wrap_ipmi_domain_close(ipmi_domain_t *raw_domain, ipmi_domain_close_done_cb close_done, void *cb_data)
{
	ZBX_UNUSED(raw_domain);

	domain_close_calls++;

	/* only a successful ipmi_domain_close() completes asynchronously via close_done() in real OpenIPMI */
	if (0 == domain_close_ret)
		close_done(cb_data);

	return domain_close_ret;
}

static int	entity_handler_calls, entity_handler_ret;

int	__wrap_ipmi_domain_add_entity_update_handler(ipmi_domain_t *raw_domain, ipmi_domain_entity_cb handler,
		void *cb_data)
{
	ZBX_UNUSED(raw_domain);
	ZBX_UNUSED(handler);
	ZBX_UNUSED(cb_data);

	entity_handler_calls++;

	return entity_handler_ret;
}

static int	dummy_control, dummy_sensor, dummy_entity, dummy_domain;

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
	else if (0 == strcmp(cb, "sensor_change"))
	{
		zbx_ipmi_sensor_t	*s;
		int			exp_count = zbx_mock_get_parameter_int("out.sensor_count");

		sensor_readable = 1;

		h->sensor_count = 1;
		h->sensors = (zbx_ipmi_sensor_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_sensor_t));
		s = &h->sensors[0];
		memset(s, 0, sizeof(*s));
		s->sensor_id = ipmi_sensor_convert_to_id((ipmi_sensor_t *)&dummy_sensor);
		s->full_name = zbx_strdup(NULL, "test sensor");

		zbx_sensor_change_cb(op, (ipmi_entity_t *)&dummy_entity, (ipmi_sensor_t *)&dummy_sensor, h);

		zbx_mock_assert_int_eq("sensor_count", exp_count, h->sensor_count);

		if (0 < h->sensor_count)
			zbx_free(h->sensors[0].full_name);
		zbx_free(h->sensors);
	}
	else if (0 == strcmp(cb, "sensor_change_not_readable"))
	{
		sensor_readable = 0;

		h->sensor_count = 0;
		h->sensors = NULL;

		zbx_sensor_change_cb(op, (ipmi_entity_t *)&dummy_entity, (ipmi_sensor_t *)&dummy_sensor, h);

		/* the readability gate must reject the sensor before zbx_allocate_ipmi_sensor() (a real */
		/* dereference) is ever reached */
		zbx_mock_assert_int_eq("sensor_count stays 0 when sensor is not readable", 0, h->sensor_count);
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
	else if (0 == strcmp(cb, "connection_change"))
	{
		int	err = zbx_mock_get_parameter_int("in.err");

		domain_close_calls = 0;
		domain_close_ret = zbx_mock_get_parameter_int("in.domain_close_ret");
		entity_handler_calls = 0;
		entity_handler_ret = zbx_mock_get_parameter_int("in.entity_handler_ret");

		zbx_connection_change_cb((ipmi_domain_t *)&dummy_domain, err, 0, 0, 1, h);

		zbx_mock_assert_int_eq("ipmi_domain_close() call count",
				zbx_mock_get_parameter_int("out.domain_close_calls"), domain_close_calls);
		zbx_mock_assert_int_eq("ipmi_domain_add_entity_update_handler() call count",
				zbx_mock_get_parameter_int("out.entity_handler_calls"), entity_handler_calls);

		if (0 != err)
		{
			zbx_mock_assert_int_eq("h->ret", NETWORK_ERROR, h->ret);

			if (NULL == h->err)
				fail_msg("h->err should be set when the connection reports an error");
		}
	}
	else
		fail_msg("unknown 'in.cb' value: %s", cb);

	zbx_hashset_remove(&hostset, &h);
	zbx_free(h->err);
	zbx_free(h->ip);
	zbx_free(h->username);
	zbx_free(h->password);
	zbx_free(h);
	zbx_hashset_destroy(&hostset);
}
