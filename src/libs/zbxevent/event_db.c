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

#include "zbxevent.h"

#include "zbx_host_constants.h"
#include "zbx_trigger_constants.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxtime.h"

#ifdef HAVE_OPENIPMI
#	define ZBX_IPMI_FIELDS_NUM	4	/* number of selected IPMI-related fields in function */
						/* zbx_event_db_get_host() */
#else
#	define ZBX_IPMI_FIELDS_NUM	0
#endif

int	zbx_event_db_get_host(const zbx_db_event *event, zbx_dc_host_t *host, char *error, size_t max_error_len)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		sql[512];	/* do not forget to adjust size if SQLs change */
	size_t		offset;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	offset = zbx_snprintf(sql, sizeof(sql), "select distinct h.hostid,h.proxyid,h.host,h.tls_connect");
#ifdef HAVE_OPENIPMI
	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
			/* do not forget to update ZBX_IPMI_FIELDS_NUM if number of selected IPMI fields changes */
			",h.ipmi_authtype,h.ipmi_privilege,h.ipmi_username,h.ipmi_password");
#endif
	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
			",h.tls_issuer,h.tls_subject,h.tls_psk_identity,h.tls_psk,h.monitored_by,hp.proxyid");
	switch (event->source)
	{
		case EVENT_SOURCE_TRIGGERS:
			zbx_snprintf(sql + offset, sizeof(sql) - offset,
					" from functions f,items i,hosts h"
					" left join host_proxy hp on h.hostid=hp.hostid"
					" where f.itemid=i.itemid"
						" and i.hostid=h.hostid"
						" and h.status=%d"
						" and f.triggerid=" ZBX_FS_UI64,
					HOST_STATUS_MONITORED, event->objectid);

			break;
		case EVENT_SOURCE_DISCOVERY:
			offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
					" from interface i,dservices ds,hosts h"
					" left join host_proxy hp on h.hostid=hp.hostid"
					" where h.hostid=i.hostid"
						" and i.ip=ds.ip"
						" and i.useip=1"
						" and h.status=%d",
						HOST_STATUS_MONITORED);

			switch (event->object)
			{
				case EVENT_OBJECT_DHOST:
					zbx_snprintf(sql + offset, sizeof(sql) - offset,
							" and ds.dhostid=" ZBX_FS_UI64, event->objectid);
					break;
				case EVENT_OBJECT_DSERVICE:
					zbx_snprintf(sql + offset, sizeof(sql) - offset,
							" and ds.dserviceid=" ZBX_FS_UI64, event->objectid);
					break;
			}
			break;
		case EVENT_SOURCE_AUTOREGISTRATION:
			zbx_snprintf(sql + offset, sizeof(sql) - offset,
					" from autoreg_host a,hosts h"
					" left join host_proxy hp on h.hostid=hp.hostid"
					" where " ZBX_SQL_NULLCMP("a.proxyid", "h.proxyid")
						" and a.host=h.host"
						" and h.status=%d"
						" and h.flags<>%d"
						" and a.autoreg_hostid=" ZBX_FS_UI64,
					HOST_STATUS_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE, event->objectid);
			break;
		default:
			zbx_snprintf(error, max_error_len, "Unsupported event source [%d]", event->source);
			return FAIL;
	}

	host->hostid = 0;

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		if (0 != host->hostid)
		{
			switch (event->source)
			{
				case EVENT_SOURCE_TRIGGERS:
					zbx_strlcpy(error, "Too many hosts in a trigger expression", max_error_len);
					break;
				case EVENT_SOURCE_DISCOVERY:
					zbx_strlcpy(error, "Too many hosts with same IP addresses", max_error_len);
					break;
			}
			ret = FAIL;
			break;
		}

		ZBX_STR2UINT64(host->hostid, row[0]);
		zbx_strscpy(host->host, row[2]);
		ZBX_STR2UCHAR(host->tls_connect, row[3]);

#ifdef HAVE_OPENIPMI
		host->ipmi_authtype = (signed char)atoi(row[4]);
		host->ipmi_privilege = (unsigned char)atoi(row[5]);
		zbx_strscpy(host->ipmi_username, row[6]);
		zbx_strscpy(host->ipmi_password, row[7]);
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_strscpy(host->tls_issuer, row[4 + ZBX_IPMI_FIELDS_NUM]);
		zbx_strscpy(host->tls_subject, row[5 + ZBX_IPMI_FIELDS_NUM]);
		zbx_strscpy(host->tls_psk_identity, row[6 + ZBX_IPMI_FIELDS_NUM]);
		zbx_strscpy(host->tls_psk, row[7 + ZBX_IPMI_FIELDS_NUM]);
#endif
		ZBX_STR2UCHAR(host->monitored_by, row[8 + ZBX_IPMI_FIELDS_NUM]);

		if (HOST_MONITORED_BY_PROXY_GROUP != host->monitored_by)
			ZBX_DBROW2UINT64(host->proxyid, row[1]);
		else
			ZBX_DBROW2UINT64(host->proxyid, row[9 + ZBX_IPMI_FIELDS_NUM]);

	}
	zbx_db_free_result(result);

	if (FAIL == ret)
	{
		host->hostid = 0;
		*host->host = '\0';
	}
	else if (0 == host->hostid)
	{
		*host->host = '\0';

		zbx_strlcpy(error, "Cannot find a corresponding host", max_error_len);
		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
#undef ZBX_IPMI_FIELDS_NUM

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve discovered host value by event and field name            *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_event_db_get_dhost(const zbx_db_event *event, char **replace_to, const char *fieldname)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;
	char		sql[MAX_STRING_LEN];

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
			zbx_snprintf(sql, sizeof(sql),
					"select %s"
					" from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid"
						" and h.dhostid=s.dhostid"
						" and h.dhostid=" ZBX_FS_UI64
					" order by s.dserviceid",
					fieldname,
					event->objectid);
			break;
		case EVENT_OBJECT_DSERVICE:
			zbx_snprintf(sql, sizeof(sql),
					"select %s"
					" from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid"
						" and h.dhostid=s.dhostid"
						" and s.dserviceid=" ZBX_FS_UI64,
					fieldname,
					event->objectid);
			break;
		default:
			return ret;
	}

	result = zbx_db_select_n(sql, 1);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, ZBX_NULL2STR(row[0]));
		ret = SUCCEED;
	}
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve number of events (acknowledged or unacknowledged) for a  *
 *          trigger (in an OK or PROBLEM state) which generated an event      *
 *                                                                            *
 * Parameters: triggerid    - [IN] trigger identifier from database           *
 *             replace_to   - [IN/OUT] pointer to result buffer               *
 *             problem_only - [IN] selected trigger status:                   *
 *                             0 - TRIGGER_VALUE_PROBLEM and TRIGGER_VALUE_OK *
 *                             1 - TRIGGER_VALUE_PROBLEM                      *
 *             acknowledged - [IN] acknowledged event or not                  *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_event_db_count_from_trigger(zbx_uint64_t triggerid, char **replace_to, int problem_only, int acknowledged)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		value[4];
	int		ret = FAIL;

	if (problem_only)
		zbx_snprintf(value, sizeof(value), "%d", TRIGGER_VALUE_PROBLEM);
	else
		zbx_snprintf(value, sizeof(value), "%d,%d", TRIGGER_VALUE_PROBLEM, TRIGGER_VALUE_OK);

	result = zbx_db_select(
			"select count(*)"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64
				" and value in (%s)"
				" and acknowledged=%d",
			EVENT_SOURCE_TRIGGERS,
			EVENT_OBJECT_TRIGGER,
			triggerid,
			value,
			acknowledged);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve discovery rule check value by event and field name       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_event_db_get_dchecks(const zbx_db_event *event, char **replace_to, const char *fieldname)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;

	switch (event->object)
	{
		case EVENT_OBJECT_DSERVICE:
			result = zbx_db_select("select %s from dchecks c,dservices s"
					" where c.dcheckid=s.dcheckid and s.dserviceid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		default:
			return ret;
	}

	if (NULL != (row = zbx_db_fetch(result)) && SUCCEED != zbx_db_is_null(row[0]))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve discovered service value by event and field name         *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_event_db_get_dservice(const zbx_db_event *event, char **replace_to, const char *fieldname)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;

	switch (event->object)
	{
		case EVENT_OBJECT_DSERVICE:
			result = zbx_db_select("select %s from dservices s where s.dserviceid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		default:
			return ret;
	}

	if (NULL != (row = zbx_db_fetch(result)) && SUCCEED != zbx_db_is_null(row[0]))
	{
		*replace_to = zbx_strdup(*replace_to, row[0]);
		ret = SUCCEED;
	}
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve discovery rule value by event and field name             *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_event_db_get_drule(const zbx_db_event *event, char **replace_to, const char *fieldname)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;

	if (EVENT_SOURCE_DISCOVERY != event->source)
		return FAIL;

	switch (event->object)
	{
		case EVENT_OBJECT_DHOST:
			result = zbx_db_select("select r.%s from drules r,dhosts h"
					" where r.druleid=h.druleid and h.dhostid=" ZBX_FS_UI64,
					fieldname, event->objectid);
			break;
		case EVENT_OBJECT_DSERVICE:
			result = zbx_db_select("select r.%s from drules r,dhosts h,dservices s"
					" where r.druleid=h.druleid and h.dhostid=s.dhostid and s.dserviceid="
					ZBX_FS_UI64, fieldname, event->objectid);
			break;
		default:
			return ret;
	}

	if (NULL != (row = zbx_db_fetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, ZBX_NULL2STR(row[0]));
		ret = SUCCEED;
	}
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request value from autoreg_host table by event                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_event_db_get_autoreg(const zbx_db_event *event, char **replace_to, const char *fieldname)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;

	result = zbx_db_select(
			"select %s"
			" from autoreg_host"
			" where autoreg_hostid=" ZBX_FS_UI64, fieldname, event->objectid);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		*replace_to = zbx_strdup(*replace_to, ZBX_NULL2STR(row[0]));
		ret = SUCCEED;
	}
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve event acknowledges history                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_event_db_get_history(const zbx_db_event *event, char **replace_to,
		const zbx_uint64_t *recipient_userid, const char *tz)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*buf = NULL;
	size_t		buf_alloc = ZBX_KIBIBYTE, buf_offset = 0;

	buf = (char *)zbx_malloc(buf, buf_alloc);
	*buf = '\0';

	result = zbx_db_select("select clock,userid,message,action,old_severity,new_severity,suppress_until"
			" from acknowledges"
			" where eventid=" ZBX_FS_UI64 " order by clock",
			event->eventid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		const char	*user_name;
		char		*actions = NULL;
		zbx_db_acknowledge	ack;

		ack.clock = atoi(row[0]);
		ZBX_STR2UINT64(ack.userid, row[1]);
		ack.message = row[2];
		ack.acknowledgeid = 0;
		ack.action = atoi(row[3]);
		ack.old_severity = atoi(row[4]);
		ack.new_severity = atoi(row[5]);
		ack.suppress_until = atoi(row[6]);

		if (SUCCEED == zbx_check_user_permissions(&ack.userid, recipient_userid))
			user_name = zbx_user_string(ack.userid);
		else
			user_name = "Inaccessible user";

		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset,
				"%s %s \"%s\"\n",
				zbx_date2str(ack.clock, tz),
				zbx_time2str(ack.clock, tz),
				user_name);

		if (SUCCEED == zbx_problem_get_actions(&ack, ZBX_PROBLEM_UPDATE_ACKNOWLEDGE |
					ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE |
					ZBX_PROBLEM_UPDATE_CLOSE | ZBX_PROBLEM_UPDATE_SEVERITY |
					ZBX_PROBLEM_UPDATE_SUPPRESS | ZBX_PROBLEM_UPDATE_UNSUPPRESS |
					ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE | ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM,
					tz, &actions))
		{
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "Actions: %s.\n", actions);
			zbx_free(actions);
		}

		if ('\0' != *ack.message)
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%s\n", ack.message);

		zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, '\n');
	}
	zbx_db_free_result(result);

	if (0 != buf_offset)
	{
		buf_offset -= 2;
		buf[buf_offset] = '\0';
	}

	*replace_to = buf;
}
