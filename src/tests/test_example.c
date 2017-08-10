#include "zbxtests.h"

#include "../libs/zbxdbcache/valuecache.h"

int	__wrap_zbx_vc_get_value_range(zbx_uint64_t itemid, int value_type, zbx_vector_history_record_t *values,
		int seconds, int count, int timestamp)
{
	zbx_history_record_t	*record = (zbx_history_record_t *) mock();

	zbx_vector_history_record_append(values, *record);

	return SUCCEED;
}

static void test_exception()
{
	char	*str = NULL;
	*str = '0';
}

static void test_successful_evaluate_function()
{
	int     ret;
	char    value[100], *error = NULL;
	DC_ITEM item = {.value_type = ITEM_VALUE_TYPE_UINT64};

	history_value_t		h_value = {.ui64 = 1024};
	zbx_history_record_t	record = {.value = h_value};

	will_return(__wrap_zbx_vc_get_value_range, &record);

	ret = evaluate_function(value, &item, "last", "", 1, &error);

	assert_int_equal(ret, SUCCEED);
}

int main(void)
{
	const struct CMUnitTest tests[] =
	{
		cmocka_unit_test(test_successful_evaluate_function), 	/* evaluate_function */
		cmocka_unit_test(test_exception), 			/* Segmentation fault */
		cmocka_unit_test(test_successful_process_escalations) 	/* process_escalations */
	};

	return cmocka_run_group_tests(tests, NULL, NULL);
}
