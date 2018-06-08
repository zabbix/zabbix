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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "zbxalgo.h"

#define UINT64_BIT_COUNT	(sizeof(zbx_uint64_t) << 3)
#define UINT32_BIT_COUNT	(UINT64_BIT_COUNT >> 1)
#define UINT32_BIT_MASK		(~((~__UINT64_C(0)) << UINT32_BIT_COUNT))

/******************************************************************************
 *                                                                            *
 * Function: udec128_128                                                      *
 *                                                                            *
 * Purpose: Decrement of 128 bit unsigned integer by the specified value.     *
 *                                                                            *
 * Parameters: base   - [IN,OUT] the integer to decrement.                    *
 *             value  - [IN] the value to decrement by.                       *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static void	udec128_128(zbx_uint128_t *base, const zbx_uint128_t *value)
{
	zbx_uint64_t	lo = base->lo;

	base->lo -= value->lo;
	if (lo < base->lo)
		base->hi--;
	base->hi -= value->hi;
}

/******************************************************************************
 *                                                                            *
 * Function: ushiftr128                                                       *
 *                                                                            *
 * Purpose: Logical right shift of 128 bit unsigned integer.                  *
 *                                                                            *
 * Parameters: base  - [IN,OUT] the inital value and result                   *
 *             bits  - [IN] the number of bits to shift for.                  *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static void	ushiftr128(zbx_uint128_t *base, unsigned int bits)
{
	if (0 == bits)
		return;

	if (UINT64_BIT_COUNT <= bits)
	{
		bits -= UINT64_BIT_COUNT;
		base->lo = base->hi >> bits;
		base->hi = 0;
		return;
	}

	base->lo >>= bits;
	base->lo |= (base->hi << (UINT64_BIT_COUNT - bits));
	base->hi >>= bits;
}


/******************************************************************************
 *                                                                            *
 * Function: ushiftl128                                                       *
 *                                                                            *
 * Purpose: Logical left shift of 128 bit unsigned integer.                   *
 *                                                                            *
 * Parameters: base  - [IN,OUT] the inital value and result                   *
 *             bits  - [IN] the number of bits to shift for.                  *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static void	ushiftl128(zbx_uint128_t *base, unsigned int bits)
{
	if (0 == bits)
		return;

	if (UINT64_BIT_COUNT <= bits)
	{
		bits -= UINT64_BIT_COUNT;
		base->hi = base->lo << bits;
		base->lo = 0;
		return;
	}

	base->hi <<= bits;
	base->hi |= (base->lo >> (UINT64_BIT_COUNT - bits));
	base->lo <<= bits;
}

/******************************************************************************
 *                                                                            *
 * Function: ucmp128_128                                                      *
 *                                                                            *
 * Purpose: Comparison of two 128 bit unsigned integer values.                *
 *                                                                            *
 * Parameters: value1  - [IN] the first value to compare.                     *
 *             value2  - [IN] the second value to compare.                    *
 *                                                                            *
 * Return value: -1  - value1 < value2                                        *
 *                0  - value1 = value2                                        *
 *                1  - value1 > value2                                        *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	ucmp128_128(const zbx_uint128_t *value1, const zbx_uint128_t *value2)
{
	if (value1->hi != value2->hi)
		return value1->hi < value2->hi ? -1 : 1;
	if (value1->lo == value2->lo)
		return 0;
	return value1->lo < value2->lo ? -1 : 1;
}

/******************************************************************************
 *                                                                            *
 * Function: umul64_32_shift                                                  *
 *                                                                            *
 * Purpose: Multiplication of 64 bit unsigned integer with 32 bit unsigned    *
 *          integer value, shifted left by specified number of bits           *
 *                                                                            *
 * Parameters: base   - [OUT] the value to add result to                      *
 *             value  - [IN] the value to multiply.                           *
 *             factor - [IN] the factor to multiply by.                       *
 *             shift  - [IN] the number of bits to shift the result by before *
 *                      adding it to the base value.                          *
 *                                                                            *
 * Comments: This is a helper function for umul64_64 implementation.          *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static void	umul64_32_shift(zbx_uint128_t *base, zbx_uint64_t value, zbx_uint64_t factor, int shift)
{
	zbx_uint128_t	buffer;

	uset128(&buffer, 0, (value & UINT32_BIT_MASK) * factor);
	ushiftl128(&buffer, shift);
	uinc128_128(base, &buffer);

	uset128(&buffer, 0, (value >> UINT32_BIT_COUNT) * factor);
	ushiftl128(&buffer, UINT32_BIT_COUNT + shift);
	uinc128_128(base, &buffer);
}

/******************************************************************************
 *                                                                            *
 * Function: uinc128_64                                                       *
 *                                                                            *
 * Purpose: Increment of 128 bit unsigned integer by the specified 64 bit     *
 *          value.                                                            *
 *                                                                            *
 * Parameters: base   - [IN,OUT] the integer to increment.                    *
 *             value  - [IN] the value to increment by.                       *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
void	uinc128_64(zbx_uint128_t *base, zbx_uint64_t value)
{
	zbx_uint64_t	low = base->lo;

	base->lo += value;
	/* handle wraparound */
	if (low > base->lo)
		base->hi++;
}

/******************************************************************************
 *                                                                            *
 * Function: uinc128_128                                                      *
 *                                                                            *
 * Purpose: Increment of 128 bit unsigned integer by the specified 128 bit    *
 *          value                                                             *
 *                                                                            *
 * Parameters: base   - [IN,OUT] the integer to increment.                    *
 *             value  - [IN] the value to increment by.                       *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
void	uinc128_128(zbx_uint128_t *base, const zbx_uint128_t *value)
{
	zbx_uint64_t	low = base->lo;

	base->lo += value->lo;
	/* handle wraparound */
	if (low > base->lo)
		base->hi++;
	base->hi += value->hi;
}

/******************************************************************************
 *                                                                            *
 * Function: umul64_64                                                        *
 *                                                                            *
 * Purpose: Multiplication of two 64 bit unsigned integer values.             *
 *                                                                            *
 * Parameters: result - [OUT] the resulting 128 bit unsigned integer value    *
 *             value  - [IN] the value to multiply.                           *
 *             factor - [IN] the factor to multiply by.                       *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
void	umul64_64(zbx_uint128_t *result, zbx_uint64_t value, zbx_uint64_t factor)
{
	uset128(result, 0, 0);
	/* multiply the value with lower double word of factor and add the result */
	umul64_32_shift(result, value, factor & UINT32_BIT_MASK, 0);
	/* multiply the value with higher double word of factor and add the result */
	umul64_32_shift(result, value, factor >> UINT32_BIT_COUNT, UINT32_BIT_COUNT);
}

/******************************************************************************
 *                                                                            *
 * Function: udiv128_64                                                       *
 *                                                                            *
 * Purpose: Division of 128 bit unsigned integer by a 64 bit unsigned integer *
 *          value.                                                            *
 *                                                                            *
 * Parameters: result    - [OUT] the resulting quotient value.                *
 *             dividend  - [IN] the dividend.                                 *
 *             value     - [IN] the divisor.                                  *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
void	udiv128_64(zbx_uint128_t *result, const zbx_uint128_t *dividend, zbx_uint64_t value)
{
	zbx_uint128_t	reminder, divisor;
	zbx_uint64_t	result_mask = __UINT64_C(1) << (UINT64_BIT_COUNT - 1);

	/* first handle the simple 64bit/64bit case */
	if (0 == dividend->hi)
	{
		result->hi = 0;
		result->lo = dividend->lo / value;
		return;
	}

	/* divide the high qword and store the result in result, reminder in reminder */
	reminder = *dividend;
	if (dividend->hi >= value)
	{
		result->hi = dividend->hi / value;
		reminder.hi -= result->hi * value;
	}
	else
		result->hi = 0;
	result->lo = 0;

	/* shift divisor left by 64 bits - simply assign it to the high qword */
	uset128(&divisor, value, 0);

	/* Reminder is always less than divisor shifted right by 64 bits (because of the */
	/* high qword division above). So pre-shift the divisor to right by one.         */
	ushiftr128(&divisor, 1);

	/* do manual division while reminder is larger than 64 bits */
	while (reminder.hi)
	{
		while (ucmp128_128(&reminder, &divisor) < 0)
		{
			ushiftr128(&divisor, 1);
			result_mask >>= 1;
		}

		udec128_128(&reminder, &divisor);
		result->lo |= result_mask;

	}
	/* reminder is less than 64 bits, proceed with 64bit division */
	result->lo |= reminder.lo / value;
}
