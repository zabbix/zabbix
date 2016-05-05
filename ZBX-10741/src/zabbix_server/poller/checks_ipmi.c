/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "checks_ipmi.h"

#ifdef HAVE_OPENIPMI

#include "log.h"

#include <OpenIPMI/ipmiif.h>
#include <OpenIPMI/ipmi_posix.h>
#include <OpenIPMI/ipmi_lan.h>
#include <OpenIPMI/ipmi_sdr.h>
#include <OpenIPMI/ipmi_msgbits.h>

typedef struct
{
	ipmi_sensor_t		*sensor;
	char			*s_name;
	double			value;
}
zbx_ipmi_sensor_t;

typedef struct
{
	ipmi_control_t		*control;
	char			*c_name;
	int			*val, num_values;
}
zbx_ipmi_control_t;

typedef struct zbx_ipmi_host_s
{
	char			*ip;
	int			port;
	int			authtype;
	int			privilege;
	char			*username;
	char			*password;
	zbx_ipmi_sensor_t	*sensors;
	int			sensor_count;
	zbx_ipmi_control_t	*controls;
	int			control_count;
	ipmi_con_t		*con;
	int			domain_up, done;
	char			*err;
	int			ret;
	struct zbx_ipmi_host_s	*next;
}
zbx_ipmi_host_t;

static zbx_ipmi_host_t	*hosts = NULL;
static os_handler_t	*os_hnd;

static zbx_ipmi_host_t	*get_ipmi_host(const char *ip, const int port,
		int authtype, int privilege, const char *username,
		const char *password)
{
	const char	*__function_name = "get_ipmi_host";
	zbx_ipmi_host_t	*h;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d'", __function_name, ip, port);

	h = hosts;
	while (NULL != h)
	{
		if (0 == strcmp(ip, h->ip) && port == h->port && authtype == h->authtype &&
				privilege == h->privilege && 0 == strcmp(username, h->username) &&
				0 == strcmp(password, h->password))
		{
			break;
		}

		h = h->next;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, h);

	return h;
}

static zbx_ipmi_host_t  *allocate_ipmi_host(const char *ip, int port, int authtype, int privilege,
		const char *username, const char *password)
{
	const char	*__function_name = "allocate_ipmi_host";
	zbx_ipmi_host_t	*h;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d'", __function_name, ip, port);

	h = zbx_malloc(NULL, sizeof(zbx_ipmi_host_t));

	memset(h, 0, sizeof(zbx_ipmi_host_t));

	h->ip = strdup(ip);
	h->port = port;
	h->authtype = authtype;
	h->privilege = privilege;
	h->username = strdup(username);
	h->password = strdup(password);

	h->next = hosts;
	hosts = h;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, h);

	return h;
}

static zbx_ipmi_sensor_t	*get_ipmi_sensor(zbx_ipmi_host_t *h, ipmi_sensor_t *sensor)
{
	const char		*__function_name = "get_ipmi_sensor";
	int			i;
	zbx_ipmi_sensor_t	*s = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p psensor:%p", __function_name, h, sensor);

	for (i = 0; i < h->sensor_count; i++)
	{
		if (h->sensors[i].sensor == sensor)
		{
			s = &h->sensors[i];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, s);

	return s;
}

static zbx_ipmi_sensor_t	*get_ipmi_sensor_by_name(zbx_ipmi_host_t *h, const char *s_name)
{
	const char		*__function_name = "get_ipmi_sensor_by_name";
	int			i;
	zbx_ipmi_sensor_t	*s = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sensor:'%s@[%s]:%d'", __function_name, s_name, h->ip, h->port);

	for (i = 0; i < h->sensor_count; i++)
	{
		if (0 == strcmp(h->sensors[i].s_name, s_name))
		{
			s = &h->sensors[i];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, s);

	return s;
}

static zbx_ipmi_sensor_t	*allocate_ipmi_sensor(zbx_ipmi_host_t *h, ipmi_sensor_t *sensor)
{
	const char		*__function_name = "allocate_ipmi_sensor";
	size_t			sz;
	zbx_ipmi_sensor_t	*s;
	char			*s_name = NULL;

	sz = (size_t)ipmi_sensor_get_id_length(sensor);
	s_name = zbx_malloc(s_name, sz + 1);
	ipmi_sensor_get_id(sensor, s_name, sz);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sensor:'%s@[%s]:%d'", __function_name, s_name, h->ip, h->port);

	h->sensor_count++;
	sz = h->sensor_count * sizeof(zbx_ipmi_sensor_t);

	if (NULL == h->sensors)
		h->sensors = zbx_malloc(h->sensors, sz);
	else
		h->sensors = zbx_realloc(h->sensors, sz);

	s = &h->sensors[h->sensor_count - 1];

	memset(s, 0, sizeof(zbx_ipmi_sensor_t));

	s->sensor = sensor;
	s->s_name = s_name;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, s);

	return s;
}

static void	delete_ipmi_sensor(zbx_ipmi_host_t *h, ipmi_sensor_t *sensor)
{
	const char	*__function_name = "delete_ipmi_sensor";
	int		i;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p psensor:%p",
			__function_name, h, sensor);

	for (i = 0; i < h->sensor_count; i++)
	{
		if (h->sensors[i].sensor != sensor)
			continue;

		sz = sizeof(zbx_ipmi_sensor_t);

		zabbix_log(LOG_LEVEL_DEBUG, "sensor '%s@[%s]:%d' deleted",
				h->sensors[i].s_name, h->ip, h->port);

		zbx_free(h->sensors[i].s_name);

		h->sensor_count--;
		if (h->sensor_count != i)
			memmove(&h->sensors[i], &h->sensors[i + 1], sz * (h->sensor_count - i));
		h->sensors = zbx_realloc(h->sensors, sz * h->sensor_count);

		break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static zbx_ipmi_control_t	*get_ipmi_control(zbx_ipmi_host_t *h, ipmi_control_t *control)
{
	const char		*__function_name = "get_ipmi_control";
	int			i;
	zbx_ipmi_control_t	*c = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p pcontrol:%p", __function_name, h, control);

	for (i = 0; i < h->control_count; i++)
	{
		if (h->controls[i].control == control)
		{
			c = &h->controls[i];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, c);

	return c;
}

static zbx_ipmi_control_t	*get_ipmi_control_by_name(zbx_ipmi_host_t *h, const char *c_name)
{
	const char		*__function_name = "get_ipmi_control_by_name";
	int			i;
	zbx_ipmi_control_t	*c = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() %s@[%s]:%d", __function_name, c_name, h->ip, h->port);

	for (i = 0; i < h->control_count; i++)
	{
		if (0 == strcmp(h->controls[i].c_name, c_name))
		{
			c = &h->controls[i];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, c);

	return c;
}

static zbx_ipmi_control_t	*allocate_ipmi_control(zbx_ipmi_host_t *h, ipmi_control_t *control)
{
	const char		*__function_name = "allocate_ipmi_control";
	size_t			sz;
	zbx_ipmi_control_t	*c;
	char			*c_name = NULL;

	sz = (size_t)ipmi_control_get_id_length(control);
	c_name = zbx_malloc(c_name, sz + 1);
	ipmi_control_get_id(control, c_name, sz);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() control:'%s@[%s]:%d'", __function_name, c_name, h->ip, h->port);

	h->control_count++;
	sz = h->control_count * sizeof(zbx_ipmi_control_t);

	if (NULL == h->controls)
		h->controls = zbx_malloc(h->controls, sz);
	else
		h->controls = zbx_realloc(h->controls, sz);

	c = &h->controls[h->control_count - 1];

	memset(c, 0, sizeof(zbx_ipmi_control_t));

	c->control = control;
	c->c_name = c_name;
	c->num_values = ipmi_control_get_num_vals(control);
	sz = sizeof(int) * c->num_values;
	c->val = zbx_malloc(c->val, sz);
	memset(c->val, 0, sz);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, c);

	return c;
}

static void	delete_ipmi_control(zbx_ipmi_host_t *h, ipmi_control_t *control)
{
	const char	*__function_name = "delete_ipmi_control";
	int	i;
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p pcontrol:%p", __function_name, h, control);

	for (i = 0; i < h->control_count; i++)
	{
		if (h->controls[i].control != control)
			continue;

		sz = sizeof(zbx_ipmi_control_t);

		zabbix_log(LOG_LEVEL_DEBUG, "control '%s@[%s]:%d' deleted",
				h->controls[i].c_name, h->ip, h->port);

		zbx_free(h->controls[i].c_name);
		zbx_free(h->controls[i].val);

		h->control_count--;
		if (h->control_count != i)
			memmove(&h->controls[i], &h->controls[i + 1], sz * (h->control_count - i));
		h->controls = zbx_realloc(h->controls, sz * h->control_count);

		break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	got_thresh_reading(ipmi_sensor_t *sensor, int err, enum ipmi_value_present_e value_present,
		unsigned int raw_value, double val, ipmi_states_t *states, void *cb_data)
{
	const char		*__function_name = "got_thresh_reading";
	const char		*e_string, *s_type_string, *s_reading_type_string;
	ipmi_entity_t		*ent;
	const char		*percent = "", *base, *mod_use = "", *modifier = "", *rate;
	zbx_ipmi_host_t		*h = cb_data;
	zbx_ipmi_sensor_t	*s;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() fail: %s", __function_name, zbx_strerror(err));

		h->err = zbx_dsprintf(h->err, "error 0x%x while reading threshold sensor", err);
		h->ret = NETWORK_ERROR;
		h->done = 1;
		goto out;
	}

	if (0 == ipmi_is_sensor_scanning_enabled(states) || 0 != ipmi_is_initial_update_in_progress(states))
	{
		h->err = zbx_strdup(h->err, "sensor data is not available");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		goto out;
	}

	s = get_ipmi_sensor(h, sensor);

	if (NULL == s)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_strdup(h->err, "fatal error");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		goto out;
	}

	switch (value_present)
	{
		case IPMI_NO_VALUES_PRESENT:
		case IPMI_RAW_VALUE_PRESENT:
			h->err = zbx_strdup(h->err, "no value present for threshold sensor");
			h->ret = NOTSUPPORTED;
			break;
		case IPMI_BOTH_VALUES_PRESENT:
			s->value = val;

			/* next lines only for debug logging */
			ent = ipmi_sensor_get_entity(sensor);
			e_string = ipmi_entity_get_entity_id_string(ent);
			s_type_string = ipmi_sensor_get_sensor_type_string(sensor);
			s_reading_type_string = ipmi_sensor_get_event_reading_type_string(sensor);

			base = ipmi_sensor_get_base_unit_string(sensor);
			if (ipmi_sensor_get_percentage(sensor))
				percent = "%";
			switch (ipmi_sensor_get_modifier_unit_use(sensor)) {
				case IPMI_MODIFIER_UNIT_NONE:
					break;
				case IPMI_MODIFIER_UNIT_BASE_DIV_MOD:
					mod_use = "/";
					modifier = ipmi_sensor_get_modifier_unit_string(sensor);
					break;
				case IPMI_MODIFIER_UNIT_BASE_MULT_MOD:
					mod_use = "*";
					modifier = ipmi_sensor_get_modifier_unit_string(sensor);
					break;
			}
			rate = ipmi_sensor_get_rate_unit_string(sensor);

			zabbix_log(LOG_LEVEL_DEBUG, "Value [%s | %s | %s | %s | " ZBX_FS_DBL "%s %s%s%s%s]",
					s->s_name, e_string, s_type_string, s_reading_type_string,
					val, percent, base, mod_use, modifier, rate);
			break;
	}
	h->done = 1;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(h->ret));
}

static void	read_ipmi_sensor(zbx_ipmi_host_t *h, zbx_ipmi_sensor_t *s)
{
	const char		*__function_name = "read_ipmi_sensor";
	int			type, ret;
	struct timeval		tv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sensor:'%s@[%s]:%d'",
			__function_name, s->s_name, h->ip, h->port);

	h->ret = SUCCEED;
	h->done = 0;

	type = ipmi_sensor_get_event_reading_type(s->sensor);

	switch (type)
	{
		case IPMI_EVENT_READING_TYPE_THRESHOLD:
			if (0 != (ret = ipmi_sensor_get_reading(s->sensor, got_thresh_reading, h)))
			{
				h->err = zbx_dsprintf(h->err, "Cannot read sensor %s."
						" ipmi_sensor_get_reading() return error: 0x%x",
						s->s_name, ret);
				h->ret = NOTSUPPORTED;
				goto out;
			}
			break;
		default:
			h->err = zbx_strdup(h->err, "discrete sensor is not supported");
			h->ret = NOTSUPPORTED;
			goto out;
	}

	tv.tv_sec = 10;
	tv.tv_usec = 0;

	while (0 == h->done)
		os_hnd->perform_one_op(os_hnd, &tv);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(h->ret));
}

static void	got_control_reading(ipmi_control_t *control, int err, int *val, void *cb_data)
{
	const char		*__function_name = "got_control_reading";
	zbx_ipmi_host_t		*h = cb_data;
	int			n;
	zbx_ipmi_control_t	*c;
	const char		*e_string;
	ipmi_entity_t		*ent;
	size_t			sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() fail: %s", __function_name, zbx_strerror(err));

		h->err = zbx_dsprintf(h->err, "error 0x%x while reading control", err);
		h->ret = NETWORK_ERROR;
		h->done = 1;
		goto out;
	}

	c = get_ipmi_control(h, control);

	if (NULL == c)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_strdup(h->err, "fatal error");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		goto out;
	}

	if (c->num_values == 0)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_strdup(h->err, "no value present for control");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		goto out;
	}

	ent = ipmi_control_get_entity(control);
	e_string = ipmi_entity_get_entity_id_string(ent);

	for (n = 0; n < c->num_values; n++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "control values [%s | %s | %d:%d]",
				c->c_name, e_string, n + 1, val[n]);
	}

	sz = sizeof(int) * c->num_values;
	memcpy(c->val, val, sz);

	h->done = 1;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(h->ret));
}

static void	got_control_setting(ipmi_control_t *control, int err, void *cb_data)
{
	const char		*__function_name = "got_control_setting";
	zbx_ipmi_host_t		*h = cb_data;
	zbx_ipmi_control_t	*c;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() fail: %s", __function_name, zbx_strerror(err));

		h->err = zbx_dsprintf(h->err, "error 0x%x while set control", err);
		h->ret = NETWORK_ERROR;
		h->done = 1;
		return;
	}

	c = get_ipmi_control(h, control);

	if (NULL == c)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_strdup(h->err, "fatal error");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "set value completed for control %s@[%s]:%d", c->c_name, h->ip, h->port);

	h->done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(h->ret));
}

static void	read_ipmi_control(zbx_ipmi_host_t *h, zbx_ipmi_control_t *c)
{
	const char		*__function_name = "read_ipmi_control";
	int			ret;
	struct timeval		tv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() control:'%s@[%s]:%d'", __function_name, c->c_name, h->ip, h->port);

	if (0 == ipmi_control_is_readable(c->control))
	{
		h->err = zbx_strdup(h->err, "control is not readable");
		h->ret = NOTSUPPORTED;
		goto out;
	}

	h->ret = SUCCEED;
	h->done = 0;

	if (0 != (ret = ipmi_control_get_val(c->control, got_control_reading, h)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot read control %s. ipmi_control_get_val() return error: 0x%x",
				c->c_name, ret);
		h->ret = NOTSUPPORTED;
		goto out;
	}

	tv.tv_sec = 10;
	tv.tv_usec = 0;

	while (0 == h->done)
		os_hnd->perform_one_op(os_hnd, &tv);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(h->ret));
}

static void	set_ipmi_control(zbx_ipmi_host_t *h, zbx_ipmi_control_t *c, int value)
{
	const char		*__function_name = "set_ipmi_control";
	int			ret;
	struct timeval		tv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() control:'%s@[%s]:%d' value:%d",
			__function_name, c->c_name, h->ip, h->port, value);

	if (c->num_values == 0)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_strdup(h->err, "no value present for control");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		goto out;
	}

	if (0 == ipmi_control_is_settable(c->control))
	{
		h->err = zbx_strdup(h->err, "control is not settable");
		h->ret = NOTSUPPORTED;
		goto out;
	}

	c->val[0] = value;
	h->ret = SUCCEED;
	h->done = 0;

	if (0 != (ret = ipmi_control_set_val(c->control, c->val, got_control_setting, h)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot set control %s. ipmi_control_set_val() return error: 0x%x",
				c->c_name, ret);
		h->ret = NOTSUPPORTED;
		goto out;
	}

	tv.tv_sec = 10;
	tv.tv_usec = 0;

	while (0 == h->done)
		os_hnd->perform_one_op(os_hnd, &tv);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(h->ret));
}

static void	sensor_change(enum ipmi_update_e op, ipmi_entity_t *ent, ipmi_sensor_t *sensor, void *cb_data)
{
	const char	*__function_name = "sensor_change";
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p host:'[%s]:%d'", __function_name, h, h->ip, h->port);

	switch (ipmi_sensor_get_event_reading_type(sensor))
	{
		case IPMI_EVENT_READING_TYPE_THRESHOLD:
		case IPMI_EVENT_READING_TYPE_DISCRETE_USAGE:
		case IPMI_EVENT_READING_TYPE_DISCRETE_STATE:
		case IPMI_EVENT_READING_TYPE_DISCRETE_PREDICTIVE_FAILURE:
		case IPMI_EVENT_READING_TYPE_DISCRETE_LIMIT_EXCEEDED:
		case IPMI_EVENT_READING_TYPE_DISCRETE_PERFORMANCE_MET:
		case IPMI_EVENT_READING_TYPE_DISCRETE_SEVERITY:
		case IPMI_EVENT_READING_TYPE_DISCRETE_DEVICE_PRESENCE:
		case IPMI_EVENT_READING_TYPE_DISCRETE_DEVICE_ENABLE:
		case IPMI_EVENT_READING_TYPE_DISCRETE_AVAILABILITY:
		case IPMI_EVENT_READING_TYPE_DISCRETE_REDUNDANCY:
		case IPMI_EVENT_READING_TYPE_DISCRETE_ACPI_POWER:
			if (op == IPMI_ADDED)
			{
				if (NULL == get_ipmi_sensor(h, sensor))
					allocate_ipmi_sensor(h, sensor);
			}
			else if (op == IPMI_DELETED)
				delete_ipmi_sensor(h, sensor);
			break;
		case IPMI_EVENT_READING_TYPE_SENSOR_SPECIFIC:
			;	/* nothing */
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	control_change(enum ipmi_update_e op, ipmi_entity_t *ent, ipmi_control_t *control, void *cb_data)
{
	const char	*__function_name = "control_change";
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p host:'[%s]:%d'", __function_name, h, h->ip, h->port);

	if (IPMI_ADDED == op)
	{
		if (NULL == get_ipmi_control(h, control))
			allocate_ipmi_control(h, control);
	}
	else if (IPMI_DELETED == op)
	{
		delete_ipmi_control(h, control);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	entity_change(enum ipmi_update_e op, ipmi_domain_t *domain, ipmi_entity_t *entity, void *cb_data)
{
	const char	*__function_name = "entity_change";
	int		ret;
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p host:'[%s]:%d'", __function_name, h, h->ip, h->port);

	if (op == IPMI_ADDED)
	{
		if (0 != (ret = ipmi_entity_add_sensor_update_handler(entity, sensor_change, h)))
			zabbix_log(LOG_LEVEL_DEBUG, "ipmi_entity_set_sensor_update_handler() return error: 0x%x", ret);

		if (0 != (ret = ipmi_entity_add_control_update_handler(entity, control_change, h)))
			zabbix_log(LOG_LEVEL_DEBUG, "ipmi_entity_add_control_update_handler() return error: 0x%x", ret);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	domain_closed(void *cb_data)
{
	const char	*__function_name = "domain_closed";
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p host:'[%s]:%d'", __function_name, h, h->ip, h->port);

	h->domain_up = 0;
	h->done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	setup_done(ipmi_domain_t *domain, int err, unsigned int conn_num, unsigned int port_num,
		int still_connected, void *cb_data)
{
	const char	*__function_name = "setup_done";
	int		ret;
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p host:'[%s]:%d'", __function_name, h, h->ip, h->port);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() fail: %s", __function_name, zbx_strerror(err));

		h->err = zbx_dsprintf(h->err, "cannot connect to IPMI host: %s", zbx_strerror(err));
		h->ret = NETWORK_ERROR;

		if (0 != (ret = ipmi_domain_close(domain, domain_closed, h)))
			zabbix_log(LOG_LEVEL_DEBUG, "cannot close IPMI domain: [0x%x]", ret);

		goto out;
	}

	if (0 != (ret = ipmi_domain_add_entity_update_handler(domain, entity_change, h)))
		zabbix_log(LOG_LEVEL_DEBUG, "ipmi_domain_add_entity_update_handler() return error: [0x%x]", ret);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(h->ret));
}

static void	domain_up(ipmi_domain_t *domain, void *cb_data)
{
	const char	*__function_name = "domain_up";
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p, host:'[%s]:%d'", __function_name, h, h->ip, h->port);

	h->domain_up = 1;
	h->done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	my_vlog(os_handler_t *handler, const char *format, enum ipmi_log_type_e log_type, va_list ap)
{
	char	type[8], str[MAX_STRING_LEN];

	switch (log_type)
	{
		case IPMI_LOG_INFO		: zbx_strlcpy(type, "INFO: ", sizeof(type)); break;
		case IPMI_LOG_WARNING		: zbx_strlcpy(type, "WARN: ", sizeof(type)); break;
		case IPMI_LOG_SEVERE		: zbx_strlcpy(type, "SEVR: ", sizeof(type)); break;
		case IPMI_LOG_FATAL		: zbx_strlcpy(type, "FATL: ", sizeof(type)); break;
		case IPMI_LOG_ERR_INFO		: zbx_strlcpy(type, "EINF: ", sizeof(type)); break;
		case IPMI_LOG_DEBUG_START	:
		case IPMI_LOG_DEBUG		: zbx_strlcpy(type, "DEBG: ", sizeof(type)); break;
		case IPMI_LOG_DEBUG_CONT	:
		case IPMI_LOG_DEBUG_END		: *type = '\0'; break;
	}

	zbx_vsnprintf(str, sizeof(str), format, ap);

	zabbix_log(LOG_LEVEL_DEBUG, "%s%s", type, str);
}

int	init_ipmi_handler()
{
	const char	*__function_name = "init_ipmi_handler";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == (os_hnd = ipmi_posix_setup_os_handler()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "unable to allocate IPMI handler");
		return FAIL;
	}

	os_hnd->set_log_handler(os_hnd, my_vlog);

	ipmi_init(os_hnd);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;
}

int	free_ipmi_handler()
{
	const char	*__function_name = "free_ipmi_handler";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	while (NULL != hosts)
	{
		int		i;
		zbx_ipmi_host_t	*h;

		h = hosts;
		hosts = hosts->next;

		h->con->close_connection(h->con);

		for (i = 0; i < h->sensor_count; i++)
			zbx_free(h->sensors[i].s_name);

		for (i = 0; i < h->control_count; i++)
		{
			zbx_free(h->controls[i].c_name);
			zbx_free(h->controls[i].val);
		}

		zbx_free(h->sensors);
		zbx_free(h->controls);
		zbx_free(h->ip);
		zbx_free(h->username);
		zbx_free(h->password);
		zbx_free(h->err);

		zbx_free(h);
	}

	os_hnd->free_os_handler(os_hnd);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;
}

static zbx_ipmi_host_t	*init_ipmi_host(const char *ip, int port, int authtype, int privilege, const char *username, const char *password)
{
	const char		*__function_name = "init_ipmi_host";
	zbx_ipmi_host_t		*h;
	int			ret;
	ipmi_open_option_t	options[2];
	struct timeval		tv;
	char			*addrs[1], *ports[1];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d'", __function_name, ip, port);

	h = get_ipmi_host(ip, port, authtype, privilege, username, password);

	if (NULL != h)
	{
		if (1 == h->domain_up)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, h);
			return h;
		}
	}
	else
		h = allocate_ipmi_host(ip, port, authtype, privilege, username, password);

	h->ret = SUCCEED;
	h->done = 0;

	addrs[0] = strdup(h->ip);
	ports[0] = zbx_dsprintf(NULL, "%d", h->port);

	if (0 != (ret = ipmi_ip_setup_con(addrs, ports, 1,
			h->authtype == -1 ? (unsigned int)(~0) : (unsigned int)h->authtype,
			(unsigned int)h->privilege, h->username, strlen(h->username),
			h->password, strlen(h->password), os_hnd, NULL, &h->con)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot connect to IPMI host [%s]:%d."
				" ipmi_ip_setup_con() returned error 0x%x",
				h->ip, h->port, ret);
		h->ret = NETWORK_ERROR;
		goto out;
	}

	if (0 != (ret = h->con->start_con(h->con)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot connect to IPMI host [%s]:%d."
				" start_con() returned error 0x%x",
				h->ip, h->port, ret);
		h->ret = NETWORK_ERROR;
		goto out;
	}

	options[0].option = IPMI_OPEN_OPTION_ALL;
	options[0].ival = 0;
	options[1].option = IPMI_OPEN_OPTION_SDRS;
	options[1].ival = 1;

	if (0 != (ret = ipmi_open_domain("", &h->con, 1, setup_done, h, domain_up, h, options, 2, NULL)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot connect to IPMI host [%s]:%d. ipmi_open_domain() failed: %s",
				h->ip, h->port, zbx_strerror(ret));
		h->ret = NETWORK_ERROR;
		goto out;
	}

	tv.tv_sec = 10;
	tv.tv_usec = 0;

	while (0 == h->done)
		os_hnd->perform_one_op(os_hnd, &tv);
out:
	zbx_free(addrs[0]);
	zbx_free(ports[0]);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, h);

	return h;
}

int	get_value_ipmi(DC_ITEM *item, AGENT_RESULT *value)
{
	const char		*__function_name = "get_value_ipmi";
	zbx_ipmi_host_t		*h;
	zbx_ipmi_sensor_t	*s;
	zbx_ipmi_control_t	*c = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s:%s'", __function_name, item->host.host, item->key_orig);

	if (NULL == os_hnd)
	{
		SET_MSG_RESULT(value, strdup("IPMI handler is not initialised"));
		return NOTSUPPORTED;
	}

	h = init_ipmi_host(item->interface.addr, item->interface.port, item->host.ipmi_authtype,
			item->host.ipmi_privilege, item->host.ipmi_username, item->host.ipmi_password);

	if (0 == h->domain_up)
	{
		if (NULL != h->err)
		{
			SET_MSG_RESULT(value, strdup(h->err));
		}
		return h->ret;
	}

	s = get_ipmi_sensor_by_name(h, item->ipmi_sensor);
	if (NULL == s)
		c = get_ipmi_control_by_name(h, item->ipmi_sensor);

	if (NULL == s && NULL == c)
	{
		SET_MSG_RESULT(value, zbx_dsprintf(NULL, "sensor or control %s@[%s]:%d does not exist",
				item->ipmi_sensor, h->ip, h->port));
		return NOTSUPPORTED;
	}

	if (NULL != s)
		read_ipmi_sensor(h, s);
	else
		read_ipmi_control(h, c);

	if (h->ret != SUCCEED)
	{
		if (NULL != h->err)
		{
			SET_MSG_RESULT(value, strdup(h->err));
		}
		return h->ret;
	}

	if (NULL != s)
		SET_DBL_RESULT(value, s->value);
	if (NULL != c)
		SET_DBL_RESULT(value, c->val[0]);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(h->ret));

	return h->ret;
}

/* function 'parse_ipmi_command' requires 'c_name' with size 'ITEM_IPMI_SENSOR_LEN_MAX' */
int	parse_ipmi_command(const char *command, char *c_name, int *val, char *error, size_t max_error_len)
{
	const char	*__function_name = "parse_ipmi_command";

	const char	*p;
	size_t		sz_c_name;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() command:'%s'", __function_name, command);

	while ('\0' != *command && NULL != strchr(" \t", *command))
		command++;

	for (p = command; '\0' != *p && NULL == strchr(" \t", *p); p++)
		;

	if (0 == (sz_c_name = p - command))
	{
		zbx_strlcpy(error, "IPMI command is empty", max_error_len);
		goto fail;
	}

	if (ITEM_IPMI_SENSOR_LEN_MAX <= sz_c_name)
	{
		zbx_snprintf(error, max_error_len, "IPMI command is too long [%.*s]", sz_c_name, command);
		goto fail;
	}

	memcpy(c_name, command, sz_c_name);
	c_name[sz_c_name] = '\0';

	while ('\0' != *p && NULL != strchr(" \t", *p))
		p++;

	if ('\0' == *p || 0 == strcasecmp(p, "on"))
		*val = 1;
	else if (0 == strcasecmp(p, "off"))
		*val = 0;
	else if (SUCCEED == is_uint(p))
		*val = atoi(p);
	else
	{
		zbx_snprintf(error, max_error_len, "IPMI command value is not supported [%s]", p);
		goto fail;
	}

	ret = SUCCEED;
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	set_ipmi_control_value(DC_ITEM *item, int value, char *error, size_t max_error_len)
{
	zbx_ipmi_host_t		*h;
	zbx_ipmi_control_t	*c;

	zabbix_log(LOG_LEVEL_DEBUG, "In set_ipmi_control_value(control:%s, value:%d)", item->ipmi_sensor, value);

	if (NULL == os_hnd)
	{
		zbx_strlcpy(error, "IPMI handler is not initialised", max_error_len);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		return NOTSUPPORTED;
	}

	h = init_ipmi_host(item->interface.addr, item->interface.port, item->host.ipmi_authtype,
			item->host.ipmi_privilege, item->host.ipmi_username, item->host.ipmi_password);

	if (0 == h->domain_up)
	{
		if (NULL != h->err)
		{
			zbx_strlcpy(error, h->err, max_error_len);
			zabbix_log(LOG_LEVEL_DEBUG, "%s", h->err);
		}
		return h->ret;
	}

	c = get_ipmi_control_by_name(h, item->ipmi_sensor);

	if (NULL == c)
	{
		zbx_snprintf(error, max_error_len, "control %s@[%s]:%d does not exist",
				item->ipmi_sensor, h->ip, h->port);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		return NOTSUPPORTED;
	}

	set_ipmi_control(h, c, value);

	if (h->ret != SUCCEED)
	{
		if (NULL != h->err)
		{
			zbx_strlcpy(error, h->err, max_error_len);
			zabbix_log(LOG_LEVEL_DEBUG, "%s", h->err);
		}
	}

	return h->ret;
}

#endif	/* HAVE_OPENIPMI */
