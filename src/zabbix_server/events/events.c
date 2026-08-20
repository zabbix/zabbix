/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxevent.h"
#include "zbxexport.h"
#include "zbxstr.h"
#include "zbxexpr.h"
#include "zbxdbwrap.h"
#include "zbx_trigger_constants.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxjson.h"
#include "zbxconnector.h"
#include "zbxtagfilter.h"
#include "zbx_expression_constants.h"
#include "zbx_cep_client.h"
#include "zbxtime.h"

typedef enum
{
	CORRELATION_MATCH = 0,
	CORRELATION_NO_MATCH,
	CORRELATION_MAY_MATCH
}
zbx_correlation_match_result_t;

static zbx_vector_db_event_t	events;

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

static void	process_trigger_tag(zbx_dc_um_handle_t	*um_handle, zbx_db_event* event, const zbx_tag_t *tag)
{
	zbx_tag_t	*t;

	t = duplicate_tag(tag);

	zbx_substitute_macros(&t->tag, NULL, 0, &zbx_macro_trigger_tag_resolv, um_handle, event, NULL);
	zbx_substitute_macros(&t->value, NULL, 0, &zbx_macro_trigger_tag_resolv, um_handle, event, NULL);

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

zbx_db_event	*zbx_create_internal_event(unsigned char object, zbx_uint64_t objectid, int clock, int ns,
		int value, const char *error, zbx_dc_trigger_t *dc_trigger)
{
	zbx_db_event		*event;
	zbx_vector_item_tag_t	item_tags;
	zbx_dc_um_handle_t	*um_handle;

	zbx_vector_item_tag_create(&item_tags);

	event = zbx_create_event(EVENT_SOURCE_INTERNAL, object, objectid, clock, ns, value);
	zbx_vector_tags_ptr_create(&event->tags);

	if (NULL != error)
		event->name = zbx_strdup(NULL, error);

	zbx_vector_tags_ptr_create(&event->tags);
	zbx_vector_item_tag_create(&item_tags);

	um_handle = zbx_dc_open_user_macros();

	switch (object)
	{
		case EVENT_OBJECT_TRIGGER:
			memset(&event->trigger, 0, sizeof(zbx_db_trigger));
			zbx_vector_uint64_create(&event->trigger.dep_triggerids);

			if (NULL != dc_trigger)
			{
				event->trigger.triggerid = objectid;
				event->trigger.expression = zbx_strdup(NULL, dc_trigger->expression);
				event->trigger.recovery_expression = zbx_strdup(NULL, dc_trigger->recovery_expression);

				for (int i = 0; i < dc_trigger->tags.values_num; i++)
					process_trigger_tag(um_handle, event, dc_trigger->tags.values[i]);

				get_item_tags_by_expression(&event->trigger, &item_tags);
			}
			else
				THIS_SHOULD_NEVER_HAPPEN_MSG("internal trigger event being created without a trigger");
			break;
		case EVENT_OBJECT_ITEM:
			zbx_dc_get_item_tags(objectid, &item_tags);
	}

	for (int i = 0; i < item_tags.values_num; i++)
	{
		process_item_tag(event, item_tags.values[i], um_handle);
		zbx_free_item_tag(item_tags.values[i]);
	}

	zbx_dc_close_user_macros(um_handle);
	zbx_vector_item_tag_destroy(&item_tags);

	return event;
}

zbx_db_event	*zbx_create_trigger_event(const zbx_dc_trigger_t *dc_trigger, int clock, int ns, int value)
{
	zbx_db_event		*event;
	zbx_vector_item_tag_t	item_tags;
	zbx_dc_um_handle_t	*um_handle;
	char			err[256];

	zbx_vector_item_tag_create(&item_tags);

	event = zbx_create_event(EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, dc_trigger->triggerid, clock, ns, value);
	zbx_vector_tags_ptr_create(&event->tags);

	um_handle = zbx_dc_open_user_macros();

	if (TRIGGER_VALUE_PROBLEM == value)
		event->severity = dc_trigger->priority;

	event->trigger.triggerid = dc_trigger->triggerid;
	event->trigger.description = zbx_strdup(NULL, dc_trigger->description);
	event->trigger.expression = zbx_strdup(NULL, dc_trigger->expression);
	event->trigger.recovery_mode = dc_trigger->recovery_mode;
	event->trigger.recovery_expression = zbx_strdup(NULL, dc_trigger->recovery_expression);
	event->trigger.priority = dc_trigger->priority;
	event->trigger.type = dc_trigger->type;
	event->trigger.correlation_mode = dc_trigger->correlation_mode;
	event->trigger.correlation_tag = zbx_strdup(NULL, dc_trigger->correlation_tag);
	event->trigger.value = dc_trigger->value;
	event->trigger.opdata = zbx_strdup(NULL, dc_trigger->opdata);
	event->trigger.event_name = (NULL != dc_trigger->event_name ? zbx_strdup(NULL,dc_trigger->event_name) : NULL);
	event->name = zbx_strdup(NULL, (NULL != dc_trigger->event_name ? dc_trigger->event_name :
			dc_trigger->description));
	event->trigger.cache = NULL;
	event->trigger.url = NULL;
	event->trigger.url_name = NULL;
	event->trigger.comments = NULL;

	zbx_substitute_macros(&event->trigger.correlation_tag, err, sizeof(err), &zbx_macro_trigger_tag_resolv,
			um_handle, event, NULL);

	zbx_token_search_t	search_token = ZBX_TOKEN_SEARCH_REFERENCES;

	if (EVENT_SOURCE_TRIGGERS == event->source)
		search_token |= ZBX_TOKEN_SEARCH_EXPRESSION_MACRO;

	zbx_substitute_macros_ext_search(search_token, &event->name, err, sizeof(err),
			&zbx_macro_event_name_resolv, um_handle, event, NULL);

	zbx_vector_tags_ptr_create(&event->tags);

	for (int i = 0; i < dc_trigger->tags.values_num; i++)
		process_trigger_tag(um_handle, event, dc_trigger->tags.values[i]);

	get_item_tags_by_expression(&event->trigger, &item_tags);

	for (int i = 0; i < item_tags.values_num; i++)
	{
		process_item_tag(event, item_tags.values[i], um_handle);
		zbx_free_item_tag(item_tags.values[i]);
	}

	zbx_vector_uint64_create(&event->trigger.dep_triggerids);
	zbx_vector_uint64_append_array(&event->trigger.dep_triggerids, dc_trigger->dep_triggerids.values,
			dc_trigger->dep_triggerids.values_num);

	zbx_dc_close_user_macros(um_handle);
	zbx_vector_item_tag_destroy(&item_tags);

	return event;
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
*              trigger_recovery_mode       - [IN]                             *
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
void	zbx_add_event(zbx_db_event *event)
{
	zbx_vector_db_event_append(&events, event);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes the data structures required for event processing     *
 *                                                                            *
 ******************************************************************************/
void	zbx_initialize_events(void)
{
	zbx_vector_db_event_create(&events);
}

/******************************************************************************
 *                                                                            *
 * Purpose: uninitializes the data structures required for event processing   *
 *                                                                            *
 ******************************************************************************/
void	zbx_uninitialize_events(void)
{
	zbx_vector_db_event_destroy(&events);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans all events and events recoveries                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_clean_events(void)
{
	zbx_vector_db_event_clear_ext(&events, zbx_db_free_event);
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
void	zbx_export_events(zbx_dbconn_t *db, const zbx_vector_db_event_t *problems,
		const zbx_vector_db_event_recovery_t *recovery, zbx_export_file_t *problem_export,
		zbx_vector_connector_filter_t *connector_filters, unsigned char **data, size_t *data_alloc,
		size_t *data_offset)
{
	int			i;
	struct zbx_json		json;
	size_t			sql_alloc = 256, sql_offset;
	char			*sql = NULL;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_hashset_t		hosts;
	zbx_vector_uint64_t	hostids;
	zbx_hashset_iter_t	iter;
	zbx_connector_object_t	connector_object;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)events.values_num);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	sql = (char *)zbx_malloc(sql, sql_alloc);
	zbx_hashset_create(&hosts, events.values_num, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&connector_object.ids);

	for (i = 0; i < problems->values_num; i++)
	{
		zbx_dc_host_t		*host;
		zbx_db_event		*event;
		zbx_vector_str_t	groups;

		event = problems->values[i];

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

			if (0 == connector_object.ids.values_num && NULL == problem_export)
				continue;
		}

		zbx_json_reset(&json);

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

		result = zbx_dbconn_select(db, "%s", sql);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_GROUPS);

		zbx_vector_str_create(&groups);
		while (NULL != (row = zbx_db_fetch(result)))
			zbx_vector_str_append(&groups, zbx_strdup(NULL, row[0]));

		zbx_vector_str_sort(&groups, ZBX_DEFAULT_STR_COMPARE_FUNC);

		for (int j = 0; j < groups.values_num; j++)
			zbx_json_addstring(&json, NULL, groups.values[j], ZBX_JSON_TYPE_STRING);

		zbx_vector_str_clear_ext(&groups, zbx_str_free);
		zbx_vector_str_destroy(&groups);

		zbx_db_free_result(result);

		zbx_json_close(&json);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_TAGS);
		for (int j = 0; j < event->tags.values_num; j++)
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

		if (NULL != problem_export)
			zbx_problems_export_write(problem_export, json.buffer, json.buffer_size);
	}

	for (i = 0; i < recovery->values_num; i++)
	{
		zbx_db_event		*event;
		zbx_vector_uint64_t	*p_eventids;

		event = recovery->values[i].event;
		p_eventids = &recovery->values[i].p_eventids;

		if (EVENT_SOURCE_TRIGGERS != event->source || TRIGGER_VALUE_OK != event->value)
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

			if (0 == connector_object.ids.values_num && NULL == problem_export)
				continue;
		}

		for (int j = 0; j < p_eventids->values_num; j++)
		{
			zbx_json_reset(&json);

			zbx_json_addint64(&json, ZBX_PROTO_TAG_CLOCK, event->clock);
			zbx_json_addint64(&json, ZBX_PROTO_TAG_NS, event->ns);
			zbx_json_addint64(&json, ZBX_PROTO_TAG_VALUE, event->value);
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_EVENTID, event->eventid);
			zbx_json_adduint64(&json, ZBX_PROTO_TAG_PROBLEM_EVENTID, p_eventids->values[j]);

			if (0 != connector_object.ids.values_num)
			{
				connector_object.objectid = event->trigger.triggerid;
				connector_object.ts.sec = event->clock;
				connector_object.ts.ns = event->ns;
				connector_object.str = json.buffer;
				zbx_connector_serialize_object(data, data_alloc, data_offset, &connector_object);
				zbx_vector_uint64_clear(&connector_object.ids);
			}

			if (NULL != problem_export)
				zbx_problems_export_write(problem_export, json.buffer, json.buffer_size);
		}
	}

	if (NULL != problem_export)
		zbx_problems_export_flush(problem_export);

	zbx_vector_uint64_destroy(&connector_object.ids);
	zbx_hashset_destroy(&hosts);
	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);
	zbx_json_free(&json);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds maintenance data to events                                   *
 *                                                                            *
 ******************************************************************************/
static void	add_event_maintenances(zbx_vector_db_event_t *problems, zbx_vector_uint64_t *maintenanceids)
{
	zbx_vector_event_suppress_query_ptr_t	event_queries;
	zbx_event_suppress_query_t		*query;

	/* prepare query data  */

	zbx_vector_event_suppress_query_ptr_create(&event_queries);

	for (int i = 0; i < problems->values_num; i++)
	{
		zbx_db_event	*event = problems->values[i];

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

	/* get maintenance data  */
	if (SUCCEED == zbx_dc_get_event_maintenances(&event_queries, maintenanceids))
	{
		for (int i = 0; i < event_queries.values_num; i++)
		{
			zbx_db_event	*event = (zbx_db_event *)problems->values[i];

			query = event_queries.values[i];
			for (int j = 0; j < query->maintenances.values_num; j++)
			{
				zbx_db_event_add_maintenanceid(event, query->maintenances.values[j].first,
						(int)query->maintenances.values[j].second);
			}
		}
	}

	for (int i = 0; i < event_queries.values_num; i++)
	{
		query = event_queries.values[i];
		/* reset tags vector to avoid double freeing copied tag name/value pointers */
		zbx_vector_tags_ptr_clear(&query->tags);
	}
	zbx_vector_event_suppress_query_ptr_clear_ext(&event_queries, zbx_event_suppress_query_free);
	zbx_vector_event_suppress_query_ptr_destroy(&event_queries);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve running maintenances for each event and saves it in      *
 *          event_suppress table                                              *
 *                                                                            *
 ******************************************************************************/
static void	update_event_maintenances(zbx_vector_db_event_t *cep_events)
{
	zbx_vector_db_event_t	problems;
	zbx_vector_uint64_t	maintenanceids;
	int			i;
	zbx_db_event		*event;

	zbx_vector_uint64_create(&maintenanceids);
	zbx_vector_db_event_create(&problems);
	zbx_vector_db_event_reserve(&problems, events.values_num);

	/* prepare trigger problem event vector */
	for (i = 0; i < cep_events->values_num; i++)
	{
		event = cep_events->values[i];

		if (EVENT_SOURCE_TRIGGERS == event->source && TRIGGER_VALUE_PROBLEM == event->value)
			zbx_vector_db_event_append(&problems, event);
	}

	if (0 != problems.values_num && SUCCEED == zbx_dc_get_running_maintenanceids(&maintenanceids))
		add_event_maintenances(&problems, &maintenanceids);

	zbx_vector_db_event_destroy(&problems);
	zbx_vector_uint64_destroy(&maintenanceids);
}

static void	save_discovery_events(zbx_vector_db_event_t *db_events)
{
	zbx_db_insert_t		db_insert;
	zbx_uint64_t		eventid;
	zbx_db_event		*event;


	eventid = zbx_db_get_maxid_num("events", db_events->values_num);

	zbx_db_insert_prepare(&db_insert, "events", "eventid", "source", "object", "objectid", "clock", "ns", "value",
			(char *)NULL);

	for (int i = 0; i < db_events->values_num; i++)
	{
		event = db_events->values[i];

		event->eventid = eventid++;

		zbx_db_insert_add_values(&db_insert, event->eventid, event->source, event->object,
				event->objectid, event->clock, event->ns, event->value);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	process_actions(zbx_db_dbconn(), db_events, NULL, NULL);
}

int	zbx_process_events(void)
{
	int	processed_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)events.values_num);

	if (0 != events.values_num)
	{
		zbx_vector_db_event_t	cep_events, discovery_events;

		zbx_vector_db_event_create(&cep_events);
		zbx_vector_db_event_create(&discovery_events);

		zbx_vector_db_event_reserve(&cep_events, (size_t)events.values_num);
		zbx_vector_db_event_reserve(&cep_events, (size_t)discovery_events.values_num);

		for (int i = 0; i < events.values_num; i++)
		{
			zbx_db_event	*event = events.values[i];

			switch (event->source)
			{
				case EVENT_SOURCE_AUTOREGISTRATION:
				case EVENT_SOURCE_DISCOVERY:
					zbx_vector_db_event_append(&discovery_events, event);
					break;
				case EVENT_SOURCE_INTERNAL:
				case EVENT_SOURCE_TRIGGERS:
					zbx_vector_db_event_append(&cep_events, event);
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported event source %d", event->source);
					break;
			}
		}

		if (0 != cep_events.values_num)
		{
			update_event_maintenances(&cep_events);
			processed_num = zbx_cep_send_events(cep_events.values, cep_events.values_num);
		}

		if (0 != discovery_events.values_num)
		{
			save_discovery_events(&discovery_events);
			processed_num += discovery_events.values_num;
		}

		zbx_vector_db_event_destroy(&discovery_events);
		zbx_vector_db_event_destroy(&cep_events);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: closes problem event                                              *
 *                                                                            *
 * Parameters: triggerid - [IN] source trigger id                             *
 *             eventid   - [IN] event to close                                *
 *             userid    - [IN] user closing the event                        *
 *                                                                            *
 * Return value: SUCCEED - the problem was closed                             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_close_problem(zbx_uint64_t triggerid, zbx_uint64_t eventid, zbx_uint64_t userid)
{
	zbx_dc_trigger_t		trigger;
	int				errcode, ret = FAIL;
	zbx_timespec_t			ts;
	zbx_cep_assessment_query_t	query;
	unsigned char			*results = NULL;
	zbx_db_event			*event;

	query.triggerid = triggerid;
	query.flags = TRIGGER_VALUE_OK;
	zbx_vector_uint64_create(&query.dep_triggerids);

	zbx_dc_config_get_triggers_by_triggerids(&trigger, &triggerid, &errcode, 1);
	if (SUCCEED != errcode)
		goto out;

	zbx_cep_assess_trigger_events(&query, 1, &results);

	if (CEP_EVENT_DENY == results[0])
		goto out;

	zbx_timespec(&ts);
	event = zbx_create_trigger_event(&trigger, ts.sec, ts.ns, TRIGGER_VALUE_OK);
	zbx_cep_close_problem_by_user(event, eventid, userid);
	zbx_db_free_event(event);

	if (TRIGGER_STATE_UNKNOWN == trigger.state)
	{
		int	tnx_err;

		do
		{
			zbx_db_begin();
			zbx_db_execute("update trigger_rtdata set state=%d,error='' where triggerid=" ZBX_FS_UI64,
					TRIGGER_STATE_NORMAL, triggerid);
		}
		while (ZBX_DB_DOWN == (tnx_err = zbx_db_commit()));

		if (ZBX_DB_OK == tnx_err)
		{
			zbx_trigger_diff_t	diff = {0}, *pdiff = &diff;

			diff.triggerid = triggerid;
			diff.flags = ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE | ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR;
			diff.state = TRIGGER_STATE_NORMAL;
			diff.error = "";

			zbx_dc_config_triggers_apply_changes(&pdiff, 1);
		}
	}

	ret = SUCCEED;
out:
	zbx_dc_config_clean_triggers(&trigger, &errcode, 1);
	zbx_vector_uint64_destroy(&query.dep_triggerids);
	zbx_free(results);

	return ret;
}
