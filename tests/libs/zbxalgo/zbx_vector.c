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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxalgo.h"
#include "zbxstr.h"

static void	free_tag_vector(zbx_vector_tags_ptr_t v)
{
	for (int i = 0; i < v.values_num; i++)
		zbx_free_tag(v.values[i]);

	zbx_vector_tags_ptr_clear(&v);
	zbx_vector_tags_ptr_destroy(&v);
}

static int	zbx_default_uint32_compare_func(const void *d1, const void *d2)
{
	const zbx_uint32_t	*i1 = (const zbx_uint32_t *)d1, *i2 = (const zbx_uint32_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(*i1, *i2);

	return 0;
}

#define COMPARE_VECTORS(TYPE)										\
													\
static int	compare_vectors_##TYPE(const zbx_vector_##TYPE##_t *v1, const zbx_vector_##TYPE##_t *v2)\
{													\
	if (v1->values_num != v2->values_num)								\
		return FAIL;										\
													\
	for (int i = 0; i < v1->values_num; i++)							\
	{												\
		if (v1->values[i] != v2->values[i])							\
			return FAIL;									\
	}												\
													\
	return SUCCEED;											\
}													\

COMPARE_VECTORS(uint32)
COMPARE_VECTORS(int32)

#undef COMPARE_VECTORS

static int	compare_vectors_tag(const zbx_vector_tags_ptr_t v1, const zbx_vector_tags_ptr_t v2)
{
	if (v1.values_num != v2.values_num)
		return FAIL;

	for (int i = 0; i < v1.values_num; i++)
	{
		const zbx_tag_t	*tag1 = v1.values[i];
		const zbx_tag_t	*tag2 = v2.values[i];

		if (SUCCEED != strcmp(tag1->tag, tag2->tag) || SUCCEED != strcmp(tag1->value, tag2->value))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

static void	extract_yaml_values_arr_int32(const char *path, int *arr)
{
	zbx_mock_handle_t	hvalues, hvalue;
	int			value_num = 0;

	hvalues = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != (zbx_mock_vector_element(hvalues, &hvalue)))
	{
		int	value;

		if (ZBX_MOCK_SUCCESS != zbx_mock_int(hvalue, &value))
		{
			value = 1;
			fail_msg("Cannot read value #%d", value_num);
		}

		arr[value_num] = value;
		value_num++;
	}
}

static void	extract_yaml_values_arr_uint32(const char *path, zbx_uint32_t *arr)
{
	zbx_mock_handle_t	hvalues, hvalue;
	int			value_num = 0;

	hvalues = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hvalues, &hvalue))
	{
		zbx_uint32_t	value;

		if (ZBX_MOCK_SUCCESS != zbx_mock_uint32(hvalue, &value))
		{
			value = 1;
			fail_msg("Cannot read value #%d", value_num);
		}

		arr[value_num] = value;
		value_num++;
	}
}

static void	extract_yaml_values_uint32(const char *path, zbx_vector_uint32_t *values)
{
	zbx_mock_handle_t	hvalues, hvalue;
	int			value_num = 0;

	hvalues = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hvalues, &hvalue))
	{
		zbx_uint32_t	value;

		if (ZBX_MOCK_SUCCESS != zbx_mock_uint32(hvalue, &value))
		{
			value = 1;
			fail_msg("Cannot read value #%d", value_num);
		}

		zbx_vector_uint32_append(values, value);

		value_num++;
	}
}

static void	extract_yaml_values_int32(const char *path, zbx_vector_int32_t *values)
{
	zbx_mock_handle_t	hvalues, hvalue;
	int			value_num = 0;

	hvalues = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(hvalues, &hvalue))
	{
		int	value;

		if (ZBX_MOCK_SUCCESS != zbx_mock_int(hvalue, &value))
		{
			value = 1;
			fail_msg("Cannot read value #%d", value_num);
		}

		zbx_vector_int32_append(values, value);

		value_num++;
	}
}

static void	extract_from_yaml_tag_vector(zbx_vector_tags_ptr_t *vector, zbx_mock_handle_t *handle)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	vec_handle;

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(*handle, &vec_handle))
	{
		const char		*tag, *value;
		zbx_mock_handle_t	vec_elem_handle;

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_vector_element(vec_handle, &vec_elem_handle)))
		{
			fail_msg("failed to get first vector element %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(vec_elem_handle, &tag)))
		{
			fail_msg("failed to extract tag %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_vector_element(vec_handle, &vec_elem_handle)))
		{
			fail_msg("failed to get second vector element %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(vec_elem_handle, &value)))
		{
			fail_msg("failed to extract value %s", zbx_mock_error_string(error));
			return;
		}

		zbx_tag_t	*tag_s = (zbx_tag_t *)malloc(sizeof(zbx_tag_t));

		tag_s->tag = zbx_strdup(NULL, tag);
		tag_s->value = zbx_strdup(NULL, value);
		zbx_vector_tags_ptr_append(vector, tag_s);
	}
}

static void	extract_from_yaml_tag(zbx_tag_t *tag_s, zbx_mock_handle_t *handle)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	vec_handle;

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(*handle, &vec_handle))
	{
		const char		*tag, *value;
		zbx_mock_handle_t	vec_elem_handle;

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_vector_element(vec_handle, &vec_elem_handle)))
		{
			fail_msg("failed to get first vector element %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(vec_elem_handle, &tag)))
		{
			fail_msg("failed to extract tag %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_vector_element(vec_handle, &vec_elem_handle)))
		{
			fail_msg("failed to get second vector element %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(vec_elem_handle, &value)))
		{
			fail_msg("failed to extract value %s", zbx_mock_error_string(error));
			return;
		}

		tag_s->tag = zbx_strdup(NULL, tag);
		tag_s->value = zbx_strdup(NULL, value);
	}
}

static void	extract_from_yaml_tag_arr(zbx_tag_t **tag_s, zbx_mock_handle_t *handle)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	vec_handle;
	int			index = 0;

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(*handle, &vec_handle))
	{
		const char		*tag, *value;
		zbx_mock_handle_t	vec_elem_handle;

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_vector_element(vec_handle, &vec_elem_handle)))
		{
			fail_msg("failed to get first vector element %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(vec_elem_handle, &tag)))
		{
			fail_msg("failed to extract tag %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_vector_element(vec_handle, &vec_elem_handle)))
		{
			fail_msg("failed to get second vector element %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(vec_elem_handle, &value)))
		{
			fail_msg("failed to extract value %s", zbx_mock_error_string(error));
			return;
		}

		tag_s[index] = (zbx_tag_t *)malloc(sizeof(zbx_tag_t));
		tag_s[index]->tag = zbx_strdup(NULL, tag);
		tag_s[index]->value = zbx_strdup(NULL, value);
		index++;
	}
}

static void	is_exit_code(zbx_mock_error_t error, int exit_code)
{
	if (ZBX_MOCK_NO_EXIT_CODE != (error = zbx_mock_exit_code(&exit_code)))
	{
		if (ZBX_MOCK_SUCCESS == error)
			fail_msg("exit(%d) expected", exit_code);
		else
			fail_msg("Cannot get exit code from test case data: %s", zbx_mock_error_string(error));
	}
}

static void	dump_debug_info_int32(zbx_vector_int32_t *v1, zbx_vector_int32_t *v2)
{
	for (int i = 0; i < v1->values_num; i++)
	{
		printf("value V1: %d\n", v1->values[i]);
	}

	for (int i = 0; i < v2->values_num; i++)
	{
		printf("value V2: %d\n", v2->values[i]);
	}
}

static void	dump_debug_info_uint32(zbx_vector_uint32_t *v1, zbx_vector_uint32_t *v2)
{
	for (int i = 0; i < v1->values_num; i++)
	{
		printf("value V1: %u\n", v1->values[i]);
	}

	for (int i = 0; i < v2->values_num; i++)
	{
		printf("value V2: %u\n", v2->values[i]);
	}
}

static void	dump_debug_info_tag(zbx_vector_tags_ptr_t v1, zbx_vector_tags_ptr_t v2)
{
	for (int i = 0; i < v1.values_num; i++)
	{
		printf("value V1: tag:%s , valued: %s\n", v1.values[i]->tag, v1.values[i]->value);
	}

	for (int i = 0; i < v2.values_num; i++)
	{
		printf("value V2: tag:%s , valued: %s\n", v2.values[i]->tag, v2.values[i]->value);
	}
}


#define TEST_UINT32_AND_INT32(TYPE, ARR_TYPE, GET)								\
														\
	if (SUCCEED == zbx_strcmp_natural(vector_type, #TYPE))							\
	{													\
		zbx_vector_##TYPE##_t	vector_in, vector_out;							\
														\
		zbx_vector_##TYPE##_create(&vector_in);								\
														\
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter_exists("in.not_empty_vector"))			\
		{												\
			extract_yaml_values_##TYPE("in.data", &vector_in);					\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "insert"))						\
		{												\
			zbx_vector_##TYPE##_create(&vector_out);						\
			extract_yaml_values_##TYPE("out.data", &vector_out);					\
														\
			ARR_TYPE	insert_value = zbx_mock_get_parameter_##GET("in.insert_value");		\
			int		index = zbx_mock_get_parameter_int("in.index");				\
														\
			zbx_vector_##TYPE##_insert(&vector_in, insert_value, index);				\
														\
			if(SUCCEED != compare_vectors_##TYPE(&vector_in, &vector_out))				\
				dump_debug_info_##TYPE(&vector_in, &vector_out);				\
														\
			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_##TYPE(&vector_in,	\
					&vector_out));								\
			zbx_vector_##TYPE##_destroy(&vector_out);						\
			zbx_vector_##TYPE##_clear(&vector_out);							\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "append_array"))					\
		{												\
			zbx_vector_##TYPE##_create(&vector_out);						\
			extract_yaml_values_##TYPE("out.data", &vector_out);					\
														\
			ARR_TYPE	*values_array;								\
			size_t		array_size = zbx_mock_get_parameter_uint64("in.array_size");		\
			int		array_size_int = zbx_mock_get_parameter_int("in.array_size");		\
														\
			values_array = malloc(array_size * sizeof(ARR_TYPE));					\
			extract_yaml_values_arr_##TYPE("in.array", values_array);				\
			zbx_vector_##TYPE##_append_array(&vector_in, values_array, array_size_int);		\
														\
			if(SUCCEED != compare_vectors_##TYPE(&vector_in, &vector_out))				\
				dump_debug_info_##TYPE(&vector_in, &vector_out);				\
														\
			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_##TYPE(&vector_in,	\
					&vector_out));								\
			zbx_vector_##TYPE##_destroy(&vector_out);						\
			zbx_vector_##TYPE##_clear(&vector_out);							\
			zbx_free(values_array);									\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "noorder"))					\
		{												\
			int	index = zbx_mock_get_parameter_int("in.index");					\
														\
			zbx_vector_##TYPE##_remove_noorder(&vector_in, index);					\
			is_exit_code(error, exit_code);								\
			zbx_vector_##TYPE##_create(&vector_out);						\
			extract_yaml_values_##TYPE("out.data", &vector_out);					\
														\
			if(SUCCEED != compare_vectors_##TYPE(&vector_in, &vector_out))				\
				dump_debug_info_##TYPE(&vector_in, &vector_out);				\
														\
			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_##TYPE(&vector_in,	\
					&vector_out));								\
			zbx_vector_##TYPE##_destroy(&vector_out);						\
			zbx_vector_##TYPE##_clear(&vector_out);							\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "remove"))						\
		{												\
			int	index = zbx_mock_get_parameter_int("in.index");					\
														\
			zbx_vector_##TYPE##_remove(&vector_in, index);						\
			is_exit_code(error, exit_code);								\
			zbx_vector_##TYPE##_create(&vector_out);						\
			extract_yaml_values_##TYPE("out.data", &vector_out);					\
														\
			if(SUCCEED != compare_vectors_##TYPE(&vector_in, &vector_out))				\
				dump_debug_info_##TYPE(&vector_in, &vector_out);				\
														\
			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_##TYPE(&vector_in,	\
					&vector_out));								\
			zbx_vector_##TYPE##_destroy(&vector_out);						\
			zbx_vector_##TYPE##_clear(&vector_out);							\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "sort"))						\
		{												\
			zbx_vector_##TYPE##_create(&vector_out);						\
			extract_yaml_values_##TYPE("out.data", &vector_out);					\
														\
			zbx_vector_##TYPE##_sort(&vector_in, zbx_default_##GET##_compare_func);			\
														\
			if(SUCCEED != compare_vectors_##TYPE(&vector_in, &vector_out))				\
				dump_debug_info_##TYPE(&vector_in, &vector_out);				\
														\
			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_##TYPE(&vector_in,	\
					&vector_out));								\
			zbx_vector_##TYPE##_destroy(&vector_out);						\
			zbx_vector_##TYPE##_clear(&vector_out);							\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "uniq"))						\
		{												\
			zbx_vector_##TYPE##_create(&vector_out);						\
			extract_yaml_values_##TYPE("out.data", &vector_out);					\
														\
			zbx_vector_##TYPE##_uniq(&vector_in, zbx_default_##GET##_compare_func);			\
														\
			if(SUCCEED != compare_vectors_##TYPE(&vector_in, &vector_out))				\
				dump_debug_info_##TYPE(&vector_in, &vector_out);				\
														\
			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_##TYPE(&vector_in,	\
					&vector_out));								\
			zbx_vector_##TYPE##_destroy(&vector_out);						\
			zbx_vector_##TYPE##_clear(&vector_out);							\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "nearestindex"))					\
		{												\
			ARR_TYPE	target = zbx_mock_get_parameter_##GET("in.target");			\
			int		index_out = zbx_mock_get_parameter_int("out.index");			\
			int		index = zbx_vector_##TYPE##_nearestindex(&vector_in, target,		\
							zbx_default_##GET##_compare_func);			\
			zbx_mock_assert_int_eq("return value", index, index_out);				\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "bsearch"))					\
		{												\
			ARR_TYPE	target = zbx_mock_get_parameter_##GET("in.target");			\
			int		expected_result = zbx_mock_get_parameter_int("out.index");		\
			int		result = zbx_vector_##TYPE##_bsearch(&vector_in, target,		\
							zbx_default_##GET##_compare_func);			\
			zbx_mock_assert_int_eq("return value", expected_result, result);			\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "lsearch"))					\
		{												\
			ARR_TYPE	target = zbx_mock_get_parameter_##GET("in.target");			\
			int		index = atoi(zbx_mock_get_parameter_string("in.index"));		\
			int		expected_result = zbx_mock_str_to_return_code(				\
					zbx_mock_get_parameter_string("out.return"));				\
			int		result = zbx_vector_##TYPE##_lsearch(&vector_in, target, &index,	\
							zbx_default_##GET##_compare_func);			\
			zbx_mock_assert_int_eq("return value", expected_result, result);			\
		}												\
														\
		if (SUCCEED == zbx_strcmp_natural(func_type, "search"))						\
		{												\
			ARR_TYPE	target = zbx_mock_get_parameter_##GET("in.target");			\
			int		expected_result = atoi(zbx_mock_get_parameter_string("out.index"));	\
			int		result = zbx_vector_##TYPE##_search(&vector_in, target,			\
					zbx_default_##GET##_compare_func);					\
			zbx_mock_assert_int_eq("return value", expected_result, result);			\
		}												\
														\
		zbx_vector_##TYPE##_clear(&vector_in);								\
		zbx_vector_##TYPE##_destroy(&vector_in);							\
	}													\

void	zbx_mock_test_entry(void **state)
{
	const char		*vector_type = zbx_mock_get_parameter_string("in.vector_type");
	const char		*func_type = zbx_mock_get_parameter_string("in.func_type");
	zbx_mock_error_t	error = ZBX_MOCK_SUCCESS;
	int			exit_code = 0;

	ZBX_UNUSED(state);

	TEST_UINT32_AND_INT32(uint32, zbx_uint32_t, uint32);
	TEST_UINT32_AND_INT32(int32, int, int);

	if (SUCCEED == zbx_strcmp_natural(vector_type, "zbx_tag"))
	{
		zbx_vector_tags_ptr_t	vector_in;
		zbx_mock_handle_t	handle_in, handle_in_tag, handle_out;

		zbx_vector_tags_ptr_create(&vector_in);

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("data", &handle_in)))
		{
			fail_msg("failed to extract input tags %s", zbx_mock_error_string(error));
			return;
		}

		extract_from_yaml_tag_vector(&vector_in, &handle_in);

		if (SUCCEED == zbx_strcmp_natural(func_type, "insert"))
		{
			zbx_vector_tags_ptr_t	vector_out;
			zbx_tag_t		*tag= (zbx_tag_t *)malloc(sizeof(zbx_tag_t));
			int			index = zbx_mock_get_parameter_int("in.index");

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("tag", &handle_in_tag)))
			{
				fail_msg("failed to extract tag %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag(tag, &handle_in_tag);
			zbx_vector_tags_ptr_insert(&vector_in, tag, index);

			zbx_vector_tags_ptr_create(&vector_out);

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("data_out", &handle_out)))
			{
				fail_msg("failed to extract output tags %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag_vector(&vector_out, &handle_out);

			if (SUCCEED != compare_vectors_tag(vector_in, vector_out))
				dump_debug_info_tag(vector_in, vector_out);

			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_tag(vector_in, vector_out));
			free_tag_vector(vector_out);
		}

		if (SUCCEED == zbx_strcmp_natural(func_type, "append_array"))
		{
			zbx_vector_tags_ptr_t	vector_out;
			zbx_tag_t		**tags;
			size_t			size = zbx_mock_get_parameter_uint64("in.array_size");
			int			size_int = zbx_mock_get_parameter_int("in.array_size");

			tags = (zbx_tag_t **)malloc(size * sizeof(zbx_tag_t *));

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("array", &handle_in_tag)))
			{
				fail_msg("failed to extract tags array %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag_arr(tags, &handle_in_tag);
			zbx_vector_tags_ptr_append_array(&vector_in, tags, size_int);
			zbx_vector_tags_ptr_create(&vector_out);

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("data_out", &handle_out)))
			{
				fail_msg("failed to extract output tags %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag_vector(&vector_out, &handle_out);

			if (SUCCEED != compare_vectors_tag(vector_in, vector_out))
				dump_debug_info_tag(vector_in, vector_out);

			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_tag(vector_in, vector_out));
			free_tag_vector(vector_out);
			zbx_free(tags);
		}

		if (SUCCEED == zbx_strcmp_natural(func_type, "remove"))
		{
			zbx_vector_tags_ptr_t	vector_out;
			zbx_tag_t		*element_to_free;
			int			index = zbx_mock_get_parameter_int("in.index");

			element_to_free = vector_in.values[index];
			zbx_free_tag(element_to_free);
			zbx_vector_tags_ptr_remove(&vector_in, index);
			zbx_vector_tags_ptr_create(&vector_out);

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("data_out", &handle_out)))
			{
				fail_msg("failed to extract output tags %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag_vector(&vector_out, &handle_out);

			if (SUCCEED != compare_vectors_tag(vector_in, vector_out))
				dump_debug_info_tag(vector_in, vector_out);

			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_tag(vector_in, vector_out));
			free_tag_vector(vector_out);
		}

		if (SUCCEED == zbx_strcmp_natural(func_type, "noorder"))
		{
			zbx_vector_tags_ptr_t	vector_out;
			zbx_tag_t		**element_to_free;
			int			index = atoi(zbx_mock_get_parameter_string("in.index"));

			element_to_free = &vector_in.values[index];
			zbx_free((*element_to_free)->tag);
			zbx_free((*element_to_free)->value);
			zbx_free(*element_to_free);
			zbx_vector_tags_ptr_remove_noorder(&vector_in, index);
			zbx_vector_tags_ptr_create(&vector_out);

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("data_out", &handle_out)))
			{
				fail_msg("failed to extract output tags %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag_vector(&vector_out, &handle_out);

			if (SUCCEED != compare_vectors_tag(vector_in, vector_out))
				dump_debug_info_tag(vector_in, vector_out);

			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_tag(vector_in, vector_out));
			free_tag_vector(vector_out);
		}

		if (SUCCEED == zbx_strcmp_natural(func_type, "sort"))
		{
			zbx_vector_tags_ptr_t	vector_out;

			zbx_vector_tags_ptr_sort(&vector_in, zbx_compare_tags_and_values);
			zbx_vector_tags_ptr_create(&vector_out);

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("data_out", &handle_out)))
			{
				fail_msg("failed to extract output tags %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag_vector(&vector_out, &handle_out);

			if (SUCCEED != compare_vectors_tag(vector_in, vector_out))
				dump_debug_info_tag(vector_in, vector_out);

			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_tag(vector_in, vector_out));
			free_tag_vector(vector_out);
		}

		if (SUCCEED == zbx_strcmp_natural(func_type, "uniq"))
		{
			zbx_vector_tags_ptr_t	vector_out, vector_copy;

			zbx_vector_tags_ptr_create(&vector_copy);

			for (int i = 0; i < vector_in.values_num; i++)
				zbx_vector_tags_ptr_append(&vector_copy, vector_in.values[i]);

			zbx_vector_tags_ptr_uniq(&vector_copy, zbx_compare_tags_and_values);
			zbx_vector_tags_ptr_create(&vector_out);

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("data_out", &handle_out)))
			{
				fail_msg("failed to extract output tags %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag_vector(&vector_out, &handle_out);

			if (SUCCEED != compare_vectors_tag(vector_in, vector_out))
				dump_debug_info_tag(vector_in, vector_out);

			zbx_mock_assert_int_eq("return value", SUCCEED, compare_vectors_tag(vector_copy, vector_out));

			zbx_vector_tags_ptr_clear(&vector_copy);
			zbx_vector_tags_ptr_destroy(&vector_copy);
			free_tag_vector(vector_out);
		}

		if (SUCCEED == zbx_strcmp_natural(func_type, "nearestindex"))
		{
			zbx_tag_t	*tag = (zbx_tag_t *)malloc(sizeof(zbx_tag_t));
			int		index_out = zbx_mock_get_parameter_int("out.index");

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("tag", &handle_in_tag)))
			{
				fail_msg("failed to extract tag %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag(tag, &handle_in_tag);

			int	index = zbx_vector_tags_ptr_nearestindex(&vector_in, tag, zbx_compare_tags_and_values);

			zbx_mock_assert_int_eq("return value", index, index_out);
			zbx_free_tag(tag);
		}

		if (SUCCEED == zbx_strcmp_natural(func_type, "bsearch"))
		{
			zbx_tag_t	*tag = (zbx_tag_t *)malloc(sizeof(zbx_tag_t));
			int		index = zbx_mock_get_parameter_int("out.index");

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("tag", &handle_in_tag)))
			{
				fail_msg("failed to extract tag %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag(tag, &handle_in_tag);

			int	result = zbx_vector_tags_ptr_bsearch(&vector_in, tag, zbx_compare_tags_and_values);

			zbx_mock_assert_int_eq("return value", index, result);
			zbx_free_tag(tag);
		}

		if (SUCCEED == zbx_strcmp_natural(func_type, "lsearch"))
		{
			zbx_tag_t	*tag = (zbx_tag_t *)malloc(sizeof(zbx_tag_t));
			int		index = zbx_mock_get_parameter_int("in.index");
			int		expected_result = zbx_mock_str_to_return_code(
							zbx_mock_get_parameter_string("out.return"));

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("tag", &handle_in_tag)))
			{
				fail_msg("failed to extract tag %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag(tag, &handle_in_tag);

			int	result = zbx_vector_tags_ptr_lsearch(&vector_in, tag, &index,
					zbx_compare_tags_and_values);

			zbx_mock_assert_int_eq("return value", expected_result, result);
			zbx_free_tag(tag);
		}

		if (SUCCEED == zbx_strcmp_natural(func_type, "search"))
		{
			zbx_tag_t	*tag = (zbx_tag_t *)malloc(sizeof(zbx_tag_t));
			int		expected_result = zbx_mock_get_parameter_int("out.index");

			if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("tag", &handle_in_tag)))
			{
				fail_msg("failed to extract tag %s", zbx_mock_error_string(error));
				return;
			}

			extract_from_yaml_tag(tag, &handle_in_tag);

			int	result = zbx_vector_tags_ptr_search(&vector_in, tag, zbx_compare_tags_and_values);

			zbx_mock_assert_int_eq("return value", expected_result, result);
			zbx_free_tag(tag);
		}

		free_tag_vector(vector_in);
	}
}
