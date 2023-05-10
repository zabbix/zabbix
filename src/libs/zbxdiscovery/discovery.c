/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxdiscovery.h"

#include "zbxserver.h"
#include "log.h"
#include "zbxtime.h"
#include "zbxnum.h"
#include "zbxserialize.h"

typedef struct
{
	zbx_uint64_t	dserviceid;
	int		status;
	int		lastup;
	int		lastdown;
	char		*value;
}
DB_DSERVICE;

#define DISCOVERER_INITIALIZED_YES	1

static int	discoverer_initialized = 0;

void	zbx_discoverer_init(void)
{
	discoverer_initialized = DISCOVERER_INITIALIZED_YES;
}

static zbx_db_result_t	discovery_get_dhost_by_value(zbx_uint64_t dcheckid, const char *value)
{
	zbx_db_result_t	result;
	char		*value_esc;

	value_esc = zbx_db_dyn_escape_field("dservices", "value", value);

	result = zbx_db_select(
			"select dh.dhostid,dh.status,dh.lastup,dh.lastdown"
			" from dhosts dh,dservices ds"
			" where ds.dhostid=dh.dhostid"
				" and ds.dcheckid=" ZBX_FS_UI64
				" and ds.value" ZBX_SQL_STRCMP
			" order by dh.dhostid",
			dcheckid, ZBX_SQL_STRVAL_EQ(value_esc));

	zbx_free(value_esc);

	return result;
}

static zbx_db_result_t	discovery_get_dhost_by_ip_port(zbx_uint64_t druleid, const char *ip, int port)
{
	zbx_db_result_t	result;
	char		*ip_esc;

	ip_esc = zbx_db_dyn_escape_field("dservices", "ip", ip);

	result = zbx_db_select(
			"select dh.dhostid,dh.status,dh.lastup,dh.lastdown"
			" from dhosts dh,dservices ds"
			" where ds.dhostid=dh.dhostid"
				" and dh.druleid=" ZBX_FS_UI64
				" and ds.ip" ZBX_SQL_STRCMP
				" and ds.port=%d"
			" order by dh.dhostid",
			druleid, ZBX_SQL_STRVAL_EQ(ip_esc), port);

	zbx_free(ip_esc);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: separate multiple-IP hosts                                        *
 *                                                                            *
 * Parameters: host ip address                                                *
 *                                                                            *
 ******************************************************************************/
static void	discovery_separate_host(zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip)
{
	zbx_db_result_t	result;
	char		*ip_esc, *sql = NULL;
	zbx_uint64_t	dhostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __func__, ip);

	ip_esc = zbx_db_dyn_escape_field("dservices", "ip", ip);

	sql = zbx_dsprintf(sql,
			"select dserviceid"
			" from dservices"
			" where dhostid=" ZBX_FS_UI64
				" and ip" ZBX_SQL_STRCMP,
			dhost->dhostid, ZBX_SQL_STRVAL_NE(ip_esc));

	result = zbx_db_select_n(sql, 1);

	if (NULL != zbx_db_fetch(result))
	{
		dhostid = zbx_db_get_maxid("dhosts");

		zbx_db_execute("insert into dhosts (dhostid,druleid)"
				" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				dhostid, druleid);

		zbx_db_execute("update dservices"
				" set dhostid=" ZBX_FS_UI64
				" where dhostid=" ZBX_FS_UI64
					" and ip" ZBX_SQL_STRCMP,
				dhostid, dhost->dhostid, ZBX_SQL_STRVAL_EQ(ip_esc));

		dhost->dhostid = dhostid;
		dhost->status = DOBJECT_STATUS_DOWN;
		dhost->lastup = 0;
		dhost->lastdown = 0;
	}
	zbx_db_free_result(result);

	zbx_free(sql);
	zbx_free(ip_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: register host if one does not exist                               *
 *                                                                            *
 * Parameters: host ip address                                                *
 *                                                                            *
 ******************************************************************************/
static void	discovery_register_host(zbx_uint64_t druleid, zbx_uint64_t dcheckid, zbx_uint64_t unique_dcheckid,
		zbx_db_dhost *dhost, const char *ip, int port, int status, const char *value)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		match_value = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s' status:%d value:'%s'", __func__, ip, status, value);

	if (unique_dcheckid == dcheckid)
	{
		result = discovery_get_dhost_by_value(dcheckid, value);

		if (NULL == (row = zbx_db_fetch(result)))
		{
			zbx_db_free_result(result);

			result = discovery_get_dhost_by_ip_port(druleid, ip, port);
			row = zbx_db_fetch(result);
		}
		else
			match_value = 1;

	}
	else
	{
		result = discovery_get_dhost_by_ip_port(druleid, ip, port);
		row = zbx_db_fetch(result);
	}

	if (NULL == row)
	{
		if (DOBJECT_STATUS_UP == status)	/* add host only if service is up */
		{
			zabbix_log(LOG_LEVEL_DEBUG, "new host discovered at %s", ip);

			dhost->dhostid = zbx_db_get_maxid("dhosts");
			dhost->status = DOBJECT_STATUS_DOWN;
			dhost->lastup = 0;
			dhost->lastdown = 0;

			zbx_db_execute("insert into dhosts (dhostid,druleid)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
					dhost->dhostid, druleid);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "host at %s is already in database", ip);

		ZBX_STR2UINT64(dhost->dhostid, row[0]);
		dhost->status = atoi(row[1]);
		dhost->lastup = atoi(row[2]);
		dhost->lastdown = atoi(row[3]);

		if (0 == match_value)
			discovery_separate_host(druleid, dhost, ip);
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: register service if one does not exist                            *
 *                                                                            *
 * Parameters: host ip address                                                *
 *                                                                            *
 ******************************************************************************/
static void	discovery_register_service(zbx_uint64_t dcheckid, zbx_db_dhost *dhost, DB_DSERVICE *dservice,
		const char *ip, const char *dns, int port, int status)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*ip_esc, *dns_esc;

	zbx_uint64_t	dhostid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s' port:%d", __func__, ip, port);

	ip_esc = zbx_db_dyn_escape_field("dservices", "ip", ip);

	result = zbx_db_select(
			"select dserviceid,dhostid,status,lastup,lastdown,value,dns"
			" from dservices"
			" where dcheckid=" ZBX_FS_UI64
				" and ip" ZBX_SQL_STRCMP
				" and port=%d",
			dcheckid, ZBX_SQL_STRVAL_EQ(ip_esc), port);

	if (NULL == (row = zbx_db_fetch(result)))
	{
		if (DOBJECT_STATUS_UP == status)	/* add host only if service is up */
		{
			zabbix_log(LOG_LEVEL_DEBUG, "new service discovered on port %d", port);

			dservice->dserviceid = zbx_db_get_maxid("dservices");
			dservice->status = DOBJECT_STATUS_DOWN;
			dservice->value = zbx_strdup(dservice->value, "");

			dns_esc = zbx_db_dyn_escape_field("dservices", "dns", dns);

			zbx_db_execute("insert into dservices (dserviceid,dhostid,dcheckid,ip,dns,port,status)"
					" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s','%s',%d,%d)",
					dservice->dserviceid, dhost->dhostid, dcheckid, ip_esc, dns_esc, port,
					dservice->status);

			zbx_free(dns_esc);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "service is already in database");

		ZBX_STR2UINT64(dservice->dserviceid, row[0]);
		ZBX_STR2UINT64(dhostid, row[1]);
		dservice->status = atoi(row[2]);
		dservice->lastup = atoi(row[3]);
		dservice->lastdown = atoi(row[4]);
		dservice->value = zbx_strdup(dservice->value, row[5]);

		if (dhostid != dhost->dhostid)
		{
			zbx_db_execute("update dservices"
					" set dhostid=" ZBX_FS_UI64
					" where dhostid=" ZBX_FS_UI64,
					dhost->dhostid, dhostid);

			zbx_db_execute("delete from dhosts"
					" where dhostid=" ZBX_FS_UI64,
					dhostid);
		}

		if (0 != strcmp(row[6], dns))
		{
			dns_esc = zbx_db_dyn_escape_field("dservices", "dns", dns);

			zbx_db_execute("update dservices"
					" set dns='%s'"
					" where dserviceid=" ZBX_FS_UI64,
					dns_esc, dservice->dserviceid);

			zbx_free(dns_esc);
		}
	}
	zbx_db_free_result(result);

	zbx_free(ip_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update discovered service details                                 *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_dservice(zbx_uint64_t dserviceid, int status, int lastup, int lastdown,
		const char *value)
{
	char	*value_esc;

	value_esc = zbx_db_dyn_escape_field("dservices", "value", value);

	zbx_db_execute("update dservices set status=%d,lastup=%d,lastdown=%d,value='%s' where dserviceid=" ZBX_FS_UI64,
			status, lastup, lastdown, value_esc, dserviceid);

	zbx_free(value_esc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update discovered service details                                 *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_dservice_value(zbx_uint64_t dserviceid, const char *value)
{
	char	*value_esc;

	value_esc = zbx_db_dyn_escape_field("dservices", "value", value);

	zbx_db_execute("update dservices set value='%s' where dserviceid=" ZBX_FS_UI64, value_esc, dserviceid);

	zbx_free(value_esc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update discovered host details                                    *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_dhost(const zbx_db_dhost *dhost)
{
	zbx_db_execute("update dhosts set status=%d,lastup=%d,lastdown=%d where dhostid=" ZBX_FS_UI64,
			dhost->status, dhost->lastup, dhost->lastdown, dhost->dhostid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process and update the new service status                         *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_service_status(zbx_db_dhost *dhost, const DB_DSERVICE *dservice, int service_status,
		const char *value, int now, zbx_add_event_func_t add_event_cb)
{
	zbx_timespec_t	ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ts.sec = now;
	ts.ns = 0;

	if (DOBJECT_STATUS_UP == service_status)
	{
		if (DOBJECT_STATUS_DOWN == dservice->status || 0 == dservice->lastup)
		{
			discovery_update_dservice(dservice->dserviceid, service_status, now, 0, value);

			if (NULL != add_event_cb)
			{
				add_event_cb(EVENT_SOURCE_DISCOVERY, EVENT_OBJECT_DSERVICE, dservice->dserviceid, &ts,
						DOBJECT_STATUS_DISCOVER, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0,
						NULL, NULL, NULL);
			}

			if (DOBJECT_STATUS_DOWN == dhost->status)
			{
				/* Service went UP, but host status is DOWN. Update host status. */

				dhost->status = DOBJECT_STATUS_UP;
				dhost->lastup = now;
				dhost->lastdown = 0;

				discovery_update_dhost(dhost);

				if (NULL != add_event_cb)
				{
					add_event_cb(EVENT_SOURCE_DISCOVERY, EVENT_OBJECT_DHOST, dhost->dhostid, &ts,
							DOBJECT_STATUS_DISCOVER, NULL, NULL, NULL, 0, 0, NULL,
							0, NULL, 0, NULL, NULL, NULL);
				}
			}
		}
		else if (0 != strcmp(dservice->value, value))
		{
			discovery_update_dservice_value(dservice->dserviceid, value);
		}
	}
	else	/* DOBJECT_STATUS_DOWN */
	{
		if (DOBJECT_STATUS_UP == dservice->status || 0 == dservice->lastdown)
		{
			discovery_update_dservice(dservice->dserviceid, service_status, 0, now, dservice->value);

			if (NULL != add_event_cb)
			{
				add_event_cb(EVENT_SOURCE_DISCOVERY, EVENT_OBJECT_DSERVICE, dservice->dserviceid, &ts,
						DOBJECT_STATUS_LOST, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL,
						NULL, NULL);
			}

			/* service went DOWN, no need to update host status here as other services may be UP */
		}
	}

	if (NULL != add_event_cb)
	{
		add_event_cb(EVENT_SOURCE_DISCOVERY, EVENT_OBJECT_DSERVICE, dservice->dserviceid, &ts, service_status,
				NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL, NULL, NULL);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update new host status                                            *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_host_status(zbx_db_dhost *dhost, int status, int now,
		zbx_add_event_func_t add_event_cb)
{
	zbx_timespec_t	ts;

	ts.sec = now;
	ts.ns = 0;

	/* update host status */
	if (DOBJECT_STATUS_UP == status)
	{
		if (DOBJECT_STATUS_DOWN == dhost->status || 0 == dhost->lastup)
		{
			dhost->status = status;
			dhost->lastdown = 0;
			dhost->lastup = now;

			discovery_update_dhost(dhost);

			if (NULL != add_event_cb)
			{
				add_event_cb(EVENT_SOURCE_DISCOVERY, EVENT_OBJECT_DHOST, dhost->dhostid, &ts,
						DOBJECT_STATUS_DISCOVER, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0,
						NULL, NULL, NULL);
			}
		}
	}
	else	/* DOBJECT_STATUS_DOWN */
	{
		if (DOBJECT_STATUS_UP == dhost->status || 0 == dhost->lastdown)
		{
			dhost->status = status;
			dhost->lastdown = now;
			dhost->lastup = 0;

			discovery_update_dhost(dhost);

			if (NULL != add_event_cb)
			{
				add_event_cb(EVENT_SOURCE_DISCOVERY, EVENT_OBJECT_DHOST, dhost->dhostid, &ts,
						DOBJECT_STATUS_LOST, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL,
						NULL, NULL);
			}
		}
	}

	if (NULL != add_event_cb)
	{
		add_event_cb(EVENT_SOURCE_DISCOVERY, EVENT_OBJECT_DHOST, dhost->dhostid, &ts, status, NULL, NULL, NULL,
				0, 0, NULL, 0, NULL, 0, NULL, NULL, NULL);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process new host status                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_discovery_update_host(zbx_db_dhost *dhost, int status, int now, zbx_add_event_func_t add_event_cb)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 != dhost->dhostid)
		discovery_update_host_status(dhost, status, now, add_event_cb);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process new service status                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_discovery_update_service(zbx_uint64_t druleid, zbx_uint64_t dcheckid, zbx_uint64_t unique_dcheckid,
		zbx_db_dhost *dhost, const char *ip, const char *dns, int port, int status, const char *value, int now,
		zbx_add_event_func_t add_event_cb)
{
	DB_DSERVICE	dservice;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s' dns:'%s' port:%d status:%d value:'%s'",
			__func__, ip, dns, port, status, value);

	memset(&dservice, 0, sizeof(dservice));

	/* register host if is not registered yet */
	if (0 == dhost->dhostid)
		discovery_register_host(druleid, dcheckid, unique_dcheckid, dhost, ip, port, status, value);

	/* register service if is not registered yet */
	if (0 != dhost->dhostid)
		discovery_register_service(dcheckid, dhost, &dservice, ip, dns, port, status);

	/* service was not registered because we do not add down service */
	if (0 != dservice.dserviceid)
		discovery_update_service_status(dhost, &dservice, status, value, now, add_event_cb);

	zbx_free(dservice.value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free discovery check                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_discovery_dcheck_free(zbx_dc_dcheck_t *dcheck)
{
	zbx_free(dcheck->key_);
	zbx_free(dcheck->ports);

	if (SVC_SNMPv1 == dcheck->type || SVC_SNMPv2c == dcheck->type || SVC_SNMPv3 == dcheck->type)
	{
		zbx_free(dcheck->snmp_community);
		zbx_free(dcheck->snmpv3_securityname);
		zbx_free(dcheck->snmpv3_authpassphrase);
		zbx_free(dcheck->snmpv3_privpassphrase);
		zbx_free(dcheck->snmpv3_contextname);
	}

	zbx_free(dcheck);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free discovery rule                                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_discovery_drule_free(zbx_dc_drule_t *drule)
{
	zbx_free(drule->delay_str);
	zbx_free(drule->iprange);
	zbx_free(drule->name);

	zbx_vector_dc_dcheck_ptr_clear_ext(&drule->dchecks, zbx_discovery_dcheck_free);
	zbx_vector_dc_dcheck_ptr_destroy(&drule->dchecks);

	zbx_free(drule);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends command to discovery manager                                *
 *                                                                            *
 * Parameters: code     - [IN] message code                                   *
 *             data     - [IN] message data                                   *
 *             size     - [IN] message data size                              *
 *             response - [OUT] response message (can be NULL if response is  *
 *                              not requested)                                *
 *                                                                            *
 ******************************************************************************/
static void	discovery_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size,
		zbx_ipc_message_t *response)
{
	char			*error = NULL;
	static zbx_ipc_socket_t	socket = {0};

	/* each process has a permanent connection to discovery manager */
	if (0 == socket.fd && FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_DISCOVERER, SEC_PER_MIN,
			&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to discoverer service: %s", error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_socket_write(&socket, code, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to discoverer service");
		exit(EXIT_FAILURE);
	}

	if (NULL != response && FAIL == zbx_ipc_socket_read(&socket, response))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot receive data from discoverer service");
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get queue size (enqueued checks count) of discovery manager       *
 *                                                                            *
 * Parameters: size  - [OUT] enqueued item count                              *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - queue size retrieved                               *
 *               FAIL    - discovery manager is not initialized               *
 *                                                                            *
 ******************************************************************************/
int	zbx_discovery_get_queue_size(zbx_uint64_t *size, char **error)
{
	zbx_ipc_message_t	message;

	if (DISCOVERER_INITIALIZED_YES != discoverer_initialized)
	{
		if (NULL != error)
		{
			*error = zbx_strdup(NULL, "discoverer is not initialized: please check \"StartDiscoverers\""
					" configuration parameter");
		}

		return FAIL;
	}

	zbx_ipc_message_init(&message);
	discovery_send(ZBX_IPC_DISCOVERER_QUEUE, NULL, 0, &message);
	memcpy(size, message.data, sizeof(zbx_uint64_t));
	zbx_ipc_message_clean(&message);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack worker usage statistics                                    *
 *                                                                            *
 * Parameters: usage - [OUT] the worker usage statistics                      *
 *             data  - [IN] the input data                                    *
 *                                                                            *
 ******************************************************************************/
static void	discovery_unpack_usage_stats(zbx_vector_dbl_t *usage, int *count, const unsigned char *data)
{
	const unsigned char	*offset = data;
	int			usage_num;

	offset += zbx_deserialize_value(offset, &usage_num);
	zbx_vector_dbl_reserve(usage, (size_t)usage_num);

	for (int i = 0; i < usage_num; i++)
	{
		double	busy;

		offset += zbx_deserialize_value(offset, &busy);
		zbx_vector_dbl_append(usage, busy);
	}

	(void)zbx_deserialize_value(offset, count);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get discovery manager diagnostic statistics                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_discovery_get_usage_stats(zbx_vector_dbl_t *usage, int *count, char **error)
{
	unsigned char	*result;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_DISCOVERER, ZBX_IPC_DISCOVERER_USAGE_STATS,
			SEC_PER_MIN, NULL, 0, &result, error))
	{
		return FAIL;
	}

	discovery_unpack_usage_stats(usage, count, result);
	zbx_free(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack diagnostic statistics data into a single buffer that can be  *
 *          used in IPC                                                       *
 * Parameters: data    - [OUT] memory buffer for packed data                  *
 *             usage   - [IN] the worker usage statistics                     *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_discovery_pack_usage_stats(unsigned char **data, const zbx_vector_dbl_t *usage, int count)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len;

	data_len = (zbx_uint32_t)((unsigned int)usage->values_num * sizeof(double) + sizeof(int) + sizeof(int));

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr += zbx_serialize_value(ptr, usage->values_num);

	for (int i = 0; i < usage->values_num; i++)
		ptr += zbx_serialize_value(ptr, usage->values[i]);

	(void)zbx_serialize_value(ptr, count);

	return data_len;
}

void zbx_discovery_stats_ext_get(struct zbx_json *json, const void *arg)
{
	zbx_uint64_t	size;

	ZBX_UNUSED(arg);

	/* zabbix[discovery_queue] */
	if (SUCCEED == zbx_discovery_get_queue_size(&size, NULL))
		zbx_json_adduint64(json, "discovery_queue", size);
}
