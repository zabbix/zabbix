/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxmockdb.h"

#include "common.h"
#include "zbxalgo.h"
#include "zbxhistory.h"
#include "zbxdb.h"
#include "db.h"

void	__wrap_zbx_sleep_loop(int sleeptime)
{
	ZBX_UNUSED(sleeptime);
}

zbx_uint64_t	__wrap_DCget_nextid(const char *table_name, int num)
{
	ZBX_UNUSED(table_name);
	ZBX_UNUSED(num);
	return 0;
}

int	__wrap_zbx_host_availability_is_set(const zbx_host_availability_t *ha)
{
	ZBX_UNUSED(ha);
	return SUCCEED;
}

int	__wrap_zbx_add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag,
		unsigned char trigger_value, const char *error)
{
	ZBX_UNUSED(source);
	ZBX_UNUSED(object);
	ZBX_UNUSED(objectid);
	ZBX_UNUSED(timespec);
	ZBX_UNUSED(value);
	ZBX_UNUSED(trigger_description);
	ZBX_UNUSED(trigger_expression);
	ZBX_UNUSED(trigger_recovery_expression);
	ZBX_UNUSED(trigger_priority);
	ZBX_UNUSED(trigger_type);
	ZBX_UNUSED(trigger_tags);
	ZBX_UNUSED(trigger_correlation_mode);
	ZBX_UNUSED(trigger_correlation_tag);
	ZBX_UNUSED(trigger_value);
	ZBX_UNUSED(error);
	return SUCCEED;

}

int	__wrap_zbx_process_events(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock)
{
	ZBX_UNUSED(trigger_diff);
	ZBX_UNUSED(triggerids_lock);
	return SUCCEED;
}

void	__wrap_zbx_clean_events(void)
{
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vcmock_history_dump                                          *
 *                                                                            *
 * Purpose: dumps history record vector contents to standard output           *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vcmock_history_dump(unsigned char value_type, const zbx_vector_history_record_t *values)
{
	int	i;
	char	buffer[256];

	for (i = 0; i < values->values_num; i++)
	{
		const zbx_history_record_t	*rec = &values->values[i];

		zbx_timespec_to_strtime(&rec->timestamp, buffer, sizeof(buffer));
		printf("  - %s\n", buffer);
		zbx_history_value2str(buffer, sizeof(buffer), &rec->value, value_type);
		printf("    %s\n", buffer);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vcmock_read_history_value                                    *
 *                                                                            *
 * Purpose: reads history value and timestamp from input data                 *
 *                                                                            *
 * Parameters: hvalue     - [IN] handle to the history record mapping         *
 *             value_type - [IN] the value type of the history data           *
 *             value      - [OUT] the history value                           *
 *             ts         - [OUT] the history value timestamp                 *
 *                                                                            *
 ******************************************************************************/
static void	zbx_vcmock_read_history_value(zbx_mock_handle_t hvalue, unsigned char value_type,
		history_value_t *value, zbx_timespec_t *ts)
{
	const char		*data;
	zbx_mock_error_t	err;

	data = zbx_mock_get_object_member_string(hvalue, "value");

	if (ITEM_VALUE_TYPE_LOG != value_type)
	{
		switch (value_type)
		{
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				value->str = zbx_strdup(NULL, data);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				if (FAIL == is_uint64(data, &value->ui64))
					fail_msg("Invalid uint64 value \"%s\"", data);
				break;
			case ITEM_VALUE_TYPE_FLOAT:
				value->dbl = atof(data);
		}
	}
	else
	{
		zbx_log_value_t		*log;

		log = (zbx_log_value_t *)zbx_malloc(NULL, sizeof(zbx_log_value_t));

		log->value = zbx_strdup(NULL, data);
		log->source = zbx_strdup(NULL, zbx_mock_get_object_member_string(hvalue, "source"));

		data = zbx_mock_get_object_member_string(hvalue, "logeventid");
		if (FAIL == is_uint64(data, &log->logeventid))
			fail_msg("Invalid log logeventid value \"%s\"", data);

		data = zbx_mock_get_object_member_string(hvalue, "severity");
		if (FAIL == is_uint32(data, &log->severity))
			fail_msg("Invalid log severity value \"%s\"", data);

		data = zbx_mock_get_object_member_string(hvalue, "timestamp");
		if (FAIL == is_uint32(data, &log->timestamp))
			fail_msg("Invalid log timestamp value \"%s\"", data);

		value->log = log;
	}

	data = zbx_mock_get_object_member_string(hvalue, "ts");
	if (ZBX_MOCK_SUCCESS != (err = zbx_strtime_to_timespec(data, ts)))
		fail_msg("Invalid value timestamp \"%s\": %s", data, zbx_mock_error_string(err));
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vcmock_read_values                                           *
 *                                                                            *
 * Purpose: reads historical values from input data                           *
 *                                                                            *
 * Parameters: hdata      - [IN] handle to the history values in input data   *
 *             value_type - [IN] the history record value type                *
 *             values     - [OUT] the read values                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_vcmock_read_values(zbx_mock_handle_t hdata, unsigned char value_type, zbx_vector_history_record_t *values)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	hvalue;
	zbx_history_record_t	rec;

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hdata, &hvalue))))
	{
		zbx_vcmock_read_history_value(hvalue, value_type, &rec.value, &rec.timestamp);
		zbx_vector_history_record_append_ptr(values, &rec);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vcmock_check_records                                         *
 *                                                                            *
 * Purpose: Compares two history record vectors and throw assertion if either *
 *          values or timestamps don't match                                  *
 *                                                                            *
 * Parameters: prefix          - [IN] the assert message prefix               *
 *             value_type      - [IN] the value type                          *
 *             expected_values - [IN] the expected history records            *
 *             returned_values - [IN] the returned history records            *
 *                                                                            *
 ******************************************************************************/
void	zbx_vcmock_check_records(const char *prefix, unsigned char value_type,
		const zbx_vector_history_record_t *expected_values, const zbx_vector_history_record_t *returned_values)
{
	int				i;
	const zbx_history_record_t	*expected, *returned;
	const zbx_log_value_t		*expected_log, *returned_log;

	printf("Expected values:\n");
	zbx_vcmock_history_dump(value_type, expected_values);

	printf("Returned values:\n");
	zbx_vcmock_history_dump(value_type, returned_values);

	zbx_mock_assert_int_eq(prefix, expected_values->values_num, returned_values->values_num);

	for (i = 0; i < expected_values->values_num; i++)
	{
		expected = &expected_values->values[i];
		returned = &returned_values->values[i];

		zbx_mock_assert_timespec_eq(prefix, &expected->timestamp, &returned->timestamp);

		switch (value_type)
		{
			case ITEM_VALUE_TYPE_STR:
			case ITEM_VALUE_TYPE_TEXT:
				zbx_mock_assert_str_eq(prefix, expected->value.str, returned->value.str);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				zbx_mock_assert_uint64_eq(prefix, expected->value.ui64, returned->value.ui64);
				break;
			case ITEM_VALUE_TYPE_LOG:
				expected_log = expected->value.log;
				returned_log = returned->value.log;
				zbx_mock_assert_str_eq(prefix, expected_log->value, returned_log->value);
				zbx_mock_assert_str_eq(prefix, expected_log->source, returned_log->source);
				zbx_mock_assert_uint64_eq(prefix, expected_log->logeventid, returned_log->logeventid);
				break;
			case ITEM_VALUE_TYPE_FLOAT:
				zbx_mock_assert_double_eq(prefix, expected->value.dbl, returned->value.dbl);
				break;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: vc_history_record_compare_desc_func                              *
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
static int	vc_history_record_compare_desc_func(const zbx_history_record_t *d1, const zbx_history_record_t *d2)
{
	if (d1->timestamp.sec == d2->timestamp.sec)
		return d2->timestamp.ns - d1->timestamp.ns;

	return d2->timestamp.sec - d1->timestamp.sec;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mock_test_entry                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_mock_test_entry(void **state)
{
	char				*error = NULL;
	int				err, start, end, count, value_type, seconds;
	zbx_uint64_t			itemid;
	zbx_timespec_t			ts;
	zbx_vector_history_record_t	values_received, values_expected;

	ZBX_UNUSED(state);

	zbx_mockdb_init();

	err = zbx_history_init(&error);
	zbx_mock_assert_result_eq("zbx_history_init()", SUCCEED, err);

	if (FAIL == is_uint64(zbx_mock_get_parameter_string("in.itemid"), &itemid))
		fail_msg("Invalid itemid value");

	zbx_strtime_to_timespec(zbx_mock_get_parameter_string("in.end"), &ts);
	end = ts.sec;
	seconds = atoi(zbx_mock_get_parameter_string("in.seconds"));
	start = (0 == seconds ? 0 : end - seconds);
	count = atoi(zbx_mock_get_parameter_string("in.count"));
	value_type = zbx_mock_str_to_value_type(zbx_mock_get_parameter_string("in['value type']"));

	zbx_history_record_vector_create(&values_received);
	zbx_history_record_vector_create(&values_expected);

	err = zbx_history_get_values(itemid, value_type, start, count, end, &values_received);
	zbx_mock_assert_result_eq("zbx_history_get_values()", SUCCEED, err);

	zbx_vector_history_record_sort(&values_received, (zbx_compare_func_t)vc_history_record_compare_desc_func);

	zbx_vcmock_read_values(zbx_mock_get_parameter_handle("out.values"), value_type, &values_expected);
	zbx_vcmock_check_records("Returned values", value_type,  &values_expected, &values_received);

	zbx_history_record_vector_destroy(&values_expected, value_type);
	zbx_history_record_vector_destroy(&values_received, value_type);

	zbx_history_destroy();

	zbx_mockdb_destroy();
}
