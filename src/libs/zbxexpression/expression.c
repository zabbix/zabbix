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

#include "zbxexpression.h"
#include "expression.h"

#include "zbxdbwrap.h"
#include "zbxcachevalue.h"
#include "zbxstr.h"
#include "zbxexpr.h"
#include "zbx_expression_constants.h"
#include "zbxevent.h"
#include "zbxtime.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "zbxcalc.h"

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

/******************************************************************************
 *                                                                            *
 * Purpose: substitute simple macros in data string with real values.         *
 *                                                                            *
 ******************************************************************************/
int	substitute_simple_macros_impl(const zbx_uint64_t *actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, const zbx_uint64_t *userid, const zbx_uint64_t *hostid,
		const zbx_dc_host_t *dc_host, const zbx_dc_item_t *dc_item, const zbx_db_alert *alert,
		const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm, const zbx_db_service *service,
		const char *tz, zbx_history_recv_item_t *history_data_item, char **data, int macro_type, char *error,
		int maxerrlen)
{
	char				c, *replace_to = NULL, *m_ptr;
	const char			*m;
	int				N_functionid, indexed_macro, ret, res = SUCCEED,
					pos = 0, found, raw_value;
	size_t				data_alloc, data_len;
	zbx_vector_uint64_t		hostids;
	const zbx_vector_uint64_t	*phostids;
	zbx_token_t			token, inner_token;
	zbx_token_search_t		token_search = ZBX_TOKEN_SEARCH_BASIC;
	char				*expression = NULL;
	zbx_dc_um_handle_t		*um_handle;
	zbx_db_event			*cause_event = NULL, *cause_recovery_event = NULL;
	zbx_user_names_t		*user_names = NULL;

	ZBX_UNUSED(dc_item);
	ZBX_UNUSED(history_data_item);
	ZBX_UNUSED(hostid);
	ZBX_UNUSED(actionid);
	ZBX_UNUSED(alert);
	ZBX_UNUSED(ack);
	ZBX_UNUSED(service_alarm);
	ZBX_UNUSED(service);
	ZBX_UNUSED(dc_host);

	if (NULL == data || NULL == *data || '\0' == **data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:EMPTY", __func__);
		return res;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __func__, *data);

	if (0 != (macro_type & (ZBX_MACRO_TYPE_TRIGGER_DESCRIPTION | ZBX_MACRO_TYPE_EVENT_NAME)))
		token_search |= ZBX_TOKEN_SEARCH_REFERENCES;

	if (0 != (macro_type & ZBX_MACRO_TYPE_EVENT_NAME))
	{

		const zbx_db_event	*c_event;

		c_event = ((NULL != r_event) ? r_event : event);

		if (NULL != c_event && EVENT_SOURCE_TRIGGERS == c_event->source)
			token_search |= ZBX_TOKEN_SEARCH_EXPRESSION_MACRO;
	}

	if (SUCCEED != zbx_token_find(*data, pos, &token, token_search))
		goto out;

	um_handle = zbx_dc_open_user_macros();
	zbx_vector_uint64_create(&hostids);

	data_alloc = data_len = strlen(*data) + 1;

	for (found = SUCCEED; SUCCEED == res && SUCCEED == found;
			found = zbx_token_find(*data, pos, &token, token_search))
	{
		indexed_macro = 0;
		N_functionid = 1;
		raw_value = 0;
		pos = token.loc.l;
		inner_token = token;
		ret = SUCCEED;

		switch (token.type)
		{
			case ZBX_TOKEN_OBJECTID:
			case ZBX_TOKEN_LLD_MACRO:
			case ZBX_TOKEN_LLD_FUNC_MACRO:
				/* neither lld nor {123123} macros are processed by this function, skip them */
				pos = token.loc.r + 1;
				continue;
			case ZBX_TOKEN_MACRO:
				if (0 != zbx_is_indexed_macro(*data, &token) &&
						NULL != (m = zbx_macro_in_list(*data, token.loc,
						zbx_get_indexable_macros(), &N_functionid)))
				{
					indexed_macro = 1;
				}
				else
				{
					m = *data + token.loc.l;
					c = (*data)[token.loc.r + 1];
					(*data)[token.loc.r + 1] = '\0';
				}
				break;
			case ZBX_TOKEN_USER_FUNC_MACRO:
				raw_value = 1;
				/* user macros are not indexed */
				if (NULL == (m_ptr = zbx_get_macro_from_func(*data, &token.data.func_macro, NULL)) ||
						SUCCEED != zbx_token_find(*data, token.data.func_macro.macro.l,
						&inner_token, token_search))
				{
					/* Ignore functions with macros not supporting them, but do not skip the */
					/* whole token, nested macro should be resolved in this case. */
					pos++;
					ret = FAIL;
				}
				m = m_ptr;
				break;
			case ZBX_TOKEN_FUNC_MACRO:
				raw_value = 1;
				indexed_macro = zbx_is_indexed_macro(*data, &token);
				if (NULL == (m_ptr = zbx_get_macro_from_func(*data, &token.data.func_macro,
						&N_functionid)) || SUCCEED != zbx_token_find(*data,
						token.data.func_macro.macro.l, &inner_token, token_search))
				{
					/* Ignore functions with macros not supporting them, but do not skip the */
					/* whole token, nested macro should be resolved in this case. */
					pos++;
					ret = FAIL;
				}
				m = m_ptr;
				break;
			case ZBX_TOKEN_USER_MACRO:
				/* To avoid *data modification user macro resolver should be replaced with a function */
				/* that takes initial *data string and token.data.user_macro instead of m as params.  */
				m = *data + token.loc.l;
				c = (*data)[token.loc.r + 1];
				(*data)[token.loc.r + 1] = '\0';
				break;
			case ZBX_TOKEN_REFERENCE:
			case ZBX_TOKEN_EXPRESSION_MACRO:
				/* These macros (and probably all other in the future) must be resolved using only */
				/* information stored in token.data union. For now, force crash if they rely on m. */
				m = NULL;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				res = FAIL;
				continue;
		}

		if (SUCCEED == ret)
		{

		zbx_dc_um_handle_t	*um_handle_prev = NULL;

		if (SUCCEED != zbx_token_is_user_macro(m, &token))
		{
			um_handle_prev = um_handle;
			um_handle = zbx_dc_open_user_macros_masked();
		}

		if (0 != (macro_type & (ZBX_MACRO_TYPE_TRIGGER_DESCRIPTION | ZBX_MACRO_TYPE_TRIGGER_COMMENTS |
					ZBX_MACRO_TYPE_EVENT_NAME)))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (SUCCEED == zbx_token_is_user_macro(m, &token))
				{
					if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, &phostids))
					{
						zbx_dc_get_user_macro(um_handle, m, phostids->values,
								phostids->values_num, &replace_to);
					}
					pos = token.loc.r;
				}
				else if (ZBX_TOKEN_REFERENCE == token.type)
				{
					if (SUCCEED != zbx_db_trigger_get_constant(&event->trigger,
							token.data.reference.index, &replace_to))
					{
						/* expansion failed, reference substitution is impossible */
						token_search &= ~ZBX_TOKEN_SEARCH_REFERENCES;
						continue;
					}
				}
				else if (ZBX_TOKEN_EXPRESSION_MACRO == inner_token.type)
				{
					if (0 != (macro_type & ZBX_MACRO_TYPE_EVENT_NAME))
					{
						zbx_timespec_t	ts;
						char		*errmsg = NULL;

						ts.sec = event->clock;
						ts.ns = event->ns;

						if (SUCCEED != (ret = zbx_db_get_expression_macro_result(event, *data,
								&inner_token.data.expression_macro.expression, &ts,
								&replace_to, &errmsg)))
						{
							*errmsg = tolower(*errmsg);
							zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot evaluate"
									" expression macro: %s", __func__, errmsg);
							zbx_strlcpy(error, errmsg, maxerrlen);
							zbx_free(errmsg);
						}
					}
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to,
							N_functionid, &zbx_dc_get_host_value,
							ZBX_DC_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_PORT);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_VALUE);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE_TIMESTAMP))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_TIMESTAMP);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE_TIME))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_TIME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE_DATE))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_DATE);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE_AGE))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_AGE);
				}
				else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
				{
					ret = zbx_get_history_log_value(m, &event->trigger, &replace_to,
							N_functionid, event->clock, event->ns, tz);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_VALUE);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE_TIMESTAMP))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_TIMESTAMP);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE_TIME))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_TIME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE_DATE))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_DATE);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE_AGE))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_AGE);
				}
				else if (0 != (macro_type & ZBX_MACRO_TYPE_EVENT_NAME))
				{
					if (0 == strcmp(m, MVAR_TIME))
					{
						replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
					}
					else if (0 == strcmp(m, MVAR_TIMESTAMP))
					{
						replace_to = zbx_dsprintf(replace_to, "%ld", (long)time(NULL));
					}
					else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_EXPLAIN))
					{
						zbx_db_trigger_explain_expression(&event->trigger, &replace_to,
								zbx_evaluate_function, 0);
					}
					else if (0 == strcmp(m, MVAR_FUNCTION_VALUE))
					{
						zbx_db_trigger_get_function_value(&event->trigger, N_functionid,
								&replace_to, zbx_evaluate_function, 0);
					}
					else if (0 == strcmp(m, MVAR_FUNCTION_RECOVERY_VALUE))
					{
						zbx_db_trigger_get_function_value(&event->trigger, N_functionid,
								&replace_to, zbx_evaluate_function, 1);
					}
				}
			}
		}
		else if (0 != (macro_type & ZBX_MACRO_TYPE_TRIGGER_URL))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (SUCCEED == zbx_token_is_user_macro(m, &token))
				{
					if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, &phostids))
					{
						zbx_dc_get_user_macro(um_handle, m, phostids->values,
								phostids->values_num, &replace_to);
					}
					pos = token.loc.r;
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to,
							N_functionid, &zbx_dc_get_host_value,
							ZBX_DC_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = zbx_db_with_trigger_itemid(&event->trigger, &replace_to, N_functionid,
							&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_PORT);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_VALUE);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE_TIMESTAMP))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_TIMESTAMP);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE_TIME))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_TIME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE_DATE))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_DATE);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE_AGE))
				{
					ret = zbx_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value, tz, ZBX_VALUE_PROPERTY_AGE);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_VALUE);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE_TIMESTAMP))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_TIMESTAMP);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE_TIME))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_TIME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE_DATE))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_DATE);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE_AGE))
				{
					ret = zbx_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value, tz,
							ZBX_VALUE_PROPERTY_AGE);
				}
				else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
				{
					ret = zbx_get_history_log_value(m, &event->trigger, &replace_to,
							N_functionid, event->clock, event->ns, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_ID))
				{
					zbx_event_get_macro_value(m, event, &replace_to, userid, NULL, NULL);
				}
			}
		}

		if ((ZBX_TOKEN_FUNC_MACRO == token.type || ZBX_TOKEN_USER_FUNC_MACRO == token.type) &&
				NULL != replace_to)
		{
			if (SUCCEED != (ret = zbx_calculate_macro_function(*data, &token.data.func_macro, &replace_to)))
				zbx_free(replace_to);
		}

		if (NULL != um_handle_prev)
		{
			zbx_dc_close_user_macros(um_handle);
			um_handle = um_handle_prev;
		}

		}

		if (FAIL == ret)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot resolve macro '%.*s'",
					(int)(token.loc.r - token.loc.l + 1), *data + token.loc.l);

			if (ZBX_TOKEN_MACRO == token.type && SUCCEED == zbx_is_strict_macro(m))
			{
				if (NULL != error)
				{
					/* return error if strict macro resolving failed */
					zbx_snprintf(error, maxerrlen, "Invalid macro '%.*s' value",
							(int)(token.loc.r - token.loc.l + 1), *data + token.loc.l);

					res = FAIL;
				}
			}

			replace_to = zbx_strdup(replace_to, STR_UNKNOWN_VARIABLE);
		}

		if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_MACRO == token.type && 0 == indexed_macro))
			(*data)[token.loc.r + 1] = c;

		if (NULL != replace_to)
		{
			pos = token.loc.r;

			pos += zbx_replace_mem_dyn(data, &data_alloc, &data_len, token.loc.l,
					token.loc.r - token.loc.l + 1, replace_to, strlen(replace_to));
			zbx_free(replace_to);
		}

		if (ZBX_TOKEN_FUNC_MACRO == token.type || ZBX_TOKEN_USER_FUNC_MACRO == token.type)
			zbx_free(m_ptr);

		pos++;
	}

	zbx_vc_flush_stats();

	zbx_user_names_free(user_names);
	zbx_free(expression);
	zbx_vector_uint64_destroy(&hostids);

	zbx_dc_close_user_macros(um_handle);

	if (NULL != cause_event)
		zbx_db_free_event(cause_event);

	if (NULL != cause_recovery_event)
		zbx_db_free_event(cause_recovery_event);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End %s() data:'%s'", __func__, *data);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_simple_macros with masked secret macros                *
 *          (default setting).                                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_simple_macros(const zbx_uint64_t *actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, const zbx_uint64_t *userid, const zbx_uint64_t *hostid,
		const zbx_dc_host_t *dc_host, const zbx_dc_item_t *dc_item, const zbx_db_alert *alert,
		const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm, const zbx_db_service *service,
		const char *tz, char **data, int macro_type, char *error, int maxerrlen)
{
	return substitute_simple_macros_impl(actionid, event, r_event, userid, hostid, dc_host, dc_item, alert, ack,
			service_alarm, service, tz, NULL, data, macro_type, error, maxerrlen);
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_simple_macros with unmasked secret macros.             *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_simple_macros_unmasked(const zbx_uint64_t *actionid, const zbx_db_event *event,
		const zbx_db_event *r_event, const zbx_uint64_t *userid, const zbx_uint64_t *hostid,
		const zbx_dc_host_t *dc_host, const zbx_dc_item_t *dc_item, const zbx_db_alert *alert,
		const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm, const zbx_db_service *service,
		const char *tz, char **data, int macro_type, char *error, int maxerrlen)
{
	int			ret;
	zbx_dc_um_handle_t	*um_handle;

	um_handle = zbx_dc_open_user_macros_secure();

	ret = substitute_simple_macros_impl(actionid, event, r_event, userid, hostid, dc_host, dc_item, alert, ack,
			service_alarm, service, tz, NULL, data, macro_type, error, maxerrlen);

	zbx_dc_close_user_macros(um_handle);

	return ret;

}
