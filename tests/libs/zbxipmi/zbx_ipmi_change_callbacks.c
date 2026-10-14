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

/* zbx_sensor_change_cb() and zbx_control_change_cb() are static OpenIPMI callbacks, so checks_ipmi.c is      */
/* included directly to reach them and the 'hosts' list their cb_data is resolved against.                   */
/*                                                                                                            */
/* When OpenIPMI replaces a sensor or control (e.g. its SDR changed while it was being read), the new object   */
/* gets the same OpenIPMI ID as the old one, and IPMI_ADDED for the new object may be reported before          */
/* IPMI_DELETED for the old one. The fabricated 'old' and 'new' objects below are therefore converted to the   */
/* same ID by the wrapped ipmi_sensor_convert_to_id()/ipmi_control_convert_to_id(). The objects are never      */
/* dereferenced: the entry for the old object is registered directly, so IPMI_ADDED for an already known ID    */
/* does not reach zbx_allocate_ipmi_sensor()/zbx_allocate_ipmi_control(). The 'other' object has its own ID.    */
/*                                                                                                            */
/* Events may also be sent with the cb_data of another host, of a host which does not exist (e.g. freed before */
/* a late callback) or with NULL cb_data - such callbacks must not touch the tested host.                     */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

ipmi_sensor_id_t	__wrap_ipmi_sensor_convert_to_id(ipmi_sensor_t *sensor);
ipmi_control_id_t	__wrap_ipmi_control_convert_to_id(ipmi_control_t *control);
int			__wrap_ipmi_sensor_get_is_readable(ipmi_sensor_t *sensor);

static int	old_object, new_object, other_object, entity, object_id, other_object_id, sensor_readable = 1;

/* the old and the new object share one ID, the other object has a different one */
static ipmi_domain_t	*object_id_by_object(const void *object)
{
	return (ipmi_domain_t *)(object == &other_object ? &other_object_id : &object_id);
}

ipmi_sensor_id_t	__wrap_ipmi_sensor_convert_to_id(ipmi_sensor_t *sensor)
{
	ipmi_sensor_id_t	id;

	memset(&id, 0, sizeof(id));
	id.mcid.domain_id.domain = object_id_by_object(sensor);

	return id;
}

ipmi_control_id_t	__wrap_ipmi_control_convert_to_id(ipmi_control_t *control)
{
	ipmi_control_id_t	id;

	memset(&id, 0, sizeof(id));
	id.mcid.domain_id.domain = object_id_by_object(control);

	return id;
}

int	__wrap_ipmi_sensor_get_is_readable(ipmi_sensor_t *sensor)
{
	ZBX_UNUSED(sensor);

	return sensor_readable;
}

static enum ipmi_update_e	op_by_name(const char *name)
{
	if (0 == strcmp(name, "added"))
		return IPMI_ADDED;
	if (0 == strcmp(name, "deleted"))
		return IPMI_DELETED;
	if (0 == strcmp(name, "changed"))
		return IPMI_CHANGED;

	fail_msg("unknown operation: %s", name);

	return IPMI_CHANGED;
}

static void	*object_by_name(const char *name)
{
	if (0 == strcmp(name, "old"))
		return &old_object;
	if (0 == strcmp(name, "new"))
		return &new_object;
	if (0 == strcmp(name, "other"))
		return &other_object;

	fail_msg("unknown object: %s", name);

	return NULL;
}

static void	register_old_sensor(zbx_ipmi_host_t *h)
{
	zbx_ipmi_sensor_t	*s;

	h->sensor_count = 1;
	h->sensors = (zbx_ipmi_sensor_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_sensor_t));
	s = &h->sensors[0];
	memset(s, 0, sizeof(*s));
	s->sensor_id = ipmi_sensor_convert_to_id((ipmi_sensor_t *)&old_object);
	s->full_name = zbx_strdup(NULL, "(7.1).Temp");
	s->type_str = zbx_strdup(NULL, "temperature");
	s->reading_type_str = zbx_strdup(NULL, "threshold");
}

static void	register_old_control(zbx_ipmi_host_t *h)
{
	zbx_ipmi_control_t	*c;

	h->control_count = 1;
	h->controls = (zbx_ipmi_control_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_control_t));
	c = &h->controls[0];
	memset(c, 0, sizeof(*c));
	c->control_id = ipmi_control_convert_to_id((ipmi_control_t *)&old_object);
	c->c_name = zbx_strdup(NULL, "power");
	c->full_name = zbx_strdup(NULL, "(23.1).power");
	c->num_values = 1;
	c->val = (int *)zbx_malloc(NULL, sizeof(int));
}

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t		*h, *other_host;
	zbx_mock_handle_t	hevents, hevent;
	zbx_mock_error_t	err;
	const char		*kind = zbx_mock_get_parameter_string("in.kind");
	int			is_sensor;

	ZBX_UNUSED(state);

	if (0 == strcmp(kind, "sensor"))
		is_sensor = 1;
	else if (0 == strcmp(kind, "control"))
		is_sensor = 0;
	else
		fail_msg("unknown kind: %s", kind);

	h = zbx_allocate_ipmi_host("127.0.0.1", 623, 2, 4, "user", "pass");
	other_host = zbx_allocate_ipmi_host("127.0.0.2", 623, 2, 4, "user", "pass");

	if (1 == is_sensor)
		register_old_sensor(h);
	else
		register_old_control(h);

	hevents = zbx_mock_get_parameter_handle("in.events");

	while (ZBX_MOCK_SUCCESS == (err = zbx_mock_vector_element(hevents, &hevent)))
	{
		enum ipmi_update_e	op = op_by_name(zbx_mock_get_object_member_string(hevent, "op"));
		void			*object = object_by_name(zbx_mock_get_object_member_string(hevent, "object")),
					*cb_data = ZBX_IPMI_HOST_CB_DATA(h);
		zbx_mock_handle_t	hparam;
		const char		*value;

		sensor_readable = 1;

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hevent, "readable", &hparam) &&
				ZBX_MOCK_SUCCESS == zbx_mock_string(hparam, &value))
		{
			sensor_readable = atoi(value);
		}

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hevent, "host", &hparam) &&
				ZBX_MOCK_SUCCESS == zbx_mock_string(hparam, &value))
		{
			if (0 == strcmp(value, "other"))
				cb_data = ZBX_IPMI_HOST_CB_DATA(other_host);
			else if (0 == strcmp(value, "nonexistent"))
				cb_data = (void *)((uintptr_t)other_host->domain_nr + 1000);
			else if (0 == strcmp(value, "null"))
				cb_data = NULL;
			else if (0 != strcmp(value, "tested"))
				fail_msg("unknown host: %s", value);
		}

		if (1 == is_sensor)
			zbx_sensor_change_cb(op, (ipmi_entity_t *)&entity, (ipmi_sensor_t *)object, cb_data);
		else
			zbx_control_change_cb(op, (ipmi_entity_t *)&entity, (ipmi_control_t *)object, cb_data);
	}

	if (ZBX_MOCK_END_OF_VECTOR != err)
		fail_msg("cannot read events: %s", zbx_mock_error_string(err));

	if (1 == is_sensor)
		zbx_mock_assert_int_eq("sensor count", zbx_mock_get_parameter_int("out.count"), h->sensor_count);
	else
		zbx_mock_assert_int_eq("control count", zbx_mock_get_parameter_int("out.count"), h->control_count);

	hosts = NULL;
	zbx_free_ipmi_host(other_host);
	zbx_free_ipmi_host(h);
}
