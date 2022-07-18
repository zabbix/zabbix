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

#include "zbxalgo.h"
#include "algodefs.h"

#include "common.h"

typedef unsigned char uchar;

/*
 * modified FNV hash function (see http://www.isthe.com/chongo/tech/comp/fnv/)
 */
zbx_hash_t	zbx_hash_modfnv(const void *data, size_t len, zbx_hash_t seed)
{
	const uchar	*p = (const uchar *)data;

	zbx_hash_t	hash;

	hash = 2166136261u ^ seed;

	while (len-- >= 1)
	{
		hash = (hash ^ *(p++)) * 16777619u;
	}

	hash += hash << 13;
	hash ^= hash >> 7;
	hash += hash << 3;
	hash ^= hash >> 17;
	hash += hash << 5;

	return hash;
}

/*
 * see http://xoshiro.di.unimi.it/splitmix64.c
 */
zbx_hash_t	zbx_hash_splittable64(const void *data)
{
	zbx_uint64_t	value = *(const zbx_uint64_t *)data;

	value ^= value >> 30;
	value *= __UINT64_C(0xbf58476d1ce4e5b9);
	value ^= value >> 27;
	value *= __UINT64_C(0x94d049bb133111eb);
	value ^= value >> 31;

	return (zbx_hash_t)value ^ (value >> 32);
}

/* default hash functions */

zbx_hash_t	zbx_default_ptr_hash_func(const void *data)
{
	return ZBX_DEFAULT_PTR_HASH_ALGO(data, ZBX_PTR_SIZE, ZBX_DEFAULT_HASH_SEED);
}

zbx_hash_t	zbx_default_string_hash_func(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_ALGO(data, strlen((const char *)data), ZBX_DEFAULT_HASH_SEED);
}

zbx_hash_t	zbx_default_uint64_pair_hash_func(const void *data)
{
	const zbx_uint64_pair_t	*pair = (const zbx_uint64_pair_t *)data;

	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&pair->first);
	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&pair->second, sizeof(pair->second), hash);

	return hash;
}

/* default comparison functions */

int	zbx_default_int_compare_func(const void *d1, const void *d2)
{
	const int	*i1 = (const int *)d1;
	const int	*i2 = (const int *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(*i1, *i2);

	return 0;
}

int	zbx_default_uint64_compare_func(const void *d1, const void *d2)
{
	const zbx_uint64_t	*i1 = (const zbx_uint64_t *)d1;
	const zbx_uint64_t	*i2 = (const zbx_uint64_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(*i1, *i2);

	return 0;
}

int	zbx_default_uint64_ptr_compare_func(const void *d1, const void *d2)
{
	const zbx_uint64_t	*p1 = *(const zbx_uint64_t * const *)d1;
	const zbx_uint64_t	*p2 = *(const zbx_uint64_t * const *)d2;

	return zbx_default_uint64_compare_func(p1, p2);
}

int	zbx_default_str_compare_func(const void *d1, const void *d2)
{
	return strcmp(*(const char * const *)d1, *(const char * const *)d2);
}

int	zbx_default_str_ptr_compare_func(const void *d1, const void *d2)
{
	return strcmp(**(const char * const * const *)d1, **(const char * const * const *)d2);
}

int	zbx_natural_str_compare_func(const void *d1, const void *d2)
{
	return zbx_strcmp_natural(*(const char * const *)d1, *(const char * const *)d2);
}

int	zbx_default_ptr_compare_func(const void *d1, const void *d2)
{
	const void	*p1 = *(const void * const *)d1;
	const void	*p2 = *(const void * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1, p2);

	return 0;
}

int	zbx_default_uint64_pair_compare_func(const void *d1, const void *d2)
{
	const zbx_uint64_pair_t	*p1 = (const zbx_uint64_pair_t *)d1;
	const zbx_uint64_pair_t	*p2 = (const zbx_uint64_pair_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(p1->first, p2->first);
	ZBX_RETURN_IF_NOT_EQUAL(p1->second, p2->second);

	return 0;
}

int	zbx_default_dbl_compare_func(const void *d1, const void *d2)
{
	const double	*p1 = (const double *)d1;
	const double	*p2 = (const double *)d2;

	ZBX_RETURN_IF_DBL_NOT_EQUAL(*p1, *p2);

	return 0;
}

/* default memory management functions */

void	*zbx_default_mem_malloc_func(void *old, size_t size)
{
	return zbx_malloc(old, size);
}

void	*zbx_default_mem_realloc_func(void *old, size_t size)
{
	return zbx_realloc(old, size);
}

void	zbx_default_mem_free_func(void *ptr)
{
	zbx_free(ptr);
}

/* numeric functions */

static int	is_prime(int n)
{
	int i;

	if (n <= 1)
		return 0;
	if (n == 2)
		return 1;
	if (n % 2 == 0)
		return 0;

	for (i = 3; i * i <= n; i+=2)
		if (n % i == 0)
			return 0;

	return 1;
}

int	next_prime(int n)
{
	while (!is_prime(n))
		n++;

	return n;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate integer part of square root of a 32 bit integer value   *
 *                                                                            *
 * Parameters: value     - [IN] the value to calculate square root for        *
 *                                                                            *
 * Return value: the integer part of square root                              *
 *                                                                            *
 * Comments: Uses basic digit by digit square root calculation algorithm with *
 *           binary base.                                                     *
 *                                                                            *
 ******************************************************************************/
unsigned int	zbx_isqrt32(unsigned int value)
{
	unsigned int	i, remainder = 0, result = 0, p;

	for (i = 0; i < 16; i++)
	{
		result <<= 1;
		remainder = (remainder << 2) + (value >> 30);
		value <<= 2;

		p = (result << 1) | 1;
		if (p <= remainder)
		{
			remainder -= p;
			result |= 1;
		}
	}

	return result;
}
