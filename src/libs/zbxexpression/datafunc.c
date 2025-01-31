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
#include "datafunc.h"
#include "expression.h"

#include "zbxdb.h"
#include "zbxcacheconfig.h"
#include "zbx_expression_constants.h"
#include "zbxevent.h"
#include "zbxdbwrap.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxalgo.h"
#include "zbxtime.h"

/******************************************************************************
 *                                                                            *
 * Purpose: get template trigger ID from which the trigger is inherited.      *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	expr_db_get_templateid_by_triggerid(zbx_uint64_t triggerid, zbx_uint64_t *templateid)
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
int	expr_db_get_trigger_template_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to)
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

/******************************************************************************
 *                                                                            *
 * Purpose: get comma-space separated host group names in which the trigger   *
 *          is defined.                                                       *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	expr_db_get_trigger_hostgroup_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to)
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

/******************************************************************************
 *                                                                            *
 * Purpose: get item key, replace macros in the key.                          *
 *                                                                            *
 * Parameters: dc_item    - [IN] item information used in substitution        *
 *             replace_to - [OUT] string with item key with replaced macros   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_substitute_macros_in_item_key(zbx_dc_item_t *dc_item, char **replace_to)
{
	char	*key = zbx_strdup(NULL, dc_item->key_orig);

	substitute_key_macros_impl(&key, NULL, dc_item, NULL, NULL, ZBX_MACRO_TYPE_ITEM_KEY, NULL, 0);
	zbx_free(*replace_to);
	*replace_to = key;
}

int	expr_db_get_trigger_error(const zbx_db_trigger *trigger, char **replace_to)
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

int	expr_get_item_key(zbx_uint64_t itemid, char **replace_to)
{
	zbx_dc_item_t	dc_item;
	int		ret = FAIL, errcode;

	zbx_dc_config_get_items_by_itemids(&dc_item, &itemid, &errcode, 1);

	if (SUCCEED == errcode)
	{
		zbx_substitute_macros_in_item_key(&dc_item, replace_to);
		ret = SUCCEED;
	}

	zbx_dc_config_clean_items(&dc_item, &errcode, 1);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve escalation history.                                      *
 *                                                                            *
 ******************************************************************************/
void	expr_db_get_escalation_history(zbx_uint64_t actionid, const zbx_db_event *event, const zbx_db_event *r_event,
			char **replace_to, const zbx_uint64_t *recipient_userid, const char *tz)
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

	result = zbx_db_select("select a.clock,a.alerttype,a.status,mt.name,a.sendto,a.error,a.esc_step,a.userid,a.message"
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

/******************************************************************************
 *                                                                            *
 * Purpose: request action value by macro.                                    *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	expr_db_get_action_value(const char *macro, zbx_uint64_t actionid, char **replace_to)
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

		result = zbx_db_select("select name from actions where actionid=" ZBX_FS_UI64, actionid);

		if (NULL != (row = zbx_db_fetch(result)))
			*replace_to = zbx_strdup(*replace_to, row[0]);
		else
			ret = FAIL;

		zbx_db_free_result(result);
	}

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

/******************************************************************************
 *                                                                            *
 * Purpose: get root cause of service being in problem state.                 *
 *                                                                            *
 ******************************************************************************/
void	expr_db_get_rootcause(const zbx_db_service *service, char **replace_to)
{
	int			i;
	zbx_vector_eventdata_t	rootcauses;

	zbx_vector_eventdata_create(&rootcauses);

	eventdata_compose(&service->events, &rootcauses);
	zbx_vector_eventdata_sort(&rootcauses, (zbx_compare_func_t)zbx_eventdata_compare);
	zbx_eventdata_to_str(&rootcauses, replace_to);

	for (i = 0; i < rootcauses.values_num; i++)
		zbx_eventdata_free(&rootcauses.values[i]);

	zbx_vector_eventdata_destroy(&rootcauses);
}

int	expr_db_get_event_symptoms(const zbx_db_event *event, char **replace_to)
{
	int			i, ret = FAIL;
	zbx_db_row_t		row;
	zbx_db_result_t		result;
	zbx_vector_uint64_t	symptom_eventids;

	zbx_vector_uint64_create(&symptom_eventids);

	result = zbx_db_select("select eventid from event_symptom where cause_eventid=" ZBX_FS_UI64, event->eventid);

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

		for (i = 0; i < symptoms.values_num; i++)
			zbx_eventdata_free(&symptoms.values[i]);

		zbx_vector_eventdata_destroy(&symptoms);

		zbx_vector_db_event_clear_ext(&symptom_events, zbx_db_free_event);
		zbx_vector_db_event_destroy(&symptom_events);
	}

	zbx_vector_uint64_destroy(&symptom_eventids);

	return ret;
}
