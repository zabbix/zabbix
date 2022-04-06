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

#include "anomalystl.h"

#include "common.h"
#include "log.h"
#include "zbxeval.h"
#include "../zbxalgo/vectorimpl.h"

ZBX_PTR_VECTOR_DECL(VV, zbx_vector_history_record_t *)
ZBX_PTR_VECTOR_IMPL(VV, zbx_vector_history_record_t *)

/*******************************************************************************
 *                                                                             *
 * Purpose: finds how many values in stl remainder are outliers                *
 *                                                                             *
 * Parameters:  remainder        - [IN] stl remainder values vector            *
 *              deviations_count - [IN] how much a value can be away from the  *
 *                                      (mad, stdevsamp or stdevpop) to get    *
 *                                      counted as an outlier                  *
 *                        devalg - [IN] function (mad, stdevsamp or stdevpop)  *
 *                                      to evaluate a single deviation unit    *
 *           detect_period_start - [IN] evaluate number of deviations in       *
 *                                      remainder starting from this time      *
 *                 detect_period - [IN] evaluate number of deviations in       *
 *                                      remainder until this time              *
 *                        result - [OUT] result - double, a percentage how     *
 *                                       many outliers are in remainder        *
 *                         error - [OUT] the error message                     *
 *                                                                             *
 * Return value: SUCCEED - evaluated successfully, 'result' contains new value *
 *               FAIL - evaluation failed, 'result' contains old value         *
 *                                                                             *
 *******************************************************************************/
int	zbx_get_percentage_of_deviations_in_stl_remainder(const zbx_vector_history_record_t *remainder,
		double deviations_count, const char* devalg, int detect_period_start, int detect_period_end,
		double *result, char **error)
{
	int			i, total_values_count = 0, deviations_detected_count = 0, ret = FAIL;
	double			remainder_deviation, deviation_limit;
	zbx_statistical_func_t	stat_func;
	zbx_vector_dbl_t	remainder_values_dbl;

	zbx_vector_dbl_create(&remainder_values_dbl);

	if (0 == remainder->values_num)
	{
		*error = zbx_strdup(*error, "empty remainder");
		goto out;
	}

	if (0 == strcmp("mad", devalg))
	{
		stat_func = zbx_eval_calc_mad;
	}
	else if (0 == strcmp("stddevpop", devalg))
	{
		stat_func = zbx_eval_calc_stddevpop;
	}
	else if (0 == strcmp("stddevsamp", devalg))
	{
		stat_func = zbx_eval_calc_stddevsamp;
	}
	else
	{
		*error = zbx_dsprintf(*error, "undefined devalg parameter: \"%s\"", devalg);
		THIS_SHOULD_NEVER_HAPPEN;
		goto out;
	}

	zbx_vector_dbl_reserve(&remainder_values_dbl, (size_t)remainder->values_num);

	for (i = 0; i < remainder->values_num; i++)
		zbx_vector_dbl_append(&remainder_values_dbl, remainder->values[i].value.dbl);

	if (SUCCEED != (ret = stat_func(&remainder_values_dbl, &remainder_deviation, error)))
		goto out;

	deviation_limit = remainder_deviation * deviations_count;

	for (i = 0; i < remainder->values_num; i++)
	{
		if (remainder->values[i].timestamp.sec >= detect_period_start &&
				remainder->values[i].timestamp.sec <= detect_period_end)
		{
			total_values_count++;

			if (fabs(remainder->values[i].value.dbl) > deviation_limit)
				deviations_detected_count++;
		}
	}

	if (0 == total_values_count)
		*result = 0;
	else
		*result = (double)deviations_detected_count / total_values_count;
out:
	zbx_vector_dbl_destroy(&remainder_values_dbl);

	return ret;
}

static double	nextodd(double x)
{
	x = round(x);

	if (SUCCEED == zbx_double_compare(0, remainder(x, 2.0)))
		x += 1;

	return x;
}

static void	VV_clear(zbx_vector_history_record_t *v)
{
	zbx_history_record_vector_destroy(v, ITEM_VALUE_TYPE_FLOAT);
	zbx_free(v);
}

static int	eval_loess_regression_curve(const zbx_vector_history_record_t *y, int n, int length, int ideg, int xs,
		int nleft, int nright, const zbx_vector_history_record_t *w, int userw,
		const zbx_vector_history_record_t *rw, double *ret)
{
	int			i, ret_status = FAIL, count_mid = 0;
	double			h;
	zbx_vector_dbl_t	r;
	zbx_vector_uint64_t	low_mask, high_mask, mid_mask, lowmid_mask, window, low, high, mid, lowmid;
	double			a = 0;

	h = MAX(xs - nleft, nright - xs);

	if (length > n)
		h += (length - n);

	zbx_vector_dbl_create(&r);
	zbx_vector_uint64_create(&window);
	zbx_vector_uint64_create(&mid_mask);
	zbx_vector_uint64_create(&low_mask);
	zbx_vector_uint64_create(&high_mask);
	zbx_vector_uint64_create(&lowmid_mask);
	zbx_vector_uint64_create(&low);
	zbx_vector_uint64_create(&high);
	zbx_vector_uint64_create(&mid);
	zbx_vector_uint64_create(&lowmid);

	for (i = nleft - xs; i < nright - xs + 1; i++)
		zbx_vector_dbl_append(&r, abs(i));

	for (i = nleft - 1; i < nright; i++)
		zbx_vector_uint64_append(&window, (zbx_uint64_t)i);

	for (i = 0; i < r.values_num; i++)
		zbx_vector_uint64_append(&low_mask, (0.001 * h >= r.values[i]) ? 1 : 0);

	for (i = 0; i < r.values_num; i++)
		zbx_vector_uint64_append(&high_mask, (0.999 * h < r.values[i]) ? 1 : 0);

	for (i = 0; i < low_mask.values_num; i++)
		zbx_vector_uint64_append(&mid_mask, !(low_mask.values[i] | high_mask.values[i]));

	for (i = 0; i < high_mask.values_num; i++)
		zbx_vector_uint64_append(&lowmid_mask, !(high_mask.values[i]));

	/* filter out false entries */
	for (i = 0; i < low_mask.values_num; i++)
	{
		if (1 == low_mask.values[i])
			zbx_vector_uint64_append(&low, window.values[i]);
	}

	for (i = 0; i < high_mask.values_num; i++)
	{
		if (1 == high_mask.values[i])
			zbx_vector_uint64_append(&high, window.values[i]);
	}

	for (i = 0; i < mid_mask.values_num; i++)
	{
		if (1 == mid_mask.values[i])
			zbx_vector_uint64_append(&mid, window.values[i]);
	}

	for (i = 0; i < lowmid_mask.values_num; i++)
	{
		if (1 == lowmid_mask.values[i])
			zbx_vector_uint64_append(&lowmid, window.values[i]);
	}

	for (i = 0; i < low.values_num; i++)
		w->values[low.values[i]].value.dbl = 1;

	for (i = 0; i < mid_mask.values_num; i++)
	{
		if (1 == mid_mask.values[i])
		{
			w->values[mid.values[count_mid]].value.dbl = pow(1 - pow(r.values[i] / h, 3), 3);
			count_mid++;
		}
	}

	if (1 == userw)
	{
		for (i = 0; i < lowmid.values_num; i++)
			w->values[lowmid.values[i]].value.dbl *= rw->values[lowmid.values[i]].value.dbl;
	}

	for (i = 0; i < lowmid.values_num; i++)
		a += w->values[lowmid.values[i]].value.dbl;

	for (i = 0; i < high.values_num; i++)
		w->values[high.values[i]].value.dbl = 0;

	if (0 < a)
	{
		ret_status = SUCCEED;

		for (i = nleft - 1; i < nright; i++)
			w->values[i].value.dbl /= a;

		if (0 < h  && 0 < ideg)
		{
			double	c, b;

			a = 0;

			for (i = nleft - 1; i < nright; i++)
				a += (w->values[i].value.dbl * (i + 1));

			b = xs - a;
			c = 0;

			for (i = nleft - 1; i < nright; i++)
				c += (w->values[i].value.dbl * pow((i + 1 - a), 2));

			if (sqrt(c) > 0.001 * (n - 1))
			{
				b /= c;

				for (i = nleft - 1; i < nright; i++)
					w->values[i].value.dbl *= ((b * ((i + 1) - a)) + 1);
			}
		}

		*ret = 0;

		for (i = nleft - 1; i < nright; i++)
			*ret += w->values[i].value.dbl * y->values[i].value.dbl;
	}

	zbx_vector_dbl_destroy(&r);
	zbx_vector_uint64_destroy(&window);
	zbx_vector_uint64_destroy(&high_mask);
	zbx_vector_uint64_destroy(&low_mask);
	zbx_vector_uint64_destroy(&mid_mask);
	zbx_vector_uint64_destroy(&lowmid_mask);
	zbx_vector_uint64_destroy(&low);
	zbx_vector_uint64_destroy(&high);
	zbx_vector_uint64_destroy(&mid);
	zbx_vector_uint64_destroy(&lowmid);

	return ret_status;
}

static void	apply_loess_smoothing(const zbx_vector_history_record_t *y, int n, int length, int ideg, int njump,
		int userw, const zbx_vector_history_record_t *rw, zbx_vector_history_record_t *ys,
		const zbx_vector_history_record_t *res)
{
	int	newnj, i, nleft, nright;

	if (n < 2)
	{
		ys->values[0].value.dbl = y->values[0].value.dbl;
		return;
	}

	newnj = MIN(njump, n - 1);

	if (length >= n)
	{
		nleft = 1;
		nright = n;

		for (i = 0; i < n; i = i + newnj)
		{
			double	nys;

			if (SUCCEED == eval_loess_regression_curve(y, n, length, ideg, i + 1, nleft, nright, res,
					userw, rw, &nys))
			{
				ys->values[i].value.dbl = nys;
			}
			else
			{
				ys->values[i].value.dbl = y->values[i].value.dbl;
			}
		}
	}
	else
	{
		if (1 == newnj)
		{
			int	nsh;

			nsh = (int)((length + 1) / 2);
			nleft = 1;
			nright = length;

			for (i = 0; i < n; i++)
			{
				double nys;

				if ((i + 1) > nsh && nright != n)
				{
					nleft += 1;
					nright += 1;
				}

				if (SUCCEED == eval_loess_regression_curve(y, n, length, ideg, i + 1, nleft, nright,
						res, userw, rw, &nys))
				{
					ys->values[i].value.dbl = nys;
				}
				else
				{
					ys->values[i].value.dbl = y->values[i].value.dbl;
				}
			}
		}
		else
		{
			int	nsh;

			nsh = (int)((length + 1) / 2);

			for (i = 1; i < n + 1; i = i + newnj)
			{
				double	nys;

				if (i < nsh)
				{
					nleft = 1;
					nright = length;
				}
				else if (i >= (n - nsh + 1))
				{
					nleft = n - length + 1;
					nright = n;
				}
				else
				{
					nleft = i - nsh + 1;
					nright = length + i - nsh;
				}

				if (SUCCEED == eval_loess_regression_curve(y, n, length, ideg, i, nleft, nright,
						res, userw, rw, &nys))
				{
					ys->values[i - 1].value.dbl = nys;
				}
				else
				{
					ys->values[i - 1].value.dbl = y->values[i - 1].value.dbl;
				}
			}
		}
	}

	if (1 != newnj)
	{
		int	k;
		double	delta;

		for (i = 0; i < n - newnj; i = i + newnj)
		{
			int	j;

			delta = (ys->values[i + newnj].value.dbl - ys->values[i].value.dbl) / newnj;

			for (j = i + 1; j < i + newnj; j++)
				ys->values[j].value.dbl = ys->values[i].value.dbl + (delta * (j - i));
		}

		k = (int)(((n - 1)/newnj) * newnj + 1);

		if (k != n)
		{
			double	nys;

			if (SUCCEED == eval_loess_regression_curve(y, n, length, ideg, n, nleft, nright, res,
					userw, rw, &nys))
			{
				ys->values[n - 1].value.dbl = nys;
			}
			else
			{
				ys->values[n - 1].value.dbl = y->values[n - 1].value.dbl;
			}

			if (k != (n - 1))
			{
				delta = (ys->values[n - 1].value.dbl - ys->values[k - 1].value.dbl) / (n - k);

				for (i = k; i < n - 1; i++)
					ys->values[k].value.dbl = ys->values[k - 1].value.dbl + (delta * (i - k + 1));
			}

		}
	}
}

static void	combine_smooth(const zbx_vector_history_record_t *y, int n, int np, int ns, int isdeg, int nsjump,
		int userw, const zbx_vector_history_record_t *rw, zbx_vector_history_record_t *season,
		zbx_vector_history_record_t *work1, zbx_vector_history_record_t *work2,
		zbx_vector_history_record_t *work3, zbx_vector_history_record_t *work4)
{
	int	i;

	for (i = 0; i < np; i++)
	{
		int				k, m, j, nleft, nright;
		double				nval;
		zbx_vector_history_record_t	work_2_copy;

		k = ((n - i - 1) / np) + 1;

		for (j = 0; j < k; j++)
			work1->values[j].value.dbl = y->values[j * np + i].value.dbl;

		if (1 == userw)
		{
			for (j = 0; j < k; j++)
				work3->values[i].value.dbl = rw->values[i * np + i].value.dbl;
		}

		zbx_history_record_vector_create(&work_2_copy);

		for (j = 1; j < work2->values_num; j++)
		{
			zbx_history_record_t	cp;

			cp.timestamp = work2->values[j].timestamp;
			cp.value.dbl = work2->values[j].value.dbl;
			zbx_vector_history_record_append_ptr(&work_2_copy, &cp);
		}

		apply_loess_smoothing(work1, k, ns, isdeg, nsjump, userw, work3, &work_2_copy, work4);

		for (j = 1; j < work2->values_num; j++)
		{
			work2->values[j].timestamp = work_2_copy.values[j - 1].timestamp;
			work2->values[j].value.dbl = work_2_copy.values[j - 1].value.dbl;
		}

		zbx_history_record_vector_destroy(&work_2_copy, ITEM_VALUE_TYPE_FLOAT);

		nright = MIN(ns, k);

		if (SUCCEED == eval_loess_regression_curve(work1, k, ns, isdeg, 0, 1, nright, work4, userw,
				work3, &nval))
		{
			work2->values[0].value.dbl = nval;
		}
		else
		{
			work2->values[0].value.dbl = work2->values[1].value.dbl;
		}

		nleft = MAX(1, k - ns + 1);

		if (SUCCEED == eval_loess_regression_curve(work1, k, ns, isdeg, k+1, nleft, k, work4, userw,
				work3, &nval))
		{
			work2->values[k + 1].value.dbl = nval;
		}
		else
		{
			work2->values[k + 1].value.dbl = work2->values[k].value.dbl;
		}

		for (m = 0; m < k + 2; m++)
		{
			season->values[m * np + i].timestamp = work2->values[m].timestamp;
			season->values[m * np + i].value.dbl = work2->values[m].value.dbl;
		}
	}
}

static void	eval_moving_average(const zbx_vector_history_record_t *x, int n, int length,
		zbx_vector_history_record_t *ave)
{
	int	i, newn;
	double	v = 0;

	for (i = 0; i < length; i++)
		v += x->values[i].value.dbl;

	ave->values[0].value.dbl = v / length;

	newn = n - length + 1;

	if (newn > 1)
	{
		int	k, m, j;

		k = length;
		m = 0;

		for (j = 1; j < newn; j++)
		{
			k += 1;
			m += 1;

			v = v - x->values[m - 1].value.dbl + x->values[k - 1].value.dbl;

			ave->values[j].value.dbl = v / length;
		}
	}
}

static double	find_stl_median(zbx_vector_history_record_t *v)
{
	zbx_vector_history_record_sort(v, (zbx_compare_func_t)history_record_float_compare);

	if (0 == v->values_num % 2)
		return (v->values[v->values_num / 2 - 1].value.dbl + v->values[v->values_num / 2].value.dbl) / 2.0;
	else
		return v->values[v->values_num / 2].value.dbl;
}

static	void eval_robustness_weights(const zbx_vector_history_record_t *y, int n,
		const zbx_vector_history_record_t *fit, zbx_vector_history_record_t *rw)
{
	int				i;
	double				med;
	zbx_vector_uint64_t		low, high, mid;
	zbx_vector_history_record_t	r;

	ZBX_UNUSED(n);

	zbx_vector_uint64_create(&low);
	zbx_vector_uint64_create(&high);
	zbx_vector_uint64_create(&mid);
	zbx_history_record_vector_create(&r);

	for (i = 0; i < y->values_num; i++)
	{
		zbx_history_record_t	cp;

		cp.timestamp = y->values[i].timestamp;
		cp.value.dbl = fabs(y->values[i].value.dbl - fit->values[i].value.dbl);
		zbx_vector_history_record_append_ptr(&r, &cp);
	}

	med = 6 * find_stl_median(&r);

	for (i = 0; i < r.values_num; i++)
	{
		zbx_vector_uint64_append(&low, (r.values[i].value.dbl <= 0.001 * med) ? 1 : 0);
		zbx_vector_uint64_append(&high, (r.values[i].value.dbl > 0.999 * med) ? 1 : 0);
		zbx_vector_uint64_append(&mid, !(low.values[i] | high.values[i]));
	}

	for (i = 0; i < low.values_num; i++)
	{
		if (1 == low.values[i])
			rw->values[i].value.dbl = 1;
	}

	for (i = 0; i < mid.values_num; i++)
	{
		if (1 == mid.values[i])
		{
			rw->values[i].value.dbl = pow(1 - pow(r.values[i].value.dbl, 2), 2);
		}
	}

	for(i = 0; i < high.values_num; i++)
	{
		if (1 == high.values[i])
			rw->values[i].value.dbl = 0;
	}

	zbx_history_record_vector_destroy(&r, ITEM_VALUE_TYPE_FLOAT);

	zbx_vector_uint64_destroy(&low);
	zbx_vector_uint64_destroy(&high);
	zbx_vector_uint64_destroy(&mid);
}

static void	step(const zbx_vector_history_record_t *y, int n, int np, int ns, int nt, int nl, int isdeg, int itdeg,
		int ildeg, int nsjump, int ntjump, int nljump, int ni, int userw, zbx_vector_history_record_t *rw,
		zbx_vector_history_record_t *season, zbx_vector_history_record_t *trend, zbx_vector_VV_t *work)
{
	int	i, j;

	for (i = 0; i < ni; i++)
	{
		zbx_vector_history_record_t	work_0_copy, work_1_copy, work_2_copy, work_3_copy, work_4_copy;

		for (j = 0; j < n; j++)
			work->values[j]->values[0].value.dbl = y->values[j].value.dbl - trend->values[j].value.dbl;

		zbx_history_record_vector_create(&work_0_copy);
		zbx_history_record_vector_create(&work_1_copy);
		zbx_history_record_vector_create(&work_2_copy);
		zbx_history_record_vector_create(&work_3_copy);
		zbx_history_record_vector_create(&work_4_copy);

		for (j = 0; j < work->values_num; j++)
		{
			zbx_history_record_t	cp;

			cp.timestamp = work->values[j]->values[0].timestamp;
			cp.value.dbl = work->values[j]->values[0].value.dbl;
			zbx_vector_history_record_append_ptr(&work_0_copy, &cp);

			cp.timestamp = work->values[j]->values[1].timestamp;
			cp.value.dbl = work->values[j]->values[1].value.dbl;
			zbx_vector_history_record_append_ptr(&work_1_copy, &cp);

			cp.timestamp = work->values[j]->values[2].timestamp;
			cp.value.dbl = work->values[j]->values[2].value.dbl;
			zbx_vector_history_record_append_ptr(&work_2_copy, &cp);

			cp.timestamp = work->values[j]->values[3].timestamp;
			cp.value.dbl = work->values[j]->values[3].value.dbl;
			zbx_vector_history_record_append_ptr(&work_3_copy, &cp);

			cp.timestamp = work->values[j]->values[4].timestamp;
			cp.value.dbl = work->values[j]->values[4].value.dbl;
			zbx_vector_history_record_append_ptr(&work_4_copy, &cp);
		}

		combine_smooth(&work_0_copy, n, np, ns, isdeg, nsjump, userw, rw, &work_1_copy, &work_2_copy,
				&work_3_copy, &work_4_copy, season);

		eval_moving_average(&work_1_copy, n + 2 * np, np, &work_2_copy);
		eval_moving_average(&work_2_copy, n + np + 1, np, &work_0_copy);
		eval_moving_average(&work_0_copy, n + 2, 3, &work_2_copy);

		apply_loess_smoothing(&work_2_copy, n, nl, ildeg, nljump, 0, &work_3_copy, &work_0_copy, &work_4_copy);

		for (j = np; j < np + n; j++)
		{
			season->values[j - np].value.dbl = work_1_copy.values[j].value.dbl -
				work_0_copy.values[j - np].value.dbl;
		}

		for (j = 0; j < n; j++)
			work_0_copy.values[j].value.dbl = y->values[j].value.dbl - season->values[j].value.dbl;

		apply_loess_smoothing(&work_0_copy, n, nt, itdeg, ntjump, userw, rw, trend, &work_2_copy);

		/* save changes from copies back into work */
		for (j = 0; j < work->values_num; j++)
		{
			work->values[j]->values[0].value.dbl = work_0_copy.values[j].value.dbl;
			work->values[j]->values[1].value.dbl = work_1_copy.values[j].value.dbl;
			work->values[j]->values[2].value.dbl = work_2_copy.values[j].value.dbl;
			work->values[j]->values[3].value.dbl = work_3_copy.values[j].value.dbl;
			work->values[j]->values[4].value.dbl = work_4_copy.values[j].value.dbl;
		}

		zbx_history_record_vector_destroy(&work_0_copy, ITEM_VALUE_TYPE_FLOAT);
		zbx_history_record_vector_destroy(&work_1_copy, ITEM_VALUE_TYPE_FLOAT);
		zbx_history_record_vector_destroy(&work_2_copy, ITEM_VALUE_TYPE_FLOAT);
		zbx_history_record_vector_destroy(&work_3_copy, ITEM_VALUE_TYPE_FLOAT);
		zbx_history_record_vector_destroy(&work_4_copy, ITEM_VALUE_TYPE_FLOAT);
	}
}

int	zbx_STL(const zbx_vector_history_record_t *values_in, int freq, int is_robust, int s_window, int s_degree,
		double t_window, int t_degree, int l_window, int l_degree, int nsjump, int ntjump, int nljump,
		int inner, int outer, zbx_vector_history_record_t *trend, zbx_vector_history_record_t *seasonal,
		zbx_vector_history_record_t *remainder, char **error)
{
	int				values_in_len, userw, i, ret = FAIL;
	zbx_vector_history_record_t	weights;
	zbx_vector_VV_t			work;
	double				tmp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	values_in_len = values_in->values_num;

	if (2 > freq)
	{
		*error = zbx_dsprintf(*error, "Frequency (season/h) must be greater than 1, it is: %d", freq);
		goto out;
	}

	if (2 * freq >= values_in_len)
	{
		*error = zbx_dsprintf(*error, "STL requires number of data elements more than two times the frequency. "
				"Frequency (season/h) is: %d, number of data entries is: %d", freq, values_in_len);
		goto out;
	}

	if (S_WINDOW_DEF == s_window)
		s_window = 10 * values_in_len + 1;

	if (S_JUMP_DEF == nsjump)
		nsjump = (int)(tmp = ceil((double)s_window / 10));

	ZBX_UNUSED(tmp);

	if (T_WINDOW_DEF == t_window)
		t_window = nextodd(ceil(1.5 * (double)freq / (1 - (1.5 / s_window))));

	if (T_JUMP_DEF == ntjump)
		ntjump = (int)(tmp = ceil(t_window/10));

	ZBX_UNUSED(tmp);

	if (L_WINDOW_DEF == l_window)
		l_window = nextodd(freq);

	if (L_DEGREE_DEF == l_degree)
		l_degree = t_degree;

	if (L_JUMP_DEF == nljump)
		nljump = (int)(tmp = ceil((double)l_window / 10));

	ZBX_UNUSED(tmp);

	if (INNER_DEF == inner)
		inner = (1 == is_robust) ? 1 : 2;

	if (OUTER_DEF == outer)
		outer = (1 == is_robust) ? 15 : 0;

	zbx_vector_history_record_reserve(seasonal, (size_t)values_in_len);
	zbx_vector_history_record_reserve(trend, (size_t)values_in_len);
	zbx_history_record_vector_create(&weights);
	zbx_vector_history_record_reserve(&weights, (size_t)values_in_len);
	zbx_vector_history_record_reserve(remainder, (size_t)values_in_len);

	for (i = 0; i < values_in_len; i++)
	{
		zbx_history_record_t	value1, value2, value3, value4;

		value1.timestamp = values_in->values[i].timestamp;
		value1.value.dbl = 0;
		zbx_vector_history_record_append_ptr(&weights, &value1);

		value2.timestamp = values_in->values[i].timestamp;
		value2.value.dbl = 0;
		zbx_vector_history_record_append_ptr(seasonal, &value2);

		value3.timestamp = values_in->values[i].timestamp;
		value3.value.dbl = 0;
		zbx_vector_history_record_append_ptr(trend, &value3);

		value4.timestamp = values_in->values[i].timestamp;
		value4.value.dbl = 0;
		zbx_vector_history_record_append_ptr(remainder, &value4);
	}

	zbx_vector_VV_create(&work);
	zbx_vector_VV_reserve(&work, (size_t)(values_in_len + 2 * freq));

	for (i = 0; i < work.values_alloc; i++)
	{
		int				j;
		zbx_vector_history_record_t	*work_temp;

		work_temp = (zbx_vector_history_record_t*)zbx_malloc(NULL, sizeof(zbx_vector_history_record_t));
		zbx_history_record_vector_create(work_temp);

		for (j = 0; j < 5; j++)
		{
			zbx_history_record_t	x;

			x.value.dbl = 0;
			zbx_vector_history_record_append_ptr(work_temp, &x);
		}

		zbx_vector_VV_append(&work, work_temp);
	}

	s_window = MAX(3, s_window);
	t_window = MAX(3, t_window);
	l_window = MAX(3, l_window);

	if (0 == (s_window % 2))
		s_window += 1;

	if (0 == ((int)t_window % 2))
		t_window += 1;

	if (0 == (l_window % 2))
		l_window += 1;

	userw = 0;

	step(values_in, values_in_len, freq, s_window, (int)t_window, l_window, s_degree, t_degree, l_degree, nsjump,
			ntjump, nljump, inner, userw, &weights, seasonal, trend, &work);

	userw = 1;

	for (i = 0; i < outer; i++)
	{
		int				j;
		zbx_vector_history_record_t	work_0_copy;

		zbx_history_record_vector_create(&work_0_copy);

		for (j = 0; j < values_in_len; j++)
		{
			work.values[j]->values[0].value.dbl = trend->values[j].value.dbl +
					seasonal->values[j].value.dbl;
		}

		for (j = 0; j < work.values_num; j++)
		{
			zbx_history_record_t	cp;

			cp.timestamp = work.values[j]->values[0].timestamp;
			cp.value.dbl = work.values[j]->values[0].value.dbl;
			zbx_vector_history_record_append_ptr(&work_0_copy, &cp);
		}

		eval_robustness_weights(values_in, values_in_len, &work_0_copy, &weights);
		step(values_in, values_in_len, freq, s_window, t_window, l_window, s_degree, t_degree, l_degree, nsjump,
				ntjump, nljump, inner, userw, &weights, seasonal, trend, &work);

		zbx_history_record_vector_destroy(&work_0_copy, ITEM_VALUE_TYPE_FLOAT);
	}

	if (0 >= outer)
	{
		for (i = 0; i < weights.values_num; i++)
			weights.values[i].value.dbl = 1;
	}

	for (i = 0; i < values_in->values_num; i++)
	{
		remainder->values[i].value.dbl = values_in->values[i].value.dbl - trend->values[i].value.dbl -
				seasonal->values[i].value.dbl;
	}

	zbx_vector_VV_clear_ext(&work, VV_clear);
	zbx_vector_VV_destroy(&work);
	zbx_history_record_vector_destroy(&weights, ITEM_VALUE_TYPE_FLOAT);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
