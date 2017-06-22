/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "log.h"
#include "proxy.h"
#include "zbxserver.h"
#include "zbxserialize.h"
#include "zbxipcservice.h"

#include "zbxpreproc.h"

extern unsigned char	program_type;

#define PACKED_FIELD_RAW	0
#define PACKED_FIELD_STRING	1

/* packed field data description */
typedef struct
{
	const void	*value;	/* value to be packed */
	zbx_uint32_t	size;	/* size of a value (can be 0 for strings) */
	unsigned char	type;	/* field type */
}
zbx_packed_field_t;

#define pack_field(value, size) (zbx_packed_field_t){value, size, (0 == size)?PACKED_FIELD_STRING : PACKED_FIELD_RAW};

/******************************************************************************
 *                                                                            *
 * Function: pack_data                                                        *
 *                                                                            *
 * Purpose: helper for data packing based on defined format                   *
 *                                                                            *
 * Parameters: data   - [OUT] memory buffer for packed data, can be NULL for  *
 *                            buffer size calculations                        *
 *             fields - [IN]  the definition of data to be packed             *
 *             result - [IN]  field count                                     *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	pack_data(unsigned char **data, zbx_packed_field_t *fields, int count)
{
	int 		i;
	zbx_uint32_t	field_size, data_size = 0;
	unsigned char	*offset = NULL;

	if (NULL != data)
	{
		/* recursive call to calculate required buffer size */
		data_size = pack_data(NULL, fields, count);
		*data = zbx_malloc(NULL, data_size);
		offset = *data;
	}

	for (i = 0; i < count; i++)
	{
		field_size = fields[i].size;
		if (NULL != offset)
		{
			/* data packing */
			if (PACKED_FIELD_STRING == fields[i].type)
			{
				memcpy(offset, (zbx_uint32_t *)&field_size, sizeof(zbx_uint32_t));
				memcpy(offset + sizeof(zbx_uint32_t), fields[i].value, field_size);
				field_size += sizeof(zbx_uint32_t);
			}
			else
				memcpy(offset, fields[i].value, field_size);

			offset += field_size;
		}
		else
		{
			/* size calculation */
			if (PACKED_FIELD_STRING == fields[i].type)
			{
				field_size = (NULL != fields[i].value) ? strlen(fields[i].value) + 1 : 0;
				fields[i].size = field_size;
				field_size += sizeof(zbx_uint32_t);
			}

			data_size += field_size;
		}
	}

	return data_size;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_pack_value                                      *
 *                                                                            *
 * Purpose: pack item value data into a single buffer that can be used in IPC *
 *                                                                            *
 * Parameters: data  - [OUT] memory buffer for packed data                    *
 *             value - [IN]  value to be packed                               *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_value(unsigned char **data, zbx_preproc_item_value_t *value)
{
	zbx_packed_field_t	fields[22], *offset = fields; /* 22 - max field count */
	unsigned char		ts_marker, result_marker, log_marker;

	ts_marker = (NULL != value->ts);
	result_marker = (NULL != value->result);

	*offset++ = pack_field(&value->itemid, sizeof(zbx_uint64_t));
	*offset++ = pack_field(&value->item_flags, sizeof(unsigned char));
	*offset++ = pack_field(&value->state, sizeof(unsigned char));
	*offset++ = pack_field(value->error, 0);
	*offset++ = pack_field(&ts_marker, sizeof(unsigned char));

	if (NULL != value->ts)
	{
		*offset++ = pack_field(&value->ts->sec, sizeof(int));
		*offset++ = pack_field(&value->ts->ns, sizeof(int));
	}

	*offset++ = pack_field(&result_marker, sizeof(unsigned char));

	if (NULL != value->result)
	{
		*offset++ = pack_field(&value->result->lastlogsize, sizeof(zbx_uint64_t));
		*offset++ = pack_field(&value->result->ui64, sizeof(zbx_uint64_t));
		*offset++ = pack_field(&value->result->dbl, sizeof(double));
		*offset++ = pack_field(value->result->str, 0);
		*offset++ = pack_field(value->result->text, 0);
		*offset++ = pack_field(value->result->msg, 0);
		*offset++ = pack_field(&value->result->type, sizeof(int));
		*offset++ = pack_field(&value->result->mtime, sizeof(int));

		log_marker = (NULL != value->result->log);
		*offset++ = pack_field(&log_marker, sizeof(unsigned char));
		if (NULL != value->result->log)
		{
			*offset++ = pack_field(value->result->log->value, 0);
			*offset++ = pack_field(value->result->log->source, 0);
			*offset++ = pack_field(&value->result->log->timestamp, sizeof(int));
			*offset++ = pack_field(&value->result->log->severity, sizeof(int));
			*offset++ = pack_field(&value->result->log->logeventid, sizeof(int));
		}
	}

	return pack_data(data, fields, offset - fields);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_pack_task                                       *
 *                                                                            *
 * Purpose: pack preprocessing task data into a single buffer that can be     *
 *          used in IPC                                                       *
 *                                                                            *
 * Parameters: data          - [OUT] memory buffer for packed data            *
 *             itemid        - [IN] itemid                                    *
 *             ts            - [IN] value timestamp                           *
 *             value         - [IN] item value                                *
 *             history_value - [IN] history data for delta preprocessing      *
 *             step_count    - [IN] preprocessing step count                  *
 *             steps         - [IN]preprocessing steps                        *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_task(unsigned char **data, zbx_uint64_t itemid, zbx_timespec_t *ts,
		zbx_variant_t *value, zbx_item_history_value_t *history_value, int step_count,
		zbx_item_preproc_t *steps)
{
	zbx_packed_field_t	*offset, *fields;
	unsigned char		ts_marker, history_marker;
	zbx_uint32_t		size;
	int			i;

	/* 13 is a max field count (without preprocessing step fields) */
	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (13 + step_count * 2) * sizeof(zbx_packed_field_t));

	offset = fields;
	ts_marker = (NULL != ts);
	history_marker = (NULL != history_value);

	*offset++ = pack_field(&itemid, sizeof(zbx_uint64_t));
	*offset++ = pack_field(&ts_marker, sizeof(unsigned char));

	if (NULL != ts)
	{
		*offset++ = pack_field(&ts->sec, sizeof(int));
		*offset++ = pack_field(&ts->ns, sizeof(int));
	}

	*offset++ = pack_field(&value->type, sizeof(unsigned char));

	switch (value->type)
	{
		case ZBX_VARIANT_UI64:
			*offset++ = pack_field(&value->data.ui64, sizeof(zbx_uint64_t));
			break;

		case ZBX_VARIANT_DBL:
			*offset++ = pack_field(&value->data.dbl, sizeof(double));
			break;

		case ZBX_VARIANT_STR:
			*offset++ = pack_field(value->data.str, 0);
			break;

		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	*offset++ = pack_field(&history_marker, sizeof(unsigned char));
	if (NULL != history_value)
	{
		*offset++ = pack_field(&history_value->value_type, sizeof(unsigned char));
		*offset++ = pack_field(&history_value->value.type, sizeof(unsigned char));

		switch (history_value->value.type)
		{
			case ZBX_VARIANT_UI64:
				*offset++ = pack_field(&history_value->value.data.ui64, sizeof(zbx_uint64_t));
				break;

			case ZBX_VARIANT_DBL:
				*offset++ = pack_field(&history_value->value.data.dbl, sizeof(double));
				break;

			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		*offset++ = pack_field(&history_value->timestamp.sec, sizeof(int));
		*offset++ = pack_field(&history_value->timestamp.ns, sizeof(int));
	}

	*offset++ = pack_field(&step_count, sizeof(int));

	for (i = 0; i < step_count; i++)
	{
		*offset++ = pack_field(&steps[i].type, sizeof(char));
		*offset++ = pack_field(steps[i].params, 0);
	}

	size = pack_data(data, fields, offset - fields);
	zbx_free(fields);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_pack_result                                     *
 *                                                                            *
 * Purpose: pack preprocessing result data into a single buffer that can be   *
 *          used in IPC                                                       *
 *                                                                            *
 * Parameters: data          - [OUT] memory buffer for packed data            *
 *             value         - [IN] result value                              *
 *             history_value - [IN] item history data                         *
 *             error         - [IN] preprocessing error                       *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_result(unsigned char **data, zbx_variant_t *value,
		zbx_item_history_value_t *history_value, char *error)
{
	zbx_packed_field_t	*offset, fields[9]; /* 9 - max field count */
	unsigned char		history_marker;

	offset = fields;
	history_marker = (NULL != history_value);

	*offset++ = pack_field(&value->type, sizeof(unsigned char));

	switch (value->type)
	{
		case ZBX_VARIANT_UI64:
			*offset++ = pack_field(&value->data.ui64, sizeof(zbx_uint64_t));
			break;

		case ZBX_VARIANT_DBL:
			*offset++ = pack_field(&value->data.dbl, sizeof(double));
			break;

		case ZBX_VARIANT_STR:
			*offset++ = pack_field(value->data.str, 0);
			break;
	}

	*offset++ = pack_field(&history_marker, sizeof(unsigned char));

	if (NULL != history_value)
	{
		*offset++ = pack_field(&history_value->value_type, sizeof(unsigned char));
		*offset++ = pack_field(&history_value->value.type, sizeof(unsigned char));

		switch (history_value->value.type)
		{
			case ZBX_VARIANT_UI64:
				*offset++ = pack_field(&history_value->value.data.ui64, sizeof(zbx_uint64_t));
				break;

			case ZBX_VARIANT_DBL:
				*offset++ = pack_field(&history_value->value.data.dbl, sizeof(double));
				break;

			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		*offset++ = pack_field(&history_value->timestamp.sec, sizeof(int));
		*offset++ = pack_field(&history_value->timestamp.ns, sizeof(int));
	}

	*offset++ = pack_field(error, 0);

	return pack_data(data, fields, offset - fields);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_unpack_value                                    *
 *                                                                            *
 * Purpose: unpack item value data from IPC data buffer                       *
 *                                                                            *
 * Parameters: value    - [OUT] unpacked item value                           *
 *             data	- [IN]  IPC data buffer                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_value(zbx_preproc_item_value_t *value, unsigned char *data)
{
	zbx_uint32_t	value_len;
	zbx_timespec_t	*timespec = NULL;
	AGENT_RESULT	*agent_result = NULL;
	zbx_log_t	*log = NULL;
	unsigned char	*offset = data, ts_marker, result_marker, log_marker;

	offset += zbx_deserialize_uint64(offset, &value->itemid);
	offset += zbx_deserialize_char(offset, &value->item_flags);
	offset += zbx_deserialize_char(offset, &value->state);
	offset += zbx_deserialize_str_ptr(offset, value->error, value_len);
	offset += zbx_deserialize_char(offset, &ts_marker);

	if (0 != ts_marker)
	{
		timespec = (zbx_timespec_t *)zbx_malloc(NULL, sizeof(zbx_timespec_t));

		offset += zbx_deserialize_int(offset, &timespec->sec);
		offset += zbx_deserialize_int(offset, &timespec->ns);
	}

	value->ts = timespec;

	offset += zbx_deserialize_char(offset, &result_marker);
	if (0 != result_marker)
	{
		agent_result = (AGENT_RESULT *)zbx_malloc(NULL, sizeof(AGENT_RESULT));

		offset += zbx_deserialize_uint64(offset, &agent_result->lastlogsize);
		offset += zbx_deserialize_uint64(offset, &agent_result->ui64);
		offset += zbx_deserialize_double(offset, &agent_result->dbl);
		offset += zbx_deserialize_str_ptr(offset, agent_result->str, value_len);
		offset += zbx_deserialize_str_ptr(offset, agent_result->text, value_len);
		offset += zbx_deserialize_str_ptr(offset, agent_result->msg, value_len);
		offset += zbx_deserialize_int(offset, &agent_result->type);
		offset += zbx_deserialize_int(offset, &agent_result->mtime);

		offset += zbx_deserialize_char(offset, &log_marker);
		if (0 != log_marker)
		{
			log = (zbx_log_t *)zbx_malloc(NULL, sizeof(zbx_log_t));

			offset += zbx_deserialize_str_ptr(offset, log->value, value_len);
			offset += zbx_deserialize_str_ptr(offset, log->source, value_len);
			offset += zbx_deserialize_int(offset, &log->timestamp);
			offset += zbx_deserialize_int(offset, &log->severity);
			offset += zbx_deserialize_int(offset, &log->logeventid);
		}

		agent_result->log = log;
	}

	value->result = agent_result;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_unpack_task                                     *
 *                                                                            *
 * Purpose: unpack preprocessing task data from IPC data buffer               *
 *                                                                            *
 * Parameters: itemid        - [OUT] itemid                                   *
 *             ts            - [OUT] value timestamp                          *
 *             value         - [OUT] item value                               *
 *             history_value - [OUT] history data for delta preprocessing     *
 *             step_count    - [OUT] preprocessing step count                 *
 *             steps         - [OUT]preprocessing steps                       *
 *             data          - [IN] IPC data buffer                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_task(zbx_uint64_t *itemid, zbx_timespec_t **ts, zbx_variant_t *value,
		zbx_item_history_value_t **history_value, int *step_count, zbx_item_preproc_t **steps,
		const unsigned char *data)
{
	zbx_uint32_t			value_len;
	const unsigned char		*offset = data;
	unsigned char 			ts_marker, history_marker;
	zbx_item_history_value_t	*hvalue = NULL;
	zbx_timespec_t			*timespec = NULL;
	zbx_item_preproc_t		*preproc_steps = NULL;
	int				i;

	offset += zbx_deserialize_uint64(offset, itemid);
	offset += zbx_deserialize_char(offset, &ts_marker);

	if (0 != ts_marker)
	{
		timespec = (zbx_timespec_t *)zbx_malloc(NULL, sizeof(zbx_timespec_t));

		offset += zbx_deserialize_int(offset, &timespec->sec);
		offset += zbx_deserialize_int(offset, &timespec->ns);
	}

	*ts = timespec;
	offset += zbx_deserialize_char(offset, &value->type);

	switch (value->type)
	{
		case ZBX_VARIANT_UI64:
			offset += zbx_deserialize_uint64(offset, &value->data.ui64);
			break;

		case ZBX_VARIANT_DBL:
			offset += zbx_deserialize_double(offset, &value->data.dbl);
			break;

		case ZBX_VARIANT_STR:
			offset += zbx_deserialize_str(offset, &value->data.str, value_len);
			break;

		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	offset += zbx_deserialize_char(offset, &history_marker);
	if (0 != history_marker)
	{
		hvalue = (zbx_item_history_value_t *)zbx_malloc(NULL, sizeof(zbx_item_history_value_t));

		offset += zbx_deserialize_char(offset, &hvalue->value_type);
		offset += zbx_deserialize_char(offset, &hvalue->value.type);

		switch (hvalue->value.type)
		{
			case ZBX_VARIANT_UI64:
				offset += zbx_deserialize_uint64(offset, &hvalue->value.data.ui64);
				break;

			case ZBX_VARIANT_DBL:
				offset += zbx_deserialize_double(offset, &hvalue->value.data.dbl);
				break;

			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		offset += zbx_deserialize_int(offset, &hvalue->timestamp.sec);
		offset += zbx_deserialize_int(offset, &hvalue->timestamp.ns);
	}

	*history_value = hvalue;
	offset += zbx_deserialize_int(offset, step_count);
	if (0 < *step_count)
	{
		preproc_steps = (zbx_item_preproc_t *)zbx_malloc(NULL, (*step_count) * sizeof(zbx_item_preproc_t));

		for (i = 0; i < *step_count; i++)
		{
			offset += zbx_deserialize_char(offset, &preproc_steps[i].type);
			offset += zbx_deserialize_str_s(offset, preproc_steps[i].params, value_len);
		}
	}

	*steps = preproc_steps;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_unpack_result                                   *
 *                                                                            *
 * Purpose: unpack preprocessing task data from IPC data buffer               *
 *                                                                            *
 * Parameters: value         - [OUT] result value                             *
 *             history_value - [OUT] item history data                        *
 *             error         - [OUT] preprocessing error                      *
 *             data          - [IN] IPC data buffer                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_result(zbx_variant_t *value, zbx_item_history_value_t **history_value,
		char **error, const unsigned char *data)
{
	zbx_uint32_t			value_len;
	const unsigned char		*offset = data;
	unsigned char 			history_marker;
	zbx_item_history_value_t	*hvalue = NULL;

	offset += zbx_deserialize_char(offset, &value->type);

	switch (value->type)
	{
		case ZBX_VARIANT_UI64:
			offset += zbx_deserialize_uint64(offset, &value->data.ui64);
			break;

		case ZBX_VARIANT_DBL:
			offset += zbx_deserialize_double(offset, &value->data.dbl);
			break;

		case ZBX_VARIANT_STR:
			offset += zbx_deserialize_str(offset, &value->data.str, value_len);
			break;
	}

	offset += zbx_deserialize_char(offset, &history_marker);
	if (0 != history_marker)
	{
		hvalue = (zbx_item_history_value_t *)zbx_malloc(NULL, sizeof(zbx_item_history_value_t));

		offset += zbx_deserialize_char(offset, &hvalue->value_type);
		offset += zbx_deserialize_char(offset, &hvalue->value.type);

		switch (hvalue->value.type)
		{
			case ZBX_VARIANT_UI64:
				offset += zbx_deserialize_uint64(offset, &hvalue->value.data.ui64);
				break;

			case ZBX_VARIANT_DBL:
				offset += zbx_deserialize_double(offset, &hvalue->value.data.dbl);
				break;

			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		offset += zbx_deserialize_int(offset, &hvalue->timestamp.sec);
		offset += zbx_deserialize_int(offset, &hvalue->timestamp.ns);
	}

	*history_value = hvalue;

	(void)zbx_deserialize_str(offset, error, value_len);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_send                                                *
 *                                                                            *
 * Purpose: sends command to preprocessor manager                             *
 *                                                                            *
 * Parameters: code     - [IN] message code                                  *
 *             data     - [IN] message data                                  *
 *             size     - [IN] message data size                             *
 *             response - [OUT] response message (can be NULL if response is *
 *                              not requested)                               *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size,
		zbx_ipc_message_t *response)
{
	char 			*error;
	static zbx_ipc_socket_t	socket = {0};

	/* each process has a permanent connection to preprocessing manager */
	if (0 == socket.fd && FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_PREPROCESSING, SEC_PER_MIN,
			&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to preprocessing service: %s", error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_socket_write(&socket, code, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send data to preprocessing service");
		exit(EXIT_FAILURE);
	}

	if (NULL != response && FAIL == zbx_ipc_socket_read(&socket, response))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot receive data from preprocessing service");
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocess_item_value                                        *
 *                                                                            *
 * Purpose: perform item value preprocessing and dependend item processing    *
 *                                                                            *
 * Parameters: itemid     - [IN] the itemid                                   *
 *             item_flags - [IN] the item flags (e. g. lld rule)              *
 *             result     - [IN] agent result containing the value to add     *
 *             ts         - [IN] the value timestamp                          *
 *             state      - [IN] the item state                               *
 *             error      - [IN] the error message in case item state is      *
 *                               ITEM_STATE_NOTSUPPORTED                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocess_item_value(zbx_uint64_t itemid, unsigned char item_flags, AGENT_RESULT *result,
		zbx_timespec_t *ts, unsigned char state, char *error)
{
	const char			*__function_name = "zbx_preprocess_item_value";
	zbx_uint32_t			size = 0;
	unsigned char			*data = NULL;
	zbx_preproc_item_value_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* only server performs preprocessing */
	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
	{
		if (0 != (item_flags & ZBX_FLAG_DISCOVERY_RULE))
		{
			if (NULL != result && NULL != GET_TEXT_RESULT(result))
				lld_process_discovery_rule(itemid, result->text, ts);

			goto out;
		}

		value.itemid = itemid;
		value.result = result;
		value.error = error;
		value.item_flags = item_flags;
		value.state = state;
		value.ts = ts;

		size = zbx_preprocessor_pack_value(&data, &value);
		preprocessor_send(ZBX_IPC_PREPROCESSOR_REQUEST, data, size, NULL);

		zbx_free(data);
	}
	else
		dc_add_history(itemid, item_flags, result, ts, state, error);

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_hold                                            *
 *                                                                            *
 * Purpose: send hold command to preprocessing manager                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_hold()
{
	unsigned char command = ZBX_PREPROCESSOR_COMMAND_HOLD;

	preprocessor_send(ZBX_IPC_PREPROCESSOR_COMMAND, &command, 1, NULL);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_flush                                           *
 *                                                                            *
 * Purpose: send flush command to preprocessing manager                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_flush()
{
	unsigned char command = ZBX_PREPROCESSOR_COMMAND_FLUSH;

	if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
		preprocessor_send(ZBX_IPC_PREPROCESSOR_COMMAND, &command, 1, NULL);
	else
		dc_flush_history();
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_get_queue_size                                  *
 *                                                                            *
 * Purpose: get queue size (enqueued value count) of preprocessing manager    *
 *                                                                            *
 * Return value: enqueued item count                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_preprocessor_get_queue_size()
{
	zbx_uint64_t		size;
	zbx_ipc_message_t	message;

	zbx_ipc_message_init(&message);
	preprocessor_send(ZBX_IPC_PREPROCESSOR_QUEUE, NULL, 0, &message);
	memcpy(&size, message.data, sizeof(zbx_uint64_t));
	zbx_ipc_message_clean(&message);

	return size;
}
