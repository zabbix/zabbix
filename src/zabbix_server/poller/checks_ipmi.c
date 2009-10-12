/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "checks_ipmi.h"

#ifdef HAVE_OPENIPMI

#include "log.h"

#include <OpenIPMI/ipmiif.h>
#include <OpenIPMI/ipmi_posix.h>
#include <OpenIPMI/ipmi_lan.h>
#include <OpenIPMI/ipmi_sdr.h>
#include <OpenIPMI/ipmi_msgbits.h>

typedef struct zbx_ipmi_sensor {
	ipmi_sensor_t		*sensor;
	char			*s_name;
	double			value;
} zbx_ipmi_sensor_t;

typedef struct zbx_ipmi_control {
	ipmi_control_t		*control;
	char			*c_name;
	int			*val, num_values;
} zbx_ipmi_control_t;

typedef struct zbx_ipmi_host {
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
} zbx_ipmi_host_t;

static zbx_ipmi_host_t	*hosts = NULL;
static int		host_count = 0;
static os_handler_t	*os_hnd;

static zbx_ipmi_host_t	*get_ipmi_host(const char *ip, const int port, int authtype, int privilege,
		const char *username, const char *password)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_ipmi_host([%s]:%d)", ip, port);

	for (i = 0; i < host_count; i ++)
		if (0 == strcmp(ip, hosts[i].ip) && port == hosts[i].port && authtype == hosts[i].authtype
				&& privilege == hosts[i].privilege && 0 == strcmp(username, hosts[i].username)
				&& 0 == strcmp(password, hosts[i].password))
			return &hosts[i];

	return NULL;
}

static zbx_ipmi_host_t  *allocate_ipmi_host(const char *ip, int port, int authtype, int privilege,
		const char *username, const char *password)
{
	size_t		sz;
	zbx_ipmi_host_t	*h;

	zabbix_log(LOG_LEVEL_DEBUG, "In allocate_ipmi_host([%s]:%d)", ip, port);

	host_count++;
	sz = host_count * sizeof(zbx_ipmi_host_t);

	if (NULL == hosts)
		hosts = zbx_malloc(hosts, sz);
	else
		hosts = zbx_realloc(hosts, sz);

	h = &hosts[host_count - 1];

	memset(h, 0, sizeof(zbx_ipmi_host_t));

	h->ip = strdup(ip);
	h->port = port;
	h->authtype = authtype;
	h->privilege = privilege;
	h->username = strdup(username);
	h->password = strdup(password);

	return h;
}

static zbx_ipmi_sensor_t	*get_ipmi_sensor(zbx_ipmi_host_t *h, ipmi_sensor_t *sensor)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_ipmi_sensor()");

	for (i = 0; i < h->sensor_count; i++)
		if (h->sensors[i].sensor == sensor)
			return &h->sensors[i];

	return NULL;
}

static zbx_ipmi_sensor_t	*get_ipmi_sensor_by_name(zbx_ipmi_host_t *h, const char *s_name)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_ipmi_sensor_by_name() %s@[%s]:%d",
			s_name, h->ip, h->port);

	for (i = 0; i < h->sensor_count; i++)
		if (0 == strcmp(h->sensors[i].s_name, s_name))
			return &h->sensors[i];

	return NULL;
}

static zbx_ipmi_sensor_t	*allocate_ipmi_sensor(zbx_ipmi_host_t *h, ipmi_sensor_t *sensor)
{
	size_t			sz;
	zbx_ipmi_sensor_t	*s;
	char			*s_name = NULL;

	sz = (size_t)ipmi_sensor_get_id_length(sensor);
	s_name = zbx_malloc(s_name, sz + 1);
	ipmi_sensor_get_id(sensor, s_name, sz);

	zabbix_log(LOG_LEVEL_DEBUG, "In allocate_ipmi_sensor() %s@[%s]:%d",
			s_name, h->ip, h->port);

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

	return s;
}

static void	delete_ipmi_sensor(zbx_ipmi_host_t *h, ipmi_sensor_t *sensor)
{
	int	i;
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In delete_ipmi_sensor()");

	for (i = 0; i < h->sensor_count; i++)
		if (h->sensors[i].sensor == sensor)
		{
			sz = sizeof(zbx_ipmi_sensor_t);

			zabbix_log(LOG_LEVEL_DEBUG, "Sensor %s@[%s]:%d deleted",
					h->sensors[i].s_name, h->ip, h->port);

			zbx_free(h->sensors[i].s_name);

			h->sensor_count--;
			if (h->sensor_count != i)
				memmove(&h->sensors[i], &h->sensors[i + 1], sz * (h->sensor_count - i));
			h->sensors = zbx_realloc(h->sensors, sz * h->sensor_count);

			break;
		}
}

static zbx_ipmi_control_t	*get_ipmi_control(zbx_ipmi_host_t *h, ipmi_control_t *control)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_ipmi_control()");

	for (i = 0; i < h->control_count; i++)
		if (h->controls[i].control == control)
			return &h->controls[i];

	return NULL;
}

static zbx_ipmi_control_t	*get_ipmi_control_by_name(zbx_ipmi_host_t *h, const char *c_name)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_ipmi_control_by_name() %s@[%s]:%d",
			c_name, h->ip, h->port);

	for (i = 0; i < h->control_count; i++)
		if (0 == strcmp(h->controls[i].c_name, c_name))
			return &h->controls[i];

	return NULL;
}

static zbx_ipmi_control_t	*allocate_ipmi_control(zbx_ipmi_host_t *h, ipmi_control_t *control)
{
	size_t			sz;
	zbx_ipmi_control_t	*c;
	char			*c_name = NULL;

	sz = (size_t)ipmi_control_get_id_length(control);
	c_name = zbx_malloc(c_name, sz + 1);
	ipmi_control_get_id(control, c_name, sz);

	zabbix_log(LOG_LEVEL_DEBUG, "In allocate_ipmi_control() %s@[%s]:%d",
			c_name, h->ip, h->port);

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

	return c;
}

static void	delete_ipmi_control(zbx_ipmi_host_t *h, ipmi_control_t *control)
{
	int	i;
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In delete_ipmi_control()");

	for (i = 0; i < h->control_count; i++)
		if (h->controls[i].control == control)
		{
			sz = sizeof(zbx_ipmi_control_t);

			zabbix_log(LOG_LEVEL_DEBUG, "Control %s@[%s]:%d deleted",
					h->controls[i].c_name, h->ip, h->port);

			zbx_free(h->controls[i].c_name);
			zbx_free(h->controls[i].val);

			h->control_count--;
			if (h->control_count != i)
				memmove(&h->controls[i], &h->controls[i + 1], sz * (h->control_count - i));
			h->controls = zbx_realloc(h->controls, sz * h->control_count);

			break;
		}
}

static void	got_thresh_reading(ipmi_sensor_t *sensor, int err, enum ipmi_value_present_e value_present,
		unsigned int raw_value, double val, ipmi_states_t *states, void *cb_data)
{
	const char		*e_string, *s_type_string, *s_reading_type_string;
	ipmi_entity_t		*ent;
	const char		*percent = "", *base, *mod_use = "", *modifier = "", *rate;
	zbx_ipmi_host_t	*h = cb_data;
	zbx_ipmi_sensor_t	*s;

	zabbix_log(LOG_LEVEL_DEBUG, "In got_thresh_reading()");

	if (err) {
		h->err = zbx_dsprintf(h->err, "Error 0x%x while read threshold sensor", err);
		h->ret = NETWORK_ERROR;
		h->done = 1;
		return;
	}

	s = get_ipmi_sensor(h, sensor);

	if (NULL == s)
	{
		/* this should never happen */
		h->err = zbx_dsprintf(h->err, "Fatal error");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	switch (value_present)
	{
		case IPMI_NO_VALUES_PRESENT:
		case IPMI_RAW_VALUE_PRESENT:
			h->err = zbx_dsprintf(h->err, "No value present for threshold sensor");
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
}
/*
static void	got_discrete_states(ipmi_sensor_t *sensor, int err, ipmi_states_t *states, void *cb_data)
{
	int			id, i, val, ret;
	const char		*e_string, *s_type_string, *s_reading_type_string;
	ipmi_entity_t		*ent;
	zbx_ipmi_host_t		*h = cb_data;
	zbx_ipmi_sensor_t	*s;

	s = get_ipmi_sensor(h, sensor);

	if (NULL == s)
	{*/
		/* this should never happen */
/*		h->err = zbx_dsprintf(h->err, "Fatal error");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	if (err) {
		h->err = zbx_dsprintf(h->err, "Error 0x%x while read discrete sensor %s@[%s]:%d",
				s->s_name, h->ip, h->port);
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	ent = ipmi_sensor_get_entity(sensor);
	id = ipmi_entity_get_entity_id(ent);
	e_string = ipmi_get_entity_id_string(id);
	s_type_string = ipmi_sensor_get_sensor_type_string(sensor);
	s_reading_type_string = ipmi_sensor_get_event_reading_type_string(sensor);

	for (i = 0; i < 15; i++)
	{
		ret = ipmi_sensor_discrete_event_readable(sensor, i, &val);
		if (ret || !val)
			continue;

		zabbix_log(LOG_LEVEL_DEBUG, "State [%s | %s | %s | %s | state %d value is %d]",
				s->s_name, e_string, s_type_string, s_reading_type_string, i, ipmi_is_state_set(states, i));

		s->value = ?;
	}
}
*/
static void	read_ipmi_sensor(zbx_ipmi_host_t *h, zbx_ipmi_sensor_t *s)
{
	int			type, ret;
	struct timeval		tv;

	zabbix_log(LOG_LEVEL_DEBUG, "In read_ipmi_sensor() %s@[%s]:%d",
			s->s_name, h->ip, h->port);

	h->ret = SUCCEED;
	h->done = 0;

	type = ipmi_sensor_get_event_reading_type(s->sensor);

	switch (type) {
		case IPMI_EVENT_READING_TYPE_THRESHOLD:
			if (0 != (ret = ipmi_sensor_get_reading(s->sensor, got_thresh_reading, h)))
			{
				h->err = zbx_dsprintf(h->err, "Cannot read sensor %s."
						" ipmi_sensor_get_reading() return error: 0x%x",
						s->s_name, ret);
				h->ret = NOTSUPPORTED;
				return;
			}
			break;
		default:
			h->err = zbx_dsprintf(h->err, "Discrete sensor is not supported.");
			h->ret = NOTSUPPORTED;
			return;
/*			if (0 != (ret = ipmi_sensor_get_states(s->sensor, got_discrete_states, h)))
			{
				h->err = zbx_dsprintf(h->err, "Cannot read sensor %s."
						" ipmi_sensor_get_states() return error: 0x%x",
						s->s_name, ret);
				h->ret = NOTSUPPORTED;
				return;
			}*/
	}

	tv.tv_sec = 10;
	tv.tv_usec = 0;
	while (0 == h->done)
		os_hnd->perform_one_op(os_hnd, &tv);
}

static void	got_control_reading(ipmi_control_t *control, int err, int *val, void *cb_data)
{
	zbx_ipmi_host_t		*h = cb_data;
	int			n;
	zbx_ipmi_control_t	*c;
	const char		*c_type, *e_string;
	ipmi_entity_t		*ent;
	size_t			sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In got_control_reading()");

	if (err) {
		h->err = zbx_dsprintf(h->err, "Error 0x%x while read control", err);
		h->ret = NETWORK_ERROR;
		h->done = 1;
		return;
	}

	c = get_ipmi_control(h, control);

	if (NULL == c)
	{
		/* this should never happen */
		h->err = zbx_dsprintf(h->err, "Fatal error");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	if (c->num_values == 0)
	{
		/* this should never happen */
		h->err = zbx_dsprintf(h->err, "No value present for control");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	ent = ipmi_control_get_entity(control);
	e_string = ipmi_entity_get_entity_id_string(ent);
	c_type = ipmi_control_get_type_string(control);

	for (n = 0; n < c->num_values; n++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Control values [%s | %s | %d:%d]",
				c->c_name, e_string, n + 1, val[n]);
	}

	sz = sizeof(int) * c->num_values;
	memcpy(c->val, val, sz);

	h->done = 1;
}

static void	got_control_setting(ipmi_control_t *control, int err, void *cb_data)
{
	zbx_ipmi_host_t		*h = cb_data;
	zbx_ipmi_control_t	*c;
	const char		*c_type, *e_string;
	ipmi_entity_t		*ent;

	zabbix_log(LOG_LEVEL_DEBUG, "In got_control_setting()");

	if (err) {
		h->err = zbx_dsprintf(h->err, "Error 0x%x while set control", err);
		h->ret = NETWORK_ERROR;
		h->done = 1;
		return;
	}

	c = get_ipmi_control(h, control);

	if (NULL == c)
	{
		/* this should never happen */
		h->err = zbx_dsprintf(h->err, "Fatal error");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	ent = ipmi_control_get_entity(control);
	e_string = ipmi_entity_get_entity_id_string(ent);
	c_type = ipmi_control_get_type_string(control);

	zabbix_log(LOG_LEVEL_DEBUG, "Set value completed for control %s@[%s]:%d",
			c->c_name, h->ip, h->port);

	h->done = 1;
}

static void	read_ipmi_control(zbx_ipmi_host_t *h, zbx_ipmi_control_t *c)
{
	int			ret;
	struct timeval		tv;

	zabbix_log(LOG_LEVEL_DEBUG, "In read_ipmi_control() %s@[%s]:%d",
			c->c_name, h->ip, h->port);

	if (0 == ipmi_control_is_readable(c->control))
	{
		h->err = zbx_dsprintf(h->err, "Control is not readable.");
		h->ret = NOTSUPPORTED;
		return;
	}

	h->ret = SUCCEED;
	h->done = 0;

	if (0 != (ret = ipmi_control_get_val(c->control, got_control_reading, h)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot read control %s."
				" ipmi_control_get_val() return error: 0x%x",
				c->c_name, ret);
		h->ret = NOTSUPPORTED;
		return;
	}

	tv.tv_sec = 10;
	tv.tv_usec = 0;
	while (0 == h->done)
		os_hnd->perform_one_op(os_hnd, &tv);
}

static void	set_ipmi_control(zbx_ipmi_host_t *h, zbx_ipmi_control_t *c, int value)
{
	int			ret;
	struct timeval		tv;

	zabbix_log(LOG_LEVEL_DEBUG, "In set_ipmi_control() %d => %s@[%s]:%d",
			value, c->c_name, h->ip, h->port);

	if (c->num_values == 0)
	{
		/* this should never happen */
		h->err = zbx_dsprintf(h->err, "No value present for control");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	if (0 == ipmi_control_is_settable(c->control))
	{
		h->err = zbx_dsprintf(h->err, "Control is not settable.");
		h->ret = NOTSUPPORTED;
		return;
	}

	c->val[0] = value;
	h->ret = SUCCEED;
	h->done = 0;

	if (0 != (ret = ipmi_control_set_val(c->control, c->val, got_control_setting, h)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot set control %s."
				" ipmi_control_set_val() return error: 0x%x",
				c->c_name, ret);
		h->ret = NOTSUPPORTED;
		return;
	}

	tv.tv_sec = 10;
	tv.tv_usec = 0;
	while (0 == h->done)
		os_hnd->perform_one_op(os_hnd, &tv);
}

static void	sensor_change(enum ipmi_update_e op, ipmi_entity_t *ent, ipmi_sensor_t *sensor, void *cb_data)
{
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In sensor_change()");

	switch (ipmi_sensor_get_event_reading_type(sensor)) {
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
}

static void	control_change(enum ipmi_update_e op, ipmi_entity_t *ent, ipmi_control_t *control, void *cb_data)
{
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In control_change()");

	if (op == IPMI_ADDED)
	{
		if (NULL == get_ipmi_control(h, control))
			allocate_ipmi_control(h, control);
	}
	else if (op == IPMI_DELETED)
		delete_ipmi_control(h, control);
}

static void	entity_change(enum ipmi_update_e op, ipmi_domain_t *domain, ipmi_entity_t *entity, void *cb_data)
{
	int		ret;
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In entity_change()");

	if (op == IPMI_ADDED)
	{
		if (0 != (ret = ipmi_entity_add_sensor_update_handler(entity, sensor_change, h)))
			zabbix_log(LOG_LEVEL_DEBUG, "ipmi_entity_set_sensor_update_handler() return error: 0x%x", ret);

		if (0 != (ret = ipmi_entity_add_control_update_handler(entity, control_change, h)))
			zabbix_log(LOG_LEVEL_DEBUG, "ipmi_entity_add_control_update_handler() return error: 0x%x", ret);

	}
}

static void	domain_closed(void *cb_data)
{
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In domain_closed() [%s]:%d", h->ip, h->port);

	h->domain_up = 0;
	h->done = 1;
}

static void	setup_done(ipmi_domain_t *domain, int err, unsigned int conn_num, unsigned int port_num,
		int still_connected, void *cb_data)
{
	int		ret;
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In setup_done() [%s]:%d", h->ip, h->port);

	if (err)
	{
		h->err = zbx_dsprintf(h->err, "Cannot connect to IPMI host. Error 0x%x %s",
				err, strerror(err));
		h->ret = NETWORK_ERROR;

		if (0 != (ret = ipmi_domain_close(domain, domain_closed, h)))
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot close IPMI domain. Error 0x%x", ret);
		return;
	}

	if (0 != (ret = ipmi_domain_add_entity_update_handler(domain, entity_change, h)))
		zabbix_log(LOG_LEVEL_DEBUG, "ipmi_domain_add_entity_update_handler() return error: 0x%x", ret);
}

static void	domain_up(ipmi_domain_t *domain, void *cb_data)
{
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In domain_up() [%s]:%d", h->ip, h->port);

	h->domain_up = 1;
	h->done = 1;
}

static void	my_vlog(os_handler_t *handler, const char *format, enum ipmi_log_type_e log_type, va_list ap)
{
	char	type[8], str[MAX_STRING_LEN];

	switch(log_type)
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

	vsnprintf(str, sizeof(str), format, ap);

	zabbix_log(LOG_LEVEL_DEBUG, "%s%s", type, str);
}

int	init_ipmi_handler()
{
	zabbix_log(LOG_LEVEL_DEBUG, "In init_ipmi_handler()");

	if (NULL == (os_hnd = ipmi_posix_setup_os_handler()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Unable to allocate IPMI handler");
		return FAIL;
	}

	os_hnd->set_log_handler(os_hnd, my_vlog);

	ipmi_init(os_hnd);

	return SUCCEED;
}

int	free_ipmi_handler()
{
	struct timeval	tv;
	int		h, s;

	zabbix_log(LOG_LEVEL_DEBUG, "In free_ipmi_handler()");

	tv.tv_sec = 10;
	tv.tv_usec = 0;

	for (h = 0; h < host_count; h ++)
	{
		hosts[h].con->close_connection(hosts[h].con);

		for (s = 0; s < hosts[h].sensor_count; s ++)
			zbx_free(hosts[h].sensors[s].s_name);

		for (s = 0; s < hosts[h].control_count; s ++)
		{
			zbx_free(hosts[h].controls[s].c_name);
			zbx_free(hosts[h].controls[s].val);
		}

		zbx_free(hosts[h].sensors);
		zbx_free(hosts[h].ip);
		zbx_free(hosts[h].username);
		zbx_free(hosts[h].password);
		zbx_free(hosts[h].err);
	}
	zbx_free(hosts);

	os_hnd->free_os_handler(os_hnd);

	return SUCCEED;
}

static zbx_ipmi_host_t	*init_ipmi_host(const char *ip, int port, int authtype, int privilege, const char *username, const char *password)
{
	zbx_ipmi_host_t		*h;
	int			ret;
	ipmi_open_option_t	options[2];
	struct timeval		tv;
	char			*addrs[1], *ports[1];

	zabbix_log(LOG_LEVEL_DEBUG, "In init_ipmi_host([%s]:%d)", ip, port);

	h = get_ipmi_host(ip, port, authtype, privilege, username, password);

	if (NULL != h)
	{
		h->ret = SUCCEED;
		h->done = 0;

		if (1 == h->domain_up)
			return h;
	}
	else
		h = allocate_ipmi_host(ip, port, authtype, privilege, username, password);

	addrs[0] = strdup(h->ip);
	ports[0] = zbx_dsprintf(NULL, "%d", h->port);

	if (0 != (ret = ipmi_ip_setup_con(addrs, ports, 1, h->authtype == -1 ? (unsigned int)(~0) : (unsigned int)h->authtype,
			(unsigned int)h->privilege, h->username, (unsigned int)strlen(h->username),
			h->password, (unsigned int)strlen(h->password), os_hnd, NULL, &h->con)))
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
		h->err = zbx_dsprintf(h->err, "Cannot connect to IPMI host [%s]:%d."
				"ipmi_open_domain() returned error 0x%x %s",
				h->ip, h->port, ret, strerror(ret));
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

	return h;
}

int	get_value_ipmi(DC_ITEM *item, AGENT_RESULT *value)
{
	zbx_ipmi_host_t		*h;
	zbx_ipmi_sensor_t	*s;
	zbx_ipmi_control_t	*c = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_value_ipmi(key:%s)",
			item->key_orig);

	if (NULL == os_hnd)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s", "IPMI handler is not initialised");
		SET_MSG_RESULT(value, strdup("IPMI handler is not initialised"));
		return NOTSUPPORTED;
	}

	h = init_ipmi_host(item->host.ipmi_ip, item->host.ipmi_port, item->host.ipmi_authtype,
			item->host.ipmi_privilege, item->host.ipmi_username, item->host.ipmi_password);

	if (0 == h->domain_up) {
		if (NULL != h->err)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", h->err);
			SET_MSG_RESULT(value, strdup(h->err));
		}
		return h->ret;
	}

	s = get_ipmi_sensor_by_name(h, item->ipmi_sensor);
	if (NULL == s)
		c = get_ipmi_control_by_name(h, item->ipmi_sensor);

	if (NULL == s && NULL == c)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Sensor or control %s@[%s]:%d does not exist",
				item->ipmi_sensor, h->ip, h->port);
		SET_MSG_RESULT(value, strdup("Sensor or control does not exist"));
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
			zabbix_log(LOG_LEVEL_DEBUG, "%s", h->err);
			SET_MSG_RESULT(value, strdup(h->err));
		}
		return h->ret;
	}

	if (NULL != s)
		SET_DBL_RESULT(value, s->value);
	if (NULL != c)
		SET_DBL_RESULT(value, c->val[0]);

	return h->ret;
}

/* function 'parse_ipmi_command' require 'c_name' with size 'ITEM_IPMI_SENSOR_LEN_MAX' */
int	parse_ipmi_command(char *command, char *c_name, int *val)
{
	char	*p, *ipmi_command;

	zabbix_log(LOG_LEVEL_DEBUG, "In parse_ipmi_command(%s)", command);

	if (0 != strncmp(command, "IPMI", 4))
		return FAIL;

	p = command + 4;
	while (*p == ' ' && *p != '\0')
		p++;

	ipmi_command = p;
	*val = 1;

	if (NULL != (p = strchr(p, ' ')))
	{
		*p++ = '\0';
		while (*p == ' ' && *p != '\0')
			p++;

		if (*p == '\0' || 0 == strcasecmp(p, "on"))
			*val = 1;
		else if (0 == strcasecmp(p, "off"))
			*val = 0;
		else if (SUCCEED == is_uint(p))
			*val = atoi(p);
		else
		{
			zabbix_log(LOG_LEVEL_ERR, "IPMI command Value is not supported [%s %s]",
					command, p);
			return FAIL;
		}
	}
	zbx_strlcpy(c_name, ipmi_command, ITEM_IPMI_SENSOR_LEN_MAX);

	return SUCCEED;
}

int	set_ipmi_control_value(DC_ITEM *item, int value, char *error, size_t error_max_len)
{
	zbx_ipmi_host_t		*h;
	zbx_ipmi_control_t	*c;

	zabbix_log(LOG_LEVEL_DEBUG, "In set_ipmi_control_value(control:%s, value:%d)",
			item->ipmi_sensor, value);

	if (NULL == os_hnd)
	{
		zbx_strlcpy(error, "IPMI handler is not initialised", error_max_len);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		return NOTSUPPORTED;
	}

	h = init_ipmi_host(item->host.useip ? item->host.ip : item->host.dns, item->host.ipmi_port,
			item->host.ipmi_authtype, item->host.ipmi_privilege, item->host.ipmi_username, item->host.ipmi_password);

	if (0 == h->domain_up) {
		if (NULL != h->err)
		{
			zbx_strlcpy(error, h->err, error_max_len);
			zabbix_log(LOG_LEVEL_DEBUG, "%s", h->err);
		}
		return h->ret;
	}

	c = get_ipmi_control_by_name(h, item->ipmi_sensor);

	if (NULL == c)
	{
		zbx_snprintf(error, error_max_len, "Control %s@[%s]:%d does not exist",
				item->ipmi_sensor, h->ip, h->port);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", error);
		return NOTSUPPORTED;
	}

	set_ipmi_control(h, c, value);

	if (h->ret != SUCCEED)
	{
		if (NULL != h->err)
		{
			zbx_strlcpy(error, h->err, error_max_len);
			zabbix_log(LOG_LEVEL_DEBUG, "%s", h->err);
		}
	}

	return h->ret;
}
#endif	/* HAVE_OPENIPMI */
