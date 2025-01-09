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

#include "zbxip.h"

#include "zbxnum.h"

/******************************************************************************
 *                                                                            *
 * Purpose: Checks if the specified character is allowed whitespace character *
 *          that can be used before or after iprange definition.              *
 *                                                                            *
 * Parameters: value - [IN] character to check                                *
 *                                                                            *
 * Return value: SUCCEED - value is whitespace character                      *
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
 * Purpose: calculates length of address data without trailing whitespace     *
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
 * Purpose: applies bit mask to parsed v4 or v6 IP range                      *
 *                                                                            *
 * Parameters: iprange - [IN]                                                 *
 *             bits    - [IN] number of bits in IP mask                       *
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
 * Purpose: parses IPv4 address into IP range structure                       *
 *                                                                            *
 * Parameters: iprange - [OUT]                                                *
 *             address - [IN] IP address with the optional ranges or network  *
 *                            mask (see documentation for network discovery   *
 *                            rule configuration).                            *
 *                                                                            *
 * Return value: SUCCEED - IP range was successfully parsed                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	iprangev4_parse(zbx_iprange_t *iprange, const char *address)
{
	int		index, bits = -1;
	const char	*ptr = address, *dash, *end;
	size_t		len;

	iprange->type = ZBX_IPRANGE_V4;

	/* ignore trailing whitespace characters */
	len = iprange_address_length(address);

	if (NULL != (end = strchr(address, '/')))
	{
		if (FAIL == zbx_is_uint_n_range(end + 1, len - (end + 1 - address), &bits, sizeof(bits), 0, 30))
			return FAIL;

		iprange->mask = 1;
	}
	else
	{
		end = address + len;
		iprange->mask = 0;
	}

	/* iterate through address numbers (bit groups) */
	for (index = 0; ptr < end && index < ZBX_IPRANGE_GROUPS_V4; address = ptr + 1)
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
		if (FAIL == zbx_is_uint_n_range(address, len, &iprange->range[index].from,
				sizeof(iprange->range[index].from), 0, 255))
		{
			return FAIL;
		}

		/* if range is specified, extract the end value, otherwise set end value equal to the start value */
		if (NULL != dash)
		{
			dash++;
			if (FAIL == zbx_is_uint_n_range(dash, ptr - dash, &iprange->range[index].to,
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
	if (ZBX_IPRANGE_GROUPS_V4 != index)
		return FAIL;

	if (-1 != bits)
		iprange_apply_mask(iprange, bits);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses IPv6 address into IP range structure                       *
 *                                                                            *
 * Parameters: iprange - [OUT]                                                *
 *             address - [IN] IP address with the optional ranges or network  *
 *                             mask (see documentation for network discovery  *
 *                             rule configuration).                           *
 *                                                                            *
 * Return value: SUCCEED - IP range was successfully parsed                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	iprangev6_parse(zbx_iprange_t *iprange, const char *address)
{
	int		index, fill = -1, bits = -1, target;
	const char	*ptr = address, *dash, *end;
	size_t		len;

	iprange->type = ZBX_IPRANGE_V6;

	/* ignore trailing whitespace characters */
	len = iprange_address_length(address);

	if (NULL != (end = strchr(address, '/')))
	{
		if (FAIL == zbx_is_uint_n_range(end + 1, len - (end + 1 - address), &bits, sizeof(bits), 0, 128))
			return FAIL;

		iprange->mask = 1;
	}
	else
	{
		end = address + len;
		iprange->mask = 0;
	}

	/* iterate through address numbers (bit groups) */
	for (index = 0; ptr < end && index < ZBX_IPRANGE_GROUPS_V6; address = ptr + 1)
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
		if (FAIL == zbx_is_hex_n_range(address, len, &iprange->range[index].from, 4, 0, (1 << 16) - 1))
			return FAIL;

		/* if range is specified, extract the end value, otherwise set end value equal to the start value */
		if (NULL != dash)
		{
			dash++;
			if (FAIL == zbx_is_hex_n_range(dash, ptr - dash, &iprange->range[index].to, 4, 0, (1 << 16) - 1))
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
	if (ZBX_IPRANGE_GROUPS_V6 < index)
		return FAIL;

	/* expand the :: construct to the required number of zeros */
	if (ZBX_IPRANGE_GROUPS_V6 > index)
	{
		/* fail if the address contains less than 8 groups and no :: construct was used */
		if (-1 == fill)
			return FAIL;

		target = 7;

		/* shift the second part of address to the end */
		while (--index > fill)
			iprange->range[target--] = iprange->range[index];

		/* fill the middle with zeros */
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
 * Purpose: converts IP address (v4 or v6) into IP string                     *
 *                                                                            *
 * Parameters: type      - [IN] type of IP                                    *
 *             ipaddress - [IN] IP address as number array                    *
 *             ip        - [IN/OUT] string with current address from IP range *
 *             len       - [IN] size of string buffer for ip address          *
 *                                                                            *
 ******************************************************************************/
void	zbx_iprange_ip2str(const unsigned char type, const int *ipaddress, char *ip, const size_t len)
{
	if (ZBX_IPRANGE_V6 == type)
	{
		zbx_snprintf(ip, len, "%x:%x:%x:%x:%x:%x:%x:%x", (unsigned int)ipaddress[0],
				(unsigned int)ipaddress[1], (unsigned int)ipaddress[2],
				(unsigned int)ipaddress[3], (unsigned int)ipaddress[4],
				(unsigned int)ipaddress[5], (unsigned int)ipaddress[6],
				(unsigned int)ipaddress[7]);
	}
	else
	{
		zbx_snprintf(ip, len, "%u.%u.%u.%u", (unsigned int)ipaddress[0], (unsigned int)ipaddress[1],
				(unsigned int)ipaddress[2], (unsigned int)ipaddress[3]);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses IP address (v4 or v6) into IP range structure              *
 *                                                                            *
 * Parameters: iprange - [OUT]                                                *
 *             address - [IN] IP address with the optional ranges or network  *
 *                            mask (see documentation for network discovery   *
 *                            rule configuration).                            *
 *                                                                            *
 * Return value: SUCCEED - IP range was successfully parsed                   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_iprange_parse(zbx_iprange_t *iprange, const char *address)
{
	/* ignore leading whitespace characters */
	while (SUCCEED == iprange_is_whitespace_character(*address))
		address++;

	if (NULL != strchr(address, '.'))
		return iprangev4_parse(iprange, address);

	return iprangev6_parse(iprange, address);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets first IP address from specified range                        *
 *                                                                            *
 * Parameters: iprange - [IN]                                                 *
 *             address - [OUT] first address of specified range               *
 *                             (with at least 8 items to support IPv6)        *
 *                                                                            *
 * Comments: IP address is returned as number array                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_iprange_first(const zbx_iprange_t *iprange, int *address)
{
	int	i, groups;

	groups = (ZBX_IPRANGE_V4 == iprange->type ? ZBX_IPRANGE_GROUPS_V4 : ZBX_IPRANGE_GROUPS_V6);

	for (i = 0; i < groups; i++)
		address[i] = iprange->range[i].from;

	/* exclude network address if the IPv4 range was specified with network mask */
	if (ZBX_IPRANGE_V4 == iprange->type && 0 != iprange->mask)
		address[groups - 1]++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets next IP address from specified range                         *
 *                                                                            *
 * Parameters: iprange - [IN]                                                 *
 *             address - [IN/OUT] IN  - current address from IP range         *
 *                                OUT - next address from IP range            *
 *                                (with at least 8 items to support IPv6)     *
 *                                                                            *
 * Return value: SUCCEED - next IP address was returned successfully          *
 *               FAIL    - no more addresses in specified range               *
 *                                                                            *
 * Comments: IP address is returned as number array                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_iprange_next(const zbx_iprange_t *iprange, int *address)
{
	int	i, groups;

	groups = (ZBX_IPRANGE_V4 == iprange->type ? ZBX_IPRANGE_GROUPS_V4 : ZBX_IPRANGE_GROUPS_V6);

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
 * Purpose: gets next unique IP address from specified range                  *
 *                                                                            *
 * Parameters: ipranges - [IN] array of ipranges                              *
 *             num      - [IN] size of ipranges array                         *
 *             ip       - [IN/OUT] string with current address from IP range  *
 *             len      - [IN] size of string buffer for ip address           *
 *                                                                            *
 * Return value: SUCCEED - next IP address was in specified range             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_iprange_uniq_next(const zbx_iprange_t *ipranges, const int num, char *ip, const size_t len)
{
	static ZBX_THREAD_LOCAL int	idx, ipaddress[8];

	if ('\0' == *ip)
	{
		idx = 0;
		zbx_iprange_first(&ipranges[idx], ipaddress);
		zbx_iprange_ip2str(ipranges[idx].type, ipaddress, ip, len);

		return SUCCEED;
	}

	if (FAIL == zbx_iprange_uniq_iter(ipranges, num, &idx, ipaddress))
		return FAIL;

	zbx_iprange_ip2str(ipranges[idx].type, ipaddress, ip, len);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets next unique digital IP address from specified range          *
 *                                                                            *
 * Parameters: ipranges  - [IN] array of ipranges                             *
 *             num       - [IN] size of ipranges array                        *
 *             idx       - [IN/OUT] current index of ipranges                 *
 *             ipaddress - [IN/OUT] current ip address from range             *
 *                                                                            *
 * Return value: SUCCEED - next IP address was in specified range             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_iprange_uniq_iter(const zbx_iprange_t *ipranges, const int num, int *idx, int *ipaddress)
{
	int	i, z[ZBX_IPRANGE_GROUPS_V6] = {0};

	if (0 == num)
		return FAIL;

	if (0 == memcmp(ipaddress, z, sizeof(int) * (ZBX_IPRANGE_V4 == ipranges->type ?
			ZBX_IPRANGE_GROUPS_V4 : ZBX_IPRANGE_GROUPS_V6)))
	{
		*idx = 0;
		zbx_iprange_first(ipranges, ipaddress);
		return SUCCEED;
	}

	if (*idx == num)
		return FAIL;

	do
	{
		if (FAIL == zbx_iprange_next(&ipranges[*idx], ipaddress))
		{
			if (++(*idx) == num)
				return FAIL;

			zbx_iprange_first(&ipranges[*idx], ipaddress);
		}

		for (i = 0; i < *idx; i++)
		{
			if (SUCCEED == zbx_iprange_validate(&ipranges[i], ipaddress))
				break;
		}
	}
	while (i != *idx);	/* skipping ip from overlapping ipranges */

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if IP address is in specified range                        *
 *                                                                            *
 * Parameters: iprange - [IN]                                                 *
 *             address - [IN] IP address to check                             *
 *                            (with at least 8 items to support IPv6)         *
 *                                                                            *
 * Return value: SUCCEED - IP address was in specified range                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_iprange_validate(const zbx_iprange_t *iprange, const int *address)
{
	int	i, groups;

	groups = (ZBX_IPRANGE_V4 == iprange->type ? ZBX_IPRANGE_GROUPS_V4 : ZBX_IPRANGE_GROUPS_V6);

	for (i = 0; i < groups; i++)
	{
		if (address[i] < iprange->range[i].from || address[i] > iprange->range[i].to)
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets number of addresses covered by specified IP range            *
 *                                                                            *
 * Parameters: iprange - [IN]                                                 *
 *                                                                            *
 * Return value: The number of addresses covered by the range or              *
 *               ZBX_MAX_UINT64 if this number exceeds 64 bit unsigned        *
 *               integer.                                                     *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_iprange_volume(const zbx_iprange_t *iprange)
{
	int		i, groups;
	zbx_uint64_t	n, volume = 1;

	groups = (ZBX_IPRANGE_V4 == iprange->type ? ZBX_IPRANGE_GROUPS_V4 : ZBX_IPRANGE_GROUPS_V6);

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

/******************************************************************************
 *                                                                            *
 * Purpose: gets next unique port value from specified range                  *
 *                                                                            *
 * Parameters: ranges - [IN] array of port ranges                             *
 *             num    - [IN] size of port ranges array                        *
 *             port   - [IN/OUT] port with current value from port range      *
 *                                                                            *
 * Return value: SUCCEED - next port value was in specified range             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_portrange_uniq_next(const zbx_range_t *ranges, const int num, int *port)
{
	static ZBX_THREAD_LOCAL int	idx, current_port;

	if (0 == num)
		return FAIL;

	if (ZBX_PORTRANGE_INIT_PORT == *port)
	{
		idx = 0;
		current_port = ranges[idx].from;
		*port = current_port;
		return SUCCEED;
	}

	if (num == idx)
		return FAIL;

	if (FAIL == zbx_portrange_uniq_iter(ranges, num, &idx, &current_port))
		return FAIL;

	*port = current_port;

	return SUCCEED;

}

/******************************************************************************
 *                                                                            *
 * Purpose: gets next unique port value from specified range                  *
 *                                                                            *
 * Parameters: ranges - [IN] array of port ranges (allowed values 1-65534)    *
 *             num    - [IN] size of port ranges array                        *
 *             idx    - [IN/OUT] index of range in array                      *
 *             port   - [IN/OUT] port with current value from port range      *
 *                                                                            *
 * Return value: SUCCEED - next port value was in specified range             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_portrange_uniq_iter(const zbx_range_t *ranges, const int num, int *idx, int *port)
{
	int	i;

	if (0 == num)
		return FAIL;

	if (ZBX_PORTRANGE_INIT_PORT == *port)
	{
		*idx = 0;
		*port = ranges->from;
		return SUCCEED;
	}

	if (num == *idx)
		return FAIL;

	do
	{
		if (++(*port) > ranges[*idx].to)
		{
			if (++(*idx) == num)
				return FAIL;

			*port = ranges[*idx].from;
		}

		for (i = 0; i < *idx; i++)
		{
			if (*port >= ranges[i].from && *port <= ranges[i].to)
				break;
		}
	}
	while (i != *idx);	/* skipping port from overlapping port ranges */

	return SUCCEED;
}
