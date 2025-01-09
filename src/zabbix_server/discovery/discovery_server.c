/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "discovery_server.h"

#include "zbxtime.h"
#include "zbxnum.h"
#include "zbxdb.h"
#include "zbxstr.h"
#include "zbxalgo.h"

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
 * Purpose: separates multiple-IP hosts                                       *
 *                                                                            *
 * Parameters:                                                                *
 *    druleid - [IN] host ip address                                          *
 *    dhost   - [OUT]                                                         *
 *    ip      - [IN]                                                          *
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
 * Purpose: registers host if one does not exist yet                          *
 *                                                                            *
 * Parameters:                                                                *
 *    druleid         - [IN]                                                  *
 *    dcheckid        - [IN]                                                  *
 *    unique_dcheckid - [IN]                                                  *
 *    dhost           - [IN]                                                  *
 *    ip              - [OUT] host ip address                                 *
 *    port            - [IN]                                                  *
 *    status          - [IN]                                                  *
 *    value           - [IN]                                                  *
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

typedef struct
{
	zbx_uint64_t	dserviceid;
	int		status;
	int		lastup;
	int		lastdown;
	char		*value;
}
zbx_db_dservice;

/******************************************************************************
 *                                                                            *
 * Purpose: registers service if one does not exist yet                       *
 *                                                                            *
 * Parameters:                                                                *
 *    dcheckid - [IN]                                                         *
 *    dhost    - [OUT]                                                        *
 *    dservice - [IN]                                                         *
 *    ip       - [IN] host ip address                                         *
 *    dns      - [IN]                                                         *
 *    port     - [IN]                                                         *
 *    status   - [IN]                                                         *
 *                                                                            *
 ******************************************************************************/
static void	discovery_register_service(zbx_uint64_t dcheckid, zbx_db_dhost *dhost, zbx_db_dservice *dservice,
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
 * Purpose: updates discovered service details                                *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_dservice(zbx_uint64_t dserviceid, int status, int lastup, int lastdown,
		const char *value)
{
	char	*value_esc = zbx_db_dyn_escape_field("dservices", "value", value);

	zbx_db_execute("update dservices set status=%d,lastup=%d,lastdown=%d,value='%s' where dserviceid=" ZBX_FS_UI64,
			status, lastup, lastdown, value_esc, dserviceid);

	zbx_free(value_esc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates discovered service details                                *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_dservice_value(zbx_uint64_t dserviceid, const char *value)
{
	char	*value_esc = zbx_db_dyn_escape_field("dservices", "value", value);

	zbx_db_execute("update dservices set value='%s' where dserviceid=" ZBX_FS_UI64, value_esc, dserviceid);

	zbx_free(value_esc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates discovered host details                                   *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_dhost(const zbx_db_dhost *dhost)
{
	zbx_db_execute("update dhosts set status=%d,lastup=%d,lastdown=%d where dhostid=" ZBX_FS_UI64,
			dhost->status, dhost->lastup, dhost->lastdown, dhost->dhostid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes and updates new service status                          *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_service_status(zbx_db_dhost *dhost, const zbx_db_dservice *dservice,
		int service_status, const char *value, int now, zbx_add_event_func_t add_event_cb)
{
	zbx_timespec_t	ts = {.sec = now, .ns = 0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
 * Purpose: updates new host status                                           *
 *                                                                            *
 ******************************************************************************/
static void	discovery_update_host_status(zbx_db_dhost *dhost, int status, int now,
		zbx_add_event_func_t add_event_cb)
{
	zbx_timespec_t	ts = {.sec = now, .ns = 0};

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
 * Purpose: find host by ip                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_discovery_find_host_server(const zbx_uint64_t druleid, const char *ip, zbx_db_dhost *dhost)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*ip_esc, sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ip_esc = zbx_db_dyn_escape_field("dservices", "ip", ip);
	zbx_snprintf(sql, sizeof(sql),
			"select dh.dhostid,dh.status,dh.lastup,dh.lastdown"
			" from dhosts dh,dservices ds"
			" where ds.dhostid=dh.dhostid"
				" and dh.druleid=" ZBX_FS_UI64
				" and ds.ip" ZBX_SQL_STRCMP
			" order by dh.dhostid",
			druleid, ZBX_SQL_STRVAL_EQ(ip_esc));
	zbx_free(ip_esc);
	result = zbx_db_select_n(sql, 1);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(dhost->dhostid, row[0]);
		dhost->status = atoi(row[1]);
		dhost->lastup = atoi(row[2]);
		dhost->lastdown = atoi(row[3]);
	}

	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process new host status                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_discovery_update_host_server(void *handle, zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip,
		const char *dns, int status, time_t now, zbx_add_event_func_t add_event_cb)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(handle);
	ZBX_UNUSED(druleid);
	ZBX_UNUSED(ip);
	ZBX_UNUSED(dns);

	if (0 != dhost->dhostid)
		discovery_update_host_status(dhost, status, (int)now, add_event_cb);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes new service status                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_discovery_update_service_server(void *handle, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		zbx_uint64_t unique_dcheckid, zbx_db_dhost *dhost, const char *ip, const char *dns, int port,
		int status, const char *value, time_t now, zbx_vector_uint64_t *dserviceids,
		zbx_add_event_func_t add_event_cb)
{
	zbx_db_dservice	dservice;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s' dns:'%s' port:%d status:%d value:'%s'",
			__func__, ip, dns, port, status, value);

	ZBX_UNUSED(handle);

	memset(&dservice, 0, sizeof(dservice));

	/* register host if is not registered yet */
	if (0 == dhost->dhostid)
		discovery_register_host(druleid, dcheckid, unique_dcheckid, dhost, ip, port, status, value);

	/* register service if is not registered yet */
	if (0 != dhost->dhostid)
		discovery_register_service(dcheckid, dhost, &dservice, ip, dns, port, status);

	/* service was not registered because we do not add down service */
	if (0 != dservice.dserviceid)
	{
		discovery_update_service_status(dhost, &dservice, status, value, (int)now, add_event_cb);
		zbx_vector_uint64_append(dserviceids, dservice.dserviceid);
	}

	zbx_free(dservice.value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: mark service status DOWN for all not received service statuses    *
 *                                                                            *
 ******************************************************************************/
void	zbx_discovery_update_service_down_server(const zbx_uint64_t dhostid, const time_t now,
		zbx_vector_uint64_t *dserviceids)
{
	char	buffer[MAX_STRING_LEN], *sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() dhostid:" ZBX_FS_UI64 " dserviceids:%d now:" ZBX_FS_TIME_T,
			__func__, dhostid, dserviceids->values_num, now);

	zbx_snprintf(buffer, sizeof(buffer),
			"update dservices"
			" set status=%d,lastup=%d,lastdown=" ZBX_FS_TIME_T
			" where (status=%d or lastup<>0)"
				" and dhostid=" ZBX_FS_UI64
				" and not",
			DOBJECT_STATUS_DOWN, 0, now, DOBJECT_STATUS_UP, dhostid);

	zbx_vector_uint64_sort(dserviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_db_prepare_multiple_query(buffer, "dserviceid", dserviceids, &sql, &sql_alloc, &sql_offset);

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update drule error info                                           *
 *                                                                            *
******************************************************************************/
void	zbx_discovery_update_drule_server(void *handle, zbx_uint64_t druleid, const char *error, time_t now)
{
	char		buffer[MAX_STRING_LEN], *sql = NULL;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		is_upd = (NULL == error ? 0 : 1);
	size_t		err_len = strlen(ZBX_NULL2EMPTY_STR(error));

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() druleid:" ZBX_FS_UI64 " error len:%d", __func__, druleid, (int)err_len);

	ZBX_UNUSED(handle);
	ZBX_UNUSED(now);

	zbx_snprintf(buffer, sizeof(buffer),
			"select error"
			" from drules"
			" where druleid=" ZBX_FS_UI64
				" and error<>''",
			druleid);
	result = zbx_db_select_n(buffer, 1);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		if (NULL == error)
		{
			sql = zbx_dsprintf(sql,
					"update drules"
					" set error=''"
					" where druleid=" ZBX_FS_UI64,
					druleid);
		}

		if (err_len == strlen(row[0]) && NULL != strstr(error, row[0]))
			is_upd = 0;
	}

	zbx_db_free_result(result);

	if (0 != is_upd)
	{
		char	*err_esc = zbx_db_dyn_escape_field("drules", "error", error);

		sql = zbx_dsprintf(sql,
				"update drules"
				" set error='%s'"
				" where druleid=" ZBX_FS_UI64,
				err_esc, druleid);
		zbx_free(err_esc);
	}

	if (NULL != sql)
		zbx_db_execute("%s", sql);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	*zbx_discovery_open_server(void)
{
	return NULL;
}

void	zbx_discovery_close_server(void *handle)
{
	ZBX_UNUSED(handle);
}
