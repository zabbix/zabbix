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

#include "history.h"

#include "common.h"
#include "log.h"

ZBX_VECTOR_IMPL(history_record, zbx_history_record_t)

extern char	*CONFIG_HISTORY_STORAGE_URL;
extern char	*CONFIG_HISTORY_STORAGE_OPTS;

zbx_history_iface_t	history_ifaces[ITEM_VALUE_TYPE_MAX];

/************************************************************************************
 *                                                                                  *
 * Purpose: initializes history storage                                             *
 *                                                                                  *
 * Comments: History interfaces are created for all values types based on           *
 *           configuration. Every value type can have different history storage     *
 *           backend.                                                               *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_init(char **error)
{
	int		i, ret;

	/* TODO: support per value type specific configuration */

	const char	*opts[] = {"dbl", "str", "log", "uint", "text"};

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		if (NULL == CONFIG_HISTORY_STORAGE_URL || NULL == strstr(CONFIG_HISTORY_STORAGE_OPTS, opts[i]))
			ret = zbx_history_sql_init(&history_ifaces[i], i, error);
		else
			ret = zbx_history_elastic_init(&history_ifaces[i], i, error);

		if (FAIL == ret)
			return FAIL;
	}

	return SUCCEED;
}

/************************************************************************************
 *                                                                                  *
 * Purpose: destroys history storage                                                *
 *                                                                                  *
 * Comments: All interfaces created by zbx_history_init() function are destroyed    *
 *           here.                                                                  *
 *                                                                                  *
 ************************************************************************************/
void	zbx_history_destroy(void)
{
	int	i;

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		zbx_history_iface_t	*writer = &history_ifaces[i];

		writer->destroy(writer);
	}
}

/************************************************************************************
 *                                                                                  *
 * Purpose: Sends values to the history storage                                     *
 *                                                                                  *
 * Parameters: history - [IN] the values to store                                   *
 *                                                                                  *
 * Comments: add history values to the configured storage backends                  *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_add_values(const zbx_vector_ptr_t *history, int *ret_flush)
{
	int	i, flags = 0;

	*ret_flush = FLUSH_SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		zbx_history_iface_t	*writer = &history_ifaces[i];

		if (0 < writer->add_values(writer, history))
			flags |= (1 << i);
	}

	for (i = 0; i < ITEM_VALUE_TYPE_MAX; i++)
	{
		zbx_history_iface_t	*writer = &history_ifaces[i];

		if (0 != (flags & (1 << i)))
		{
			if (FLUSH_DUPL_REJECTED == (*ret_flush = writer->flush(writer)))
				break;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return (FLUSH_SUCCEED == *ret_flush ? SUCCEED : FAIL);
}

/************************************************************************************
 *                                                                                  *
 * Purpose: gets item values from history storage                                   *
 *                                                                                  *
 * Parameters:  itemid     - [IN] the itemid                                        *
 *              value_type - [IN] the item value type                               *
 *              start      - [IN] the period start timestamp                        *
 *              count      - [IN] the number of values to read                      *
 *              end        - [IN] the period end timestamp                          *
 *              values     - [OUT] the item history data values                     *
 *                                                                                  *
 * Return value: SUCCEED - the history data were read successfully                  *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function reads <count> values from ]<start>,<end>] interval or    *
 *           all values from the specified interval if count is zero.               *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_get_values(zbx_uint64_t itemid, int value_type, int start, int count, int end,
		zbx_vector_history_record_t *values)
{
	int			ret, pos;
	zbx_history_iface_t	*writer = &history_ifaces[value_type];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemid:" ZBX_FS_UI64 " value_type:%d start:%d count:%d end:%d",
			__func__, itemid, value_type, start, count, end);

	pos = values->values_num;
	ret = writer->get_values(writer, itemid, start, count, end, values);

	if (SUCCEED == ret && SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		int	i;
		char	buffer[MAX_STRING_LEN];

		for (i = pos; i < values->values_num; i++)
		{
			zbx_history_record_t	*h = &values->values[i];

			zbx_history_value2str(buffer, sizeof(buffer), &h->value, value_type);
			zabbix_log(LOG_LEVEL_TRACE, "  %d.%09d %s", h->timestamp.sec, h->timestamp.ns, buffer);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s values:%d", __func__, zbx_result_string(ret),
			values->values_num - pos);

	return ret;
}

/************************************************************************************
 *                                                                                  *
 * Purpose: checks if the value type requires trends data calculations              *
 *                                                                                  *
 * Parameters: value_type - [IN] the value type                                     *
 *                                                                                  *
 * Return value: SUCCEED - trends must be calculated for this value type            *
 *               FAIL - otherwise                                                   *
 *                                                                                  *
 * Comments: This function is used to check if the trends must be calculated for    *
 *           the specified value type based on the history storage used.            *
 *                                                                                  *
 ************************************************************************************/
int	zbx_history_requires_trends(int value_type)
{
	zbx_history_iface_t	*writer = &history_ifaces[value_type];

	return 0 != writer->requires_trends ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees history log and all resources allocated for it              *
 *                                                                            *
 * Parameters: log   - [IN] the history log to free                           *
 *                                                                            *
 ******************************************************************************/
static void	history_logfree(zbx_log_value_t *log)
{
	zbx_free(log->source);
	zbx_free(log->value);
	zbx_free(log);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroys value vector and frees resources allocated for it        *
 *                                                                            *
 * Parameters: vector    - [IN] the value vector                              *
 *                                                                            *
 * Comments: Use this function to destroy value vectors created by            *
 *           zbx_vc_get_values_by_* functions.                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_vector_destroy(zbx_vector_history_record_t *vector, int value_type)
{
	if (NULL != vector->values)
	{
		zbx_history_record_vector_clean(vector, value_type);
		zbx_vector_history_record_destroy(vector);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated by a cached value                       *
 *                                                                            *
 * Parameters: value      - [IN] the cached value to clear                    *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_clear(zbx_history_record_t *value, int value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_free(value->value.str);
			break;
		case ITEM_VALUE_TYPE_LOG:
			history_logfree(value->value.log);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts history value to string format                           *
 *                                                                            *
 * Parameters: buffer     - [OUT] the output buffer                           *
 *             size       - [IN] the output buffer size                       *
 *             value      - [IN] the value to convert                         *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_value2str(char *buffer, size_t size, const history_value_t *value, int value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf(buffer, size, ZBX_FS_DBL64, value->dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf(buffer, size, ZBX_FS_UI64, value->ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_strlcpy_utf8(buffer, value->str, size);
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_strlcpy_utf8(buffer, value->log->value, size);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts history value to string format (with dynamic buffer)     *
 *                                                                            *
 * Parameters: value      - [IN] the value to convert                         *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 * Return value: The value in text format.                                    *
 *                                                                            *
 ******************************************************************************/
char	*zbx_history_value2str_dyn(const history_value_t *value, int value_type)
{
	char	*str = NULL;
	size_t	str_alloc = 0, str_offset = 0;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_snprintf_alloc(&str, &str_alloc, &str_offset, ZBX_FS_DBL, value->dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_snprintf_alloc(&str, &str_alloc, &str_offset, ZBX_FS_UI64, value->ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			str = zbx_strdup(NULL, value->str);
			break;
		case ITEM_VALUE_TYPE_LOG:
			str = zbx_strdup(NULL, value->log->value);
	}
	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts history value to string format (double type printed in   *
 *          human friendly format)                                            *
 *                                                                            *
 * Parameters: buffer     - [OUT] the output buffer                           *
 *             size       - [IN] the output buffer size                       *
 *             value      - [IN] the value to convert                         *
 *             value_type - [IN] the history value type                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_value_print(char *buffer, size_t size, const history_value_t *value, int value_type)
{
	if (ITEM_VALUE_TYPE_FLOAT == value_type)
		zbx_print_double(buffer, size, value->dbl);
	else
		zbx_history_value2str(buffer, size, value, value_type);
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases resources allocated to store history records             *
 *                                                                            *
 * Parameters: vector      - [IN] the history record vector                   *
 *             value_type  - [IN] the type of vector values                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_record_vector_clean(zbx_vector_history_record_t *vector, int value_type)
{
	int	i;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			for (i = 0; i < vector->values_num; i++)
				zbx_free(vector->values[i].value.str);

			break;
		case ITEM_VALUE_TYPE_LOG:
			for (i = 0; i < vector->values_num; i++)
				history_logfree(vector->values[i].value.log);
	}

	zbx_vector_history_record_clear(vector);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares two cache values by their timestamps                     *
 *                                                                            *
 * Parameters: d1   - [IN] the first value                                    *
 *             d2   - [IN] the second value                                   *
 *                                                                            *
 * Return value:   <0 - the first value timestamp is less than second         *
 *                 =0 - the first value timestamp is equal to the second      *
 *                 >0 - the first value timestamp is greater than second      *
 *                                                                            *
 * Comments: This function is commonly used to sort value vector in ascending *
 *           order.                                                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_history_record_compare_asc_func(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	if (d1->timestamp.sec == d2->timestamp.sec)
		return d1->timestamp.ns - d2->timestamp.ns;

	return d1->timestamp.sec - d2->timestamp.sec;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares two cache values by their timestamps                     *
 *                                                                            *
 * Parameters: d1   - [IN] the first value                                    *
 *             d2   - [IN] the second value                                   *
 *                                                                            *
 * Return value:   >0 - the first value timestamp is less than second         *
 *                 =0 - the first value timestamp is equal to the second      *
 *                 <0 - the first value timestamp is greater than second      *
 *                                                                            *
 * Comments: This function is commonly used to sort value vector in descending*
 *           order.                                                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_history_record_compare_desc_func(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	if (d1->timestamp.sec == d2->timestamp.sec)
		return d2->timestamp.ns - d1->timestamp.ns;

	return d2->timestamp.sec - d1->timestamp.sec;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts history value to variant value                           *
 *                                                                            *
 * Parameters: value      - [IN] the value to convert                         *
 *             value_type - [IN] the history value type                       *
 *             var        - [IN] the output value                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_value2variant(const history_value_t *value, unsigned char value_type, zbx_variant_t *var)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			zbx_variant_set_dbl(var, value->dbl);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			zbx_variant_set_ui64(var, value->ui64);
			break;
		case ITEM_VALUE_TYPE_STR:
		case ITEM_VALUE_TYPE_TEXT:
			zbx_variant_set_str(var, zbx_strdup(NULL, value->str));
			break;
		case ITEM_VALUE_TYPE_LOG:
			zbx_variant_set_str(var, zbx_strdup(NULL, value->log->value));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: relays the version retrieval logic to the history implementation  *
 *          functions                                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_check_version(struct zbx_json *json, int *result)
{
	if (NULL != CONFIG_HISTORY_STORAGE_URL)
		zbx_elastic_version_extract(json, result);
}
