/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
 * Function: iprange_is_whitespace_character                                  *
 *                                                                            *
 * Purpose: checks if the specified character is allowed whitespace character *
 *          that can be used before or after iprange definition               *
 *                                                                            *
 * Parameters: value - [IN] the character to check                            *
 *                                                                            *
 * Return value: SUCCEED - the value is whitespace character                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	iprange_is_whitespace_character(unsigned char value)
{
	switch (value)
	{
		case ' ':
		case '\r':
		case '\n':
		case '\t':
			return SUCCEED;
		default:
			return FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_address_length                                           *
 *                                                                            *
 * Purpose: calculates the length of address data without trailing whitespace *
 *                                                                            *
 ******************************************************************************/
static size_t	iprange_address_length(const char *address)
{
	size_t		len;
	const char	*ptr;

	len = strlen(address);
	ptr = address + len - 1;

	while (0 < len && SUCCEED == iprange_is_whitespace_character(*ptr))
	{
		ptr--;
		len--;
	}

	return len;
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_apply_mask                                               *
 *                                                                            *
 * Purpose: applies a bit mask to the parsed v4 or v6 IP range                *
 *                                                                            *
 * Parameters: iprange - [IN] the IP range                                    *
 *             bits    - [IN] the number of bits in IP mask                   *
 *                                                                            *
 ******************************************************************************/
static void	iprange_apply_mask(zbx_iprange_t *iprange, int bits)
{
	int	i, groups, group_bits;

	switch (iprange->type)
	{
		case ZBX_IPRANGE_V4:
			groups = 4;
			group_bits = 8;
			break;
		case ZBX_IPRANGE_V6:
			groups = 8;
			group_bits = 16;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return;
	}

	bits = groups * group_bits - bits;

	for (i = groups - 1; 0 < bits; bits -= group_bits, i--)
	{
		unsigned int	mask_empty, mask_fill;
		int		mask_bits = bits;

		if (mask_bits > group_bits)
			mask_bits = group_bits;

		mask_empty = 0xffffffff << mask_bits;
		mask_fill = 0xffffffff >> (32 - mask_bits);

		iprange->range[i].from &= mask_empty;
		iprange->range[i].to |= mask_fill;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: iprangev4_parse                                                  *
 *                                                                            *
 * Purpose: parse IPv4 address into IP range structure                        *
 *                                                                            *
 * Parameters: iprange - [OUT] the IP range                                   *
 *             address - [IN] the IP address                                  *
 *                                                                            *
 * Return value: SUCCEED - the IP range was successfully parsed               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	iprangev4_parse(zbx_iprange_t *iprange, const char *address)
{
	int		index, len, bits = -1;
	const char	*ptr = address, *dash, *end;

	iprange->type = ZBX_IPRANGE_V4;

	/* ignore trailing whitespace characters */
	len = iprange_address_length(address);

	if (NULL != (end = strchr(address, '/')))
	{
		if (FAIL == is_uint_n_range(end + 1, len - (end + 1 - address), &bits, sizeof(bits), 0, 30))
			return FAIL;

		iprange->mask = 1;
	}
	else
	{
		end = address + len;
		iprange->mask = 0;
	}

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
		if (FAIL == is_uint_n_range(address, len, &iprange->range[index].from,
				sizeof(iprange->range[index].from), 0, 255))
		{
			return FAIL;
		}

		/* if range is specified, extract the end value, otherwise set end value equal to the start value */
		if (NULL != dash)
		{
			dash++;
			if (FAIL == is_uint_n_range(dash, ptr - dash, &iprange->range[index].to,
					sizeof(iprange->range[index].to), 0, 255))
			{
				return FAIL;
			}

			if (iprange->range[index].to < iprange->range[index].from)
				return FAIL;
		}
		else
			iprange->range[index].to = iprange->range[index].from;

		index++;
	}

	/* IPv4 address will always have 4 groups */
	if (4 != index)
		return FAIL;

	if (-1 != bits)
		iprange_apply_mask(iprange, bits);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: iprangev6_parse                                                  *
 *                                                                            *
 * Purpose: parse IPv6 address into IP range structure                        *
 *                                                                            *
 * Parameters: iprange - [OUT] the IP range                                   *
 *             address - [IN] the IP address                                  *
 *                                                                            *
 * Return value: SUCCEED - the IP range was successfully parsed               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	iprangev6_parse(zbx_iprange_t *iprange, const char *address)
{
	int		index, len, fill = -1, bits = -1, target;
	const char	*ptr = address, *dash, *end;

	iprange->type = ZBX_IPRANGE_V6;

	/* ignore trailing whitespace characters */
	len = iprange_address_length(address);

	if (NULL != (end = strchr(address, '/')))
	{
		if (FAIL == is_uint_n_range(end + 1, len - (end + 1 - address), &bits, sizeof(bits), 0, 128))
			return FAIL;

		iprange->mask = 1;
	}
	else
	{
		end = address + len;
		iprange->mask = 0;
	}

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
		if (FAIL == is_hex_n_range(address, len, &iprange->range[index].from, 4, 0, (1 << 16) - 1))
			return FAIL;

		/* if range is specified, extract the end value, otherwise set end value equal to the start value */
		if (NULL != dash)
		{
			dash++;
			if (FAIL == is_hex_n_range(dash, ptr - dash, &iprange->range[index].to, 4, 0, (1 << 16) - 1))
				return FAIL;

			if (iprange->range[index].to < iprange->range[index].from)
				return FAIL;
		}
		else
			iprange->range[index].to = iprange->range[index].from;

		index++;
check_fill:
		/* check if the next group is empty */
		if ('\0' != ptr[0] && ':' == ptr[1])
		{
			/* :: construct is allowed only once in address */
			if (-1 != fill)
				return FAIL;

			iprange->range[index].from = 0;
			iprange->range[index].to = 0;
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
			iprange->range[target--] = iprange->range[index];

		/* fill the middle with zeroes */
		while (target > fill)
		{
			iprange->range[target].from = 0;
			iprange->range[target].to = 0;
			target--;
		}
	}

	if (-1 != bits)
		iprange_apply_mask(iprange, bits);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_parse                                                    *
 *                                                                            *
 * Purpose: parse IP address (v4 or v6) into IP range structure               *
 *                                                                            *
 * Parameters: iprange - [OUT] the IP range                                   *
 *             address - [IN] the IP address                                  *
 *                                                                            *
 * Return value: SUCCEED - the IP range was successfully parsed               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	iprange_parse(zbx_iprange_t *iprange, const char *address)
{
	/* ignore leading whitespace characters */
	while (SUCCEED == iprange_is_whitespace_character(*address))
		address++;

	if (NULL != strchr(address, '.'))
		return iprangev4_parse(iprange, address);
	else
		return iprangev6_parse(iprange, address);
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_first                                                    *
 *                                                                            *
 * Purpose: gets the first IP address from the specified range                *
 *                                                                            *
 * Parameters: iprange - [IN] the IP range                                    *
 *             address - [OUT] the first address of the specified range       *
 *                             (with at least 8 items to support IPv6)        *
 *                                                                            *
 * Comments: The IP address is returned as a number array.                    *
 *                                                                            *
 ******************************************************************************/
void	iprange_first(const zbx_iprange_t *iprange, int *address)
{
	int	i, groups;

	groups = (ZBX_IPRANGE_V4 == iprange->type ? 4 : 8);

	for (i = 0; i < groups; i++)
		address[i] = iprange->range[i].from;

	/* exclude network address if the IPv4 range was specified with network mask */
	if (ZBX_IPRANGE_V4 == iprange->type && 0 != iprange->mask)
		address[groups - 1]++;
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_next                                                     *
 *                                                                            *
 * Purpose: gets the next IP address from the specified range                 *
 *                                                                            *
 * Parameters: iprange - [IN] the IP range                                    *
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
int	iprange_next(const zbx_iprange_t *iprange, int *address)
{
	int	i, groups;

	groups = (ZBX_IPRANGE_V4 == iprange->type ? 4 : 8);

	for (i = groups - 1; i >= 0; i--)
	{
		if (address[i] < iprange->range[i].to)
		{
			address[i]++;

			/* exclude broadcast address if the IPv4 range was specified with network mask */
			if (ZBX_IPRANGE_V4 == iprange->type && 0 != iprange->mask)
			{
				for (i = groups - 1; i >= 0; i--)
				{
					if (address[i] != iprange->range[i].to)
						return SUCCEED;
				}

				return FAIL;
			}

			return SUCCEED;
		}

		if (iprange->range[i].from < iprange->range[i].to)
			address[i] = iprange->range[i].from;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_validate                                                 *
 *                                                                            *
 * Purpose: checks if the IP address is in specified range                    *
 *                                                                            *
 * Parameters: iprange - [IN] the IP range                                    *
 *             address - [IN] the IP address to check                         *
 *                            (with at least 8 items to support IPv6)         *
 *                                                                            *
 * Return value: SUCCEED - the IP address was in the specified range          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	iprange_validate(const zbx_iprange_t *iprange, const int *address)
{
	int	i, groups;

	groups = (ZBX_IPRANGE_V4 == iprange->type ? 4 : 8);

	for (i = 0; i < groups; i++)
	{
		if (address[i] < iprange->range[i].from || address[i] > iprange->range[i].to)
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: iprange_volume                                                   *
 *                                                                            *
 * Purpose: get the number of addresses covered by the specified IP range     *
 *                                                                            *
 * Parameters: iprange - [IN] the IP range                                    *
 *                                                                            *
 * Return value: The number of addresses covered by the range or              *
 *               ZBX_MAX_UINT64 if this number exceeds 64 bit unsigned        *
 *               integer.                                                     *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	iprange_volume(const zbx_iprange_t *iprange)
{
	int		i, groups;
	zbx_uint64_t	n, volume = 1;

	groups = (ZBX_IPRANGE_V4 == iprange->type ? 4 : 8);

	for (i = 0; i < groups; i++)
	{
		n = iprange->range[i].to - iprange->range[i].from + 1;

		if (ZBX_MAX_UINT64 / n < volume)
			return ZBX_MAX_UINT64;

		volume *= n;
	}

	/* exclude network and broadcast addresses if the IPv4 range was specified with network mask */
	if (ZBX_IPRANGE_V4 == iprange->type && 0 != iprange->mask)
		volume -= 2;

	return volume;
}
