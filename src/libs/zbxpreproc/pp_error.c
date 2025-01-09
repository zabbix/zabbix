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

#include "pp_error.h"

#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbxvariant.h"
#include "zbxalgo.h"
#include "zbxdbschema.h"

/* mock field to estimate how much data can be stored in characters, bytes or both, */
/* depending on database backend                                                    */
typedef struct
{
	int	bytes_num;
	int	chars_num;
}
zbx_db_mock_field_t;

ZBX_PTR_VECTOR_IMPL(pp_result_ptr, zbx_pp_result_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: set result value                                                  *
 *                                                                            *
 * Parameters: result    - [OUT] result to set                                *
 *             value     - [IN] field type in database schema                 *
 *             action    - [IN] on fail action                                *
 *             value_raw - [IN] value before applying on fail action if       *
 *                              non-default action was applied. This value is *
 *                              'moved' over to result.                       *
 *                                                                            *
 ******************************************************************************/
void	pp_result_set(zbx_pp_result_t *result, const zbx_variant_t *value, int action, zbx_variant_t *value_raw)
{
	zbx_variant_copy(&result->value, value);
	result->value_raw = *value_raw;
	zbx_variant_set_none(value_raw);
	result->action = action;
}

void	zbx_pp_result_free(zbx_pp_result_t *result)
{
	zbx_variant_clear(&result->value);
	zbx_variant_clear(&result->value_raw);
	zbx_free(result);
}

void	pp_free_results(zbx_pp_result_t *results, int results_num)
{
	for (int i = 0; i < results_num; i++)
	{
		zbx_variant_clear(&results[i].value);
		zbx_variant_clear(&results[i].value_raw);
	}

	zbx_free(results);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes mock field                                            *
 *                                                                            *
 * Parameters: field      - [OUT] field data                                  *
 *             field_type - [IN] field type in database schema                *
 *             field_len  - [IN] field size in database schema                *
 *                                                                            *
 ******************************************************************************/
static void	zbx_db_mock_field_init(zbx_db_mock_field_t *field, int field_type, int field_len)
{
	switch (field_type)
	{
		case ZBX_TYPE_CHAR:
			field->chars_num = field_len;
			field->bytes_num = -1;
			return;
	}

	THIS_SHOULD_NEVER_HAPPEN;

	field->chars_num = 0;
	field->bytes_num = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: 'appends' text to the field, if successful the character/byte     *
 *           limits are updated                                               *
 *                                                                            *
 * Parameters: field - [IN/OUT] mock field                                    *
 *             text  - [IN] text to append                                    *
 *                                                                            *
 * Return value: SUCCEED - the field had enough space to append the text      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	zbx_db_mock_field_append(zbx_db_mock_field_t *field, const char *text)
{
	int	bytes_num, chars_num;

	if (-1 != field->bytes_num)
	{
		bytes_num = (int)strlen(text);
		if (bytes_num > field->bytes_num)
			return FAIL;
	}
	else
		bytes_num = 0;

	if (-1 != field->chars_num)
	{
		chars_num = (int)zbx_strlen_utf8(text);
		if (chars_num > field->chars_num)
			return FAIL;
	}
	else
		chars_num = 0;

	field->bytes_num -= bytes_num;
	field->chars_num -= chars_num;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: format value in text format                                       *
 *                                                                            *
 * Parameters: value     - [IN] value to format                               *
 *             value_str - [OUT] formatted value                              *
 *                                                                            *
 * Comments: Control characters are replaced with '.' and truncated if it's   *
 *           larger than ZBX_PP_VALUE_PREVIEW_LEN characters.                 *
 *                                                                            *
 ******************************************************************************/
static void	pp_error_format_value(const zbx_variant_t *value, char **value_str)
{
	const char	*value_desc;
	size_t		i, len;

#define ZBX_PP_VALUE_PREVIEW_LEN	100

	value_desc = zbx_variant_value_desc(value);

	if (ZBX_PP_VALUE_PREVIEW_LEN < zbx_strlen_utf8(value_desc))
	{
		/* truncate value and append '...' */
		len = zbx_strlen_utf8_nchars(value_desc, ZBX_PP_VALUE_PREVIEW_LEN - ZBX_CONST_STRLEN("..."));
		*value_str = zbx_malloc(NULL, len + ZBX_CONST_STRLEN("...") + 1);
		memcpy(*value_str, value_desc, len);
		memcpy(*value_str + len, "...", ZBX_CONST_STRLEN("...") + 1);
	}
	else
	{
		*value_str = zbx_malloc(NULL, (len = strlen(value_desc)) + 1);
		memcpy(*value_str, value_desc, len + 1);
	}

	/* replace control characters */
	for (i = 0; i < len; i++)
	{
		if (0 != iscntrl((*value_str)[i]))
			(*value_str)[i] = '.';
	}

#undef ZBX_PP_VALUE_PREVIEW_LEN
}

/******************************************************************************
 *                                                                            *
 * Purpose: format one preprocessing step result                              *
 *                                                                            *
 * Parameters: step   - [IN] preprocessing step number                        *
 *             result - [IN] preprocessing step result                        *
 *             out    - [OUT] formatted string                                *
 *                                                                            *
 ******************************************************************************/
static void	pp_error_format_result(int step, const zbx_pp_result_t *result, char **out)
{
	char	*actions[] = {"", " (discard value)", " (set value)", " (set error)"};

	if (ZBX_VARIANT_ERR != result->value.type)
	{
		char	*value_str;

		pp_error_format_value(&result->value, &value_str);
		*out = zbx_dsprintf(NULL, "%d. Result%s: %s\n", step, actions[result->action], value_str);
		zbx_free(value_str);
	}
	else
	{
		*out = zbx_dsprintf(NULL, "%d. Failed%s: %s\n", step, actions[result->action],
				zbx_variant_value_desc(&result->value));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: format preprocessing error message                                *
 *                                                                            *
 * Parameters: value        - [IN] input value                                *
 *             results      - [IN] preprocessing step results                 *
 *             results_num  - [IN] number of executed steps                   *
 *             error        - [OUT] formatted error message                   *
 *                                                                            *
 ******************************************************************************/
void	pp_format_error(const zbx_variant_t *value, zbx_pp_result_t *results, int results_num, char **error)
{
	char			*value_str, *err_step;
	int			i;
	size_t			error_alloc = 512, error_offset = 0;
	zbx_vector_str_t	results_str;
	zbx_db_mock_field_t	field;

	zbx_vector_str_create(&results_str);

	/* add header to error message */
	*error = zbx_malloc(NULL, error_alloc);
	pp_error_format_value(value, &value_str);
	zbx_snprintf_alloc(error, &error_alloc, &error_offset, "Preprocessing failed for: %s\n", value_str);
	zbx_free(value_str);

	zbx_db_mock_field_init(&field, ZBX_TYPE_CHAR, ZBX_ITEM_ERROR_LEN);

	zbx_db_mock_field_append(&field, *error);
	zbx_db_mock_field_append(&field, "...\n");

	/* format the last (failed) step */
	pp_error_format_result(results_num, &results[results_num - 1], &err_step);
	zbx_vector_str_append(&results_str, err_step);

	if (SUCCEED == zbx_db_mock_field_append(&field, err_step))
	{
		/* format the first steps */
		for (i = results_num - 2; i >= 0; i--)
		{
			pp_error_format_result(i + 1, &results[i], &err_step);

			if (SUCCEED != zbx_db_mock_field_append(&field, err_step))
			{
				zbx_free(err_step);
				break;
			}

			zbx_vector_str_append(&results_str, err_step);
		}
	}

	/* add steps to error message */

	if (results_str.values_num < results_num)
		zbx_strcpy_alloc(error, &error_alloc, &error_offset, "...\n");

	for (i = results_str.values_num - 1; i >= 0; i--)
		zbx_strcpy_alloc(error, &error_alloc, &error_offset, results_str.values[i]);

	zbx_rtrim(*error, ZBX_WHITESPACE);

	/* truncate formatted error if necessary */
	if (ZBX_ITEM_ERROR_LEN < zbx_strlen_utf8(*error))
	{
		char	*ptr;

		ptr = (*error) + zbx_strlen_utf8_nchars(*error, ZBX_ITEM_ERROR_LEN - 3);
		for (i = 0; i < 3; i++)
			*ptr++ = '.';
		*ptr = '\0';
	}

	zbx_vector_str_clear_ext(&results_str, zbx_str_free);
	zbx_vector_str_destroy(&results_str);
}

/******************************************************************************
 *                                                                            *
 * Purpose: apply 'on fail' preprocessing error handler                       *
 *                                                                            *
 * Parameters: value - [IN/OUT]                                               *
 *             step  - [IN] preprocessing operation that produced error       *
 *                                                                            *
 ******************************************************************************/
int	pp_error_on_fail(zbx_dc_um_shared_handle_t *um_handle, zbx_uint64_t hostid, zbx_variant_t *value,
		const zbx_pp_step_t *step)
{
	char	*error_handler_params;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	switch (step->error_handler)
	{
		case ZBX_PREPROC_FAIL_DISCARD_VALUE:
			zbx_variant_clear(value);
			break;
		case ZBX_PREPROC_FAIL_SET_VALUE:
		case ZBX_PREPROC_FAIL_SET_ERROR:
			zbx_variant_clear(value);

			error_handler_params = zbx_strdup(NULL, step->error_handler_params);

			if (NULL != um_handle)
			{
				char	*error = NULL;

				if (SUCCEED != zbx_dc_expand_user_and_func_macros_from_cache(um_handle->um_cache,
						&error_handler_params, &hostid, 1, ZBX_MACRO_ENV_NONSECURE, &error))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "cannot resolve user macros: %s", error);
					zbx_free(error);
				}
			}

			if (ZBX_PREPROC_FAIL_SET_VALUE == step->error_handler)
			{
				zbx_variant_set_str(value, error_handler_params);
			}
			else if (ZBX_PREPROC_FAIL_SET_ERROR == step->error_handler)
			{
				zbx_variant_set_error(value, error_handler_params);
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				zabbix_log(LOG_LEVEL_ERR, "unexpected \"custom on fail\" handler type %d",
						step->error_handler);
				zbx_free(error_handler_params);
			}

			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() value:%s", __func__, zbx_variant_value_desc(value));

	return step->error_handler;
}
