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

typedef struct zbx_ipmi_sensor {
	ipmi_sensor_t		*sensor;
	char			*s_name;
	double			value;
} zbx_ipmi_sensor_t;

typedef struct zbx_ipmi_host {
	char			*ip;
	int			port;
	int			authtype;
	int			priviledge;
	char			*username;
	char			*password;
	zbx_ipmi_sensor_t	*sensors;
	int			sensor_count;
	ipmi_con_t		*con;
	int			domain_up, done;
	char			*err;
	int			ret;
} zbx_ipmi_host_t;

static zbx_ipmi_host_t	*hosts = NULL;
static int		host_count = 0;
static os_handler_t	*os_hnd;

static zbx_ipmi_host_t	*get_ipmi_host(const char *ip, const int port, int authtype, int priviledge,
		const char *username, const char *password)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_ipmi_host([%s]:%d)", ip, port);

	for (i = 0; i < host_count; i ++)
	{
		if (0 == strcmp(ip, hosts[i].ip) && port == hosts[i].port && authtype == hosts[i].authtype
				&& priviledge == hosts[i].priviledge && 0 == strcmp(username, hosts[i].username)
				&& 0 == strcmp(password, hosts[i].password))
			return &hosts[i];
	}

	return NULL;
}

static zbx_ipmi_host_t  *allocate_ipmi_host(const char *ip, int port, int authtype, int priviledge,
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
	h->priviledge = priviledge;
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

static void	got_thresh_reading(ipmi_sensor_t *sensor, int err, enum ipmi_value_present_e value_present,
		unsigned int raw_value, double val, ipmi_states_t *states, void *cb_data)
{
	int			id;
	const char		*e_string, *s_type_string, *s_reading_type_string;
	ipmi_entity_t		*ent;
	const char		*percent = "", *base, *mod_use = "", *modifier = "", *rate;
	zbx_ipmi_host_t 	*h = cb_data;
	zbx_ipmi_sensor_t	*s;

	s = get_ipmi_sensor(h, sensor);

	if (NULL == s)
	{
		/* this should never happen */
		h->err = zbx_dsprintf(h->err, "Fatal error");
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	if (err) {
		h->err = zbx_dsprintf(h->err, "Error 0x%x while read threshold sensor %s@[%s]:%d",
				s->s_name, h->ip, h->port);
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	switch (value_present)
	{
		case IPMI_NO_VALUES_PRESENT:
		case IPMI_RAW_VALUE_PRESENT:
			h->err = zbx_dsprintf(h->err, "No value present for threshold sensor %s@[%s]:%d",
					s->s_name, h->ip, h->port);
			h->ret = NOTSUPPORTED;
			break;
		case IPMI_BOTH_VALUES_PRESENT:
			s->value = val;

			/* next lines only for debug logging */
			ent = ipmi_sensor_get_entity(sensor);
			id = ipmi_entity_get_entity_id(ent);
			e_string = ipmi_get_entity_id_string(id);
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
	zbx_ipmi_host_t 	*h = cb_data;
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

	h->done = 0;
	tv.tv_sec = 10;
	tv.tv_usec = 0;
	while (0 == h->done)
		os_hnd->perform_one_op(os_hnd, &tv);
}

static void	sensor_change(enum ipmi_update_e op, ipmi_entity_t *ent, ipmi_sensor_t *sensor, void *cb_data)
{
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In sensor_change()");

	if (op == IPMI_ADDED) {
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
				if (NULL == get_ipmi_sensor(h, sensor))
					allocate_ipmi_sensor(h, sensor);
				break;
			case IPMI_EVENT_READING_TYPE_SENSOR_SPECIFIC:
				;	/* nothing */
		}
	}
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
	}
}

static void domain_closed(void *cb_data)
{
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In domain_closed() [%s]:%d", h->ip, h->port);

	h->done = 1;
}

static void	setup_done(ipmi_domain_t *domain, int err, unsigned int conn_num, unsigned int port_num,
		int still_connected, void *cb_data)
{
	int		ret;
	zbx_ipmi_host_t *h = cb_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In setup_done() [%s]:%d err:%d", h->ip, h->port, err);

	if (err)
	{
		h->err = zbx_dsprintf(h->err, "Cannot connect to IPMI host [%s]:%d. Error 0x%x %s",
				h->ip, h->port, err, strerror(err));
		h->ret = NETWORK_ERROR;

		if (0 != (ret = ipmi_domain_close(domain, domain_closed, h)))
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot close IPMI domain. Error 0x%x", ret);
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
		case IPMI_LOG_INFO	  : zbx_strlcpy(type, "INFO: ", sizeof(type)); break;
		case IPMI_LOG_WARNING	  : zbx_strlcpy(type, "WARN: ", sizeof(type)); break;
		case IPMI_LOG_SEVERE	  : zbx_strlcpy(type, "SEVR: ", sizeof(type)); break;
		case IPMI_LOG_FATAL	  : zbx_strlcpy(type, "FATL: ", sizeof(type)); break;
		case IPMI_LOG_ERR_INFO	  : zbx_strlcpy(type, "EINF: ", sizeof(type)); break;
		case IPMI_LOG_DEBUG_START :
		case IPMI_LOG_DEBUG	  : zbx_strlcpy(type, "DEBG: ", sizeof(type)); break;
		case IPMI_LOG_DEBUG_CONT  :
		case IPMI_LOG_DEBUG_END	  : *type = '\0'; break;
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

static zbx_ipmi_host_t	*init_ipmi_host(const char *ip, int port, int authtype, int priviledge, const char *username, const char *password)
{
	zbx_ipmi_host_t		*h;
	int			ret;
	ipmi_open_option_t	options[2];
	struct timeval		tv;
	char			*addrs[1], *ports[1];

	zabbix_log(LOG_LEVEL_DEBUG, "In init_ipmi_host([%s]:%d)", ip, port);

	h = get_ipmi_host(ip, port, authtype, priviledge, username, password);

	if (NULL != h)
	{
		h->ret = SUCCEED;

		if (1 == h->domain_up)
			return h;
	}
	else
		h = allocate_ipmi_host(ip, port, authtype, priviledge, username, password);

	addrs[0] = strdup(h->ip);
	ports[0] = zbx_dsprintf(NULL, "%d", h->port);

	if (0 != (ret = ipmi_ip_setup_con(addrs, ports, 1, h->authtype, h->priviledge, h->username,
			strlen(h->username), h->password, strlen(h->password), os_hnd, NULL, &h->con)))
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

	h->done = 0;
	tv.tv_sec = 10;
	tv.tv_usec = 0;
	while (0 == h->done)
		os_hnd->perform_one_op(os_hnd, &tv);
out:
	zbx_free(addrs[0]);
	zbx_free(ports[0]);

	return h;
}

int	get_value_ipmi(DB_ITEM *item, AGENT_RESULT *value)
{
	zbx_ipmi_host_t		*h;
	zbx_ipmi_sensor_t	*s;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_value_ipmi(key:%s)",
			item->key);

	if (NULL == os_hnd)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s", "IPMI handler is not initialized");
		SET_MSG_RESULT(value, strdup("IPMI handler is not initialized"));
		return NOTSUPPORTED;
	}

	h = init_ipmi_host(item->useip ? item->host_ip : item->host_dns, item->ipmi_port,
			item->ipmi_authtype, item->ipmi_privilege, item->ipmi_username, item->ipmi_password);

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
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Sensor %s@[%s]:%d does not exists",
				item->ipmi_sensor, h->ip, h->port);
		SET_MSG_RESULT(value, strdup("Sensor does not exists"));
		return NOTSUPPORTED;
	}

	read_ipmi_sensor(h, s);

	if (h->ret != SUCCEED)
	{
		if (NULL != h->err)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s", h->err);
			SET_MSG_RESULT(value, strdup(h->err));
		}
		return h->ret;
	}

	SET_DBL_RESULT(value, s->value);

	return h->ret;
}
#endif	/* HAVE_OPENIPMI */
