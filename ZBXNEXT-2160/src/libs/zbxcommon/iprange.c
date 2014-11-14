/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

/******************************************************************************
 *                                                                            *
 * Function: iprange_apply_mask                                               *
 *                                                                            *
 * Purpose: applies a bit mask to the parsed v4 or v6 IP range                *
 *                                                                            *
 * Parameters: range      - [IN] the IP range array                           *
 *             bits       - [IN] the number of bits in IP mask                *
 *             groups     - [IN] the number of groups in IP address (4 or 8)  *
 *             group_bits - [IN] the number of bits per IP address group      *
 *                               (8 or 16)                                    *
 *                                                                            *
 ******************************************************************************/
static void	iprange_apply_mask(zbx_range_t *range, unsigned int bits, unsigned int groups, unsigned int group_bits)
{
	int	i;

	bits = (groups * group_bits) - bits;

	for (i = groups - 1; 0 < bits && 0 <= i; bits -= group_bits, i--)
	{
		unsigned int	mask_empty, mask_fill, mask_bits = bits;

		if (mask_bits > group_bits)
			mask_bits = group_bits;

		mask_empty = 0xffffffff << mask_bits;
		mask_fill = 0xffffffff >> (32 - mask_bits);

		range[i].from &= mask_empty;
		range[i].to |= mask_fill;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: iprangev4_parse                                                  *
 *                                                                            *
 * Purpose: parse IPv4 address into range array                               *
 *                                                                            *
 * Parameters: address - [IN] the IP address                                  *
 *             range   - [OUT] the IP range array                             *
 *                                                                            *
 * Return value: SUCCEED - the IP range was succesfully parsed                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	iprangev4_parse(const char *address, zbx_range_t *range)
{
	int		index, len, bits = -1;
	const char	*ptr = address, *dash, *end;

	if (NULL != (end = strchr(address, '/')))
	{
		if (FAIL == is_uint32(end + 1, &bits))
			return FAIL;
	}
	else
		end = address + strlen(address);

	/* iterate through address numbers (bit groups) */
	for (index = 0; ptr < end && index <= 4; address = ptr + 1)
	{
		if (NULL == (ptr = strchr(address, '.')))
			ptr = end;

		if (NULL != (dash = strchr(address, '-')))
		{
			/* having range and mask together is not supported */
			if (-1 != bits)
				return FAIL;

			/* check if the range specification is used by the current group */
			if (dash > ptr)
				dash = NULL;
		}

		len = (NULL == dash ? ptr : dash) - address;

		/* extract the range start value */
		if (FAIL == is_uint_n_range(address, len, &range[index].from, (size_t)4, 0LL, (1LL << 8) - 1))
			return FAIL;

		/* if range is specified, extract the end value, otherwise set end value equal to the start value */
		if (NULL != dash)
		{
			dash++;
			if (FAIL == is_uint_n_range(dash, ptr - dash, &range[index].to, (size_t)4, 0LL, (1LL << 8) - 1))
				return FAIL;
		}
		else
			range[index].to = range[index].from;

		index++;
	}

	/* IPv4 address will always have 4 groups */
	if (4 != index)
		return FAIL;

	if (-1 != bits)
		iprange_apply_mask(range, bits, 4, 8);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: iprangev6_parse                                                  *
 *                                                                            *
 * Purpose: parse IPv6 address into range array                               *
 *                                                                            *
 * Parameters: address - [IN] the IP address                                  *
 *             range   - [OUT] the IP range array                             *
 *                                                                            *
 * Return value: SUCCEED - the IP range was successfully parsed               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	iprangev6_parse(const char *address, zbx_range_t *range)
{
	int		index, len, fill = -1, bits = -1, target;
	const char	*ptr = address, *dash, *end;

	if (NULL != (end = strchr(address, '/')))
	{
		if (FAIL == is_uint32(end + 1, &bits))
			return FAIL;
	}
	else
		end = address + strlen(address);

	/* iterate through address numbers (bit groups) */
	for (index = 0; ptr < end && index <= 8; address = ptr + 1)
	{
		if (NULL == (ptr = strchr(address, ':')))
			ptr = end;

		if (ptr == address)
		{
			/* handle the case when address starts with :: */
			if (':' != ptr[1])
				return FAIL;

			goto check_fill;
		}

		if (NULL != (dash = strchr(address, '-')))
		{
			/* having range and mask together is not supported */
			if (-1 != bits)
				return FAIL;

			/* check if the range specification is used by the current group */
			if (dash > ptr)
				dash = NULL;
		}

		len = (NULL == dash ? ptr : dash) - address;

		/* extract the range start value */
		if (FAIL == is_hex_n_range(address, len, &range[index].from, (size_t)4, 0LL, (1LL << 16) - 1))
			return FAIL;

		/* if range is specified, extract the end value, otherwise set end value equal to the start value */
		if (NULL != dash)
		{
			dash++;
			if (FAIL == is_hex_n_range(dash, ptr - dash, &range[index].to, (size_t)4, 0LL, (1LL << 16) - 1))
				return FAIL;
		}
		else
			range[index].to = range[index].from;

		index++;
check_fill:
		/* check if the next group is empty */
		if ('\0' != ptr[0] && ':' == ptr[1])
		{
			/* :: construct is allowed only once in address */
			if (-1 != fill)
				return FAIL;

			range[index].from = 0;
			range[index].to = 0;
			fill = index++;
			ptr++;

			/* check if address ends with :: */
			if (ptr == end - 1)
				break;
		}
	}

	/* fail if the address contains 9+ groups */
	if (8 < index)
		return FAIL;

	/* expand the :: construct to the required number of zeroes */
	if (8 > index)
	{
		/* fail if the address contains less than 8 groups and no :: construct was used */
		if (-1 == fill)
			return FAIL;

		target = 7;

		/* shift the second part of address to the end */
		while (--index > fill)
			range[target--] = range[index];

		/* fill the middle with zeroes */
		while (target > fill)
		{
			range[target].from = 0;
			range[target].to = 0;
			target--;
		}
	}

	if (-1 != bits)
		iprange_apply_mask(range, bits, 8, 16);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_parse                                                    *
 *                                                                            *
 * Purpose: parse IP address (v4 or v6) into range array                      *
 *                                                                            *
 * Parameters: address - [IN] the IP address                                  *
 *             range   - [OUT] the IP range array (with at least 8 items to   *
 *                             support IPv6)                                  *
 *             type    - [OUT] the type of parsed address - see ZBX_IPRANGE_* *
 *                             defines                                        *
 *                                                                            *
 * Return value: SUCCEED - the IP range was successfully parsed               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	iprange_parse(const char *address, zbx_range_t *range, int *type)
{
	if (NULL != strchr(address, '.'))
	{
		*type = ZBX_IPRANGE_V4;
		return iprangev4_parse(address, range);
	}
	else
	{
		*type = ZBX_IPRANGE_V6;
		return iprangev6_parse(address, range);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_first                                                    *
 *                                                                            *
 * Purpose: gets the first IP address from the specified range                *
 *                                                                            *
 * Parameters: range   - [IN] the IP range array                              *
 *             type    - [IN] the address type (see ZBX_IPRANGE_* defines)    *
 *             address - [OUT] the first address of the specified range       *
 *                             (with at least 8 items to support IPv6)        *
 *                                                                            *
 * Comments: The IP address is returned as a number array.                    *
 *                                                                            *
 ******************************************************************************/
void	iprange_first(const zbx_range_t *range, int type, int *address)
{
	int	i, groups;

	groups = (ZBX_IPRANGE_V4 == type ? 4 : 8);

	for (i = 0; i < groups; i++)
		address[i] = range[i].from;
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_next                                                     *
 *                                                                            *
 * Purpose: gets the next IP address from the specified range                 *
 *                                                                            *
 * Parameters: range   - [IN] the IP range array                              *
 *             type    - [IN] the address type (see ZBX_IPRANGE_* defines)    *
 *             address - [IN/OUT] IN - the current address from IP range      *
 *                                OUT - the next address from IP range        *
 *                                (with at least 8 items to support IPv6)     *
 *                                                                            *
 * Return value: SUCCEED - the next IP address was returned successfully      *
 *               FAIL    - no more addresses in the specified range           *
 *                                                                            *
 * Comments: The IP address is returned as a number array.                    *
 *                                                                            *
 ******************************************************************************/
int	iprange_next(const zbx_range_t *range, int type, int *address)
{
	int	i, groups;

	groups = (ZBX_IPRANGE_V4 == type ? 4 : 8);

	for (i = groups - 1; i >= 0; i--)
	{
		if (address[i] < range[i].to)
		{
			address[i]++;
			return SUCCEED;
		}

		if (range[i].from < range[i].to)
			address[i] = range[i].from;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_validate                                                 *
 *                                                                            *
 * Purpose: checks if the IP address is in specified range                    *
 *                                                                            *
 * Parameters: range   - [IN] the IP range array                              *
 *             type    - [IN] the address type (see ZBX_IPRANGE_* defines)    *
 *             address - [IN] the IP address to check                         *
 *                            (with at least 8 items to support IPv6)         *
 *                                                                            *
 * Return value: SUCCEED - the IP address was in the specified range          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	iprange_validate(const zbx_range_t *range, int type, const int *address)
{
	int	i, groups;

	groups = (ZBX_IPRANGE_V4 == type ? 4 : 8);

	for (i = 0; i < groups; i++)
	{
		if (address[i] < range[i].from || address[i] > range[i].to)
			return FAIL;
	}

	return SUCCEED;
}
