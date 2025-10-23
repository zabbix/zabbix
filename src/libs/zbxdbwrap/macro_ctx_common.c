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

#include "zbxdbwrap.h"

#include "zbxcacheconfig.h"
#include "zbx_expression_constants.h"
#include "zbx_item_constants.h"
#include "zbx_trigger_constants.h"
#include "zbxevent.h"
#include "zbxcalc.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxexpr.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxtime.h"

/******************************************************************************
 *                                                                            *
 * Purpose: request action value by macro.                                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	expr_db_get_action_value(const char *macro, zbx_uint64_t actionid, char **replace_to)
{
	int	ret = SUCCEED;

	if (0 == strcmp(macro, MVAR_ACTION_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, actionid);
	}
	else if (0 == strcmp(macro, MVAR_ACTION_NAME))
	{
		zbx_db_result_t	result;
		zbx_db_row_t	row;

		if (NULL == (result = zbx_db_select("select name from actions where actionid=" ZBX_FS_UI64, actionid)))
			return FAIL;

		if (NULL != (row = zbx_db_fetch(result)))
			*replace_to = zbx_strdup(*replace_to, row[0]);
		else
			ret = FAIL;

		zbx_db_free_result(result);
	}

	return ret;
}

static const char	*alert_type_string(unsigned char type)
{
	switch (type)
	{
		case ALERT_TYPE_MESSAGE:
			return "message";
		default:
			return "script";
	}
}

static const char	*alert_status_string(unsigned char type, unsigned char status)
{
	switch (status)
	{
		case ALERT_STATUS_SENT:
			return (ALERT_TYPE_MESSAGE == type ? "sent" : "executed");
		case ALERT_STATUS_NOT_SENT:
			return "in progress";
		default:
			return "failed";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve escalation history.                                      *
 *                                                                            *
 ******************************************************************************/
static void	expr_db_get_escalation_history(zbx_uint64_t actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, char **replace_to, const zbx_uint64_t *recipient_userid, const char *tz)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*buf = NULL, *p;
	size_t		buf_alloc = ZBX_KIBIBYTE, buf_offset = 0;
	int		esc_step;
	unsigned char	type, status;
	time_t		now;
	zbx_uint64_t	userid;

	buf = (char *)zbx_malloc(buf, buf_alloc);

	zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "Problem started: %s %s Age: %s\n",
			zbx_date2str(event->clock, tz), zbx_time2str(event->clock, tz),
			zbx_age2str(time(NULL) - event->clock));

	result = zbx_db_select(
			"select a.clock,a.alerttype,a.status,mt.name,a.sendto,a.error,a.esc_step,a.userid,a.message"
			" from alerts a"
			" left join media_type mt"
				" on mt.mediatypeid=a.mediatypeid"
			" where a.eventid=" ZBX_FS_UI64
				" and a.actionid=" ZBX_FS_UI64
			" order by a.clock",
			event->eventid, actionid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		int	user_permit;

		now = atoi(row[0]);
		type = (unsigned char)atoi(row[1]);
		status = (unsigned char)atoi(row[2]);
		esc_step = atoi(row[6]);
		ZBX_DBROW2UINT64(userid, row[7]);
		user_permit = zbx_check_user_permissions(&userid, recipient_userid);

		if (0 != esc_step)
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%d. ", esc_step);

		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "%s %s %-7s %-11s",
				zbx_date2str(now, tz), zbx_time2str(now, tz),	/* date, time */
				alert_type_string(type),		/* alert type */
				alert_status_string(type, status));	/* alert status */

		if (ALERT_TYPE_COMMAND == type)
		{
			if (NULL != (p = strchr(row[8], ':')))
			{
				*p = '\0';
				zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " \"%s\"", row[8]);	/* host */
				*p = ':';
			}
		}
		else
		{
			const char	*media_type_name, *send_to, *user_name;

			media_type_name = (SUCCEED == zbx_db_is_null(row[3]) ? "" : row[3]);

			if (SUCCEED == user_permit)
			{
				send_to = row[4];
				user_name = zbx_user_string(userid);
			}
			else
			{
				send_to = "\"Inaccessible recipient details\"";
				user_name = "Inaccessible user";
			}

			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s %s \"%s\"",
					media_type_name,
					send_to,	/* historical recipient */
					user_name);	/* alert user full name */
		}

		if (ALERT_STATUS_FAILED == status)
		{
			/* alert error can be generated by SMTP Relay or other media and contain sensitive details */
			if (SUCCEED == user_permit)
				zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, " %s", row[5]);
			else
				zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, " \"Inaccessible error message\"");
		}

		zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, '\n');
	}
	zbx_db_free_result(result);

	if (NULL != r_event)
	{
		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "Problem ended: %s %s\n",
				zbx_date2str(r_event->clock, tz), zbx_time2str(r_event->clock, tz));
	}

	if (0 != buf_offset)
		buf[--buf_offset] = '\0';

	*replace_to = buf;
}

static const char	*trigger_state_string(int state)
{
	switch (state)
	{
		case TRIGGER_STATE_NORMAL:
			return "Normal";
		case TRIGGER_STATE_UNKNOWN:
			return "Unknown";
		default:
			return "unknown";
	}
}

static const char	*item_state_string(int state)
{
	switch (state)
	{
		case ITEM_STATE_NORMAL:
			return "Normal";
		case ITEM_STATE_NOTSUPPORTED:
			return "Not supported";
		default:
			return "unknown";
	}
}

static const char	*event_value_string(int source, int object, int value)
{
	if (EVENT_SOURCE_TRIGGERS == source || EVENT_SOURCE_SERVICE == source)
	{
		switch (value)
		{
			case EVENT_STATUS_PROBLEM:
				return "PROBLEM";
			case EVENT_STATUS_RESOLVED:
				return "RESOLVED";
			default:
				return "unknown";
		}
	}

	if (EVENT_SOURCE_INTERNAL == source)
	{
		switch (object)
		{
			case EVENT_OBJECT_TRIGGER:
				return trigger_state_string(value);
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				return item_state_string(value);
		}
	}

	return "unknown";
}

/******************************************************************************
 *                                                                            *
 * Purpose: request recovery event value by macro.                            *
 *                                                                            *
 ******************************************************************************/
static void	get_recovery_event_value(const char *macro, const zbx_db_event *r_event, char **replace_to,
		const char *tz)
{
	if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_DATE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_date2str(r_event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, r_event->eventid);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_STATUS))
	{
		*replace_to = zbx_strdup(*replace_to,
				event_value_string(r_event->source, r_event->object, r_event->value));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_TIME))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_time2str(r_event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_TIMESTAMP))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", r_event->clock);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_VALUE))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", r_event->value);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_NAME))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%s", r_event->name);
	}
	else if (EVENT_SOURCE_TRIGGERS == r_event->source || EVENT_SOURCE_INTERNAL == r_event->source ||
			EVENT_SOURCE_SERVICE == r_event->source)
	{
		if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_TAGS))
			zbx_event_get_str_tags(r_event, replace_to);
		else if (0 == strcmp(macro, MVAR_EVENT_RECOVERY_TAGSJSON))
			zbx_event_get_json_tags(r_event, replace_to);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a particular attribute of a log value.                   *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_history_log_value(const char *m, const zbx_db_trigger *trigger, char **replace_to, int N_functionid,
		int clock, int ns, const char *tz)
{
	zbx_uint64_t	itemid;
	int		ret = FAIL, request;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == strcmp(m, MVAR_ITEM_LOG_AGE))
	{
		request = ZBX_DC_REQUEST_ITEM_LOG_AGE;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_DATE))
	{
		request = ZBX_DC_REQUEST_ITEM_LOG_DATE;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_EVENTID))
	{
		request = ZBX_DC_REQUEST_ITEM_LOG_EVENTID;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_NSEVERITY))
	{
		request = ZBX_DC_REQUEST_ITEM_LOG_NSEVERITY;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_SEVERITY))
	{
		request = ZBX_DC_REQUEST_ITEM_LOG_SEVERITY;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_SOURCE))
	{
		request = ZBX_DC_REQUEST_ITEM_LOG_SOURCE;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_TIME))
	{
		request = ZBX_DC_REQUEST_ITEM_LOG_TIME;
	}
	else if (0 == strcmp(m, MVAR_ITEM_LOG_TIMESTAMP))
	{
		request = ZBX_DC_REQUEST_ITEM_LOG_TIMESTAMP;
	}
	else
		goto out;

	if (SUCCEED == (ret = zbx_db_trigger_get_itemid(trigger, N_functionid, &itemid)))
		ret = zbx_dc_get_history_log_value(itemid, replace_to, request, clock, ns, tz);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	macro_trigger_common_resolv(zbx_macro_resolv_data_t *p, const zbx_dc_um_handle_t *um_handle,
		const zbx_db_event *event, const char *tz, char **replace_to)
{
	int	ret = SUCCEED;

	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			const zbx_vector_uint64_t	**phostids =
					(const zbx_vector_uint64_t **)zbx_expr_rem(&event->trigger,
					sizeof(const zbx_vector_uint64_t *), NULL, NULL);

			if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, phostids))
			{
				zbx_dc_get_user_macro(um_handle, p->macro, (*phostids)->values,
						(*phostids)->values_num, replace_to);
			}
			p->pos = (int)p->token.loc.r;
		}
		else if (ZBX_TOKEN_REFERENCE == p->token.type)
		{
			if (SUCCEED != zbx_db_trigger_get_constant(&event->trigger,
					p->token.data.reference.index, replace_to))
			{
				/* expansion failed, reference substitution is impossible */
				p->token_search &= ~ZBX_TOKEN_SEARCH_REFERENCES;

				return SUCCEED_PARTIAL; /* move to the next macro */
			}
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_HOST);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_IP);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DNS))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_DNS);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_CONN);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_PORT);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_VALUE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_TIMESTAMP))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_TIMESTAMP);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_TIME))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_TIME);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_DATE))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_DATE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_AGE))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_AGE);
		}
		else if (0 == strncmp(p->macro, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
		{
			ret = zbx_get_history_log_value(p->macro, &event->trigger, replace_to, p->index, event->clock,
					event->ns, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_VALUE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_TIMESTAMP))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_TIMESTAMP);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_TIME))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_TIME);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_DATE))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_DATE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_AGE))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_AGE);
		}
	}

	return ret;
}

int	zbx_macro_event_name_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to,
		char **data, char *error, size_t maxerrlen)
{
	int	ret = SUCCEED;

	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const zbx_db_event		*event = va_arg(args, const zbx_db_event *);
	const char			*tz = va_arg(args, const char *);

	if (ZBX_TOKEN_EXPRESSION_MACRO == p->inner_token.type)
	{
		zbx_timespec_t	ts;
		char		*errmsg = NULL;

		ts.sec = event->clock;
		ts.ns = event->ns;

		if (SUCCEED != (ret = zbx_db_get_expression_macro_result(event, *data,
				&p->inner_token.data.expression_macro.expression, &ts, replace_to, &errmsg)))
		{
			*errmsg = (char)tolower(*errmsg);
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot evaluate expression macro: %s", __func__, errmsg);
			zbx_strlcpy(error, errmsg, maxerrlen);
			zbx_free(errmsg);
		}
	}
	else
	{
		ret = macro_trigger_common_resolv(p, um_handle, event, tz, replace_to);

		if (ret == SUCCEED && EVENT_OBJECT_TRIGGER == event->object)
		{
			if (0 == strcmp(p->macro, MVAR_TIME))
			{
				*replace_to = zbx_strdup(*replace_to, zbx_time2str(time(NULL), tz));
			}
			else if (0 == strcmp(p->macro, MVAR_TIMESTAMP))
			{
				*replace_to = zbx_dsprintf(*replace_to, "%ld", (long)time(NULL));
			}
			else if (0 == strcmp(p->macro, MVAR_TRIGGER_EXPRESSION_EXPLAIN))
			{
				zbx_db_trigger_explain_expression(&event->trigger, replace_to, zbx_evaluate_function,
						0);
			}
			else if (0 == strcmp(p->macro, MVAR_FUNCTION_VALUE))
			{
				zbx_db_trigger_get_function_value(&event->trigger, p->index, replace_to,
						zbx_evaluate_function, 0);
			}
			else if (0 == strcmp(p->macro, MVAR_FUNCTION_RECOVERY_VALUE))
			{
				zbx_db_trigger_get_function_value(&event->trigger, p->index, replace_to,
						zbx_evaluate_function, 1);
			}
		}
	}

	return ret;
}

int	zbx_macro_trigger_desc_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to,
		char **data, char *error, size_t maxerrlen)
{
	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const zbx_db_event		*event = va_arg(args, const zbx_db_event *);
	const char			*tz = va_arg(args, const char *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	return macro_trigger_common_resolv(p, um_handle, event, tz, replace_to);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve {EVENT.OPDATA} macro.                                     *
 *                                                                            *
 ******************************************************************************/
static void	resolve_opdata(const zbx_db_event *event, zbx_dc_um_handle_t *um_handle, char **replace_to,
		const char *tz, char *error, size_t maxerrlen)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if ('\0' == *event->trigger.opdata)
	{
		int			i;
		zbx_vector_uint64_t	itemids;
		zbx_timespec_t		ts;

		ts.sec = time(NULL);
		ts.ns = 999999999;

		zbx_vector_uint64_create(&itemids);
		zbx_db_trigger_get_itemids(&event->trigger, &itemids);

		for (i = 0; i < itemids.values_num; i++)
		{
			char	*val = NULL;

			if (NULL != *replace_to)
				*replace_to = zbx_strdcat(*replace_to, ", ");

			if (SUCCEED == zbx_db_item_get_value(itemids.values[i], &val, 0, &ts, NULL))
			{
				*replace_to = zbx_strdcat(*replace_to, val);
				zbx_free(val);
			}
			else
				*replace_to = zbx_strdcat(*replace_to, STR_UNKNOWN_VARIABLE);
		}

		zbx_vector_uint64_destroy(&itemids);
	}
	else
	{
		*replace_to = zbx_strdup(*replace_to, event->trigger.opdata);
		zbx_substitute_macros_ext_search(ZBX_TOKEN_SEARCH_REFERENCES, replace_to, error, maxerrlen,
				&zbx_macro_trigger_desc_resolv, um_handle, event, tz);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: request cause event value by macro.                               *
 *                                                                            *
 ******************************************************************************/
static int	get_event_cause_value(const char *macro, char **replace_to, zbx_dc_um_handle_t *um_handle,
		const zbx_db_event *event, zbx_db_event **cause_event, zbx_db_event **cause_recovery_event,
		const zbx_uint64_t *recipient_userid, const char *tz, char *error, size_t maxerrlen)
{
	zbx_db_event	*c_event;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid = " ZBX_FS_UI64 ", event name = '%s'", __func__, event->eventid,
			event->name);

	if (NULL == *cause_event)
		zbx_db_prepare_empty_event(zbx_db_get_cause_eventid(event->eventid), cause_event);

	if (0 == (*cause_event)->eventid)
		goto out;

	if (0 == strcmp(macro, MVAR_EVENT_CAUSE_DURATION) ||
			0 == strcmp(macro, MVAR_EVENT_CAUSE_STATUS) ||
			0 == strcmp(macro, MVAR_EVENT_CAUSE_VALUE) ||
			0 == strcmp(macro, MVAR_EVENT_CAUSE_OPDATA))
	{
		if (NULL == *cause_recovery_event)
		{
			zbx_vector_uint64_t		eventids, r_eventids;
			zbx_vector_uint64_pair_t	dummy_event_pairs;

			zbx_vector_uint64_create(&eventids);
			zbx_vector_uint64_create(&r_eventids);
			zbx_vector_uint64_pair_create(&dummy_event_pairs);

			zbx_vector_uint64_append(&eventids, (*cause_event)->eventid);
			zbx_db_get_eventid_r_eventid_pairs(&eventids, &dummy_event_pairs, &r_eventids);

			zbx_db_prepare_empty_event(0 != r_eventids.values_num ? r_eventids.values[0] : 0,
					cause_recovery_event);

			zbx_vector_uint64_destroy(&eventids);
			zbx_vector_uint64_destroy(&r_eventids);
			zbx_vector_uint64_pair_destroy(&dummy_event_pairs);
		}

		c_event = (0 != (*cause_recovery_event)->eventid) ? *cause_recovery_event : *cause_event;
	}
	else
		c_event = *cause_event;

	zbx_db_get_event_data_core(c_event);

	if (0 == (ZBX_FLAGS_DB_EVENT_RETRIEVED_CORE & c_event->flags))
		goto out;

	if (0 == strcmp(macro, MVAR_EVENT_CAUSE_UPDATE_HISTORY))
	{
		zbx_event_db_get_history(c_event, replace_to, recipient_userid, tz);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_ACK_STATUS))
	{
		*replace_to = zbx_strdup(*replace_to, c_event->acknowledged ? "Yes" : "No");
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_AGE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - c_event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_DATE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_date2str(c_event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_DURATION))
	{
		if (NULL != cause_recovery_event && 0 != (*cause_recovery_event)->eventid)
		{
			*replace_to = zbx_strdup(*replace_to, zbx_age2str((*cause_recovery_event)->clock -
					c_event->clock));
		}
		else
			*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - c_event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, c_event->eventid);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_NAME))
	{
		*replace_to = zbx_strdup(*replace_to, c_event->name);
	}
	if (0 == strcmp(macro, MVAR_EVENT_CAUSE_STATUS))
	{
		*replace_to = zbx_strdup(*replace_to, event_value_string((unsigned char)c_event->source,
				(unsigned char)c_event->object, (unsigned char)c_event->value));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_TAGS))
	{
		zbx_db_get_event_data_tags(c_event);
		zbx_event_get_str_tags(c_event, replace_to);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_TAGSJSON))
	{
		zbx_db_get_event_data_tags(c_event);
		zbx_event_get_json_tags(c_event, replace_to);
	}
	else if (0 == strncmp(macro, MVAR_EVENT_CAUSE_TAGS_PREFIX, ZBX_CONST_STRLEN(MVAR_EVENT_CAUSE_TAGS_PREFIX)))
	{
		zbx_db_get_event_data_tags(c_event);
		zbx_event_get_tag(macro + ZBX_CONST_STRLEN(MVAR_EVENT_CAUSE_TAGS_PREFIX), c_event, replace_to);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_TIME))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_time2str(c_event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_TIMESTAMP))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", c_event->clock);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_VALUE))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", c_event->value);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_SEVERITY))
	{
		if (FAIL == zbx_config_get_trigger_severity_name(c_event->severity, replace_to))
			*replace_to = zbx_strdup(*replace_to, "unknown");
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_NSEVERITY))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", (int)c_event->severity);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_OBJECT))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", c_event->object);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_SOURCE))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", c_event->source);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_CAUSE_OPDATA))
	{
		zbx_db_get_event_data_tags(c_event);
		zbx_db_get_event_data_triggers(c_event);

		if (0 == (ZBX_FLAGS_DB_EVENT_RETRIEVED_TRIGGERS & c_event->flags))
			goto out;

		resolve_opdata(c_event, um_handle, replace_to, tz, error, maxerrlen);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compose temporary vector containing event data.                   *
 *                                                                            *
 ******************************************************************************/
static void	eventdata_compose(const zbx_vector_db_event_t *events, zbx_vector_eventdata_t *vect_eventdata)
{
	for (int i = 0; i < events->values_num; i++)
	{
		int		ret;
		zbx_db_event	*event;
		zbx_eventdata_t	eventdata = {0};

		event = events->values[i];

		if (FAIL == (ret = zbx_db_with_trigger_itemid(&event->trigger, &eventdata.host, 1,
				&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_HOST)))
		{
			goto fail;
		}

		eventdata.nseverity = event->severity;
		if (FAIL == (ret = zbx_config_get_trigger_severity_name(event->severity, &eventdata.severity)))
			goto fail;

		zbx_event_get_str_tags(event, &eventdata.tags);
		eventdata.name = event->name;
		eventdata.clock = event->clock;
fail:
		if (FAIL == ret)
			zbx_eventdata_free(&eventdata);
		else
			zbx_vector_eventdata_append(vect_eventdata, eventdata);
	}
}

static int	expr_db_get_event_symptoms(const zbx_db_event *event, char **replace_to)
{
	int			ret = FAIL;
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	zbx_vector_uint64_t	symptom_eventids;

	if (NULL == (result = zbx_db_select("select eventid from event_symptom where cause_eventid=" ZBX_FS_UI64,
			event->eventid)))
	{
		return FAIL;
	}

	zbx_vector_uint64_create(&symptom_eventids);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	symptom_eventid;

		ZBX_STR2UINT64(symptom_eventid, row[0]);
		zbx_vector_uint64_append(&symptom_eventids, symptom_eventid);
	}
	zbx_db_free_result(result);

	if (symptom_eventids.values_num > 0)
	{
		zbx_vector_eventdata_t	symptoms;
		zbx_vector_db_event_t	symptom_events;

		zbx_vector_eventdata_create(&symptoms);
		zbx_vector_db_event_create(&symptom_events);

		zbx_db_get_events_by_eventids(&symptom_eventids, &symptom_events);
		eventdata_compose(&symptom_events, &symptoms);
		zbx_vector_eventdata_sort(&symptoms, (zbx_compare_func_t)zbx_eventdata_compare);
		ret = zbx_eventdata_to_str(&symptoms, replace_to);

		for (int i = 0; i < symptoms.values_num; i++)
			zbx_eventdata_free(&symptoms.values[i]);

		zbx_vector_eventdata_destroy(&symptoms);

		zbx_vector_db_event_clear_ext(&symptom_events, zbx_db_free_event);
		zbx_vector_db_event_destroy(&symptom_events);
	}

	zbx_vector_uint64_destroy(&symptom_eventids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request current event value by macro.                             *
 *                                                                            *
 ******************************************************************************/
static void	get_current_event_value(const char *macro, const zbx_db_event *event, char **replace_to)
{
	if (0 == strcmp(macro, MVAR_EVENT_STATUS))
	{
		*replace_to = zbx_strdup(*replace_to,
				event_value_string(event->source, event->object, event->value));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_VALUE))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", event->value);
	}
}

static int	expr_get_proxy_name_description(zbx_uint64_t itemid, int request, char **replace_to)
{
	int		errcode, ret = FAIL;
	zbx_dc_host_t	dc_host;

	zbx_dc_config_get_hosts_by_itemids(&dc_host, &itemid, &errcode, 1);

	if (SUCCEED == errcode)
	{
		if (0 == dc_host.proxyid)
		{
			*replace_to = zbx_strdup(*replace_to, "");
			ret = SUCCEED;
		}
		else
		{
			ret = zbx_db_get_proxy_value(dc_host.proxyid, replace_to,
					ZBX_DB_REQUEST_PROXY_NAME == request ? "name" : "description");
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get comma-space separated host group names in which the trigger   *
 *          is defined.                                                       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	expr_db_get_trigger_hostgroup_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid,
		char **replace_to)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;
	char		*sql = NULL;
	size_t		replace_to_alloc = 64, replace_to_offset = 0,
			sql_alloc = 256, sql_offset = 0;
	int		user_type = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != userid)
	{
		result = zbx_db_select("select r.type from users u,role r where u.roleid=r.roleid and"
				" userid=" ZBX_FS_UI64, *userid);

		if (NULL != (row = zbx_db_fetch(result)) && FAIL == zbx_db_is_null(row[0]))
			user_type = atoi(row[0]);
		zbx_db_free_result(result);

		if (-1 == user_type)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot check permissions", __func__);
			goto out;
		}
	}

	*replace_to = (char *)zbx_realloc(*replace_to, replace_to_alloc);
	**replace_to = '\0';

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct g.name"
			" from hstgrp g,hosts_groups hg,items i,functions f"
			" where g.groupid=hg.groupid"
				" and hg.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);
	if (NULL != userid && USER_TYPE_SUPER_ADMIN != user_type)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and exists("
					"select null"
					" from host_hgset hh,permission p,user_ugset uu"
					" where i.hostid=hh.hostid"
						" and hh.hgsetid=p.hgsetid"
						" and p.ugsetid=uu.ugsetid"
						" and uu.userid=" ZBX_FS_UI64
				")",
				*userid);
	}
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by g.name");

	result = zbx_db_select("%s", sql);

	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		if (0 != replace_to_offset)
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ", ");
		zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, row[0]);
		ret = SUCCEED;
	}
	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static const char	*trigger_value_string(unsigned char value)
{
	switch (value)
	{
		case TRIGGER_VALUE_PROBLEM:
			return "PROBLEM";
		case TRIGGER_VALUE_OK:
			return "OK";
		default:
			return "unknown";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get template trigger ID from which the trigger is inherited.      *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	expr_db_get_templateid_by_triggerid(zbx_uint64_t triggerid, zbx_uint64_t *templateid)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;

	result = zbx_db_select(
			"select templateid"
			" from triggers"
			" where triggerid=" ZBX_FS_UI64,
			triggerid);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_DBROW2UINT64(*templateid, row[0]);
		ret = SUCCEED;
	}
	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get comma-space separated trigger template names in which         *
 *          the trigger is defined.                                           *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 * Comments: based on the patch submitted by Hmami Mohamed.                   *
 *                                                                            *
 ******************************************************************************/
static int	expr_db_get_trigger_template_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;
	zbx_uint64_t	templateid;
	char		*sql = NULL;
	size_t		replace_to_alloc = 64, replace_to_offset = 0,
			sql_alloc = 256, sql_offset = 0;
	int		user_type = -1;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != userid)
	{
		result = zbx_db_select("select r.type from users u,role r where u.roleid=r.roleid and"
				" userid=" ZBX_FS_UI64, *userid);

		if (NULL != (row = zbx_db_fetch(result)) && FAIL == zbx_db_is_null(row[0]))
			user_type = atoi(row[0]);
		zbx_db_free_result(result);

		if (-1 == user_type)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot check permissions", __func__);
			goto out;
		}
	}

	/* use parent trigger ID for lld generated triggers */
	result = zbx_db_select(
			"select parent_triggerid"
			" from trigger_discovery"
			" where triggerid=" ZBX_FS_UI64,
			triggerid);

	if (NULL != (row = zbx_db_fetch(result)))
		ZBX_STR2UINT64(triggerid, row[0]);
	zbx_db_free_result(result);

	if (SUCCEED != expr_db_get_templateid_by_triggerid(triggerid, &templateid) || 0 == templateid)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trigger not found or not templated", __func__);
		goto out;
	}

	do
	{
		triggerid = templateid;
	}
	while (SUCCEED == (ret = expr_db_get_templateid_by_triggerid(triggerid, &templateid)) && 0 != templateid);

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() trigger not found", __func__);
		goto out;
	}

	*replace_to = (char *)zbx_realloc(*replace_to, replace_to_alloc);
	**replace_to = '\0';

	sql = (char *)zbx_malloc(sql, sql_alloc);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct h.name"
			" from hosts h,items i,functions f"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=" ZBX_FS_UI64,
			triggerid);

	if (NULL != userid && USER_TYPE_SUPER_ADMIN != user_type)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				" and exists("
					"select null"
					" from host_hgset hh,permission p,user_ugset uu"
					" where h.hostid=hh.hostid"
						" and hh.hgsetid=p.hgsetid"
						" and p.ugsetid=uu.ugsetid"
						" and uu.userid=" ZBX_FS_UI64
				")",
				*userid);
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by h.name");

	result = zbx_db_select("%s", sql);

	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		if (0 != replace_to_offset)
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ", ");
		zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, row[0]);
	}
	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	expr_db_get_trigger_error(const zbx_db_trigger *trigger, char **replace_to)
{
	int		ret = SUCCEED;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == (result = zbx_db_select("select error from triggers where triggerid=" ZBX_FS_UI64,
			trigger->triggerid)))
	{
		ret = FAIL;
		goto out;
	}

	*replace_to = zbx_strdup(*replace_to, (NULL == (row = zbx_db_fetch(result))) ?  "" : row[0]);

	zbx_db_free_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static const char	*zbx_dobject_status2str(int st)
{
	switch (st)
	{
		case DOBJECT_STATUS_UP:
			return "UP";
		case DOBJECT_STATUS_DOWN:
			return "DOWN";
		case DOBJECT_STATUS_DISCOVER:
			return "DISCOVERED";
		case DOBJECT_STATUS_LOST:
			return "LOST";
		default:
			return "UNKNOWN";
	}
}

static int	resolve_host_target_macros(const char *m, const zbx_dc_host_t *dc_host, char **replace_to)
{
	int	ret = SUCCEED;

	if (NULL == dc_host)
		return SUCCEED;

	if (0 == strcmp(m, MVAR_HOST_TARGET_DNS))
	{
		ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_DC_REQUEST_HOST_DNS);
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_CONN))
	{
		ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_DC_REQUEST_HOST_CONN);
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_HOST))
	{
		*replace_to = zbx_strdup(*replace_to, dc_host->host);
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_IP))
	{
		ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_DC_REQUEST_HOST_IP);
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_NAME))
	{
		*replace_to = zbx_strdup(*replace_to, dc_host->name);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get root cause of service being in problem state.                 *
 *                                                                            *
 ******************************************************************************/
static void	expr_db_get_rootcause(const zbx_db_service *service, char **replace_to)
{
	zbx_vector_eventdata_t	rootcauses;

	zbx_vector_eventdata_create(&rootcauses);

	eventdata_compose(&service->events, &rootcauses);
	zbx_vector_eventdata_sort(&rootcauses, (zbx_compare_func_t)zbx_eventdata_compare);
	zbx_eventdata_to_str(&rootcauses, replace_to);

	for (int i = 0; i < rootcauses.values_num; i++)
		zbx_eventdata_free(&rootcauses.values[i]);

	zbx_vector_eventdata_destroy(&rootcauses);
}

static int	macro_trigger_url_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data,
		char *error, size_t maxerrlen)
{
	int	ret = SUCCEED;

	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const zbx_db_event		*event = va_arg(args, const zbx_db_event *);
	const zbx_uint64_t		*userid = va_arg(args, const zbx_uint64_t *);
	const char			*tz = va_arg(args, const char *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			const zbx_vector_uint64_t	**phostids =
					(const zbx_vector_uint64_t **)zbx_expr_rem(&event->trigger,
					sizeof(const zbx_vector_uint64_t *), NULL, NULL);

			if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, phostids))
			{
				zbx_dc_get_user_macro(um_handle, p->macro, (*phostids)->values, (*phostids)->values_num,
						replace_to);
			}
			p->pos = p->token.loc.r;
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_ID))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_ID);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_HOST);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index, &zbx_dc_get_host_value,
					ZBX_DC_REQUEST_HOST_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_IP))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_IP);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DNS))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_DNS);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_CONN);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_PORT);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_ID))
		{
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, event->objectid);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_VALUE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_TIMESTAMP))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_TIMESTAMP);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_TIME))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_TIME);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_DATE))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_DATE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_AGE))
		{
			ret = zbx_db_item_lastvalue(&event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_AGE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_VALUE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_TIMESTAMP))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_TIMESTAMP);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_TIME))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_TIME);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_DATE))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_DATE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_AGE))
		{
			ret = zbx_db_item_value(&event->trigger, replace_to, p->index, event->clock, event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_AGE);
		}
		else if (0 == strncmp(p->macro, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
		{
			ret = zbx_get_history_log_value(p->macro, &event->trigger, replace_to, p->index, event->clock,
					event->ns, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_ID))
		{
			zbx_event_get_macro_value(p->macro, event, replace_to, userid, NULL, NULL);
		}
	}

	return ret;
}

static void	db_event_ptr_clean(zbx_db_event **event)
{
	if (NULL != *event)
		zbx_db_free_event(*event);
}

static void	vector_uint64_create_rem_wrap(void *ptr)
{
	zbx_vector_uint64_create((zbx_vector_uint64_t *)ptr);
}

static void	vector_uint64_destroy_rem_wrap(void *ptr)
{
	zbx_vector_uint64_destroy((zbx_vector_uint64_t *)ptr);
}

static void	db_event_ptr_clean_rem_wrap(void *ptr)
{
	db_event_ptr_clean((zbx_db_event **)ptr);
}

/******************************************************************************
 *                                                                            *
 * Purpose: common macro resolver for messages and scripts                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_macro_message_common_resolv(zbx_macro_resolv_data_t *p, zbx_dc_um_handle_t	*um_handle,
		const zbx_uint64_t *actionid, const zbx_db_event *event, const zbx_db_event *r_event,
		const zbx_uint64_t *userid, const zbx_dc_host_t *dc_host, const zbx_db_alert *alert,
		const zbx_service_alarm_t *service_alarm, const zbx_db_service *service, const char *tz,
		char **replace_to, char **data, char *error, size_t maxerrlen)
{
	int			ret = SUCCEED;
	const zbx_db_event	*c_event = ((NULL != r_event) ? r_event : event);

	if (EVENT_SOURCE_TRIGGERS == c_event->source)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			if (NULL == dc_host)
			{
				const zbx_vector_uint64_t	**c_event_hosts =
						(const zbx_vector_uint64_t **)zbx_expr_rem(
						&c_event->trigger, sizeof(const zbx_vector_uint64_t *), NULL, NULL);

				if (SUCCEED == zbx_db_trigger_get_all_hostids(&c_event->trigger, c_event_hosts))
				{
					zbx_dc_get_user_macro(um_handle, p->macro, (*c_event_hosts)->values,
							(*c_event_hosts)->values_num, replace_to);
				}
			}
			else
				zbx_dc_get_user_macro(um_handle, p->macro, &dc_host->hostid, 1, replace_to);

			p->pos = p->token.loc.r;
		}
		else if (ZBX_TOKEN_EXPRESSION_MACRO == p->inner_token.type)
		{
			/* This should be handled first since p->macro is NULL */
			zbx_timespec_t	ts;
			char		*errmsg = NULL;

			zbx_timespec(&ts);

			if (SUCCEED != (ret = zbx_db_get_expression_macro_result(event, *data,
					&p->inner_token.data.expression_macro.expression, &ts, replace_to, &errmsg)))
			{
				*errmsg = tolower(*errmsg);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot evaluate expression macro: %s", __func__,
						errmsg);
				zbx_strlcpy(error, errmsg, maxerrlen);
				zbx_free(errmsg);
			}
		}
		else if (NULL != actionid && 0 == strncmp(p->macro, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
		{
			ret = expr_db_get_action_value(p->macro, *actionid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_DATE))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_date2str(time(NULL), tz));
		}
		else if (NULL != actionid && 0 == strcmp(p->macro, MVAR_ESC_HISTORY))
		{
			expr_db_get_escalation_history(*actionid, event, r_event, replace_to, userid, tz);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
		{
			if (NULL != r_event)
				get_recovery_event_value(p->macro, r_event, replace_to, tz);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT_CAUSE, ZBX_CONST_STRLEN(MVAR_EVENT_CAUSE)))
		{
			zbx_db_event	**cause_event = (zbx_db_event **)zbx_expr_rem(&event->eventid,
					sizeof(zbx_db_event *), NULL, db_event_ptr_clean_rem_wrap);
			zbx_db_event	**cause_recovery_event =
					(zbx_db_event **)zbx_expr_rem(cause_event,
					sizeof(zbx_db_event *), NULL, db_event_ptr_clean_rem_wrap);

			ret = get_event_cause_value(p->macro, replace_to, um_handle, event, cause_event,
					cause_recovery_event, userid, tz, error, maxerrlen);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_SYMPTOMS))
		{
			ret = expr_db_get_event_symptoms(event, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_STATUS) || 0 == strcmp(p->macro, MVAR_EVENT_VALUE))
		{
			get_current_event_value(p->macro, c_event, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_NAME))
		{
			*replace_to = zbx_strdup(*replace_to, event->name);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_OPDATA))
		{
			resolve_opdata(c_event, um_handle, replace_to, tz, error, maxerrlen);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
		{
			zbx_event_get_macro_value(p->macro, event, replace_to, userid, r_event, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_ID))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index, &zbx_dc_get_host_value,
					ZBX_DC_REQUEST_HOST_ID);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_HOST);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DESCRIPTION))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_HOST_DESCRIPTION);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_IP);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DNS))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_DNS);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_CONN);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_PORT);
		}
		else if (0 == strncmp(p->macro, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
				0 == strncmp(p->macro, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
		{
			ret = zbx_dc_get_host_inventory(p->macro, &c_event->trigger, replace_to, p->index);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_DESCRIPTION))
		{
			uint64_t	itemid;

			if (SUCCEED == (ret = zbx_db_trigger_get_itemid(&c_event->trigger, p->index, &itemid)))
			{
				if (SUCCEED == (ret = zbx_db_get_item_value(itemid, replace_to,
						ZBX_DB_REQUEST_ITEM_DESCRIPTION_ORIG)))
				{
					ret = zbx_dc_expand_user_and_func_macros_itemid(itemid, replace_to);
				}
			}
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_DESCRIPTION_ORIG))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_DESCRIPTION_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_ID))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_ID);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_KEY) || 0 == strcmp(p->macro, MVAR_TRIGGER_KEY))
		{
			uint64_t	itemid;

			if (SUCCEED == (ret = zbx_db_trigger_get_itemid(&c_event->trigger, p->index, &itemid)))
			{
				ret = zbx_dc_get_item_key(itemid, replace_to);
			}
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_KEY_ORIG))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_KEY_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE))
		{
			ret = zbx_db_item_lastvalue(&c_event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_VALUE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_TIMESTAMP))
		{
			ret = zbx_db_item_lastvalue(&c_event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_TIMESTAMP);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_TIME))
		{
			ret = zbx_db_item_lastvalue(&c_event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_TIME);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_DATE))
		{
			ret = zbx_db_item_lastvalue(&c_event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_DATE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE_AGE))
		{
			ret = zbx_db_item_lastvalue(&c_event->trigger, replace_to, p->index, p->raw_value, tz,
					ZBX_VALUE_PROPERTY_AGE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_NAME))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_NAME_ORIG))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_NAME_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE))
		{
			ret = zbx_db_item_value(&c_event->trigger, replace_to, p->index, c_event->clock, c_event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_VALUE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_TIMESTAMP))
		{
			ret = zbx_db_item_value(&c_event->trigger, replace_to, p->index, c_event->clock, c_event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_TIMESTAMP);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_TIME))
		{
			ret = zbx_db_item_value(&c_event->trigger, replace_to, p->index, c_event->clock, c_event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_TIME);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_DATE))
		{
			ret = zbx_db_item_value(&c_event->trigger, replace_to, p->index, c_event->clock, c_event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_DATE);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE_AGE))
		{
			ret = zbx_db_item_value(&c_event->trigger, replace_to, p->index, c_event->clock, c_event->ns,
					p->raw_value, tz, ZBX_VALUE_PROPERTY_AGE);
		}
		else if (0 == strncmp(p->macro, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
		{
			ret = zbx_get_history_log_value(p->macro, &c_event->trigger, replace_to, p->index,
					c_event->clock, c_event->ns, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUETYPE))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_VALUETYPE);
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_NAME))
		{
			uint64_t	itemid;

			if (SUCCEED == (ret = zbx_db_trigger_get_itemid(&c_event->trigger, p->index, &itemid)))
			{
				ret = expr_get_proxy_name_description(itemid, ZBX_DB_REQUEST_PROXY_NAME, replace_to);
			}
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_DESCRIPTION))
		{
			uint64_t	itemid;

			if (SUCCEED == (ret = zbx_db_trigger_get_itemid(&c_event->trigger, p->index, &itemid)))
			{
				ret = expr_get_proxy_name_description(itemid, ZBX_DB_REQUEST_PROXY_DESCRIPTION,
						replace_to);
			}
		}
		else if (0 == p->indexed && 0 == strcmp(p->macro, MVAR_TIME))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_time2str(time(NULL), tz));
		}
		else if (0 == p->indexed && 0 == strcmp(p->macro, MVAR_TIMESTAMP))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%ld", (long)time(NULL));
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_DESCRIPTION) ||
				0 == strcmp(p->macro, MVAR_TRIGGER_COMMENT))
		{
			*replace_to = zbx_strdup(*replace_to, c_event->trigger.comments);
			zbx_substitute_macros(replace_to, error, maxerrlen, &zbx_macro_trigger_desc_resolv, um_handle,
					c_event, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EVENTS_ACK))
		{
			ret = zbx_event_db_count_from_trigger(c_event->objectid, replace_to, 0, 1);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EVENTS_PROBLEM_ACK))
		{
			ret = zbx_event_db_count_from_trigger(c_event->objectid, replace_to, 1, 1);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EVENTS_PROBLEM_UNACK))
		{
			ret = zbx_event_db_count_from_trigger(c_event->objectid, replace_to, 1, 0);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EVENTS_UNACK))
		{
			ret = zbx_event_db_count_from_trigger(c_event->objectid, replace_to, 0, 0);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EXPRESSION))
		{
			zbx_db_trigger_get_expression(&c_event->trigger, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EXPRESSION_RECOVERY))
		{
			if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
			{
				zbx_db_trigger_get_recovery_expression(&c_event->trigger, replace_to);
			}
			else
				*replace_to = zbx_strdup(*replace_to, "");
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EXPRESSION_EXPLAIN))
		{
			zbx_db_trigger_explain_expression(&c_event->trigger, replace_to, zbx_evaluate_function, 0);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EXPRESSION_RECOVERY_EXPLAIN))
		{
			if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
			{
				zbx_db_trigger_explain_expression(&c_event->trigger, replace_to,
						zbx_evaluate_function, 1);
			}
			else
				*replace_to = zbx_strdup(*replace_to, "");
		}
		else if (0 == strcmp(p->macro, MVAR_FUNCTION_VALUE))
		{
			zbx_db_trigger_get_function_value(&c_event->trigger, p->index, replace_to,
					zbx_evaluate_function, 0);
		}
		else if (0 == strcmp(p->macro, MVAR_FUNCTION_RECOVERY_VALUE))
		{
			zbx_db_trigger_get_function_value(&c_event->trigger, p->index, replace_to,
					zbx_evaluate_function, 1);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_HOSTGROUP_NAME))
		{
			ret = expr_db_get_trigger_hostgroup_name(c_event->objectid, userid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_ID))
		{
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, c_event->objectid);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_NAME))
		{
			*replace_to = zbx_strdup(*replace_to, c_event->trigger.description);
			zbx_substitute_macros_ext_search(ZBX_TOKEN_SEARCH_REFERENCES, replace_to, error, maxerrlen,
					&zbx_macro_trigger_desc_resolv, um_handle, event, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_NAME_ORIG))
		{
			*replace_to = zbx_strdup(*replace_to, c_event->trigger.description);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_NSEVERITY))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%d", (int)c_event->trigger.priority);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_STATUS) || 0 == strcmp(p->macro, MVAR_STATUS))
		{
			*replace_to = zbx_strdup(*replace_to, trigger_value_string(c_event->trigger.value));
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_SEVERITY))
		{
			ret = zbx_config_get_trigger_severity_name(c_event->trigger.priority, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_TEMPLATE_NAME))
		{
			ret = expr_db_get_trigger_template_name(c_event->objectid, userid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_URL))
		{
			*replace_to = zbx_strdup(*replace_to, event->trigger.url);
			zbx_substitute_macros(replace_to, error, maxerrlen, &macro_trigger_url_resolv, um_handle,
					event, userid, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_URL_NAME))
		{
			*replace_to = zbx_strdup(*replace_to, event->trigger.url_name);
			zbx_substitute_macros(replace_to, error, maxerrlen, &macro_trigger_url_resolv, um_handle,
					event, userid, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_VALUE))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%d", c_event->trigger.value);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SENDTO))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->sendto);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SUBJECT))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->subject);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_MESSAGE))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->message);
		}
	}
	else if (EVENT_SOURCE_INTERNAL == c_event->source && EVENT_OBJECT_TRIGGER == c_event->object)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			const zbx_vector_uint64_t	**c_event_hosts =
					(const zbx_vector_uint64_t **)zbx_expr_rem(&c_event->trigger,
					sizeof(const zbx_vector_uint64_t *), NULL, NULL);

			if (SUCCEED == zbx_db_trigger_get_all_hostids(&c_event->trigger, c_event_hosts))
			{
				zbx_dc_get_user_macro(um_handle, p->macro, (*c_event_hosts)->values,
						(*c_event_hosts)->values_num, replace_to);
			}
			p->pos = p->token.loc.r;
		}
		else if (NULL != actionid && 0 == strncmp(p->macro, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
		{
			ret = expr_db_get_action_value(p->macro, *actionid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_DATE))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_date2str(time(NULL), tz));
		}
		else if (NULL != actionid && 0 == strcmp(p->macro, MVAR_ESC_HISTORY))
		{
			expr_db_get_escalation_history(*actionid, event, r_event, replace_to, userid, tz);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
		{
			if (NULL != r_event)
				get_recovery_event_value(p->macro, r_event, replace_to, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_STATUS) || 0 == strcmp(p->macro, MVAR_EVENT_VALUE))
		{
			get_current_event_value(p->macro, c_event, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_NAME))
		{
			*replace_to = zbx_strdup(*replace_to, event->name);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
		{
			zbx_event_get_macro_value(p->macro, event, replace_to, userid, r_event, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_ID))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_to, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_ID);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_HOST);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DESCRIPTION))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_HOST_DESCRIPTION);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_IP);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DNS))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_DNS);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_CONN);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_PORT);
		}
		else if (0 == strncmp(p->macro, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
				0 == strncmp(p->macro, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
		{
			ret = zbx_dc_get_host_inventory(p->macro, &c_event->trigger, replace_to, p->index);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_DESCRIPTION))
		{
			uint64_t	itemid;

			if (SUCCEED == (ret = zbx_db_trigger_get_itemid(&c_event->trigger, p->index, &itemid)))
			{
				if (SUCCEED == (ret = zbx_db_get_item_value(itemid, replace_to,
						ZBX_DB_REQUEST_ITEM_DESCRIPTION_ORIG)))
				{
					ret = zbx_dc_expand_user_and_func_macros_itemid(itemid, replace_to);
				}
			}
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_DESCRIPTION_ORIG))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_DESCRIPTION_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_ID))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_ID);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_KEY) || 0 == strcmp(p->macro, MVAR_TRIGGER_KEY))
		{
			uint64_t	itemid;

			if (SUCCEED == (ret = zbx_db_trigger_get_itemid(&c_event->trigger, p->index, &itemid)))
			{
				ret = zbx_dc_get_item_key(itemid, replace_to);
			}
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_KEY_ORIG))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_KEY_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_NAME))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_NAME_ORIG))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_NAME_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUETYPE))
		{
			ret = zbx_db_with_trigger_itemid(&c_event->trigger, replace_to, p->index,
					&zbx_db_get_item_value, ZBX_DB_REQUEST_ITEM_VALUETYPE);
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_NAME))
		{
			uint64_t	itemid;

			if (SUCCEED == (ret = zbx_db_trigger_get_itemid(&c_event->trigger, p->index, &itemid)))
			{
				ret = expr_get_proxy_name_description(itemid, ZBX_DB_REQUEST_PROXY_NAME, replace_to);
			}
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_DESCRIPTION))
		{
			uint64_t	itemid;

			if (SUCCEED == (ret = zbx_db_trigger_get_itemid(&c_event->trigger, p->index, &itemid)))
			{
				ret = expr_get_proxy_name_description(itemid, ZBX_DB_REQUEST_PROXY_DESCRIPTION,
						replace_to);
			}
		}
		else if (0 == p->indexed && 0 == strcmp(p->macro, MVAR_TIME))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_time2str(time(NULL), tz));
		}
		else if (0 == p->indexed && 0 == strcmp(p->macro, MVAR_TIMESTAMP))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%ld", (long)time(NULL));
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_DESCRIPTION) ||
				0 == strcmp(p->macro, MVAR_TRIGGER_COMMENT))
		{
			*replace_to = zbx_strdup(*replace_to, c_event->trigger.comments);
			zbx_substitute_macros(replace_to, error, maxerrlen, &zbx_macro_trigger_desc_resolv, um_handle,
					c_event, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EXPRESSION))
		{
			zbx_db_trigger_get_expression(&c_event->trigger, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EXPRESSION_RECOVERY))
		{
			if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
			{
				zbx_db_trigger_get_recovery_expression(&c_event->trigger, replace_to);
			}
			else
				*replace_to = zbx_strdup(*replace_to, "");
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EXPRESSION_EXPLAIN))
		{
			zbx_db_trigger_explain_expression(&c_event->trigger, replace_to, zbx_evaluate_function, 0);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_EXPRESSION_RECOVERY_EXPLAIN))
		{
			if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
			{
				zbx_db_trigger_explain_expression(&c_event->trigger, replace_to,
						zbx_evaluate_function, 1);
			}
			else
				*replace_to = zbx_strdup(*replace_to, "");
		}
		else if (0 == strcmp(p->macro, MVAR_FUNCTION_VALUE))
		{
			zbx_db_trigger_get_function_value(&c_event->trigger, p->index, replace_to,
					zbx_evaluate_function, 0);
		}
		else if (0 == strcmp(p->macro, MVAR_FUNCTION_RECOVERY_VALUE))
		{
			zbx_db_trigger_get_function_value(&c_event->trigger, p->index, replace_to,
					zbx_evaluate_function, 1);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_HOSTGROUP_NAME))
		{
			ret = expr_db_get_trigger_hostgroup_name(c_event->objectid, userid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_ID))
		{
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, c_event->objectid);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_NAME))
		{
			*replace_to = zbx_strdup(*replace_to, c_event->trigger.description);
			zbx_substitute_macros_ext_search(ZBX_TOKEN_SEARCH_REFERENCES, replace_to, error, maxerrlen,
					&zbx_macro_trigger_desc_resolv, um_handle, event, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_NAME_ORIG))
		{
			*replace_to = zbx_strdup(*replace_to, c_event->trigger.description);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_NSEVERITY))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%d", (int)c_event->trigger.priority);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_SEVERITY))
		{
			ret = zbx_config_get_trigger_severity_name(c_event->trigger.priority, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_STATE))
		{
			*replace_to = zbx_strdup(*replace_to, trigger_state_string(c_event->value));
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_STATE_ERROR))
		{
			ret = expr_db_get_trigger_error(&event->trigger, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_TEMPLATE_NAME))
		{
			ret = expr_db_get_trigger_template_name(c_event->objectid, userid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_URL))
		{
			*replace_to = zbx_strdup(*replace_to, event->trigger.url);
			zbx_substitute_macros(replace_to, error, maxerrlen, &macro_trigger_url_resolv, um_handle,
					event, userid, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_TRIGGER_URL_NAME))
		{
			*replace_to = zbx_strdup(*replace_to, event->trigger.url_name);
			zbx_substitute_macros(replace_to, error, maxerrlen, &macro_trigger_url_resolv, um_handle,
					event, userid, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SENDTO))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->sendto);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SUBJECT))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->subject);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_MESSAGE))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->message);
		}
	}
	else if (0 == p->indexed && EVENT_SOURCE_DISCOVERY == c_event->source)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			if (NULL == dc_host)
				zbx_dc_get_user_macro(um_handle, p->macro, NULL, 0, replace_to);
			else
				zbx_dc_get_user_macro(um_handle, p->macro, &dc_host->hostid, 1, replace_to);

			p->pos = p->token.loc.r;
		}
		else if (NULL != actionid && 0 == strncmp(p->macro, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
		{
			ret = expr_db_get_action_value(p->macro, *actionid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_DATE))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_date2str(time(NULL), tz));
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)) &&
				0 != strcmp(p->macro, MVAR_EVENT_DURATION))
		{
			zbx_event_get_macro_value(p->macro, event, replace_to, userid, NULL, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_DISCOVERY_DEVICE_IPADDRESS))
		{
			ret = zbx_event_db_get_dhost(c_event, replace_to, "s.ip");
		}
		else if (0 == strcmp(p->macro, MVAR_DISCOVERY_DEVICE_DNS))
		{
			ret = zbx_event_db_get_dhost(c_event, replace_to, "s.dns");
		}
		else if (0 == strcmp(p->macro, MVAR_DISCOVERY_DEVICE_STATUS))
		{
			if (SUCCEED == (ret = zbx_event_db_get_dhost(c_event, replace_to, "h.status")))
			{
				*replace_to = zbx_strdup(*replace_to, zbx_dobject_status2str(atoi(*replace_to)));
			}
		}
		else if (0 == strcmp(p->macro, MVAR_DISCOVERY_DEVICE_UPTIME))
		{
			char	sql[64];

			zbx_snprintf(sql, sizeof(sql), "case when h.status=%d then h.lastup else h.lastdown end",
					DOBJECT_STATUS_UP);

			if (SUCCEED == (ret = zbx_event_db_get_dhost(c_event, replace_to, sql)))
			{
				*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - atoi(*replace_to)));
			}
		}
		else if (0 == strcmp(p->macro, MVAR_DISCOVERY_RULE_NAME))
		{
			ret = zbx_event_db_get_drule(c_event, replace_to, "name");
		}
		else if (0 == strcmp(p->macro, MVAR_DISCOVERY_SERVICE_NAME))
		{
			if (SUCCEED == (ret = zbx_event_db_get_dchecks(c_event, replace_to, "c.type")))
			{
				*replace_to = zbx_strdup(*replace_to, zbx_dservice_type_string(atoi(*replace_to)));
			}
		}
		else if (0 == strcmp(p->macro, MVAR_DISCOVERY_SERVICE_PORT))
		{
			ret = zbx_event_db_get_dservice(c_event, replace_to, "s.port");
		}
		else if (0 == strcmp(p->macro, MVAR_DISCOVERY_SERVICE_STATUS))
		{
			if (SUCCEED == (ret = zbx_event_db_get_dservice(c_event, replace_to, "s.status")))
			{
				*replace_to = zbx_strdup(*replace_to, zbx_dobject_status2str(atoi(*replace_to)));
			}
		}
		else if (0 == strcmp(p->macro, MVAR_DISCOVERY_SERVICE_UPTIME))
		{
			char	sql[64];

			zbx_snprintf(sql, sizeof(sql), "case when s.status=%d then s.lastup else s.lastdown end",
					DOBJECT_STATUS_UP);

			if (SUCCEED == (ret = zbx_event_db_get_dservice(c_event, replace_to, sql)))
			{
				*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - atoi(*replace_to)));
			}
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_NAME))
		{
			if (SUCCEED == (ret = zbx_event_db_get_dhost(c_event, replace_to, "r.proxyid")))
			{
				zbx_uint64_t	proxyid = 0;

				if (SUCCEED == zbx_is_uint64(*replace_to, &proxyid) && 0 != proxyid)
					ret = zbx_db_get_proxy_value(proxyid, replace_to, "name");
				else
					*replace_to = zbx_strdup(*replace_to, "");
			}
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_DESCRIPTION))
		{
			if (SUCCEED == (ret = zbx_event_db_get_dhost(c_event, replace_to, "r.proxyid")))
			{
				zbx_uint64_t	proxyid = 0;

				if (SUCCEED == zbx_is_uint64(*replace_to, &proxyid) && 0 != proxyid)
					ret = zbx_db_get_proxy_value(proxyid, replace_to, "description");
				else
					*replace_to = zbx_strdup(*replace_to, "");
			}
		}
		else if (0 == strcmp(p->macro, MVAR_TIME))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_time2str(time(NULL), tz));
		}
		else if (0 == strcmp(p->macro, MVAR_TIMESTAMP))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%ld", (long)time(NULL));
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SENDTO))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->sendto);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SUBJECT))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->subject);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_MESSAGE))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->message);
		}
		else
		{
			ret = resolve_host_target_macros(p->macro, dc_host, replace_to);
		}
	}
	else if (0 == p->indexed && EVENT_SOURCE_AUTOREGISTRATION == c_event->source)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			if (NULL == dc_host)
				zbx_dc_get_user_macro(um_handle, p->macro, NULL, 0, replace_to);
			else
				zbx_dc_get_user_macro(um_handle, p->macro, &dc_host->hostid, 1, replace_to);

			p->pos = p->token.loc.r;
		}
		else if (NULL != actionid && 0 == strncmp(p->macro, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
		{
			ret = expr_db_get_action_value(p->macro, *actionid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_DATE))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_date2str(time(NULL), tz));
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)) &&
				0 != strcmp(p->macro, MVAR_EVENT_DURATION))
		{
			zbx_event_get_macro_value(p->macro, event, replace_to, userid, NULL, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_METADATA))
		{
			ret = zbx_event_db_get_autoreg(c_event, replace_to, "host_metadata");
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST))
		{
			ret = zbx_event_db_get_autoreg(c_event, replace_to, "host");
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
		{
			ret = zbx_event_db_get_autoreg(c_event, replace_to, "listen_ip");
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			ret = zbx_event_db_get_autoreg(c_event, replace_to, "listen_port");
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_NAME))
		{
			if (SUCCEED == (ret = zbx_event_db_get_autoreg(c_event, replace_to, "proxyid")))
			{
				zbx_uint64_t	proxyid = 0;

				if (SUCCEED == zbx_is_uint64(*replace_to, &proxyid) && 0 != proxyid)
					ret = zbx_db_get_proxy_value(proxyid, replace_to, "name");
				else
					*replace_to = zbx_strdup(*replace_to, "");
			}
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_DESCRIPTION))
		{
			if (SUCCEED == (ret = zbx_event_db_get_autoreg(c_event, replace_to, "proxyid")))
			{
				zbx_uint64_t	proxyid = 0;

				if (SUCCEED == zbx_is_uint64(*replace_to, &proxyid) && 0 != proxyid)
					ret = zbx_db_get_proxy_value(proxyid, replace_to, "description");
				else
					*replace_to = zbx_strdup(*replace_to, "");
			}
		}
		else if (0 == strcmp(p->macro, MVAR_TIME))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_time2str(time(NULL), tz));
		}
		else if (0 == strcmp(p->macro, MVAR_TIMESTAMP))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%ld", (long)time(NULL));
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SENDTO))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->sendto);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SUBJECT))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->subject);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_MESSAGE))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->message);
		}
		else
		{
			ret = resolve_host_target_macros(p->macro, dc_host, replace_to);
		}
	}
	else if (0 == p->indexed && EVENT_SOURCE_INTERNAL == c_event->source &&
			EVENT_OBJECT_ITEM == c_event->object)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			zbx_vector_uint64_t	*item_hosts = zbx_expr_rem(&c_event->objectid,
					sizeof(zbx_vector_uint64_t), vector_uint64_create_rem_wrap,
					vector_uint64_destroy_rem_wrap);

			zbx_dc_config_get_hostid_by_itemid(item_hosts, c_event->objectid);
			zbx_dc_get_user_macro(um_handle, p->macro, item_hosts->values, item_hosts->values_num,
					replace_to);
			p->pos = p->token.loc.r;
		}
		else if (NULL != actionid && 0 == strncmp(p->macro, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
		{
			ret = expr_db_get_action_value(p->macro, *actionid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_DATE))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_date2str(time(NULL), tz));
		}
		else if (NULL != actionid && 0 == strcmp(p->macro, MVAR_ESC_HISTORY))
		{
			expr_db_get_escalation_history(*actionid, event, r_event, replace_to, userid, tz);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
		{
			if (NULL != r_event)
				get_recovery_event_value(p->macro, r_event, replace_to, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_STATUS) || 0 == strcmp(p->macro, MVAR_EVENT_VALUE))
		{
			get_current_event_value(p->macro, c_event, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_NAME))
		{
			*replace_to = zbx_strdup(*replace_to, event->name);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
		{
			zbx_event_get_macro_value(p->macro, event, replace_to, userid, r_event, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_ID))
		{
			ret = zbx_dc_get_host_value(c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_ID);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
		{
			ret = zbx_dc_get_host_value(c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_HOST);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
		{
			ret = zbx_dc_get_host_value(c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DESCRIPTION))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_HOST_DESCRIPTION);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
		{
			ret = zbx_dc_get_interface_value(0, c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_IP);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DNS))
		{
			ret = zbx_dc_get_interface_value(0, c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_DNS);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
		{
			ret = zbx_dc_get_interface_value(0, c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_CONN);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			ret = zbx_dc_get_interface_value(0, c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_PORT);
		}
		else if (0 == strncmp(p->macro, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
				0 == strncmp(p->macro, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
		{
			ret = zbx_dc_get_host_inventory_by_itemid(p->macro, c_event->objectid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_DESCRIPTION))
		{
			if (SUCCEED == (ret = zbx_db_get_item_value(c_event->objectid, replace_to,
					ZBX_DB_REQUEST_ITEM_DESCRIPTION_ORIG)))
			{
				ret = zbx_dc_expand_user_and_func_macros_itemid(c_event->objectid, replace_to);
			}
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_DESCRIPTION_ORIG))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to,
					ZBX_DB_REQUEST_ITEM_DESCRIPTION_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_ID))
		{
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, c_event->objectid);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_KEY) || 0 == strcmp(p->macro, MVAR_TRIGGER_KEY))
		{
			ret = zbx_dc_get_item_key(c_event->objectid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_KEY_ORIG))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_ITEM_KEY_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_NAME))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_ITEM_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_NAME_ORIG))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_ITEM_NAME_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_STATE))
		{
			*replace_to = zbx_strdup(*replace_to, item_state_string(c_event->value));
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_VALUETYPE))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_ITEM_VALUETYPE);
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_NAME))
		{
			ret = expr_get_proxy_name_description(c_event->objectid, ZBX_DB_REQUEST_PROXY_NAME, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_DESCRIPTION))
		{
			ret = expr_get_proxy_name_description(c_event->objectid, ZBX_DB_REQUEST_PROXY_DESCRIPTION,
					replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TIME))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_time2str(time(NULL), tz));
		}
		else if (0 == strcmp(p->macro, MVAR_TIMESTAMP))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%ld", (long)time(NULL));
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SENDTO))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->sendto);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SUBJECT))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->subject);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_MESSAGE))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->message);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_STATE_ERROR))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_ITEM_ERROR);
		}
	}
	else if (0 == p->indexed && EVENT_SOURCE_INTERNAL == c_event->source && EVENT_OBJECT_LLDRULE == c_event->object)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			zbx_vector_uint64_t	*item_hosts = zbx_expr_rem(&c_event->objectid,
					sizeof(zbx_vector_uint64_t), vector_uint64_create_rem_wrap,
					vector_uint64_destroy_rem_wrap);

			zbx_dc_config_get_hostid_by_itemid(item_hosts, c_event->objectid);
			zbx_dc_get_user_macro(um_handle, p->macro, item_hosts->values, item_hosts->values_num,
					replace_to);
			p->pos = p->token.loc.r;
		}
		else if (NULL != actionid && 0 == strncmp(p->macro, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
		{
			ret = expr_db_get_action_value(p->macro, *actionid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_DATE))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_date2str(time(NULL), tz));
		}
		else if (NULL != actionid && 0 == strcmp(p->macro, MVAR_ESC_HISTORY))
		{
			expr_db_get_escalation_history(*actionid, event, r_event, replace_to, userid, tz);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
		{
			if (NULL != r_event)
				get_recovery_event_value(p->macro, r_event, replace_to, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_STATUS) || 0 == strcmp(p->macro, MVAR_EVENT_VALUE))
		{
			get_current_event_value(p->macro, c_event, replace_to);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
		{
			zbx_event_get_macro_value(p->macro, event, replace_to, userid, r_event, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_ID))
		{
			ret = zbx_dc_get_host_value(c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_ID);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
		{
			ret = zbx_dc_get_host_value(c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_HOST);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
		{
			ret = zbx_dc_get_host_value(c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DESCRIPTION))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_HOST_DESCRIPTION);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
		{
			ret = zbx_dc_get_interface_value(0, c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_IP);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DNS))
		{
			ret = zbx_dc_get_interface_value(0, c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_DNS);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
		{
			ret = zbx_dc_get_interface_value(0, c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_CONN);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			ret = zbx_dc_get_interface_value(0, c_event->objectid, replace_to, ZBX_DC_REQUEST_HOST_PORT);
		}
		else if (0 == strncmp(p->macro, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
				0 == strncmp(p->macro, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
		{
			ret = zbx_dc_get_host_inventory_by_itemid(p->macro, c_event->objectid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_LLDRULE_DESCRIPTION))
		{
			if (SUCCEED == (ret = zbx_db_get_item_value(c_event->objectid, replace_to,
					ZBX_DB_REQUEST_ITEM_DESCRIPTION_ORIG)))
			{
				ret = zbx_dc_expand_user_and_func_macros_itemid(c_event->objectid, replace_to);
			}
		}
		else if (0 == strcmp(p->macro, MVAR_LLDRULE_DESCRIPTION_ORIG))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to,
					ZBX_DB_REQUEST_ITEM_DESCRIPTION_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_LLDRULE_ID))
		{
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, c_event->objectid);
		}
		else if (0 == strcmp(p->macro, MVAR_LLDRULE_KEY))
		{
			ret = zbx_dc_get_item_key(c_event->objectid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_LLDRULE_KEY_ORIG))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_ITEM_KEY_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_LLDRULE_NAME))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_ITEM_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_LLDRULE_NAME_ORIG))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_ITEM_NAME_ORIG);
		}
		else if (0 == strcmp(p->macro, MVAR_LLDRULE_STATE))
		{
			*replace_to = zbx_strdup(*replace_to, item_state_string(c_event->value));
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_NAME))
		{
			ret = expr_get_proxy_name_description(c_event->objectid, ZBX_DB_REQUEST_PROXY_NAME, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_PROXY_DESCRIPTION))
		{
			ret = expr_get_proxy_name_description(c_event->objectid, ZBX_DB_REQUEST_PROXY_DESCRIPTION,
					replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TIME))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_time2str(time(NULL), tz));
		}
		else if (0 == strcmp(p->macro, MVAR_TIMESTAMP))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%ld", (long)time(NULL));
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SENDTO))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->sendto);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SUBJECT))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->subject);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_MESSAGE))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->message);
		}
		else if (0 == strcmp(p->macro, MVAR_LLDRULE_STATE_ERROR))
		{
			ret = zbx_db_get_item_value(c_event->objectid, replace_to, ZBX_DB_REQUEST_ITEM_ERROR);
		}
	}
	else if (0 == p->indexed && EVENT_SOURCE_SERVICE == c_event->source)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			if (NULL == dc_host)
				zbx_dc_get_user_macro(um_handle, p->macro, NULL, 0, replace_to);
			else
				zbx_dc_get_user_macro(um_handle, p->macro, &dc_host->hostid, 1, replace_to);

			p->pos = p->token.loc.r;
		}
		else if (NULL != actionid && 0 == strncmp(p->macro, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
		{
			ret = expr_db_get_action_value(p->macro, *actionid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_TIME))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_time2str(time(NULL), tz));
		}
		else if (0 == strcmp(p->macro, MVAR_TIMESTAMP))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%ld", (long)time(NULL));
		}
		else if (0 == strcmp(p->macro, MVAR_DATE))
		{
			*replace_to = zbx_strdup(*replace_to, zbx_date2str(time(NULL), tz));
		}
		else if (NULL != actionid && 0 == strcmp(p->macro, MVAR_ESC_HISTORY))
		{
			expr_db_get_escalation_history(*actionid, event, r_event, replace_to, userid, tz);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
		{
			if (NULL != r_event)
				get_recovery_event_value(p->macro, r_event, replace_to, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_NSEVERITY))
		{
			if (NULL != service_alarm)
				*replace_to = zbx_dsprintf(*replace_to, "%d", (int)service_alarm->value);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_SEVERITY))
		{
			if (NULL != service_alarm)
			{
				if (FAIL == zbx_config_get_trigger_severity_name(service_alarm->value, replace_to))
				{
					*replace_to = zbx_strdup(*replace_to, "unknown");
				}
			}
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_DATE))
		{
			if (NULL != service_alarm)
			{
				*replace_to = zbx_strdup(*replace_to, zbx_date2str(service_alarm->clock, tz));
			}
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_TIME))
		{
			if (NULL != service_alarm)
			{
				*replace_to = zbx_strdup(*replace_to, zbx_time2str(service_alarm->clock, tz));
			}
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_TIMESTAMP))
		{
			if (NULL != service_alarm)
				*replace_to = zbx_dsprintf(*replace_to, "%d", service_alarm->clock);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_STATUS) || 0 == strcmp(p->macro, MVAR_EVENT_VALUE))
		{
			get_current_event_value(p->macro, c_event, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_EVENT_NAME))
		{
			*replace_to = zbx_strdup(*replace_to, event->name);
		}
		else if (0 == strncmp(p->macro, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
		{
			zbx_event_get_macro_value(p->macro, event, replace_to, userid, r_event, tz);
		}
		else if (0 == strcmp(p->macro, MVAR_SERVICE_NAME))
		{
			*replace_to = zbx_strdup(*replace_to, service->name);
		}
		else if (0 == strcmp(p->macro, MVAR_SERVICE_ID))
		{
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, service->serviceid);
		}
		else if (0 == strcmp(p->macro, MVAR_SERVICE_DESCRIPTION))
		{
			*replace_to = zbx_strdup(*replace_to, service->description);
		}
		else if (0 == strcmp(p->macro, MVAR_SERVICE_ROOTCAUSE))
		{
			expr_db_get_rootcause(service, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_SERVICE_TAGS))
		{
			zbx_event_get_str_tags(event, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_SERVICE_TAGSJSON))
		{
			zbx_event_get_json_tags(event, replace_to);
		}
		else if (0 == strncmp(p->macro, MVAR_SERVICE_TAGS_PREFIX, ZBX_CONST_STRLEN(MVAR_SERVICE_TAGS_PREFIX)))
		{
			zbx_event_get_tag(p->macro + ZBX_CONST_STRLEN(MVAR_SERVICE_TAGS_PREFIX), event, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SENDTO))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->sendto);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_SUBJECT))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->subject);
		}
		else if (0 == strcmp(p->macro, MVAR_ALERT_MESSAGE))
		{
			if (NULL != alert)
				*replace_to = zbx_strdup(*replace_to, alert->message);
		}
	}

	return ret;
}
