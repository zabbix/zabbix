/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "preproc.h"
#include "preprocessing.h"
#include "preproc_history.h"

#define PACKED_FIELD_RAW	0
#define PACKED_FIELD_STRING	1
#define MAX_VALUES_LOCAL	256

/* packed field data description */
typedef struct
{
	const void	*value;	/* value to be packed */
	zbx_uint32_t	size;	/* size of a value (can be 0 for strings) */
	unsigned char	type;	/* field type */
}
zbx_packed_field_t;

#define PACKED_FIELD(value, size)	\
		(zbx_packed_field_t){(value), (size), (0 == (size) ? PACKED_FIELD_STRING : PACKED_FIELD_RAW)};

static zbx_ipc_message_t	cached_message;
static int			cached_values;

/******************************************************************************
 *                                                                            *
 * Function: message_pack_data                                                *
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
	int 			i;
	zbx_uint32_t		data_size = 0;
	zbx_uint64_t		field_size;
	unsigned char		*offset = NULL;
	const zbx_uint64_t	max_uint32 = ~(zbx_uint32_t)0;

	if (NULL != message)
	{
		/* recursive call to calculate required buffer size */
		data_size = message_pack_data(NULL, fields, count);

		if (0 == data_size || max_uint32 - message->size < data_size)
			return 0;

		message->size += data_size;
		message->data = (unsigned char *)zbx_realloc(message->data, message->size);
		offset = message->data + (message->size - data_size);
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
				if (0 != field_size && NULL != fields[i].value)
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
				field_size = (NULL != fields[i].value) ? strlen((const char *)fields[i].value) + 1 : 0;
				fields[i].size = (zbx_uint32_t)field_size;

				field_size += sizeof(zbx_uint32_t);
			}

			if (field_size + data_size > max_uint32)
				return 0;

			data_size += (zbx_uint32_t)field_size;
		}
	}

	return data_size;
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_pack_value                                          *
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
	zbx_packed_field_t	fields[23], *offset = fields;	/* 23 - max field count */
	unsigned char		ts_marker, result_marker, log_marker;

	ts_marker = (NULL != value->ts);
	result_marker = (NULL != value->result_ptr->result);

	*offset++ = PACKED_FIELD(&value->itemid, sizeof(zbx_uint64_t));
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

	if (NULL != value->result_ptr->result)
	{

		*offset++ = PACKED_FIELD(&value->result_ptr->result->lastlogsize, sizeof(zbx_uint64_t));
		*offset++ = PACKED_FIELD(&value->result_ptr->result->ui64, sizeof(zbx_uint64_t));
		*offset++ = PACKED_FIELD(&value->result_ptr->result->dbl, sizeof(double));
		*offset++ = PACKED_FIELD(value->result_ptr->result->str, 0);
		*offset++ = PACKED_FIELD(value->result_ptr->result->text, 0);
		*offset++ = PACKED_FIELD(value->result_ptr->result->msg, 0);
		*offset++ = PACKED_FIELD(&value->result_ptr->result->type, sizeof(int));
		*offset++ = PACKED_FIELD(&value->result_ptr->result->mtime, sizeof(int));

		log_marker = (NULL != value->result_ptr->result->log);
		*offset++ = PACKED_FIELD(&log_marker, sizeof(unsigned char));
		if (NULL != value->result_ptr->result->log)
		{
			*offset++ = PACKED_FIELD(value->result_ptr->result->log->value, 0);
			*offset++ = PACKED_FIELD(value->result_ptr->result->log->source, 0);
			*offset++ = PACKED_FIELD(&value->result_ptr->result->log->timestamp, sizeof(int));
			*offset++ = PACKED_FIELD(&value->result_ptr->result->log->severity, sizeof(int));
			*offset++ = PACKED_FIELD(&value->result_ptr->result->log->logeventid, sizeof(int));
		}
	}

	return message_pack_data(message, fields, offset - fields);
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_pack_variant                                        *
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
 * Function: preprocessor_pack_history                                        *
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
 * Function: preprocessor_pack_step                                           *
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
 * Function: preprocessor_pack_steps                                          *
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
 * Function: preprocesser_unpack_variant                                      *
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

	return offset - data;
}

/******************************************************************************
 *                                                                            *
 * Function: preprocesser_unpack_history                                      *
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
		zbx_vector_ptr_reserve(history, history_num);

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

	return offset - data;
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_unpack_step                                         *
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
	offset += zbx_deserialize_str_ptr(offset, step->params, value_len);
	offset += zbx_deserialize_char(offset, &step->error_handler);
	offset += zbx_deserialize_str_ptr(offset, step->error_handler_params, value_len);

	return offset - data;
}

/******************************************************************************
 *                                                                            *
 * Function: preprocessor_unpack_steps                                        *
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
		*steps = (zbx_preproc_op_t *)zbx_malloc(NULL, sizeof(zbx_preproc_op_t) * (*steps_num));
		for (i = 0; i < *steps_num; i++)
			offset += preprocessor_unpack_step(offset, *steps + i);
	}
	else
		*steps = NULL;

	return offset - data;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_pack_task                                       *
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
	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (9 + steps_num * 4 + history_num * 5)
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
	size = message_pack_data(&message, fields, offset - fields);
	*data = message.data;
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
	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (4 + history_num * 5) * sizeof(zbx_packed_field_t));
	offset = fields;

	offset += preprocessor_pack_variant(offset, value);
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
 * Function: zbx_preprocessor_pack_test_result                                *
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

	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (3 + history_num * 5 + results_num * 4) *
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
 * Function: zbx_preprocessor_pack_diag_stats                                 *
 *                                                                            *
 * Purpose: pack diagnostic statistics data into a single buffer that can be  *
 *          used in IPC                                                       *
 * Parameters: data               - [OUT] memory buffer for packed data       *
 *             values_num         - [IN] the number of queued values          *
 *             values_preproc_num - [IN] the number of queued values with     *
 *                                       preprocessing steps                  *
 *             data               - [IN] IPC data buffer                      *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_diag_stats(unsigned char **data, int values_num, int values_preproc_num)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, values_num);
	zbx_serialize_prepare_value(data_len, values_preproc_num);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, values_num);
	(void)zbx_serialize_value(ptr, values_preproc_num);

	return data_len;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_pack_top_request                                *
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
 * Function: zbx_preprocessor_pack_top_result                                 *
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
	data_len += item_len * items_num;
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
 * Function: zbx_preprocessor_unpack_value                                    *
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

	value->result_ptr = (zbx_result_ptr_t *)zbx_malloc(NULL, sizeof(zbx_result_ptr_t));
	value->result_ptr->result = agent_result;
	value->result_ptr->refcount = 1;

	return offset - data;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_unpack_task                                     *
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
 * Function: zbx_preprocessor_unpack_result                                   *
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
 * Function: zbx_preprocessor_unpack_test_result                              *
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

	zbx_vector_ptr_reserve(results, results_num);

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
 * Function: zbx_preprocessor_unpack_diag_stats                               *
 *                                                                            *
 * Purpose: unpack preprocessing test data from IPC data buffer               *
 *                                                                            *
 * Parameters: values_num         - [OUT] the number of queued values         *
 *             values_preproc_num - [OUT] the number of queued values with    *
 *                                       preprocessing steps                  *
 *             data               - [IN] IPC data buffer                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_diag_stats(int *values_num, int *values_preproc_num, const unsigned char *data)
{
	const unsigned char	*offset = data;

	offset += zbx_deserialize_int(offset, values_num);
	(void)zbx_deserialize_int(offset, values_preproc_num);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_unpack_top_request                              *
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
 * Function: zbx_preprocessor_unpack_top_request                              *
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
		zbx_vector_ptr_reserve(items, items_num);

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
 * Function: preprocessor_send                                                *
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
 * Function: zbx_preprocess_item_value                                        *
 *                                                                            *
 * Purpose: perform item value preprocessing and dependend item processing    *
 *                                                                            *
 * Parameters: itemid          - [IN] the itemid                              *
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
void	zbx_preprocess_item_value(zbx_uint64_t itemid, unsigned char item_value_type, unsigned char item_flags,
		AGENT_RESULT *result, zbx_timespec_t *ts, unsigned char state, char *error)
{
	zbx_preproc_item_value_t	value = {.itemid = itemid, .item_value_type = item_value_type,
					.error = error, .item_flags = item_flags, .state = state, .ts = ts};
	zbx_result_ptr_t		result_ptr = {.result = result};
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
			result_ptr.result = NULL;
			value.state = ITEM_STATE_NOTSUPPORTED;
			value.error = "Value is too large.";
		}
	}

	value.result_ptr = &result_ptr;

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
 * Function: zbx_preprocessor_flush                                           *
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
 * Function: zbx_preprocessor_get_queue_size                                  *
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
 * Function: zbx_preproc_op_free                                              *
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
 * Function: zbx_preproc_result_free                                          *
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
 * Function: preprocessor_pack_test_request                                   *
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
	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (6 + steps->values_num * 4 + history_num * 5)
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
 * Function: zbx_preprocessor_unpack_test_request                             *
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
 * Function: zbx_preprocessor_test                                            *
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
 * Function: zbx_preprocessor_get_diag_stats                                  *
 *                                                                            *
 * Purpose: get preprocessing manager diagnostic statistics                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_preprocessor_get_diag_stats(int *values_num, int *values_preproc_num, char **error)
{
	unsigned char	*result;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_PREPROCESSING, ZBX_IPC_PREPROCESSOR_DIAG_STATS,
			SEC_PER_MIN, NULL, 0, &result, error))
	{
		return FAIL;
	}

	zbx_preprocessor_unpack_diag_stats(values_num, values_preproc_num, result);
	zbx_free(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_preprocessor_get_top_items                                   *
 *                                                                            *
 * Purpose: get the top N items by the number of queued values                *
 *                                                                            *
 ******************************************************************************/
int	zbx_preprocessor_get_top_items(int limit, zbx_vector_ptr_t *items, char **error)
{
	int		ret;
	unsigned char	*data, *result;
	zbx_uint32_t	data_len;

	data_len = zbx_preprocessor_pack_top_items_request(&data, limit);

	if (SUCCEED != (ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_PREPROCESSING, ZBX_IPC_PREPROCESSOR_TOP_ITEMS,
			SEC_PER_MIN, data, data_len, &result, error)))
	{
		goto out;
	}

	zbx_preprocessor_unpack_top_result(items, result);
	zbx_free(result);
out:
	zbx_free(data);

	return ret;
}
