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

/* zbx_get_ipmi_sensor(), zbx_get_ipmi_sensor_by_id(), zbx_get_ipmi_sensor_by_full_name() and               */
/* zbx_delete_ipmi_sensor() only ever scan h->sensors[] by ID identity (via ipmi_cmp_sensor_id(), a plain   */
/* field comparison on a value-type struct - never a dereference) or string comparison, so a fabricated,    */
/* never-dereferenced address (the address of a local int) stands in for a real ipmi_sensor_t* throughout   */
/* this file. ipmi_sensor_convert_to_id() itself DOES dereference its argument in the real OpenIPMI         */
/* implementation, so it is wrapped below to just echo the fabricated pointer back into the ID, preserving  */
/* pointer-identity semantics for this fixture without ever touching its memory.                            */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

ipmi_sensor_id_t	__wrap_ipmi_sensor_convert_to_id(ipmi_sensor_t *raw_sensor)
{
	ipmi_sensor_id_t	id;

	memset(&id, 0, sizeof(id));
	id.mcid.domain_id.domain = (ipmi_domain_t *)raw_sensor;

	return id;
}

static int	dummy_a, dummy_b, dummy_c;

static void	add_sensor(zbx_ipmi_host_t *h, ipmi_sensor_t *raw_sensor, const char *id, const char *full_name,
		int reading_type)
{
	zbx_ipmi_sensor_t	*s;

	h->sensor_count++;
	h->sensors = (zbx_ipmi_sensor_t *)zbx_realloc(h->sensors, (size_t)h->sensor_count * sizeof(zbx_ipmi_sensor_t));

	s = &h->sensors[h->sensor_count - 1];
	memset(s, 0, sizeof(*s));
	s->sensor_id = ipmi_sensor_convert_to_id(raw_sensor);
	zbx_strlcpy(s->sdr_id, id, sizeof(s->sdr_id));
	s->sdr_id_type = IPMI_ASCII_STR;
	s->sdr_id_sz = (int)strlen(id);
	s->full_name = zbx_strdup(NULL, full_name);
	s->sensor_type_str = zbx_strdup(NULL, "type");
	s->event_reading_type_str = zbx_strdup(NULL, "reading_type");
	s->event_reading_type = reading_type;
}

static void	free_sensors(zbx_ipmi_host_t *h)
{
	for (int i = 0; i < h->sensor_count; i++)
	{
		zbx_free(h->sensors[i].full_name);
		zbx_free(h->sensors[i].sensor_type_str);
		zbx_free(h->sensors[i].event_reading_type_str);
	}
	zbx_free(h->sensors);
}

static ipmi_sensor_t	*sensor_by_name(const char *name)
{
	if (0 == strcmp(name, "a"))
		return (ipmi_sensor_t *)&dummy_a;
	if (0 == strcmp(name, "b"))
		return (ipmi_sensor_t *)&dummy_b;
	if (0 == strcmp(name, "c"))	/* never registered in the host - simulates "not found" */
		return (ipmi_sensor_t *)&dummy_c;

	fail_msg("unknown sensor name: %s", name);
	return NULL;
}

static int	dummy_d;

void	zbx_mock_test_entry(void **state)
{
	zbx_ipmi_host_t	h;
	const char	*func = zbx_mock_get_parameter_string("in.func");
	zbx_ipmi_sensor_t	*s = NULL;

	ZBX_UNUSED(state);

	memset(&h, 0, sizeof(h));
	h.ip = "127.0.0.1";
	h.port = 623;

	if (0 == strcmp(func, "by_id_prefers_threshold"))
	{
		/* Some devices present the same physical sensor as both a threshold and a discrete sensor    */
		/* sharing the same id; zbx_get_ipmi_sensor_by_id() must always prefer the threshold one,      */
		/* regardless of which one comes first in h->sensors[] (see the comment in the function).      */
		const char	*order = zbx_mock_get_parameter_string("in.order");

		if (0 == strcmp(order, "threshold_first"))
		{
			add_sensor(&h, (ipmi_sensor_t *)&dummy_a, "dup", "Dup Threshold",
					IPMI_EVENT_READING_TYPE_THRESHOLD);
			add_sensor(&h, (ipmi_sensor_t *)&dummy_d, "dup", "Dup Discrete",
					IPMI_EVENT_READING_TYPE_DISCRETE_STATE);
		}
		else if (0 == strcmp(order, "discrete_first"))
		{
			add_sensor(&h, (ipmi_sensor_t *)&dummy_d, "dup", "Dup Discrete",
					IPMI_EVENT_READING_TYPE_DISCRETE_STATE);
			add_sensor(&h, (ipmi_sensor_t *)&dummy_a, "dup", "Dup Threshold",
					IPMI_EVENT_READING_TYPE_THRESHOLD);
		}
		else
			fail_msg("unknown 'in.order' value: %s", order);

		s = zbx_get_ipmi_sensor_by_id(&h, "dup");

		if (NULL == s)
			fail_msg("expected a matching sensor to be found, got NULL");
		zbx_mock_assert_str_eq("by_id must prefer the threshold sensor", "Dup Threshold", s->full_name);

		free_sensors(&h);
		return;
	}

	add_sensor(&h, (ipmi_sensor_t *)&dummy_a, "id-a", "Sensor A", IPMI_EVENT_READING_TYPE_THRESHOLD);
	add_sensor(&h, (ipmi_sensor_t *)&dummy_b, "id-b", "Sensor B", IPMI_EVENT_READING_TYPE_DISCRETE_STATE);

	if (0 == strcmp(func, "by_pointer"))
		s = zbx_get_ipmi_sensor(&h, sensor_by_name(zbx_mock_get_parameter_string("in.lookup")));
	else if (0 == strcmp(func, "by_id"))
		s = zbx_get_ipmi_sensor_by_id(&h, zbx_mock_get_parameter_string("in.lookup"));
	else if (0 == strcmp(func, "by_full_name"))
		s = zbx_get_ipmi_sensor_by_full_name(&h, zbx_mock_get_parameter_string("in.lookup"));
	else if (0 == strcmp(func, "delete"))
	{
		int	exp_count = zbx_mock_get_parameter_int("out.sensor_count");

		/* a third sensor lets 'delete the first of three' exercise a memmove() of more than one */
		/* element, unlike the two-sensor cases below */
		if (0 == strcmp(zbx_mock_get_parameter_string("in.three_sensors"), "yes"))
		{
			add_sensor(&h, (ipmi_sensor_t *)&dummy_d, "id-d", "Sensor D",
					IPMI_EVENT_READING_TYPE_THRESHOLD);
		}

		zbx_delete_ipmi_sensor(&h, sensor_by_name(zbx_mock_get_parameter_string("in.lookup")));
		zbx_mock_assert_int_eq("sensor_count after delete", exp_count, h.sensor_count);

		/* the surviving sensor(s) must still be intact - memmove() didn't corrupt them */
		if (0 < h.sensor_count)
		{
			zbx_mock_assert_str_eq("surviving sensor full_name",
					zbx_mock_get_parameter_string("out.surviving_full_name"),
					h.sensors[0].full_name);
		}

		free_sensors(&h);
		return;
	}
	else
		fail_msg("unknown 'in.func' value: %s", func);

	if (0 == strcmp(zbx_mock_get_parameter_string("out.found"), "yes"))
	{
		if (NULL == s)
			fail_msg("expected a matching sensor to be found, got NULL");
		zbx_mock_assert_str_eq("found sensor full_name", zbx_mock_get_parameter_string("out.full_name"),
				s->full_name);
	}
	else
		zbx_mock_assert_ptr_eq("no sensor should be found", NULL, s);

	free_sensors(&h);
}
