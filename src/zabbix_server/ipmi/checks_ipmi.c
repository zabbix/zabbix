/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "common.h"

#ifdef HAVE_OPENIPMI

#include "checks_ipmi.h"

/* Theoretically it should be enough max 16 bytes for sensor ID and terminating '\0' (see SDR record format in IPMI */
/* v2 spec). OpenIPMI author Corey Minyard explained at	*/
/* www.mail-archive.com/openipmi-developer@lists.sourceforge.net/msg02013.html: */
/* "...Since you can use BCD and the field is 16 bytes max, you can get up to 32 bytes in the ID string. Adding the */
/* sensor sharing and that's another three bytes (I believe 142 is the maximum number you can get), so 35 bytes is  */
/* the maximum, I believe." */
#define IPMI_SENSOR_ID_SZ	36

/* delete inactive hosts after this period */
#define INACTIVE_HOST_LIMIT	3 * SEC_PER_HOUR

#define IPMI_THRESHOLDS_NUM	6
#define MAX_DISCRETE_STATES	15

#define ZBX_IPMI_TAG_ID				"id"
#define ZBX_IPMI_TAG_NAME			"name"
#define ZBX_IPMI_TAG_SENSOR			"sensor"
#define ZBX_IPMI_TAG_READING			"reading"
#define ZBX_IPMI_TAG_STATE			"state"
#define ZBX_IPMI_TAG_TYPE			"type"
#define ZBX_IPMI_TAG_TEXT			"text"

#define ZBX_IPMI_TAG_UNITS			"units"
#define ZBX_IPMI_TAG_VALUE			"value"
#define ZBX_IPMI_TAG_THRESHOLD			"threshold"
#define ZBX_IPMI_TAG_LOWER			"lower"
#define ZBX_IPMI_TAG_UPPER			"upper"
#define ZBX_IPMI_TAG_NON_CRIT			"non_crit"
#define ZBX_IPMI_TAG_CRIT			"crit"
#define ZBX_IPMI_TAG_NON_RECOVER		"non_recover"

#define ZBX_IPMI_THRESHOLD_STATUS_DISABLED	0
#define ZBX_IPMI_THRESHOLD_STATUS_ENABLED	1

#include "log.h"

#include <OpenIPMI/ipmiif.h>
#include <OpenIPMI/ipmi_posix.h>
#include <OpenIPMI/ipmi_lan.h>
#include <OpenIPMI/ipmi_auth.h>

#define RETURN_IF_CB_DATA_NULL(x, y)							\
	if (NULL == (x))								\
	{										\
		zabbix_log(LOG_LEVEL_WARNING, "%s() called with cb_data:NULL", (y));	\
		return;									\
	}

typedef union
{
	double		threshold;
	zbx_uint64_t	discrete;
}
zbx_ipmi_sensor_value_t;

typedef struct
{
	int	status;	/* Is this threshold enabled? */
	double	val;
}
zbx_ipmi_sensor_threshold_t;

typedef struct
{
	ipmi_sensor_t			*sensor;
	char				id[IPMI_SENSOR_ID_SZ];
	enum ipmi_str_type_e		id_type;	/* For sensors IPMI specifications mention Unicode, BCD plus, */
							/* 6-bit ASCII packed, 8-bit ASCII+Latin1.  */
	int				id_sz;		/* "id" value length in bytes */
	zbx_ipmi_sensor_value_t		value;
	int				reading_type;	/* "Event/Reading Type Code", e.g. Threshold, */
							/* Discrete, 'digital' Discrete. */
	int				type;		/* "Sensor Type Code", e.g. Temperature, Voltage, */
							/* Current, Fan, Physical Security (Chassis Intrusion), etc. */
	char				*full_name;
	int				state;
	zbx_ipmi_sensor_threshold_t	thresholds[IPMI_THRESHOLDS_NUM];
}
zbx_ipmi_sensor_t;

typedef struct
{
	ipmi_control_t		*control;
	char			*c_name;
	int			num_values;
	int			*val;
	char			*full_name;
}
zbx_ipmi_control_t;

typedef struct zbx_ipmi_host
{
	char			*ip;
	int			port;
	int			authtype;
	int			privilege;
	int			ret;
	char			*username;
	char			*password;
	zbx_ipmi_sensor_t	*sensors;
	zbx_ipmi_control_t	*controls;
	int			sensor_count;
	int			control_count;
	ipmi_con_t		*con;
	int			domain_up;
	int			done;
	time_t			lastaccess;	/* Time of last access attempt. Used to detect and delete inactive */
						/* (disabled) IPMI hosts from OpenIPMI to stop polling them. */
	unsigned int		domain_nr;	/* Domain number. It is converted to text string and used as */
						/* domain name. */
	char			*err;
	struct zbx_ipmi_host	*next;
}
zbx_ipmi_host_t;

static unsigned int	domain_nr = 0;		/* for making a sequence of domain names "0", "1", "2", ... */
static zbx_ipmi_host_t	*hosts = NULL;		/* head of single-linked list of monitored hosts */
static os_handler_t	*os_hnd;

static char	*zbx_sensor_id_to_str(char *str, size_t str_sz, const char *id, enum ipmi_str_type_e id_type, int id_sz)
{
	/* minimum size of 'str' buffer, str_sz, is 35 bytes to avoid truncation */
	int	i;
	char	*p = str;
	size_t	id_len;

	if (0 == id_sz)		/* id is meaningful only if length > 0 (see SDR record format in IPMI v2 spec) */
	{
		*str = '\0';
		return str;
	}

	if (IPMI_SENSOR_ID_SZ < id_sz)
	{
		zbx_strlcpy(str, "ILLEGAL-SENSOR-ID-SIZE", str_sz);
		THIS_SHOULD_NEVER_HAPPEN;
		return str;
	}

	switch (id_type)
	{
		case IPMI_ASCII_STR:
		case IPMI_UNICODE_STR:
			id_len = str_sz > (size_t)id_sz ? (size_t)id_sz : str_sz - 1;
			memcpy(str, id, id_len);
			*(str + id_len) = '\0';
			break;
		case IPMI_BINARY_STR:
			/* "BCD Plus" or "6-bit ASCII packed" encoding - print it as a hex string. */

			*p++ = '0';	/* prefix to distinguish from ASCII/Unicode strings */
			*p++ = 'x';
			for (i = 0; i < id_sz; i++, p += 2)
			{
				zbx_snprintf(p, str_sz - (size_t)(2 + i + i), "%02x",
						(unsigned int)(unsigned char)*(id + i));
			}
			*p = '\0';
			break;
		default:
			zbx_strlcpy(str, "ILLEGAL-SENSOR-ID-TYPE", str_sz);
			THIS_SHOULD_NEVER_HAPPEN;
	}
	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Find element in the global list 'hosts' using parameters as       *
 *          search criteria                                                   *
 *                                                                            *
 * Return value: pointer to list element with host data                       *
 *               NULL if not found                                            *
 *                                                                            *
 ******************************************************************************/
static zbx_ipmi_host_t	*zbx_get_ipmi_host(const char *ip, const int port, int authtype, int privilege,
		const char *username, const char *password)
{
	zbx_ipmi_host_t	*h;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d'", __func__, ip, port);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)h);

	return h;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a new element in the global list 'hosts'                   *
 *                                                                            *
 * Return value: pointer to the new list element with host data               *
 *                                                                            *
 ******************************************************************************/
static zbx_ipmi_host_t	*zbx_allocate_ipmi_host(const char *ip, int port, int authtype, int privilege,
		const char *username, const char *password)
{
	zbx_ipmi_host_t	*h;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d'", __func__, ip, port);

	h = (zbx_ipmi_host_t *)zbx_malloc(NULL, sizeof(zbx_ipmi_host_t));

	memset(h, 0, sizeof(zbx_ipmi_host_t));

	h->ip = strdup(ip);
	h->port = port;
	h->authtype = authtype;
	h->privilege = privilege;
	h->username = strdup(username);
	h->password = strdup(password);
	h->domain_nr = domain_nr++;

	h->next = hosts;
	hosts = h;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)h);

	return h;
}

static zbx_ipmi_sensor_t	*zbx_get_ipmi_sensor(const zbx_ipmi_host_t *h, const ipmi_sensor_t *sensor)
{
	int			i;
	zbx_ipmi_sensor_t	*s = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p psensor:%p", __func__, (const void *)h,
			(const void *)sensor);

	for (i = 0; i < h->sensor_count; i++)
	{
		if (h->sensors[i].sensor == sensor)
		{
			s = &h->sensors[i];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)s);

	return s;
}

static zbx_ipmi_sensor_t	*zbx_get_ipmi_sensor_by_id(const zbx_ipmi_host_t *h, const char *id)
{
	int			i;
	zbx_ipmi_sensor_t	*s = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sensor:'%s@[%s]:%d'", __func__, id, h->ip, h->port);

	for (i = 0; i < h->sensor_count; i++)
	{
		if (0 == strcmp(h->sensors[i].id, id))
		{
			/* Some devices present a sensor as both a threshold sensor and a discrete sensor. We work */
			/* around this by preferring the threshold sensor in such case, as it is most widely used. */

			s = &h->sensors[i];

			if (IPMI_EVENT_READING_TYPE_THRESHOLD == s->reading_type)
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)s);

	return s;
}

static zbx_ipmi_sensor_t	*zbx_get_ipmi_sensor_by_full_name(const zbx_ipmi_host_t *h, const char *full_name)
{
	int			i;
	zbx_ipmi_sensor_t	*s = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sensor:'%s@[%s]:%d", __func__, full_name, h->ip, h->port);

	for (i = 0; i < h->sensor_count; i++)
	{
		if (0 == strcmp(h->sensors[i].full_name, full_name))
		{
			s = &h->sensors[i];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)s);

	return s;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Check if an item name starts from domain name and find the domain *
 *          name length                                                       *
 *                                                                            *
 * Parameters: h         - [IN] ipmi host                                     *
 *             full_name - [IN] item name                                     *
 *                                                                            *
 * Return value: 0 or offset for skipping the domain name                     *
 *                                                                            *
 ******************************************************************************/
static size_t	get_domain_offset(const zbx_ipmi_host_t *h, const char *full_name)
{
	char	domain_name[IPMI_DOMAIN_NAME_LEN];
	size_t	offset;

	zbx_snprintf(domain_name, sizeof(domain_name), "%u", h->domain_nr);
	offset = strlen(domain_name);

	if (offset >= strlen(full_name) || 0 != strncmp(domain_name, full_name, offset))
		offset = 0;

	return offset;
}
/******************************************************************************
 *                                                                            *
 * Purpose:  Converts sensor id to printable string and return id_type        *
 *                                                                            *
 * Parameters: sensor    - [IN] ipmi sensor                                   *
 *             id        - [OUT] sensor id                                    *
 *             sz        - [IN] sensor id buffer length                       *
 *             id_sz     - [OUT] sensor id length                             *
 *             id_type   - [OUT] type of sensor id                            *
 *             id_str    - [OUT] sensor id string                             *
 *             id_str_sz - [IN] sensor id string buffer length                *
 *                                                                            *
 * Return value: pointer to sensor id string                                  *
 *                                                                            *
 ******************************************************************************/
static char *zbx_get_sensor_id(ipmi_sensor_t *sensor, char *id, int sz, int *id_sz, enum ipmi_str_type_e *id_type,
		char *id_str, int id_str_sz )
{
	*id_sz = ipmi_sensor_get_id_length(sensor);
	memset(id, 0, (size_t)sz);
	ipmi_sensor_get_id(sensor, id, sz);
	*id_type = ipmi_sensor_get_id_type(sensor);
	return zbx_sensor_id_to_str(id_str, (size_t)id_str_sz, id, *id_type, *id_sz);
}

static zbx_ipmi_sensor_t	*zbx_allocate_ipmi_sensor(zbx_ipmi_host_t *h, ipmi_sensor_t *sensor)
{
	char			id_str[2 * IPMI_SENSOR_ID_SZ + 1];
	zbx_ipmi_sensor_t	*s;
	char			id[IPMI_SENSOR_ID_SZ];
	enum ipmi_str_type_e	id_type;
	int			id_sz;
	size_t			sz;
	char			full_name[IPMI_SENSOR_NAME_LEN];
	int 			i;

	zbx_get_sensor_id(sensor, id, sizeof(id), &id_sz, &id_type, id_str, sizeof(id_str));
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sensor:'%s@[%s]:%d'", __func__, id_str, h->ip, h->port);

	h->sensor_count++;
	sz = (size_t)h->sensor_count * sizeof(zbx_ipmi_sensor_t);

	if (NULL == h->sensors)
		h->sensors = (zbx_ipmi_sensor_t *)zbx_malloc(h->sensors, sz);
	else
		h->sensors = (zbx_ipmi_sensor_t *)zbx_realloc(h->sensors, sz);

	s = &h->sensors[h->sensor_count - 1];
	s->sensor = sensor;
	memcpy(s->id, id, sizeof(id));
	s->id_type = id_type;
	s->id_sz = id_sz;
	memset(&s->value, 0, sizeof(s->value));
	s->reading_type = ipmi_sensor_get_event_reading_type(sensor);
	s->type = ipmi_sensor_get_sensor_type(sensor);

	ipmi_sensor_get_name(s->sensor, full_name, sizeof(full_name));
	s->full_name = zbx_strdup(NULL, full_name + get_domain_offset(h, full_name));
	for (i = IPMI_LOWER_NON_CRITICAL; i <= IPMI_UPPER_NON_RECOVERABLE; i++)
		s->thresholds[i].status = ZBX_IPMI_THRESHOLD_STATUS_DISABLED;

	zabbix_log(LOG_LEVEL_DEBUG, "Added sensor: host:'%s:%d' id_type:%d id_sz:%d id:'%s' reading_type:0x%x "
			"('%s') type:0x%x ('%s') domain:'%u' name:'%s'", h->ip, h->port, (int)s->id_type, s->id_sz,
			zbx_sensor_id_to_str(id_str, sizeof(id_str), s->id, s->id_type, s->id_sz),
			(unsigned int)s->reading_type, ipmi_sensor_get_event_reading_type_string(s->sensor),
			(unsigned int)s->type, ipmi_sensor_get_sensor_type_string(s->sensor), h->domain_nr,
			s->full_name);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)s);

	return s;
}

static void	zbx_delete_ipmi_sensor(zbx_ipmi_host_t *h, const ipmi_sensor_t *sensor)
{
	char	id_str[2 * IPMI_SENSOR_ID_SZ + 1];
	int	i;
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p psensor:%p", __func__, (void *)h, (const void *)sensor);

	for (i = 0; i < h->sensor_count; i++)
	{
		if (h->sensors[i].sensor != sensor)
			continue;

		sz = sizeof(zbx_ipmi_sensor_t);

		zabbix_log(LOG_LEVEL_DEBUG, "sensor '%s@[%s]:%d' deleted",
				zbx_sensor_id_to_str(id_str, sizeof(id_str), h->sensors[i].id, h->sensors[i].id_type,
				h->sensors[i].id_sz), h->ip, h->port);

		zbx_free(h->sensors[i].full_name);

		h->sensor_count--;
		if (h->sensor_count != i)
			memmove(&h->sensors[i], &h->sensors[i + 1], sz * (size_t)(h->sensor_count - i));
		h->sensors = (zbx_ipmi_sensor_t *)zbx_realloc(h->sensors, sz * (size_t)h->sensor_count);

		break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_ipmi_control_t	*zbx_get_ipmi_control(const zbx_ipmi_host_t *h, const ipmi_control_t *control)
{
	int			i;
	zbx_ipmi_control_t	*c = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p pcontrol:%p", __func__, (const void *)h, (const void *)control);

	for (i = 0; i < h->control_count; i++)
	{
		if (h->controls[i].control == control)
		{
			c = &h->controls[i];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)c);

	return c;
}

static zbx_ipmi_control_t	*zbx_get_ipmi_control_by_name(const zbx_ipmi_host_t *h, const char *c_name)
{
	int			i;
	zbx_ipmi_control_t	*c = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() control: %s@[%s]:%d", __func__, c_name, h->ip, h->port);

	for (i = 0; i < h->control_count; i++)
	{
		if (0 == strcmp(h->controls[i].c_name, c_name))
		{
			c = &h->controls[i];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)c);

	return c;
}

static zbx_ipmi_control_t	*zbx_get_ipmi_control_by_full_name(const zbx_ipmi_host_t *h, const char *full_name)
{
	int			i;
	zbx_ipmi_control_t	*c = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() control:'%s@[%s]:%d", __func__, full_name, h->ip, h->port);

	for (i = 0; i < h->control_count; i++)
	{
		if (0 == strcmp(h->controls[i].full_name, full_name))
		{
			c = &h->controls[i];
			break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)c);

	return c;
}

static zbx_ipmi_control_t	*zbx_allocate_ipmi_control(zbx_ipmi_host_t *h, ipmi_control_t *control)
{
	size_t			sz, dm_sz;
	zbx_ipmi_control_t	*c;
	char			*c_name = NULL;
	char			full_name[IPMI_SENSOR_NAME_LEN];

	sz = (size_t)ipmi_control_get_id_length(control);
	c_name = (char *)zbx_malloc(c_name, sz + 1);
	ipmi_control_get_id(control, c_name, sz);

	ipmi_control_get_name(control, full_name, sizeof(full_name));
	dm_sz = get_domain_offset(h, full_name);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() Added control: host'%s:%d' id:'%s' domain:'%u' name:'%s'",
			__func__, h->ip, h->port, c_name, h->domain_nr, full_name + dm_sz);

	h->control_count++;
	sz = (size_t)h->control_count * sizeof(zbx_ipmi_control_t);

	if (NULL == h->controls)
		h->controls = (zbx_ipmi_control_t *)zbx_malloc(h->controls, sz);
	else
		h->controls = (zbx_ipmi_control_t *)zbx_realloc(h->controls, sz);

	c = &h->controls[h->control_count - 1];

	memset(c, 0, sizeof(zbx_ipmi_control_t));

	c->control = control;
	c->c_name = c_name;
	c->num_values = ipmi_control_get_num_vals(control);
	sz = sizeof(int) * (size_t)c->num_values;
	c->val = (int *)zbx_malloc(c->val, sz);
	memset(c->val, 0, sz);
	c->full_name = zbx_strdup(NULL, full_name + dm_sz);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)c);

	return c;
}

static void	zbx_delete_ipmi_control(zbx_ipmi_host_t *h, const ipmi_control_t *control)
{
	int	i;
	size_t	sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p pcontrol:%p", __func__, (void *)h, (const void *)control);

	for (i = 0; i < h->control_count; i++)
	{
		if (h->controls[i].control != control)
			continue;

		sz = sizeof(zbx_ipmi_control_t);

		zabbix_log(LOG_LEVEL_DEBUG, "control '%s@[%s]:%d' deleted", h->controls[i].c_name, h->ip, h->port);

		zbx_free(h->controls[i].c_name);
		zbx_free(h->controls[i].val);
		zbx_free(h->controls[i].full_name);

		h->control_count--;
		if (h->control_count != i)
			memmove(&h->controls[i], &h->controls[i + 1], sz * (size_t)(h->control_count - i));
		h->controls = (zbx_ipmi_control_t *)zbx_realloc(h->controls, sz * (size_t)h->control_count);

		break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/* callback function invoked from OpenIPMI */
static void	zbx_got_thresholds_cb(ipmi_sensor_t *sensor, int err, ipmi_thresholds_t *th, void *cb_data)
{
	zbx_ipmi_host_t		*h = (zbx_ipmi_host_t *)cb_data;
	zbx_ipmi_sensor_t	*s;
	enum ipmi_thresh_e 	i;
	int			val, ret;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d' err:%d th:%p", __func__, h->ip, h->port, err, (void *)th);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() fail: %s", __func__, zbx_strerror(err));

		h->err = zbx_dsprintf(h->err, "error 0x%x while getting thresholds for sensor", (unsigned int)err);
		h->ret = NOTSUPPORTED;
		goto out;
	}

	if (NULL == (s = zbx_get_ipmi_sensor(h, sensor)))
	{
		char			id[IPMI_SENSOR_ID_SZ];
		char			id_str[2 * IPMI_SENSOR_ID_SZ + 1];
		int			id_sz;
		enum ipmi_str_type_e	id_type;

		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_dsprintf(h->err, "fatal error: host:'[%s]:%d' sensor %s", h->ip, h->port,
				zbx_get_sensor_id(sensor, id, sizeof(id), &id_sz, &id_type,  id_str, sizeof(id_str)));

		h->ret = NOTSUPPORTED;
		goto out;
	}

	for (i = IPMI_LOWER_NON_CRITICAL; i <= IPMI_UPPER_NON_RECOVERABLE; i++)
	{
		double	thr;

		s->thresholds[i].status = ZBX_IPMI_THRESHOLD_STATUS_DISABLED;
		ret = ipmi_sensor_threshold_readable(sensor, i, &val);
		if (0 != ret || 0 == val)
			continue;

		if (0 == ipmi_threshold_get(th, i, &thr))
		{
			s->thresholds[i].status = ZBX_IPMI_THRESHOLD_STATUS_ENABLED;
			s->thresholds[i].val = thr;
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Threshold %s could not be fetched", ipmi_get_threshold_string(i));
	}
out:
	h->done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}

/*get sensor units full string*/
static char *zbx_get_ipmi_units(ipmi_sensor_t *sensor)
{
	const char	*base, *mod_use = "", *modifier = "", *rate;

	if (0 != ipmi_sensor_get_percentage(sensor))
		return zbx_dsprintf(NULL, "%%");

	base = ipmi_sensor_get_base_unit_string(sensor);

	switch (ipmi_sensor_get_modifier_unit_use(sensor))
	{
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
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}
	rate = ipmi_sensor_get_rate_unit_string(sensor);

	return zbx_dsprintf(NULL, "%s%s%s%s", base, mod_use, modifier, rate);
}

/* callback function invoked from OpenIPMI */
static void	zbx_got_thresh_reading_cb(ipmi_sensor_t *sensor, int err, enum ipmi_value_present_e value_present,
		unsigned int raw_value, double val, ipmi_states_t *states, void *cb_data)
{
	char			id_str[2 * IPMI_SENSOR_ID_SZ + 1];
	zbx_ipmi_host_t		*h = (zbx_ipmi_host_t *)cb_data;
	zbx_ipmi_sensor_t	*s;

	ZBX_UNUSED(raw_value);

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() fail: %s", __func__, zbx_strerror(err));

		h->err = zbx_dsprintf(h->err, "error 0x%x while reading threshold sensor", (unsigned int)err);
		h->ret = NOTSUPPORTED;
		goto out;
	}

	if (0 == ipmi_is_sensor_scanning_enabled(states) || 0 != ipmi_is_initial_update_in_progress(states))
	{
		h->err = zbx_strdup(h->err, "sensor data is not available");
		h->ret = NOTSUPPORTED;
		goto out;
	}

	s = zbx_get_ipmi_sensor(h, sensor);

	if (NULL == s)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_strdup(h->err, "fatal error");
		h->ret = NOTSUPPORTED;
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
			s->value.threshold = val;

			if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
			{
				char		*units;
				const char	*e_string, *s_type_string, *s_reading_type_string;

				e_string = ipmi_entity_get_entity_id_string(ipmi_sensor_get_entity(sensor));
				s_type_string = ipmi_sensor_get_sensor_type_string(sensor);
				s_reading_type_string = ipmi_sensor_get_event_reading_type_string(sensor);

				units = zbx_get_ipmi_units(sensor);

				zabbix_log(LOG_LEVEL_DEBUG, "Value [%s | %s | %s | %s | " ZBX_FS_DBL " %s]",
						zbx_sensor_id_to_str(id_str, sizeof(id_str), s->id, s->id_type,
						s->id_sz), e_string, s_type_string, s_reading_type_string, val, units);

				zbx_free(units);
			}

			s->state = 0;
			if (IPMI_THRESHOLD_ACCESS_SUPPORT_NONE != ipmi_sensor_get_threshold_access(sensor))
			{
				int	value, ret;
				int	i;

				for (i = IPMI_LOWER_NON_CRITICAL; i <= IPMI_UPPER_NON_RECOVERABLE; i++)
				{
					ret = ipmi_sensor_threshold_reading_supported(sensor, i, &value);
					if (0 != ret || 0 == value)
						continue;
					if (0 != ipmi_is_threshold_out_of_range(states, i))
						s->state |= 1 << i;
				}
			}

			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}
out:
	h->done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}

/* callback function invoked from OpenIPMI */
static void	zbx_got_discrete_states_cb(ipmi_sensor_t *sensor, int err, ipmi_states_t *states, void *cb_data)
{
	char			id_str[2 * IPMI_SENSOR_ID_SZ + 1];
	int			id, i, val, ret, is_state_set;
	zbx_ipmi_host_t		*h = (zbx_ipmi_host_t *)cb_data;
	zbx_ipmi_sensor_t	*s;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == ipmi_is_sensor_scanning_enabled(states) || 0 != ipmi_is_initial_update_in_progress(states))
	{
		h->err = zbx_strdup(h->err, "sensor data is not available");
		h->ret = NOTSUPPORTED;
		goto out;
	}

	s = zbx_get_ipmi_sensor(h, sensor);

	if (NULL == s)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_strdup(h->err, "fatal error");
		h->ret = NOTSUPPORTED;
		goto out;
	}

	if (0 != err)
	{
		h->err = zbx_dsprintf(h->err, "error 0x%x while reading a discrete sensor %s@[%s]:%d",
				(unsigned int)err,
				zbx_sensor_id_to_str(id_str, sizeof(id_str), s->id, s->id_type, s->id_sz), h->ip,
				h->port);
		h->ret = NOTSUPPORTED;
		goto out;
	}

	id = ipmi_entity_get_entity_id(ipmi_sensor_get_entity(sensor));

	/* Discrete values are 16-bit. We're storing them into a 64-bit uint. */

	s->value.discrete = 0;
	for (i = 0; i < MAX_DISCRETE_STATES; i++)
	{
		ret = ipmi_sensor_discrete_event_readable(sensor, i, &val);
		if (0 != ret || 0 == val)
			continue;

		is_state_set = ipmi_is_state_set(states, i);

		zabbix_log(LOG_LEVEL_DEBUG, "State [%s | %s | %s | %s | state %d value is %d]",
				zbx_sensor_id_to_str(id_str, sizeof(id_str), s->id, s->id_type, s->id_sz),
				ipmi_get_entity_id_string(id), ipmi_sensor_get_sensor_type_string(sensor),
				ipmi_sensor_get_event_reading_type_string(sensor), i, is_state_set);

		if (0 != is_state_set)
			s->value.discrete |= 1 << i;
	}
out:
	h->done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}

/******************************************************************************
 *                                                                            *
 * Purpose: Pass control to OpenIPMI library to process events                *
 *                                                                            *
 * Return value: SUCCEED - no errors                                          *
 *               FAIL - an error occurred while processing events             *
 *                                                                            *
 ******************************************************************************/
static int	zbx_perform_openipmi_ops(zbx_ipmi_host_t *h, const char *func_name)
{
	struct timeval	tv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d' phost:%p from %s()", __func__, h->ip, h->port,
			(void *)h, func_name);

	tv.tv_sec = 10;		/* set timeout for one operation */
	tv.tv_usec = 0;

	while (0 == h->done)
	{
		int	res;

		if (0 == (res = os_hnd->perform_one_op(os_hnd, &tv)))
			continue;

		zabbix_log(LOG_LEVEL_DEBUG, "End %s() from %s(): error: %s", __func__, func_name, zbx_strerror(res));

		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End %s() from %s()", __func__, func_name);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Pass control to OpenIPMI library to process all internal events   *
 *                                                                            *
 * Parameters: timeout - [IN] timeout (in seconds) for processing single      *
 *                            operation; processing multiple operations may   *
 *                            take more time                                  *
 *                                                                            *
 *****************************************************************************/
void	zbx_perform_all_openipmi_ops(int timeout)
{
	/* Before OpenIPMI v2.0.26, perform_one_op() did not modify timeout argument.   */
	/* Starting with OpenIPMI v2.0.26, perform_one_op() updates timeout argument.   */
	/* To make sure that the loop works consistently with all versions of OpenIPMI, */
	/* initialize timeout argument for perform_one_op() inside the loop.            */

	for (;;)
	{
		struct timeval	tv;
		double		start_time;
		int		res;

		tv.tv_sec = timeout;
		tv.tv_usec = 0;

		start_time = zbx_time();

		/* perform_one_op() returns 0 on success, errno on failure (timeout means success) */
		if (0 != (res = os_hnd->perform_one_op(os_hnd, &tv)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "IPMI error: %s", zbx_strerror(res));
			break;
		}

		/* If execution of perform_one_op() took more time than specified in timeout argument, assume that  */
		/* perform_one_op() timed out and break the loop.                                                   */
		/* If it took less than specified in timeout argument, assume that some operation was performed and */
		/* there may be more operations to be performed.                                                    */
		if (zbx_time() - start_time >= timeout)
		{
			break;
		}
	}
}

static void	zbx_read_ipmi_sensor(zbx_ipmi_host_t *h, const zbx_ipmi_sensor_t *s)
{
	char		id_str[2 * IPMI_SENSOR_ID_SZ + 1];
	int		ret;
	const char	*s_reading_type_string;

	/* copy sensor details at start - it can go away and we won't be able to make an error message */
	zbx_sensor_id_to_str(id_str, sizeof(id_str), s->id, s->id_type, s->id_sz);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sensor:'%s@[%s]:%d'", __func__, id_str, h->ip, h->port);

	h->ret = SUCCEED;
	h->done = 0;

	switch (s->reading_type)
	{
		case IPMI_EVENT_READING_TYPE_THRESHOLD:
			if (0 != (ret = ipmi_sensor_get_reading(s->sensor, zbx_got_thresh_reading_cb, h)))
			{
				/* do not use pointer to sensor here - the sensor may have disappeared during */
				/* ipmi_sensor_get_reading(), as domain might be closed due to communication failure */
				h->err = zbx_dsprintf(h->err, "Cannot read sensor \"%s\"."
						" ipmi_sensor_get_reading() return error: 0x%x", id_str,
						(unsigned int)ret);
				h->ret = NOTSUPPORTED;
				goto out;
			}
			break;
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
		case IPMI_EVENT_READING_TYPE_SENSOR_SPECIFIC:
		case 0x70:	/* reading types 70h-7Fh are for OEM discrete sensors */
		case 0x71:
		case 0x72:
		case 0x73:
		case 0x74:
		case 0x75:
		case 0x76:
		case 0x77:
		case 0x78:
		case 0x79:
		case 0x7a:
		case 0x7b:
		case 0x7c:
		case 0x7d:
		case 0x7e:
		case 0x7f:
			if (0 != (ret = ipmi_sensor_get_states(s->sensor, zbx_got_discrete_states_cb, h)))
			{
				/* do not use pointer to sensor here - the sensor may have disappeared during */
				/* ipmi_sensor_get_states(), as domain might be closed due to communication failure */
				h->err = zbx_dsprintf(h->err, "Cannot read sensor \"%s\"."
						" ipmi_sensor_get_states() return error: 0x%x", id_str,
						(unsigned int)ret);
				h->ret = NOTSUPPORTED;
				goto out;
			}
			break;
		default:
			s_reading_type_string = ipmi_sensor_get_event_reading_type_string(s->sensor);

			h->err = zbx_dsprintf(h->err, "Cannot read sensor \"%s\"."
					" IPMI reading type \"%s\" is not supported", id_str, s_reading_type_string);
			h->ret = NOTSUPPORTED;
			goto out;
	}

	zbx_perform_openipmi_ops(h, __func__);	/* ignore returned result */
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}

static void	zbx_read_ipmi_thresholds(zbx_ipmi_host_t *h, const zbx_ipmi_sensor_t *s)
{
	char	id_str[2 * IPMI_SENSOR_ID_SZ + 1];
	int 	ret;
	int	thr_access;

	/* copy sensor details at start - it can go away and we won't be able to make an error message */
	zbx_sensor_id_to_str(id_str, sizeof(id_str), s->id, s->id_type, s->id_sz);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sensor:'%s@[%s]:%d'", __func__, id_str, h->ip, h->port);

	h->done = 0;

	thr_access = ipmi_sensor_get_threshold_access(s->sensor);
	if (thr_access == IPMI_THRESHOLD_ACCESS_SUPPORT_NONE || thr_access == IPMI_THRESHOLD_ACCESS_SUPPORT_FIXED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot access thresholds");
		goto out;
	}

	if (0 != (ret = ipmi_sensor_get_thresholds(s->sensor, zbx_got_thresholds_cb, h)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot read thresholds for sensor \"%s\". ipmi_sensor_get_thresholds()"
				" return error: 0x%x", id_str, (unsigned int)ret);
		goto out;
	}

	zbx_perform_openipmi_ops(h, __func__);	/* ignore returned result */
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}
/* callback function invoked from OpenIPMI */
static void	zbx_got_control_reading_cb(ipmi_control_t *control, int err, int *val, void *cb_data)
{
	zbx_ipmi_host_t		*h = (zbx_ipmi_host_t *)cb_data;
	int			n;
	zbx_ipmi_control_t	*c;
	const char		*e_string;
	size_t			sz;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() fail: %s", __func__, zbx_strerror(err));

		h->err = zbx_dsprintf(h->err, "error 0x%x while reading control", (unsigned int)err);
		h->ret = NOTSUPPORTED;
		goto out;
	}

	c = zbx_get_ipmi_control(h, control);

	if (NULL == c)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_strdup(h->err, "fatal error");
		h->ret = NOTSUPPORTED;
		goto out;
	}

	if (c->num_values == 0)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		h->err = zbx_strdup(h->err, "no value present for control");
		h->ret = NOTSUPPORTED;
		goto out;
	}

	e_string = ipmi_entity_get_entity_id_string(ipmi_control_get_entity(control));

	for (n = 0; n < c->num_values; n++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "control values [%s | %s | %d:%d]",
				c->c_name, e_string, n + 1, val[n]);
	}

	sz = sizeof(int) * (size_t)c->num_values;
	memcpy(c->val, val, sz);
out:
	h->done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}

/* callback function invoked from OpenIPMI */
static void	zbx_got_control_setting_cb(ipmi_control_t *control, int err, void *cb_data)
{
	zbx_ipmi_host_t		*h = (zbx_ipmi_host_t *)cb_data;
	zbx_ipmi_control_t	*c;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() fail: %s", __func__, zbx_strerror(err));

		h->err = zbx_dsprintf(h->err, "error 0x%x while set control", (unsigned int)err);
		h->ret = NOTSUPPORTED;
		h->done = 1;
		return;
	}

	c = zbx_get_ipmi_control(h, control);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}

static void	zbx_read_ipmi_control(zbx_ipmi_host_t *h, const zbx_ipmi_control_t *c)
{
	int	ret;
	char	control_name[128];	/* internally defined CONTROL_ID_LEN is 32 in OpenIPMI 2.0.22 */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() control:'%s@[%s]:%d'", __func__, c->c_name, h->ip, h->port);

	if (0 == ipmi_control_is_readable(c->control))
	{
		h->err = zbx_strdup(h->err, "control is not readable");
		h->ret = NOTSUPPORTED;
		goto out;
	}

	/* copy control name - it can go away and we won't be able to make an error message */
	zbx_strlcpy(control_name, c->c_name, sizeof(control_name));

	h->ret = SUCCEED;
	h->done = 0;

	if (0 != (ret = ipmi_control_get_val(c->control, zbx_got_control_reading_cb, h)))
	{
		/* do not use pointer to control here - the control may have disappeared during */
		/* ipmi_control_get_val(), as domain might be closed due to communication failure */
		h->err = zbx_dsprintf(h->err, "Cannot read control %s. ipmi_control_get_val() return error: 0x%x",
				control_name, (unsigned int)ret);
		h->ret = NOTSUPPORTED;
		goto out;
	}

	zbx_perform_openipmi_ops(h, __func__);	/* ignore returned result */
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}

static void	zbx_set_ipmi_control(zbx_ipmi_host_t *h, zbx_ipmi_control_t *c, int value)
{
	int	ret;
	char	control_name[128];	/* internally defined CONTROL_ID_LEN is 32 in OpenIPMI 2.0.22 */

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() control:'%s@[%s]:%d' value:%d",
			__func__, c->c_name, h->ip, h->port, value);

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

	/* copy control name - it can go away and we won't be able to make an error message */
	zbx_strlcpy(control_name, c->c_name, sizeof(control_name));

	c->val[0] = value;
	h->ret = SUCCEED;
	h->done = 0;

	if (0 != (ret = ipmi_control_set_val(c->control, c->val, zbx_got_control_setting_cb, h)))
	{
		/* do not use pointer to control here - the control may have disappeared during */
		/* ipmi_control_set_val(), as domain might be closed due to communication failure */
		h->err = zbx_dsprintf(h->err, "Cannot set control %s. ipmi_control_set_val() return error: 0x%x",
				control_name, (unsigned int)ret);
		h->ret = NOTSUPPORTED;
		goto out;
	}

	zbx_perform_openipmi_ops(h, __func__);	/* ignore returned result */
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}

/* callback function invoked from OpenIPMI */
static void	zbx_sensor_change_cb(enum ipmi_update_e op, ipmi_entity_t *ent, ipmi_sensor_t *sensor, void *cb_data)
{
	zbx_ipmi_host_t	*h = (zbx_ipmi_host_t *)cb_data;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d' phost:%p ent:%p sensor:%p op:%d",
			__func__, h->ip, h->port, (void *)h, (void *)ent, (void *)sensor, (int)op);

	/* ignore non-readable sensors (e.g. Event-only) */
	if (0 != ipmi_sensor_get_is_readable(sensor))
	{
		switch (op)
		{
			case IPMI_ADDED:
				if (NULL == zbx_get_ipmi_sensor(h, sensor))
					zbx_allocate_ipmi_sensor(h, sensor);
				break;
			case IPMI_DELETED:
				zbx_delete_ipmi_sensor(h, sensor);
				break;
			case IPMI_CHANGED:
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/* callback function invoked from OpenIPMI */
static void	zbx_control_change_cb(enum ipmi_update_e op, ipmi_entity_t *ent, ipmi_control_t *control, void *cb_data)
{
	zbx_ipmi_host_t	*h = (zbx_ipmi_host_t *)cb_data;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d' phost:%p ent:%p control:%p op:%d",
			__func__, h->ip, h->port, (void *)h, (void *)ent, (void *)control, (int)op);

	switch (op)
	{
		case IPMI_ADDED:
			if (NULL == zbx_get_ipmi_control(h, control))
				zbx_allocate_ipmi_control(h, control);
			break;
		case IPMI_DELETED:
			zbx_delete_ipmi_control(h, control);
			break;
		case IPMI_CHANGED:
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/* callback function invoked from OpenIPMI */
static void	zbx_entity_change_cb(enum ipmi_update_e op, ipmi_domain_t *domain, ipmi_entity_t *entity, void *cb_data)
{
	int	ret;
	zbx_ipmi_host_t	*h = (zbx_ipmi_host_t *)cb_data;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	entity_name[IPMI_ENTITY_NAME_LEN];

		ipmi_entity_get_name(entity, entity_name, sizeof(entity_name));

		zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d' phost:%p domain:%p entity:%p:'%s' op:%d",
				__func__, h->ip, h->port, (void *)h, (void *)domain, (void *)entity, entity_name,
				(int)op);
	}

	if (op == IPMI_ADDED)
	{
		if (0 != (ret = ipmi_entity_add_sensor_update_handler(entity, zbx_sensor_change_cb, h)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "ipmi_entity_set_sensor_update_handler() return error: 0x%x",
					(unsigned int)ret);
		}

		if (0 != (ret = ipmi_entity_add_control_update_handler(entity, zbx_control_change_cb, h)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "ipmi_entity_add_control_update_handler() return error: 0x%x",
					(unsigned int)ret);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/* callback function invoked from OpenIPMI */
static void	zbx_domain_closed_cb(void *cb_data)
{
	zbx_ipmi_host_t	*h = (zbx_ipmi_host_t *)cb_data;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() phost:%p host:'[%s]:%d'", __func__, (void *)h, h->ip, h->port);

	h->domain_up = 0;
	h->done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/* callback function invoked from OpenIPMI */
static void	zbx_connection_change_cb(ipmi_domain_t *domain, int err, unsigned int conn_num, unsigned int port_num,
		int still_connected, void *cb_data)
{
	/* this function is called when a connection comes up or goes down */

	int		ret;
	zbx_ipmi_host_t	*h = (zbx_ipmi_host_t *)cb_data;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d' phost:%p domain:%p err:%d conn_num:%u port_num:%u"
			" still_connected:%d cb_data:%p", __func__, h->ip, h->port, (void *)h, (void *)domain,
			err, conn_num, port_num, still_connected, cb_data);

	if (0 != err)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() fail: %s", __func__, zbx_strerror(err));

		h->err = zbx_dsprintf(h->err, "cannot connect to IPMI host: %s", zbx_strerror(err));
		h->ret = NETWORK_ERROR;

		if (0 != (ret = ipmi_domain_close(domain, zbx_domain_closed_cb, h)))
			zabbix_log(LOG_LEVEL_DEBUG, "cannot close IPMI domain: [0x%x]", (unsigned int)ret);

		goto out;
	}

	if (0 != (ret = ipmi_domain_add_entity_update_handler(domain, zbx_entity_change_cb, h)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ipmi_domain_add_entity_update_handler() return error: [0x%x]",
				(unsigned int)ret);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(h->ret));
}

/* callback function invoked from OpenIPMI */
static void	zbx_domain_up_cb(ipmi_domain_t *domain, void *cb_data)
{
	zbx_ipmi_host_t	*h = (zbx_ipmi_host_t *)cb_data;

	RETURN_IF_CB_DATA_NULL(cb_data, __func__);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d' domain:%p cb_data:%p", __func__, h->ip,
			h->port, (void *)domain, cb_data);

	h->domain_up = 1;
	h->done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	zbx_vlog(os_handler_t *handler, const char *format, enum ipmi_log_type_e log_type, va_list ap)
{
	char	type[8], str[MAX_STRING_LEN];

	ZBX_UNUSED(handler);

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
		default				: THIS_SHOULD_NEVER_HAPPEN;
	}

	zbx_vsnprintf(str, sizeof(str), format, ap);

	zabbix_log(LOG_LEVEL_DEBUG, "%s%s", type, str);
}

int	zbx_init_ipmi_handler(void)
{
	int		res, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (os_hnd = ipmi_posix_setup_os_handler()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "unable to allocate IPMI handler");
		goto out;
	}

	os_hnd->set_log_handler(os_hnd, zbx_vlog);

	if (0 != (res = ipmi_init(os_hnd)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "unable to initialize the OpenIPMI library."
				" ipmi_init() return error: 0x%x", (unsigned int)res);
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	zbx_free_ipmi_host(zbx_ipmi_host_t *h)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d' h:%p", __func__, h->ip, h->port, (void *)h);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_free_ipmi_handler(void)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (NULL != hosts)
	{
		zbx_ipmi_host_t	*h;

		h = hosts;
		hosts = hosts->next;

		zbx_free_ipmi_host(h);
	}

	os_hnd->free_os_handler(os_hnd);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static zbx_ipmi_host_t	*zbx_init_ipmi_host(const char *ip, int port, int authtype, int privilege, const char *username,
		const char *password)
{
	zbx_ipmi_host_t		*h;
	ipmi_open_option_t	options[4];

	/* Although we use only one address and port we pass them in 2-element arrays. The reason is */
	/* OpenIPMI v.2.0.16 - 2.0.24 file lib/ipmi_lan.c, function ipmi_lanp_setup_con() ending with loop */
	/* in OpenIPMI file lib/ipmi_lan.c, function ipmi_lanp_setup_con() ending with */
	/*    for (i=0; i<MAX_IP_ADDR; i++) {           */
	/*        if (!ports[i])                        */
	/*            ports[i] = IPMI_LAN_STD_PORT_STR; */
	/*    }                                         */
	/* MAX_IP_ADDR is '#define MAX_IP_ADDR 2' in OpenIPMI and not available to library users. */
	/* The loop is running two times regardless of number of addresses supplied by the caller, so we use */
	/* 2-element arrays to match OpenIPMI internals. */
	char			*addrs[2] = {NULL}, *ports[2] = {NULL};

	char			domain_name[11];	/* max int length */
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() host:'[%s]:%d'", __func__, ip, port);

	/* Host already in the list? */

	if (NULL != (h = zbx_get_ipmi_host(ip, port, authtype, privilege, username, password)))
	{
		if (1 == h->domain_up)
			goto out;
	}
	else
		h = zbx_allocate_ipmi_host(ip, port, authtype, privilege, username, password);

	h->ret = SUCCEED;
	h->done = 0;

	addrs[0] = strdup(h->ip);
	ports[0] = zbx_dsprintf(NULL, "%d", h->port);

	if (0 != (ret = ipmi_ip_setup_con(addrs, ports, 1,
			h->authtype == -1 ? (unsigned int)IPMI_AUTHTYPE_DEFAULT : (unsigned int)h->authtype,
			(unsigned int)h->privilege, h->username, strlen(h->username),
			h->password, strlen(h->password), os_hnd, NULL, &h->con)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot connect to IPMI host [%s]:%d."
				" ipmi_ip_setup_con() returned error 0x%x",
				h->ip, h->port, (unsigned int)ret);
		h->ret = NETWORK_ERROR;
		goto out;
	}

	if (0 != (ret = h->con->start_con(h->con)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot connect to IPMI host [%s]:%d."
				" start_con() returned error 0x%x",
				h->ip, h->port, (unsigned int)ret);
		h->ret = NETWORK_ERROR;
		goto out;
	}

	options[0].option = IPMI_OPEN_OPTION_ALL;
	options[0].ival = 0;
	options[1].option = IPMI_OPEN_OPTION_SDRS;		/* scan SDRs */
	options[1].ival = 1;
	options[2].option = IPMI_OPEN_OPTION_IPMB_SCAN;		/* scan IPMB bus to find out as much as possible */
	options[2].ival = 1;
	options[3].option = IPMI_OPEN_OPTION_LOCAL_ONLY;	/* scan only local resources */
	options[3].ival = 1;

	zbx_snprintf(domain_name, sizeof(domain_name), "%u", h->domain_nr);

	if (0 != (ret = ipmi_open_domain(domain_name, &h->con, 1, zbx_connection_change_cb, h, zbx_domain_up_cb, h,
			options, ARRSIZE(options), NULL)))
	{
		h->err = zbx_dsprintf(h->err, "Cannot connect to IPMI host [%s]:%d. ipmi_open_domain() failed: %s",
				h->ip, h->port, zbx_strerror(ret));
		h->ret = NETWORK_ERROR;
		goto out;
	}

	zbx_perform_openipmi_ops(h, __func__);	/* ignore returned result */
out:
	zbx_free(addrs[0]);
	zbx_free(ports[0]);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p domain_nr:%u", __func__, (void *)h, h->domain_nr);

	return h;
}

static ipmi_domain_id_t	domain_id;		/* global variable for passing OpenIPMI domain ID between callbacks */
static int		domain_id_found;	/* A flag to indicate whether the 'domain_id' carries a valid value. */
						/* Values: 0 - not found, 1 - found. The flag is used because we */
						/* cannot set 'domain_id' to NULL. */
static int		domain_close_ok;

/* callback function invoked from OpenIPMI */
static void	zbx_get_domain_id_by_name_cb(ipmi_domain_t *domain, void *cb_data)
{
	char	name[IPMI_DOMAIN_NAME_LEN], *domain_name = (char *)cb_data;

	RETURN_IF_CB_DATA_NULL(cb_data, "zbx_get_domain_id_by_name_cb");

	/* from 'domain' pointer find the domain name */
	ipmi_domain_get_name(domain, name, sizeof(name));

	/* if the domain name matches the name we are searching for then store the domain ID into global variable */
	if (0 == strcmp(domain_name, name))
	{
		domain_id = ipmi_domain_convert_to_id(domain);
		domain_id_found = 1;
	}
}

/* callback function invoked from OpenIPMI */
static void	zbx_domain_close_cb(ipmi_domain_t *domain, void *cb_data)
{
	zbx_ipmi_host_t	*h = (zbx_ipmi_host_t *)cb_data;
	int		ret;

	RETURN_IF_CB_DATA_NULL(cb_data, "zbx_domain_close_cb");

	if (0 != (ret = ipmi_domain_close(domain, zbx_domain_closed_cb, h)))
		zabbix_log(LOG_LEVEL_DEBUG, "cannot close IPMI domain: [0x%x]", (unsigned int)ret);
	else
		domain_close_ok = 1;
}

static int	zbx_close_inactive_host(zbx_ipmi_host_t *h)
{
	char	domain_name[11];	/* max int length */
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): %s", __func__, h->ip);

	zbx_snprintf(domain_name, sizeof(domain_name), "%u", h->domain_nr);

	/* Search the list of domains in OpenIPMI library and find which one to close. It could happen that */
	/* the domain is not found (e.g. if Zabbix allocated an IPMI host during network problem and the domain was */
	/* closed by OpenIPMI library but the host is still in our 'hosts' list). */

	domain_id_found = 0;
	ipmi_domain_iterate_domains(zbx_get_domain_id_by_name_cb, domain_name);

	h->done = 0;
	domain_close_ok = 0;

	if (1 == domain_id_found)
	{
		int	res;

		if (0 != (res = ipmi_domain_pointer_cb(domain_id, zbx_domain_close_cb, h)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): ipmi_domain_pointer_cb() return error: %s", __func__,
					zbx_strerror(res));
			goto out;
		}

		if (1 != domain_close_ok || SUCCEED != zbx_perform_openipmi_ops(h, __func__))
			goto out;
	}

	/* The domain was either successfully closed or not found. */
	zbx_free_ipmi_host(h);
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

void	zbx_delete_inactive_ipmi_hosts(time_t last_check)
{
	zbx_ipmi_host_t	*h = hosts, *prev = NULL, *next;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	while (NULL != h)
	{
		if (last_check - h->lastaccess > INACTIVE_HOST_LIMIT)
		{
			next = h->next;

			if (SUCCEED == zbx_close_inactive_host(h))
			{
				if (NULL == prev)
					hosts = next;
				else
					prev->next = next;

				h = next;

				continue;
			}
		}

		prev = h;
		h = h->next;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Check if a string starts with one of predefined prefixes and      *
 *          set prefix length                                                 *
 *                                                                            *
 * Parameters: str        - [IN] string to examine                            *
 *             prefix_len - [OUT] length of the prefix                        *
 *                                                                            *
 * Return value: 1 - the string starts with the name prefix,                  *
 *               0 - otherwise (no prefix or other prefix was found)          *
 *                                                                            *
 ******************************************************************************/
static int	has_name_prefix(const char *str, size_t *prefix_len)
{
#define ZBX_ID_PREFIX	"id:"
#define ZBX_NAME_PREFIX	"name:"

	const size_t	id_len = sizeof(ZBX_ID_PREFIX) - 1, name_len = sizeof(ZBX_NAME_PREFIX) - 1;

	if (0 == strncmp(str, ZBX_NAME_PREFIX, name_len))
	{
		*prefix_len = name_len;
		return 1;
	}

	if (0 == strncmp(str, ZBX_ID_PREFIX, id_len))
		*prefix_len = id_len;
	else
		*prefix_len = 0;

	return 0;

#undef ZBX_ID_PREFIX
#undef ZBX_NAME_PREFIX
}

int	get_value_ipmi(zbx_uint64_t itemid, const char *addr, unsigned short port, signed char authtype,
		unsigned char privilege, const char *username, const char *password, const char *sensor, char **value)
{
	zbx_ipmi_host_t		*h;
	zbx_ipmi_sensor_t	*s;
	zbx_ipmi_control_t	*c = NULL;
	size_t			offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64, __func__, itemid);

	if (NULL == os_hnd)
	{
		*value = zbx_strdup(*value, "IPMI handler is not initialised.");
		return CONFIG_ERROR;
	}

	h = zbx_init_ipmi_host(addr, port, authtype, privilege, username, password);

	h->lastaccess = time(NULL);

	if (0 == h->domain_up)
	{
		if (NULL != h->err)
			*value = zbx_strdup(*value, h->err);

		return h->ret;
	}

	if (0 == has_name_prefix(sensor, &offset))
	{
		if (NULL == (s = zbx_get_ipmi_sensor_by_id(h, sensor + offset)))
			c = zbx_get_ipmi_control_by_name(h, sensor + offset);
	}
	else
	{
		if (NULL == (s = zbx_get_ipmi_sensor_by_full_name(h, sensor + offset)))
			c = zbx_get_ipmi_control_by_full_name(h, sensor + offset);
	}

	if (NULL == s && NULL == c)
	{
		*value = zbx_dsprintf(*value, "sensor or control %s@[%s]:%d does not exist", sensor, h->ip, h->port);
		return NOTSUPPORTED;
	}

	if (NULL != s)
		zbx_read_ipmi_sensor(h, s);
	else
		zbx_read_ipmi_control(h, c);

	if (h->ret != SUCCEED)
	{
		if (NULL != h->err)
			*value = zbx_strdup(*value, h->err);

		return h->ret;
	}

	if (NULL != s)
	{
		if (IPMI_EVENT_READING_TYPE_THRESHOLD == s->reading_type)
			*value = zbx_dsprintf(*value, ZBX_FS_DBL, s->value.threshold);
		else
			*value = zbx_dsprintf(*value, ZBX_FS_UI64, s->value.discrete);
	}

	if (NULL != c)
		*value = zbx_dsprintf(*value, "%d", c->val[0]);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s", __func__, zbx_result_string(h->ret),
			ZBX_NULL2EMPTY_STR(*value));

	return h->ret;
}

static void add_threshold_ipmi(struct zbx_json *json, const char *tag, zbx_ipmi_sensor_threshold_t *threshold)
{
	if (ZBX_IPMI_THRESHOLD_STATUS_ENABLED == threshold->status && 0 != isfinite(threshold->val))
		zbx_json_addfloat(json, tag, threshold->val);
}

int	get_discovery_ipmi(zbx_uint64_t itemid, const char *addr, unsigned short port, signed char authtype,
		unsigned char privilege, const char *username, const char *password, char **value)
{
	zbx_ipmi_host_t		*h;
	int 			i, j;
	struct zbx_json		json;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64, __func__, itemid);

	if (NULL == os_hnd)
	{
		*value = zbx_strdup(*value, "IPMI handler is not initialised.");
		return CONFIG_ERROR;
	}

	h = zbx_init_ipmi_host(addr, port, authtype, privilege, username, password);

	h->lastaccess = time(NULL);

	if (0 == h->domain_up)
	{
		if (NULL != h->err)
			*value = zbx_strdup(*value, h->err);

		return h->ret;
	}

	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < h->sensor_count; i++)
	{
		const char 	*p;
		char 		state_name[MAX_STRING_LEN];
		size_t		offset = 0;
		zbx_uint64_t	state;

		zbx_read_ipmi_sensor(h, &h->sensors[i]);
		if (SUCCEED != h->ret)
		{
			zabbix_log(LOG_LEVEL_DEBUG,"Sensor '%s' cannot be discovered. Error: %s",h->sensors[i].id,
					ZBX_NULL2EMPTY_STR(h->err));

			continue;
		}

		if (IPMI_EVENT_READING_TYPE_THRESHOLD == h->sensors[i].reading_type)
		{
			zbx_read_ipmi_thresholds(h, &h->sensors[i]);
			if (SUCCEED != h->ret)
			{
				zabbix_log(LOG_LEVEL_DEBUG,"Sensor '%s' cannot be discovered. Error: %s",
						h->sensors[i].id, ZBX_NULL2EMPTY_STR(h->err));

				continue;
			}
		}

		zbx_json_addobject(&json, NULL);

		zbx_json_addstring(&json, ZBX_IPMI_TAG_ID, h->sensors[i].id, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, ZBX_IPMI_TAG_NAME, h->sensors[i].full_name, ZBX_JSON_TYPE_STRING);

		zbx_json_addobject(&json, ZBX_IPMI_TAG_SENSOR);
		zbx_json_adduint64(&json, ZBX_IPMI_TAG_TYPE, (zbx_uint64_t)h->sensors[i].type);
		p = ipmi_sensor_get_sensor_type_string(h->sensors[i].sensor);
		zbx_json_addstring(&json, ZBX_IPMI_TAG_TEXT, p,	ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);

		zbx_json_addobject(&json, ZBX_IPMI_TAG_READING);
		zbx_json_adduint64(&json, ZBX_IPMI_TAG_TYPE, (zbx_uint64_t)h->sensors[i].reading_type);
		p = ipmi_sensor_get_event_reading_type_string(h->sensors[i].sensor);
		zbx_json_addstring(&json, ZBX_IPMI_TAG_TEXT, p,	ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);

		if (IPMI_EVENT_READING_TYPE_THRESHOLD != h->sensors[i].reading_type)	/* discrete */
		{
			state = h->sensors[i].value.discrete;
			zbx_json_addobject(&json, ZBX_IPMI_TAG_STATE);
			zbx_json_adduint64(&json, ZBX_IPMI_TAG_STATE, state);

			state_name[0] = '\0';
			for (j = 0; j < MAX_DISCRETE_STATES; j++)
			{
				if (0 != (state & (1u << j)))
				{
					if (NULL != (p = ipmi_sensor_reading_name_string(h->sensors[i].sensor, j)))
					{
						if (0 < offset)
							offset += zbx_snprintf(state_name + offset,
									sizeof(state_name) - offset,", ");
						offset += zbx_snprintf(state_name + offset, sizeof(state_name) - offset,
								"%s", p);
					}
				}
			}

			zbx_json_addstring(&json, ZBX_IPMI_TAG_TEXT, state_name, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json);
		}
		else	/* threshold */
		{
			char	*units;

			state = (zbx_uint64_t)h->sensors[i].state;
			zbx_json_addobject(&json, ZBX_IPMI_TAG_STATE);
			zbx_json_adduint64(&json, ZBX_IPMI_TAG_STATE, state);

			state_name[0] = '\0';
			for (j = IPMI_LOWER_NON_CRITICAL; j <= IPMI_UPPER_NON_RECOVERABLE; j++)
			{
				if (0 != (state & (1u << j)))
				{
					if (0 < offset)
						offset += zbx_snprintf(state_name + offset,
								sizeof(state_name) - offset,", ");
					offset += zbx_snprintf(state_name + offset, sizeof(state_name) - offset,
							"%s - out of range", ipmi_get_threshold_string(j));
				}
			}

			zbx_json_addstring(&json, ZBX_IPMI_TAG_TEXT, state_name, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json);

			zbx_json_addfloat(&json, ZBX_IPMI_TAG_VALUE, h->sensors[i].value.threshold);

			units = zbx_get_ipmi_units(h->sensors[i].sensor);
			zbx_json_addstring(&json, ZBX_IPMI_TAG_UNITS, units, ZBX_JSON_TYPE_STRING);
			zbx_free(units);

			zbx_read_ipmi_thresholds(h, &h->sensors[i]);

			zbx_json_addobject(&json, ZBX_IPMI_TAG_THRESHOLD);
			zbx_json_addobject(&json, ZBX_IPMI_TAG_LOWER);

			add_threshold_ipmi(&json,ZBX_IPMI_TAG_NON_CRIT,
					&h->sensors[i].thresholds[IPMI_LOWER_NON_CRITICAL]);
			add_threshold_ipmi(&json,ZBX_IPMI_TAG_CRIT,
					&h->sensors[i].thresholds[IPMI_LOWER_CRITICAL]);
			add_threshold_ipmi(&json,ZBX_IPMI_TAG_NON_RECOVER,
					&h->sensors[i].thresholds[IPMI_LOWER_NON_RECOVERABLE]);

			zbx_json_close(&json);

			zbx_json_addobject(&json, ZBX_IPMI_TAG_UPPER);

			add_threshold_ipmi(&json,ZBX_IPMI_TAG_NON_CRIT,
					&h->sensors[i].thresholds[IPMI_UPPER_NON_CRITICAL]);
			add_threshold_ipmi(&json,ZBX_IPMI_TAG_CRIT,
					&h->sensors[i].thresholds[IPMI_UPPER_CRITICAL]);
			add_threshold_ipmi(&json,ZBX_IPMI_TAG_NON_RECOVER,
					&h->sensors[i].thresholds[IPMI_UPPER_NON_RECOVERABLE]);

			zbx_json_close(&json);
			zbx_json_close(&json);
		}
		zbx_json_close(&json);

	}
	zbx_json_close(&json);

	*value = zbx_strdup(*value, json.buffer);

	zbx_json_free(&json);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%s", __func__, ZBX_NULL2EMPTY_STR(*value));

	return SUCCEED;
}

/* function 'zbx_parse_ipmi_command' requires 'c_name' with size 'ITEM_IPMI_SENSOR_LEN_MAX' */
int	zbx_parse_ipmi_command(const char *command, char *c_name, int *val, char *error, size_t max_error_len)
{
	const char	*p;
	size_t		sz_c_name;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() command:'%s'", __func__, command);

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
		zbx_snprintf(error, max_error_len, "IPMI command is too long [%.*s]", (int)sz_c_name, command);
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
	else if (SUCCEED != is_uint31(p, val))
	{
		zbx_snprintf(error, max_error_len, "IPMI command value is not supported [%s]", p);
		goto fail;
	}

	ret = SUCCEED;
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	zbx_set_ipmi_control_value(zbx_uint64_t hostid, const char *addr, unsigned short port, signed char authtype,
		unsigned char privilege, const char *username, const char *password, const char *sensor,
		int value, char **error)
{
	zbx_ipmi_host_t		*h;
	zbx_ipmi_control_t	*c;
	size_t			offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hostid:" ZBX_FS_UI64 "control:%s value:%d",
			__func__, hostid, sensor, value);

	if (NULL == os_hnd)
	{
		*error = zbx_strdup(*error, "IPMI handler is not initialized.");
		zabbix_log(LOG_LEVEL_DEBUG, "%s", *error);
		return NOTSUPPORTED;
	}

	h = zbx_init_ipmi_host(addr, port, authtype, privilege, username, password);

	if (0 == h->domain_up)
	{
		if (NULL != h->err)
		{
			*error = zbx_strdup(*error, h->err);
			zabbix_log(LOG_LEVEL_DEBUG, "%s", h->err);
		}
		return h->ret;
	}

	if (0 == has_name_prefix(sensor, &offset))
		c = zbx_get_ipmi_control_by_name(h, sensor + offset);
	else
		c = zbx_get_ipmi_control_by_full_name(h, sensor + offset);

	if (NULL == c)
	{
		*error = zbx_dsprintf(*error, "Control \"%s\" at address \"%s:%d\" does not exist.", sensor, h->ip, h->port);
		zabbix_log(LOG_LEVEL_DEBUG, "%s", *error);
		return NOTSUPPORTED;
	}

	zbx_set_ipmi_control(h, c, value);

	if (h->ret != SUCCEED)
	{
		if (NULL != h->err)
		{
			*error = zbx_strdup(*error, h->err);
			zabbix_log(LOG_LEVEL_DEBUG, "%s", h->err);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);\

	return h->ret;
}

#endif	/* HAVE_OPENIPMI */
