/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "preprocessing.h"

#include "common.h"
#include "log.h"
#include "zbxserialize.h"
#include "preproc_history.h"
#include "item_preproc.h"

#define PACKED_FIELD_RAW	0
#define PACKED_FIELD_STRING	1
#define MAX_VALUES_LOCAL	256

#define PACKED_FIELD(value, size)	\
		(zbx_packed_field_t){(value), (size), (0 == (size) ? PACKED_FIELD_STRING : PACKED_FIELD_RAW)};

static zbx_ipc_message_t	cached_message;
static int			cached_values;

ZBX_PTR_VECTOR_IMPL(ipcmsg, zbx_ipc_message_t *)

static zbx_uint32_t	fields_calc_size(zbx_packed_field_t *fields, int fields_num)
{
	zbx_uint32_t	data_size = 0, field_size;
	int		i;

	for (i = 0; i < fields_num; i++)
	{
		if (PACKED_FIELD_STRING == fields[i].type)
		{
			field_size = (NULL != fields[i].value) ? (zbx_uint32_t)strlen((const char *)fields[i].value) + 1 : 0;
			fields[i].size = (zbx_uint32_t)field_size;
			field_size += (zbx_uint32_t)sizeof(zbx_uint32_t);
		}
		else
			field_size = fields[i].size;

		if (UINT32_MAX - field_size < data_size)
			return 0;

		data_size += field_size;
	}

	return data_size;
}

static zbx_uint32_t	fields_pack(const zbx_packed_field_t *fields, int fields_num, unsigned char *data)
{
	int		i;
	unsigned char	*offset = data;

	for (i = 0; i < fields_num; i++)
	{
		/* data packing */
		if (PACKED_FIELD_STRING == fields[i].type)
		{
			memcpy(offset, &fields[i].size, sizeof(zbx_uint32_t));
			offset += sizeof(zbx_uint32_t);
			if (0 != fields[i].size)
				memcpy(offset, fields[i].value, fields[i].size);
		}
		else
			memcpy(offset, fields[i].value, fields[i].size);

		offset += fields[i].size;
	}

	return (zbx_uint32_t)(offset - data);
}

static int	message_pack_fields(zbx_ipc_message_t *message, const zbx_packed_field_t *fields,
		int fields_num, zbx_uint32_t fields_size)
{
	if (UINT32_MAX - message->size < fields_size)
		return FAIL;

	message->size += fields_size;
	message->data = (unsigned char *)zbx_realloc(message->data, message->size);
	fields_pack(fields, fields_num, message->data + (message->size - fields_size));

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: helper for data packing based on defined format                   *
 *                                                                            *
 * Parameters: message - [OUT] IPC message, can be NULL for buffer size       *
 *                             calculations                                   *
 *             fields  - [IN]  the definition of data to be packed            *
 *             count   - [IN]  field count                                    *
 *                                                                            *
 * Return value: size of packed data or 0 if the message size would exceed    *
 *               4GB limit                                                    *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	message_pack_data(zbx_ipc_message_t *message, zbx_packed_field_t *fields, int count)
{
	zbx_uint32_t	data_size = 0;

	if (0 == (data_size = fields_calc_size(fields, count)))
		return 0;

	if (NULL != message)
	{
		if (SUCCEED != message_pack_fields(message, fields, count, data_size))
			return 0;
	}

	return data_size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack item value data into a single buffer that can be used in IPC *
 *                                                                            *
 * Parameters: message - [OUT] IPC message                                    *
 *             value   - [IN]  value to be packed                             *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	preprocessor_pack_value(zbx_ipc_message_t *message, zbx_preproc_item_value_t *value)
{
	zbx_packed_field_t	fields[24], *offset = fields;	/* 24 - max field count */
	unsigned char		ts_marker, result_marker, log_marker;

	ts_marker = (NULL != value->ts);
	result_marker = (NULL != value->result);

	*offset++ = PACKED_FIELD(&value->itemid, sizeof(zbx_uint64_t));
	*offset++ = PACKED_FIELD(&value->hostid, sizeof(zbx_uint64_t));
	*offset++ = PACKED_FIELD(&value->item_value_type, sizeof(unsigned char));
	*offset++ = PACKED_FIELD(&value->item_flags, sizeof(unsigned char));
	*offset++ = PACKED_FIELD(&value->state, sizeof(unsigned char));
	*offset++ = PACKED_FIELD(value->error, 0);
	*offset++ = PACKED_FIELD(&ts_marker, sizeof(unsigned char));

	if (NULL != value->ts)
	{
		*offset++ = PACKED_FIELD(&value->ts->sec, sizeof(int));
		*offset++ = PACKED_FIELD(&value->ts->ns, sizeof(int));
	}

	*offset++ = PACKED_FIELD(&result_marker, sizeof(unsigned char));

	if (NULL != value->result)
	{

		*offset++ = PACKED_FIELD(&value->result->lastlogsize, sizeof(zbx_uint64_t));
		*offset++ = PACKED_FIELD(&value->result->ui64, sizeof(zbx_uint64_t));
		*offset++ = PACKED_FIELD(&value->result->dbl, sizeof(double));
		*offset++ = PACKED_FIELD(value->result->str, 0);
		*offset++ = PACKED_FIELD(value->result->text, 0);
		*offset++ = PACKED_FIELD(value->result->msg, 0);
		*offset++ = PACKED_FIELD(&value->result->type, sizeof(int));
		*offset++ = PACKED_FIELD(&value->result->mtime, sizeof(int));

		log_marker = (NULL != value->result->log);
		*offset++ = PACKED_FIELD(&log_marker, sizeof(unsigned char));
		if (NULL != value->result->log)
		{
			*offset++ = PACKED_FIELD(value->result->log->value, 0);
			*offset++ = PACKED_FIELD(value->result->log->source, 0);
			*offset++ = PACKED_FIELD(&value->result->log->timestamp, sizeof(int));
			*offset++ = PACKED_FIELD(&value->result->log->severity, sizeof(int));
			*offset++ = PACKED_FIELD(&value->result->log->logeventid, sizeof(int));
		}
	}

	return message_pack_data(message, fields, (int)(offset - fields));
}

/******************************************************************************
 *                                                                            *
 * Purpose: packs variant value for serialization                             *
 *                                                                            *
 * Parameters: fields - [OUT] the packed fields                               *
 *             value  - [IN] the value to pack                                *
 *                                                                            *
 * Return value: The number of fields used.                                   *
 *                                                                            *
 * Comments: Don't pack local variables, only ones passed in parameters!      *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_pack_variant(zbx_packed_field_t *fields, const zbx_variant_t *value)
{
	int	offset = 0;

	fields[offset++] = PACKED_FIELD(&value->type, sizeof(unsigned char));

	switch (value->type)
	{
		case ZBX_VARIANT_UI64:
			fields[offset++] = PACKED_FIELD(&value->data.ui64, sizeof(zbx_uint64_t));
			break;

		case ZBX_VARIANT_DBL:
			fields[offset++] = PACKED_FIELD(&value->data.dbl, sizeof(double));
			break;

		case ZBX_VARIANT_STR:
			fields[offset++] = PACKED_FIELD(value->data.str, 0);
			break;

		case ZBX_VARIANT_BIN:
			fields[offset++] = PACKED_FIELD(value->data.bin, sizeof(zbx_uint32_t) +
					zbx_variant_data_bin_get(value->data.bin, NULL));
			break;
	}

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Purpose: packs preprocessing history for serialization                     *
 *                                                                            *
 * Parameters: fields  - [OUT] the packed fields                              *
 *             history - [IN] the history to pack                             *
 *                                                                            *
 * Return value: The number of fields used.                                   *
 *                                                                            *
 * Comments: Don't pack local variables, only ones passed in parameters!      *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_pack_history(zbx_packed_field_t *fields, const zbx_vector_ptr_t *history,
		const int *history_num)
{
	int	i, offset = 0;

	fields[offset++] = PACKED_FIELD(history_num, sizeof(int));

	for (i = 0; i < *history_num; i++)
	{
		zbx_preproc_op_history_t	*ophistory = (zbx_preproc_op_history_t *)history->values[i];

		fields[offset++] = PACKED_FIELD(&ophistory->index, sizeof(int));
		offset += preprocessor_pack_variant(&fields[offset], &ophistory->value);
		fields[offset++] = PACKED_FIELD(&ophistory->ts.sec, sizeof(int));
		fields[offset++] = PACKED_FIELD(&ophistory->ts.ns, sizeof(int));
	}

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Purpose: packs preprocessing step for serialization                        *
 *                                                                            *
 * Parameters: fields - [OUT] the packed fields                               *
 *             step   - [IN] the step to pack                                 *
 *                                                                            *
 * Return value: The number of fields used.                                   *
 *                                                                            *
 * Comments: Don't pack local variables, only ones passed in parameters!      *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_pack_step(zbx_packed_field_t *fields, const zbx_preproc_op_t *step)
{
	int	offset = 0;

	fields[offset++] = PACKED_FIELD(&step->type, sizeof(char));
	fields[offset++] = PACKED_FIELD(step->params, 0);
	fields[offset++] = PACKED_FIELD(&step->error_handler, sizeof(char));
	fields[offset++] = PACKED_FIELD(step->error_handler_params, 0);

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Purpose: packs preprocessing steps for serialization                       *
 *                                                                            *
 * Parameters: fields    - [OUT] the packed fields                            *
 *             steps     - [IN] the steps to pack                             *
 *             steps_num - [IN] the number of steps                           *
 *                                                                            *
 * Return value: The number of fields used.                                   *
 *                                                                            *
 * Comments: Don't pack local variables, only ones passed in parameters!      *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_pack_steps(zbx_packed_field_t *fields, const zbx_preproc_op_t *steps, const int *steps_num)
{
	int	i, offset = 0;

	fields[offset++] = PACKED_FIELD(steps_num, sizeof(int));

	for (i = 0; i < *steps_num; i++)
		offset += preprocessor_pack_step(&fields[offset], &steps[i]);

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpacks serialized variant value                                  *
 *                                                                            *
 * Parameters: data  - [IN] the serialized data                               *
 *             value - [OUT] the value                                        *
 *                                                                            *
 * Return value: The number of bytes parsed.                                  *
 *                                                                            *
 ******************************************************************************/
static int	preprocesser_unpack_variant(const unsigned char *data, zbx_variant_t *value)
{
	const unsigned char	*offset = data;
	zbx_uint32_t		value_len;

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

		case ZBX_VARIANT_BIN:
			offset += zbx_deserialize_bin(offset, &value->data.bin, value_len);
			break;
	}

	return (int)(offset - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpacks serialized preprocessing history                          *
 *                                                                            *
 * Parameters: data    - [IN] the serialized data                             *
 *             history - [OUT] the history                                    *
 *                                                                            *
 * Return value: The number of bytes parsed.                                  *
 *                                                                            *
 ******************************************************************************/
static int	preprocesser_unpack_history(const unsigned char *data, zbx_vector_ptr_t *history)
{
	const unsigned char	*offset = data;
	int			i, history_num;

	offset += zbx_deserialize_int(offset, &history_num);

	if (0 != history_num)
	{
		zbx_vector_ptr_reserve(history, (size_t)history_num);

		for (i = 0; i < history_num; i++)
		{
			zbx_preproc_op_history_t	*ophistory;

			ophistory = zbx_malloc(NULL, sizeof(zbx_preproc_op_history_t));

			offset += zbx_deserialize_int(offset, &ophistory->index);
			offset += preprocesser_unpack_variant(offset, &ophistory->value);
			offset += zbx_deserialize_int(offset, &ophistory->ts.sec);
			offset += zbx_deserialize_int(offset, &ophistory->ts.ns);

			zbx_vector_ptr_append(history, ophistory);
		}
	}

	return (int)(offset - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpacks serialized preprocessing step                             *
 *                                                                            *
 * Parameters: data - [IN] the serialized data                                *
 *             step - [OUT] the preprocessing step                            *
 *                                                                            *
 * Return value: The number of bytes parsed.                                  *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_unpack_step(const unsigned char *data, zbx_preproc_op_t *step)
{
	const unsigned char	*offset = data;
	zbx_uint32_t		value_len;

	offset += zbx_deserialize_char(offset, &step->type);
	offset += zbx_deserialize_str(offset, &step->params, value_len);
	offset += zbx_deserialize_char(offset, &step->error_handler);
	offset += zbx_deserialize_str(offset, &step->error_handler_params, value_len);

	return (int)(offset - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpacks serialized preprocessing steps                            *
 *                                                                            *
 * Parameters: data      - [IN] the serialized data                           *
 *             steps     - [OUT] the preprocessing steps                      *
 *             steps_num - [OUT] the number of steps                          *
 *                                                                            *
 * Return value: The number of bytes parsed.                                  *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_unpack_steps(const unsigned char *data, zbx_preproc_op_t **steps, int *steps_num)
{
	const unsigned char	*offset = data;
	int			i;

	offset += zbx_deserialize_int(offset, steps_num);
	if (0 < *steps_num)
	{
		*steps = (zbx_preproc_op_t *)zbx_malloc(NULL, sizeof(zbx_preproc_op_t) * (size_t)(*steps_num));
		for (i = 0; i < *steps_num; i++)
			offset += preprocessor_unpack_step(offset, *steps + i);
	}
	else
		*steps = NULL;

	return (int)(offset - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack preprocessing task data into a single buffer that can be     *
 *          used in IPC                                                       *
 *                                                                            *
 * Parameters: data          - [OUT] memory buffer for packed data            *
 *             itemid        - [IN] item id                                   *
 *             value_type    - [IN] item value type                           *
 *             ts            - [IN] value timestamp                           *
 *             value         - [IN] item value                                *
 *             history       - [IN] history data (can be NULL)                *
 *             steps         - [IN] preprocessing steps                       *
 *             steps_num     - [IN] preprocessing step count                  *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_task(unsigned char **data, zbx_uint64_t itemid, unsigned char value_type,
		zbx_timespec_t *ts, zbx_variant_t *value, const zbx_vector_ptr_t *history,
		const zbx_preproc_op_t *steps, int steps_num)
{
	zbx_packed_field_t	*offset, *fields;
	unsigned char		ts_marker;
	zbx_uint32_t		size;
	int			history_num;
	zbx_ipc_message_t	message;

	history_num = (NULL != history ? history->values_num : 0);

	/* 9 is a max field count (without preprocessing step and history fields) */
	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (size_t)(9 + steps_num * 4 + history_num * 5)
			* sizeof(zbx_packed_field_t));

	offset = fields;
	ts_marker = (NULL != ts);

	*offset++ = PACKED_FIELD(&itemid, sizeof(zbx_uint64_t));
	*offset++ = PACKED_FIELD(&value_type, sizeof(unsigned char));
	*offset++ = PACKED_FIELD(&ts_marker, sizeof(unsigned char));

	if (NULL != ts)
	{
		*offset++ = PACKED_FIELD(&ts->sec, sizeof(int));
		*offset++ = PACKED_FIELD(&ts->ns, sizeof(int));
	}

	offset += preprocessor_pack_variant(offset, value);
	offset += preprocessor_pack_history(offset, history, &history_num);
	offset += preprocessor_pack_steps(offset, steps, &steps_num);

	zbx_ipc_message_init(&message);
	size = message_pack_data(&message, fields, (int)(offset - fields));
	*data = message.data;
	zbx_free(fields);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack fields into serialized message                               *
 *                                                                            *
 * Parameters: fields      - [IN] the fields to pack                          *
 *             fields_num  - [IN] the number of fields                        *
 *             fields_size - [IN] the size of packed field data               *
 *             messages    - [OUT] the message queue                          *
 *                                                                            *
 * Return value: SUCCEED - the message was added successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_append_packed_message(const zbx_packed_field_t *fields, int fields_num,
		zbx_uint32_t fields_size, zbx_uint32_t base_code, zbx_vector_ipcmsg_t *messages)
{
	zbx_ipc_message_t	*message;

	message = (zbx_ipc_message_t *)zbx_malloc(NULL, sizeof(zbx_ipc_message_t));
	zbx_ipc_message_init(message);
	zbx_vector_ipcmsg_append(messages, message);

	if (SUCCEED != message_pack_fields(message, fields, fields_num, fields_size))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_vector_ipcmsg_clear_ext(messages, zbx_ipc_message_free);
		return FAIL;
	}

	if (1 == messages->values_num)
		message->code = base_code;
	else
		message->code = base_code + 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack dependent item preprocessing fields into messages for        *
 *          sending to worker                                                 *
 *                                                                            *
 * Parameters: value    - [IN] the master item value                          *
 *             ts       - [IN] the master item value timestamp                *
 *             deps     - [IN] the dependent item data                        *
 *             deps_num - [IN] the number of dependent items                  *
 *             messages - [IN] the message queue                              *
 *                                                                            *
 * Return value: SUCCEED - the message was added successfully                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_pack_dep_request(const zbx_variant_t *value, const zbx_timespec_t *ts,
		const zbx_preproc_dep_t *deps, int deps_num, zbx_vector_ipcmsg_t *messages)
{
	zbx_packed_field_t	*offset, *fields;
	zbx_uint32_t		size;
	int			i, fields_num, batch_num, sent_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() items:%d", __func__, deps_num);

	fields_num = 6;		/* value (variant) + timestamp (timespec) + total items + batch of items */

	for (i = 0; i < deps_num; i++)
	{
		fields_num += 5; 		/* itemid + flags + value_type + ops_num + batch_num */
		fields_num += deps[i].steps_num * 4;
		fields_num += deps[i].history.values_num * 5;
	}

	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (size_t)fields_num * sizeof(zbx_packed_field_t));

	offset = fields;
	offset += preprocessor_pack_variant(offset, value);
	*offset++ = PACKED_FIELD(&ts->sec, sizeof(int));
	*offset++ = PACKED_FIELD(&ts->ns, sizeof(int));
	*offset++ = PACKED_FIELD(&deps_num, sizeof(int));
	*offset++ = PACKED_FIELD(&batch_num, sizeof(int));

	size = fields_calc_size(fields, 6);

	for (i = 0; i < deps_num; i++)
	{
		zbx_uint32_t		dep_size;
		zbx_packed_field_t	*dep = offset;
		int			dep_num;

		*offset++ = PACKED_FIELD(&deps[i].itemid, sizeof(zbx_uint64_t));
		*offset++ = PACKED_FIELD(&deps[i].flags, sizeof(unsigned char));
		*offset++ = PACKED_FIELD(&deps[i].value_type, sizeof(unsigned char));

		offset += preprocessor_pack_steps(offset, deps[i].steps, &deps[i].steps_num);
		offset += preprocessor_pack_history(offset, &deps[i].history, &deps[i].history.values_num);

		dep_num = (int)(offset - dep);
		dep_size = fields_calc_size(dep, dep_num);

		if (ZBX_PREPROC_MAX_PACKET_SIZE - dep_size < size)
		{
			batch_num = i - sent_num;

			if (SUCCEED != (preprocessor_append_packed_message(fields, (int)(dep - fields), size,
					ZBX_IPC_PREPROCESSOR_DEP_REQUEST, messages)))
			{
				goto out;
			}

			sent_num = i;

			offset = fields;
			*offset++ = PACKED_FIELD(&batch_num, sizeof(int));
			memmove(offset, dep, sizeof(zbx_packed_field_t) * (size_t)dep_num);
			offset += dep_num;

			size = fields_calc_size(fields, 1);
		}

		size += dep_size;
	}

	batch_num = i - sent_num;
	(void)preprocessor_append_packed_message(fields, (int)(offset - fields), size, ZBX_IPC_PREPROCESSOR_DEP_REQUEST,
			messages);
out:
	zbx_free(fields);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() messages:%d", __func__, messages->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack preprocessing result data into a single buffer that can be   *
 *          used in IPC                                                       *
 *                                                                            *
 * Parameters: data          - [OUT] memory buffer for packed data            *
 *             value         - [IN] result value                              *
 *             history       - [IN] item history data                         *
 *             error         - [IN] preprocessing error                       *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_result(unsigned char **data, zbx_variant_t *value,
		const zbx_vector_ptr_t *history, char *error)
{
	zbx_packed_field_t	*offset, *fields;
	zbx_uint32_t		size;
	zbx_ipc_message_t	message;
	int			history_num;

	history_num = history->values_num;

	/* 4 is a max field count (without history fields) */
	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (size_t)(4 + history_num * 5) * sizeof(zbx_packed_field_t));
	offset = fields;

	offset += preprocessor_pack_variant(offset, value);
	offset += preprocessor_pack_history(offset, history, &history_num);

	*offset++ = PACKED_FIELD(error, 0);

	zbx_ipc_message_init(&message);
	size = message_pack_data(&message, fields, (int)(offset - fields));
	*data = message.data;

	zbx_free(fields);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free dependent item preprocessing response                        *
 *                                                                            *
 * Parameters: results     - [OUT] the preprocessing results                  *
 *             results_num - [IN] the number of preprocessing results         *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_free_dep_results(zbx_preproc_dep_result_t *results, int results_num)
{
	int	i;

	for (i = 0; i < results_num; i++)
	{
		free_result(&results[i].value);
		zbx_free(results[i].error);
		zbx_vector_ptr_clear_ext(&results[i].history, (zbx_clean_func_t)zbx_preproc_op_history_free);
		zbx_vector_ptr_destroy(&results[i].history);
	}

	zbx_free(results);
}

void	zbx_preprocessor_result_init(zbx_preproc_result_buffer_t *buf, int total_num)
{
	if (ZBX_PREPROC_MAX_PACKET_SIZE < (buf->data_alloc = (zbx_uint32_t)total_num * 64))
		buf->data_alloc = ZBX_PREPROC_MAX_PACKET_SIZE;

	buf->data = (unsigned char *)zbx_malloc(NULL, buf->data_alloc);
	buf->data_offset = zbx_serialize_value(buf->data, total_num);
	/* reserve space for number of results in batch */
	buf->data_offset += (zbx_uint32_t)sizeof(int);

	/* reserve fields for result + one history record */
	buf->fields_num = 12;
	buf->fields = (zbx_packed_field_t *)zbx_malloc(NULL, (size_t)buf->fields_num * sizeof(zbx_packed_field_t));

	buf->results_num = 0;
	buf->code = ZBX_IPC_PREPROCESSOR_DEP_RESULT;
}

void	zbx_preprocessor_result_clear(zbx_preproc_result_buffer_t *buf)
{
	zbx_free(buf->data);
	zbx_free(buf->fields);
}

void	zbx_preprocessor_result_flush(zbx_preproc_result_buffer_t *buf, zbx_ipc_socket_t *socket)
{
	unsigned char	*batch_ptr;

	if (0 == buf->results_num)
		return;

	batch_ptr = buf->data;

	if (ZBX_IPC_PREPROCESSOR_DEP_RESULT == buf->code)
		batch_ptr += sizeof(int);

	(void)zbx_serialize_value(batch_ptr, buf->results_num);

	if (FAIL == zbx_ipc_socket_write(socket, buf->code, buf->data, buf->data_offset))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send preprocessing result");
		exit(EXIT_FAILURE);
	}
}

void	zbx_preprocessor_result_append(zbx_preproc_result_buffer_t *buf, zbx_uint64_t itemid, unsigned char flags,
		unsigned char value_type, const zbx_variant_t *value, const char *error,
		const zbx_vector_ptr_t *history, zbx_ipc_socket_t *socket)
{
	zbx_uint32_t		result_size;
	int			fields_num;
	zbx_packed_field_t	*offset;

	fields_num = 7; /* itemid + flags + value_type + value(variant) + error + history_num */
	fields_num += 5 * history->values_num;

	if (fields_num > buf->fields_num)
	{
		buf->fields = (zbx_packed_field_t *)zbx_realloc(buf->fields,
				(size_t)fields_num * sizeof(zbx_packed_field_t));
		buf->fields_num = fields_num;
	}

	offset = buf->fields;
	*offset++ = PACKED_FIELD(&itemid, sizeof(zbx_uint64_t));
	*offset++ = PACKED_FIELD(&flags, sizeof(unsigned char));
	*offset++ = PACKED_FIELD(&value_type, sizeof(unsigned char));
	offset += preprocessor_pack_variant(offset, value);
	*offset++ = PACKED_FIELD(error, 0);
	offset += preprocessor_pack_history(offset, history, &history->values_num);

	result_size = fields_calc_size(buf->fields, (int)(offset - buf->fields));

	if (ZBX_PREPROC_MAX_PACKET_SIZE - sizeof(int) < result_size)
	{
		zabbix_log(LOG_LEVEL_CRIT, "preprocessing result with size %u for item \"" ZBX_FS_UI64 "\" is too large",
				result_size, itemid);
		exit(EXIT_FAILURE);
	}

	if (ZBX_PREPROC_MAX_PACKET_SIZE - result_size < buf->data_offset)
	{
		zbx_preprocessor_result_flush(buf, socket);
		buf->code = ZBX_IPC_PREPROCESSOR_DEP_RESULT_CONT;
		/* reserve space for number of results in batch */
		buf->data_offset = sizeof(int);
		buf->results_num = 0;
	}

	if (buf->data_offset + result_size > buf->data_alloc)
	{
		while (buf->data_offset + result_size > buf->data_alloc)
		{
			if (ZBX_PREPROC_MAX_PACKET_SIZE / 2 < buf->data_alloc)
				buf->data_alloc = ZBX_PREPROC_MAX_PACKET_SIZE;
			else
				buf->data_alloc *= 2;
		}

		buf->data = (unsigned char *)zbx_realloc(buf->data, buf->data_alloc);
	}

	buf->data_offset += fields_pack(buf->fields, (int)(offset - buf->fields), buf->data + buf->data_offset);
	buf->results_num++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack preprocessing result data into a single buffer that can be   *
 *          used in IPC                                                       *
 *                                                                            *
 * Parameters: data          - [OUT] memory buffer for packed data            *
 *             ret           - [IN] return code                               *
 *             results       - [IN] the preprocessing step results            *
 *             results_num   - [IN] the number of preprocessing step results  *
 *             history       - [IN] item history data                         *
 *             error         - [IN] preprocessing error                       *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_test_result(unsigned char **data, const zbx_preproc_result_t *results,
		int results_num, const zbx_vector_ptr_t *history, const char *error)
{
	zbx_packed_field_t	*offset, *fields;
	zbx_uint32_t		size;
	zbx_ipc_message_t	message;
	int			i, history_num;

	history_num = history->values_num;

	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (size_t)(3 + history_num * 5 + results_num * 4) *
			sizeof(zbx_packed_field_t));
	offset = fields;

	*offset++ = PACKED_FIELD(&results_num, sizeof(int));

	for (i = 0; i < results_num; i++)
	{
		offset += preprocessor_pack_variant(offset, &results[i].value);
		*offset++ = PACKED_FIELD(results[i].error, 0);
		*offset++ = PACKED_FIELD(&results[i].action, sizeof(unsigned char));
	}

	offset += preprocessor_pack_history(offset, history, &history_num);

	*offset++ = PACKED_FIELD(error, 0);

	zbx_ipc_message_init(&message);
	size = message_pack_data(&message, fields, offset - fields);
	*data = message.data;

	zbx_free(fields);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack diagnostic statistics data into a single buffer that can be  *
 *          used in IPC                                                       *
 * Parameters: data       - [OUT] memory buffer for packed data               *
 *             total      - [IN] the number of values                         *
 *             queued     - [IN] the number of values waiting to be           *
 *                               preprocessed                                 *
 *             processing - [IN] the number of values being preprocessed      *
 *             done       - [IN] the number of values waiting to be flushed   *
 *                               that are either preprocessed or did not      *
 *                               require preprocessing                        *
 *             pending    - [IN] the number of values pending to be           *
 *                               preprocessed after previous value for        *
 *                               example delta, throttling depends on         *
 *                               previous value                               *
 *             data       - [IN] IPC data buffer                              *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_diag_stats(unsigned char **data, int total, int queued, int processing, int done,
		int pending)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, total);
	zbx_serialize_prepare_value(data_len, queued);
	zbx_serialize_prepare_value(data_len, processing);
	zbx_serialize_prepare_value(data_len, done);
	zbx_serialize_prepare_value(data_len, pending);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, total);
	ptr += zbx_serialize_value(ptr, queued);
	ptr += zbx_serialize_value(ptr, processing);
	ptr += zbx_serialize_value(ptr, done);
	(void)zbx_serialize_value(ptr, pending);

	return data_len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack top request data into a single buffer that can be used in IPC*
 *                                                                            *
 * Parameters: data  - [OUT] memory buffer for packed data                    *
 *             field - [IN] the sort field                                    *
 *             limit - [IN] the number of top values to return                *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_top_items_request(unsigned char **data, int limit)
{
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, limit);
	*data = (unsigned char *)zbx_malloc(NULL, data_len);
	(void)zbx_serialize_value(*data, limit);

	return data_len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack top result data into a single buffer that can be used in IPC *
 *                                                                            *
 * Parameters: data      - [OUT] memory buffer for packed data                *
 *             items     - [IN] the array of item references                  *
 *             items_num - [IN] the number of items                           *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_top_items_result(unsigned char **data, zbx_preproc_item_stats_t **items,
		int items_num)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, item_len = 0;
	int		i;

	if (0 != items_num)
	{
		zbx_serialize_prepare_value(item_len, items[0]->itemid);
		zbx_serialize_prepare_value(item_len, items[0]->values_num);
		zbx_serialize_prepare_value(item_len, items[0]->steps_num);
	}

	zbx_serialize_prepare_value(data_len, items_num);
	data_len += item_len * (zbx_uint32_t)items_num;
	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, items_num);

	for (i = 0; i < items_num; i++)
	{
		ptr += zbx_serialize_value(ptr, items[i]->itemid);
		ptr += zbx_serialize_value(ptr, items[i]->values_num);
		ptr += zbx_serialize_value(ptr, items[i]->steps_num);
	}

	return data_len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack item value data from IPC data buffer                       *
 *                                                                            *
 * Parameters: value    - [OUT] unpacked item value                           *
 *             data     - [IN]  IPC data buffer                               *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_unpack_value(zbx_preproc_item_value_t *value, unsigned char *data)
{
	zbx_uint32_t	value_len;
	zbx_timespec_t	*timespec = NULL;
	AGENT_RESULT	*agent_result = NULL;
	zbx_log_t	*log = NULL;
	unsigned char	*offset = data, ts_marker, result_marker, log_marker;

	offset += zbx_deserialize_uint64(offset, &value->itemid);
	offset += zbx_deserialize_uint64(offset, &value->hostid);
	offset += zbx_deserialize_char(offset, &value->item_value_type);
	offset += zbx_deserialize_char(offset, &value->item_flags);
	offset += zbx_deserialize_char(offset, &value->state);
	offset += zbx_deserialize_str(offset, &value->error, value_len);
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
		offset += zbx_deserialize_str(offset, &agent_result->str, value_len);
		offset += zbx_deserialize_str(offset, &agent_result->text, value_len);
		offset += zbx_deserialize_str(offset, &agent_result->msg, value_len);
		offset += zbx_deserialize_int(offset, &agent_result->type);
		offset += zbx_deserialize_int(offset, &agent_result->mtime);

		offset += zbx_deserialize_char(offset, &log_marker);
		if (0 != log_marker)
		{
			log = (zbx_log_t *)zbx_malloc(NULL, sizeof(zbx_log_t));

			offset += zbx_deserialize_str(offset, &log->value, value_len);
			offset += zbx_deserialize_str(offset, &log->source, value_len);
			offset += zbx_deserialize_int(offset, &log->timestamp);
			offset += zbx_deserialize_int(offset, &log->severity);
			offset += zbx_deserialize_int(offset, &log->logeventid);
		}

		agent_result->log = log;
	}

	value->result = agent_result;

	return (int)(offset - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing task data from IPC data buffer               *
 *                                                                            *
 * Parameters: itemid        - [OUT] itemid                                   *
 *             value_type    - [OUT] item value type                          *
 *             ts            - [OUT] value timestamp                          *
 *             value         - [OUT] item value                               *
 *             history       - [OUT] history data                             *
 *             steps         - [OUT] preprocessing steps                      *
 *             steps_num     - [OUT] preprocessing step count                 *
 *             data          - [IN] IPC data buffer                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_task(zbx_uint64_t *itemid, unsigned char *value_type, zbx_timespec_t **ts,
		zbx_variant_t *value, zbx_vector_ptr_t *history, zbx_preproc_op_t **steps,
		int *steps_num, const unsigned char *data)
{
	const unsigned char		*offset = data;
	unsigned char 			ts_marker;
	zbx_timespec_t			*timespec = NULL;

	offset += zbx_deserialize_uint64(offset, itemid);
	offset += zbx_deserialize_char(offset, value_type);
	offset += zbx_deserialize_char(offset, &ts_marker);

	if (0 != ts_marker)
	{
		timespec = (zbx_timespec_t *)zbx_malloc(NULL, sizeof(zbx_timespec_t));

		offset += zbx_deserialize_int(offset, &timespec->sec);
		offset += zbx_deserialize_int(offset, &timespec->ns);
	}

	*ts = timespec;

	offset += preprocesser_unpack_variant(offset, value);
	offset += preprocesser_unpack_history(offset, history);
	(void)preprocessor_unpack_steps(offset, steps, steps_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free preprocessing steps                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_free_steps(zbx_preproc_op_t *steps, int steps_num)
{
	while (0 < steps_num)
	{
		steps_num--;
		zbx_free(steps[steps_num].params);
		zbx_free(steps[steps_num].error_handler_params);
	}

	zbx_free(steps);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free dependent item preprocessing request                         *
 *                                                                            *
 * Parameters: deps     - [OUT] the dependent items                           *
 *             deps_num - [IN] the number of dependent items                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_free_deps(zbx_preproc_dep_t *deps, int deps_num)
{
	int	i;

	for (i = 0; i < deps_num; i++)
	{
		zbx_preprocessor_free_steps(deps[i].steps, deps[i].steps_num);
		zbx_vector_ptr_clear_ext(&deps[i].history, (zbx_clean_func_t)zbx_preproc_op_history_free);
		zbx_vector_ptr_destroy(&deps[i].history);
	}

	zbx_free(deps);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack dependent item preprocessing request                       *
 *                                                                            *
 * Parameters: data - [IN] serialized dependent item preprocessing data       *
 *             dep  - [OUT] unpacked dependent item preprocessing data        *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	preprocessor_unpack_dep(const unsigned char *data, zbx_preproc_dep_t *dep)
{
	const unsigned char	*offset = data;

	offset += zbx_deserialize_value(offset, &dep->itemid);
	offset += zbx_deserialize_value(offset, &dep->flags);
	offset += zbx_deserialize_value(offset, &dep->value_type);
	offset += preprocessor_unpack_steps(offset, &dep->steps, &dep->steps_num);

	zbx_vector_ptr_create(&dep->history);
	offset += preprocesser_unpack_history(offset, &dep->history);

	return offset - data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack initial dependent item preprocessing request               *
 *                                                                            *
 * Parameters: ts        - [OUT] the value timestamp                          *
 *             value     - [OUT] the master item value                        *
 *             total_num - [OUT] the total number of dependent items          *
 *             deps      - [OUT] the dependent items                          *
 *             deps_num  - [OUT] the number of dependent items in batch       *
 *             data      - [IN] the data to unpack                            *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_dep_task(zbx_timespec_t *ts, zbx_variant_t *value, int *total_num,
		zbx_preproc_dep_t **deps, int *deps_num, const unsigned char *data)
{
	const unsigned char	*offset = data;
	int			i;

	offset += preprocesser_unpack_variant(offset, value);
	offset += zbx_deserialize_value(offset, &ts->sec);
	offset += zbx_deserialize_value(offset, &ts->ns);

	offset += zbx_deserialize_value(offset, total_num);
	offset += zbx_deserialize_value(offset, deps_num);
	*deps = (zbx_preproc_dep_t *)zbx_malloc(NULL, (size_t)*total_num * sizeof(zbx_preproc_dep_t));

	for (i = 0; i < *deps_num; i++)
		offset += preprocessor_unpack_dep(offset, *deps + i);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack following dependent item preprocessing request             *
 *                                                                            *
 * Parameters: ts       - [OUT] the value timestamp                           *
 *             value    - [OUT] the master item value                         *
 *             deps     - [OUT] the dependent items                           *
 *             deps_num - [OUT] the number of dependent items                 *
 *             data     - [IN] the data to unpack                             *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_dep_task_cont(zbx_preproc_dep_t *deps, int *deps_num, const unsigned char *data)
{
	const unsigned char	*offset = data;
	int			i, batch_num;

	offset += zbx_deserialize_value(offset, &batch_num);

	for (i = 0; i < batch_num; i++)
		offset += preprocessor_unpack_dep(offset, deps + i);

	*deps_num += batch_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing task data from IPC data buffer               *
 *                                                                            *
 * Parameters: value         - [OUT] result value                             *
 *             history       - [OUT] item history data                        *
 *             error         - [OUT] preprocessing error                      *
 *             data          - [IN] IPC data buffer                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_result(zbx_variant_t *value, zbx_vector_ptr_t *history, char **error,
		const unsigned char *data)
{
	zbx_uint32_t		value_len;
	const unsigned char	*offset = data;

	offset += preprocesser_unpack_variant(offset, value);
	offset += preprocesser_unpack_history(offset, history);

	(void)zbx_deserialize_str(offset, error, value_len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert variant value to AGENT_RESULT                             *
 *                                                                            *
 * Parameters: value      - [IN] the value to convert                         *
 *             value_type - [IN] the item value type                          *
 *             result     - [OUT] the result                                  *
 *                                                                            *
 ******************************************************************************/
static void	agent_result_set_value(zbx_variant_t *value, zbx_item_value_type_t value_type, AGENT_RESULT *result,
		char **error)
{
	unsigned char	type;
	zbx_log_t	*log;

	init_result(result);

	if (NULL != *error)
		return;

	if (ZBX_VARIANT_NONE == value->type)
		return;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			type = ZBX_VARIANT_DBL;
			break;
		case ITEM_VALUE_TYPE_UINT64:
			type = ZBX_VARIANT_UI64;
			break;
		default:
			/* ITEM_VALUE_TYPE_STR, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_LOG */
			type = ZBX_VARIANT_STR;
	}

	if (FAIL == zbx_variant_convert(value, type))
	{
		*error = zbx_dsprintf(*error, "Value \"%s\" of type \"%s\" is not suitable for"
			" value type \"%s\"", zbx_variant_value_desc(value), zbx_variant_type_desc(value),
			zbx_item_value_type_string((zbx_item_value_type_t)value_type));

		return;
	}

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			SET_DBL_RESULT(result, value->data.dbl);
			break;
		case ITEM_VALUE_TYPE_STR:
			SET_STR_RESULT(result, value->data.str);
			zbx_variant_set_none(value);
			break;
		case ITEM_VALUE_TYPE_TEXT:
			SET_TEXT_RESULT(result, value->data.str);
			zbx_variant_set_none(value);
			break;
		case ITEM_VALUE_TYPE_LOG:
			log = (zbx_log_t *)zbx_malloc(NULL, sizeof(zbx_log_t));
			memset(log, 0, sizeof(zbx_log_t));
			log->value = value->data.str;
			SET_LOG_RESULT(result, log);
			zbx_variant_set_none(value);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			SET_UI64_RESULT(result, value->data.ui64);
			break;
		default:
			*error = zbx_dsprintf(*error, "Unsupported value \"%s\" of type \"%s\"",
					zbx_variant_value_desc(value), zbx_variant_type_desc(value));
			return;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack dependent item preprocessing result                        *
 *                                                                            *
 * Parameters: data   - [IN] IPC data buffer                                  *
 *             result - [OUT] the preprocessing result                        *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	preprocessor_unpack_dep_result(const unsigned char *data, zbx_preproc_dep_result_t *result)
{
	const unsigned char	*offset = data;
	zbx_uint32_t		error_len;
	zbx_variant_t		value;

	offset += zbx_deserialize_value(offset, &result->itemid);
	offset += zbx_deserialize_value(offset, &result->flags);
	offset += zbx_deserialize_value(offset, &result->value_type);
	offset += preprocesser_unpack_variant(offset, &value);
	offset += zbx_deserialize_str(offset, &result->error, error_len);

	zbx_vector_ptr_create(&result->history);
	offset += preprocesser_unpack_history(offset, &result->history);

	agent_result_set_value(&value, result->value_type, &result->value, &result->error);

	zbx_variant_clear(&value);

	return offset - data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing results                                      *
 *                                                                            *
 * Parameters: total_num   - [OUT] the total number of results                *
 *             results_num - [OUT] the number of results in this batch        *
 *             results     - [OUT] the preprocessing results                  *
 *             data        - [IN] the data to unpack                          *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_dep_result(int *total_num, int *results_num, zbx_preproc_dep_result_t **results,
		const unsigned char *data)
{
	const unsigned char	*offset = data;
	int			i;

	offset += zbx_deserialize_value(offset, total_num);
	offset += zbx_deserialize_value(offset, results_num);
	*results = (zbx_preproc_dep_result_t *)zbx_malloc(NULL, (size_t)*total_num * sizeof(zbx_preproc_dep_result_t));

	for (i = 0; i < *results_num; i++)
		offset += preprocessor_unpack_dep_result(offset, *results + i);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack following preprocessing results                            *
 *                                                                            *
 * Parameters: results_num - [OUT] the number of results in this batch        *
 *             results     - [OUT] the preprocessing results                  *
 *             data        - [IN] the data to unpack                          *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_dep_result_cont(int *results_num, zbx_preproc_dep_result_t *results,
		const unsigned char *data)
{
	const unsigned char	*offset = data;
	int			i, batch_num;

	offset += zbx_deserialize_value(offset, &batch_num);

	for (i = 0; i < batch_num; i++)
		offset += preprocessor_unpack_dep_result(offset, results + i);

	*results_num += batch_num;
}
/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing test data from IPC data buffer               *
 *                                                                            *
 * Parameters: results       - [OUT] the preprocessing step results           *
 *             history       - [OUT] item history data                        *
 *             error         - [OUT] preprocessing error                      *
 *             data          - [IN] IPC data buffer                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_test_result(zbx_vector_ptr_t *results, zbx_vector_ptr_t *history,
		char **error, const unsigned char *data)
{
	zbx_uint32_t		value_len;
	const unsigned char	*offset = data;
	int			i, results_num;
	zbx_preproc_result_t	*result;

	offset += zbx_deserialize_int(offset, &results_num);

	zbx_vector_ptr_reserve(results, (size_t)results_num);

	for (i = 0; i < results_num; i++)
	{
		result = (zbx_preproc_result_t *)zbx_malloc(NULL, sizeof(zbx_preproc_result_t));
		offset += preprocesser_unpack_variant(offset, &result->value);
		offset += zbx_deserialize_str(offset, &result->error, value_len);
		offset += zbx_deserialize_char(offset, &result->action);
		zbx_vector_ptr_append(results, result);
	}

	offset += preprocesser_unpack_history(offset, history);

	(void)zbx_deserialize_str(offset, error, value_len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing test data from IPC data buffer               *
 *                                                                            *
 * Parameters: total      - [OUT] the number of values                        *
 *             queued     - [OUT] the number of values waiting to be          *
 *                                preprocessed                                *
 *             processing - [OUT] the number of values being preprocessed     *
 *             done       - [OUT] the number of values waiting to be flushed  *
 *                                that are either preprocessed or did not     *
 *                                require preprocessing                       *
 *             pending    - [OUT] the number of values pending to be          *
 *                                preprocessed after previous value for       *
 *                                example delta, throttling depends on        *
 *                                previous value                              *
 *             data       - [IN] IPC data buffer                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_diag_stats(int *total, int *queued, int *processing, int *done,
		int *pending, const unsigned char *data)
{
	const unsigned char	*offset = data;

	offset += zbx_deserialize_int(offset, total);
	offset += zbx_deserialize_int(offset, queued);
	offset += zbx_deserialize_int(offset, processing);
	offset += zbx_deserialize_int(offset, done);
	(void)zbx_deserialize_int(offset, pending);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing test data from IPC data buffer               *
 *                                                                            *
 * Parameters: data  - [OUT] memory buffer for packed data                    *
 *             limit - [IN] the number of top values to return                *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_top_request(int *limit, const unsigned char *data)
{
	(void)zbx_deserialize_value(data, limit);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing test data from IPC data buffer               *
 *                                                                            *
 * Parameters: items - [OUT] the item diag data                               *
 *             data  - [IN] memory buffer for packed data                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_top_result(zbx_vector_ptr_t *items, const unsigned char *data)
{
	int	i, items_num;

	data += zbx_deserialize_value(data, &items_num);

	if (0 != items_num)
	{
		zbx_vector_ptr_reserve(items, (size_t)items_num);

		for (i = 0; i < items_num; i++)
		{
			zbx_preproc_item_stats_t	*item;

			item = (zbx_preproc_item_stats_t *)zbx_malloc(NULL, sizeof(zbx_preproc_item_stats_t));
			data += zbx_deserialize_value(data, &item->itemid);
			data += zbx_deserialize_value(data, &item->values_num);
			data += zbx_deserialize_value(data, &item->steps_num);
			zbx_vector_ptr_append(items, item);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends command to preprocessor manager                             *
 *                                                                            *
 * Parameters: code     - [IN] message code                                   *
 *             data     - [IN] message data                                   *
 *             size     - [IN] message data size                              *
 *             response - [OUT] response message (can be NULL if response is  *
 *                              not requested)                                *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_send(zbx_uint32_t code, unsigned char *data, zbx_uint32_t size,
		zbx_ipc_message_t *response)
{
	char			*error = NULL;
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
 * Purpose: perform item value preprocessing and dependent item processing    *
 *                                                                            *
 * Parameters: itemid          - [IN] the itemid                              *
 *             itemid          - [IN] the hostid                              *
 *             item_value_type - [IN] the item value type                     *
 *             item_flags      - [IN] the item flags (e. g. lld rule)         *
 *             result          - [IN] agent result containing the value       *
 *                               to add                                       *
 *             ts              - [IN] the value timestamp                     *
 *             state           - [IN] the item state                          *
 *             error           - [IN] the error message in case item state is *
 *                               ITEM_STATE_NOTSUPPORTED                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocess_item_value(zbx_uint64_t itemid, zbx_uint64_t hostid, unsigned char item_value_type,
		unsigned char item_flags, AGENT_RESULT *result, zbx_timespec_t *ts, unsigned char state, char *error)
{
	zbx_preproc_item_value_t	value = {.itemid = itemid, .hostid = hostid, .item_value_type = item_value_type,
					.error = error, .item_flags = item_flags, .state = state, .ts = ts,
					.result = result};
	size_t				value_len = 0, len;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ITEM_STATE_NORMAL == state)
	{
		if (0 != ISSET_STR(result))
			value_len = strlen(result->str);

		if (0 != ISSET_TEXT(result))
		{
			if (value_len < (len = strlen(result->text)))
				value_len = len;
		}

		if (0 != ISSET_LOG(result))
		{
			if (value_len < (len = strlen(result->log->value)))
				value_len = len;
		}

		if (ZBX_MAX_RECV_DATA_SIZE < value_len)
		{
			value.result = NULL;
			value.state = ITEM_STATE_NOTSUPPORTED;
			value.error = "Value is too large.";
		}
	}

	if (0 == preprocessor_pack_value(&cached_message, &value))
	{
		zbx_preprocessor_flush();
		preprocessor_pack_value(&cached_message, &value);
	}

	if (MAX_VALUES_LOCAL < ++cached_values)
		zbx_preprocessor_flush();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send flush command to preprocessing manager                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_flush(void)
{
	if (0 < cached_message.size)
	{
		preprocessor_send(ZBX_IPC_PREPROCESSOR_REQUEST, cached_message.data, cached_message.size, NULL);

		zbx_ipc_message_clean(&cached_message);
		zbx_ipc_message_init(&cached_message);
		cached_values = 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get queue size (enqueued value count) of preprocessing manager    *
 *                                                                            *
 * Return value: enqueued item count                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_preprocessor_get_queue_size(void)
{
	zbx_uint64_t		size;
	zbx_ipc_message_t	message;

	zbx_ipc_message_init(&message);
	preprocessor_send(ZBX_IPC_PREPROCESSOR_QUEUE, NULL, 0, &message);
	memcpy(&size, message.data, sizeof(zbx_uint64_t));
	zbx_ipc_message_clean(&message);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees preprocessing step                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_preproc_op_free(zbx_preproc_op_t *op)
{
	zbx_free(op->params);
	zbx_free(op->error_handler_params);
	zbx_free(op);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees preprocessing step test result                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_preproc_result_free(zbx_preproc_result_t *result)
{
	zbx_variant_clear(&result->value);
	zbx_free(result->error);
	zbx_free(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: packs preprocessing step request for serialization                *
 *                                                                            *
 * Return value: The size of packed data                                      *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	preprocessor_pack_test_request(unsigned char **data, unsigned char value_type,
		const char *value, const zbx_timespec_t *ts, const zbx_vector_ptr_t *history,
		const zbx_vector_ptr_t *steps)
{
	zbx_packed_field_t	*offset, *fields;
	zbx_uint32_t		size;
	int			i, history_num;
	zbx_ipc_message_t	message;

	history_num = (NULL != history ? history->values_num : 0);

	/* 6 is a max field count (without preprocessing step and history fields) */
	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (size_t)(6 + steps->values_num * 4 + history_num * 5)
			* sizeof(zbx_packed_field_t));

	offset = fields;

	*offset++ = PACKED_FIELD(&value_type, sizeof(unsigned char));
	*offset++ = PACKED_FIELD(value, 0);
	*offset++ = PACKED_FIELD(&ts->sec, sizeof(int));
	*offset++ = PACKED_FIELD(&ts->ns, sizeof(int));

	offset += preprocessor_pack_history(offset, history, &history_num);

	*offset++ = PACKED_FIELD(&steps->values_num, sizeof(int));

	for (i = 0; i < steps->values_num; i++)
		offset += preprocessor_pack_step(offset, (zbx_preproc_op_t *)steps->values[i]);

	zbx_ipc_message_init(&message);
	size = message_pack_data(&message, fields, offset - fields);
	*data = message.data;
	zbx_free(fields);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing test request data from IPC data buffer       *
 *                                                                            *
 * Parameters: value_type    - [OUT] item value type                          *
 *             value         - [OUT] the value                                *
 *             ts            - [OUT] value timestamp                          *
 *             value         - [OUT] item value                               *
 *             history       - [OUT] history data                             *
 *             steps         - [OUT] preprocessing steps                      *
 *             steps_num     - [OUT] preprocessing step count                 *
 *             data          - [IN] IPC data buffer                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_test_request(unsigned char *value_type, char **value, zbx_timespec_t *ts,
		zbx_vector_ptr_t *history, zbx_preproc_op_t **steps, int *steps_num, const unsigned char *data)
{
	zbx_uint32_t			value_len;
	const unsigned char		*offset = data;

	offset += zbx_deserialize_char(offset, value_type);
	offset += zbx_deserialize_str(offset, value, value_len);
	offset += zbx_deserialize_int(offset, &ts->sec);
	offset += zbx_deserialize_int(offset, &ts->ns);

	offset += preprocesser_unpack_history(offset, history);
	(void)preprocessor_unpack_steps(offset, steps, steps_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: tests item preprocessing with the specified input value and steps *
 *                                                                            *
 ******************************************************************************/
int	zbx_preprocessor_test(unsigned char value_type, const char *value, const zbx_timespec_t *ts,
		const zbx_vector_ptr_t *steps, zbx_vector_ptr_t *results, zbx_vector_ptr_t *history,
		char **preproc_error, char **error)
{
	unsigned char	*data = NULL;
	zbx_uint32_t	size;
	int		ret = FAIL;
	unsigned char	*result;

	size = preprocessor_pack_test_request(&data, value_type, value, ts, history, steps);

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_PREPROCESSING, ZBX_IPC_PREPROCESSOR_TEST_REQUEST,
			SEC_PER_MIN, data, size, &result, error))
	{
		goto out;
	}

	zbx_preprocessor_unpack_test_result(results, history, preproc_error, result);
	zbx_free(result);

	ret = SUCCEED;
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get preprocessing manager diagnostic statistics                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_preprocessor_get_diag_stats(int *total, int *queued, int *processing, int *done,
		int *pending, char **error)
{
	unsigned char	*result;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_PREPROCESSING, ZBX_IPC_PREPROCESSOR_DIAG_STATS,
			SEC_PER_MIN, NULL, 0, &result, error))
	{
		return FAIL;
	}

	zbx_preprocessor_unpack_diag_stats(total, queued, processing, done, pending, result);
	zbx_free(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the top N items by the number of queued values                *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_get_top_items(int limit, zbx_vector_ptr_t *items, char **error, zbx_uint32_t code)
{
	int		ret;
	unsigned char	*data, *result;
	zbx_uint32_t	data_len;

	data_len = zbx_preprocessor_pack_top_items_request(&data, limit);

	if (SUCCEED != (ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_PREPROCESSING, code, SEC_PER_MIN, data, data_len,
			&result, error)))
	{
		goto out;
	}

	zbx_preprocessor_unpack_top_result(items, result);
	zbx_free(result);
out:
	zbx_free(data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the top N items by the number of queued values                *
 *                                                                            *
 ******************************************************************************/
int	zbx_preprocessor_get_top_items(int limit, zbx_vector_ptr_t *items, char **error)
{
	return preprocessor_get_top_items(limit, items, error, ZBX_IPC_PREPROCESSOR_TOP_ITEMS);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the oldest items with preprocessing still in queue            *
 *                                                                            *
 ******************************************************************************/
int	zbx_preprocessor_get_top_oldest_preproc_items(int limit, zbx_vector_ptr_t *items, char **error)
{
	return preprocessor_get_top_items(limit, items, error, ZBX_IPC_PREPROCESSOR_TOP_OLDEST_PREPROC_ITEMS);
}
