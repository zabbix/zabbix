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
#include "valuecache.h"
#include "dbcache.h"
#include "checks_calculated.h"

#include "checks_aggregate.h"

#define ZBX_VALUE_FUNC_UNKNOWN	0
#define ZBX_VALUE_FUNC_MIN	1
#define ZBX_VALUE_FUNC_AVG	2
#define ZBX_VALUE_FUNC_MAX	3
#define ZBX_VALUE_FUNC_SUM	4
#define ZBX_VALUE_FUNC_COUNT	5
#define ZBX_VALUE_FUNC_LAST	6


#define MATCH_STRING(x, name, len)	ZBX_CONST_STRLEN(x) == len && 0 == memcmp(name, x, len)

static int	get_function_by_name(const char *name, size_t len)
{

	if (MATCH_STRING("avg_foreach", name, len))
		return ZBX_VALUE_FUNC_AVG;

	if (MATCH_STRING("count_foreach", name, len))
		return ZBX_VALUE_FUNC_COUNT;

	if (MATCH_STRING("last_foreach", name, len))
		return ZBX_VALUE_FUNC_LAST;

	if (MATCH_STRING("max_foreach", name, len))
		return ZBX_VALUE_FUNC_MAX;

	if (MATCH_STRING("min_foreach", name, len))
		return ZBX_VALUE_FUNC_MIN;

	if (MATCH_STRING("sum_foreach", name, len))
		return ZBX_VALUE_FUNC_SUM;

	return ZBX_VALUE_FUNC_UNKNOWN;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_min                                        *
 *                                                                            *
 * Purpose: calculate minimum value from the history value vector             *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_min(zbx_vector_history_record_t *values, int value_type, double *result)
{
	int	i;

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		*result = (double)values->values[0].value.ui64;

		for (i = 1; i < values->values_num; i++)
			if ((double)values->values[i].value.ui64 < *result)
				*result = (double)values->values[i].value.ui64;
	}
	else
	{
		*result = values->values[0].value.dbl;

		for (i = 1; i < values->values_num; i++)
			if (values->values[i].value.dbl < *result)
				*result = values->values[i].value.dbl;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_max                                        *
 *                                                                            *
 * Purpose: calculate maximum value from the history value vector             *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_max(zbx_vector_history_record_t *values, int value_type, double *result)
{
	int	i;

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		*result = (double)values->values[0].value.ui64;

		for (i = 1; i < values->values_num; i++)
			if ((double)values->values[i].value.ui64 > *result)
				*result = (double)values->values[i].value.ui64;
	}
	else
	{
		*result = values->values[0].value.dbl;

		for (i = 1; i < values->values_num; i++)
			if (values->values[i].value.dbl > *result)
				*result = values->values[i].value.dbl;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_sum                                        *
 *                                                                            *
 * Purpose: calculate sum of values from the history value vector             *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_sum(zbx_vector_history_record_t *values, int value_type, double *result)
{
	int	i;

	*result = 0;

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		for (i = 0; i < values->values_num; i++)
			*result += (double)values->values[i].value.ui64;
	}
	else
	{
		for (i = 0; i < values->values_num; i++)
			*result += values->values[i].value.dbl;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_avg                                        *
 *                                                                            *
 * Purpose: calculate average value of values from the history value vector   *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_avg(zbx_vector_history_record_t *values, int value_type, double *result)
{
	evaluate_history_func_sum(values, value_type, result);
	*result /= values->values_num;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_count                                      *
 *                                                                            *
 * Purpose: calculate number of values in value vector                        *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_count(zbx_vector_history_record_t *values, double *result)
{
	*result = (double)values->values_num;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_last                                       *
 *                                                                            *
 * Purpose: calculate the last (newest) value in value vector                 *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_last(zbx_vector_history_record_t *values, int value_type, double *result)
{
	if (ITEM_VALUE_TYPE_UINT64 == value_type)
		*result = (double)values->values[0].value.ui64;
	else
		*result = values->values[0].value.dbl;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func                                            *
 *                                                                            *
 * Purpose: calculate function with values from value vector                  *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             func        - [IN] the function to calculate. Only             *
 *                           ZBX_VALUE_FUNC_MIN, ZBX_VALUE_FUNC_AVG,          *
 *                           ZBX_VALUE_FUNC_MAX, ZBX_VALUE_FUNC_SUM,          *
 *                           ZBX_VALUE_FUNC_COUNT, ZBX_VALUE_FUNC_LAST        *
 *                           functions are supported.                         *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func(zbx_vector_history_record_t *values, int value_type, int func,
		double *result)
{
	switch (func)
	{
		case ZBX_VALUE_FUNC_MIN:
			evaluate_history_func_min(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_AVG:
			evaluate_history_func_avg(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_MAX:
			evaluate_history_func_max(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_SUM:
			evaluate_history_func_sum(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_COUNT:
			evaluate_history_func_count(values, result);
			break;
		case ZBX_VALUE_FUNC_LAST:
			evaluate_history_func_last(values, value_type, result);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: calc_get_item                                                    *
 *                                                                            *
 * Purpose: get item from cache by itemid                                     *
 *                                                                            *
 * Parameters: eval    - [IN] the evaluation data                             *
 *             itemid  - [IN] the item identifier                             *
 *                                                                            *
 * Return value: The cached item.                                             *
 *                                                                            *
 ******************************************************************************/
DC_ITEM	*get_dcitem(zbx_vector_ptr_t *dcitem_refs, zbx_uint64_t itemid)
{
	int	index;

	if (FAIL == (index = zbx_vector_ptr_bsearch(dcitem_refs, &itemid, calc_find_dcitem_by_itemid)))
		return NULL;

	return dcitem_refs->values[index];
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_aggregate                                               *
 *                                                                            *
 * Parameters: item      - [IN] aggregated item                               *
 *             grp_func  - [IN] one of ZBX_GRP_FUNC_*                         *
 *             groups    - [IN] list of host groups                           *
 *             itemkey   - [IN] item key to aggregate                         *
 *             item_func - [IN] one of ZBX_VALUE_FUNC_*                       *
 *             param     - [IN] item_func parameter (optional)                *
 *                                                                            *
 * Return value: SUCCEED - aggregate item evaluated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	evaluate_aggregate(zbx_vector_uint64_t *itemids, zbx_vector_ptr_t *dcitem_refs, const zbx_timespec_t *ts,
		const char *func_name, size_t len, int args_num, const zbx_variant_t *args, zbx_variant_t *value,
		char **error)
{
	int				ret = FAIL, i, count, seconds, item_func;
	zbx_vector_history_record_t	values;
	zbx_vector_dbl_t		*results;
	double				result;
	zbx_variant_t			arg;

	if (ZBX_VALUE_FUNC_UNKNOWN == (item_func = get_function_by_name(func_name, len)))
	{
		*error = zbx_strdup(NULL, "unsupported function");
		return FAIL;
	}

	if (ZBX_VALUE_FUNC_LAST == item_func)
	{
		if (0 != args_num)
		{
			*error = zbx_strdup(NULL, "invalid number of function parameters");
			return FAIL;
		}

		count = 1;
		seconds = 0;
	}
	else
	{
		if (1 != args_num)
		{
			*error = zbx_strdup(NULL, "invalid number of function parameters");
			return FAIL;
		}

		if (ZBX_VARIANT_STR == args[0].type)
		{
			if (FAIL == is_time_suffix(args[0].data.str, &seconds, ZBX_LENGTH_UNLIMITED))
			{
				*error = zbx_strdup(NULL, "invalid second parameter");
				goto out;
			}
		}
		else
		{
			zbx_variant_copy(&arg, &args[0]);

			if (SUCCEED != zbx_variant_convert(&arg, ZBX_VARIANT_DBL))
			{
				zbx_variant_clear(&arg);
				*error = zbx_strdup(NULL, "invalid second parameter");
				return FAIL;
			}

			seconds = arg.data.dbl;
			zbx_variant_clear(&arg);
		}
	}

	results = (zbx_vector_dbl_t *)zbx_malloc(NULL, sizeof(zbx_vector_dbl_t));
	zbx_vector_dbl_create(results);

	for (i = 0; i < itemids->values_num; i++)
	{
		DC_ITEM	*dcitem;

		if (NULL == (dcitem = get_dcitem(dcitem_refs, itemids->values[i])))
			continue;

		if (ITEM_STATUS_ACTIVE != dcitem->status)
			continue;

		if (HOST_STATUS_MONITORED != dcitem->host.status)
			continue;

		if (ITEM_VALUE_TYPE_FLOAT != dcitem->value_type && ITEM_VALUE_TYPE_UINT64 != dcitem->value_type)
			continue;

		zbx_history_record_vector_create(&values);

		if (SUCCEED == zbx_vc_get_values(dcitem->itemid, dcitem->value_type, &values, seconds, count, ts) &&
				0 < values.values_num)
		{
			evaluate_history_func(&values, dcitem->value_type, item_func, &result);
			zbx_vector_dbl_append(results, result);
		}

		zbx_history_record_vector_destroy(&values, dcitem->value_type);
	}

	if (0 == results->values_num)
	{
		zbx_vector_dbl_destroy(results);
		zbx_free(results);

		*error = zbx_strdup(NULL, "no data for query");
		goto out;
	}

	zbx_variant_set_dbl_vector(value, results);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
