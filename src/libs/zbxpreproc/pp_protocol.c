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

#include "pp_protocol.h"
#include "zbxcommon.h"
#include "zbxpreproc.h"

#include "zbxpreprocbase.h"
#include "zbxserialize.h"
#include "zbx_item_constants.h"
#include "zbxvariant.h"
#include "zbxtime.h"
#include "zbxstats.h"

#define PACKED_FIELD_RAW	0
#define PACKED_FIELD_STRING	1

#define PACKED_FIELD(value, size)	\
		(zbx_packed_field_t){(value), (size), (0 == (size) ? PACKED_FIELD_STRING : PACKED_FIELD_RAW)}

static zbx_ipc_message_t	cached_message;
static int			cached_values;

ZBX_PTR_VECTOR_IMPL(ipcmsg, zbx_ipc_message_t *)

static zbx_uint32_t	fields_calc_size(zbx_packed_field_t *fields, int fields_num)
{
	zbx_uint32_t	data_size = 0, field_size;

	for (int i = 0; i < fields_num; i++)
	{
		if (PACKED_FIELD_STRING == fields[i].type)
		{
			field_size = (NULL != fields[i].value) ?
					(zbx_uint32_t)strlen((const char *)fields[i].value) + 1 : 0;
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
	unsigned char	*offset = data;

	for (int i = 0; i < fields_num; i++)
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
 *             fields  - [IN] definition of data to be packed                 *
 *             count   - [IN] field count                                     *
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
 *             value   - [IN] value to be packed                              *
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
 * Parameters: fields - [OUT] packed fields                                   *
 *             value  - [IN] value to pack                                    *
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

		case ZBX_VARIANT_ERR:
			fields[offset++] = PACKED_FIELD(value->data.err, 0);
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
 * Parameters: fields  - [OUT] packed fields                                  *
 *             history - [IN] history to pack                                 *
 *             history_num - [IN] number of history entries                   *
 *                                                                            *
 * Return value: The number of fields used.                                   *
 *                                                                            *
 * Comments: Don't pack local variables, only ones passed in parameters!      *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_pack_history(zbx_packed_field_t *fields, const zbx_pp_history_t *history,
		const int *history_num)
{
	int	offset = 0;

	fields[offset++] = PACKED_FIELD(history_num, sizeof(int));

	for (int i = 0; i < *history_num; i++)
	{
		zbx_pp_step_history_t	*step_history = &history->step_history.values[i];

		fields[offset++] = PACKED_FIELD(&step_history->index, sizeof(int));
		offset += preprocessor_pack_variant(&fields[offset], &step_history->value);
		fields[offset++] = PACKED_FIELD(&step_history->ts.sec, sizeof(int));
		fields[offset++] = PACKED_FIELD(&step_history->ts.ns, sizeof(int));
	}

	return offset;
}

/******************************************************************************
 *                                                                            *
 * Purpose: packs preprocessing step for serialization                        *
 *                                                                            *
 * Parameters: fields - [OUT] packed fields                                   *
 *             step   - [IN] step to pack                                     *
 *                                                                            *
 * Return value: The number of fields used.                                   *
 *                                                                            *
 * Comments: Don't pack local variables, only ones passed in parameters!      *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_pack_step(zbx_packed_field_t *fields, const zbx_pp_step_t *step)
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
 * Purpose: unpacks serialized variant value                                  *
 *                                                                            *
 * Parameters: data  - [IN] serialized data                                   *
 *             value - [OUT] value                                            *
 *                                                                            *
 * Return value: The number of bytes parsed.                                  *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_unpack_variant(const unsigned char *data, zbx_variant_t *value)
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
		case ZBX_VARIANT_ERR:
			offset += zbx_deserialize_str(offset, &value->data.err, value_len);
			break;
		case ZBX_VARIANT_BIN:
			offset += zbx_deserialize_bin(offset, &value->data.bin, value_len);
			break;
		case ZBX_VARIANT_NONE:
		case ZBX_VARIANT_VECTOR:
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	return (int)(offset - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpacks serialized preprocessing history                          *
 *                                                                            *
 * Parameters: data    - [IN] serialized data                                 *
 *             history - [OUT] history                                        *
 *                                                                            *
 * Return value: The number of bytes parsed.                                  *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_unpack_history(const unsigned char *data, zbx_pp_history_t *history)
{
	const unsigned char	*offset = data;
	int			history_num;

	offset += zbx_deserialize_int(offset, &history_num);

	if (0 != history_num)
	{
		zbx_pp_history_reserve(history, history_num);

		for (int i = 0; i < history_num; i++)
		{
			int		index;
			zbx_variant_t	value;
			zbx_timespec_t	ts;

			offset += zbx_deserialize_int(offset, &index);
			offset += preprocessor_unpack_variant(offset, &value);
			offset += zbx_deserialize_int(offset, &ts.sec);
			offset += zbx_deserialize_int(offset, &ts.ns);

			zbx_pp_history_add(history, index, &value, ts);
		}
	}

	return (int)(offset - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpacks serialized preprocessing step                             *
 *                                                                            *
 * Parameters: data - [IN] serialized data                                    *
 *             step - [OUT] preprocessing step                                *
 *                                                                            *
 * Return value: The number of bytes parsed.                                  *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_unpack_step(const unsigned char *data, zbx_pp_step_t *step)
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
 * Parameters: data    - [IN] serialized data                                 *
 *             preproc - [OUT] item preprocessing data                        *
 *                                                                            *
 * Return value: The number of bytes parsed.                                  *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_unpack_steps(const unsigned char *data, zbx_pp_item_preproc_t *preproc)
{
	const unsigned char	*offset = data;

	offset += zbx_deserialize_int(offset, &preproc->steps_num);
	if (0 < preproc->steps_num)
	{
		preproc->steps = (zbx_pp_step_t *)zbx_malloc(NULL, sizeof(zbx_pp_step_t) * (size_t)preproc->steps_num);

		for (int i = 0; i < preproc->steps_num; i++)
			offset += preprocessor_unpack_step(offset, preproc->steps + i);
	}

	return (int)(offset - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack preprocessing result data into a single buffer that can be   *
 *          used in IPC                                                       *
 *                                                                            *
 * Parameters: data        - [OUT] memory buffer for packed data              *
 *             results     - [IN] preprocessing step results                  *
 *             results_num - [IN] number of preprocessing step results        *
 *             history     - [IN] item history data                           *
 *                                                                            *
 * Return value: size of packed data                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_test_result(unsigned char **data, const zbx_pp_result_t *results,
		int results_num, const zbx_pp_history_t *history)
{
	zbx_packed_field_t	*offset, *fields;
	zbx_uint32_t		size;
	zbx_ipc_message_t	message;
	int			history_num;

	history_num = (NULL != history ? history->step_history.values_num : 0);

	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (size_t)(3 + history_num * 5 + results_num * 5) *
			sizeof(zbx_packed_field_t));
	offset = fields;

	*offset++ = PACKED_FIELD(&results_num, sizeof(int));

	for (int i = 0; i < results_num; i++)
	{
		offset += preprocessor_pack_variant(offset, &results[i].value);
		*offset++ = PACKED_FIELD(&results[i].action, sizeof(unsigned char));
		offset += preprocessor_pack_variant(offset, &results[i].value_raw);
	}

	offset += preprocessor_pack_history(offset, history, &history_num);

	zbx_ipc_message_init(&message);
	size = message_pack_data(&message, fields, (int)(offset - fields));
	*data = message.data;

	zbx_free(fields);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack diagnostic statistics data into a single buffer that can be  *
 *          used in IPC                                                       *
 * Parameters: data          - [OUT] memory buffer for packed data            *
 *             preproc_num   - [IN] number of items with preprocessing        *
 *                                related data (preprocessing, internal/      *
 *                                master/dependent items)                     *
 *             pending_num   - [IN] number of values waiting to be            *
 *                               preprocessed                                 *
 *             finished_num  - [IN] number of values being preprocessed       *
 *             sequences_num - [IN] number of registered task sequences       *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_diag_stats(unsigned char **data, zbx_uint64_t preproc_num,
		zbx_uint64_t pending_num, zbx_uint64_t finished_num, zbx_uint64_t sequences_num)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0;

	zbx_serialize_prepare_value(data_len, preproc_num);
	zbx_serialize_prepare_value(data_len, pending_num);
	zbx_serialize_prepare_value(data_len, finished_num);
	zbx_serialize_prepare_value(data_len, sequences_num);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, preproc_num);
	ptr += zbx_serialize_value(ptr, pending_num);
	ptr += zbx_serialize_value(ptr, finished_num);
	(void)zbx_serialize_value(ptr, sequences_num);

	return data_len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack diagnostic statistics data into a single buffer that can be  *
 *          used in IPC                                                       *
 *                                                                            *
 * Parameters: data  - [OUT] memory buffer for packed data                    *
 *             usage - [IN] worker usage statistics                           *
 *             count - [IN]                                                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_usage_stats(unsigned char **data, const zbx_vector_dbl_t *usage, int count)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len;

	data_len = (zbx_uint32_t)((unsigned int)usage->values_num * sizeof(double) + sizeof(int) + sizeof(int));

	ptr = *data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr += zbx_serialize_value(ptr, usage->values_num);

	for (int i = 0; i < usage->values_num; i++)
		ptr += zbx_serialize_value(ptr, usage->values[i]);

	(void)zbx_serialize_value(ptr, count);

	return data_len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: pack top request data into a single buffer that can be used in IPC*
 *                                                                            *
 * Parameters: data  - [OUT] memory buffer for packed data                    *
 *             limit - [IN] number of top values to return                    *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_top_stats_request(unsigned char **data, int limit)
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
 * Parameters: data          - [OUT] memory buffer for packed data            *
 *             stats     - [IN] list of stats                                 *
 *             stats_num - [IN] number of sequences to pack                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_preprocessor_pack_top_stats_result(unsigned char **data, zbx_vector_pp_top_stats_ptr_t *stats,
		int stats_num)
{
	unsigned char	*ptr;
	zbx_uint32_t	data_len = 0, stat_len = 0;

	if (0 != stats_num)
	{
		zbx_serialize_prepare_value(stat_len, stats->values[0]->itemid);
		zbx_serialize_prepare_value(stat_len, stats->values[0]->tasks_num);
	}

	zbx_serialize_prepare_value(data_len, stats_num);
	data_len += stat_len * (zbx_uint32_t)stats_num;
	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	ptr = *data;
	ptr += zbx_serialize_value(ptr, stats_num);

	for (int i = 0; i < stats_num; i++)
	{
		ptr += zbx_serialize_value(ptr, stats->values[i]->itemid);
		ptr += zbx_serialize_value(ptr, stats->values[i]->tasks_num);
	}

	return data_len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack item value data from IPC data buffer                       *
 *                                                                            *
 * Parameters: value - [OUT] unpacked item value                              *
 *             data  - [IN]  IPC data buffer                                  *
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

	return (zbx_uint32_t)(offset - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing test data from IPC data buffer               *
 *                                                                            *
 * Parameters: results - [OUT] preprocessing step results                     *
 *             history - [OUT] item history data                              *
 *             data    - [IN] IPC data buffer                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_test_result(zbx_vector_pp_result_ptr_t *results, zbx_pp_history_t *history,
		const unsigned char *data)
{
	const unsigned char	*offset = data;
	int			results_num;
	zbx_pp_result_t		*result;

	offset += zbx_deserialize_int(offset, &results_num);

	zbx_vector_pp_result_ptr_reserve(results, (size_t)results_num);

	for (int i = 0; i < results_num; i++)
	{
		result = (zbx_pp_result_t *)zbx_malloc(NULL, sizeof(zbx_pp_result_t));
		offset += preprocessor_unpack_variant(offset, &result->value);
		offset += zbx_deserialize_char(offset, &result->action);
		offset += preprocessor_unpack_variant(offset, &result->value_raw);
		zbx_vector_pp_result_ptr_append(results, result);
	}

	(void)preprocessor_unpack_history(offset, history);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing test data from IPC data buffer               *
 *                                                                            *
 * Parameters: preproc_num   - [OUT] number of items with preprocessing       *
 *                                related data (preprocessing, internal/      *
 *                                master/dependent items)                     *
 *             pending_num   - [OUT] number of values waiting to be           *
 *                               preprocessed                                 *
 *             finished_num  - [OUT] number of values being preprocessed      *
 *             sequences_num - [OUT] number of registered task sequences      *
 *             data          - [OUT] data buffer                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_diag_stats(zbx_uint64_t *preproc_num, zbx_uint64_t *pending_num,
		zbx_uint64_t *finished_num, zbx_uint64_t *sequences_num, const unsigned char *data)
{
	const unsigned char	*offset = data;

	offset += zbx_deserialize_value(offset, preproc_num);
	offset += zbx_deserialize_value(offset, pending_num);
	offset += zbx_deserialize_value(offset, finished_num);
	(void)zbx_deserialize_value(offset, sequences_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack worker usage statistics                                    *
 *                                                                            *
 * Parameters: usage - [OUT] worker usage statistics                          *
 *             count - [OUT]                                                  *
 *             data  - [IN] input data                                        *
 *                                                                            *
 ******************************************************************************/
static void	preprocessor_unpack_usage_stats(zbx_vector_dbl_t *usage, int *count, const unsigned char *data)
{
	const unsigned char	*offset = data;
	int			usage_num;

	offset += zbx_deserialize_value(offset, &usage_num);
	zbx_vector_dbl_reserve(usage, (size_t)usage_num);

	for (int i = 0; i < usage_num; i++)
	{
		double	busy;

		offset += zbx_deserialize_value(offset, &busy);
		zbx_vector_dbl_append(usage, busy);
	}

	(void)zbx_deserialize_value(offset, count);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing test data from IPC data buffer               *
 *                                                                            *
 * Parameters: limit - [IN] number of top values to return                    *
 *             data  - [OUT] memory buffer for packed data                    *
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
 * Parameters: sequences - [OUT] item diag data                               *
 *             data      - [IN] memory buffer for packed data                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_top_stats_result(zbx_vector_pp_top_stats_ptr_t *stats, const unsigned char *data)
{
	int	stats_num;

	data += zbx_deserialize_value(data, &stats_num);

	if (0 != stats_num)
	{
		zbx_vector_pp_top_stats_ptr_reserve(stats, (size_t)stats_num);

		for (int i = 0; i < stats_num; i++)
		{
			zbx_pp_top_stats_t	*stat;

			stat = (zbx_pp_top_stats_t *)zbx_malloc(NULL, sizeof(zbx_pp_top_stats_t));
			data += zbx_deserialize_value(data, &stat->itemid);
			data += zbx_deserialize_value(data, &stat->tasks_num);
			zbx_vector_pp_top_stats_ptr_append(stats, stat);
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
 * Parameters: itemid          - [IN]                                         *
 *             hostid          - [IN]                                         *
 *             item_value_type - [IN] item value type                         *
 *             item_flags      - [IN] item flags (e. g. lld rule)             *
 *             result          - [IN] agent result containing the value       *
 *                               to add                                       *
 *             ts              - [IN] value timestamp                         *
 *             state           - [IN] item state                              *
 *             error           - [IN] error message in case item state is     *
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
		if (0 != ZBX_ISSET_STR(result))
			value_len = strlen(result->str);

		if (0 != ZBX_ISSET_TEXT(result))
		{
			if (value_len < (len = strlen(result->text)))
				value_len = len;
		}

		if (0 != ZBX_ISSET_LOG(result))
		{
			if (value_len < (len = strlen(result->log->value)))
				value_len = len;
		}

		if (0 != ZBX_ISSET_BIN(result))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
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

	if (ZBX_PREPROCESSING_BATCH_SIZE < ++cached_values)
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
 * Purpose: packs preprocessing step request for serialization                *
 *                                                                            *
 * Return value: The size of packed data                                      *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	preprocessor_pack_test_request(unsigned char **data, unsigned char value_type,
		const char *value, const zbx_timespec_t *ts, unsigned char state, const zbx_pp_history_t *history,
		const zbx_vector_pp_step_ptr_t *steps)
{
	zbx_packed_field_t	*offset, *fields;
	zbx_uint32_t		size;
	int			history_num;
	zbx_ipc_message_t	message;

	history_num = (NULL != history ? history->step_history.values_num : 0);

	/* 6 is a max field count (without preprocessing step and history fields) */
	fields = (zbx_packed_field_t *)zbx_malloc(NULL, (size_t)(7 + steps->values_num * 4 + history_num * 5)
			* sizeof(zbx_packed_field_t));

	offset = fields;

	*offset++ = PACKED_FIELD(&value_type, sizeof(unsigned char));
	*offset++ = PACKED_FIELD(value, 0);
	*offset++ = PACKED_FIELD(&ts->sec, sizeof(int));
	*offset++ = PACKED_FIELD(&ts->ns, sizeof(int));
	*offset++ = PACKED_FIELD(&state, sizeof(unsigned char));

	offset += preprocessor_pack_history(offset, history, &history_num);

	*offset++ = PACKED_FIELD(&steps->values_num, sizeof(int));

	for (int i = 0; i < steps->values_num; i++)
		offset += preprocessor_pack_step(offset, steps->values[i]);

	zbx_ipc_message_init(&message);
	size = message_pack_data(&message, fields, (int)(offset - fields));
	*data = message.data;
	zbx_free(fields);

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unpack preprocessing test request data from IPC data buffer       *
 *                                                                            *
 * Parameters: preproc - [OUT] item preprocessing data                        *
 *             value   - [OUT] value                                          *
 *             ts      - [OUT] value timestamp                                *
 *             data    - [IN] IPC data buffer                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_unpack_test_request(zbx_pp_item_preproc_t *preproc, zbx_variant_t *value, zbx_timespec_t *ts,
		const unsigned char *data)
{
	char			*str;
	zbx_uint32_t		str_len;
	const unsigned char	*offset = data;
	unsigned char		state;
	zbx_pp_history_t	*history;

	offset += zbx_deserialize_char(offset, &preproc->value_type);
	offset += zbx_deserialize_str(offset, &str, str_len);

	offset += zbx_deserialize_int(offset, &ts->sec);
	offset += zbx_deserialize_int(offset, &ts->ns);

	offset += zbx_deserialize_char(offset, &state);

	if (ITEM_STATE_NORMAL == state)
		zbx_variant_set_str(value, str);
	else
		zbx_variant_set_error(value, str);

	history = zbx_pp_history_create(0);

	offset += preprocessor_unpack_history(offset, history);
	(void)preprocessor_unpack_steps(offset, preproc);

	for (int i = 0; i < preproc->steps_num; i++)
	{
		if (SUCCEED == zbx_pp_preproc_has_history(preproc->steps[i].type))
			preproc->history_num++;
	}

	preproc->history_cache = zbx_pp_history_cache_create();
	zbx_pp_history_cache_history_set_and_release(preproc->history_cache, NULL, history);
}

/******************************************************************************
 *                                                                            *
 * Purpose: tests item preprocessing with the specified input value and steps *
 *                                                                            *
 ******************************************************************************/
int	zbx_preprocessor_test(unsigned char value_type, const char *value, const zbx_timespec_t *ts,
		unsigned char state, const zbx_vector_pp_step_ptr_t *steps, zbx_vector_pp_result_ptr_t *results,
		zbx_pp_history_t *history, char **error)
{
	unsigned char	*data = NULL;
	zbx_uint32_t	size;
	int		ret = FAIL;
	unsigned char	*result;

	size = preprocessor_pack_test_request(&data, value_type, value, ts, state, history, steps);

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_PREPROCESSING, ZBX_IPC_PREPROCESSOR_TEST_REQUEST,
			SEC_PER_MIN, data, size, &result, error))
	{
		goto out;
	}

	zbx_pp_history_clear(history);
	zbx_pp_history_init(history);
	zbx_preprocessor_unpack_test_result(results, history, result);
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
int	zbx_preprocessor_get_diag_stats(zbx_uint64_t *preproc_num, zbx_uint64_t *pending_num,
		zbx_uint64_t *finished_num, zbx_uint64_t *sequences_num, char **error)
{
	unsigned char	*result;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_PREPROCESSING, ZBX_IPC_PREPROCESSOR_DIAG_STATS,
			SEC_PER_MIN, NULL, 0, &result, error))
	{
		return FAIL;
	}

	zbx_preprocessor_unpack_diag_stats(preproc_num, pending_num, finished_num, sequences_num, result);
	zbx_free(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the top N items by the number of queued values                *
 *                                                                            *
 ******************************************************************************/
static int	preprocessor_get_top_view(int limit, zbx_vector_pp_top_stats_ptr_t *stats, char **error,
		zbx_uint32_t code)
{
	int		ret;
	unsigned char	*data, *result;
	zbx_uint32_t	data_len;

	data_len = zbx_preprocessor_pack_top_stats_request(&data, limit);

	if (SUCCEED != (ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_PREPROCESSING, code, SEC_PER_MIN, data, data_len,
			&result, error)))
	{
		goto out;
	}

	zbx_preprocessor_unpack_top_stats_result(stats, result);
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
int	zbx_preprocessor_get_top_sequences(int limit, zbx_vector_pp_top_stats_ptr_t *stats, char **error)
{
	return preprocessor_get_top_view(limit, stats, error, ZBX_IPC_PREPROCESSOR_TOP_SEQUENCES);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the top N items by the number of queued values                *
 *                                                                            *
 ******************************************************************************/
int	zbx_preprocessor_get_top_peak(int limit, zbx_vector_pp_top_stats_ptr_t *stats, char **error)
{
	return preprocessor_get_top_view(limit, stats, error, ZBX_IPC_PREPROCESSOR_TOP_PEAK);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get preprocessing manager diagnostic statistics                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_preprocessor_get_usage_stats(zbx_vector_dbl_t *usage, int *count, char **error)
{
	unsigned char	*result;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_PREPROCESSING, ZBX_IPC_PREPROCESSOR_USAGE_STATS,
			SEC_PER_MIN, NULL, 0, &result, error))
	{
		return FAIL;
	}

	preprocessor_unpack_usage_stats(usage, count, result);
	zbx_free(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get preprocessing worker usage statistics                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_preprocessor_get_worker_info(zbx_process_info_t *info)
{
	zbx_vector_dbl_t	usage;
	char			*error = NULL;

	zbx_vector_dbl_create(&usage);

	memset(info, 0, sizeof(zbx_process_info_t));

	if (SUCCEED != zbx_preprocessor_get_usage_stats(&usage, &info->count, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get preprocessor usage statistics: %s", error);
		zbx_free(error);
		goto out;
	}

	if (0 == usage.values_num)
		goto out;

	info->busy_min = info->busy_max = info->busy_avg = usage.values[0];

	for (int i = 1; i < usage.values_num; i++)
	{
		if (usage.values[i] < info->busy_min)
			info->busy_min = usage.values[i];

		if (usage.values[i] > info->busy_max)
			info->busy_max = usage.values[i];

		info->busy_avg += usage.values[i];
	}

	info->busy_avg /= (double)usage.values_num;

	info->idle_min = 100.0 - info->busy_min;
	info->idle_max = 100.0 - info->busy_max;
	info->idle_avg = 100.0 - info->busy_avg;
	info->count = usage.values_num;
out:
	zbx_vector_dbl_destroy(&usage);
}
