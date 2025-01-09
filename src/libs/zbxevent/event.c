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

#include "zbx_expression_constants.h"
#include "zbx_trigger_constants.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"
#include "zbxjson.h"
#include "zbxstr.h"
#include "zbxtime.h"

ZBX_VECTOR_IMPL(eventdata, zbx_eventdata_t)

/******************************************************************************
 *                                                                            *
 * Purpose: free memory allocated for temporary event data                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_eventdata_free(zbx_eventdata_t *eventdata)
{
	zbx_free(eventdata->host);
	zbx_free(eventdata->severity);
	zbx_free(eventdata->tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare events to sort by highest severity and host name          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eventdata_compare(const zbx_eventdata_t *d1, const zbx_eventdata_t *d2)
{
	ZBX_RETURN_IF_NOT_EQUAL(d2->nseverity, d1->nseverity);

	return strcmp(d1->host, d2->host);
}

/******************************************************************************
 *                                                                            *
 * Purpose: build string from event data                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_eventdata_to_str(const zbx_vector_eventdata_t *eventdata, char **replace_to)
{
	int		i;
	const char	*d = "";

	if (0 == eventdata->values_num)
		return FAIL;

	for (i = 0; i < eventdata->values_num; i++)
	{
		zbx_eventdata_t	*e = &eventdata->values[i];

		*replace_to = zbx_strdcatf(*replace_to, "%sHost: \"%s\" Problem name: \"%s\" Severity: \"%s\" Age: %s"
				" Problem tags: \"%s\"", d, e->host, e->name, e->severity,
				zbx_age2str(time(NULL) - e->clock), e->tags);
		d = "\n";
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: format event tags string in format <tag1>[:<value1>], ...         *
 *                                                                            *
 * Parameters: event        [IN] the event                                    *
 *             replace_to - [OUT] replacement string                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_event_get_str_tags(const zbx_db_event *event, char **replace_to)
{
	size_t			replace_to_offset = 0, replace_to_alloc = 0;
	int			i;
	zbx_vector_tags_ptr_t	tags;

	if (0 == event->tags.values_num)
	{
		*replace_to = zbx_strdup(*replace_to, "");
		return;
	}

	zbx_free(*replace_to);

	/* copy tags to temporary vector for sorting */

	zbx_vector_tags_ptr_create(&tags);
	zbx_vector_tags_ptr_append_array(&tags, event->tags.values, event->tags.values_num);
	zbx_vector_tags_ptr_sort(&tags, zbx_compare_tags_natural);

	for (i = 0; i < tags.values_num; i++)
	{
		const zbx_tag_t	*tag = tags.values[i];

		if (0 != i)
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ", ");

		zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, tag->tag);

		if ('\0' != *tag->value)
		{
			zbx_chrcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, ':');
			zbx_strcpy_alloc(replace_to, &replace_to_alloc, &replace_to_offset, tag->value);
		}
	}

	zbx_vector_tags_ptr_destroy(&tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: format event tags string in JSON format                           *
 *                                                                            *
 * Parameters: event        [IN] the event                                    *
 *             replace_to - [OUT] replacement string                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_event_get_json_tags(const zbx_db_event *event, char **replace_to)
{
	struct zbx_json	json;
	int		i;

	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < event->tags.values_num; i++)
	{
		const zbx_tag_t	*tag = event->tags.values[i];

		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, "tag", tag->tag, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "value", tag->value, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);
	}

	zbx_json_close(&json);
	*replace_to = zbx_strdup(*replace_to, json.buffer);
	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: format event actions in JSON format                               *
 *                                                                            *
 * Parameters: ack        - [IN] problem update data                          *
 *             replace_to - [OUT]                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_event_get_json_actions(const zbx_db_acknowledge *ack, char **replace_to)
{
	struct zbx_json	json;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	if (0 != (ack->action & ZBX_PROBLEM_UPDATE_ACKNOWLEDGE))
		zbx_json_addstring(&json, ZBX_PROTO_TAG_ACKNOWLEDGE, ZBX_PROTO_VALUE_TRUE, ZBX_JSON_TYPE_TRUE);

	if (0 != (ack->action & ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE))
		zbx_json_addstring(&json, ZBX_PROTO_TAG_UNACKNOWLEDGE, ZBX_PROTO_VALUE_TRUE, ZBX_JSON_TYPE_TRUE);

	if (0 != (ack->action & ZBX_PROBLEM_UPDATE_MESSAGE))
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_MESSAGE, ack->message, ZBX_JSON_TYPE_STRING);
	}

	if (0 != (ack->action & ZBX_PROBLEM_UPDATE_SEVERITY))
	{
		zbx_json_addobject(&json, ZBX_PROTO_TAG_SEVERITY);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_OLD, (zbx_uint64_t)ack->old_severity);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_NEW, (zbx_uint64_t)ack->new_severity);
		zbx_json_close(&json);
	}

	if (0 != (ack->action & ZBX_PROBLEM_UPDATE_CLOSE))
		zbx_json_addstring(&json, ZBX_PROTO_TAG_CLOSE, ZBX_PROTO_VALUE_TRUE, ZBX_JSON_TYPE_TRUE);

	if (0 != (ack->action & ZBX_PROBLEM_UPDATE_SUPPRESS))
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_SUPPRESS_UNTIL, ack->suppress_until);

	if (0 != (ack->action & ZBX_PROBLEM_UPDATE_UNSUPPRESS))
		zbx_json_addstring(&json, ZBX_PROTO_TAG_UNSUPPRESS, ZBX_PROTO_VALUE_TRUE, ZBX_JSON_TYPE_TRUE);

	if (0 != (ack->action & ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE))
		zbx_json_addstring(&json, ZBX_PROTO_TAG_CAUSE, ZBX_PROTO_VALUE_TRUE, ZBX_JSON_TYPE_TRUE);

	if (0 != (ack->action & ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM))
		zbx_json_addstring(&json, ZBX_PROTO_TAG_SYMPTOM, ZBX_PROTO_VALUE_TRUE, ZBX_JSON_TYPE_TRUE);

	zbx_json_adduint64(&json, ZBX_PROTO_TAG_TIMESTAMP, (zbx_uint64_t)ack->clock);

	zbx_json_close(&json);
	*replace_to = zbx_strdup(*replace_to, json.buffer);
	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get event tag value by name                                       *
 *                                                                            *
 * Parameters: macro      - [IN] the macro                                    *
 *             event      - [IN] event                                        *
 *             replace_to - [OUT] replacement string                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_event_get_tag(const char *text, const zbx_db_event *event, char **replace_to)
{
	char	*name;
	int	ret = FAIL;

	if (SUCCEED == zbx_str_extract(text, strlen(text) - 1, &name))
	{
		if (0 < event->tags.values_num)
		{
			int			i;
			zbx_tag_t		*tag;
			zbx_vector_tags_ptr_t	tags;

			zbx_vector_tags_ptr_create(&tags);
			zbx_vector_tags_ptr_append_array(&tags, event->tags.values, event->tags.values_num);
			zbx_vector_tags_ptr_sort(&tags, zbx_compare_tags_natural);

			for (i = 0; i < tags.values_num; i++)
			{
				tag = tags.values[i];

				if (0 == strcmp(name, tag->tag))
				{
					*replace_to = zbx_strdup(*replace_to, tag->value);
					ret = SUCCEED;
					break;
				}
			}

			zbx_vector_tags_ptr_destroy(&tags);
		}

		zbx_free(name);
	}

	if (FAIL == ret)
		*replace_to = zbx_strdup(*replace_to, STR_UNKNOWN_VARIABLE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: request event value by macro                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_event_get_macro_value(const char *macro, const zbx_db_event *event, char **replace_to,
			const zbx_uint64_t *recipient_userid, const zbx_db_event *r_event, const char *tz)
{
	if (0 == strcmp(macro, MVAR_EVENT_AGE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_DATE))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_date2str(event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_DURATION))
	{
		if (NULL == r_event)
			*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - event->clock));
		else
			*replace_to = zbx_strdup(*replace_to, zbx_age2str(r_event->clock - event->clock));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_ID))
	{
		*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, event->eventid);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_TIME))
	{
		*replace_to = zbx_strdup(*replace_to, zbx_time2str(event->clock, tz));
	}
	else if (0 == strcmp(macro, MVAR_EVENT_TIMESTAMP))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", event->clock);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_SOURCE))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", event->source);
	}
	else if (0 == strcmp(macro, MVAR_EVENT_OBJECT))
	{
		*replace_to = zbx_dsprintf(*replace_to, "%d", event->object);
	}
	else if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		if (0 == strcmp(macro, MVAR_EVENT_ACK_HISTORY) || 0 == strcmp(macro, MVAR_EVENT_UPDATE_HISTORY))
		{
			zbx_event_db_get_history(event, replace_to, recipient_userid, tz);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_ACK_STATUS))
		{
			*replace_to = zbx_strdup(*replace_to, event->acknowledged ? "Yes" : "No");
		}
		else if (0 == strcmp(macro, MVAR_EVENT_NSEVERITY))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%d", (int)event->severity);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_SEVERITY))
		{
			if (FAIL == zbx_config_get_trigger_severity_name(event->severity, replace_to))
				*replace_to = zbx_strdup(*replace_to, "unknown");
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGS))
		{
			zbx_event_get_str_tags(event, replace_to);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGSJSON))
		{
			zbx_event_get_json_tags(event, replace_to);
		}
		else if (0 == strncmp(macro, MVAR_EVENT_TAGS_PREFIX, ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX)))
		{
			zbx_event_get_tag(macro + ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX), event, replace_to);
		}
	}
	else if (EVENT_SOURCE_INTERNAL == event->source)
	{
		if (0 == strcmp(macro, MVAR_EVENT_TAGS))
		{
			zbx_event_get_str_tags(event, replace_to);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGSJSON))
		{
			zbx_event_get_json_tags(event, replace_to);
		}
		else if (0 == strncmp(macro, MVAR_EVENT_TAGS_PREFIX, ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX)))
		{
			zbx_event_get_tag(macro + ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX), event, replace_to);
		}
	}
	else if (EVENT_SOURCE_SERVICE == event->source)
	{
		if (0 == strcmp(macro, MVAR_EVENT_NSEVERITY))
		{
			*replace_to = zbx_dsprintf(*replace_to, "%d", (int)event->severity);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_SEVERITY))
		{
			if (FAIL == zbx_config_get_trigger_severity_name(event->severity, replace_to))
				*replace_to = zbx_strdup(*replace_to, "unknown");
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGS))
		{
			zbx_event_get_str_tags(event, replace_to);
		}
		else if (0 == strcmp(macro, MVAR_EVENT_TAGSJSON))
		{
			zbx_event_get_json_tags(event, replace_to);
		}
		else if (0 == strncmp(macro, MVAR_EVENT_TAGS_PREFIX, ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX)))
		{
			zbx_event_get_tag(macro + ZBX_CONST_STRLEN(MVAR_EVENT_TAGS_PREFIX), event, replace_to);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get human readable list of problem update actions                 *
 *                                                                            *
 * Parameters: ack     - [IN] problem update data                             *
 *             actions - [IN] the required action flags                       *
 *             out     - [OUT] the output buffer                              *
 *                                                                            *
 * Return value: SUCCEED - successfully returned list of problem update       *
 *               FAIL    - no matching actions were made                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_problem_get_actions(const zbx_db_acknowledge *ack, int actions, const char *tz, char **out)
{
	const char	*prefixes[] = {"", ", ", ", ", ", ", ", ", ", ", ", ", ", ", ", "};
	char		*buf = NULL;
	size_t		buf_alloc = 0, buf_offset = 0;
	int		i, index, flags;

	if (0 == (flags = ack->action & actions))
		return FAIL;

	for (i = 0, index = 0; i < ZBX_PROBLEM_UPDATE_ACTION_COUNT; i++)
	{
		if (0 != (flags & (1 << i)))
			index++;
	}

	if (1 < index)
		prefixes[index - 1] = " and ";

	index = 0;

	if (0 != (flags & ZBX_PROBLEM_UPDATE_ACKNOWLEDGE))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "acknowledged");
		index++;
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "unacknowledged");
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_MESSAGE))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "commented");
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_SEVERITY))
	{
		zbx_config_t	cfg;
		const char	*from = "unknown", *to = "unknown";

		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SEVERITY_NAME);

		if (TRIGGER_SEVERITY_COUNT > ack->old_severity && 0 <= ack->old_severity)
			from = cfg.severity_name[ack->old_severity];

		if (TRIGGER_SEVERITY_COUNT > ack->new_severity && 0 <= ack->new_severity)
			to = cfg.severity_name[ack->new_severity];

		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "changed severity from %s to %s",
				from, to);

		zbx_config_clean(&cfg);
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_CLOSE))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "closed");
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_SUPPRESS))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "suppressed ");
		if (0 == ack->suppress_until)
		{
			zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "indefinitely");
		}
		else
		{
			zbx_snprintf_alloc(&buf, &buf_alloc, &buf_offset, "until %s %s",
					zbx_date2str((time_t)ack->suppress_until, tz),
					zbx_time2str((time_t)ack->suppress_until, tz));
		}
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_UNSUPPRESS))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "unsuppressed");
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index++]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "ranked as symptom");
	}

	if (0 != (flags & ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE))
	{
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, prefixes[index]);
		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, "ranked as cause");
	}

	zbx_free(*out);
	*out = buf;

	return SUCCEED;
}
