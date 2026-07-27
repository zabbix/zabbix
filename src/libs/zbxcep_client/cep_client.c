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

#include "zbx_cep_client.h"

#include "zbx_trigger_constants.h"
#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxserialize.h"
#include "zbxipcservice.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"

ZBX_VECTOR_IMPL(cep_assessment_query, zbx_cep_assessment_query_t)
ZBX_VECTOR_IMPL(event_maintenance, zbx_event_maintenance_t)
ZBX_VECTOR_IMPL(event_severity, zbx_event_severity_t)

/******************************************************************************
 *                                                                            *
 * Purpose: serialize a vector of 64-bit IDs into a newly allocated buffer    *
 *                                                                            *
 * Parameters: ids   - [IN]  vector of 64-bit identifiers to serialize        *
 *             data  - [OUT] pointer to the allocated serialized buffer       *
 *                                                                            *
 * Return value: size of the serialized buffer in bytes                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_cep_serialize_ids(const zbx_vector_uint64_t *ids, unsigned char **data)
{
	unsigned char	*ptr;
	zbx_uint32_t	size;

	size = sizeof(ids->values_num) + ids->values_num * sizeof(zbx_uint64_t);
	ptr = *data = (unsigned char *)zbx_malloc(NULL, size);

	ptr += zbx_serialize_value(ptr, ids->values_num);
	for (int i = 0; i < ids->values_num; i++)
		ptr += zbx_serialize_value(ptr, ids->values[i]);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize CEP assessment queries from a serialized buffer       *
 *                                                                            *
 * Parameters: data    - [IN]  pointer to serialized CEP queries data         *
 *             queries - [OUT] vector of deserialized CEP assessment queries  *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_deserialize_event_queries(const unsigned char *data, zbx_vector_cep_assessment_query_t *queries)
{
	int			queries_num;
	const unsigned char	*ptr = data;

	ptr += zbx_deserialize_value(ptr, &queries_num);
	zbx_vector_cep_assessment_query_reserve(queries, queries_num);

	for (int i = 0; i < queries_num; i++)
	{
		zbx_cep_assessment_query_t	query;

		ptr += zbx_deserialize_value(ptr, &query.triggerid);
		ptr += zbx_deserialize_value(ptr, &query.flags);

		if (0 != (query.flags & CEP_QUERY_FLAG_DEPS))
		{
			int	depids_num;

			ptr += zbx_deserialize_value(ptr, &depids_num);
			zbx_vector_uint64_create(&query.dep_triggerids);
			zbx_vector_uint64_reserve(&query.dep_triggerids, (size_t)depids_num);

			for (int j = 0; j < depids_num; j++)
			{
				zbx_uint64_t	triggerid;

				ptr += zbx_deserialize_value(ptr, &triggerid);
				zbx_vector_uint64_append(&query.dep_triggerids, triggerid);
			}
		}

		zbx_vector_cep_assessment_query_append(queries, query);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: peek into serialized CEP event queries and return their count     *
 *                                                                            *
 * Parameters: data - [IN]  pointer to serialized CEP event queries data      *
 *                                                                            *
 * Return value: number of CEP event queries in the serialized buffer         *
 *                                                                            *
 ******************************************************************************/
int	zbx_cep_peek_event_queries(const unsigned char *data)
{
	int	queries_num;

	(void)zbx_deserialize_value(data, &queries_num);

	return queries_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear CEP assessment query structure                              *
 *                                                                            *
 * Parameters: query - [IN/OUT] CEP assessment query to clear                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_assessment_query_clear(zbx_cep_assessment_query_t *query)
{
	if (0 != (query->flags & CEP_QUERY_FLAG_DEPS))
		zbx_vector_uint64_destroy(&query->dep_triggerids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: return a connected IPC socket to the CEP service                  *
 *                                                                            *
 * Return value: pointer to a thread-local CEP IPC socket                     *
 *                                                                            *
 * Comments: Establishes the connection to the CEP service on first use and   *
 *           reuses the same socket for subsequent calls.                     *
 *                                                                            *
 ******************************************************************************/
static	zbx_ipc_socket_t	*cep_client_socket(void)
{
	static ZBX_THREAD_LOCAL zbx_ipc_socket_t	socket;

	if (FAIL == zbx_ipc_socket_connected(&socket))
	{
		char	*error = NULL;

		if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_CEP, SEC_PER_MIN, &error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot connect to CEP service: %s", error);
			zbx_exit(EXIT_FAILURE);
		}
	}

	return &socket;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate whether CEP triggers must generate events based on their *
 *          current state                                                     *
 *                                                                            *
 * Parameters: queries     - [IN]  array of CEP assessment queries            *
 *             queries_num - [IN]  number of queries in the array             *
 *             results     - [OUT] pointer to an array of trigger assessment  *
 *                                  results                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_assess_trigger_events(const zbx_cep_assessment_query_t *queries, int queries_num,
		unsigned char **results)
{
	unsigned char	*data, *ptr;
	zbx_uint32_t	data_len = sizeof(queries_num) + queries_num * (sizeof(zbx_uint64_t) + 1);

	for (int i = 0; i < queries_num; i++)
	{
		if (0 == queries[i].dep_triggerids.values_num)
			continue;

		data_len += sizeof(int) + queries[i].dep_triggerids.values_num * sizeof(zbx_uint64_t);
	}

	ptr = data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr += zbx_serialize_value(ptr, queries_num);

	for (int i = 0; i < queries_num; i++)
	{
		const zbx_cep_assessment_query_t	*query = &queries[i];
		unsigned char			flags = query->flags;

		if (0 != query->dep_triggerids.values_num)
			flags |= CEP_QUERY_FLAG_DEPS;

		ptr += zbx_serialize_value(ptr, query->triggerid);
		ptr += zbx_serialize_char(ptr, flags);

		if (0 != query->dep_triggerids.values_num)
		{
			ptr += zbx_serialize_value(ptr, query->dep_triggerids.values_num);
			for (int j = 0; j < query->dep_triggerids.values_num; j++)
				ptr += zbx_serialize_value(ptr, query->dep_triggerids.values[j]);
		}
	}

	int	ret = zbx_ipc_socket_write(cep_client_socket(), ZBX_CEP_ASSESS_TRIGGER_EVENTS, data, data_len);

	zbx_free(data);
	if (SUCCEED != ret)
	{
		zbx_free(data);
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_ipc_message_t	response = {0};

	if (FAIL == zbx_ipc_socket_read(cep_client_socket(), &response))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot receive data from CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	*results = response.data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: ensure that a buffer has enough allocated space for new data      *
 *                                                                            *
 * Parameters: data        - [IN/OUT] pointer to the buffer                   *
 *             data_alloc  - [IN/OUT] pointer to the current buffer capacity  *
 *             data_offset - [IN]    current data offset in the buffer        *
 *             data_len    - [IN]    length of new data to be appended        *
 *                                                                            *
 * Comments: Reallocates the buffer and increases its capacity if the current *
 *           space is insufficient for the requested data length.             *
 *                                                                            *
 ******************************************************************************/
static void	cep_buffer_reserve(unsigned char **data, zbx_uint32_t *data_alloc, zbx_uint32_t data_offset,
		zbx_uint32_t data_len)
{
	if (data_offset + data_len > *data_alloc)
	{
		do
		{
			*data_alloc = *data_alloc / 2 * 3;
		}
		while (data_offset + data_len > *data_alloc);

		*data = (unsigned char *)zbx_realloc(*data, *data_alloc);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize an event into a dynamic buffer                          *
 *                                                                            *
 * Parameters: data        - [IN/OUT] pointer to the buffer                   *
 *             data_alloc  - [IN/OUT] pointer to the current buffer capacity  *
 *             data_offset - [IN/OUT] pointer to the current write offset     *
 *             event       - [IN]    event to serialize                       *
 *                                                                            *
 ******************************************************************************/
static void	cep_buffer_serialize_event(unsigned char **data, zbx_uint32_t *data_alloc, zbx_uint32_t *data_offset,
		const zbx_db_event *event)
{
	zbx_uint32_t		data_len = 0, name_len, tags_local_len[32], *tags_len, expr_len, r_expr_len,
				corr_tag_len;
	int			maintenances_num;
	zbx_db_event_suppress_t	sup_local;

	zbx_serialize_prepare_value(data_len, event->eventid);
	zbx_serialize_prepare_value(data_len, event->source);
	zbx_serialize_prepare_value(data_len, event->object);
	zbx_serialize_prepare_value(data_len, event->objectid);
	zbx_serialize_prepare_str_len(data_len, event->name, name_len);
	zbx_serialize_prepare_value(data_len, event->clock);
	zbx_serialize_prepare_value(data_len, event->ns);
	zbx_serialize_prepare_value(data_len, event->value);
	zbx_serialize_prepare_value(data_len, event->severity);

	zbx_serialize_prepare_value(data_len, event->tags.values_num);

	if ((size_t)event->tags.values_num * 2 > ARRSIZE(tags_local_len))
		tags_len = (zbx_uint32_t *)zbx_malloc(NULL, (size_t)event->tags.values_num * 2 * sizeof(zbx_uint32_t));
	else
		tags_len = tags_local_len;

	for (int i = 0; i < event->tags.values_num; i++)
	{
		zbx_serialize_prepare_str_len(data_len, event->tags.values[i]->tag, tags_len[i * 2]);
		zbx_serialize_prepare_str_len(data_len, event->tags.values[i]->value, tags_len[i * 2 + 1]);
	}

	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		zbx_serialize_prepare_value(data_len, event->trigger.type);
		zbx_serialize_prepare_value(data_len, event->trigger.priority);
		zbx_serialize_prepare_value(data_len, event->trigger.recovery_mode);
		zbx_serialize_prepare_value(data_len, event->trigger.correlation_mode);
		zbx_serialize_prepare_str_len(data_len, event->trigger.expression, expr_len);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == event->trigger.recovery_mode)
			zbx_serialize_prepare_str_len(data_len, event->trigger.recovery_expression, r_expr_len);

		if (ZBX_TRIGGER_CORRELATION_TAG == event->trigger.correlation_mode)
			zbx_serialize_prepare_str_len(data_len, event->trigger.correlation_tag, corr_tag_len);

		zbx_serialize_prepare_value(data_len, event->trigger.dep_triggerids.values_num);
		data_len += sizeof(zbx_uint64_t) * event->trigger.dep_triggerids.values_num;

		data_len += sizeof(int);
		if (NULL != event->suppress)
		{
			maintenances_num = event->suppress->values_num;
			data_len += (sizeof(sup_local.maintenanceid) + sizeof(sup_local.until)) * maintenances_num;
		}
		else
			maintenances_num = 0;
	}

	cep_buffer_reserve(data, data_alloc, *data_offset, data_len);

	unsigned char	*ptr = *data + *data_offset;

	ptr += zbx_serialize_value(ptr, event->eventid);
	ptr += zbx_serialize_value(ptr, event->source);
	ptr += zbx_serialize_value(ptr, event->object);
	ptr += zbx_serialize_value(ptr, event->objectid);
	ptr += zbx_serialize_str(ptr, event->name, name_len);
	ptr += zbx_serialize_value(ptr, event->clock);
	ptr += zbx_serialize_value(ptr, event->ns);
	ptr += zbx_serialize_value(ptr, event->value);
	ptr += zbx_serialize_value(ptr, event->severity);

	ptr += zbx_serialize_value(ptr, event->tags.values_num);
	for (int i = 0; i < event->tags.values_num; i++)
	{
		ptr += zbx_serialize_str(ptr,event->tags.values[i]->tag, tags_len[i * 2]);
		ptr += zbx_serialize_str(ptr, event->tags.values[i]->value, tags_len[i * 2 + 1]);
	}

	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		ptr += zbx_serialize_value(ptr, event->trigger.type);
		ptr += zbx_serialize_value(ptr, event->trigger.priority);
		ptr += zbx_serialize_value(ptr, event->trigger.recovery_mode);
		ptr += zbx_serialize_value(ptr, event->trigger.correlation_mode);
		ptr += zbx_serialize_str(ptr, event->trigger.expression, expr_len);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == event->trigger.recovery_mode)
			ptr += zbx_serialize_str(ptr, event->trigger.recovery_expression, r_expr_len);

		if (ZBX_TRIGGER_CORRELATION_TAG == event->trigger.correlation_mode)
			ptr += zbx_serialize_str(ptr, event->trigger.correlation_tag, corr_tag_len);

		ptr += zbx_serialize_value(ptr, event->trigger.dep_triggerids.values_num);
		for (int i = 0; i < event->trigger.dep_triggerids.values_num; i++)
			ptr += zbx_serialize_value(ptr, event->trigger.dep_triggerids.values[i]);

		ptr += zbx_serialize_value(ptr, maintenances_num);
		if (NULL != event->suppress)
		{
			for (int i = 0; i < event->suppress->values_num; i++)
			{
				ptr += zbx_serialize_value(ptr, event->suppress->values[i].maintenanceid);
				ptr += zbx_serialize_value(ptr, event->suppress->values[i].until);
			}
		}
	}

	if (tags_len != tags_local_len)
		zbx_free(tags_len);

	*data_offset += data_len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: send a batch of events to the CEP service                         *
 *                                                                            *
 * Parameters: events     - [IN]  array of pointers to events                 *
 *             events_num - [IN]  number of events in the array               *
 *                                                                            *
 ******************************************************************************/
int	zbx_cep_send_events(zbx_db_event * const *events, int events_num)
{
	unsigned char	*data;
	zbx_uint32_t	data_alloc = 4096, data_offset = 0;

	data = (unsigned char *)zbx_malloc(NULL, data_alloc);
	data_offset = zbx_serialize_value(data, events_num);

	for (int i = 0; i < events_num; i++)
		cep_buffer_serialize_event(&data, &data_alloc, &data_offset, events[i]);

	if (FAIL == zbx_ipc_socket_write(cep_client_socket(), ZBX_CEP_ADD_EVENTS, data, data_offset))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send events message to CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_ipc_message_t	response = {0};

	if (FAIL == zbx_ipc_socket_read(cep_client_socket(), &response))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot receive events message response from CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	(void)zbx_deserialize_value(response.data, &events_num);

	zbx_ipc_message_clean(&response);

	zbx_free(data);

	return events_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize a single event from a buffer                          *
 *                                                                            *
 * Parameters: data  - [IN]  pointer to serialized event data                 *
 *             event - [OUT] pointer to the deserialized event structure      *
 *                                                                            *
 * Return value: number of bytes consumed from the buffer                     *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	cep_deserialize_event(const unsigned char *data, zbx_db_event **event)
{
	const unsigned char	*ptr = data;
	zbx_db_event		*e;
	zbx_uint32_t		len;
	int			tags_num;

	e = *event = (zbx_db_event *)zbx_malloc(NULL, sizeof(zbx_db_event));
	memset(e, 0, sizeof(zbx_db_event));
	zbx_vector_uint64_create(&e->trigger.dep_triggerids);

	ptr += zbx_deserialize_value(ptr, &e->eventid);
	ptr += zbx_deserialize_value(ptr, &e->source);
	ptr += zbx_deserialize_value(ptr, &e->object);
	ptr += zbx_deserialize_value(ptr, &e->objectid);
	ptr += zbx_deserialize_str(ptr, &e->name, len);
	ptr += zbx_deserialize_value(ptr, &e->clock);
	ptr += zbx_deserialize_value(ptr, &e->ns);
	ptr += zbx_deserialize_value(ptr, &e->value);
	ptr += zbx_deserialize_value(ptr, &e->severity);

	ptr += zbx_deserialize_value(ptr, &tags_num);

	zbx_vector_tags_ptr_create(&e->tags);
	zbx_vector_tags_ptr_reserve(&e->tags, (size_t)tags_num);

	for (int i = 0; i < tags_num; i++)
	{
		zbx_tag_t	*tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));

		ptr += zbx_deserialize_str(ptr, &tag->tag, len);
		ptr += zbx_deserialize_str(ptr, &tag->value, len);
		zbx_vector_tags_ptr_append(&e->tags, tag);
	}

	if (EVENT_SOURCE_TRIGGERS == e->source)
	{
		int			deps_num, maintenances_num;
		zbx_db_event_suppress_t	suppress_local = {0};

		e->trigger.triggerid = e->objectid;

		ptr += zbx_deserialize_value(ptr, &e->trigger.type);
		ptr += zbx_deserialize_value(ptr, &e->trigger.priority);
		ptr += zbx_deserialize_value(ptr, &e->trigger.recovery_mode);
		ptr += zbx_deserialize_value(ptr, &e->trigger.correlation_mode);
		ptr += zbx_deserialize_str(ptr, &e->trigger.expression, len);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == e->trigger.recovery_mode)
			ptr += zbx_deserialize_str(ptr, &e->trigger.recovery_expression, len);

		if (ZBX_TRIGGER_CORRELATION_TAG == e->trigger.correlation_mode)
			ptr += zbx_deserialize_str(ptr, &e->trigger.correlation_tag, len);

		ptr += zbx_deserialize_value(ptr, &deps_num);
		if (0 != deps_num)
		{
			zbx_uint64_t	triggerid;

			zbx_vector_uint64_reserve(&e->trigger.dep_triggerids, (size_t)deps_num);
			for (int i = 0; i < deps_num; i++)
			{
				ptr += zbx_deserialize_value(ptr, &triggerid);
				zbx_vector_uint64_append(&e->trigger.dep_triggerids, triggerid);
			}
		}

		ptr += zbx_deserialize_value(ptr, &maintenances_num);
		if (0 != maintenances_num)
		{
			e->suppress = zbx_create_event_suppress(maintenances_num);

			for (int i = 0; i < maintenances_num; i++)
			{
				ptr += zbx_deserialize_value(ptr, &suppress_local.maintenanceid);
				ptr += zbx_deserialize_value(ptr, &suppress_local.until);
				zbx_vector_db_event_suppress_append(e->suppress, suppress_local);
			}
		}
	}

	return ptr - data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize a batch of events from a buffer into a vector         *
 *                                                                            *
 * Parameters: data   - [IN]  pointer to serialized events data               *
 *             events - [OUT] vector to store deserialized events             *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_deserialize_events(const unsigned char *data, zbx_vector_db_event_t *events)
{
	int	events_num;

	data += zbx_deserialize_value(data, &events_num);
	zbx_vector_db_event_reserve(events, events_num);

	for (int i = 0; i < events_num; i++)
	{
		zbx_db_event	*event;

		data += cep_deserialize_event(data, &event);
		zbx_vector_db_event_append(events, event);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: send a user close problem event request to the CEP service        *
 *                                                                            *
 * Parameters: event   - [IN]  closing OK event                               *
 *             eventid - [IN]  identifier of the problem event to be closed   *
 *             userid  - [IN]  identifier of the user closing the problem     *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_close_problem_by_user(const zbx_db_event *event, zbx_uint64_t eventid, zbx_uint64_t userid)
{
	unsigned char	*data;
	zbx_uint32_t	data_alloc = 256, data_offset = 0;

	data = (unsigned char *)zbx_malloc(NULL, data_alloc);

	cep_buffer_serialize_event(&data, &data_alloc, &data_offset, event);
	cep_buffer_reserve(&data, &data_alloc, data_offset, sizeof(zbx_uint64_t) * 2);
	data_offset += zbx_serialize_value(data + data_offset, eventid);
	data_offset += zbx_serialize_value(data + data_offset, userid);

	if (FAIL == zbx_ipc_socket_write(cep_client_socket(), ZBX_CEP_ADD_USER_CLOSE_EVENT, data, data_offset))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send close problem message to CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize a user close problem request from a buffer            *
 *                                                                            *
 * Parameters: data    - [IN]  pointer to serialized close problem data       *
 *             event   - [OUT] pointer to the deserialized OK event           *
 *             eventid - [OUT] identifier of the problem to be closed         *
 *             userid  - [OUT] identifier of the user closing the problem     *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_deserialize_close_problem(const unsigned char  *data, zbx_db_event **event, zbx_uint64_t *eventid,
		zbx_uint64_t *userid)
{
	data += cep_deserialize_event(data, event);
	data += zbx_deserialize_value(data, eventid);
	(void)zbx_deserialize_value(data, userid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize maintenance event data from a buffer                  *
 *                                                                            *
 * Parameters: data   - [IN]  pointer to serialized maintenance event data    *
 *             events - [OUT] vector to store deserialized maintenance event  *
 *                              mappings                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_deserialize_event_maintenance(const unsigned char *data, zbx_vector_event_maintenance_t *events)
{
	zbx_uint32_t	events_num;

	data += zbx_deserialize_value(data, &events_num);
	zbx_vector_event_maintenance_reserve(events, (size_t)events_num);
	for (zbx_uint32_t i = 0; i < events_num; i++)
	{
		zbx_event_maintenance_t	event_local;
		data += zbx_deserialize_value(data, &event_local.eventid);
		data += zbx_deserialize_value(data, &event_local.maintenanceid);

		zbx_vector_event_maintenance_append(events, event_local);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize and send maintenance event data                         *
 *                                                                            *
 * Parameters: events     - [IN] array of maintenance event data entries      *
 *             events_num - [IN] number of maintenance event data entries     *
 *             code       - [IN] CEP message code                             *
 *                                                                            *
 ******************************************************************************/
static void	cep_send_event_maintenance(const zbx_event_maintenance_t *events, int events_num, zbx_uint32_t code)
{
	unsigned char	*data = NULL, *ptr;
	zbx_uint32_t	data_len = 2 * sizeof(zbx_uint64_t) * events_num + sizeof(int);

	ptr = data = (unsigned char *)zbx_malloc(NULL, (size_t)data_len);
	ptr += zbx_serialize_value(ptr, events_num);
	for (int i = 0; i < events_num; i++)
	{
		ptr += zbx_serialize_value(ptr, events[i].eventid);
		ptr += zbx_serialize_value(ptr, events[i].maintenanceid);
	}

	if (FAIL == zbx_ipc_socket_write(cep_client_socket(), code, data, data_len))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send event maintenance message to CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize and send maintenance event data for suppression         *
 *                                                                            *
 * Parameters: events     - [IN] array of maintenance event data entries      *
 *             events_num - [IN] number of maintenance event data entries     *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_send_event_maintenance_on(const zbx_event_maintenance_t *events, int events_num)
{
	cep_send_event_maintenance(events, events_num, ZBX_CEP_SUPPRESS_EVENTS);
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize and send maintenance event data for unsuppression       *
 *                                                                            *
 * Parameters: events     - [IN] array of maintenance event data entries      *
 *             events_num - [IN] number of maintenance event data entries     *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_send_event_maintenance_off(const zbx_event_maintenance_t *events, int events_num)
{
	cep_send_event_maintenance(events, events_num, ZBX_CEP_UNSUPPRESS_EVENTS);
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize and send event severity data                            *
 *                                                                            *
 * Parameters: events     - [IN] array of event severity data entries         *
 *             events_num - [IN] number of event severity data entries        *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_send_event_severities(const zbx_event_severity_t *events, int events_num)
{
	zbx_uint32_t		data_len;
	unsigned char		*ptr, *data;
	zbx_event_severity_t	es_local;

	data_len = sizeof(events_num);
	data_len += (zbx_uint32_t)(events_num * (sizeof(es_local.eventid) + sizeof(es_local.severity)));
	ptr = data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr += zbx_serialize_value(ptr, events_num);

	for (int i = 0; i < events_num; i++)
	{
		ptr += zbx_serialize_value(ptr, events[i].eventid);
		ptr += zbx_serialize_value(ptr, events[i].severity);
	}

	if (FAIL == zbx_ipc_socket_write(cep_client_socket(), ZBX_CEP_UPDATE_SEVERITIES, data, data_len))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send severity update message to CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize event severity data from a buffer                     *
 *                                                                            *
 * Parameters: data   - [IN]  pointer to serialized event severity data       *
 *             events - [OUT] vector to store deserialized event severity     *
 *                             data entries                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_deserialize_event_severities(const unsigned char *data, zbx_vector_event_severity_t *events)
{
	int	events_num;

	data += zbx_deserialize_value(data, &events_num);
	zbx_vector_event_severity_reserve(events, (size_t)events_num);
	for (int i = 0; i < events_num; i++)
	{
		zbx_event_severity_t	event_local;

		data += zbx_deserialize_value(data, &event_local.eventid);
		data += zbx_deserialize_value(data, &event_local.severity);

		zbx_vector_event_severity_append(events, event_local);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize event tags into a buffer                                *
 *                                                                            *
 * Parameters: data        - [IN/OUT] pointer to buffer pointer for writing   *
 *             data_alloc  - [IN/OUT] pointer to buffer size                  *
 *             data_offset - [IN/OUT] pointer to current buffer offset        *
 *             event_tags  - [IN]     event tags to serialize                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_buffer_serialize_event_tags(unsigned char **data, zbx_uint32_t *data_alloc, zbx_uint32_t *data_offset,
		const zbx_event_tags_t *event_tags)
{
	zbx_uint32_t	data_len = 0, tags_local_len[32], *tags_len;

	zbx_serialize_prepare_value(data_len, event_tags->eventid);
	zbx_serialize_prepare_value(data_len, event_tags->source);
	zbx_serialize_prepare_value(data_len, event_tags->tags.values_num);

	if ((size_t)event_tags->tags.values_num * 2 > ARRSIZE(tags_local_len))
	{
		tags_len = (zbx_uint32_t *)zbx_malloc(NULL,
				(size_t)event_tags->tags.values_num * 2 * sizeof(zbx_uint32_t));
	}
	else
		tags_len = tags_local_len;

	for (int i = 0; i < event_tags->tags.values_num; i++)
	{
		zbx_serialize_prepare_str_len(data_len, event_tags->tags.values[i].tag, tags_len[i * 2]);
		zbx_serialize_prepare_str_len(data_len, event_tags->tags.values[i].value, tags_len[i * 2 + 1]);
	}

	cep_buffer_reserve(data, data_alloc, *data_offset, data_len);

	unsigned char	*ptr = *data + *data_offset;

	ptr += zbx_serialize_value(ptr, event_tags->eventid);
	ptr += zbx_serialize_value(ptr, event_tags->source);
	ptr += zbx_serialize_value(ptr, event_tags->tags.values_num);
	for (int i = 0; i < event_tags->tags.values_num; i++)
	{
		ptr += zbx_serialize_str(ptr,event_tags->tags.values[i].tag, tags_len[i * 2]);
		ptr += zbx_serialize_str(ptr, event_tags->tags.values[i].value, tags_len[i * 2 + 1]);
	}

	if (tags_len != tags_local_len)
		zbx_free(tags_len);

	*data_offset += data_len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize and send event tag data                                 *
 *                                                                            *
 * Parameters: events     - [IN] array of pointers to event tag sets          *
 *             events_num - [IN] number of event tag sets                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_send_event_tags(zbx_event_tags_t * const *events, int events_num)
{
	unsigned char		*data;
	zbx_uint32_t		data_alloc = 4096, data_offset = 0;

	data = (unsigned char *)zbx_malloc(NULL, data_alloc);
	data_offset = zbx_serialize_value(data, events_num);

	for (int i = 0; i < events_num; i++)
		zbx_buffer_serialize_event_tags(&data, &data_alloc, &data_offset, events[i]);

	if (FAIL == zbx_ipc_socket_write(cep_client_socket(), ZBX_CEP_ADD_EVENT_TAGS, data, data_offset))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send event tag update message to CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize event tag data from a buffer                          *
 *                                                                            *
 * Parameters: data   - [IN]  pointer to serialized event tag data            *
 *             events - [OUT] vector to store deserialized event tag sets     *
 *                                                                            *
 ******************************************************************************/
void	zbx_deserialize_event_tags(const unsigned char *data, zbx_vector_event_tags_t *events)
{
	int	events_num;

	data += zbx_deserialize_value(data, &events_num);
	zbx_vector_event_tags_reserve(events, (size_t)events_num);

	for (int i = 0; i < events_num; i++)
	{
		zbx_event_tags_t	event_tags_local;
		int			tags_num;
		zbx_uint32_t		len;

		data += zbx_deserialize_value(data, &event_tags_local.eventid);
		data += zbx_deserialize_value(data, &event_tags_local.source);
		data += zbx_deserialize_value(data, &tags_num);
		zbx_vector_tag_create(&event_tags_local.tags);
		zbx_vector_tag_reserve(&event_tags_local.tags, (size_t)tags_num);

		for (int j = 0; j < tags_num; j++)
		{
			zbx_tag_t	tag_local;

			data += zbx_deserialize_str(data, &tag_local.tag, len);
			data += zbx_deserialize_str(data, &tag_local.value, len);
			zbx_vector_tag_append(&event_tags_local.tags, tag_local);
		}

		zbx_vector_event_tags_append(events, event_tags_local);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize and send IDs of deleted events                          *
 *                                                                            *
 * Parameters: eventids     - [IN] array of deleted event IDs                 *
 *             eventids_num - [IN] number of deleted event IDs                *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_send_deleted_events(const zbx_uint64_t *eventids, int eventids_num)
{
	zbx_uint32_t	data_len = sizeof(eventids_num) + sizeof(zbx_uint64_t) * eventids_num;
	unsigned char	*data, *ptr;

	ptr = data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr += zbx_serialize_value(ptr, eventids_num);
	for (int i = 0; i < eventids_num; i++)
		ptr += zbx_serialize_value(ptr, eventids[i]);

	if (FAIL == zbx_ipc_socket_write(cep_client_socket(), ZBX_CEP_DELETE_EVENTS, data, data_len))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send delete events message to CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize 64-bit IDs from a buffer                              *
 *                                                                            *
 * Parameters: data - [IN]  pointer to serialized 64-bit IDs                  *
 *             ids  - [OUT] vector to store deserialized 64-bit IDs           *
 *                                                                            *
 ******************************************************************************/
void	zbx_cep_deserialize_ids(const unsigned char *data, zbx_vector_uint64_t *ids)
{
	int	ids_num;

	data += zbx_deserialize_value(data, &ids_num);
	zbx_vector_uint64_reserve(ids, (size_t)ids_num);
	for (int i = 0; i < ids_num; i++)
	{
		zbx_uint64_t	id;

		data += zbx_deserialize_value(data, &id);
		zbx_vector_uint64_append(ids, id);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: check trigger dependencies via cep service                        *
 *                                                                            *
 * Parameters: depids - [IN] trigger dependency IDs to check                  *
 *                                                                            *
 * Return value: SUCCEED if no dependent trigger has a problem                *
 *               FAIL otherwise                                               *
 *                                                                            *
 ******************************************************************************/
static int	cep_check_trigger_deps(zbx_vector_uint64_t *depids)
{
	zbx_uint32_t	data_len;
	unsigned char	*data;
	int		ret;

	data_len = zbx_cep_serialize_ids(depids, &data);

	if (FAIL == zbx_ipc_socket_write(cep_client_socket(), ZBX_CEP_CHECK_TRIGGER_DEPS, data, data_len))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send check trigger dependencies message to CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	zbx_ipc_message_t	response = {0};

	if (FAIL == zbx_ipc_socket_read(cep_client_socket(), &response))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot receive data from CEP service");
		zbx_exit(EXIT_FAILURE);
	}

	ret = (CEP_EVENT_ALLOW == *response.data ? SUCCEED : FAIL);

	zbx_ipc_message_clean(&response);
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check trigger dependencies via cep service                        *
 *                                                                            *
 * Parameters: triggerid - [IN] trigger ID to check                           *
 *                                                                            *
 * Return value: SUCCEED if no dependent trigger has a problem                *
 *               FAIL otherwise                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_cep_check_trigger_deps(zbx_uint64_t triggerid)
{
	zbx_vector_uint64_t	depids;
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64, __func__, triggerid);

	zbx_vector_uint64_create(&depids);

	zbx_dc_get_trigger_deps_by_triggerid(triggerid, &depids);
	ret = cep_check_trigger_deps(&depids);

	zbx_vector_uint64_destroy(&depids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve statistics from the CEP service                          *
 *                                                                            *
 * Parameters: stats - [OUT] populated with event counters from CEP service   *
 *             error - [OUT] error message if the operation fails             *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_cep_get_stats(zbx_cep_stats_t *stats, char **error)
{
	zbx_ipc_socket_t	socket;
	char			*errmsg = NULL;
	int			ret = FAIL;
	zbx_ipc_message_t	response = {0};
	unsigned char		*ptr;

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_CEP, SEC_PER_MIN, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot connect to CEP service: %s", errmsg);
		zbx_free(errmsg);
		return ret;
	}

	if (FAIL == zbx_ipc_socket_write(&socket, ZBX_CEP_GET_STATS, NULL, 0))
	{
		*error = zbx_strdup(NULL, "cannot send get stats message to CEP service");
		goto out;
	}

	if (FAIL == zbx_ipc_socket_read(&socket, &response))
	{
		*error = zbx_strdup(NULL, "cannot receive data from CEP service");
		goto out;
	}

	ptr = response.data;
	ptr += zbx_deserialize_value(ptr, &stats->events_accessed);
	ptr += zbx_deserialize_value(ptr, &stats->events_processed);
	ptr += zbx_deserialize_value(ptr, &stats->events_discarded);
	ptr += zbx_deserialize_value(ptr, &stats->task_remote_num);
	ptr += zbx_deserialize_value(ptr, &stats->task_internal_num);
	ptr += zbx_deserialize_value(ptr, &stats->task_completed_num);
	ptr += zbx_deserialize_value(ptr, &stats->events_num);
	(void)zbx_deserialize_value(ptr, &stats->objects_num);

	zbx_ipc_message_clean(&response);

	ret = SUCCEED;
out:
	zbx_ipc_socket_close(&socket);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: send sync object state request to CEP service and await response  *
 *                                                                            *
 * Parameters: error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_cep_sync_object_state(char **error)
{
	zbx_ipc_socket_t	socket;
	char			*errmsg = NULL;
	int			ret = FAIL;
	zbx_ipc_message_t	response = {0};

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_CEP, SEC_PER_MIN, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot connect to CEP service: %s", errmsg);
		zbx_free(errmsg);
		return ret;
	}

	if (FAIL == zbx_ipc_socket_write(&socket, ZBX_CEP_SYNC_OBJECT_STATE, NULL, 0))
	{
		*error = zbx_strdup(NULL, "cannot send sync object state message to CEP service");
		goto out;
	}

	if (FAIL == zbx_ipc_socket_read(&socket, &response))
	{
		*error = zbx_strdup(NULL, "cannot receive data from CEP service");
		goto out;
	}

	zbx_ipc_message_clean(&response);

	ret = SUCCEED;
out:
	zbx_ipc_socket_close(&socket);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve startup statistics from the CEP service                  *
 *                                                                            *
 * Parameters: stats - [OUT] CEP cache loading statistics                     *
 *             error - [OUT] error message if the operation fails             *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_cep_get_diaginfo(zbx_cep_diaginfo_t *stats, char **error)
{
	zbx_ipc_socket_t	socket;
	char			*errmsg = NULL;
	int			ret = FAIL;
	zbx_ipc_message_t	response = {0};
	unsigned char		*ptr;

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_CEP, SEC_PER_MIN, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot connect to CEP service: %s", errmsg);
		zbx_free(errmsg);
		return ret;
	}

	if (FAIL == zbx_ipc_socket_write(&socket, ZBX_CEP_GET_DIAGINFO, NULL, 0))
	{
		*error = zbx_strdup(NULL, "cannot send get init stats message to CEP service");
		goto out;
	}

	if (FAIL == zbx_ipc_socket_read(&socket, &response))
	{
		*error = zbx_strdup(NULL, "cannot receive data from CEP service");
		goto out;
	}

	ptr = response.data;
	ptr += zbx_deserialize_value(ptr, &stats->startup.events_num);
	ptr += zbx_deserialize_value(ptr, &stats->startup.events_time);
	ptr += zbx_deserialize_value(ptr, &stats->startup.tags_num);
	ptr += zbx_deserialize_value(ptr, &stats->startup.tags_time);
	ptr += zbx_deserialize_value(ptr, &stats->startup.suppress_num);
	ptr += zbx_deserialize_value(ptr, &stats->startup.suppress_time);
	ptr += zbx_deserialize_value(ptr, &stats->blocked_commit_num);
	ptr += zbx_deserialize_value(ptr, &stats->commits_num);
	ptr += zbx_deserialize_value(ptr, &stats->commit_task_num);
	(void)zbx_deserialize_value(ptr, &stats->workers_num);

	zbx_ipc_message_clean(&response);

	ret = SUCCEED;
out:
	zbx_ipc_socket_close(&socket);

	return ret;
}

