/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "zbxalgo.h"
#include "zbxeval.h"

static int	zbx_is_normal_double(double dbl)
{
	if (FP_ZERO != fpclassify(dbl) && FP_NORMAL != fpclassify(dbl))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: calc_arithmetic_mean                                             *
 *                                                                            *
 * Purpose: calculate arithmetic mean (i.e. average)                          *
 *                                                                            *
 * Parameters: v - [IN] non-empty vector with input data                      *
 *                                                                            *
 * Return value: arithmetic mean value                                        *
 *                                                                            *
 ******************************************************************************/
static double  calc_arithmetic_mean(const zbx_vector_dbl_t *v)
{
	double  sum = 0;
	int	i;

	for (i = 0; i < v->values_num; i++)
		sum += v->values[i];

	return sum / v->values_num;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_calc_kurtosis                                           *
 *                                                                            *
 * Purpose: evaluate function 'kurtosis'                                      *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL - failed to evaluate function (see 'error')             *
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

static int	zbx_vector_dbl_compare(const void *d1, const void *d2)
{
	const double	*p1 = (const double *)d1;
	const double	*p2 = (const double *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(*p1, *p2);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: find_median                                                      *
 *                                                                            *
 * Purpose: find median (helper function)                                     *
 *                                                                            *
 * Parameters: v - [IN/OUT] non-empty vector with input data.                 *
 *                 NOTE: it will be modified (sorted in place).               *
 *                                                                            *
 * Return value: median                                                       *
 *                                                                            *
 ******************************************************************************/
static double	find_median(zbx_vector_dbl_t *v)
{
	zbx_vector_dbl_sort(v, zbx_vector_dbl_compare);

	if (0 == v->values_num % 2)	/* number of elements is even */
		return (v->values[v->values_num / 2 - 1] + v->values[v->values_num / 2]) / 2.0;
	else
		return v->values[v->values_num / 2];
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_calc_mad                                                *
 *                                                                            *
 * Purpose: calculate 'median absolute deviation'                             *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data.                *
 *                            NOTE: its elements will be modified and should  *
 *                            not be used in the caller!                      *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL - failed to evaluate function (see 'error')             *
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
 * Function: zbx_eval_calc_skewness                                           *
 *                                                                            *
 * Purpose: evaluate 'skewness' function                                      *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL - failed to evaluate function (see 'error')             *
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
 * Function: zbx_eval_calc_stddevpop                                          *
 *                                                                            *
 * Purpose: evaluate function 'stdevpop' (population standard deviation)      *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL - failed to evaluate function (see 'error')             *
 *                                                                            *
 * Comments: the algorithm was taken from "Population standard deviation of   *
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
 * Function: zbx_eval_calc_stddevsamp                                         *
 *                                                                            *
 * Purpose: evaluate function 'stddevsamp' (sample standard deviation)        *
 *                                                                            *
 * Parameters: values - [IN] vector with input data with at least 2 elements  *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL - failed to evaluate function (see 'error')             *
 *                                                                            *
 * Comments: the algorithm was taken from "Population standard deviation of   *
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
 * Function: zbx_eval_calc_sumofsquares                                       *
 *                                                                            *
 * Purpose: calculate sum of squares                                          *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL - failed to evaluate function (see 'error')             *
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
 * Function: zbx_eval_calc_varpop                                             *
 *                                                                            *
 * Purpose: evaluate function 'varpop' (population variance)                  *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL - failed to evaluate function (see 'error')             *
 *                                                                            *
 * Comments: the algorithm was taken from "Population variance" in            *
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
 * Function: zbx_eval_calc_varsamp                                            *
 *                                                                            *
 * Purpose: evaluate function 'varsamp' (sample variance)                     *
 *                                                                            *
 * Parameters: values - [IN] non-empty vector with input data                 *
 *             result - [OUT] calculated value                                *
 *             error  - [OUT] dynamically allocated error message             *
 *                                                                            *
 * Return value: SUCCEED - evaluated successfully                             *
 *               FAIL - failed to evaluate function (see 'error')             *
 *                                                                            *
 * Comments: the algorithm was taken from "Sample variance" in                *
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
