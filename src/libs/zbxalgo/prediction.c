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

#include "zbxalgo.h"

#include "zbxnum.h"

#define ZBX_MATH_EPSILON	(1e-6)

#define ZBX_IS_NAN(x)	((x) != (x))
#define ZBX_VALID_MATRIX(m)		(0 < (m)->rows && 0 < (m)->columns && NULL != (m)->elements)
#define ZBX_MATRIX_EL(m, row, col)	((m)->elements[(row) * (m)->columns + (col)])
#define ZBX_MATRIX_ROW(m, row)		((m)->elements + (row) * (m)->columns)

typedef struct
{
	int	rows;
	int	columns;
	double	*elements;
}
zbx_matrix_t;

static void	zbx_matrix_struct_alloc(zbx_matrix_t **pm)
{
	*pm = (zbx_matrix_t *)zbx_malloc(*pm, sizeof(zbx_matrix_t));

	(*pm)->rows = 0;
	(*pm)->columns = 0;
	(*pm)->elements = NULL;
}

static int	zbx_matrix_alloc(zbx_matrix_t *m, int rows, int columns)
{
	if (0 >= rows || 0 >= columns)
		goto error;

	m->rows = rows;
	m->columns = columns;

	m->elements = (double *)zbx_malloc(m->elements, sizeof(double) * rows * columns);

	return SUCCEED;
error:
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
}

static void	zbx_matrix_free(zbx_matrix_t *m)
{
	if (NULL != m)
		zbx_free(m->elements);

	zbx_free(m);
}

static int	zbx_matrix_copy(zbx_matrix_t *dest, zbx_matrix_t *src)
{
	if (!ZBX_VALID_MATRIX(src))
		goto error;

	if (SUCCEED != zbx_matrix_alloc(dest, src->rows, src->columns))
		return FAIL;

	memcpy(dest->elements, src->elements, sizeof(double) * src->rows * src->columns);
	return SUCCEED;
error:
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
}

static int	zbx_identity_matrix(zbx_matrix_t *m, int n)
{
	if (SUCCEED != zbx_matrix_alloc(m, n, n))
		return FAIL;

	for (int i = 0; i < n; i++)
		for (int j = 0; j < n; j++)
			ZBX_MATRIX_EL(m, i, j) = (i == j ? 1.0 : 0.0);

	return SUCCEED;
}

static int	zbx_transpose_matrix(zbx_matrix_t *m, zbx_matrix_t *r)
{
	if (!ZBX_VALID_MATRIX(m))
		goto error;

	if (SUCCEED != zbx_matrix_alloc(r, m->columns, m->rows))
		return FAIL;

	for (int i = 0; i < r->rows; i++)
		for (int j = 0; j < r->columns; j++)
			ZBX_MATRIX_EL(r, i, j) = ZBX_MATRIX_EL(m, j, i);

	return SUCCEED;
error:
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
}

static void	zbx_matrix_swap_rows(zbx_matrix_t *m, int r1, int r2)
{
	double	tmp;

	for (int i = 0; i < m->columns; i++)
	{
		tmp = ZBX_MATRIX_EL(m, r1, i);
		ZBX_MATRIX_EL(m, r1, i) = ZBX_MATRIX_EL(m, r2, i);
		ZBX_MATRIX_EL(m, r2, i) = tmp;
	}
}

static void	zbx_matrix_divide_row_by(zbx_matrix_t *m, int row, double denominator)
{
	for (int i = 0; i < m->columns; i++)
		ZBX_MATRIX_EL(m, row, i) /= denominator;
}

static void	zbx_matrix_add_rows_with_factor(zbx_matrix_t *m, int dest, int src, double factor)
{
	for (int i = 0; i < m->columns; i++)
		ZBX_MATRIX_EL(m, dest, i) += ZBX_MATRIX_EL(m, src, i) * factor;
}

static int	zbx_inverse_matrix(zbx_matrix_t *m, zbx_matrix_t *r)
{
	zbx_matrix_t	*l = NULL;
	double		pivot, factor, det;
	int		i, j, k, n, res;

	if (!ZBX_VALID_MATRIX(m) || m->rows != m->columns)
		goto error;

	n = m->rows;

	if (1 == n)
	{
		if (SUCCEED != zbx_matrix_alloc(r, 1, 1))
			return FAIL;

		if (0.0 == ZBX_MATRIX_EL(m, 0, 0))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "matrix is singular");
			res = FAIL;
			goto out;
		}

		ZBX_MATRIX_EL(r, 0, 0) = 1.0 / ZBX_MATRIX_EL(m, 0, 0);
		return SUCCEED;
	}

	if (2 == n)
	{
		if (SUCCEED != zbx_matrix_alloc(r, 2, 2))
			return FAIL;

		if (0.0 == (det = ZBX_MATRIX_EL(m, 0, 0) * ZBX_MATRIX_EL(m, 1, 1) -
				ZBX_MATRIX_EL(m, 0, 1) * ZBX_MATRIX_EL(m, 1, 0)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "matrix is singular");
			res = FAIL;
			goto out;
		}

		ZBX_MATRIX_EL(r, 0, 0) = ZBX_MATRIX_EL(m, 1, 1) / det;
		ZBX_MATRIX_EL(r, 0, 1) = -ZBX_MATRIX_EL(m, 0, 1) / det;
		ZBX_MATRIX_EL(r, 1, 0) = -ZBX_MATRIX_EL(m, 1, 0) / det;
		ZBX_MATRIX_EL(r, 1, 1) = ZBX_MATRIX_EL(m, 0, 0) / det;
		return SUCCEED;
	}

	if (SUCCEED != zbx_identity_matrix(r, n))
		return FAIL;

	zbx_matrix_struct_alloc(&l);

	if (SUCCEED != (res = zbx_matrix_copy(l, m)))
		goto out;

	/* Gauss-Jordan elimination with partial (row) pivoting */
	for (i = 0; i < n; i++)
	{
		k = i;
		pivot = ZBX_MATRIX_EL(l, i, i);

		for (j = i; j < n; j++)
		{
			if (fabs(ZBX_MATRIX_EL(l, j, i)) > fabs(pivot))
			{
				k = j;
				pivot = ZBX_MATRIX_EL(l, j, i);
			}
		}

		if (0.0 == pivot)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "matrix is singular");
			res = FAIL;
			goto out;
		}

		if (k != i)
		{
			zbx_matrix_swap_rows(l, i, k);
			zbx_matrix_swap_rows(r, i, k);
		}

		for (j = i + 1; j < n; j++)
		{
			if (0.0 != (factor = -ZBX_MATRIX_EL(l, j, i) / ZBX_MATRIX_EL(l, i, i)))
			{
				zbx_matrix_add_rows_with_factor(l, j, i, factor);
				zbx_matrix_add_rows_with_factor(r, j, i, factor);
			}
		}
	}

	for (i = n - 1; i > 0; i--)
	{
		for (j = 0; j < i; j++)
		{
			if (0.0 != (factor = -ZBX_MATRIX_EL(l, j, i) / ZBX_MATRIX_EL(l, i, i)))
			{
				zbx_matrix_add_rows_with_factor(l, j, i, factor);
				zbx_matrix_add_rows_with_factor(r, j, i, factor);
			}
		}
	}

	for (i = 0; i < n; i++)
		zbx_matrix_divide_row_by(r, i, ZBX_MATRIX_EL(l, i, i));

	res = SUCCEED;
out:
	zbx_matrix_free(l);
	return res;
error:
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
}

static int	zbx_matrix_mult(zbx_matrix_t *left, zbx_matrix_t *right, zbx_matrix_t *result)
{
	double	element;

	if (!ZBX_VALID_MATRIX(left) || !ZBX_VALID_MATRIX(right) || left->columns != right->rows)
		goto error;

	if (SUCCEED != zbx_matrix_alloc(result, left->rows, right->columns))
		return FAIL;

	for (int i = 0; i < result->rows; i++)
	{
		for (int j = 0; j < result->columns; j++)
		{
			element = 0;

			for (int k = 0; k < left->columns; k++)
				element += ZBX_MATRIX_EL(left, i, k) * ZBX_MATRIX_EL(right, k, j);

			ZBX_MATRIX_EL(result, i, j) = element;
		}
	}

	return SUCCEED;
error:
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
}

static int	zbx_least_squares(zbx_matrix_t *independent, zbx_matrix_t *dependent, zbx_matrix_t *coefficients)
{
	/*                         |<----------to_be_inverted---------->|                                          */
	/* coefficients = inverse( transpose( independent ) * independent ) * transpose( independent ) * dependent */
	/*                |<------------------left_part------------------>|   |<-----------right_part----------->| */
	/*           we change order of matrix multiplication to reduce operation count and memory usage           */
	zbx_matrix_t	*independent_transposed = NULL, *to_be_inverted = NULL, *left_part = NULL, *right_part = NULL;
	int		res;

	zbx_matrix_struct_alloc(&independent_transposed);
	zbx_matrix_struct_alloc(&to_be_inverted);
	zbx_matrix_struct_alloc(&left_part);
	zbx_matrix_struct_alloc(&right_part);

	if (SUCCEED != (res = zbx_transpose_matrix(independent, independent_transposed)))
		goto out;

	if (SUCCEED != (res = zbx_matrix_mult(independent_transposed, independent, to_be_inverted)))
		goto out;

	if (SUCCEED != (res = zbx_inverse_matrix(to_be_inverted, left_part)))
		goto out;

	if (SUCCEED != (res = zbx_matrix_mult(independent_transposed, dependent, right_part)))
		goto out;

	if (SUCCEED != (res = zbx_matrix_mult(left_part, right_part, coefficients)))
		goto out;

out:
	zbx_matrix_free(independent_transposed);
	zbx_matrix_free(to_be_inverted);
	zbx_matrix_free(left_part);
	zbx_matrix_free(right_part);

	return res;
}

static int	zbx_fill_dependent(double *x, int n, zbx_fit_t fit, zbx_matrix_t *m)
{
	if (FIT_LINEAR == fit || FIT_POLYNOMIAL == fit || FIT_LOGARITHMIC == fit)
	{
		if (SUCCEED != zbx_matrix_alloc(m, n, 1))
			return FAIL;

		for (int i = 0; i < n; i++)
			ZBX_MATRIX_EL(m, i, 0) = x[i];
	}
	else if (FIT_EXPONENTIAL == fit || FIT_POWER == fit)
	{
		if (SUCCEED != zbx_matrix_alloc(m, n, 1))
			return FAIL;

		for (int i = 0; i < n; i++)
		{
			if (0.0 >= x[i])
			{
				zabbix_log(LOG_LEVEL_DEBUG, "data contains negative or zero values");
				return FAIL;
			}

			ZBX_MATRIX_EL(m, i, 0) = log(x[i]);
		}
	}

	return SUCCEED;
}

static int	zbx_fill_independent(double *t, int n, zbx_fit_t fit, int k, zbx_matrix_t *m)
{
	double	element;

	if (FIT_LINEAR == fit || FIT_EXPONENTIAL == fit)
	{
		if (SUCCEED != zbx_matrix_alloc(m, n, 2))
			return FAIL;

		for (int i = 0; i < n; i++)
		{
			ZBX_MATRIX_EL(m, i, 0) = 1.0;
			ZBX_MATRIX_EL(m, i, 1) = t[i];
		}
	}
	else if (FIT_LOGARITHMIC == fit || FIT_POWER == fit)
	{
		if (SUCCEED != zbx_matrix_alloc(m, n, 2))
			return FAIL;

		for (int i = 0; i < n; i++)
		{
			ZBX_MATRIX_EL(m, i, 0) = 1.0;
			ZBX_MATRIX_EL(m, i, 1) = log(t[i]);
		}
	}
	else if (FIT_POLYNOMIAL == fit)
	{
		if (k > n - 1)
			k = n - 1;

		if (SUCCEED != zbx_matrix_alloc(m, n, k+1))
			return FAIL;

		for (int i = 0; i < n; i++)
		{
			element = 1.0;

			for (int j = 0; j < k; j++)
			{
				ZBX_MATRIX_EL(m, i, j) = element;
				element *= t[i];
			}

			ZBX_MATRIX_EL(m, i, k) = element;
		}
	}

	return SUCCEED;
}

static int	zbx_regression(double *t, double *x, int n, zbx_fit_t fit, int k, zbx_matrix_t *coefficients)
{
	zbx_matrix_t	*independent = NULL, *dependent = NULL;
	int		res;

	zbx_matrix_struct_alloc(&independent);
	zbx_matrix_struct_alloc(&dependent);

	if (SUCCEED != (res = zbx_fill_independent(t, n, fit, k, independent)))
		goto out;

	if (SUCCEED != (res = zbx_fill_dependent(x, n, fit, dependent)))
		goto out;

	if (SUCCEED != (res = zbx_least_squares(independent, dependent, coefficients)))
		goto out;

out:
	zbx_matrix_free(independent);
	zbx_matrix_free(dependent);

	return res;
}

static double	zbx_polynomial_value(double t, zbx_matrix_t *coefficients)
{
	double	pow = 1.0, res = 0.0;

	for (int i = 0; i < coefficients->rows; i++, pow *= t)
		res += ZBX_MATRIX_EL(coefficients, i, 0) * pow;

	return res;
}

static double	zbx_polynomial_antiderivative(double t, zbx_matrix_t *coefficients)
{
	double	pow = t, res = 0.0;

	for (int i = 0; i < coefficients->rows; i++, pow *= t)
		res += ZBX_MATRIX_EL(coefficients, i, 0) * pow / (i + 1);

	return res;
}

static int	zbx_derive_polynomial(zbx_matrix_t *polynomial, zbx_matrix_t *derivative)
{
	int	i;

	if (!ZBX_VALID_MATRIX(polynomial))
		goto error;

	if (SUCCEED != zbx_matrix_alloc(derivative, (polynomial->rows > 1 ? polynomial->rows - 1 : 1), 1))
		return FAIL;

	for (i = 1; i < polynomial->rows; i++)
		ZBX_MATRIX_EL(derivative, i - 1, 0) = ZBX_MATRIX_EL(polynomial, i, 0) * i;

	if (1 == i)
		ZBX_MATRIX_EL(derivative, 0, 0) = 0.0;

	return SUCCEED;
error:
	THIS_SHOULD_NEVER_HAPPEN;
	return FAIL;
}

static int	zbx_polynomial_roots(zbx_matrix_t *coefficients, zbx_matrix_t *roots)
{
#define Re(z)	(z)[0]
#define Im(z)	(z)[1]

#define ZBX_COMPLEX_MULT(z1, z2, tmp)			\
do							\
{							\
	Re(tmp) = Re(z1) * Re(z2) - Im(z1) * Im(z2);	\
	Im(tmp) = Re(z1) * Im(z2) + Im(z1) * Re(z2);	\
	Re(z1) = Re(tmp);				\
	Im(z1) = Im(tmp);				\
}							\
while(0)

#define ZBX_MAX_ITERATIONS	200

	zbx_matrix_t	*denominator_multiplicands = NULL, *updates = NULL;
	double		z[2], mult[2], denominator[2], zpower[2], polynomial[2], highest_degree_coefficient,
			lower_bound, upper_bound, radius, max_update, min_distance, residual, temp;
	int		i, j, degree, first_nonzero, res, iteration = 0, roots_ok = 0, root_init = 0;

	if (!ZBX_VALID_MATRIX(coefficients))
		goto error;

	degree = coefficients->rows - 1;
	highest_degree_coefficient = ZBX_MATRIX_EL(coefficients, degree, 0);

	while (0.0 == highest_degree_coefficient && 0 < degree)
		highest_degree_coefficient = ZBX_MATRIX_EL(coefficients, --degree, 0);

	if (0 == degree)
	{
		/* please check explicitly for an attempt to solve equation 0 == 0 */
		if (0.0 == highest_degree_coefficient)
			goto error;

		return SUCCEED;
	}

	if (1 == degree)
	{
		if (SUCCEED != zbx_matrix_alloc(roots, 1, 2))
			return FAIL;

		Re(ZBX_MATRIX_ROW(roots, 0)) = -ZBX_MATRIX_EL(coefficients, 0, 0) / ZBX_MATRIX_EL(coefficients, 1, 0);
		Im(ZBX_MATRIX_ROW(roots, 0)) = 0.0;

		return SUCCEED;
	}

	if (2 == degree)
	{
		if (SUCCEED != zbx_matrix_alloc(roots, 2, 2))
			return FAIL;

		if (0.0 < (temp = ZBX_MATRIX_EL(coefficients, 1, 0) * ZBX_MATRIX_EL(coefficients, 1, 0) -
				4 * ZBX_MATRIX_EL(coefficients, 2, 0) * ZBX_MATRIX_EL(coefficients, 0, 0)))
		{
			temp = (0 < ZBX_MATRIX_EL(coefficients, 1, 0) ?
					-ZBX_MATRIX_EL(coefficients, 1, 0) - sqrt(temp) :
					-ZBX_MATRIX_EL(coefficients, 1, 0) + sqrt(temp));
			Re(ZBX_MATRIX_ROW(roots, 0)) = 0.5 * temp / ZBX_MATRIX_EL(coefficients, 2, 0);
			Re(ZBX_MATRIX_ROW(roots, 1)) = 2.0 * ZBX_MATRIX_EL(coefficients, 0, 0) / temp;
			Im(ZBX_MATRIX_ROW(roots, 0)) = Im(ZBX_MATRIX_ROW(roots, 1)) = 0.0;
		}
		else
		{
			Re(ZBX_MATRIX_ROW(roots, 0)) = Re(ZBX_MATRIX_ROW(roots, 1)) =
					-0.5 * ZBX_MATRIX_EL(coefficients, 1, 0) / ZBX_MATRIX_EL(coefficients, 2, 0);
			Im(ZBX_MATRIX_ROW(roots, 0)) = -(Im(ZBX_MATRIX_ROW(roots, 1)) = 0.5 * sqrt(-temp)) /
					ZBX_MATRIX_EL(coefficients, 2, 0);
		}

		return SUCCEED;
	}

	zbx_matrix_struct_alloc(&denominator_multiplicands);
	zbx_matrix_struct_alloc(&updates);

	if (SUCCEED != zbx_matrix_alloc(roots, degree, 2) ||
			SUCCEED != zbx_matrix_alloc(denominator_multiplicands, degree, 2) ||
			SUCCEED != zbx_matrix_alloc(updates, degree, 2))
	{
		res = FAIL;
		goto out;
	}

	/* if n lower coefficients are zeros, zero is a root of multiplicity n */
	for (first_nonzero = 0; 0.0 == ZBX_MATRIX_EL(coefficients, first_nonzero, 0); first_nonzero++)
		Re(ZBX_MATRIX_ROW(roots, first_nonzero)) = Im(ZBX_MATRIX_ROW(roots, first_nonzero)) = 0.0;

	/* compute bounds for the roots */
	upper_bound = lower_bound = 1.0;

	for (i = first_nonzero; i < degree; i++)
	{
		if (upper_bound < fabs(ZBX_MATRIX_EL(coefficients, i, 0) / highest_degree_coefficient))
			upper_bound = fabs(ZBX_MATRIX_EL(coefficients, i, 0) / highest_degree_coefficient);

		if (lower_bound < fabs(ZBX_MATRIX_EL(coefficients, i + 1, 0) /
				ZBX_MATRIX_EL(coefficients, first_nonzero, 0)))
			lower_bound = fabs(ZBX_MATRIX_EL(coefficients, i + 1, 0) /
					ZBX_MATRIX_EL(coefficients, first_nonzero, 0));
	}

	radius = 1.0 / lower_bound;

	/* Weierstrass (Durand-Kerner) method */
	while (ZBX_MAX_ITERATIONS >= ++iteration && !roots_ok)
	{
		if (0 == root_init)
		{
			if (radius <= upper_bound)
			{
				for (i = 0; i < degree - first_nonzero; i++)
				{
					Re(ZBX_MATRIX_ROW(roots, i)) = radius * cos((2.0 * M_PI * (i + 0.25)) /
							(degree - first_nonzero));
					Im(ZBX_MATRIX_ROW(roots, i)) = radius * sin((2.0 * M_PI * (i + 0.25)) /
							(degree - first_nonzero));
				}

				radius *= 2.0;
			}
			else
				root_init = 1;
		}

		roots_ok = 1;
		max_update = 0.0;
		min_distance = HUGE_VAL;

		for (i = first_nonzero; i < degree; i++)
		{
			Re(z) = Re(ZBX_MATRIX_ROW(roots, i));
			Im(z) = Im(ZBX_MATRIX_ROW(roots, i));

			/* subtract from z every one of denominator_multiplicands and multiply them */
			Re(denominator) = highest_degree_coefficient;
			Im(denominator) = 0.0;

			for (j = first_nonzero; j < degree; j++)
			{
				if (j == i)
					continue;

				temp = (ZBX_MATRIX_EL(roots, i, 0) - ZBX_MATRIX_EL(roots, j, 0)) *
						(ZBX_MATRIX_EL(roots, i, 0) - ZBX_MATRIX_EL(roots, j, 0)) +
						(ZBX_MATRIX_EL(roots, i, 1) - ZBX_MATRIX_EL(roots, j, 1)) *
						(ZBX_MATRIX_EL(roots, i, 1) - ZBX_MATRIX_EL(roots, j, 1));
				if (temp < min_distance)
					min_distance = temp;

				Re(ZBX_MATRIX_ROW(denominator_multiplicands, j)) = Re(z) - Re(ZBX_MATRIX_ROW(roots, j));
				Im(ZBX_MATRIX_ROW(denominator_multiplicands, j)) = Im(z) - Im(ZBX_MATRIX_ROW(roots, j));
				ZBX_COMPLEX_MULT(denominator, ZBX_MATRIX_ROW(denominator_multiplicands, j), mult);
			}

			/* calculate complex value of polynomial for z */
			Re(zpower) = 1.0;
			Im(zpower) = 0.0;
			Re(polynomial) = ZBX_MATRIX_EL(coefficients, first_nonzero, 0);
			Im(polynomial) = 0.0;

			for (j = first_nonzero + 1; j <= degree; j++)
			{
				ZBX_COMPLEX_MULT(zpower, z, mult);
				Re(polynomial) += Re(zpower) * ZBX_MATRIX_EL(coefficients, j, 0);
				Im(polynomial) += Im(zpower) * ZBX_MATRIX_EL(coefficients, j, 0);
			}

			/* check how good root approximation is */
			residual = fabs(Re(polynomial)) + fabs(Im(polynomial));
			roots_ok = roots_ok && (ZBX_MATH_EPSILON > residual);

			/* divide polynomial value by denominator */
			if (0.0 != (temp = Re(denominator) * Re(denominator) + Im(denominator) * Im(denominator)))
			{
				Re(ZBX_MATRIX_ROW(updates, i)) = (Re(polynomial) * Re(denominator) +
						Im(polynomial) * Im(denominator)) / temp;
				Im(ZBX_MATRIX_ROW(updates, i)) = (Im(polynomial) * Re(denominator) -
						Re(polynomial) * Im(denominator)) / temp;
			}
			else	/* Denominator is zero if two or more root approximations are equal. */
				/* Since root approximations are initially different their equality means that they */
				/* converged to a multiple root (hopefully) and no updates are required in this case. */
			{
				Re(ZBX_MATRIX_ROW(updates, i)) = Im(ZBX_MATRIX_ROW(updates, i)) = 0.0;
			}

			temp = ZBX_MATRIX_EL(updates, i, 0) * ZBX_MATRIX_EL(updates, i, 0) +
					ZBX_MATRIX_EL(updates, i, 1) * ZBX_MATRIX_EL(updates, i, 1);

			if (temp > max_update)
				max_update = temp;
		}

		if (max_update > radius * radius && 0 == root_init)
			continue;
		else
			root_init = 1;

		for (i = first_nonzero; i < degree; i++)
		{
			Re(ZBX_MATRIX_ROW(roots, i)) -= Re(ZBX_MATRIX_ROW(updates, i));
			Im(ZBX_MATRIX_ROW(roots, i)) -= Im(ZBX_MATRIX_ROW(updates, i));
		}
	}

	if (0 == roots_ok)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "polynomial root finding problem is ill-defined");
		res = FAIL;
	}
	else
		res = SUCCEED;
out:
	zbx_matrix_free(denominator_multiplicands);
	zbx_matrix_free(updates);
	return res;
error:
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
#undef ZBX_MAX_ITERATIONS
#undef Re
#undef Im
}

static int	zbx_polynomial_minmax(double now, double time, zbx_mode_t mode, zbx_matrix_t *coefficients,
		double *result)
{
	zbx_matrix_t	*derivative = NULL, *derivative_roots = NULL;
	double		min, max, tmp;
	int		i, res;

	if (!ZBX_VALID_MATRIX(coefficients))
		goto error;

	zbx_matrix_struct_alloc(&derivative);
	zbx_matrix_struct_alloc(&derivative_roots);

	if (SUCCEED != (res = zbx_derive_polynomial(coefficients, derivative)))
		goto out;

	if (SUCCEED != (res = zbx_polynomial_roots(derivative, derivative_roots)))
		goto out;

	/* Choose min and max among now, now + time and derivative roots in between (these are potential local      */
	/* extrema). We ignore imaginary part of roots. This means that more calculations will be made, but result  */
	/* will not be affected and we won't need a boundary on minimal imaginary part that differs from zero.      */

	min = zbx_polynomial_value(now, coefficients);
	tmp = zbx_polynomial_value(now + time, coefficients);

	if (tmp < min)
	{
		max = min;
		min = tmp;
	}
	else
		max = tmp;

	for (i = 0; i < derivative_roots->rows; i++)
	{
		tmp = ZBX_MATRIX_EL(derivative_roots, i, 0);

		if (tmp < now || tmp > now + time)
			continue;

		tmp = zbx_polynomial_value(tmp, coefficients);

		if (tmp < min)
			min = tmp;
		else if (tmp > max)
			max = tmp;
	}

	if (MODE_MAX == mode)
		*result = max;
	else if (MODE_MIN == mode)
		*result = min;
	else if (MODE_DELTA == mode)
		*result = max - min;
	else
		THIS_SHOULD_NEVER_HAPPEN;

out:
	zbx_matrix_free(derivative);
	zbx_matrix_free(derivative_roots);
	return res;
error:
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
}

static int	zbx_polynomial_timeleft(double now, double threshold, zbx_matrix_t *coefficients, double *result)
{
	zbx_matrix_t	*shifted_coefficients = NULL, *roots = NULL;
	double		tmp;
	int		res, no_root = 1;

	if (!ZBX_VALID_MATRIX(coefficients))
		goto error;

	zbx_matrix_struct_alloc(&shifted_coefficients);
	zbx_matrix_struct_alloc(&roots);

	if (SUCCEED != (res = zbx_matrix_copy(shifted_coefficients, coefficients)))
		goto out;

	ZBX_MATRIX_EL(shifted_coefficients, 0, 0) -= threshold;

	if (SUCCEED != (res = zbx_polynomial_roots(shifted_coefficients, roots)))
		goto out;

	/* choose the closest root right from now or set result to -1 otherwise */
	/* if zbx_polynomial_value(tmp) is not close enough to zero it must be a complex root and must be skipped */

	for (int i = 0; i < roots->rows; i++)
	{
		tmp = ZBX_MATRIX_EL(roots, i, 0);

		if (no_root)
		{
			if (tmp > now && ZBX_MATH_EPSILON > fabs(zbx_polynomial_value(tmp, shifted_coefficients)))
			{
				no_root = 0;
				*result = tmp;
			}
		}
		else if (now < tmp && tmp < *result &&
				ZBX_MATH_EPSILON > fabs(zbx_polynomial_value(tmp, shifted_coefficients)))
		{
			*result = tmp;
		}
	}

	if (no_root)
		*result = DBL_MAX;
	else
		*result -= now;

out:
	zbx_matrix_free(shifted_coefficients);
	zbx_matrix_free(roots);
	return res;
error:
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
}

static int	zbx_calculate_value(double t, zbx_matrix_t *coefficients, zbx_fit_t fit, double *value)
{
	if (!ZBX_VALID_MATRIX(coefficients))
		goto error;

	if (FIT_LINEAR == fit)
		*value = ZBX_MATRIX_EL(coefficients, 0, 0) + ZBX_MATRIX_EL(coefficients, 1, 0) * t;
	else if (FIT_POLYNOMIAL == fit)
		*value = zbx_polynomial_value(t, coefficients);
	else if (FIT_EXPONENTIAL == fit)
		*value = exp(ZBX_MATRIX_EL(coefficients, 0, 0) + ZBX_MATRIX_EL(coefficients, 1, 0) * t);
	else if (FIT_LOGARITHMIC == fit)
		*value = ZBX_MATRIX_EL(coefficients, 0, 0) + ZBX_MATRIX_EL(coefficients, 1, 0) * log(t);
	else if (FIT_POWER == fit)
		*value = exp(ZBX_MATRIX_EL(coefficients, 0, 0) + ZBX_MATRIX_EL(coefficients, 1, 0) * log(t));
	else
		goto error;

	return SUCCEED;
error:
	THIS_SHOULD_NEVER_HAPPEN;

	return FAIL;
}

int	zbx_fit_code(char *fit_str, zbx_fit_t *fit, unsigned *k, char **error)
{
	if ('\0' == *fit_str || 0 == strcmp(fit_str, "linear"))
	{
		*fit = FIT_LINEAR;
		*k = 0;
	}
	else if (0 == strncmp(fit_str, "polynomial", strlen("polynomial")))
	{
		*fit = FIT_POLYNOMIAL;

		if (SUCCEED != zbx_is_uint_range(fit_str + strlen("polynomial"), k, 1, 6))
		{
			*error = zbx_strdup(*error, "polynomial degree is invalid");
			return FAIL;
		}
	}
	else if (0 == strcmp(fit_str, "exponential"))
	{
		*fit = FIT_EXPONENTIAL;
		*k = 0;
	}
	else if (0 == strcmp(fit_str, "logarithmic"))
	{
		*fit = FIT_LOGARITHMIC;
		*k = 0;
	}
	else if (0 == strcmp(fit_str, "power"))
	{
		*fit = FIT_POWER;
		*k = 0;
	}
	else
	{
		*error = zbx_strdup(*error, "invalid 'fit' parameter");
		return FAIL;
	}

	return SUCCEED;
}

int	zbx_mode_code(char *mode_str, zbx_mode_t *mode, char **error)
{
	if ('\0' == *mode_str || 0 == strcmp(mode_str, "value"))
	{
		*mode = MODE_VALUE;
	}
	else if (0 == strcmp(mode_str, "max"))
	{
		*mode = MODE_MAX;
	}
	else if (0 == strcmp(mode_str, "min"))
	{
		*mode = MODE_MIN;
	}
	else if (0 == strcmp(mode_str, "delta"))
	{
		*mode = MODE_DELTA;
	}
	else if (0 == strcmp(mode_str, "avg"))
	{
		*mode = MODE_AVG;
	}
	else
	{
		*error = zbx_strdup(*error, "invalid 'mode' parameter");
		return FAIL;
	}

	return SUCCEED;
}

static void	zbx_log_expression(double now, zbx_fit_t fit, int k, zbx_matrix_t *coeffs)
{
	/* x is item value, t is time in seconds counted from now */
	if (FIT_LINEAR == fit)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "fitted expression is: x = (" ZBX_FS_DBL ") + (" ZBX_FS_DBL ") * ("
				ZBX_FS_DBL " + t)", ZBX_MATRIX_EL(coeffs, 0, 0), ZBX_MATRIX_EL(coeffs, 1, 0), now);
	}
	else if (FIT_POLYNOMIAL == fit)
	{
		char	*polynomial = NULL;
		size_t	alloc, offset;

		while (0 <= k)
		{
			zbx_snprintf_alloc(&polynomial, &alloc, &offset, "(" ZBX_FS_DBL ") * (" ZBX_FS_DBL " + t) ^ %d",
					ZBX_MATRIX_EL(coeffs, k, 0), now, k);

			if (0 < k--)
				zbx_snprintf_alloc(&polynomial, &alloc, &offset, " + ");
		}

		zabbix_log(LOG_LEVEL_DEBUG, "fitted expression is: x = %s", polynomial);

		zbx_free(polynomial);
	}
	else if (FIT_EXPONENTIAL == fit)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "fitted expression is: x = (" ZBX_FS_DBL ") * exp( (" ZBX_FS_DBL ") * ("
				ZBX_FS_DBL " + t) )", exp(ZBX_MATRIX_EL(coeffs, 0, 0)), ZBX_MATRIX_EL(coeffs, 1, 0),
				now);
	}
	else if (FIT_LOGARITHMIC == fit)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "fitted expression is: x = (" ZBX_FS_DBL ") + (" ZBX_FS_DBL ") * log("
				ZBX_FS_DBL " + t)", ZBX_MATRIX_EL(coeffs, 0, 0), ZBX_MATRIX_EL(coeffs, 1, 0), now);
	}
	else if (FIT_POWER == fit)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "fitted expression is: x = (" ZBX_FS_DBL ") * (" ZBX_FS_DBL " + t) ^ ("
				ZBX_FS_DBL ")", exp(ZBX_MATRIX_EL(coeffs, 0, 0)), now, ZBX_MATRIX_EL(coeffs, 1, 0));
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;
}

double	zbx_forecast(double *t, double *x, int n, double now, double time, zbx_fit_t fit, unsigned k, zbx_mode_t mode)
{
	zbx_matrix_t	*coefficients = NULL;
	double		left, right, result;
	int		res;

	if (1 == n)
	{
		if (MODE_VALUE == mode || MODE_MAX == mode || MODE_MIN == mode || MODE_AVG == mode)
			return x[0];

		if (MODE_DELTA == mode)
			return 0.0;

		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_MATH_ERROR;
	}
	else if (FIT_POLYNOMIAL == fit)
	{
		if ((unsigned)n <= k)
			return ZBX_MATH_ERROR;
	}

	zbx_matrix_struct_alloc(&coefficients);

	if (SUCCEED != (res = zbx_regression(t, x, n, fit, k, coefficients)))
		goto out;

	zbx_log_expression(now, fit, (int)k, coefficients);

	if (MODE_VALUE == mode)
	{
		res = zbx_calculate_value(now + time, coefficients, fit, &result);
		goto out;
	}

	if (0.0 == time)
	{
		if (MODE_MAX == mode || MODE_MIN == mode || MODE_AVG == mode)
		{
			res = zbx_calculate_value(now + time, coefficients, fit, &result);
		}
		else if (MODE_DELTA == mode)
		{
			result = 0.0;
			res = SUCCEED;
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			res = FAIL;
		}

		goto out;
	}

	if (FIT_LINEAR == fit || FIT_EXPONENTIAL == fit || FIT_LOGARITHMIC == fit || FIT_POWER == fit)
	{
		/* fit is monotone, therefore maximum and minimum are either at now or at now + time */
		if (SUCCEED != zbx_calculate_value(now, coefficients, fit, &left) ||
				SUCCEED != zbx_calculate_value(now + time, coefficients, fit, &right))
		{
			res = FAIL;
			goto out;
		}

		if (MODE_MAX == mode)
		{
			result = (left > right ? left : right);
		}
		else if (MODE_MIN == mode)
		{
			result = (left < right ? left : right);
		}
		else if (MODE_DELTA == mode)
		{
			result = (left > right ? left - right : right - left);
		}
		else if (MODE_AVG == mode)
		{
			if (FIT_LINEAR == fit)
			{
				result = 0.5 * (left + right);
			}
			else if (FIT_EXPONENTIAL == fit)
			{
				result = (right - left) / time / ZBX_MATRIX_EL(coefficients, 1, 0);
			}
			else if (FIT_LOGARITHMIC == fit)
			{
				result = right + ZBX_MATRIX_EL(coefficients, 1, 0) *
						(log(1.0 + time / now) * now / time - 1.0);
			}
			else if (FIT_POWER == fit)
			{
				if (-1.0 != ZBX_MATRIX_EL(coefficients, 1, 0))
					result = (right * (now + time) - left * now) / time /
							(ZBX_MATRIX_EL(coefficients, 1, 0) + 1.0);
				else
					result = exp(ZBX_MATRIX_EL(coefficients, 0, 0)) * log(1.0 + time / now) / time;
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				res = FAIL;
				goto out;
			}
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			res = FAIL;
			goto out;
		}

		res = SUCCEED;
	}
	else if (FIT_POLYNOMIAL == fit)
	{
		if (MODE_MAX == mode || MODE_MIN == mode || MODE_DELTA == mode)
		{
			res = zbx_polynomial_minmax(now, time, mode, coefficients, &result);
		}
		else if (MODE_AVG == mode)
		{
			result = (zbx_polynomial_antiderivative(now + time, coefficients) -
					zbx_polynomial_antiderivative(now, coefficients)) / time;
			res = SUCCEED;
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			res = FAIL;
		}
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		res = FAIL;
	}
out:
	zbx_matrix_free(coefficients);

	if (SUCCEED != res)
	{
		result = ZBX_MATH_ERROR;
	}
	else if (ZBX_IS_NAN(result))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "numerical error");
		result = ZBX_MATH_ERROR;
	}
	/* these checks are needed in case of +/- infinity */
	else if (DBL_MAX < result)
	{
		result = DBL_MAX;
	}
	else if (-DBL_MAX > result)
	{
		result = -DBL_MAX;
	}

	return result;
}

double	zbx_timeleft(double *t, double *x, int n, double now, double threshold, zbx_fit_t fit, unsigned k)
{
	zbx_matrix_t	*coefficients = NULL;
	double		current, result = -1.0;
	int		res;

	if (1 == n)
		return (x[0] == threshold ? 0.0 : DBL_MAX);

	zbx_matrix_struct_alloc(&coefficients);

	if (SUCCEED != (res = zbx_regression(t, x, n, fit, k, coefficients)))
		goto out;

	zbx_log_expression(now, fit, (int)k, coefficients);

	if (SUCCEED != (res = zbx_calculate_value(now, coefficients, fit, &current)))
	{
		goto out;
	}
	else if (current == threshold)
	{
		result = 0.0;
		goto out;
	}

	if (FIT_LINEAR == fit)
	{
		result = (threshold - ZBX_MATRIX_EL(coefficients, 0, 0)) / ZBX_MATRIX_EL(coefficients, 1, 0) - now;
	}
	else if (FIT_POLYNOMIAL == fit)
	{
		res = zbx_polynomial_timeleft(now, threshold, coefficients, &result);
	}
	else if (FIT_EXPONENTIAL == fit)
	{
		result = (log(threshold) - ZBX_MATRIX_EL(coefficients, 0, 0)) / ZBX_MATRIX_EL(coefficients, 1, 0) - now;
	}
	else if (FIT_LOGARITHMIC == fit)
	{
		result = exp((threshold - ZBX_MATRIX_EL(coefficients, 0, 0)) / ZBX_MATRIX_EL(coefficients, 1, 0)) - now;
	}
	else if (FIT_POWER == fit)
	{
		result = exp((log(threshold) - ZBX_MATRIX_EL(coefficients, 0, 0)) / ZBX_MATRIX_EL(coefficients, 1, 0))
				- now;
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		res = FAIL;
	}
out:
	if (SUCCEED != res)
	{
		result = ZBX_MATH_ERROR;
	}
	else if (ZBX_IS_NAN(result))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "numerical error");
		result = ZBX_MATH_ERROR;
	}
	else if (0.0 > result || DBL_MAX < result)
	{
		result = DBL_MAX;
	}

	zbx_matrix_free(coefficients);

	return result;
}
