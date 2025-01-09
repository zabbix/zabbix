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
#include "datafunc.h"

#ifdef HAVE_LIBXML2
#	include "zbxxml.h"
#endif

#include "zbxdb.h"
#include "zbxvariant.h"
#include "zbxeval.h"
#include "zbxdbwrap.h"
#include "zbxcachevalue.h"
#include "zbxstr.h"
#include "zbxexpr.h"
#include "zbxparam.h"
#include "zbx_trigger_constants.h"
#include "zbx_expression_constants.h"
#include "zbxevent.h"
#include "zbxtime.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "zbxinterface.h"
#include "zbxip.h"

/******************************************************************************
 *                                                                            *
 * Purpose: formats full user name from name, surname and alias.              *
 *                                                                            *
 * Parameters: name    - [IN] user name, can be empty string                  *
 *             surname - [IN] user surname, can be empty string               *
 *             alias   - [IN] user alias                                      *
 *                                                                            *
 * Return value: the formatted user fullname.                                 *
 *                                                                            *
 ******************************************************************************/
static char	*format_user_fullname(const char *name, const char *surname, const char *alias)
{
	char	*buf = NULL;
	size_t	buf_alloc = 0, buf_offset = 0;

	zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, name);

	if ('\0' != *surname)
	{
		if (0 != buf_offset)
			zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ' ');

		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, surname);
	}

	if ('\0' != *alias)
	{
		size_t	offset = buf_offset;

		if (0 != buf_offset)
			zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, " (");

		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, alias);

		if (0 != offset)
			zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ')');
	}

	return buf;
}

/* macros that are supported in expression macro */
static const char	*expr_macros[] = {MVAR_HOST_HOST, MVAR_HOSTNAME, MVAR_ITEM_KEY, NULL};

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

/******************************************************************************
 *                                                                            *
 * Purpose: calculate result of expression macro.                             *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
static int	get_expression_macro_result(const zbx_db_event *event, char *data, zbx_strloc_t *loc,
		zbx_timespec_t *ts, char **replace_to, char **error)
{
	int				ret = FAIL;
	zbx_eval_context_t		ctx;
	const zbx_vector_uint64_t	*hostids;
	zbx_variant_t			value;
	zbx_expression_eval_t		eval;
	char				*expression = NULL;
	size_t				exp_alloc = 0, exp_offset = 0;
	zbx_dc_um_handle_t		*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_strncpy_alloc(&expression, &exp_alloc, &exp_offset, data + loc->l, loc->r - loc->l + 1);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() expression: '%s'", __func__, expression);

	um_handle = zbx_dc_open_user_macros();

	if (SUCCEED != zbx_eval_parse_expression(&ctx, expression, ZBX_EVAL_PARSE_EXPRESSION_MACRO, error))
		goto out;

	if (SUCCEED != zbx_db_trigger_get_all_hostids(&event->trigger, &hostids))
	{
		*error = zbx_strdup(NULL, "cannot obtain host identifiers for the expression macro");
		goto out;
	}

	if (SUCCEED != zbx_eval_substitute_macros(&ctx, NULL, &zbx_db_trigger_supplement_eval_resolv, um_handle,
			hostids->values, hostids->values_num, event))
	{
		goto out;
	}

	zbx_expression_eval_init(&eval, ZBX_EXPRESSION_NORMAL, &ctx);
	zbx_expression_eval_resolve_trigger_hosts_items(&eval, &event->trigger);

	if (SUCCEED == (ret = zbx_expression_eval_execute(&eval, ts, &value, error)))
	{
		*replace_to = zbx_strdup(NULL, zbx_variant_value_desc(&value));
		zbx_variant_clear(&value);
	}

	zbx_expression_eval_clear(&eval);
out:
	zbx_eval_clear(&ctx);
	zbx_free(expression);

	zbx_dc_close_user_macros(um_handle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: cache host identifier referenced by an item or a lld-rule.        *
 *                                                                            *
 * Parameters: hostids - [OUT] host identifier cache                          *
 *             itemid  - [IN]  item identifier                                *
 *                                                                            *
 ******************************************************************************/
static void	cache_item_hostid(zbx_vector_uint64_t *hostids, zbx_uint64_t itemid)
{
	if (0 == hostids->values_num)
	{
		zbx_dc_item_t	item;
		int		errcode;

		zbx_dc_config_get_items_by_itemids(&item, &itemid, &errcode, 1);

		if (SUCCEED == errcode)
			zbx_vector_uint64_append(hostids, item.host.hostid);

		zbx_dc_config_clean_items(&item, &errcode, 1);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve {EVENT.OPDATA} macro.                                     *
 *                                                                            *
 ******************************************************************************/
static void	resolve_opdata(const zbx_db_event *event, char **replace_to, const char *tz, char *error, int maxerrlen)
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

			if (SUCCEED == expr_db_item_get_value(itemids.values[i], &val, 0, &ts))
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
		substitute_simple_macros_impl(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, tz,
				NULL, replace_to, ZBX_MACRO_TYPE_TRIGGER_DESCRIPTION, error, maxerrlen);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve {USER.*} macros.                                          *
 *                                                                            *
 ******************************************************************************/
static void	resolve_user_macros(zbx_uint64_t userid, const char *m, char **user_username, char **user_name,
		char **user_surname, int *user_names_found, char **replace_to)
{
	/* use only one DB request for all occurrences of 5 macros */
	if (0 == *user_names_found)
	{
		if (SUCCEED == zbx_db_get_user_names(userid, user_username, user_name, user_surname))
			*user_names_found = 1;
		else
			return;
	}

	if (0 == strcmp(m, MVAR_USER_USERNAME) || 0 == strcmp(m, MVAR_USER_ALIAS))
	{
		*replace_to = zbx_strdup(*replace_to, *user_username);
	}
	else if (0 == strcmp(m, MVAR_USER_NAME))
	{
		*replace_to = zbx_strdup(*replace_to, *user_name);
	}
	else if (0 == strcmp(m, MVAR_USER_SURNAME))
	{
		*replace_to = zbx_strdup(*replace_to, *user_surname);
	}
	else if (0 == strcmp(m, MVAR_USER_FULLNAME))
	{
		zbx_free(*replace_to);
		*replace_to = format_user_fullname(*user_name, *user_surname, *user_username);
	}
}

static int	resolve_host_target_macros(const char *m, const zbx_dc_host_t *dc_host, char **replace_to)
{
	int	ret = SUCCEED;

	if (NULL == dc_host)
		return SUCCEED;

	if (0 == strcmp(m, MVAR_HOST_TARGET_DNS))
	{
		ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_REQUEST_HOST_DNS);
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_CONN))
	{
		ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_REQUEST_HOST_CONN);
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_HOST))
	{
		*replace_to = zbx_strdup(*replace_to, dc_host->host);
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_IP))
	{
		ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_REQUEST_HOST_IP);
	}
	else if (0 == strcmp(m, MVAR_HOST_TARGET_NAME))
	{
		*replace_to = zbx_strdup(*replace_to, dc_host->name);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request cause event value by macro.                               *
 *                                                                            *
 ******************************************************************************/
static int	get_event_cause_value(const char *macro, char **replace_to, const zbx_db_event *event,
		zbx_db_event **cause_event, zbx_db_event **cause_recovery_event, const zbx_uint64_t *recipient_userid,
		const char *tz, char *error, int maxerrlen)
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

		resolve_opdata(c_event, replace_to, tz, error, maxerrlen);
	}

	ret = SUCCEED;
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
	char				c, *replace_to = NULL, sql[64], *m_ptr;
	const char			*m;
	int				N_functionid, indexed_macro, ret, res = SUCCEED,
					pos = 0, found, user_names_found = 0, raw_value;
	size_t				data_alloc, data_len;
	zbx_dc_interface_t		interface;
	zbx_vector_uint64_t		hostids;
	const zbx_vector_uint64_t	*phostids;
	zbx_token_t			token, inner_token;
	zbx_token_search_t		token_search = ZBX_TOKEN_SEARCH_BASIC;
	char				*expression = NULL, *user_username = NULL, *user_name = NULL,
					*user_surname = NULL;
	zbx_dc_um_handle_t		*um_handle;
	zbx_db_event			*cause_event = NULL, *cause_recovery_event = NULL;

	if (NULL == data || NULL == *data || '\0' == **data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:EMPTY", __func__);
		return res;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __func__, *data);

	if (0 != (macro_type & (ZBX_MACRO_TYPE_TRIGGER_DESCRIPTION | ZBX_MACRO_TYPE_EVENT_NAME)))
		token_search |= ZBX_TOKEN_SEARCH_REFERENCES;

	if (0 != (macro_type & (ZBX_MACRO_TYPE_MESSAGE_NORMAL | ZBX_MACRO_TYPE_MESSAGE_RECOVERY |
			ZBX_MACRO_TYPE_MESSAGE_UPDATE | ZBX_MACRO_TYPE_EVENT_NAME)))
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

		if (0 != (macro_type & (ZBX_MACRO_TYPE_MESSAGE_NORMAL | ZBX_MACRO_TYPE_MESSAGE_RECOVERY |
				ZBX_MACRO_TYPE_MESSAGE_UPDATE |
				ZBX_MACRO_TYPE_SCRIPT_NORMAL | ZBX_MACRO_TYPE_SCRIPT_RECOVERY)))
		/* ZBX_MACRO_TYPE_SCRIPT_NORMAL and ZBX_MACRO_TYPE_SCRIPT_RECOVERY behave pretty similar to */
		/* ZBX_MACRO_TYPE_MESSAGE_NORMAL and ZBX_MACRO_TYPE_MESSAGE_RECOVERY. Therefore the code is not duplicated */
		/* but few conditions are added below where behavior differs. */
		{
			const zbx_db_event	*c_event;

			c_event = ((NULL != r_event) ? r_event : event);

			if (EVENT_SOURCE_TRIGGERS == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
				{
					if (NULL == dc_host)
					{
						if (SUCCEED == zbx_db_trigger_get_all_hostids(&c_event->trigger,
								&phostids))
						{
							zbx_dc_get_user_macro(um_handle, m, phostids->values,
									phostids->values_num, &replace_to);
						}
					}
					else
						zbx_dc_get_user_macro(um_handle, m, &dc_host->hostid, 1, &replace_to);

					pos = token.loc.r;
				}
				else if (ZBX_TOKEN_EXPRESSION_MACRO == inner_token.type)
				{
					zbx_timespec_t	ts;
					char		*errmsg = NULL;

					zbx_timespec(&ts);

					if (SUCCEED != (ret = get_expression_macro_result(event, *data,
							&inner_token.data.expression_macro.expression, &ts, &replace_to,
							&errmsg)))
					{
						*errmsg = tolower(*errmsg);
						zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot evaluate"
								" expression macro: %s", __func__, errmsg);
						zbx_strlcpy(error, errmsg, maxerrlen);
						zbx_free(errmsg);
					}
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = expr_db_get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					expr_db_get_escalation_history(*actionid, event, r_event, &replace_to, userid,
							tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_CAUSE, ZBX_CONST_STRLEN(MVAR_EVENT_CAUSE)))
				{
					ret = get_event_cause_value(m, &replace_to, event, &cause_event,
							&cause_recovery_event, userid, tz, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_EVENT_SYMPTOMS))
				{
					ret = expr_db_get_event_symptoms(event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strcmp(m, MVAR_EVENT_OPDATA))
				{
					resolve_opdata(c_event, &replace_to, tz, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_ACK_MESSAGE) || 0 == strcmp(m, MVAR_EVENT_UPDATE_MESSAGE))
				{
					if (0 != (macro_type & ZBX_MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						replace_to = zbx_strdup(replace_to, ack->message);
				}
				else if (0 == strcmp(m, MVAR_ACK_TIME) || 0 == strcmp(m, MVAR_EVENT_UPDATE_TIME))
				{
					if (0 != (macro_type & ZBX_MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						replace_to = zbx_strdup(replace_to, zbx_time2str(ack->clock, tz));
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_TIMESTAMP))
				{
					if (0 != (macro_type & ZBX_MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						replace_to = zbx_dsprintf(replace_to, "%d", ack->clock);
				}
				else if (0 == strcmp(m, MVAR_ACK_DATE) || 0 == strcmp(m, MVAR_EVENT_UPDATE_DATE))
				{
					if (0 != (macro_type & ZBX_MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						replace_to = zbx_strdup(replace_to, zbx_date2str(ack->clock, tz));
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_ACTION))
				{
					if (0 != (macro_type & ZBX_MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
					{
						zbx_problem_get_actions(ack, ZBX_PROBLEM_UPDATE_ACKNOWLEDGE |
								ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE |
								ZBX_PROBLEM_UPDATE_CLOSE | ZBX_PROBLEM_UPDATE_MESSAGE |
								ZBX_PROBLEM_UPDATE_SEVERITY |
								ZBX_PROBLEM_UPDATE_SUPPRESS |
								ZBX_PROBLEM_UPDATE_UNSUPPRESS |
								ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE |
								ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM, tz, &replace_to);
					}
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_STATUS))
				{
					if (0 != (macro_type & ZBX_MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						replace_to = zbx_strdup(replace_to, "1");
					else
						replace_to = zbx_strdup(replace_to, "0");
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_ACTIONJSON))
				{
					if (0 != (macro_type & ZBX_MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack)
						zbx_event_get_json_actions(ack, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					zbx_event_get_macro_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
				{
					ret = zbx_dc_get_host_inventory(m, &c_event->trigger, &replace_to,
							N_functionid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION_ORIG))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_ID);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = expr_db_item_lastvalue(&c_event->trigger, &replace_to, N_functionid,
							raw_value);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME_ORIG))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = expr_db_item_value(&c_event->trigger, &replace_to, N_functionid,
							c_event->clock, c_event->ns, raw_value);
				}
				else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
				{
					ret = expr_get_history_log_value(m, &c_event->trigger, &replace_to,
							N_functionid, c_event->clock, c_event->ns, tz);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUETYPE))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_VALUETYPE);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == indexed_macro && 0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == indexed_macro && 0 == strcmp(m, MVAR_TIMESTAMP))
				{
					replace_to = zbx_dsprintf(replace_to, "%ld", (long)time(NULL));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_DESCRIPTION) ||
						0 == strcmp(m, MVAR_TRIGGER_COMMENT))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.comments);
					substitute_simple_macros_impl(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, NULL, &replace_to,
							ZBX_MACRO_TYPE_TRIGGER_COMMENTS, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_ACK))
				{
					ret = zbx_event_db_count_from_trigger(c_event->objectid, &replace_to, 0, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_PROBLEM_ACK))
				{
					ret = zbx_event_db_count_from_trigger(c_event->objectid, &replace_to, 1, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_PROBLEM_UNACK))
				{
					ret = zbx_event_db_count_from_trigger(c_event->objectid, &replace_to, 1, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EVENTS_UNACK))
				{
					ret = zbx_event_db_count_from_trigger(c_event->objectid, &replace_to, 0, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION))
				{
					zbx_db_trigger_get_expression(&c_event->trigger, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						zbx_db_trigger_get_recovery_expression(&c_event->trigger, &replace_to);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_EXPLAIN))
				{
					zbx_db_trigger_explain_expression(&c_event->trigger, &replace_to,
							zbx_evaluate_function, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY_EXPLAIN))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						zbx_db_trigger_explain_expression(&c_event->trigger, &replace_to,
								zbx_evaluate_function, 1);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
				}
				else if (0 == strcmp(m, MVAR_FUNCTION_VALUE))
				{
					zbx_db_trigger_get_function_value(&c_event->trigger, N_functionid,
							&replace_to, zbx_evaluate_function, 0);
				}
				else if (0 == strcmp(m, MVAR_FUNCTION_RECOVERY_VALUE))
				{
					zbx_db_trigger_get_function_value(&c_event->trigger, N_functionid,
							&replace_to, zbx_evaluate_function, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_HOSTGROUP_NAME))
				{
					ret = expr_db_get_trigger_hostgroup_name(c_event->objectid, userid,
							&replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
					substitute_simple_macros_impl(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, NULL, &replace_to,
							ZBX_MACRO_TYPE_TRIGGER_DESCRIPTION, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME_ORIG))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NSEVERITY))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", (int)c_event->trigger.priority);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_STATUS) || 0 == strcmp(m, MVAR_STATUS))
				{
					replace_to = zbx_strdup(replace_to,
							trigger_value_string(c_event->trigger.value));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_SEVERITY))
				{
					ret = zbx_config_get_trigger_severity_name(c_event->trigger.priority,
							&replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_TEMPLATE_NAME))
				{
					ret = expr_db_get_trigger_template_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_URL))
				{
					replace_to = zbx_strdup(replace_to, event->trigger.url);
					substitute_simple_macros_impl(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, NULL, &replace_to,
							ZBX_MACRO_TYPE_TRIGGER_URL, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_URL_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->trigger.url_name);
					substitute_simple_macros_impl(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, NULL, &replace_to,
							ZBX_MACRO_TYPE_TRIGGER_URL, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_VALUE))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", c_event->trigger.value);
				}
				else if (0 != (macro_type & ZBX_MACRO_TYPE_MESSAGE_UPDATE) && NULL != ack &&
						0 == strcmp(m, MVAR_USER_FULLNAME))
				{
					const char	*user_name1;

					if (SUCCEED == zbx_check_user_permissions(&ack->userid, userid))
						user_name1 = zbx_user_string(ack->userid);
					else
						user_name1 = "Inaccessible user";

					replace_to = zbx_strdup(replace_to, user_name1);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else if (0 != (macro_type & (ZBX_MACRO_TYPE_SCRIPT_NORMAL | ZBX_MACRO_TYPE_SCRIPT_RECOVERY)) &&
						NULL != userid && (0 == strcmp(m, MVAR_USER_USERNAME) ||
						0 == strcmp(m, MVAR_USER_NAME) || 0 == strcmp(m, MVAR_USER_SURNAME) ||
						0 == strcmp(m, MVAR_USER_FULLNAME) || 0 == strcmp(m, MVAR_USER_ALIAS)))
				{
					resolve_user_macros(*userid, m, &user_username, &user_name, &user_surname,
							&user_names_found, &replace_to);
				}
				else if (0 == (macro_type & (ZBX_MACRO_TYPE_SCRIPT_NORMAL | ZBX_MACRO_TYPE_SCRIPT_RECOVERY)))
				{
					ret = resolve_host_target_macros(m, dc_host, &replace_to);
				}
			}
			else if (EVENT_SOURCE_INTERNAL == c_event->source && EVENT_OBJECT_TRIGGER == c_event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
				{
					if (SUCCEED == zbx_db_trigger_get_all_hostids(&c_event->trigger, &phostids))
					{
						zbx_dc_get_user_macro(um_handle, m, phostids->values,
								phostids->values_num, &replace_to);
					}
					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = expr_db_get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					expr_db_get_escalation_history(*actionid, event, r_event, &replace_to, userid,
							tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					zbx_event_get_macro_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
				{
					ret = zbx_dc_get_host_inventory(m, &c_event->trigger, &replace_to,
							N_functionid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION_ORIG))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_DESCRIPTION_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_ID);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME_ORIG))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUETYPE))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_ITEM_VALUETYPE);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = expr_db_get_trigger_value(&c_event->trigger, &replace_to,
							N_functionid, ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == indexed_macro && 0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == indexed_macro && 0 == strcmp(m, MVAR_TIMESTAMP))
				{
					replace_to = zbx_dsprintf(replace_to, "%ld", (long)time(NULL));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_DESCRIPTION) ||
						0 == strcmp(m, MVAR_TRIGGER_COMMENT))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.comments);
					substitute_simple_macros_impl(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, NULL, &replace_to,
							ZBX_MACRO_TYPE_TRIGGER_COMMENTS, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION))
				{
					zbx_db_trigger_get_expression(&c_event->trigger, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						zbx_db_trigger_get_recovery_expression(&c_event->trigger, &replace_to);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_EXPLAIN))
				{
					zbx_db_trigger_explain_expression(&c_event->trigger, &replace_to,
							zbx_evaluate_function, 0);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_EXPRESSION_RECOVERY_EXPLAIN))
				{
					if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == c_event->trigger.recovery_mode)
					{
						zbx_db_trigger_explain_expression(&c_event->trigger, &replace_to,
								zbx_evaluate_function, 1);
					}
					else
						replace_to = zbx_strdup(replace_to, "");
				}
				else if (0 == strcmp(m, MVAR_FUNCTION_VALUE))
				{
					zbx_db_trigger_get_function_value(&c_event->trigger, N_functionid,
							&replace_to, zbx_evaluate_function, 0);
				}
				else if (0 == strcmp(m, MVAR_FUNCTION_RECOVERY_VALUE))
				{
					zbx_db_trigger_get_function_value(&c_event->trigger, N_functionid,
							&replace_to, zbx_evaluate_function, 1);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_HOSTGROUP_NAME))
				{
					ret = expr_db_get_trigger_hostgroup_name(c_event->objectid, userid,
							&replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
					substitute_simple_macros_impl(NULL, c_event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, NULL, &replace_to,
							ZBX_MACRO_TYPE_TRIGGER_DESCRIPTION, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NAME_ORIG))
				{
					replace_to = zbx_strdup(replace_to, c_event->trigger.description);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_NSEVERITY))
				{
					replace_to = zbx_dsprintf(replace_to, "%d", (int)c_event->trigger.priority);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_SEVERITY))
				{
					ret = zbx_config_get_trigger_severity_name(c_event->trigger.priority,
							&replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_STATE))
				{
					replace_to = zbx_strdup(replace_to, trigger_state_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_STATE_ERROR))
				{
					ret = expr_db_get_trigger_error(&event->trigger, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_TEMPLATE_NAME))
				{
					ret = expr_db_get_trigger_template_name(c_event->objectid, userid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_URL))
				{
					replace_to = zbx_strdup(replace_to, event->trigger.url);
					substitute_simple_macros_impl(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, NULL, &replace_to,
							ZBX_MACRO_TYPE_TRIGGER_URL, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_URL_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->trigger.url_name);
					substitute_simple_macros_impl(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL,
							NULL, NULL, NULL, tz, NULL, &replace_to,
							ZBX_MACRO_TYPE_TRIGGER_URL, error, maxerrlen);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_DISCOVERY == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
				{
					if (NULL == dc_host)
						zbx_dc_get_user_macro(um_handle, m, NULL, 0, &replace_to);
					else
						zbx_dc_get_user_macro(um_handle, m, &dc_host->hostid, 1, &replace_to);

					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = expr_db_get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)) &&
						0 != strcmp(m, MVAR_EVENT_DURATION))
				{
					zbx_event_get_macro_value(m, event, &replace_to, userid, NULL, tz);
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_IPADDRESS))
				{
					ret = zbx_event_db_get_dhost(c_event, &replace_to, "s.ip");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_DNS))
				{
					ret = zbx_event_db_get_dhost(c_event, &replace_to, "s.dns");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_STATUS))
				{
					if (SUCCEED == (ret = zbx_event_db_get_dhost(c_event, &replace_to,
							"h.status")))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_dobject_status2str(atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_DEVICE_UPTIME))
				{
					zbx_snprintf(sql, sizeof(sql),
							"case when h.status=%d then h.lastup else h.lastdown end",
							DOBJECT_STATUS_UP);
					if (SUCCEED == (ret = zbx_event_db_get_dhost(c_event, &replace_to, sql)))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_age2str(time(NULL) - atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_RULE_NAME))
				{
					ret = zbx_event_db_get_drule(c_event, &replace_to, "name");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_NAME))
				{
					if (SUCCEED == (ret = zbx_event_db_get_dchecks(c_event, &replace_to,
							"c.type")))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_dservice_type_string(atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_PORT))
				{
					ret = zbx_event_db_get_dservice(c_event, &replace_to, "s.port");
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_STATUS))
				{
					if (SUCCEED == (ret = zbx_event_db_get_dservice(c_event, &replace_to,
							"s.status")))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_dobject_status2str(atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_DISCOVERY_SERVICE_UPTIME))
				{
					zbx_snprintf(sql, sizeof(sql),
							"case when s.status=%d then s.lastup else s.lastdown end",
							DOBJECT_STATUS_UP);
					if (SUCCEED == (ret = zbx_event_db_get_dservice(c_event, &replace_to, sql)))
					{
						replace_to = zbx_strdup(replace_to,
								zbx_age2str(time(NULL) - atoi(replace_to)));
					}
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					if (SUCCEED == (ret = zbx_event_db_get_dhost(c_event, &replace_to,
							"r.proxyid")))
					{
						zbx_uint64_t	proxyid = 0;

						ZBX_DBROW2UINT64(proxyid, replace_to);

						if (0 == proxyid)
							replace_to = zbx_strdup(replace_to, "");
						else
							ret = expr_db_get_proxy_value(proxyid, &replace_to, "name");
					}
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					if (SUCCEED == (ret = zbx_event_db_get_dhost(c_event, &replace_to,
							"r.proxyid")))
					{
						zbx_uint64_t	proxyid = 0;

						ZBX_DBROW2UINT64(proxyid, replace_to);

						if (0 == proxyid)
						{
							replace_to = zbx_strdup(replace_to, "");
						}
						else
						{
							ret = expr_db_get_proxy_value(proxyid, &replace_to,
									"description");
						}
					}
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_TIMESTAMP))
				{
					replace_to = zbx_dsprintf(replace_to, "%ld", (long)time(NULL));
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else
				{
					ret = resolve_host_target_macros(m, dc_host, &replace_to);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_AUTOREGISTRATION == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
				{
					if (NULL == dc_host)
						zbx_dc_get_user_macro(um_handle, m, NULL, 0, &replace_to);
					else
						zbx_dc_get_user_macro(um_handle, m, &dc_host->hostid, 1, &replace_to);

					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = expr_db_get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)) &&
						0 != strcmp(m, MVAR_EVENT_DURATION))
				{
					zbx_event_get_macro_value(m, event, &replace_to, userid, NULL, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_METADATA))
				{
					ret = zbx_event_db_get_autoreg(c_event, &replace_to, "host_metadata");
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = zbx_event_db_get_autoreg(c_event, &replace_to, "host");
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = zbx_event_db_get_autoreg(c_event, &replace_to, "listen_ip");
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = zbx_event_db_get_autoreg(c_event, &replace_to, "listen_port");
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					if (SUCCEED == (ret = zbx_event_db_get_autoreg(c_event, &replace_to,
							"proxyid")))
					{
						zbx_uint64_t	proxyid = 0;

						ZBX_DBROW2UINT64(proxyid, replace_to);

						if (0 == proxyid)
							replace_to = zbx_strdup(replace_to, "");
						else
							ret = expr_db_get_proxy_value(proxyid, &replace_to, "name");
					}
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					if (SUCCEED == (ret = zbx_event_db_get_autoreg(c_event, &replace_to,
							"proxyid")))
					{
						zbx_uint64_t	proxyid = 0;

						ZBX_DBROW2UINT64(proxyid, replace_to);

						if (0 == proxyid)
						{
							replace_to = zbx_strdup(replace_to, "");
						}
						else
						{
							ret = expr_db_get_proxy_value(proxyid, &replace_to,
									"description");
						}
					}
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_TIMESTAMP))
				{
					replace_to = zbx_dsprintf(replace_to, "%ld", (long)time(NULL));
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else
				{
					ret = resolve_host_target_macros(m, dc_host, &replace_to);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_INTERNAL == c_event->source &&
					EVENT_OBJECT_ITEM == c_event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
				{
					cache_item_hostid(&hostids, c_event->objectid);
					zbx_dc_get_user_macro(um_handle, m, hostids.values, hostids.values_num,
							&replace_to);
					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = expr_db_get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					expr_db_get_escalation_history(*actionid, event, r_event, &replace_to, userid,
							tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					zbx_event_get_macro_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
				{
					ret = zbx_dc_get_host_inventory_by_itemid(m, c_event->objectid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_ITEM_DESCRIPTION_ORIG))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY) || 0 == strcmp(m, MVAR_TRIGGER_KEY))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_ITEM_NAME_ORIG))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_ITEM_STATE))
				{
					replace_to = zbx_strdup(replace_to, item_state_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUETYPE))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_VALUETYPE);
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_TIMESTAMP))
				{
					replace_to = zbx_dsprintf(replace_to, "%ld", (long)time(NULL));
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else if (0 == strcmp(m, MVAR_ITEM_STATE_ERROR))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_ERROR);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_INTERNAL == c_event->source &&
					EVENT_OBJECT_LLDRULE == c_event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
				{
					cache_item_hostid(&hostids, c_event->objectid);
					zbx_dc_get_user_macro(um_handle, m, hostids.values, hostids.values_num,
							&replace_to);
					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = expr_db_get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					expr_db_get_escalation_history(*actionid, event, r_event, &replace_to, userid,
							tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					zbx_event_get_macro_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_DESCRIPTION))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
						0 == strncmp(m, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
				{
					ret = zbx_dc_get_host_inventory_by_itemid(m, c_event->objectid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_DESCRIPTION))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_DESCRIPTION_ORIG))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_DESCRIPTION_ORIG);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, c_event->objectid);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_KEY))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_KEY);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_KEY_ORIG))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_KEY_ORIG);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_NAME))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_NAME);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_NAME_ORIG))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_NAME_ORIG);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_STATE))
				{
					replace_to = zbx_strdup(replace_to, item_state_string(c_event->value));
				}
				else if (0 == strcmp(m, MVAR_PROXY_NAME))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_PROXY_NAME);
				}
				else if (0 == strcmp(m, MVAR_PROXY_DESCRIPTION))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_PROXY_DESCRIPTION);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_TIMESTAMP))
				{
					replace_to = zbx_dsprintf(replace_to, "%ld", (long)time(NULL));
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
				else if (0 == strcmp(m, MVAR_LLDRULE_STATE_ERROR))
				{
					ret = expr_db_get_item_value(c_event->objectid, &replace_to,
							ZBX_REQUEST_ITEM_ERROR);
				}
			}
			else if (0 == indexed_macro && EVENT_SOURCE_SERVICE == c_event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
				{
					if (NULL == dc_host)
						zbx_dc_get_user_macro(um_handle, m, NULL, 0, &replace_to);
					else
						zbx_dc_get_user_macro(um_handle, m, &dc_host->hostid, 1, &replace_to);

					pos = token.loc.r;
				}
				else if (NULL != actionid &&
						0 == strncmp(m, MVAR_ACTION, ZBX_CONST_STRLEN(MVAR_ACTION)))
				{
					ret = expr_db_get_action_value(m, *actionid, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_TIME))
				{
					replace_to = zbx_strdup(replace_to, zbx_time2str(time(NULL), tz));
				}
				else if (0 == strcmp(m, MVAR_TIMESTAMP))
				{
					replace_to = zbx_dsprintf(replace_to, "%ld", (long)time(NULL));
				}
				else if (0 == strcmp(m, MVAR_DATE))
				{
					replace_to = zbx_strdup(replace_to, zbx_date2str(time(NULL), tz));
				}
				else if (NULL != actionid && 0 == strcmp(m, MVAR_ESC_HISTORY))
				{
					expr_db_get_escalation_history(*actionid, event, r_event, &replace_to, userid, tz);
				}
				else if (0 == strncmp(m, MVAR_EVENT_RECOVERY, ZBX_CONST_STRLEN(MVAR_EVENT_RECOVERY)))
				{
					if (NULL != r_event)
						get_recovery_event_value(m, r_event, &replace_to, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_NSEVERITY))
				{
					if (NULL != service_alarm)
						replace_to = zbx_dsprintf(replace_to, "%d", (int)service_alarm->value);
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_SEVERITY))
				{
					if (NULL != service_alarm)
					{
						if (FAIL == zbx_config_get_trigger_severity_name(service_alarm->value,
								&replace_to))
						{
							replace_to = zbx_strdup(replace_to, "unknown");
						}
					}
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_DATE))
				{
					if (NULL != service_alarm)
					{
						replace_to = zbx_strdup(replace_to, zbx_date2str(service_alarm->clock,
								tz));
					}
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_TIME))
				{
					if (NULL != service_alarm)
					{
						replace_to = zbx_strdup(replace_to, zbx_time2str(service_alarm->clock,
								tz));
					}
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_TIMESTAMP))
				{
					if (NULL != service_alarm)
						replace_to = zbx_dsprintf(replace_to, "%d", service_alarm->clock);
				}
				else if (0 == strcmp(m, MVAR_EVENT_UPDATE_STATUS))
				{
					if (0 != (macro_type & ZBX_MACRO_TYPE_MESSAGE_UPDATE) && NULL != service_alarm)
						replace_to = zbx_strdup(replace_to, "1");
					else
						replace_to = zbx_strdup(replace_to, "0");
				}
				else if (0 == strcmp(m, MVAR_EVENT_STATUS) || 0 == strcmp(m, MVAR_EVENT_VALUE))
				{
					get_current_event_value(m, c_event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_EVENT_NAME))
				{
					replace_to = zbx_strdup(replace_to, event->name);
				}
				else if (0 == strncmp(m, MVAR_EVENT, ZBX_CONST_STRLEN(MVAR_EVENT)))
				{
					zbx_event_get_macro_value(m, event, &replace_to, userid, r_event, tz);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_NAME))
				{
					replace_to = zbx_strdup(replace_to, service->name);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, service->serviceid);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_DESCRIPTION))
				{
					replace_to = zbx_strdup(replace_to, service->description);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_ROOTCAUSE))
				{
					expr_db_get_rootcause(service, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_TAGS))
				{
					zbx_event_get_str_tags(event, &replace_to);
				}
				else if (0 == strcmp(m, MVAR_SERVICE_TAGSJSON))
				{
					zbx_event_get_json_tags(event, &replace_to);
				}
				else if (0 == strncmp(m, MVAR_SERVICE_TAGS_PREFIX,
						ZBX_CONST_STRLEN(MVAR_SERVICE_TAGS_PREFIX)))
				{
					zbx_event_get_tag(m + ZBX_CONST_STRLEN(MVAR_SERVICE_TAGS_PREFIX), event,
							&replace_to);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SENDTO))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->sendto);
				}
				else if (0 == strcmp(m, MVAR_ALERT_SUBJECT))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->subject);
				}
				else if (0 == strcmp(m, MVAR_ALERT_MESSAGE))
				{
					if (NULL != alert)
						replace_to = zbx_strdup(replace_to, alert->message);
				}
			}
		}
		else if (0 != (macro_type & (ZBX_MACRO_TYPE_TRIGGER_DESCRIPTION | ZBX_MACRO_TYPE_TRIGGER_COMMENTS |
					ZBX_MACRO_TYPE_EVENT_NAME)))
		{
			if (EVENT_OBJECT_TRIGGER == event->object)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
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

						if (SUCCEED != (ret = get_expression_macro_result(event, *data,
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
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = expr_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value);
				}
				else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
				{
					ret = expr_get_history_log_value(m, &event->trigger, &replace_to,
							N_functionid, event->clock, event->ns, tz);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = expr_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value);
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
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
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
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_PORT);
				}
				else if (0 == strcmp(m, MVAR_TRIGGER_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
				{
					ret = expr_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
							raw_value);
				}
				else if (0 == strcmp(m, MVAR_ITEM_VALUE))
				{
					ret = expr_db_item_value(&event->trigger, &replace_to, N_functionid,
							event->clock, event->ns, raw_value);
				}
				else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
				{
					ret = expr_get_history_log_value(m, &event->trigger, &replace_to,
							N_functionid, event->clock, event->ns, tz);
				}
				else if (0 == strcmp(m, MVAR_EVENT_ID))
				{
					zbx_event_get_macro_value(m, event, &replace_to, userid, NULL, NULL);
				}
			}
		}
		else if (0 == indexed_macro &&
				0 != (macro_type & (ZBX_MACRO_TYPE_ITEM_KEY | ZBX_MACRO_TYPE_PARAMS_FIELD |
						ZBX_MACRO_TYPE_LLD_FILTER | ZBX_MACRO_TYPE_ALLOWED_HOSTS |
						ZBX_MACRO_TYPE_SCRIPT_PARAMS_FIELD | ZBX_MACRO_TYPE_QUERY_FILTER)))
		{
			zbx_uint64_t			c_hostid, c_itemid;
			const char			*host, *name;
			const zbx_dc_interface_t	*c_interface;

			if (NULL != history_data_item)
			{
				c_hostid = history_data_item->host.hostid;
				c_itemid = history_data_item->itemid;
				host = history_data_item->host.host;
				name = history_data_item->host.name;
				c_interface = &history_data_item->interface;
			}
			else
			{
				c_hostid = dc_item->host.hostid;
				c_itemid = dc_item->itemid;
				host = dc_item->host.host;
				name = dc_item->host.name;
				c_interface = &dc_item->interface;
			}

			if ((ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type)) &&
					0 == (ZBX_MACRO_TYPE_QUERY_FILTER & macro_type))
			{
				zbx_dc_get_user_macro(um_handle, m, &c_hostid, 1, &replace_to);

				pos = token.loc.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
			{
				replace_to = zbx_strdup(replace_to, host);
			}
			else if (0 == strcmp(m, MVAR_HOST_NAME))
			{
				replace_to = zbx_strdup(replace_to, name);
			}
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (INTERFACE_TYPE_UNKNOWN != c_interface->type)
				{
					if ('\0' != *c_interface->ip_orig && FAIL == zbx_is_ip(c_interface->ip_orig))
					{
						ret = FAIL;
					}
					else
						replace_to = zbx_strdup(replace_to, c_interface->ip_orig);
				}
				else
				{
					ret = zbx_dc_get_interface_value(c_hostid, c_itemid, &replace_to,
							ZBX_REQUEST_HOST_IP);
				}
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (INTERFACE_TYPE_UNKNOWN != c_interface->type)
				{
					if ('\0' != *c_interface->dns_orig &&
							FAIL == zbx_is_ip(c_interface->dns_orig) &&
							FAIL == zbx_validate_hostname(c_interface->dns_orig))
					{
						ret = FAIL;
					}
					else
						replace_to = zbx_strdup(replace_to, c_interface->dns_orig);
				}
				else
				{
					ret = zbx_dc_get_interface_value(c_hostid, c_itemid, &replace_to,
							ZBX_REQUEST_HOST_DNS);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (INTERFACE_TYPE_UNKNOWN != c_interface->type)
				{
					if (FAIL == zbx_is_ip(c_interface->addr) &&
							FAIL == zbx_validate_hostname(c_interface->addr))
					{
						ret = FAIL;
					}
					else
						replace_to = zbx_strdup(replace_to, c_interface->addr);
				}
				else
				{
					ret = zbx_dc_get_interface_value(c_hostid, c_itemid, &replace_to,
							ZBX_REQUEST_HOST_CONN);
				}
			}
			else if (0 == strcmp(m, MVAR_HOST_PORT))
			{
				if (0 == (macro_type & ZBX_MACRO_TYPE_ALLOWED_HOSTS))
				{
					if (INTERFACE_TYPE_UNKNOWN != c_interface->type)
					{
						zbx_dsprintf(replace_to, "%u", c_interface->port);
					}
					else
					{
						ret = zbx_dc_get_interface_value(c_hostid, c_itemid, &replace_to,
								ZBX_REQUEST_HOST_PORT);
					}
				}
			}
			else if (0 != (macro_type & ZBX_MACRO_TYPE_SCRIPT_PARAMS_FIELD))
			{
				if (0 == strcmp(m, MVAR_ITEM_ID))
				{
					replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, dc_item->itemid);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY))
				{
					replace_to = zbx_strdup(replace_to, dc_item->key);
				}
				else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
				{
					replace_to = zbx_strdup(replace_to, dc_item->key_orig);
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
				{
					ret = zbx_dc_get_host_inventory_by_itemid(m, dc_item->itemid, &replace_to);
				}
			}
		}
		else if (0 != (macro_type & (ZBX_MACRO_TYPE_COMMON | ZBX_MACRO_TYPE_SNMP_OID)))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
			{
				if (NULL != hostid)
					zbx_dc_get_user_macro(um_handle, m, hostid, 1, &replace_to);
				else
					zbx_dc_get_user_macro(um_handle, m, NULL, 0, &replace_to);

				pos = token.loc.r;
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & ZBX_MACRO_TYPE_SCRIPT))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
			{
				zbx_dc_get_user_macro(um_handle, m, &dc_host->hostid, 1, &replace_to);
				pos = token.loc.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
				replace_to = zbx_strdup(replace_to, dc_host->host);
			else if (0 == strcmp(m, MVAR_HOST_NAME))
				replace_to = zbx_strdup(replace_to, dc_host->name);
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				ret = zbx_dc_get_interface_value(dc_host->hostid, 0, &replace_to, ZBX_REQUEST_HOST_IP);
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				ret = zbx_dc_get_interface_value(dc_host->hostid, 0, &replace_to, ZBX_REQUEST_HOST_DNS);
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				ret = zbx_dc_get_interface_value(dc_host->hostid, 0, &replace_to,
						ZBX_REQUEST_HOST_CONN);
			}
			else if (0 == strcmp(m, MVAR_HOST_PORT))
			{
				ret = zbx_dc_get_interface_value(dc_host->hostid, 0, &replace_to, ZBX_REQUEST_HOST_PORT);
			}
			else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
			{
				ret = zbx_dc_get_host_inventory_by_hostid(m, dc_host->hostid, &replace_to);
			}
			else if (NULL != userid)
			{
				if (0 == strcmp(m, MVAR_USER_USERNAME) || 0 == strcmp(m, MVAR_USER_NAME) ||
						0 == strcmp(m, MVAR_USER_SURNAME) ||
						0 == strcmp(m, MVAR_USER_FULLNAME) || 0 == strcmp(m, MVAR_USER_ALIAS))
				{
					resolve_user_macros(*userid, m, &user_username, &user_name, &user_surname,
							&user_names_found, &replace_to);
				}
			}
		}
		else if (0 == indexed_macro && (0 != (macro_type & (ZBX_MACRO_TYPE_HTTP_RAW | ZBX_MACRO_TYPE_HTTP_JSON |
				ZBX_MACRO_TYPE_HTTP_XML))))
		{
			if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
			{
				zbx_dc_get_user_macro(um_handle, m, &dc_host->hostid, 1, &replace_to);
				pos = token.loc.r;
			}
			else if (0 == strcmp(m, MVAR_HOST_HOST) || 0 == strcmp(m, MVAR_HOSTNAME))
			{
				replace_to = zbx_strdup(replace_to, dc_host->host);
			}
			else if (0 == strcmp(m, MVAR_HOST_NAME))
			{
				replace_to = zbx_strdup(replace_to, dc_host->name);
			}
			else if (0 == strcmp(m, MVAR_HOST_IP) || 0 == strcmp(m, MVAR_IPADDRESS))
			{
				if (SUCCEED == (ret = zbx_dc_config_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.ip_orig);
			}
			else if	(0 == strcmp(m, MVAR_HOST_DNS))
			{
				if (SUCCEED == (ret = zbx_dc_config_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.dns_orig);
			}
			else if (0 == strcmp(m, MVAR_HOST_CONN))
			{
				if (SUCCEED == (ret = zbx_dc_config_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.addr);
			}
			else if (0 == strcmp(m, MVAR_HOST_PORT))
			{
				if (SUCCEED == (ret = zbx_dc_config_get_interface(&interface, dc_host->hostid, 0)))
					replace_to = zbx_strdup(replace_to, interface.port_orig);
			}
			else if (0 == strcmp(m, MVAR_ITEM_ID))
			{
				replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, dc_item->itemid);
			}
			else if (0 == strcmp(m, MVAR_ITEM_KEY))
			{
				replace_to = zbx_strdup(replace_to, dc_item->key);
			}
			else if (0 == strcmp(m, MVAR_ITEM_KEY_ORIG))
			{
				replace_to = zbx_strdup(replace_to, dc_item->key_orig);
			}
		}
		else if (0 == indexed_macro && 0 != (macro_type & ZBX_MACRO_TYPE_ALERT_EMAIL) &&
				(ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type)))
		{
			if ((EVENT_SOURCE_INTERNAL == event->source && EVENT_OBJECT_TRIGGER == event->object) ||
					EVENT_SOURCE_TRIGGERS == event->source)
			{
				if (NULL != event->trigger.expression && NULL != event->trigger.recovery_expression &&
						SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, &phostids))
				{
					zbx_dc_get_user_macro(um_handle, m, phostids->values, phostids->values_num,
							&replace_to);
				}
			}
			else if (EVENT_SOURCE_INTERNAL == event->source && (EVENT_OBJECT_ITEM == event->object ||
					EVENT_OBJECT_LLDRULE == event->object))
			{
				cache_item_hostid(&hostids, event->objectid);
				zbx_dc_get_user_macro(um_handle, m, hostids.values, hostids.values_num, &replace_to);
			}
			else
				zbx_dc_get_user_macro(um_handle, m, NULL, 0, &replace_to);

			pos = token.loc.r;
		}
		else if (0 != (macro_type & ZBX_MACRO_TYPE_TRIGGER_TAG))
		{
			if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
			{
				if (ZBX_TOKEN_USER_MACRO == token.type || (ZBX_TOKEN_USER_FUNC_MACRO == token.type &&
						0 == strncmp(m, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
				{
					if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, &phostids))
					{
						zbx_dc_get_user_macro(um_handle, m, phostids->values,
								phostids->values_num, &replace_to);
					}
					pos = token.loc.r;
				}
				else if (0 == strncmp(m, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
				{
					ret = zbx_dc_get_host_inventory(m, &event->trigger, &replace_to,
							N_functionid);
				}
				else if (0 == strcmp(m, MVAR_HOST_ID))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_ID);
				}
				else if (0 == strcmp(m, MVAR_HOST_HOST))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_HOST);
				}
				else if (0 == strcmp(m, MVAR_HOST_NAME))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_NAME);
				}
				else if (0 == strcmp(m, MVAR_HOST_IP))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_IP);
				}
				else if (0 == strcmp(m, MVAR_HOST_DNS))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_DNS);
				}
				else if (0 == strcmp(m, MVAR_HOST_CONN))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_CONN);
				}
				else if (0 == strcmp(m, MVAR_HOST_PORT))
				{
					ret = expr_db_get_trigger_value(&event->trigger, &replace_to, N_functionid,
							ZBX_REQUEST_HOST_PORT);
				}

				if (EVENT_SOURCE_TRIGGERS == event->source)
				{
					if (0 == strcmp(m, MVAR_ITEM_LASTVALUE))
					{
						ret = expr_db_item_lastvalue(&event->trigger, &replace_to, N_functionid,
								raw_value);
					}
					else if (0 == strcmp(m, MVAR_ITEM_VALUE))
					{
						ret = expr_db_item_value(&event->trigger, &replace_to, N_functionid,
								event->clock, event->ns, raw_value);
					}
					else if (0 == strncmp(m, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
					{
						ret = expr_get_history_log_value(m, &event->trigger, &replace_to,
								N_functionid, event->clock, event->ns, tz);
					}
					else if (0 == strcmp(m, MVAR_TRIGGER_ID))
					{
						replace_to = zbx_dsprintf(replace_to, ZBX_FS_UI64, event->objectid);
					}
				}
			}
		}

		if (0 != (macro_type & (ZBX_MACRO_TYPE_HTTP_JSON | ZBX_MACRO_TYPE_SCRIPT_PARAMS_FIELD)) &&
				NULL != replace_to)
		{
			zbx_json_escape(&replace_to);
		}

		if (0 != (macro_type & ZBX_MACRO_TYPE_QUERY_FILTER) && NULL != replace_to)
		{
			char	*esc;

			esc = zbx_dyn_escape_string(replace_to, "\\");
			zbx_free(replace_to);
			replace_to = esc;
		}

		if ((ZBX_TOKEN_FUNC_MACRO == token.type || ZBX_TOKEN_USER_FUNC_MACRO == token.type) &&
				NULL != replace_to)
		{
			if (SUCCEED != (ret = zbx_calculate_macro_function(*data, &token.data.func_macro, &replace_to)))
				zbx_free(replace_to);
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

	zbx_free(user_username);
	zbx_free(user_name);
	zbx_free(user_surname);
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

typedef struct
{
	zbx_uint64_t				*hostid;
	zbx_dc_item_t				*dc_item;
	const struct zbx_json_parse		*jp_row;
	const zbx_vector_lld_macro_path_ptr_t	*lld_macro_paths;
	int					macro_type;
}
replace_key_param_data_t;

/******************************************************************************
 *                                                                            *
 * Comments: auxiliary function for substitute_key_macros().                  *
 *                                                                            *
 ******************************************************************************/
static int	replace_key_param_cb(const char *data, int key_type, int level, int num, int quoted, void *cb_data,
			char **param)
{
	replace_key_param_data_t		*replace_key_param_data = (replace_key_param_data_t *)cb_data;
	zbx_uint64_t				*hostid = replace_key_param_data->hostid;
	zbx_dc_item_t				*dc_item = replace_key_param_data->dc_item;
	const struct zbx_json_parse		*jp_row = replace_key_param_data->jp_row;
	const zbx_vector_lld_macro_path_ptr_t	*lld_macros = replace_key_param_data->lld_macro_paths;
	int					macro_type = replace_key_param_data->macro_type, ret = SUCCEED;

	ZBX_UNUSED(num);

	if (ZBX_KEY_TYPE_ITEM == key_type && 0 == level)
		return ret;

	if (NULL == strchr(data, '{'))
		return ret;

	*param = zbx_strdup(NULL, data);

	if (0 != level)
		zbx_unquote_key_param(*param);

	if (NULL == jp_row)
		substitute_simple_macros_impl(NULL, NULL, NULL, NULL, hostid, NULL, dc_item, NULL, NULL, NULL, NULL,
				NULL, NULL, param, macro_type, NULL, 0);
	else
		zbx_substitute_lld_macros(param, jp_row, lld_macros, ZBX_MACRO_ANY, NULL, 0);

	if (0 != level)
	{
		if (FAIL == (ret = zbx_quote_key_param(param, quoted)))
			zbx_free(*param);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: safely substitutes macros in parameters of an item key and OID.   *
 *                                                                            *
 * Example:  key                     | macro  | result            | return    *
 *          -------------------------+--------+-------------------+---------  *
 *           echo.sh[{$MACRO}]       | a      | echo.sh[a]        | SUCCEED   *
 *           echo.sh[{$MACRO}]       | a\     | echo.sh[a\]       | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a      | echo.sh["a"]      | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a\     | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       |  a     | echo.sh[" a"]     | SUCCEED   *
 *           echo.sh[{$MACRO}]       |  a\    | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     |  a     | echo.sh[" a"]     | SUCCEED   *
 *           echo.sh["{$MACRO}"]     |  a\    | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | "a"    | echo.sh["\"a\""]  | SUCCEED   *
 *           echo.sh[{$MACRO}]       | "a"\   | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | "a"    | echo.sh["\"a\""]  | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | "a"\   | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | a,b    | echo.sh["a,b"]    | SUCCEED   *
 *           echo.sh[{$MACRO}]       | a,b\   | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | a,b    | echo.sh["a,b"]    | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a,b\   | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | a]     | echo.sh["a]"]     | SUCCEED   *
 *           echo.sh[{$MACRO}]       | a]\    | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | a]     | echo.sh["a]"]     | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | a]\    | undefined         | FAIL      *
 *           echo.sh[{$MACRO}]       | [a     | echo.sh["a]"]     | SUCCEED   *
 *           echo.sh[{$MACRO}]       | [a\    | undefined         | FAIL      *
 *           echo.sh["{$MACRO}"]     | [a     | echo.sh["[a"]     | SUCCEED   *
 *           echo.sh["{$MACRO}"]     | [a\    | undefined         | FAIL      *
 *           ifInOctets.{#SNMPINDEX} | 1      | ifInOctets.1      | SUCCEED   *
 *                                                                            *
 ******************************************************************************/
int	substitute_key_macros_impl(char **data, zbx_uint64_t *hostid, zbx_dc_item_t *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		int macro_type, char *error, size_t maxerrlen)
{
	replace_key_param_data_t	replace_key_param_data;
	int				key_type, ret;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() data:'%s'", __func__, *data);

	replace_key_param_data.hostid = hostid;
	replace_key_param_data.dc_item = dc_item;
	replace_key_param_data.jp_row = jp_row;
	replace_key_param_data.lld_macro_paths = lld_macro_paths;
	replace_key_param_data.macro_type = macro_type;

	switch (macro_type)
	{
		case ZBX_MACRO_TYPE_ITEM_KEY:
			key_type = ZBX_KEY_TYPE_ITEM;
			break;
		case ZBX_MACRO_TYPE_SNMP_OID:
			key_type = ZBX_KEY_TYPE_OID;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	ret = zbx_replace_key_params_dyn(data, key_type, replace_key_param_cb, &replace_key_param_data, error,
			maxerrlen);

	zabbix_log(LOG_LEVEL_TRACE, "End of %s():%s data:'%s'", __func__, zbx_result_string(ret), *data);

	return ret;
}

#ifdef HAVE_LIBXML2
/******************************************************************************
 *                                                                            *
 * Comments: auxiliary function for substitute_macros_xml().                  *
 *                                                                            *
 ******************************************************************************/
static void	substitute_macros_in_xml_elements(const zbx_dc_item_t *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, xmlNode *node)
{
	xmlChar	*value;
	xmlAttr	*attr;
	char	*value_tmp;

	for (;NULL != node; node = node->next)
	{
		switch (node->type)
		{
			case XML_TEXT_NODE:
				if (NULL == (value = xmlNodeGetContent(node)))
					break;

				value_tmp = zbx_strdup(NULL, (const char *)value);

				if (NULL != item)
				{
					substitute_simple_macros_impl(NULL, NULL, NULL, NULL, NULL, &item->host, item,
							NULL, NULL, NULL, NULL, NULL, NULL, &value_tmp,
							ZBX_MACRO_TYPE_HTTP_XML, NULL, 0);
				}
				else
				{
					zbx_substitute_lld_macros(&value_tmp, jp_row, lld_macro_paths, ZBX_MACRO_ANY,
							NULL, 0);
				}

				xmlNodeSetContent(node, NULL);
				xmlNodeAddContent(node, (xmlChar *)value_tmp);

				zbx_free(value_tmp);
				xmlFree(value);
				break;
			case XML_CDATA_SECTION_NODE:
				if (NULL == (value = xmlNodeGetContent(node)))
					break;

				value_tmp = zbx_strdup(NULL, (const char *)value);

				if (NULL != item)
				{
					substitute_simple_macros_impl(NULL, NULL, NULL, NULL, NULL, &item->host, item,
							NULL, NULL, NULL, NULL, NULL, NULL, &value_tmp,
							ZBX_MACRO_TYPE_HTTP_RAW, NULL, 0);
				}
				else
				{
					zbx_substitute_lld_macros(&value_tmp, jp_row, lld_macro_paths, ZBX_MACRO_ANY,
							NULL, 0);
				}

				xmlNodeSetContent(node, NULL);
				xmlNodeAddContent(node, (xmlChar *)value_tmp);

				zbx_free(value_tmp);
				xmlFree(value);
				break;
			case XML_ELEMENT_NODE:
				for (attr = node->properties; NULL != attr; attr = attr->next)
				{
					if (NULL == attr->name || NULL == (value = xmlGetProp(node, attr->name)))
						continue;

					value_tmp = zbx_strdup(NULL, (const char *)value);

					if (NULL != item)
					{
						substitute_simple_macros_impl(NULL, NULL, NULL, NULL, NULL, &item->host,
								item, NULL, NULL, NULL, NULL, NULL, NULL, &value_tmp,
								ZBX_MACRO_TYPE_HTTP_XML, NULL, 0);
					}
					else
					{
						zbx_substitute_lld_macros(&value_tmp, jp_row, lld_macro_paths,
								ZBX_MACRO_ANY, NULL, 0);
					}

					xmlSetProp(node, attr->name, (xmlChar *)value_tmp);

					zbx_free(value_tmp);
					xmlFree(value);
				}
				break;
			default:
				break;
		}

		substitute_macros_in_xml_elements(item, jp_row, lld_macro_paths, node->children);
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: Substitutes simple or LLD macros in XML text nodes, attributes of *
 *          a node or in CDATA section, validates XML.                        *
 *                                                                            *
 * Parameters: data            - [IN/OUT] pointer to buffer that contains xml *
 *             item            - [IN] item for simple macro substitution      *
 *             jp_row          - [IN] discovery data for lld macro            *
 *                                    substitution                            *
 *             lld_macro_paths - [IN] lld macro paths                         *
 *             error           - [OUT] reason for xml parsing failure         *
 *             maxerrlen       - [IN] size of error buffer                    *
 *                                                                            *
 * Return value: SUCCEED or FAIL if XML validation has failed.                *
 *                                                                            *
 ******************************************************************************/
static int	substitute_macros_xml_impl(char **data, const zbx_dc_item_t *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, int maxerrlen)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(data);
	ZBX_UNUSED(item);
	ZBX_UNUSED(jp_row);
	ZBX_UNUSED(lld_macro_paths);
	zbx_snprintf(error, maxerrlen, "Support for XML was not compiled in");
	return FAIL;
#else
	xmlDoc		*doc;
	xmlNode		*root_element;
	xmlChar		*mem;
	int		size, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == zbx_open_xml(*data, 0, maxerrlen, (void **)&doc, (void **)&root_element, &error))
	{
		if (NULL == doc)
			goto exit;

		if (NULL == root_element)
			goto clean;
	}

	substitute_macros_in_xml_elements(item, jp_row, lld_macro_paths, root_element);
	xmlDocDumpMemory(doc, &mem, &size);

	if (FAIL == zbx_check_xml_memory((char *)mem, maxerrlen, &error))
		goto clean;

	zbx_free(*data);
	*data = zbx_malloc(NULL, size + 1);
	memcpy(*data, (const char *)mem, size + 1);
	xmlFree(mem);
	ret = SUCCEED;
clean:
	xmlFreeDoc(doc);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#endif
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

void	zbx_substitute_simple_macros_allowed_hosts(zbx_history_recv_item_t *item, char **allowed_peers)
{
	substitute_simple_macros_impl(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			item, allowed_peers, ZBX_MACRO_TYPE_ALLOWED_HOSTS, NULL, 0);
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

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_macros_xml with masked secret macros.                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_macros_xml(char **data, const zbx_dc_item_t *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, int maxerrlen)
{
	return substitute_macros_xml_impl(data, item, jp_row, lld_macro_paths, error, maxerrlen);
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_macros_xml with unmasked secret macros.                *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_macros_xml_unmasked(char **data, const zbx_dc_item_t *item, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, int maxerrlen)
{
	int			ret;
	zbx_dc_um_handle_t	*um_handle;

	um_handle = zbx_dc_open_user_macros_secure();

	ret = substitute_macros_xml_impl(data, item, jp_row, lld_macro_paths, error, maxerrlen);

	zbx_dc_close_user_macros(um_handle);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_key_macros with masked secret macros.                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_key_macros(char **data, zbx_uint64_t *hostid, zbx_dc_item_t *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		int macro_type, char *error, size_t maxerrlen)
{
	return substitute_key_macros_impl(data, hostid, dc_item, jp_row, lld_macro_paths, macro_type, error, maxerrlen);
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute_key_macros with unmasked secret macros.                *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_key_macros_unmasked(char **data, zbx_uint64_t *hostid, zbx_dc_item_t *dc_item,
		const struct zbx_json_parse *jp_row, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		int macro_type, char *error, size_t maxerrlen)
{
	int			ret;
	zbx_dc_um_handle_t	*um_handle;

	um_handle = zbx_dc_open_user_macros_secure();

	ret = substitute_key_macros_impl(data, hostid, dc_item, jp_row, lld_macro_paths, macro_type, error, maxerrlen);

	zbx_dc_close_user_macros(um_handle);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extract index from valid indexed host or item key macro.          *
 *                                                                            *
 * Return value: the index or -1 if it was not valid indexed host or item key *
 *               macro.                                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_expr_macro_index(const char *macro)
{
	zbx_strloc_t	loc;
	int		func_num;

	loc.l = 0;
	loc.r = strlen(macro) - 1;

	if (NULL != zbx_macro_in_list(macro, loc, expr_macros, &func_num))
		return func_num;

	return -1;
}
