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

#include "escalator.h"

#include "zbxexpr.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbx_expression_constants.h"
#include "zbxevent.h"
#include "zbxdbwrap.h"
#include "zbxtime.h"

static int	macro_message_normal_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data,
		char *error, size_t maxerrlen)
{
	int	ret;

	/* Passed arguments */
	zbx_dc_um_handle_t		*um_handle = va_arg(args, zbx_dc_um_handle_t *);

	/* Passed arguments for common resolver */
	const zbx_uint64_t		*actionid = va_arg(args, const zbx_uint64_t *);
	const zbx_db_event		*event = va_arg(args, const zbx_db_event *);
	const zbx_db_event		*r_event = va_arg(args, const zbx_db_event *);
	const zbx_uint64_t		*userid = va_arg(args, const zbx_uint64_t *);
	const zbx_dc_host_t		*dc_host = va_arg(args, const zbx_dc_host_t *);
	const zbx_db_alert		*alert = va_arg(args, const zbx_db_alert *);
	const zbx_service_alarm_t	*service_alarm = va_arg(args, const zbx_service_alarm_t *);
	const zbx_db_service		*service = va_arg(args, const zbx_db_service *);
	const char			*tz = va_arg(args, const char *);

	const zbx_db_event	*c_event = ((NULL != r_event) ? r_event : event);

	ret = zbx_macro_message_common_resolv(p, um_handle, actionid, event, r_event, userid, dc_host, alert,
			service_alarm, service, tz, replace_to, data, error, maxerrlen);

	if (SUCCEED == ret && NULL != p->macro)
	{
		if (EVENT_SOURCE_TRIGGERS == c_event->source)
		{
			if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_STATUS))
			{
				*replace_to = zbx_strdup(*replace_to, "0");
			}
		}
		else if (0 == p->indexed && EVENT_SOURCE_SERVICE == c_event->source)
		{
			if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_STATUS))
			{
				*replace_to = zbx_strdup(*replace_to, "0");
			}
		}
	}

	return ret;
}

static int	macro_message_update_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to,
		char **data, char *error, size_t maxerrlen)
{
	int	ret;

	/* Passed arguments */
	zbx_dc_um_handle_t	*um_handle = va_arg(args, zbx_dc_um_handle_t *);

	/* Passed arguments for common resolver */
	const zbx_uint64_t		*actionid = va_arg(args, const zbx_uint64_t *);
	const zbx_db_event		*event = va_arg(args, const zbx_db_event *);
	const zbx_db_event		*r_event = va_arg(args, const zbx_db_event *);
	const zbx_uint64_t		*userid = va_arg(args, const zbx_uint64_t *);
	const zbx_dc_host_t		*dc_host = va_arg(args, const zbx_dc_host_t *);
	const zbx_db_alert		*alert = va_arg(args, const zbx_db_alert *);
	const zbx_service_alarm_t	*service_alarm = va_arg(args, const zbx_service_alarm_t *);
	const zbx_db_service		*service = va_arg(args, const zbx_db_service *);
	const char			*tz = va_arg(args, const char *);

	/* Specific passed arguments */
	const zbx_db_acknowledge	*ack = va_arg(args, const zbx_db_acknowledge *);

	const zbx_db_event	*c_event = ((NULL != r_event) ? r_event : event);

	ret = zbx_macro_message_common_resolv(p, um_handle, actionid, event, r_event, userid, dc_host, alert,
			service_alarm, service, tz, replace_to, data, error, maxerrlen);

	if (SUCCEED != ret || NULL == p->macro)
		return ret;

	if (EVENT_SOURCE_TRIGGERS == c_event->source)
	{
		if (NULL != ack)
		{
			if (0 == strcmp(p->macro, MVAR_ACK_MESSAGE) || 0 == strcmp(p->macro, MVAR_EVENT_UPDATE_MESSAGE))
			{
				*replace_to = zbx_strdup(*replace_to, ack->message);
			}
			else if (0 == strcmp(p->macro, MVAR_ACK_TIME) || 0 == strcmp(p->macro, MVAR_EVENT_UPDATE_TIME))
			{
				*replace_to = zbx_strdup(*replace_to, zbx_time2str(ack->clock, tz));
			}
			else if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_TIMESTAMP))
			{
				*replace_to = zbx_dsprintf(*replace_to, "%d", ack->clock);
			}
			else if (0 == strcmp(p->macro, MVAR_ACK_DATE) || 0 == strcmp(p->macro, MVAR_EVENT_UPDATE_DATE))
			{
				*replace_to = zbx_strdup(*replace_to, zbx_date2str(ack->clock, tz));
			}
			else if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_ACTION))
			{
				zbx_problem_get_actions(ack, ZBX_PROBLEM_UPDATE_ACKNOWLEDGE |
						ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE |
						ZBX_PROBLEM_UPDATE_CLOSE | ZBX_PROBLEM_UPDATE_MESSAGE |
						ZBX_PROBLEM_UPDATE_SEVERITY |
						ZBX_PROBLEM_UPDATE_SUPPRESS |
						ZBX_PROBLEM_UPDATE_UNSUPPRESS |
						ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE |
						ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM, tz, replace_to);
			}
			else if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_ACTIONJSON))
			{
				zbx_event_get_json_actions(ack, replace_to);
			}
			else if (0 == strcmp(p->macro, MVAR_USER_FULLNAME))
			{
				const char	*user_name1;

				if (SUCCEED == zbx_check_user_permissions(&ack->userid, userid))
					user_name1 = zbx_user_string(ack->userid);
				else
					user_name1 = "Inaccessible user";

				*replace_to = zbx_strdup(*replace_to, user_name1);
			}
		}

		if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_STATUS))
		{
			if (NULL != ack)
				*replace_to = zbx_strdup(*replace_to, "1");
			else
				*replace_to = zbx_strdup(*replace_to, "0");
		}
	}
	else if (0 == p->indexed && EVENT_SOURCE_SERVICE == c_event->source)
	{
		if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_STATUS))
		{
			if (NULL != service_alarm)
				*replace_to = zbx_strdup(*replace_to, "1");
			else
				*replace_to = zbx_strdup(*replace_to, "0");
		}
	}

	return ret;
}

int	substitute_message_macros(char **data, char *error, int maxerrlen, int message_type,
		zbx_dc_um_handle_t * um_handle, zbx_uint64_t *actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, zbx_uint64_t *userid, const zbx_dc_host_t *dc_host,
		const zbx_db_alert *alert, const zbx_service_alarm_t *service_alarm, const zbx_db_service *service,
		const char *tz, const zbx_db_acknowledge *ack)
{
	int			ret = FAIL;
	zbx_token_search_t	token_search = ZBX_TOKEN_SEARCH_BASIC;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	const zbx_db_event	*c_event = ((NULL != r_event) ? r_event : event);

	if (NULL != c_event && EVENT_SOURCE_TRIGGERS == c_event->source)
		token_search |= ZBX_TOKEN_SEARCH_EXPRESSION_MACRO;

	switch (message_type)
	{
		case ZBX_MESSAGE_TYPE_RECOVERY:
		case ZBX_MESSAGE_TYPE_NORMAL:
			ret = zbx_substitute_macros_ext_search(token_search, data, error, maxerrlen,
					&macro_message_normal_resolv, um_handle, actionid, event, r_event, userid,
					dc_host, alert, service_alarm, service, tz);
			break;
		case ZBX_MESSAGE_TYPE_UPDATE:
			ret = zbx_substitute_macros_ext_search(token_search, data, error, maxerrlen,
					&macro_message_update_resolv, um_handle, actionid, event, r_event, userid,
					dc_host, alert, service_alarm, service, tz, ack);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
