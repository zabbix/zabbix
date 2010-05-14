/*
** ZABBIX
** Copyright (C) 2000-2010 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"

#include "zbxalgo.h"

/*
 * Bob Jenkins hash function (see http://burtleburtle.net/bob/hash/evahash.html)
 */
zbx_hash_t	zbx_string_hash_lookup2(const void *data)
{
	const char	*p, *str = (const char *)data;

	size_t		len;
	zbx_hash_t	a, b, c;

#define	mix(a, b, c)						\
{								\
	a = a - b;	a = a - c;	a = a ^ (c >> 13);	\
	b = b - c;	b = b - a;	b = b ^ (a << 8);	\
	c = c - a;	c = c - b;	c = c ^ (b >> 13);	\
	a = a - b;	a = a - c;	a = a ^ (c >> 12);	\
	b = b - c;	b = b - a;	b = b ^ (a << 16);	\
	c = c - a;	c = c - b;	c = c ^ (b >> 5);	\
	a = a - b;	a = a - c;	a = a ^ (c >> 3);	\
	b = b - c;	b = b - a;	b = b ^ (a << 10);	\
	c = c - a;	c = c - b;	c = c ^ (b >> 15);	\
}

	len = strlen(str);

	a = b = 0x9e3779b9u;
	c = 0;

	p = str;

	while (len >= 12)
	{
		a = a + (p[0] + ((zbx_hash_t)p[1] << 8) + ((zbx_hash_t)p[2]  << 16) + ((zbx_hash_t)p[3]  << 24));
		b = b + (p[4] + ((zbx_hash_t)p[5] << 8) + ((zbx_hash_t)p[6]  << 16) + ((zbx_hash_t)p[7]  << 24));
		c = c + (p[8] + ((zbx_hash_t)p[9] << 8) + ((zbx_hash_t)p[10] << 16) + ((zbx_hash_t)p[11] << 24));

		mix(a, b, c);

		p += 12;
		len -= 12;
	}

	c = c + len;

	switch (len)
	{
		case 11:	c = c + ((zbx_hash_t)p[10] << 24);
		case 10:	c = c + ((zbx_hash_t)p[9] << 16);
		case 9:		c = c + ((zbx_hash_t)p[8] << 8);
		case 8:		b = b + ((zbx_hash_t)p[7] << 24);
		case 7:		b = b + ((zbx_hash_t)p[6] << 16);
		case 6:		b = b + ((zbx_hash_t)p[5] << 8);
		case 5:		b = b + p[4];
		case 4:		a = a + ((zbx_hash_t)p[3] << 24);
		case 3:		a = a + ((zbx_hash_t)p[2] << 16);
		case 2:		a = a + ((zbx_hash_t)p[1] << 8);
		case 1:		a = a + p[0];
	}

	mix(a, b, c);

	return c;
}

/*
 * modified FNV hash function (see http://home.comcast.net/~bretm/hash/6.html)
 */
zbx_hash_t	zbx_string_hash_modfnv(const void *data)
{
	const char	*p, *str = (const char *)data;

	zbx_hash_t	hash = 2166136261u;
	
	for (p = str; *p != '\0'; p++)
		hash = (hash ^ *p) * 16777619u;

	hash += hash << 13;
	hash ^= hash >> 7;
	hash += hash << 3;
	hash ^= hash >> 17;
	hash += hash << 5;

	return hash;
}

/*
 * Murmur (see http://sites.google.com/site/murmurhash/)
 */
zbx_hash_t	zbx_string_hash_murmur2(const void *data)
{
	const char	*p, *str = (const char *)data;

	int		len;
	zbx_hash_t	hash;

	const uint32_t	m = 0x5bd1e995u;
	const uint32_t	r = 24;

	len = strlen(str);

	hash = 0 /* seed */ ^ len;

	p = str;

	while (len >= 4)
	{
		uint32_t	k;

		k = p[0];
		k |= p[1] << 8;
		k |= p[2] << 16;
		k |= p[3] << 24;

		k *= m;
		k ^= k >> r;
		k *= m;

		hash *= m;
		hash ^= k;

		p += 4;
		len -= 4;
	}

	switch (len)
	{
		case 3:	hash ^= p[2] << 16;
		case 2: hash ^= p[1] << 8;
		case 1: hash ^= p[0];
			hash *= m;
	}

	hash ^= hash >> 13;
	hash *= m;
	hash ^= hash >> 15;

	return hash;
}

/*
 * sdbm (see http://www.cse.yorku.ca/~oz/hash.html)
 */
zbx_hash_t	zbx_string_hash_sdbm(const void *data)
{
	const char	*p, *str = (const char *)data;

	zbx_hash_t	hash = 0;

	p = str;

#if	1

	while (*p != '\0')
	{
		/* hash = *(p++) + hash * 65599; */

		hash = *(p++) + (hash << 6) + (hash << 16) - hash;
	}

#else	/* Duff's device */

#define	HASH_STEP	hash = *(p++) + (hash << 6) + (hash << 16) - hash

	switch (strlen(str) & 7)
	{
			do
			{
				HASH_STEP;
		case 7:		HASH_STEP;
		case 6:		HASH_STEP;
		case 5:		HASH_STEP;
		case 4:		HASH_STEP;
		case 3:		HASH_STEP;
		case 2:		HASH_STEP;
		case 1:		HASH_STEP;
		case 0:		;
			}
			while (*p != '\0');
	}

#endif

	return hash;
}

/*
 * djb2 (see http://www.cse.yorku.ca/~oz/hash.html)
 */
zbx_hash_t	zbx_string_hash_djb2(const void *data)
{
	const char	*p, *str = (const char *)data;

	zbx_hash_t	hash = 5381u;

	for (p = str; *p != '\0'; p++)
	{
		/* hash = hash * 33 + *p; */

		hash = ((hash << 5) + hash) + *p;
	}

	return hash;
}

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
