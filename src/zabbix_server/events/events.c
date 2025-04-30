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

#include "events.h"

#include "../db_lengths_constants.h"
#include "../actions/actions.h"

#include "zbxexpression.h"
#include "zbxexport.h"
#include "zbxservice.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxexpr.h"
#include "zbxdbwrap.h"
#include "zbx_trigger_constants.h"
#include "zbx_item_constants.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxjson.h"
#include "zbxvariant.h"
#include "zbxconnector.h"
#include "zbxtagfilter.h"
#include "zbxescalations.h"
#include "zbx_expression_constants.h"

/* event recovery data */
typedef struct
{
	zbx_uint64_t	eventid;
	zbx_uint64_t	objectid;
	zbx_db_event	*r_event;
	zbx_uint64_t	correlationid;
	zbx_uint64_t	c_eventid;
	zbx_uint64_t	userid;
	zbx_timespec_t	ts;
}
zbx_event_recovery_t;

/* problem event, used to cache open problems for recovery attempts */
typedef struct
{
	zbx_uint64_t		eventid;
	zbx_uint64_t		triggerid;

	zbx_vector_tags_ptr_t	tags;
}
zbx_event_problem_t;

typedef enum
{
	CORRELATION_MATCH = 0,
	CORRELATION_NO_MATCH,
	CORRELATION_MAY_MATCH
}
zbx_correlation_match_result_t;

static zbx_vector_db_event_t	events;
static zbx_hashset_t		event_recovery;
static zbx_hashset_t		correlation_cache;
static zbx_correlation_rules_t	correlation_rules;

/******************************************************************************
 *                                                                            *
 * Purpose: Check that tag name is not empty and that tag is not duplicate.   *
 *                                                                            *
 ******************************************************************************/
static int	validate_event_tag(const zbx_db_event* event, const zbx_tag_t *tag)
{
	int	i;

	if ('\0' == *tag->tag)
		return FAIL;

	/* check for duplicated tags */
	for (i = 0; i < event->tags.values_num; i++)
	{
		zbx_tag_t	*event_tag = event->tags.values[i];

		if (0 == strcmp(event_tag->tag, tag->tag) && 0 == strcmp(event_tag->value, tag->value))
			return FAIL;
	}

	return SUCCEED;
}

static zbx_tag_t	*duplicate_tag(const zbx_tag_t *tag)
{
	zbx_tag_t	*t;

	t = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
	t->tag = zbx_strdup(NULL, tag->tag);
	t->value = zbx_strdup(NULL, tag->value);

	return t;
}

static void	validate_and_add_tag(zbx_db_event* event, zbx_tag_t *tag)
{
	zbx_ltrim(tag->tag, ZBX_WHITESPACE);
	zbx_ltrim(tag->value, ZBX_WHITESPACE);

	if (ZBX_DB_TAG_NAME_LEN < zbx_strlen_utf8(tag->tag))
		tag->tag[zbx_strlen_utf8_nchars(tag->tag, ZBX_DB_TAG_NAME_LEN)] = '\0';
	if (ZBX_DB_TAG_VALUE_LEN < zbx_strlen_utf8(tag->value))
		tag->value[zbx_strlen_utf8_nchars(tag->value, ZBX_DB_TAG_VALUE_LEN)] = '\0';

	zbx_rtrim(tag->tag, ZBX_WHITESPACE);
	zbx_rtrim(tag->value, ZBX_WHITESPACE);

	if (SUCCEED == validate_event_tag(event, tag))
		zbx_vector_tags_ptr_append(&event->tags, tag);
	else
		zbx_free_tag(tag);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolves macros in trigger tags                                   *
 *                                                                            *
 * Parameters: p            - [IN] macro resolver data structure              *
 *             args         - [IN] list of variadic parameters                *
 *                                 Expected content:                          *
 *                                  - zbx_dc_um_handle_t *um_handle: user     *
 *                                      macro cache handle                    *
 *                                  - const zbx_db_event *event: event        *
 *                                  - const char *tz: name of timezone        *
 *                                      (can be NULL)                         *
 *             replace_with - [OUT] pointer to value to replace macro with    *
 *             data         - [IN/OUT] pointer to original input raw string   *
 *                                  (for macro in macro resolving)            *
 *             error        - [OUT] pointer to pre-allocated error message    *
 *                                  buffer (can be NULL)                      *
 *             maxerrlen    - [IN] size of error message buffer (can be 0 if  *
 *                                 'error' is NULL)                           *
 *                                                                            *
 ******************************************************************************/
static int	macro_trigger_tag_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_with, char **data,
		char *error, size_t maxerrlen)
{
	int				ret = SUCCEED;
	const zbx_vector_uint64_t	*phostids;

	/* Passed arguments */
	zbx_dc_um_handle_t	*um_handle = va_arg(args, zbx_dc_um_handle_t *);
	const zbx_db_event	*event = va_arg(args, const zbx_db_event *);
	const char		*tz = va_arg(args, const char *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
	{
		if (ZBX_TOKEN_USER_MACRO == p->token.type || (ZBX_TOKEN_USER_FUNC_MACRO == p->token.type &&
				0 == strncmp(p->macro, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
		{
			if (SUCCEED == zbx_db_trigger_get_all_hostids(&event->trigger, &phostids))
			{
				zbx_dc_get_user_macro(um_handle, p->macro, phostids->values, phostids->values_num,
						replace_with);
			}
			p->pos = p->token.loc.r;
		}
		else if (0 == strncmp(p->macro, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
		{
			ret = zbx_dc_get_host_inventory(p->macro, &event->trigger, replace_with, p->index);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_ID))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_with, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_ID);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_with, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_HOST);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_with, p->index,
					&zbx_dc_get_host_value, ZBX_DC_REQUEST_HOST_NAME);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_IP))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_with, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_IP);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_DNS))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_with, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_DNS);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_with, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_CONN);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			ret = zbx_db_with_trigger_itemid(&event->trigger, replace_with, p->index,
					&zbx_dc_get_interface_value_itemid, ZBX_DC_REQUEST_HOST_PORT);
		}

		if (EVENT_SOURCE_TRIGGERS == event->source)
		{
			if (0 == strcmp(p->macro, MVAR_ITEM_LASTVALUE))
			{
				ret = zbx_db_item_lastvalue(&event->trigger, replace_with, p->index, p->raw_value, tz,
						ZBX_VALUE_PROPERTY_VALUE);
			}
			else if (0 == strcmp(p->macro, MVAR_ITEM_VALUE))
			{
				ret = zbx_db_item_value(&event->trigger, replace_with, p->index, event->clock,
						event->ns, p->raw_value, tz, ZBX_VALUE_PROPERTY_VALUE);
			}
			else if (0 == strncmp(p->macro, MVAR_ITEM_LOG, ZBX_CONST_STRLEN(MVAR_ITEM_LOG)))
			{
				ret = zbx_get_history_log_value(p->macro, &event->trigger, replace_with, p->index,
						event->clock, event->ns, tz);
			}
			else if (0 == strcmp(p->macro, MVAR_TRIGGER_ID))
			{
				*replace_with = zbx_dsprintf(*replace_with, ZBX_FS_UI64, event->objectid);
			}
		}
	}

	return ret;
}

static void	process_trigger_tag(zbx_dc_um_handle_t	*um_handle, zbx_db_event* event, const zbx_tag_t *tag)
{
	zbx_tag_t	*t;

	t = duplicate_tag(tag);

	zbx_substitute_macros(&t->tag, NULL, 0, &macro_trigger_tag_resolv, um_handle, event, NULL);
	zbx_substitute_macros(&t->value, NULL, 0, &macro_trigger_tag_resolv, um_handle, event, NULL);

	validate_and_add_tag(event, t);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolves macros in item tags                                      *
 *                                                                            *
 * Parameters: p            - [IN] macro resolver data structure              *
 *             args         - [IN] list of variadic parameters                *
 *                                 Expected content:                          *
 *                                  - const char *tz: name of timezone        *
 *                                      (can be NULL)                         *
 *             replace_with - [OUT] pointer to value to replace macro with    *
 *             data         - [IN/OUT] pointer to original input raw string   *
 *                                  (for macro in macro resolving)            *
 *             error        - [OUT] pointer to pre-allocated error message    *
 *                                  buffer (can be NULL)                      *
 *             maxerrlen    - [IN] size of error message buffer (can be 0 if  *
 *                                 'error' is NULL)                           *
 *                                                                            *
 ******************************************************************************/
static int	macro_item_tag_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_with, char **data,
		char *error, size_t maxerrlen)
{
	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, zbx_dc_um_handle_t *);
	const zbx_db_event		*event = va_arg(args, const zbx_db_event *);
	const zbx_uint64_t		hostid = va_arg(args, zbx_uint64_t);
	const zbx_uint64_t		itemid = va_arg(args, zbx_uint64_t);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (0 == p->indexed)
	{
		if (EVENT_SOURCE_TRIGGERS == event->source && 0 == strcmp(p->macro, MVAR_TRIGGER_ID))
		{
			*replace_with = zbx_dsprintf(*replace_with, ZBX_FS_UI64, event->objectid);
		}
		else if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
		{
			if (ZBX_TOKEN_USER_MACRO == p->token.type || (ZBX_TOKEN_USER_FUNC_MACRO == p->token.type &&
					0 == strncmp(p->macro, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
			{
				zbx_dc_get_user_macro(um_handle, p->macro, &hostid, 1, replace_with);
			}
			else if (0 == strncmp(p->macro, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
			{
				zbx_dc_get_host_inventory_by_hostid(p->macro, hostid, replace_with);
			}
			else if (0 == strcmp(p->macro, MVAR_HOST_ID))
			{
				zbx_dc_get_host_value(itemid, replace_with, ZBX_DC_REQUEST_HOST_ID);
			}
			else if (0 == strcmp(p->macro, MVAR_HOST_HOST))
			{
				zbx_dc_get_host_value(itemid, replace_with, ZBX_DC_REQUEST_HOST_HOST);
			}
			else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
			{
				zbx_dc_get_host_value(itemid, replace_with, ZBX_DC_REQUEST_HOST_NAME);
			}
			else if (0 == strcmp(p->macro, MVAR_HOST_IP))
			{
				zbx_dc_get_interface_value(hostid, itemid, replace_with, ZBX_DC_REQUEST_HOST_IP);
			}
			else if (0 == strcmp(p->macro, MVAR_HOST_DNS))
			{
				zbx_dc_get_interface_value(hostid, itemid, replace_with, ZBX_DC_REQUEST_HOST_DNS);
			}
			else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
			{
				zbx_dc_get_interface_value(hostid, itemid, replace_with, ZBX_DC_REQUEST_HOST_CONN);
			}
			else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
			{
				zbx_dc_get_interface_value(hostid, itemid, replace_with, ZBX_DC_REQUEST_HOST_PORT);
			}
		}
	}

	return SUCCEED;
}

static void	process_item_tag(zbx_db_event* event, const zbx_item_tag_t *item_tag, zbx_dc_um_handle_t *um_handle)
{
	zbx_tag_t	*t = duplicate_tag(&item_tag->tag);

	zbx_substitute_macros(&t->tag, NULL, 0, &macro_item_tag_resolv, um_handle, event, item_tag->hostid,
			item_tag->itemid);
	zbx_substitute_macros(&t->value, NULL, 0, &macro_item_tag_resolv, um_handle, event, item_tag->hostid,
			item_tag->itemid);

	validate_and_add_tag(event, t);
}

static void	get_item_tags_by_expression(const zbx_db_trigger *trigger, zbx_vector_item_tag_t *item_tags)
{
	zbx_vector_uint64_t	functionids;

	zbx_vector_uint64_create(&functionids);
	zbx_db_trigger_get_functionids(trigger, &functionids);
	zbx_dc_config_history_sync_get_item_tags_by_functionids(functionids.values, functionids.values_num, item_tags);
	zbx_vector_uint64_destroy(&functionids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add event to an array                                             *
 *                                                                            *
 * Parameters: source   - [IN] event source (EVENT_SOURCE_*)                  *
 *             object   - [IN] event object (EVENT_OBJECT_*)                  *
 *             objectid - [IN] trigger, item ... identifier from database,    *
 *                             depends on source and object                   *
 *             timespec - [IN] event time                                     *
 *             value    - [IN] event value (TRIGGER_VALUE_*,                  *
 *                             TRIGGER_STATE_*, ITEM_STATE_* ... depends on   *
 *                             source and object)                             *
 *             trigger_description         - [IN]                             *
 *             trigger_expression          - [IN] trigger short expression    *
 *             trigger_recovery_expression - [IN]                             *
 *             trigger_priority            - [IN]                             *
 *             trigger_type                - [IN] TRIGGER_TYPE_* defines      *
 *             trigger_tags                - [IN]                             *
 *             trigger_correlation_mode    - [IN]                             *
 *             trigger_correlation_tag     - [IN]                             *
 *             trigger_value               - [IN]                             *
 *             trigger_opdata              - [IN]                             *
 *             event_name                  - [IN] event name, can be NULL     *
 *             error                       - [IN] error for internal events   *
 *                                                                            *
 * Return value: The added event.                                             *
 *                                                                            *
 ******************************************************************************/
zbx_db_event	*zbx_add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_tags_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag,
		unsigned char trigger_value, const char *trigger_opdata, const char *event_name, const char *error)
{
	zbx_vector_item_tag_t	item_tags;
	zbx_db_event		*event;
	zbx_dc_um_handle_t	*um_handle;

	event = zbx_malloc(NULL, sizeof(zbx_db_event));
	um_handle = zbx_dc_open_user_macros();

	event->eventid = 0;
	event->source = source;
	event->object = object;
	event->objectid = objectid;
	event->name = NULL;
	event->clock = timespec->sec;
	event->ns = timespec->ns;
	event->value = value;
	event->acknowledged = EVENT_NOT_ACKNOWLEDGED;
	event->flags = ZBX_FLAGS_DB_EVENT_CREATE;
	event->severity = TRIGGER_SEVERITY_NOT_CLASSIFIED;
	event->suppressed = ZBX_PROBLEM_SUPPRESSED_FALSE;
	event->maintenanceids = NULL;

	if (EVENT_SOURCE_TRIGGERS == source)
	{
		char			err[256];

		if (TRIGGER_VALUE_PROBLEM == value)
			event->severity = trigger_priority;

		event->trigger.triggerid = objectid;
		event->trigger.description = zbx_strdup(NULL, trigger_description);
		event->trigger.expression = zbx_strdup(NULL, trigger_expression);
		event->trigger.recovery_expression = zbx_strdup(NULL, trigger_recovery_expression);
		event->trigger.priority = trigger_priority;
		event->trigger.type = trigger_type;
		event->trigger.correlation_mode = trigger_correlation_mode;
		event->trigger.correlation_tag = zbx_strdup(NULL, trigger_correlation_tag);
		event->trigger.value = trigger_value;
		event->trigger.opdata = zbx_strdup(NULL, trigger_opdata);
		event->trigger.event_name = (NULL != event_name ? zbx_strdup(NULL, event_name) : NULL);
		event->name = zbx_strdup(NULL, (NULL != event_name ? event_name : trigger_description));
		event->trigger.cache = NULL;
		event->trigger.url = NULL;
		event->trigger.url_name = NULL;
		event->trigger.comments = NULL;

		zbx_substitute_macros(&event->trigger.correlation_tag, err, sizeof(err), &macro_trigger_tag_resolv,
				um_handle, event, NULL);

		zbx_substitute_simple_macros(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&event->name, ZBX_MACRO_TYPE_EVENT_NAME, err, sizeof(err));

		zbx_vector_tags_ptr_create(&event->tags);

		if (NULL != trigger_tags)
		{
			for (int i = 0; i < trigger_tags->values_num; i++)
				process_trigger_tag(um_handle, event, trigger_tags->values[i]);
		}

		zbx_vector_item_tag_create(&item_tags);
		get_item_tags_by_expression(&event->trigger, &item_tags);

		for (int i = 0; i < item_tags.values_num; i++)
		{
			process_item_tag(event, item_tags.values[i], um_handle);
			zbx_free_item_tag(item_tags.values[i]);
		}

		zbx_vector_item_tag_destroy(&item_tags);
	}
	else if (EVENT_SOURCE_INTERNAL == source)
	{
		if (NULL != error)
			event->name = zbx_strdup(NULL, error);

		zbx_vector_tags_ptr_create(&event->tags);
		zbx_vector_item_tag_create(&item_tags);

		switch (object)
		{
			case EVENT_OBJECT_TRIGGER:
				memset(&event->trigger, 0, sizeof(zbx_db_trigger));

				event->trigger.expression = zbx_strdup(NULL, trigger_expression);
				event->trigger.recovery_expression = zbx_strdup(NULL, trigger_recovery_expression);

				for (int i = 0; i < trigger_tags->values_num; i++)
					process_trigger_tag(um_handle, event, trigger_tags->values[i]);

				get_item_tags_by_expression(&event->trigger, &item_tags);
				break;
			case EVENT_OBJECT_ITEM:
				zbx_dc_get_item_tags(objectid, &item_tags);
		}

		for (int i = 0; i < item_tags.values_num; i++)
		{
			process_item_tag(event, item_tags.values[i], um_handle);
			zbx_free_item_tag(item_tags.values[i]);
		}

		zbx_vector_item_tag_destroy(&item_tags);
	}

	zbx_vector_db_event_append(&events, event);
	zbx_dc_close_user_macros(um_handle);

	return event;
}

/**********************************************************************************************
 *                                                                                            *
 * Purpose: add closing OK event for the specified problem event to an array                  *
 *                                                                                            *
 * Parameters: eventid                     - [IN] problem eventid                             *
 *             objectid                    - [IN] trigger, item ... identifier from database, *
 *                                                depends on source and object                *
 *             ts                          - [IN] event time                                  *
 *             userid                      - [IN] user closing the problem                    *
 *             correlationid               - [IN] correlation rule                            *
 *             c_eventid                   - [IN] correlation event                           *
 *             trigger_description         - [IN]                                             *
 *             trigger_expression          - [IN] trigger short expression                    *
 *             trigger_recovery_expression - [IN]                                             *
 *             trigger_priority            - [IN]                                             *
 *             trigger_type                - [IN] TRIGGER_TYPE_* defines                      *
 *             trigger_opdata              - [IN] trigger operational data                    *
 *             event_name                  - [IN]                                             *
 *                                                                                            *
 * Return value: Recovery event, created to close the specified event.                        *
 *                                                                                            *
 *********************************************************************************************/
static zbx_db_event	*close_trigger_event(zbx_uint64_t eventid, zbx_uint64_t objectid, const zbx_timespec_t *ts,
		zbx_uint64_t userid, zbx_uint64_t correlationid, zbx_uint64_t c_eventid,
		const char *trigger_description, const char *trigger_expression,
		const char *trigger_recovery_expression, unsigned char trigger_priority, unsigned char trigger_type,
		const char *trigger_opdata, const char *event_name)
{
	zbx_event_recovery_t	recovery_local;
	zbx_db_event		*r_event;

	r_event = zbx_add_event(EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, objectid, ts, TRIGGER_VALUE_OK,
			trigger_description, trigger_expression, trigger_recovery_expression, trigger_priority,
			trigger_type, NULL, ZBX_TRIGGER_CORRELATION_NONE, "", TRIGGER_VALUE_PROBLEM, trigger_opdata,
			event_name, NULL);

	recovery_local.eventid = eventid;
	recovery_local.objectid = objectid;
	recovery_local.correlationid = correlationid;
	recovery_local.c_eventid = c_eventid;
	recovery_local.r_event = r_event;
	recovery_local.userid = userid;

	zbx_hashset_insert(&event_recovery, &recovery_local, sizeof(recovery_local));

	return r_event;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flushes the events into a database                                *
 *                                                                            *
 ******************************************************************************/
static int	save_events(void)
{
	int			i;
	zbx_db_insert_t		db_insert, db_insert_tags;
	int			j, num = 0, insert_tags = 0;
	zbx_uint64_t		eventid;
	zbx_db_event		*event;

	/* new events without ids could be created during correlation - reserve ids for them */

	for (i = 0; i < events.values_num; i++)
	{
		event = events.values[i];

		if (0 != (event->flags & ZBX_FLAGS_DB_EVENT_CREATE) && 0 == event->eventid)
			num++;
	}

	if (0 != num)
		eventid = zbx_db_get_maxid_num("events", num);

	zbx_db_insert_prepare(&db_insert, "events", "eventid", "source", "object", "objectid", "clock", "ns", "value",
			"name", "severity", (char *)NULL);

	num = 0;

	for (i = 0; i < events.values_num; i++)
	{
		event = events.values[i];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (0 == event->eventid)
			event->eventid = eventid++;

		zbx_db_insert_add_values(&db_insert, event->eventid, event->source, event->object,
				event->objectid, event->clock, event->ns, event->value,
				ZBX_NULL2EMPTY_STR(event->name), event->severity);

		num++;

		if (EVENT_SOURCE_TRIGGERS != event->source && EVENT_SOURCE_INTERNAL != event->source)
			continue;

		if (0 == event->tags.values_num)
			continue;

		if (0 == insert_tags)
		{
			zbx_db_insert_prepare(&db_insert_tags, "event_tag", "eventtagid", "eventid", "tag", "value",
					(char *)NULL);
			insert_tags = 1;
		}

		for (j = 0; j < event->tags.values_num; j++)
		{
			zbx_tag_t	*tag = event->tags.values[j];

			zbx_db_insert_add_values(&db_insert_tags, __UINT64_C(0), event->eventid, tag->tag, tag->value);
		}
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (0 != insert_tags)
	{
		zbx_db_insert_autoincrement(&db_insert_tags, "eventtagid");
		zbx_db_insert_execute(&db_insert_tags);
		zbx_db_insert_clean(&db_insert_tags);
	}

	return num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: generates problems from problem events (trigger and internal      *
 *          event sources)                                                    *
 *                                                                            *
 ******************************************************************************/
static void	save_problems(void)
{
	int			i;
	zbx_vector_ptr_t	problems;
	int			j, tags_num = 0;

	zbx_vector_ptr_create(&problems);

	for (i = 0; i < events.values_num; i++)
	{
		zbx_db_event	*event = events.values[i];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (EVENT_SOURCE_TRIGGERS == event->source)
		{
			if (EVENT_OBJECT_TRIGGER != event->object || TRIGGER_VALUE_PROBLEM != event->value)
				continue;

			tags_num += event->tags.values_num;
		}
		else if (EVENT_SOURCE_INTERNAL == event->source)
		{
			switch (event->object)
			{
				case EVENT_OBJECT_TRIGGER:
					if (TRIGGER_STATE_UNKNOWN != event->value)
						continue;

					tags_num += event->tags.values_num;
					break;
				case EVENT_OBJECT_ITEM:
					if (ITEM_STATE_NOTSUPPORTED != event->value)
						continue;

					tags_num += event->tags.values_num;
					break;
				case EVENT_OBJECT_LLDRULE:
					if (ITEM_STATE_NOTSUPPORTED != event->value)
						continue;
					break;
				default:
					continue;
			}
		}
		else
			continue;

		zbx_vector_ptr_append(&problems, event);
	}

	if (0 != problems.values_num)
	{
		zbx_db_insert_t	db_insert;

		zbx_db_insert_prepare(&db_insert, "problem", "eventid", "source", "object", "objectid", "clock", "ns",
				"name", "severity", (char *)NULL);

		for (j = 0; j < problems.values_num; j++)
		{
			const zbx_db_event	*event = (const zbx_db_event *)problems.values[j];

			zbx_db_insert_add_values(&db_insert, event->eventid, event->source, event->object,
					event->objectid, event->clock, event->ns, ZBX_NULL2EMPTY_STR(event->name),
					event->severity);
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		if (0 != tags_num)
		{
			int	k;

			zbx_db_insert_prepare(&db_insert, "problem_tag", "problemtagid", "eventid", "tag", "value",
					(char *)NULL);

			for (j = 0; j < problems.values_num; j++)
			{
				const zbx_db_event	*event = (const zbx_db_event *)problems.values[j];

				if (EVENT_SOURCE_TRIGGERS != event->source && EVENT_SOURCE_INTERNAL != event->source)
					continue;

				for (k = 0; k < event->tags.values_num; k++)
				{
					zbx_tag_t	*tag = event->tags.values[k];

					zbx_db_insert_add_values(&db_insert, __UINT64_C(0), event->eventid, tag->tag,
							tag->value);
				}
			}

			zbx_db_insert_autoincrement(&db_insert, "problemtagid");
			zbx_db_insert_execute(&db_insert);
			zbx_db_insert_clean(&db_insert);
		}
	}

	zbx_vector_ptr_destroy(&problems);
}

/******************************************************************************
 *                                                                            *
 * Purpose: saves event recovery data and removes recovered events from       *
 *          problem table                                                     *
 *                                                                            *
 ******************************************************************************/
static void	save_event_recovery(void)
{
	zbx_db_insert_t		db_insert;
	zbx_event_recovery_t	*recovery;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_hashset_iter_t	iter;

	if (0 == event_recovery.num_data)
		return;

	zbx_db_insert_prepare(&db_insert, "event_recovery", "eventid", "r_eventid", "correlationid", "c_eventid",
			"userid", (char *)NULL);

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_db_insert_add_values(&db_insert, recovery->eventid, recovery->r_event->eventid,
				recovery->correlationid, recovery->c_eventid, recovery->userid);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update problem set"
			" r_eventid=" ZBX_FS_UI64
			",r_clock=%d"
			",r_ns=%d"
			",userid=" ZBX_FS_UI64,
			recovery->r_event->eventid,
			recovery->r_event->clock,
			recovery->r_event->ns,
			recovery->userid);

		if (0 != recovery->correlationid)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",correlationid=" ZBX_FS_UI64,
					recovery->correlationid);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where eventid=" ZBX_FS_UI64 ";\n",
				recovery->eventid);

		zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: find event index by its source object                             *
 *                                                                            *
 * Parameters: source   - [IN] event source                                   *
 *             object   - [IN] object type                                    *
 *             objectid - [IN]                                                *
 *                                                                            *
 * Return value: the event or NULL                                            *
 *                                                                            *
 ******************************************************************************/
static zbx_db_event	*get_event_by_source_object_id(int source, int object, zbx_uint64_t objectid)
{
	int		i;
	zbx_db_event	*event;

	for (i = 0; i < events.values_num; i++)
	{
		event = events.values[i];

		if (event->source == source && event->object == object && event->objectid == objectid)
			return event;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the event matches the specified host group              *
 *          (including nested groups)                                         *
 *                                                                            *
 * Parameters: event   - [IN] new event to check                              *
 *             groupid - [IN] group id to match                               *
 *                                                                            *
 * Return value: SUCCEED - the group matches                                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_match_event_hostgroup(const zbx_db_event *event, zbx_uint64_t groupid)
{
	zbx_db_result_t		result;
	int			ret = FAIL;
	zbx_vector_uint64_t	groupids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zbx_vector_uint64_create(&groupids);
	zbx_dc_get_nested_hostgroupids(&groupid, 1, &groupids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hg.groupid"
				" from hstgrp g,hosts_groups hg,items i,functions f"
				" where f.triggerid=" ZBX_FS_UI64
				" and i.itemid=f.itemid"
				" and hg.hostid=i.hostid"
				" and",
				event->objectid);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids.values,
			groupids.values_num);

	result = zbx_db_select("%s", sql);

	if (NULL != zbx_db_fetch(result))
		ret = SUCCEED;

	zbx_db_free_result(result);
	zbx_free(sql);
	zbx_vector_uint64_destroy(&groupids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the correlation condition matches the new event         *
 *                                                                            *
 * Parameters: condition - [IN] correlation condition to check                *
 *             event     - [IN] new event to match                            *
 *             old_value - [IN] SUCCEED - old event conditions may            *
 *                                        match event                         *
 *                              FAIL    - old event conditions never          *
 *                                        match event                         *
 *                                                                            *
 * Return value: "1"            - correlation rule match event                *
 *               "0"            - correlation rule doesn't match event        *
 *               "ZBX_UNKNOWN " - correlation rule might match                *
 *                                depending on old events                     *
 *                                                                            *
 ******************************************************************************/
static const char	*correlation_condition_match_new_event(const zbx_corr_condition_t *condition,
		const zbx_db_event *event, int old_value)
{
	/* return SUCCEED for conditions using old events */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			return (SUCCEED == old_value) ? ZBX_UNKNOWN_STR "0" : "0";
	}

	int	ret;

	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			for (int i = 0; i < event->tags.values_num; i++)
			{
				const zbx_tag_t	*tag = event->tags.values[i];

				if (0 == strcmp(tag->tag, condition->data.tag.tag))
					return "1";
			}
			break;

		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			for (int i = 0; i < event->tags.values_num; i++)
			{
				const zbx_corr_condition_tag_value_t	*cond = &condition->data.tag_value;
				const zbx_tag_t				*tag = event->tags.values[i];

				if (0 == strcmp(tag->tag, cond->tag) &&
					SUCCEED == zbx_strmatch_condition(tag->value, cond->value, cond->op))
				{
					return "1";
				}
			}
			break;

		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			ret =  correlation_match_event_hostgroup(event, condition->data.group.groupid);

			if (ZBX_CONDITION_OPERATOR_NOT_EQUAL == condition->data.group.op)
				return (SUCCEED == ret ? "0" : "1");

			return (SUCCEED == ret ? "1" : "0");

		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			for (int i = 0; i < event->tags.values_num; i++)
			{
				const zbx_tag_t	*tag = event->tags.values[i];

				if (0 == strcmp(tag->tag, condition->data.tag_pair.newtag))
					return (SUCCEED == old_value) ? ZBX_UNKNOWN_STR "0" : "0";
			}
			break;
	}

	return "0";
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the correlation rule might match the new event          *
 *                                                                            *
 * Parameters: correlation - [IN] correlation rule to check                   *
 *             event       - [IN] new event to match                          *
 *             old_value   - [IN] SUCCEED - old event conditions may          *
 *                                          match event                       *
 *                                FAIL    - old event conditions never        *
 *                                          match event                       *
 *                                                                            *
 * Return value: CORRELATION_MATCH     - correlation rule match               *
 *               CORRELATION_MAY_MATCH - correlation rule might match         *
 *                                       depending on old events              *
 *               CORRELATION_NO_MATCH  - correlation rule doesn't match       *
 *                                                                            *
 ******************************************************************************/
static zbx_correlation_match_result_t	correlation_match_new_event(const zbx_correlation_t *correlation,
		const zbx_db_event *event, int old_value)
{
	char				*expression, error[256];
	const char			*value;
	zbx_token_t			token;
	int				pos = 0;
	zbx_uint64_t			conditionid;
	zbx_strloc_t			*loc;
	double				result;
	zbx_correlation_match_result_t	ret = CORRELATION_NO_MATCH;

	if ('\0' == *correlation->formula)
		return CORRELATION_MAY_MATCH;

	expression = zbx_strdup(NULL, correlation->formula);

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		const zbx_corr_condition_t	*condition;

		if (ZBX_TOKEN_OBJECTID != token.type)
			continue;

		loc = &token.data.objectid.name;

		if (SUCCEED != zbx_is_uint64_n(expression + loc->l, loc->r - loc->l + 1, &conditionid))
			continue;

		if (NULL == (condition = (zbx_corr_condition_t *)zbx_hashset_search(&correlation_rules.conditions,
				&conditionid)))
			goto out;

		value = correlation_condition_match_new_event(condition, event, old_value);

		zbx_replace_string(&expression, token.loc.l, &token.loc.r, value);
		pos = token.loc.r;
	}

	if (SUCCEED == zbx_evaluate_unknown(expression, &result, error, sizeof(error)))
	{
		if (result == ZBX_UNKNOWN)
			ret = CORRELATION_MAY_MATCH;
		else if (SUCCEED == zbx_double_compare(result, 1))
			ret = CORRELATION_MATCH;
	}

out:
	zbx_free(expression);

	return ret;
}

#define ZBX_CORR_OPERATION_CLOSE_OLD	0
#define ZBX_CORR_OPERATION_CLOSE_NEW	1
/******************************************************************************
 *                                                                            *
 * Purpose: checks if correlation has operations to change old events         *
 *                                                                            *
 * Parameters: correlation - [IN] correlation to check                        *
 *                                                                            *
 * Return value: SUCCEED - correlation has operations to change old events    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_has_old_event_operation(const zbx_correlation_t *correlation)
{
	int				i;
	const zbx_corr_operation_t	*operation;

	for (i = 0; i < correlation->operations.values_num; i++)
	{
		operation = correlation->operations.values[i];

		switch (operation->type)
		{
			case ZBX_CORR_OPERATION_CLOSE_OLD:
				return SUCCEED;
		}
	}

	return FAIL;
}

/***********************************************************************************
 *                                                                                 *
 * Purpose: adds sql statement to match tag according to the defined               *
 *          matching operation                                                     *
 *                                                                                 *
 * Parameters: sql         - [IN/OUT]                                              *
 *             sql_alloc   - [IN/OUT]                                              *
 *             sql_offset  - [IN/OUT]                                              *
 *             tag         - [IN] tag to match                                     *
 *             value       - [IN] tag value to match                               *
 *             op          - [IN] matching operation (ZBX_CONDITION_OPERATOR_)     *
 *                                                                                 *
 ***********************************************************************************/
static void	correlation_condition_add_tag_match(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *tag,
		const char *value, unsigned char op)
{
	char	*tag_esc, *value_esc;

	tag_esc = zbx_db_dyn_escape_string(tag);
	value_esc = zbx_db_dyn_escape_string(value);

	switch (op)
	{
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "not ");
			break;
	}

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset,
			"exists (select null from problem_tag pt where p.eventid=pt.eventid and ");

	switch (op)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "pt.tag='%s' and pt.value" ZBX_SQL_STRCMP,
					tag_esc, ZBX_SQL_STRVAL_EQ(value_esc));
			break;
		case ZBX_CONDITION_OPERATOR_LIKE:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "pt.tag='%s' and pt.value like '%%%s%%'",
					tag_esc, value_esc);
			break;
	}

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

	zbx_free(value_esc);
	zbx_free(tag_esc);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates sql filter to find events matching a correlation          *
 *          condition                                                         *
 *                                                                            *
 * Parameters: condition - [IN] correlation condition to match                *
 *             event     - [IN] new event to match                            *
 *                                                                            *
 * Return value: the created filter or NULL                                   *
 *                                                                            *
 ******************************************************************************/
static char	*correlation_condition_get_event_filter(const zbx_corr_condition_t *condition,
		const zbx_db_event *event)
{
	int			i;
	zbx_tag_t		*tag;
	char			*tag_esc, *filter = NULL;
	size_t			filter_alloc = 0, filter_offset = 0;
	zbx_vector_str_t	values;

	/* replace new event dependent condition with precalculated value */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			return zbx_dsprintf(NULL, "%s=1",
					correlation_condition_match_new_event(condition, event, SUCCEED));
	}

	/* replace old event dependent condition with sql filter on problem_tag pt table */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			tag_esc = zbx_db_dyn_escape_string(condition->data.tag.tag);
			zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
					"exists (select null from problem_tag pt"
						" where p.eventid=pt.eventid"
							" and pt.tag='%s')",
					tag_esc);
			zbx_free(tag_esc);
			return filter;

		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			zbx_vector_str_create(&values);

			for (i = 0; i < event->tags.values_num; i++)
			{
				tag = event->tags.values[i];
				if (0 == strcmp(tag->tag, condition->data.tag_pair.newtag))
					zbx_vector_str_append(&values, zbx_strdup(NULL, tag->value));
			}

			if (0 == values.values_num)
			{
				/* no new tag found, substitute condition with failure expression */
				filter = zbx_strdup(NULL, "1=0");
			}
			else
			{
				tag_esc = zbx_db_dyn_escape_string(condition->data.tag_pair.oldtag);

				zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
						"exists (select null from problem_tag pt"
							" where p.eventid=pt.eventid"
								" and pt.tag='%s'"
								" and",
						tag_esc);

				zbx_db_add_str_condition_alloc(&filter, &filter_alloc, &filter_offset, "pt.value",
						(const char **)values.values, values.values_num);

				zbx_chrcpy_alloc(&filter, &filter_alloc, &filter_offset, ')');

				zbx_free(tag_esc);
				zbx_vector_str_clear_ext(&values, zbx_str_free);
			}

			zbx_vector_str_destroy(&values);
			return filter;

		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			correlation_condition_add_tag_match(&filter, &filter_alloc, &filter_offset,
					condition->data.tag_value.tag, condition->data.tag_value.value,
					condition->data.tag_value.op);
			return filter;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add sql statement to filter out correlation conditions and        *
 *          matching events                                                   *
 *                                                                            *
 * Parameters: sql         - [IN/OUT]                                         *
 *             sql_alloc   - [IN/OUT]                                         *
 *             sql_offset  - [IN/OUT]                                         *
 *             correlation - [IN] correlation rule to match                   *
 *             event       - [IN] new event to match                          *
 *                                                                            *
 * Return value: SUCCEED - filter was added successfully                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_add_event_filter(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const zbx_correlation_t *correlation, const zbx_db_event *event)
{
	char			*expression, *filter;
	zbx_token_t		token;
	int			pos = 0, ret = FAIL;
	zbx_uint64_t		conditionid;
	zbx_strloc_t		*loc;
	zbx_corr_condition_t	*condition;

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "c.correlationid=" ZBX_FS_UI64, correlation->correlationid);

	expression = zbx_strdup(NULL, correlation->formula);

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		if (ZBX_TOKEN_OBJECTID != token.type)
			continue;

		loc = &token.data.objectid.name;

		if (SUCCEED != zbx_is_uint64_n(expression + loc->l, loc->r - loc->l + 1, &conditionid))
			continue;

		if (NULL == (condition = (zbx_corr_condition_t *)zbx_hashset_search(&correlation_rules.conditions, &conditionid)))
			goto out;

		if (NULL == (filter = correlation_condition_get_event_filter(condition, event)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
		}

		zbx_replace_string(&expression, token.loc.l, &token.loc.r, filter);
		pos = token.loc.r;
		zbx_free(filter);
	}

	if ('\0' != *expression)
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " and (%s)", expression);

	ret = SUCCEED;
out:
	zbx_free(expression);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute correlation operations for the new event and matched      *
 *          old eventid                                                       *
 *                                                                            *
 * Parameters: correlation  - [IN] correlation to execute                     *
 *             event        - [IN/OUT] new event                              *
 *             old_eventid  - [IN]                                            *
 *             old_objectid - [IN] old event source objectid (triggerid)      *
 *                                                                            *
 ******************************************************************************/
static void	correlation_execute_operations(const zbx_correlation_t *correlation, zbx_db_event *event,
		zbx_uint64_t old_eventid, zbx_uint64_t old_objectid)
{
	int			i;
	zbx_corr_operation_t	*operation;
	zbx_event_recovery_t	recovery_local;
	zbx_timespec_t		ts;
	zbx_db_event		*r_event;

	for (i = 0; i < correlation->operations.values_num; i++)
	{
		operation = correlation->operations.values[i];

		switch (operation->type)
		{
			case ZBX_CORR_OPERATION_CLOSE_NEW:
				/* generate OK event to close the new event */

				/* check if this event has not been closed by another correlation rule */
				if (NULL != zbx_hashset_search(&event_recovery, &event->eventid))
					break;

				ts.sec = event->clock;
				ts.ns = event->ns;

				r_event = close_trigger_event(event->eventid, event->objectid, &ts, 0,
						correlation->correlationid, event->eventid, event->trigger.description,
						event->trigger.expression, event->trigger.recovery_expression,
						event->trigger.priority, event->trigger.type, event->trigger.opdata,
						event->trigger.event_name);

				event->flags |= ZBX_FLAGS_DB_EVENT_NO_ACTION;
				r_event->flags |= ZBX_FLAGS_DB_EVENT_NO_ACTION;

				break;
			case ZBX_CORR_OPERATION_CLOSE_OLD:
				/* queue closing of old events to lock them by triggerids */
				if (0 != old_eventid)
				{
					recovery_local.eventid = old_eventid;
					recovery_local.objectid = old_objectid;
					recovery_local.r_event = NULL;
					recovery_local.correlationid = correlation->correlationid;
					recovery_local.c_eventid = event->eventid;
					recovery_local.userid = 0;
					recovery_local.ts.sec = event->clock;
					recovery_local.ts.ns = event->ns;

					zbx_hashset_insert(&correlation_cache, &recovery_local, sizeof(recovery_local));
				}
				break;
		}
	}
}
#undef ZBX_CORR_OPERATION_CLOSE_OLD
#undef ZBX_CORR_OPERATION_CLOSE_NEW

/* specifies correlation execution scope */
typedef enum
{
	ZBX_CHECK_NEW_EVENTS,
	ZBX_CHECK_OLD_EVENTS
}
zbx_correlation_scope_t;

/* flag to cache state of problem table during event correlation */
typedef enum
{
	/* unknown state, not initialized */
	ZBX_PROBLEM_STATE_UNKNOWN = -1,
	/* all problems are resolved */
	ZBX_PROBLEM_STATE_RESOLVED,
	/* at least one open problem exists */
	ZBX_PROBLEM_STATE_OPEN
}
zbx_problem_state_t;

/******************************************************************************
 *                                                                            *
 * Purpose: find problem events that must be recovered by global correlation  *
 *          rules and check if the new event must be closed                   *
 *                                                                            *
 * Parameters: event         - [IN] new event                                 *
 *             problem_state - [IN/OUT] problem state cache variable          *
 *                                                                            *
 * Comments: The correlation data (zbx_event_recovery_t) of events that       *
 *           must be closed are added to event_correlation hashset            *
 *                                                                            *
 *           The global event correlation matching is done in two parts:      *
 *             1) exclude correlations that can't possibly match the event    *
 *                based on new event tag/value/group conditions               *
 *             2) assemble sql statement to select problems/correlations      *
 *                based on the rest correlation conditions                    *
 *                                                                            *
 ******************************************************************************/
static void	correlate_event_by_global_rules(zbx_db_event *event, zbx_problem_state_t *problem_state)
{
	int			i;
	zbx_correlation_t	*correlation;
	zbx_vector_ptr_t	corr_old, corr_new;
	char			*sql = NULL;
	const char		*delim = "";
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		eventid, correlationid, objectid;

	zbx_vector_ptr_create(&corr_old);
	zbx_vector_ptr_create(&corr_new);

	for (i = 0; i < correlation_rules.correlations.values_num; i++)
	{
		zbx_correlation_scope_t	scope;

		correlation = correlation_rules.correlations.values[i];

		switch (correlation_match_new_event(correlation, event, SUCCEED))
		{
			case CORRELATION_MATCH:
				if (SUCCEED == correlation_has_old_event_operation(correlation))
					scope = ZBX_CHECK_OLD_EVENTS;
				else
					scope = ZBX_CHECK_NEW_EVENTS;
				break;
			case CORRELATION_NO_MATCH:	/* proceed with next rule */
				continue;
			case CORRELATION_MAY_MATCH:	/* might match depending on old events */
				scope = ZBX_CHECK_OLD_EVENTS;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
		}

		if (ZBX_CHECK_OLD_EVENTS == scope)
		{
			if (ZBX_PROBLEM_STATE_UNKNOWN == *problem_state)
			{
				zbx_db_result_t	result;

				result = zbx_db_select_n("select eventid from problem"
						" where r_eventid is null and source="
						ZBX_STR(EVENT_SOURCE_TRIGGERS), 1);

				if (NULL == zbx_db_fetch(result))
					*problem_state = ZBX_PROBLEM_STATE_RESOLVED;
				else
					*problem_state = ZBX_PROBLEM_STATE_OPEN;
				zbx_db_free_result(result);
			}

			if (ZBX_PROBLEM_STATE_RESOLVED == *problem_state)
			{
				/* with no open problems all conditions involving old events will fail       */
				/* so there is no need to check old events. Instead re-check if correlation  */
				/* still matches the new event and must be processed in new event scope.     */
				if (CORRELATION_MATCH == correlation_match_new_event(correlation, event, FAIL))
					zbx_vector_ptr_append(&corr_new, correlation);
			}
			else
				zbx_vector_ptr_append(&corr_old, correlation);
		}
		else
			zbx_vector_ptr_append(&corr_new, correlation);
	}

	if (0 != corr_new.values_num)
	{
		/* Process correlations that matches new event and does not use or affect old events. */
		/* Those correlations can be executed directly, without checking database.            */
		for (i = 0; i < corr_new.values_num; i++)
			correlation_execute_operations((zbx_correlation_t *)corr_new.values[i], event, 0, 0);
	}

	if (0 != corr_old.values_num)
	{
		zbx_db_result_t	result;
		zbx_db_row_t	row;

		/* Process correlations that matches new event and either uses old events in conditions */
		/* or has operations involving old events.                                              */

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select p.eventid,p.objectid,c.correlationid"
								" from correlation c,problem p"
								" where p.r_eventid is null"
								" and p.source=" ZBX_STR(EVENT_SOURCE_TRIGGERS)
								" and (");

		for (i = 0; i < corr_old.values_num; i++)
		{
			correlation = (zbx_correlation_t *)corr_old.values[i];

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, delim);
			correlation_add_event_filter(&sql, &sql_alloc, &sql_offset, correlation, event);
			delim = " or ";
		}

		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);

			/* check if this event is not already recovered by another correlation rule */
			if (NULL != zbx_hashset_search(&correlation_cache, &eventid))
				continue;

			ZBX_STR2UINT64(correlationid, row[2]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(&corr_old, &correlationid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			ZBX_STR2UINT64(objectid, row[1]);
			correlation_execute_operations((zbx_correlation_t *)corr_old.values[i], event, eventid, objectid);
		}

		zbx_db_free_result(result);
		zbx_free(sql);
	}

	zbx_vector_ptr_destroy(&corr_new);
	zbx_vector_ptr_destroy(&corr_old);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add events to the closing queue according to global correlation   *
 *          rules                                                             *
 *                                                                            *
 ******************************************************************************/
static void	correlate_events_by_global_rules(zbx_vector_ptr_t *trigger_events,
		zbx_vector_trigger_diff_ptr_t *trigger_diff)
{
	int			i, index;
	zbx_trigger_diff_t	*diff;
	zbx_problem_state_t	problem_state = ZBX_PROBLEM_STATE_UNKNOWN;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events:%d", __func__, correlation_cache.num_data);

	zbx_dc_correlation_rules_get(&correlation_rules);

	if (0 == correlation_rules.correlations.values_num)
		goto out;

	/* process global correlation and queue the events that must be closed */
	for (i = 0; i < trigger_events->values_num; i++)
	{
		zbx_db_event	*event = (zbx_db_event *)trigger_events->values[i];

		if (0 == (ZBX_FLAGS_DB_EVENT_CREATE & event->flags))
			continue;

		correlate_event_by_global_rules(event, &problem_state);

		/* force value recalculation based on open problems for triggers with */
		/* events closed by 'close new' correlation operation                */
		if (0 != (event->flags & ZBX_FLAGS_DB_EVENT_NO_ACTION))
		{
			zbx_trigger_diff_t	trigger_diff_cmp = {.triggerid = event->objectid};

			if (FAIL != (index = zbx_vector_trigger_diff_ptr_bsearch(trigger_diff, &trigger_diff_cmp,
					zbx_trigger_diff_compare_func)))
			{
				diff = trigger_diff->values[index];
				diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT;
			}
		}
	}

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: try flushing correlation close events queue, generated by         *
 *          correlation rules                                                 *
 *                                                                            *
 ******************************************************************************/
static void	flush_correlation_queue(zbx_vector_trigger_diff_ptr_t *trigger_diff,
		zbx_vector_uint64_t *triggerids_lock)
{
	zbx_vector_uint64_t	triggerids, lockids, eventids;
	zbx_hashset_iter_t	iter;
	zbx_event_recovery_t	*recovery;
	int			i, closed_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events:%d", __func__, correlation_cache.num_data);

	if (0 == correlation_cache.num_data)
		goto out;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_create(&lockids);
	zbx_vector_uint64_create(&eventids);

	/* lock source triggers of events to be closed by global correlation rules */

	zbx_vector_uint64_sort(triggerids_lock, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* create a list of triggers that must be locked to close correlated events */
	zbx_hashset_iter_reset(&correlation_cache, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		if (FAIL != zbx_vector_uint64_bsearch(triggerids_lock, recovery->objectid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			/* trigger already locked by this process, add to locked triggerids */
			zbx_vector_uint64_append(&triggerids, recovery->objectid);
		}
		else
			zbx_vector_uint64_append(&lockids, recovery->objectid);
	}

	if (0 != lockids.values_num)
	{
		int	num = triggerids_lock->values_num;

		zbx_vector_uint64_sort(&lockids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&lockids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_dc_config_lock_triggers_by_triggerids(&lockids, triggerids_lock);

		/* append the locked trigger ids to already locked trigger ids */
		for (i = num; i < triggerids_lock->values_num; i++)
			zbx_vector_uint64_append(&triggerids, triggerids_lock->values[i]);
	}

	/* process global correlation actions if we have successfully locked trigger(s) */
	if (0 != triggerids.values_num)
	{
		zbx_dc_trigger_t	*triggers, *trigger;
		int			*errcodes, index;
		char			*sql = NULL;
		size_t			sql_alloc = 0, sql_offset = 0;
		zbx_trigger_diff_t	*diff;

		/* get locked trigger data - needed for trigger diff and event generation */

		zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		triggers = (zbx_dc_trigger_t *)zbx_malloc(NULL, sizeof(zbx_dc_trigger_t) * triggerids.values_num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * triggerids.values_num);

		zbx_dc_config_get_triggers_by_triggerids(triggers, triggerids.values, errcodes, triggerids.values_num);

		/* add missing diffs to the trigger changeset */

		for (i = 0; i < triggerids.values_num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			trigger = &triggers[i];

			zbx_trigger_diff_t	trigger_diff_cmp = {.triggerid = triggerids.values[i]};

			if (FAIL == (index = zbx_vector_trigger_diff_ptr_bsearch(trigger_diff, &trigger_diff_cmp,
					zbx_trigger_diff_compare_func)))
			{
				zbx_append_trigger_diff(trigger_diff, trigger->triggerid, trigger->priority,
						ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT, trigger->value,
						TRIGGER_STATE_NORMAL, 0, NULL);

				/* TODO: should we store trigger diffs in hashset rather than vector? */
				zbx_vector_trigger_diff_ptr_sort(trigger_diff, zbx_trigger_diff_compare_func);
			}
			else
			{
				diff = trigger_diff->values[index];
				diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT;
			}
		}

		/* get correlated eventids that are still open (unresolved) */

		zbx_hashset_iter_reset(&correlation_cache, &iter);
		while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
		{
			/* close event only if its source trigger has been locked */
			if (FAIL == (index = zbx_vector_uint64_bsearch(&triggerids, recovery->objectid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				continue;
			}

			if (SUCCEED != errcodes[index])
				continue;

			zbx_vector_uint64_append(&eventids, recovery->eventid);
		}

		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select eventid from problem"
								" where r_eventid is null and");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);
		zbx_vector_uint64_clear(&eventids);
		zbx_db_select_uint64(sql, &eventids);
		zbx_free(sql);

		/* generate OK events and add event_recovery data for closed events */
		zbx_hashset_iter_reset(&correlation_cache, &iter);
		while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
		{
			if (FAIL == (index = zbx_vector_uint64_bsearch(&triggerids, recovery->objectid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				continue;
			}

			/* close the old problem only if it's still open and trigger is not removed */
			if (SUCCEED == errcodes[index] && FAIL != zbx_vector_uint64_bsearch(&eventids, recovery->eventid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				trigger = &triggers[index];

				close_trigger_event(recovery->eventid, recovery->objectid, &recovery->ts, 0,
						recovery->correlationid, recovery->c_eventid, trigger->description,
						trigger->expression, trigger->recovery_expression,
						trigger->priority, trigger->type, trigger->opdata, trigger->event_name);

				closed_num++;
			}

			zbx_hashset_iter_remove(&iter);
		}

		zbx_dc_config_clean_triggers(triggers, errcodes, triggerids.values_num);
		zbx_free(errcodes);
		zbx_free(triggers);
	}

	zbx_vector_uint64_destroy(&eventids);
	zbx_vector_uint64_destroy(&lockids);
	zbx_vector_uint64_destroy(&triggerids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() closed:%d", __func__, closed_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update number of open problems                                    *
 *                                                                            *
 * Parameters: trigger_diff    - [IN/OUT] changeset of triggers that          *
 *                               generated the events in local cache          *
 *                                                                            *
 * Comments: When a specific event is closed (by correlation or manually) the *
 *           open problem count has to be queried from problem table to       *
 *           correctly calculate new trigger value.                           *
 *                                                                            *
 ******************************************************************************/
static void	update_trigger_problem_count(zbx_vector_trigger_diff_ptr_t *trigger_diff)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	triggerids;
	zbx_trigger_diff_t	*diff;
	int			i, index;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		triggerid;

	zbx_vector_uint64_create(&triggerids);

	for (i = 0; i < trigger_diff->values_num; i++)
	{
		diff = trigger_diff->values[i];

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT))
		{
			zbx_vector_uint64_append(&triggerids, diff->triggerid);

			/* reset problem count, it will be updated from database if there are open problems */
			diff->problem_count = 0;
			diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT;
		}
	}

	if (0 == triggerids.values_num)
		goto out;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select objectid,count(objectid) from problem"
			" where r_eventid is null"
				" and source=%d"
				" and object=%d"
				" and",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", triggerids.values, triggerids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " group by objectid");

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		zbx_trigger_diff_t	trigger_diff_cmp = {.triggerid = triggerid};

		if (FAIL == (index = zbx_vector_trigger_diff_ptr_bsearch(trigger_diff, &trigger_diff_cmp,
				zbx_trigger_diff_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = trigger_diff->values[index];
		diff->problem_count = atoi(row[1]);
		diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT;
	}
	zbx_db_free_result(result);

	zbx_free(sql);
out:
	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: update trigger value, problem count fields depending on problem   *
 *          and recovered events                                              *
 *                                                                            *
 ******************************************************************************/
static void	update_trigger_changes(zbx_vector_trigger_diff_ptr_t *trigger_diff)
{
	int			i, index;
	unsigned char		new_value;
	zbx_trigger_diff_t	*diff;

	update_trigger_problem_count(trigger_diff);

	/* update trigger problem_count for new problem events */
	for (i = 0; i < events.values_num; i++)
	{
		zbx_db_event	*event = events.values[i];

		if (EVENT_SOURCE_TRIGGERS != event->source || EVENT_OBJECT_TRIGGER != event->object)
			continue;

		zbx_trigger_diff_t	trigger_diff_cmp = {.triggerid = event->objectid};

		if (FAIL == (index = zbx_vector_trigger_diff_ptr_bsearch(trigger_diff, &trigger_diff_cmp,
				zbx_trigger_diff_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = trigger_diff->values[index];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
		{
			diff->flags &= ~(zbx_uint64_t)(ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT |
					ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE);
			continue;
		}

		/* always update trigger last change whenever a trigger event has been created */
		diff->lastchange = event->clock;
		diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE;
	}

	/* recalculate trigger value from problem_count and mark for updating if necessary */
	for (i = 0; i < trigger_diff->values_num; i++)
	{
		diff = trigger_diff->values[i];

		if (0 == (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT))
			continue;

		new_value = (0 == diff->problem_count ? TRIGGER_VALUE_OK : TRIGGER_VALUE_PROBLEM);

		if (new_value != diff->value)
		{
			diff->value = new_value;
			diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes the data structures required for event processing     *
 *                                                                            *
 ******************************************************************************/
void	zbx_initialize_events(void)
{
	zbx_vector_db_event_create(&events);
	zbx_hashset_create(&event_recovery, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&correlation_cache, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_dc_correlation_rules_init(&correlation_rules);
}

/******************************************************************************
 *                                                                            *
 * Purpose: uninitializes the data structures required for event processing   *
 *                                                                            *
 ******************************************************************************/
void	zbx_uninitialize_events(void)
{
	zbx_vector_db_event_destroy(&events);
	zbx_hashset_destroy(&event_recovery);
	zbx_hashset_destroy(&correlation_cache);

	zbx_dc_correlation_rules_free(&correlation_rules);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reset event_recovery data                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_reset_event_recovery(void)
{
	zbx_hashset_clear(&event_recovery);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans single event                                               *
 *                                                                            *
 ******************************************************************************/
static void	zbx_clean_event(zbx_db_event *event)
{
	zbx_free(event->name);

	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		zbx_db_trigger_clean(&event->trigger);
		zbx_free(event->trigger.correlation_tag);
	}

	if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
	{
		zbx_vector_tags_ptr_clear_ext(&event->tags, zbx_free_tag);
		zbx_vector_tags_ptr_destroy(&event->tags);
	}

	if (NULL != event->maintenanceids)
	{
		zbx_vector_uint64_destroy(event->maintenanceids);
		zbx_free(event->maintenanceids);
	}

	zbx_free(event);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans all events and events recoveries                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_clean_events(void)
{
	zbx_vector_db_event_clear_ext(&events, zbx_clean_event);

	zbx_reset_event_recovery();
}

/******************************************************************************
 *                                                                            *
 * Purpose:  get hosts that are associated with trigger expression/recovery   *
 *           expression                                                       *
 *                                                                            *
 ******************************************************************************/
static void	db_trigger_get_hosts(zbx_hashset_t *hosts, zbx_db_trigger *trigger)
{
	zbx_vector_uint64_t	functionids;

	zbx_vector_uint64_create(&functionids);
	zbx_db_trigger_get_all_functionids(trigger, &functionids);
	zbx_dc_get_hosts_by_functionids(&functionids, hosts);
	zbx_vector_uint64_destroy(&functionids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: export events                                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_export_events(int events_export_enabled, zbx_vector_connector_filter_t *connector_filters,
		unsigned char **data, size_t *data_alloc, size_t *data_offset)
{
	int			i, j;
	struct zbx_json		json;
	size_t			sql_alloc = 256, sql_offset;
	char			*sql = NULL;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_hashset_t		hosts;
	zbx_vector_uint64_t	hostids;
	zbx_hashset_iter_t	iter;
	zbx_event_recovery_t	*recovery;
	zbx_connector_object_t	connector_object;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)events.values_num);

	if (0 == events.values_num)
		goto exit;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	sql = (char *)zbx_malloc(sql, sql_alloc);
	zbx_hashset_create(&hosts, events.values_num, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&connector_object.ids);

	for (i = 0; i < events.values_num; i++)
	{
		zbx_dc_host_t		*host;
		zbx_db_event		*event;
		zbx_vector_str_t	groups;

		event = events.values[i];

		if (EVENT_SOURCE_TRIGGERS != event->source || 0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (TRIGGER_VALUE_PROBLEM != event->value)
			continue;

		if (0 != connector_filters->values_num)
		{
			int			k;
			zbx_vector_tags_ptr_t	event_tags;

			zbx_vector_tags_ptr_create(&event_tags);
			zbx_vector_tags_ptr_append_array(&event_tags, event->tags.values, event->tags.values_num);
			zbx_vector_tags_ptr_sort(&event_tags, zbx_compare_tags);

			for (k = 0; k < connector_filters->values_num; k++)
			{
				if (SUCCEED == zbx_match_tags(connector_filters->values[k].tags_evaltype,
						&connector_filters->values[k].connector_tags, &event_tags))
				{
					zbx_vector_uint64_append(&connector_object.ids,
							connector_filters->values[k].connectorid);
				}
			}

			zbx_vector_tags_ptr_destroy(&event_tags);

			if (0 == connector_object.ids.values_num && FAIL == events_export_enabled)
				continue;
		}

		zbx_json_clean(&json);

		zbx_json_addint64(&json, ZBX_PROTO_TAG_CLOCK, event->clock);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_NS, event->ns);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_VALUE, event->value);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_EVENTID, event->eventid);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_NAME, event->name, ZBX_JSON_TYPE_STRING);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_SEVERITY, event->severity);

		db_trigger_get_hosts(&hosts, &event->trigger);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_HOSTS);

		zbx_hashset_iter_reset(&hosts, &iter);

		while (NULL != (host = (zbx_dc_host_t *)zbx_hashset_iter_next(&iter)))
		{
			zbx_json_addobject(&json,NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, host->host, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_NAME, host->name, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json);
			zbx_vector_uint64_append(&hostids, host->hostid);
		}

		zbx_json_close(&json);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select distinct g.name"
					" from hstgrp g, hosts_groups hg"
					" where g.groupid=hg.groupid"
						" and");

		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.hostid", hostids.values,
				hostids.values_num);

		result = zbx_db_select("%s", sql);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_GROUPS);

		zbx_vector_str_create(&groups);
		while (NULL != (row = zbx_db_fetch(result)))
			zbx_vector_str_append(&groups, zbx_strdup(NULL, row[0]));

		zbx_vector_str_sort(&groups, ZBX_DEFAULT_STR_COMPARE_FUNC);

		for (j = 0; j < groups.values_num; j++)
			zbx_json_addstring(&json, NULL, groups.values[j], ZBX_JSON_TYPE_STRING);

		zbx_vector_str_clear_ext(&groups, zbx_str_free);
		zbx_vector_str_destroy(&groups);

		zbx_db_free_result(result);

		zbx_json_close(&json);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_TAGS);
		for (j = 0; j < event->tags.values_num; j++)
		{
			zbx_tag_t	*tag = event->tags.values[j];

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_TAG, tag->tag, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, tag->value, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json);
		}

		zbx_hashset_clear(&hosts);
		zbx_vector_uint64_clear(&hostids);

		if (0 != connector_object.ids.values_num)
		{
			connector_object.objectid = event->trigger.triggerid;
			connector_object.ts.sec = event->clock;
			connector_object.ts.ns = event->ns;
			connector_object.str = json.buffer;

			zbx_connector_serialize_object(data, data_alloc, data_offset, &connector_object);

			zbx_vector_uint64_clear(&connector_object.ids);
		}

		if (SUCCEED == events_export_enabled)
			zbx_problems_export_write(json.buffer, json.buffer_size);
	}

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		if (EVENT_SOURCE_TRIGGERS != recovery->r_event->source)
			continue;

		if (0 != connector_filters->values_num)
		{
			int			k;
			zbx_vector_tags_ptr_t	event_tags;

			zbx_vector_tags_ptr_create(&event_tags);
			zbx_vector_tags_ptr_append_array(&event_tags, recovery->r_event->tags.values,
					recovery->r_event->tags.values_num);
			zbx_vector_tags_ptr_sort(&event_tags, zbx_compare_tags);

			for (k = 0; k < connector_filters->values_num; k++)
			{
				if (SUCCEED == zbx_match_tags(connector_filters->values[k].tags_evaltype,
						&connector_filters->values[k].connector_tags, &event_tags))
				{
					zbx_vector_uint64_append(&connector_object.ids,
							connector_filters->values[k].connectorid);
				}
			}

			zbx_vector_tags_ptr_destroy(&event_tags);

			if (0 == connector_object.ids.values_num && FAIL == events_export_enabled)
				continue;
		}

		zbx_json_clean(&json);

		zbx_json_addint64(&json, ZBX_PROTO_TAG_CLOCK, recovery->r_event->clock);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_NS, recovery->r_event->ns);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_VALUE, recovery->r_event->value);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_EVENTID, recovery->r_event->eventid);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_PROBLEM_EVENTID, recovery->eventid);

		if (0 != connector_object.ids.values_num)
		{
			connector_object.objectid = recovery->r_event->trigger.triggerid;
			connector_object.ts.sec = recovery->r_event->clock;
			connector_object.ts.ns = recovery->r_event->ns;
			connector_object.str = json.buffer;
			zbx_connector_serialize_object(data, data_alloc, data_offset, &connector_object);
			zbx_vector_uint64_clear(&connector_object.ids);
		}

		if (SUCCEED == events_export_enabled)
			zbx_problems_export_write(json.buffer, json.buffer_size);
	}

	if (SUCCEED == events_export_enabled)
		zbx_problems_export_flush();

	zbx_vector_uint64_destroy(&connector_object.ids);
	zbx_hashset_destroy(&hosts);
	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);
	zbx_json_free(&json);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_events_update_itservices(void)
{
	unsigned char		*data = NULL;
	size_t			data_alloc = 0, data_offset = 0;
	int			i;
	zbx_hashset_iter_t	iter;
	zbx_event_recovery_t	*recovery;

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		int	values_num;

		if (EVENT_SOURCE_TRIGGERS != recovery->r_event->source)
			continue;

		values_num = recovery->r_event->tags.values_num;
		recovery->r_event->tags.values_num = 0;

		zbx_service_serialize_event(&data, &data_alloc, &data_offset, recovery->eventid, recovery->r_event->clock,
				recovery->r_event->ns, recovery->r_event->value, recovery->r_event->severity,
				&recovery->r_event->tags, NULL);

		recovery->r_event->tags.values_num = values_num;
	}

	for (i = 0; i < events.values_num; i++)
	{
		zbx_db_event	*event = events.values[i];

		if (EVENT_SOURCE_TRIGGERS != event->source || 0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (TRIGGER_VALUE_PROBLEM != event->value)
			continue;

		zbx_service_serialize_event(&data, &data_alloc, &data_offset, event->eventid, event->clock, event->ns,
				event->value, event->severity, &event->tags, event->maintenanceids);
	}

	if (NULL == data)
		return;

	if (0 != zbx_dc_get_itservices_num())
		zbx_service_flush(ZBX_IPC_SERVICE_SERVICE_PROBLEMS, data, (zbx_uint32_t)data_offset);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds event suppress data for problem events matching active       *
 *          maintenance periods                                               *
 *                                                                            *
 ******************************************************************************/
static void	add_event_suppress_data(zbx_vector_ptr_t *event_refs, zbx_vector_uint64_t *maintenanceids)
{
	zbx_vector_event_suppress_query_ptr_t		event_queries;
	zbx_event_suppress_query_t			*query;

	/* prepare query data  */

	zbx_vector_event_suppress_query_ptr_create(&event_queries);

	for (int i = 0; i < event_refs->values_num; i++)
	{
		zbx_db_event	*event = (zbx_db_event *)event_refs->values[i];

		query = (zbx_event_suppress_query_t *)zbx_malloc(NULL, sizeof(zbx_event_suppress_query_t));
		query->eventid = event->eventid;

		zbx_vector_uint64_create(&query->hostids);
		zbx_vector_uint64_create(&query->functionids);
		zbx_db_trigger_get_all_functionids(&event->trigger, &query->functionids);

		zbx_vector_tags_ptr_create(&query->tags);
		if (0 != event->tags.values_num)
			zbx_vector_tags_ptr_append_array(&query->tags, event->tags.values, event->tags.values_num);

		zbx_vector_uint64_pair_create(&query->maintenances);

		zbx_vector_event_suppress_query_ptr_append(&event_queries, query);
	}

	if (0 != event_queries.values_num)
	{
		zbx_db_insert_t	db_insert;

		/* get maintenance data and save it in database */
		if (SUCCEED == zbx_dc_get_event_maintenances(&event_queries, maintenanceids) &&
				SUCCEED == zbx_db_lock_maintenanceids(maintenanceids))
		{
			zbx_db_insert_prepare(&db_insert, "event_suppress", "event_suppressid", "eventid",
					"maintenanceid", "suppress_until", (char *)NULL);

			for (int j = 0; j < event_queries.values_num; j++)
			{
				zbx_db_event	*event = (zbx_db_event *)event_refs->values[j];

				query = event_queries.values[j];

				for (int i = 0; i < query->maintenances.values_num; i++)
				{
					/* when locking maintenances not-locked (deleted) maintenance ids */
					/* are removed from the maintenanceids vector                   */
					if (FAIL == zbx_vector_uint64_bsearch(maintenanceids,
							query->maintenances.values[i].first,
							ZBX_DEFAULT_UINT64_COMPARE_FUNC))
					{
						continue;
					}

					zbx_db_insert_add_values(&db_insert, __UINT64_C(0), query->eventid,
							query->maintenances.values[i].first,
							(int)query->maintenances.values[i].second);

					zbx_db_event_add_maintenanceid(event, query->maintenances.values[i].first);
				}
			}

			zbx_db_insert_autoincrement(&db_insert, "event_suppressid");
			zbx_db_insert_execute(&db_insert);
			zbx_db_insert_clean(&db_insert);
		}

		for (int j = 0; j < event_queries.values_num; j++)
		{
			query = event_queries.values[j];
			/* reset tags vector to avoid double freeing copied tag name/value pointers */
			zbx_vector_tags_ptr_clear(&query->tags);
		}
		zbx_vector_event_suppress_query_ptr_clear_ext(&event_queries, zbx_event_suppress_query_free);
	}

	zbx_vector_event_suppress_query_ptr_destroy(&event_queries);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve running maintenances for each event and saves it in      *
 *          event_suppress table                                              *
 *                                                                            *
 ******************************************************************************/
static void	update_event_suppress_data(void)
{
	zbx_vector_ptr_t	event_refs;
	zbx_vector_uint64_t	maintenanceids;
	int			i;
	zbx_db_event		*event;

	zbx_vector_uint64_create(&maintenanceids);
	zbx_vector_ptr_create(&event_refs);
	zbx_vector_ptr_reserve(&event_refs, events.values_num);

	/* prepare trigger problem event vector */
	for (i = 0; i < events.values_num; i++)
	{
		event = events.values[i];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (EVENT_SOURCE_TRIGGERS != event->source)
			continue;

		if (TRIGGER_VALUE_PROBLEM == event->value)
			zbx_vector_ptr_append(&event_refs, event);
	}

	if (0 == event_refs.values_num)
		goto out;

	if (SUCCEED != zbx_dc_get_running_maintenanceids(&maintenanceids))
		goto out;

	if (0 != event_refs.values_num)
		add_event_suppress_data(&event_refs, &maintenanceids);
out:
	zbx_vector_ptr_destroy(&event_refs);
	zbx_vector_uint64_destroy(&maintenanceids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flushes local event cache to database                             *
 *                                                                            *
 ******************************************************************************/
static int	flush_events(zbx_vector_escalation_new_ptr_t *escalations)
{
	int				ret;
	zbx_event_recovery_t		*recovery;
	zbx_vector_uint64_pair_t	closed_events;
	zbx_hashset_iter_t		iter;

	ret = save_events();
	save_problems();
	save_event_recovery();
	update_event_suppress_data();

	zbx_vector_uint64_pair_create(&closed_events);

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_uint64_pair_t	pair = {recovery->eventid, recovery->r_event->eventid};

		zbx_vector_uint64_pair_append_ptr(&closed_events, &pair);
	}

	zbx_vector_uint64_pair_sort(&closed_events, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	process_actions(&events, &closed_events, escalations);
	zbx_vector_uint64_pair_destroy(&closed_events);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: recover an event                                                  *
 *                                                                            *
 * Parameters: eventid   - [IN]                                               *
 *             source    - [IN] recovery event source                         *
 *             object    - [IN] recovery event object                         *
 *             objectid  - [IN] recovery event object id                      *
 *                                                                            *
 ******************************************************************************/
static void	recover_event(zbx_uint64_t eventid, int source, int object, zbx_uint64_t objectid)
{
	zbx_db_event		*event;
	zbx_event_recovery_t	recovery_local;

	if (NULL == (event = get_event_by_source_object_id(source, object, objectid)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	if (EVENT_SOURCE_INTERNAL == source)
		event->flags |= ZBX_FLAGS_DB_EVENT_RECOVER;

	recovery_local.eventid = eventid;

	if (NULL != zbx_hashset_search(&event_recovery, &recovery_local))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	recovery_local.objectid = objectid;
	recovery_local.r_event = event;
	recovery_local.correlationid = 0;
	recovery_local.c_eventid = 0;
	recovery_local.userid = 0;
	recovery_local.ts.sec = 0;
	recovery_local.ts.ns = 0;

	zbx_hashset_insert(&event_recovery, &recovery_local, sizeof(recovery_local));
}

/******************************************************************************
 *                                                                            *
 * Purpose: process internal recovery events                                  *
 *                                                                            *
 * Parameters: ok_events - [IN] the recovery events to process                *
 *                                                                            *
 ******************************************************************************/
static void	process_internal_ok_events(const zbx_vector_ptr_t *ok_events)
{
	int			i, object;
	zbx_uint64_t		objectid, eventid;
	char			*sql = NULL;
	const char		*separator = "";
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	triggerids, itemids, lldruleids;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_event		*event;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_create(&lldruleids);

	for (i = 0; i < ok_events->values_num; i++)
	{
		event = (zbx_db_event *)ok_events->values[i];

		if (ZBX_FLAGS_DB_EVENT_UNSET == event->flags)
			continue;

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				zbx_vector_uint64_append(&triggerids, event->objectid);
				break;
			case EVENT_OBJECT_ITEM:
				zbx_vector_uint64_append(&itemids, event->objectid);
				break;
			case EVENT_OBJECT_LLDRULE:
				zbx_vector_uint64_append(&lldruleids, event->objectid);
				break;
		}
	}

	if (0 == triggerids.values_num && 0 == itemids.values_num && 0 == lldruleids.values_num)
		goto out;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select eventid,object,objectid from problem"
			" where r_eventid is null"
				" and source=%d"
			" and (", EVENT_SOURCE_INTERNAL);

	if (0 != triggerids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (object=%d and",
				separator, EVENT_OBJECT_TRIGGER);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", triggerids.values,
				triggerids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		separator=" or";
	}

	if (0 != itemids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (object=%d and",
				separator, EVENT_OBJECT_ITEM);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", itemids.values,
				itemids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		separator=" or";
	}

	if (0 != lldruleids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (object=%d and",
				separator, EVENT_OBJECT_LLDRULE);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", lldruleids.values,
				lldruleids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
	}

	zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);
		object = atoi(row[1]);
		ZBX_STR2UINT64(objectid, row[2]);

		recover_event(eventid, EVENT_SOURCE_INTERNAL, object, objectid);
	}

	zbx_db_free_result(result);
	zbx_free(sql);

out:
	zbx_vector_uint64_destroy(&lldruleids);
	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: do not generate unnecessary internal events if there are no       *
 *          internal actions and no problem recovery from when actions were   *
 *          enabled                                                           *
 *                                                                            *
 * Parameters: internal_problem_events - [IN/OUT] problem events to process   *
 * Parameters: internal_ok_events      - [IN/OUT] recovery events to process  *
 *                                                                            *
 ******************************************************************************/
static void	process_internal_events_without_actions(zbx_vector_ptr_t *internal_problem_events,
		zbx_vector_ptr_t *internal_ok_events)
{
	zbx_db_event	*event;
	int		i;

	if (0 != zbx_dc_get_internal_action_count())
		return;

	for (i = 0; i < internal_problem_events->values_num; i++)
		((zbx_db_event *)internal_problem_events->values[i])->flags = ZBX_FLAGS_DB_EVENT_UNSET;

	for (i = 0; i < internal_ok_events->values_num; i++)
	{
		event = (zbx_db_event *)internal_ok_events->values[i];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_RECOVER))
			event->flags = ZBX_FLAGS_DB_EVENT_UNSET;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets open problems created by the specified triggers              *
 *                                                                            *
 * Parameters: triggerids - [IN] trigger identifiers (sorted)                 *
 *             problems   - [OUT]                                             *
 *                                                                            *
 ******************************************************************************/
static void	get_open_problems(const zbx_vector_uint64_t *triggerids, zbx_vector_ptr_t *problems)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_event_problem_t	*problem;
	zbx_tag_t		*tag;
	zbx_uint64_t		eventid;
	int			index;
	zbx_vector_uint64_t	eventids;

	zbx_vector_uint64_create(&eventids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select eventid,objectid from problem where source=%d and object=%d and",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", triggerids->values, triggerids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and r_eventid is null");

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		problem = (zbx_event_problem_t *)zbx_malloc(NULL, sizeof(zbx_event_problem_t));

		ZBX_STR2UINT64(problem->eventid, row[0]);
		ZBX_STR2UINT64(problem->triggerid, row[1]);
		zbx_vector_tags_ptr_create(&problem->tags);
		zbx_vector_ptr_append(problems, problem);

		zbx_vector_uint64_append(&eventids, problem->eventid);
	}
	zbx_db_free_result(result);

	if (0 != problems->values_num)
	{
		zbx_vector_ptr_sort(problems, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select eventid,tag,value from problem_tag where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);
			if (FAIL == (index = zbx_vector_ptr_bsearch(problems, &eventid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			problem = (zbx_event_problem_t *)problems->values[index];

			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, row[1]);
			tag->value = zbx_strdup(NULL, row[2]);
			zbx_vector_tags_ptr_append(&problem->tags, tag);
		}
		zbx_db_free_result(result);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&eventids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees cached problem event                                        *
 *                                                                            *
 ******************************************************************************/
static void	event_problem_free(zbx_event_problem_t *problem)
{
	zbx_vector_tags_ptr_clear_ext(&problem->tags, zbx_free_tag);
	zbx_vector_tags_ptr_destroy(&problem->tags);
	zbx_free(problem);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees trigger dependency                                          *
 *                                                                            *
 ******************************************************************************/

static void	trigger_dep_free(zbx_trigger_dep_t *dep)
{
	zbx_vector_uint64_destroy(&dep->masterids);
	zbx_free(dep);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check event dependency based on cached and actual trigger values  *
 *                                                                            *
 * Parameters: event        - [IN] event to check                             *
 *             deps         - [IN] trigger dependency data (sorted by         *
 *                                 triggerid)                                 *
 *             trigger_diff - [IN] trigger changeset - source of actual       *
 *                                 trigger values (sorted by triggerid)       *
 *                                                                            *
 ******************************************************************************/
static int	event_check_dependency(const zbx_db_event *event, const zbx_vector_trigger_dep_ptr_t *deps,
		const zbx_vector_trigger_diff_ptr_t *trigger_diff)
{
	int			i, index;
	zbx_trigger_dep_t	*dep;
	zbx_trigger_diff_t	*diff;

	zbx_trigger_dep_t	cmp = {.triggerid = event->objectid};

	if (FAIL == (index = zbx_vector_trigger_dep_ptr_bsearch(deps, &cmp, zbx_trigger_dep_compare_func)))
		return SUCCEED;

	dep = deps->values[index];

	if (ZBX_TRIGGER_DEPENDENCY_FAIL == dep->status)
		return FAIL;

	/* check the trigger dependency based on actual (currently being processed) trigger values */
	for (i = 0; i < dep->masterids.values_num; i++)
	{
		zbx_trigger_diff_t	trigger_diff_cmp = {.triggerid = dep->masterids.values[i]};

		if (FAIL == (index = zbx_vector_trigger_diff_ptr_bsearch(trigger_diff, &trigger_diff_cmp,
				zbx_trigger_diff_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = trigger_diff->values[index];

		if (0 == (ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE & diff->flags))
			continue;

		if (TRIGGER_VALUE_PROBLEM == diff->value)
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the two tag sets have matching tag                      *
 *                                                                            *
 * Parameters: name  - [IN] name of tag to match                              *
 *             tags1 - [IN] first tag vector                                  *
 *             tags2 - [IN] second tag vector                                 *
 *                                                                            *
 * Return value: SUCCEED - both tag sets contains a tag with the specified    *
 *                         name and the same value                            *
 *               FAIL    - otherwise.                                         *
 *                                                                            *
 ******************************************************************************/
static int	match_tag(const char *name, const zbx_vector_tags_ptr_t *tags1, const zbx_vector_tags_ptr_t *tags2)
{
	int		i, j;
	zbx_tag_t	*tag1, *tag2;

	for (i = 0; i < tags1->values_num; i++)
	{
		tag1 = tags1->values[i];

		if (0 != strcmp(tag1->tag, name))
			continue;

		for (j = 0; j < tags2->values_num; j++)
		{
			tag2 = tags2->values[j];

			if (0 == strcmp(tag2->tag, name) && 0 == strcmp(tag1->value, tag2->value))
				return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes trigger events                                          *
 *                                                                            *
 * Parameters: trigger_events - [IN] trigger events to process                *
 *             trigger_diff   - [IN] trigger changeset                        *
 *                                                                            *
 ******************************************************************************/
static void	process_trigger_events(const zbx_vector_ptr_t *trigger_events,
		const zbx_vector_trigger_diff_ptr_t *trigger_diff)
{
	int				i, j, index;
	zbx_vector_uint64_t		triggerids;
	zbx_vector_ptr_t		problems;
	zbx_vector_trigger_dep_ptr_t	deps;
	zbx_db_event			*event;
	zbx_event_problem_t		*problem;
	zbx_trigger_diff_t		*diff;
	unsigned char			value;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, trigger_events->values_num);

	zbx_vector_ptr_create(&problems);
	zbx_vector_ptr_reserve(&problems, trigger_events->values_num);

	zbx_vector_trigger_dep_ptr_create(&deps);
	zbx_vector_trigger_dep_ptr_reserve(&deps, trigger_events->values_num);

	/* cache relevant problems */

	for (i = 0; i < trigger_events->values_num; i++)
	{
		event = (zbx_db_event *)trigger_events->values[i];

		if (TRIGGER_VALUE_OK == event->value)
			zbx_vector_uint64_append(&triggerids, event->objectid);
	}

	if (0 != triggerids.values_num)
	{
		zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		get_open_problems(&triggerids, &problems);
	}

	/* get trigger dependency data */

	zbx_vector_uint64_clear(&triggerids);
	for (i = 0; i < trigger_events->values_num; i++)
	{
		event = (zbx_db_event *)trigger_events->values[i];
		zbx_vector_uint64_append(&triggerids, event->objectid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_dc_get_trigger_dependencies(&triggerids, &deps);

	/* process trigger events */

	for (i = 0; i < trigger_events->values_num; i++)
	{
		event = (zbx_db_event *)trigger_events->values[i];

		zbx_trigger_diff_t	trigger_diff_cmp = {.triggerid = event->objectid};

		if (FAIL == (index = zbx_vector_trigger_diff_ptr_search(trigger_diff, &trigger_diff_cmp,
				zbx_trigger_diff_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = trigger_diff->values[index];

		if (FAIL == (event_check_dependency(event, &deps, trigger_diff)))
		{
			/* reset event data/trigger changeset if dependency check failed */
			event->flags = ZBX_FLAGS_DB_EVENT_UNSET;
			diff->flags = ZBX_FLAGS_TRIGGER_DIFF_UNSET;
			continue;
		}

		if (TRIGGER_VALUE_PROBLEM == event->value)
		{
			/* Problem events always sets problem value to trigger.    */
			/* if the trigger is affected by global correlation rules, */
			/* its value is recalculated later.                        */
			diff->value = TRIGGER_VALUE_PROBLEM;
			diff->lastchange = event->clock;
			diff->flags |= (ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE | ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE);
			continue;
		}

		if (TRIGGER_VALUE_OK != event->value)
			continue;

		/* attempt to recover problem events/triggers */

		if (ZBX_TRIGGER_CORRELATION_NONE == event->trigger.correlation_mode)
		{
			/* with trigger correlation disabled the recovery event recovers */
			/* all problem events generated by the same trigger and sets     */
			/* trigger value to OK                                           */
			for (j = 0; j < problems.values_num; j++)
			{
				problem = (zbx_event_problem_t *)problems.values[j];

				if (problem->triggerid == event->objectid)
				{
					recover_event(problem->eventid, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER,
							event->objectid);
				}
			}

			diff->value = TRIGGER_VALUE_OK;
			diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE;
		}
		else
		{
			/* With trigger correlation enabled the recovery event recovers    */
			/* all problem events generated by the same trigger and matching   */
			/* recovery event tags. The trigger value is set to OK only if all */
			/* problem events were recovered.                                  */

			value = TRIGGER_VALUE_OK;
			event->flags = ZBX_FLAGS_DB_EVENT_UNSET;

			for (j = 0; j < problems.values_num; j++)
			{
				problem = (zbx_event_problem_t *)problems.values[j];

				if (problem->triggerid == event->objectid)
				{
					if (SUCCEED == match_tag(event->trigger.correlation_tag,
							&problem->tags, &event->tags))
					{
						recover_event(problem->eventid, EVENT_SOURCE_TRIGGERS,
								EVENT_OBJECT_TRIGGER, event->objectid);
						event->flags = ZBX_FLAGS_DB_EVENT_CREATE;
					}
					else
						value = TRIGGER_VALUE_PROBLEM;

				}
			}

			diff->value = value;
			diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE;
		}
	}

	zbx_vector_ptr_clear_ext(&problems, (zbx_clean_func_t)event_problem_free);
	zbx_vector_ptr_destroy(&problems);

	zbx_vector_trigger_dep_ptr_clear_ext(&deps, trigger_dep_free);
	zbx_vector_trigger_dep_ptr_destroy(&deps);

	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process internal trigger events                                   *
 *          to avoid trigger dependency                                       *
 *                                                                            *
 * Parameters: internal_events - [IN] internal events to process              *
 *             trigger_events  - [IN] trigger events used for dependency      *
 *             trigger_diff   -  [IN] trigger changeset                       *
 *                                                                            *
 ******************************************************************************/
static void	process_internal_events_dependency(const zbx_vector_ptr_t *internal_events,
		const zbx_vector_ptr_t *trigger_events, const zbx_vector_trigger_diff_ptr_t *trigger_diff)
{
	int				i, index;
	zbx_db_event			*event;
	zbx_vector_uint64_t		triggerids;
	zbx_vector_trigger_dep_ptr_t	deps;
	zbx_trigger_diff_t		*diff;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, internal_events->values_num + trigger_events->values_num);

	zbx_vector_trigger_dep_ptr_create(&deps);
	zbx_vector_trigger_dep_ptr_reserve(&deps, internal_events->values_num + trigger_events->values_num);

	for (i = 0; i < internal_events->values_num; i++)
	{
		event = (zbx_db_event *)internal_events->values[i];
		zbx_vector_uint64_append(&triggerids, event->objectid);
	}

	for (i = 0; i < trigger_events->values_num; i++)
	{
		event = (zbx_db_event *)trigger_events->values[i];
		zbx_vector_uint64_append(&triggerids, event->objectid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_dc_get_trigger_dependencies(&triggerids, &deps);

	for (i = 0; i < internal_events->values_num; i++)
	{
		event = (zbx_db_event *)internal_events->values[i];

		zbx_trigger_diff_t	trigger_diff_cmp = {.triggerid = event->objectid};

		if (FAIL == (index = zbx_vector_trigger_diff_ptr_search(trigger_diff, &trigger_diff_cmp,
				zbx_trigger_diff_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = (zbx_trigger_diff_t *)trigger_diff->values[index];

		if (FAIL == (event_check_dependency(event, &deps, trigger_diff)))
		{
			/* reset event data/trigger changeset if dependency check failed */
			event->flags = ZBX_FLAGS_DB_EVENT_UNSET;
			diff->flags = ZBX_FLAGS_TRIGGER_DIFF_UNSET;
			continue;
		}
	}

	zbx_vector_trigger_dep_ptr_clear_ext(&deps, trigger_dep_free);
	zbx_vector_trigger_dep_ptr_destroy(&deps);

	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes cached events                                           *
 *                                                                            *
 * Parameters: trigger_diff    - [IN/OUT] The changeset of triggers that      *
 *                               generated the events in local cache. When    *
 *                               processing global correlation rules new      *
 *                               diffs can be added to trigger changeset.     *
 *                               Can be NULL when processing events from      *
 *                               non trigger sources                          *
 *             triggerids_lock - [IN/OUT] The ids of triggers locked by items.*
 *                               When processing global correlation rules new *
 *                               triggers can be locked and added to this     *
 *                               vector.                                      *
 *                               Can be NULL when processing events from      *
 *                               non trigger sources                          *
 *             escalations     - [OUT]                                        *
 *                                                                            *
 * Return value: number of processed events                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_process_events(zbx_vector_trigger_diff_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock,
		zbx_vector_escalation_new_ptr_t *escalations)
{
	int			i, processed_num = 0;
	zbx_uint64_t		eventid;
	zbx_vector_ptr_t	internal_problem_events, internal_ok_events, trigger_events, internal_events;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)events.values_num);

	if (NULL != trigger_diff && 0 != correlation_cache.num_data)
		flush_correlation_queue(trigger_diff, triggerids_lock);

	if (0 != events.values_num)
	{
		zbx_vector_ptr_create(&internal_problem_events);
		zbx_vector_ptr_reserve(&internal_problem_events, events.values_num);
		zbx_vector_ptr_create(&internal_ok_events);
		zbx_vector_ptr_reserve(&internal_ok_events, events.values_num);

		zbx_vector_ptr_create(&trigger_events);
		zbx_vector_ptr_reserve(&trigger_events, events.values_num);

		zbx_vector_ptr_create(&internal_events);
		zbx_vector_ptr_reserve(&internal_events, events.values_num);

		/* assign event identifiers - they are required to set correlation event ids */
		eventid = zbx_db_get_maxid_num("events", events.values_num);
		for (i = 0; i < events.values_num; i++)
		{
			zbx_db_event	*event = events.values[i];

			event->eventid = eventid++;

			if (EVENT_SOURCE_TRIGGERS == event->source)
			{
				zbx_vector_ptr_append(&trigger_events, event);
				continue;
			}

			if (EVENT_SOURCE_INTERNAL == event->source)
			{
				switch (event->object)
				{
					case EVENT_OBJECT_TRIGGER:
						if (TRIGGER_STATE_NORMAL == event->value)
							zbx_vector_ptr_append(&internal_ok_events, event);
						else
							zbx_vector_ptr_append(&internal_problem_events, event);
						zbx_vector_ptr_append(&internal_events, event);
						break;
					case EVENT_OBJECT_ITEM:
						if (ITEM_STATE_NORMAL == event->value)
							zbx_vector_ptr_append(&internal_ok_events, event);
						else
							zbx_vector_ptr_append(&internal_problem_events, event);
						break;
					case EVENT_OBJECT_LLDRULE:
						if (ITEM_STATE_NORMAL == event->value)
							zbx_vector_ptr_append(&internal_ok_events, event);
						else
							zbx_vector_ptr_append(&internal_problem_events, event);
						break;
				}
			}
		}

		if (0 != internal_events.values_num)
			process_internal_events_dependency(&internal_events, &trigger_events, trigger_diff);

		if (0 != internal_ok_events.values_num)
			process_internal_ok_events(&internal_ok_events);

		if (0 != internal_problem_events.values_num || 0 != internal_ok_events.values_num)
			process_internal_events_without_actions(&internal_problem_events, &internal_ok_events);

		if (0 != trigger_events.values_num)
		{
			process_trigger_events(&trigger_events, trigger_diff);
			correlate_events_by_global_rules(&trigger_events, trigger_diff);
			flush_correlation_queue(trigger_diff, triggerids_lock);
		}

		processed_num = flush_events(escalations);

		if (0 != trigger_events.values_num)
			update_trigger_changes(trigger_diff);

		zbx_vector_ptr_destroy(&trigger_events);
		zbx_vector_ptr_destroy(&internal_ok_events);
		zbx_vector_ptr_destroy(&internal_problem_events);
		zbx_vector_ptr_destroy(&internal_events);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, (int)processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: closes problem event                                              *
 *                                                                            *
 * Parameters: triggerid - [IN] source trigger id                             *
 *             eventid   - [IN] event to close                                *
 *             userid    - [IN] user closing the event                        *
 *             rtc       - [IN] RTC socket                                    *
 *                                                                            *
 * Return value: SUCCEED - the problem was closed                             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_close_problem(zbx_uint64_t triggerid, zbx_uint64_t eventid, zbx_uint64_t userid,
		zbx_ipc_async_socket_t *rtc)
{
	zbx_dc_trigger_t	trigger;
	int			errcode, processed_num = 0;
	zbx_timespec_t		ts;
	zbx_db_event		*r_event;

	zbx_dc_config_get_triggers_by_triggerids(&trigger, &triggerid, &errcode, 1);

	if (SUCCEED == errcode)
	{
		zbx_vector_trigger_diff_ptr_t	trigger_diff;
		zbx_vector_escalation_new_ptr_t	escalations;

		zbx_vector_trigger_diff_ptr_create(&trigger_diff);
		zbx_vector_escalation_new_ptr_create(&escalations);

		zbx_append_trigger_diff(&trigger_diff, triggerid, trigger.priority,
				ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT, trigger.value,
				TRIGGER_STATE_NORMAL, 0, NULL);

		zbx_timespec(&ts);

		zbx_db_begin();

		r_event = close_trigger_event(eventid, triggerid, &ts, userid, 0, 0, trigger.description,
				trigger.expression, trigger.recovery_expression, trigger.priority,
				trigger.type, trigger.opdata, trigger.event_name);

		r_event->eventid = zbx_db_get_maxid_num("events", 1);

		processed_num = flush_events(&escalations);
		update_trigger_changes(&trigger_diff);
		zbx_db_save_trigger_changes(&trigger_diff);

		if (ZBX_DB_OK == zbx_db_commit())
		{
			int				event_export_enabled;
			zbx_vector_connector_filter_t	connector_filters_events;
			unsigned char			*data = NULL;
			size_t				data_alloc = 0, data_offset = 0;

			zbx_start_escalations(rtc, &escalations);

			zbx_dc_config_triggers_apply_changes(&trigger_diff);

			zbx_events_update_itservices();

			zbx_vector_connector_filter_create(&connector_filters_events);

			zbx_dc_config_history_sync_get_connector_filters(NULL, &connector_filters_events);

			if (SUCCEED == (event_export_enabled = zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS)) ||
					0 != connector_filters_events.values_num)
			{
				zbx_export_events(event_export_enabled, &connector_filters_events, &data, &data_alloc,
						&data_offset);

				if (0 != data_offset)
				{
					zbx_connector_send(ZBX_IPC_CONNECTOR_REQUEST, data,
							(zbx_uint32_t)data_offset);
				}
			}

			zbx_vector_connector_filter_clear(&connector_filters_events);
			zbx_vector_connector_filter_destroy(&connector_filters_events);

			zbx_free(data);
		}

		zbx_clean_events();
		zbx_vector_trigger_diff_ptr_clear_ext(&trigger_diff, zbx_trigger_diff_free);
		zbx_vector_trigger_diff_ptr_destroy(&trigger_diff);

		zbx_vector_escalation_new_ptr_clear_ext(&escalations, zbx_escalation_new_ptr_free);
		zbx_vector_escalation_new_ptr_destroy(&escalations);
	}

	zbx_dc_config_clean_triggers(&trigger, &errcode, 1);

	return (0 == processed_num ? FAIL : SUCCEED);
}
