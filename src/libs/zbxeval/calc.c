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

#include "zbxeval.h"

#include "zbxnum.h"
#include "zbxalgo.h"

typedef struct
{
	double	upper;
	double	count;
}
zbx_histogram_t;
ZBX_VECTOR_DECL(histogram, zbx_histogram_t)
ZBX_VECTOR_IMPL(histogram, zbx_histogram_t)

static int	zbx_is_normal_double(double dbl)
{
	if (FP_ZERO != fpclassify(dbl) && FP_NORMAL != fpclassify(dbl))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates arithmetic mean (i.e. average)                         *
 *                                                                            *
 * Parameters: v - [IN] non-empty vector with input data                      *
 *                                                                            *
 * Return value: arithmetic mean value                                        *
 *                                                                            *
 ******************************************************************************/
static double	calc_arithmetic_mean(const zbx_vector_dbl_t *v)
{
	double	sum = 0;
	int	i;

	for (i = 0; i < v->values_num; i++)
		sum += v->values[i];

	return sum / v->values_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates function 'kurtosis'                                     *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_kurtosis(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	mean, second_moment = 0, fourth_moment = 0, second_moment2, res;
	int	i;

	/* step 1: calculate arithmetic mean */
	mean = calc_arithmetic_mean(values);

	if (SUCCEED != zbx_is_normal_double(mean))
		goto err;

	/* step 2: calculate the second and the fourth moments */

	for (i = 0; i < values->values_num; i++)
	{
		double	diff = values->values[i] - mean;

		second_moment += diff * diff;
		fourth_moment += diff * diff * diff * diff;
	}

	second_moment /= values->values_num;
	fourth_moment /= values->values_num;

	/* step 3: calculate kurtosis */

	second_moment2 = second_moment * second_moment;

	if (FP_NORMAL != fpclassify(second_moment2) || SUCCEED != zbx_is_normal_double(fourth_moment))
		goto err;

	res = fourth_moment / second_moment2;

	if (SUCCEED != zbx_is_normal_double(res))
		goto err;

	*result = res;

	return SUCCEED;
err:
	*error = zbx_strdup(*error, "cannot calculate kurtosis() value");

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: finds median (helper function)                                    *
 *                                                                            *
 * Parameters: v - [IN/OUT] non-empty vector with input data                  *
 *                          NOTE: it will be modified (sorted in place).      *
 *                                                                            *
 * Return value: median                                                       *
 *                                                                            *
 ******************************************************************************/
static double	find_median(zbx_vector_dbl_t *v)
{
	zbx_vector_dbl_sort(v, ZBX_DEFAULT_DBL_COMPARE_FUNC);

	if (0 == v->values_num % 2)	/* number of elements is even */
		return (v->values[v->values_num / 2 - 1] + v->values[v->values_num / 2]) / 2.0;
	else
		return v->values[v->values_num / 2];
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates 'median absolute deviation'                            *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *                            NOTE: its elements will be modified and should  *
 *                            not be used in the caller!                      *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_mad(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	median;
	int	i;

	/* step 1: find median of input data */
	median = find_median(values);

	if (SUCCEED != zbx_is_normal_double(median))
		goto err;

	/* step 2: find absolute differences of input data and median. Reuse input data vector. */

	for (i = 0; i < values->values_num; i++)
		values->values[i] = fabs(values->values[i] - median);

	/* step 3: find median of the differences */
	median = find_median(values);

	if (SUCCEED != zbx_is_normal_double(median))
		goto err;

	*result = median;

	return SUCCEED;
err:
	*error = zbx_strdup(*error, "cannot calculate mad() value");

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates 'skewness' function                                     *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_skewness(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	mean, std_dev = 0, sum_diff3 = 0, divisor;
	int	i;

	/* step 1: calculate arithmetic mean */
	mean = calc_arithmetic_mean(values);

	if (SUCCEED != zbx_is_normal_double(mean))
		goto err;

	/* step 2: calculate the standard deviation and sum_diff3 */

	for (i = 0; i < values->values_num; i++)
	{
		double	diff = values->values[i] - mean;

		std_dev += diff * diff;
		sum_diff3 += diff * diff * diff;
	}

	std_dev = sqrt(std_dev / values->values_num);

	/* step 3: calculate skewness */

	divisor = values->values_num * std_dev * std_dev * std_dev;

	if (FP_NORMAL != fpclassify(divisor) || SUCCEED != zbx_is_normal_double(sum_diff3))
		goto err;

	*result = sum_diff3 / divisor;

	return SUCCEED;
err:
	*error = zbx_strdup(*error, "cannot calculate skewness() value");

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates function 'stdevpop' (population standard deviation)     *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 * Comments: algorithm was taken from "Population standard deviation of       *
 *           grades of eight students" in                                     *
 *           https://en.wikipedia.org/wiki/Standard_deviation                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_stddevpop(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	mean, std_dev = 0;
	int	i;

	/* step 1: calculate arithmetic mean */
	mean = calc_arithmetic_mean(values);

	if (SUCCEED != zbx_is_normal_double(mean))
		goto err;

	/* step 2: calculate the standard deviation */

	for (i = 0; i < values->values_num; i++)
	{
		double	diff = values->values[i] - mean;

		std_dev += diff * diff;
	}

	std_dev = sqrt(std_dev / values->values_num);

	if (SUCCEED != zbx_is_normal_double(std_dev))
		goto err;

	*result = std_dev;

	return SUCCEED;
err:
	*error = zbx_strdup(*error, "cannot calculate stddevpop() value");

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates function 'stddevsamp' (sample standard deviation)       *
 *                                                                            *
 * Parameters: values - [IN] vector with input data with at least 2 elements  *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 * Comments: algorithm was taken from "Population standard deviation of       *
 *           grades of eight students" in                                     *
 *           https://en.wikipedia.org/wiki/Standard_deviation                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_stddevsamp(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	mean, std_dev = 0;
	int	i;

	if (2 > values->values_num)	/* stddevsamp requires at least 2 data values */
	{
		*error = zbx_strdup(*error, "not enough data");
		return FAIL;
	}

	/* step 1: calculate arithmetic mean */
	mean = calc_arithmetic_mean(values);

	if (SUCCEED != zbx_is_normal_double(mean))
		goto err;

	/* step 2: calculate the standard deviation */

	for (i = 0; i < values->values_num; i++)
	{
		double	diff = values->values[i] - mean;

		std_dev += diff * diff;
	}

	std_dev = sqrt(std_dev / (values->values_num - 1));	/* divided by 'n - 1' because */
								/* sample standard deviation */
	if (SUCCEED != zbx_is_normal_double(std_dev))
		goto err;

	*result = std_dev;

	return SUCCEED;
err:
	*error = zbx_strdup(*error, "cannot calculate stddevsamp() value");

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates sum of squares                                         *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_sumofsquares(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	sum = 0;
	int	i;

	for (i = 0; i < values->values_num; i++)
		sum += values->values[i] * values->values[i];

	if (SUCCEED != zbx_is_normal_double(sum))
	{
		*error = zbx_strdup(*error, "cannot calculate sumofsquares() value");
		return FAIL;
	}

	*result = sum;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates function 'varpop' (population variance)                 *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 * Comments: algorithm was taken from "Population variance" in                *
 *           https://en.wikipedia.org/wiki/Variance#Population_variance       *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_varpop(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	mean, res = 0;
	int	i;

	/* step 1: calculate arithmetic mean */
	mean = calc_arithmetic_mean(values);

	if (SUCCEED != zbx_is_normal_double(mean))
		goto err;

	/* step 2: calculate the population variance */

	for (i = 0; i < values->values_num; i++)
	{
		double	diff = values->values[i] - mean;

		res += diff * diff;
	}

	res /= values->values_num;	/* divide by 'number of values' for population variance */

	if (SUCCEED != zbx_is_normal_double(res))
		goto err;

	*result = res;

	return SUCCEED;
err:
	*error = zbx_strdup(*error, "cannot calculate varpop() value");

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates function 'varsamp' (sample variance)                    *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 * Comments: algorithm was taken from "Sample variance" in                    *
 *           https://en.wikipedia.org/wiki/Variance#Population_variance       *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_varsamp(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	mean, res = 0;
	int	i;

	if (2 > values->values_num)	/* varsamp requires at least 2 data values */
	{
		*error = zbx_strdup(*error, "not enough data");
		return FAIL;
	}

	/* step 1: calculate arithmetic mean */
	mean = calc_arithmetic_mean(values);

	if (SUCCEED != zbx_is_normal_double(mean))
		goto err;

	/* step 2: calculate the sample variance */

	for (i = 0; i < values->values_num; i++)
	{
		double	diff = values->values[i] - mean;

		res += diff * diff;
	}

	res /= values->values_num - 1;	/* divide by 'number of values' - 1 for unbiased sample variance */

	if (SUCCEED != zbx_is_normal_double(res))
		goto err;

	*result = res;

	return SUCCEED;
err:
	*error = zbx_strdup(*error, "cannot calculate varsamp() value");

	return FAIL;
}

static void	remove_duplicate_backet(zbx_vector_histogram_t *h)
{
	zbx_histogram_t	b, last = h->values[0];
	int		i, inx = 0;

	for (i = 1; i < h->values_num; i++)
	{
		b = h->values[i];

		if (SUCCEED == zbx_double_compare(b.upper, last.upper))
		{
			last.count += b.count;
		}
		else
		{
			h->values[inx] = last;
			last = b;
			inx++;
		}
	}

	h->values[inx] = last;

	while (h->values_num > inx + 1)
		zbx_vector_histogram_remove_noorder(h, h->values_num - 1);
}

static void	ensure_histogram_monotonic(zbx_vector_histogram_t *h)
{
	double	max = h->values[0].count;
	int	i;

	for (i = 1; i < h->values_num; i++)
	{
		if (h->values[i].count > max)
			max = h->values[i].count;
		else if (h->values[i].count < max)
			h->values[i].count = max;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculates histogram quantile base on vector, where odd position  *
 *          is bucket upper bound ('le') and even position is 'rate' value    *
 *                                                                            *
 * Parameters: q      - [IN] quantile value from 0 till 1                     *
 *             values - [IN] non-empty vector with input data                 *
 *             err_fn - [IN] function name for error info                     *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_histogram_quantile(const double q, const zbx_vector_dbl_t *values, const char *err_fn,
		double *result, char **error)
{
#	define LAST(v)		v.values[v.values_num - 1]

	zbx_vector_histogram_t	histogram;
	double			res, total, rank, count, end, start;
	zbx_histogram_t		hg;
	int			i, ret = FAIL;

	if (0 == values->values_num)
	{
		*error = zbx_dsprintf(*error, "invalid parameter: number of histogram buckets must not be zero"
				" for function at \"%s\"",err_fn);
		return FAIL;
	}

	zbx_vector_histogram_create(&histogram);

	for (i = 0; i < values->values_num;)
	{
		hg.upper = values->values[i++];
		hg.count = values->values[i++];
		zbx_vector_histogram_append(&histogram, hg);
	}

	if (histogram.values_num < 2)
	{
		*error = zbx_dsprintf(*error, "invalid number of rate buckets for function at \"%s\"",err_fn);
		goto err;
	}

	zbx_vector_histogram_sort(&histogram, ZBX_DEFAULT_DBL_COMPARE_FUNC);

	if (FP_INFINITE != fpclassify(LAST(histogram).upper))
	{
		*error = zbx_dsprintf(*error, "invalid last infinity rate buckets for function at \"%s\"", err_fn);
		goto err;
	}

	remove_duplicate_backet(&histogram);

	if (histogram.values_num < 2)
	{
		*error = zbx_dsprintf(*error,
				"invalid number of rate buckets with duplicates for function at \"%s\"", err_fn);
		goto err;
	}

	ensure_histogram_monotonic(&histogram);
	total = LAST(histogram).count;

	if (FP_ZERO == fpclassify(total))
	{
		res = -1;	/* preprocessing pending with discard value */
		goto end;
	}

	rank = q * total;

	for (i = 0; i < histogram.values_num - 1; i++)
	{
		if (histogram.values[i].count >= rank)
			break;
	}

	if (i == histogram.values_num - 1)
	{
		res = histogram.values[histogram.values_num - 2].upper;
		goto end;
	}

	if (0 == i && 0 >= histogram.values[0].upper)
	{
		res = histogram.values[0].upper;
		goto end;
	}

	start = 0;
	end = histogram.values[i].upper;
	count = histogram.values[i].count;

	if (i > 0)
	{
		start = histogram.values[i - 1].upper;
		count -= histogram.values[i - 1].count;
		rank -= histogram.values[i - 1].count;
	}

	res = start + (end - start) * (rank / count);

end:
	if (SUCCEED != zbx_is_normal_double(res))
	{
		*error = zbx_dsprintf(*error, "cannot calculate value for function at \"%s\"", err_fn);
		goto err;
	}

	*result = res;
	ret = SUCCEED;
err:
	zbx_vector_histogram_destroy(&histogram);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates function avg                                            *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_avg(zbx_vector_dbl_t *values, double *result, char **error)
{
	if (0 == values->values_num)
	{
		*error = zbx_strdup(*error, "no data (at least one value is required)");
		return FAIL;
	}

	*result = calc_arithmetic_mean(values);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates function min                                            *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_min(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	value;
	int	i;

	if (0 == values->values_num)
	{
		*error = zbx_strdup(*error, "no data (at least one value is required)");
		return FAIL;
	}

	value = values->values[0];

	for (i = 1; i < values->values_num; i++)
	{
		if (values->values[i] < value)
			value = values->values[i];
	}

	*result = value;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates function max                                            *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_max(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	value;
	int	i;

	if (0 == values->values_num)
	{
		*error = zbx_strdup(*error, "no data (at least one value is required)");
		return FAIL;
	}

	value = values->values[0];

	for (i = 1; i < values->values_num; i++)
	{
		if (values->values[i] > value)
			value = values->values[i];
	}

	*result = value;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluates function sum                                            *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL    - failed to evaluate function (see 'error')          *
 *                                                                            *
 ******************************************************************************/
int	zbx_eval_calc_sum(zbx_vector_dbl_t *values, double *result, char **error)
{
	double	value;
	int	i;

	if (0 == values->values_num)
	{
		*error = zbx_strdup(*error, "no data (at least one value is required)");
		return FAIL;
	}

	value = 0;

	for (i = 0; i < values->values_num; i++)
		value += values->values[i];

	*result = value;

	return SUCCEED;
}
