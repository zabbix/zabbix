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

#include "zbxdbhigh.h"

#include "zbxalgo.h"
#include "zbxstr.h"

ZBX_PTR_VECTOR_IMPL(item_param_ptr, zbx_item_param_t *)

zbx_item_param_t	*zbx_item_param_create(const char *item_param_name,
		const char *item_param_value)
{
	zbx_item_param_t	*item_param;

	item_param = (zbx_item_param_t *)zbx_malloc(NULL, sizeof(zbx_item_param_t));

	item_param->item_parameterid = 0;
	item_param->flags = ZBX_FLAG_ITEM_PARAM_UPDATE_RESET;
	item_param->name = zbx_strdup(NULL, item_param_name);
	item_param->name_orig = NULL;
	item_param->value = zbx_strdup(NULL, item_param_value);
	item_param->value_orig = NULL;

	return item_param;
}

void	zbx_item_param_free(zbx_item_param_t *param)
{

	if (0 != (param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE_NAME))
		zbx_free(param->name_orig);
	zbx_free(param->name);

	if (0 != (param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE))
		zbx_free(param->value_orig);
	zbx_free(param->value);

	zbx_free(param);
}

/******************************************************************************
 *                                                                            *
 * Purpose: roll back item_param updates done during merge process            *
 *                                                                            *
 * Return value: SUCCEED - updates were rolled back                           *
 *               FAIL    - new item_param, rollback impossible                *
 *                                                                            *
 ******************************************************************************/
static int	item_param_rollback(zbx_item_param_t *item_param)
{
	if (0 == item_param->item_parameterid)
		return FAIL;

	if (0 != (item_param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE_NAME))
	{
		zbx_free(item_param->name);
		item_param->name = item_param->name_orig;
		item_param->name_orig = NULL;
		item_param->flags &= (~ZBX_FLAG_ITEM_PARAM_UPDATE_NAME);
	}

	if (0 != (item_param->flags & ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE))
	{
		zbx_free(item_param->value);
		item_param->value = item_param->value_orig;
		item_param->value_orig = NULL;
		item_param->flags &= (~ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE);
	}

	return SUCCEED;
}

#define ZBX_ITEM_PARAM_OP(item_param) (0 == item_param->item_parameterid ? "create" : "update")

typedef enum
{
	ZBX_ITEM_PARAM_NAME,
	ZBX_ITEM_PARAM_VALUE
}
zbx_item_param_field_t;

/******************************************************************************
 *                                                                            *
 * Purpose: check validness of a single item_param field (name or value)      *
 *                                                                            *
 * Return value: SUCCEED - item_param field is valid                          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	item_param_check_field(const zbx_item_param_t *item_param, zbx_item_param_field_t type, char **error)
{
	const char	*field, *str;
	size_t		field_len, str_len;

	switch (type)
	{
		case ZBX_ITEM_PARAM_NAME:
			field = "name";
			str = item_param->name;
			field_len = ZBX_ITEM_PARAMETER_NAME_LEN;
			break;
		case ZBX_ITEM_PARAM_VALUE:
			field = "value";
			str = item_param->value;
			field_len = ZBX_ITEM_PARAMETER_VALUE_LEN;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;

			if (NULL != error)
			{
				*error = zbx_strdcatf(*error, "Cannot %s item parameter: invalid field type.\n",
						ZBX_ITEM_PARAM_OP(item_param));
			}
			return FAIL;
	}

	if (SUCCEED != zbx_is_utf8(str))
	{
		if (NULL != error)
		{
			char	*ptr_utf8;

			ptr_utf8 = zbx_strdup(NULL, str);
			zbx_replace_invalid_utf8(ptr_utf8);
			*error = zbx_strdcatf(*error, "Cannot %s item parameter: %s \"%s\" has invalid UTF-8"
					" sequence.\n", ZBX_ITEM_PARAM_OP(item_param), field, ptr_utf8);
			zbx_free(ptr_utf8);
		}

		return FAIL;
	}

	str_len = zbx_strlen_utf8(str);

	if (field_len < str_len)
	{
		if (NULL != error)
		{
			*error = zbx_strdcatf(*error, "Cannot %s item parameter: %s \"%128s...\" is too long.\n",
					ZBX_ITEM_PARAM_OP(item_param), field, str);
		}
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check validness of all fields for a list of item_params           *
 *                                                                            *
 * Return value: SUCCEED - item_params have valid fields                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	check_item_param_fields(zbx_vector_item_param_ptr_t *item_params, char **error)
{
	int	i, ret = SUCCEED;

	for (i = 0; i < item_params->values_num; i++)
	{
		zbx_item_param_t	*item_param = item_params->values[i];
		int			errors = 0;

		if (0 != (item_param->flags & ZBX_FLAG_ITEM_PARAM_DELETE))
			continue;

		if ('\0' == *item_param->name)
		{
			if (NULL != error)
			{
				*error = zbx_strdcatf(*error, "Cannot %s item parameter: empty item parameter name.\n",
						ZBX_ITEM_PARAM_OP(item_param));
			}
			errors += FAIL;
		}

		errors += item_param_check_field(item_param, ZBX_ITEM_PARAM_NAME, error);
		errors += item_param_check_field(item_param, ZBX_ITEM_PARAM_VALUE, error);

		if (0 > errors)
		{
			if (SUCCEED != item_param_rollback(item_param))
			{
				zbx_item_param_free(item_param);
				zbx_vector_item_param_ptr_remove_noorder(item_params, i--);
			}

			ret = FAIL;
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check new item_params for duplicate name+value combinations       *
 *                                                                            *
 * Parameters: item_params  - [IN/OUT] item_params to check                   *
 *             error        - [OUT] the error message                         *
 *                                                                            *
 * Return value: SUCCEED - item_params have no duplicates                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Existing item_params are rolled back to their original values,   *
 *           while new item_params are removed.                               *
 *                                                                            *
 ******************************************************************************/
static int	check_duplicate_item_params(zbx_vector_item_param_ptr_t *item_params, char **error)
{
	int	i, j, ret = SUCCEED;

	for (i = 0; i < item_params->values_num; i++)
	{
		zbx_item_param_t	*left = item_params->values[i];

		for (j = 0; j < i; j++)
		{
			zbx_item_param_t	*right = item_params->values[j];

			if (0 == strcmp(left->name, right->name) && 0 == strcmp(left->value, right->value))
			{
				if (NULL != error)
				{
					*error = zbx_strdcatf(*error, "Cannot %s %s item parameter: \"%s\" already "
							"exists.\n", ZBX_ITEM_PARAM_OP(left), left->name,
							right->value);
				}

				zbx_item_param_free(left);
				zbx_vector_item_param_ptr_remove_noorder(item_params, i--);

				ret = FAIL;
				break;
			}
		}
	}

	return ret;
}
#undef ZBX_ITEM_PARAM_OP

/******************************************************************************
 *                                                                            *
 * Purpose: merge new item_params into existing                               *
 *                                                                            *
 * Parameters: dst    - [IN/OUT] vector of existing item_params               *
 *             src    - [IN/OUT] vector or new item_params                    *
 *                           optional - must be specified if error parameter  *
 *                           is not null                                      *
 *             error  - [IN,OUT] the error message (appended to existing),    *
 *                           optional                                         *
 *                                                                            *
 * Comments: The item_params are merged using the following logic:            *
 *           1) item_params with matching name+value are left as it is        *
 *           2) item_params with matching names will have their values        *
 *              updated                                                       *
 *           3) item_params without matches will have:                        *
 *              a) their name and value updated if there are new item_params  *
 *                 left                                                       *
 *              b) flagged to be removed otherwise                            *
 *           4) all leftover new item_params will be created                  *
 *                                                                            *
 * Return value: SUCCEED - item_params were merged without issues             *
 *               FAIL - item_params were merged with errors                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_merge_item_params(zbx_vector_item_param_ptr_t *dst, zbx_vector_item_param_ptr_t *src, char **error)
{
	int	i, j, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() old item parameters:%d new item parameters:%d", __func__, dst->values_num,
			src->values_num);

	ret = check_duplicate_item_params(src, error);

	/* perform exact name + value match */
	for (i = 0; i < dst->values_num; i++)
	{
		for (j = 0; j < src->values_num; j++)
		{
			if (0 == strcmp(dst->values[i]->name, src->values[j]->name) &&
					0 == strcmp(dst->values[i]->value, src->values[j]->value))
			{
				break;
			}
		}

		if (j != src->values_num)
		{
			zbx_item_param_free(src->values[j]);
			zbx_vector_item_param_ptr_remove_noorder(src, j);
			continue;
		}

		dst->values[i]->flags = ZBX_FLAG_ITEM_PARAM_DELETE;
	}

	if (0 == src->values_num)
		goto out;

	/* perform item_param match */
	for (i = 0; i < dst->values_num; i++)
	{
		if (ZBX_FLAG_ITEM_PARAM_DELETE != dst->values[i]->flags)
			continue;

		for (j = 0; j < src->values_num; j++)
		{
			if (0 == strcmp(dst->values[i]->name, src->values[j]->name))
				break;
		}

		if (j != src->values_num)
		{
			dst->values[i]->value_orig = dst->values[i]->value;
			dst->values[i]->value = src->values[j]->value;
			dst->values[i]->flags = ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE;
			src->values[j]->value = NULL;
			zbx_item_param_free(src->values[j]);
			zbx_vector_item_param_ptr_remove_noorder(src, j);
			continue;
		}
	}

	if (0 == src->values_num)
		goto out;

	/* update rest of the item_params */
	for (i = 0; i < dst->values_num && 0 < src->values_num; i++)
	{
		if (ZBX_FLAG_ITEM_PARAM_DELETE != dst->values[i]->flags)
			continue;

		dst->values[i]->name_orig = dst->values[i]->name;
		dst->values[i]->value_orig = dst->values[i]->value;
		dst->values[i]->name = src->values[0]->name;
		dst->values[i]->value = src->values[0]->value;
		dst->values[i]->flags = ZBX_FLAG_ITEM_PARAM_UPDATE_NAME | ZBX_FLAG_ITEM_PARAM_UPDATE_VALUE;
		src->values[0]->name = NULL;
		src->values[0]->value = NULL;
		zbx_item_param_free(src->values[0]);
		zbx_vector_item_param_ptr_remove_noorder(src, 0);
		continue;
	}

	/* add leftover new item_params */
	zbx_vector_item_param_ptr_append_array(dst, src->values, src->values_num);
	zbx_vector_item_param_ptr_clear(src);
out:
	if (SUCCEED != check_item_param_fields(dst, error))
		ret = FAIL;

	zbx_vector_item_param_ptr_sort(dst, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() item parameter:%d", __func__, dst->values_num);

	return ret;
}
