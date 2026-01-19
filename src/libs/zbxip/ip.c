/*
** Copyright (C) 2001-2026 Zabbix SIA
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
#include "zbxstr.h"

/******************************************************************************
 *                                                                            *
 * Purpose: checks if string is IPv4 address                                  *
 *                                                                            *
 * Parameters: ip - [IN]                                                      *
 *                                                                            *
 * Return value: SUCCEED - input is IPv4 address                              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_ip4(const char *ip)
{
	const char	*p = ip;
	int		digits = 0, dots = 0, res = FAIL, octet = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __func__, ip);

	while ('\0' != *p)
	{
		if (0 != isdigit(*p))
		{
			octet = octet * 10 + (*p - '0');
			digits++;
		}
		else if ('.' == *p)
		{
			if (0 == digits || 3 < digits || 255 < octet)
				break;

			digits = 0;
			octet = 0;
			dots++;
		}
		else
		{
			digits = 0;
			break;
		}

		p++;
	}
	if (3 == dots && 1 <= digits && 3 >= digits && 255 >= octet)
		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if string is IPv6 address                                  *
 *                                                                            *
 * Parameters: ip - [IN]                                                      *
 *                                                                            *
 * Return value: SUCCEED - input is IPv6 address                              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_ip6(const char *ip)
{
	const char	*p = ip, *last_colon;
	int		xdigits = 0, only_xdigits = 0, colons = 0, dbl_colons = 0, res;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ip:'%s'", __func__, ip);

	while ('\0' != *p)
	{
		if (0 != isxdigit(*p))
		{
			xdigits++;
			only_xdigits = 1;
		}
		else if (':' == *p)
		{
			if (0 == xdigits && 0 < colons)
			{
				/* consecutive sections of zeros are replaced with a double colon */
				only_xdigits = 1;
				dbl_colons++;
			}

			if (4 < xdigits || 1 < dbl_colons)
				break;

			xdigits = 0;
			colons++;
		}
		else
		{
			only_xdigits = 0;
			break;
		}

		p++;
	}

	if (2 > colons || 7 < colons || 1 < dbl_colons || 4 < xdigits)
		res = FAIL;
	else if (1 == only_xdigits)
		res = SUCCEED;
	else if (7 > colons && (last_colon = strrchr(ip, ':')) < p)
		res = zbx_is_ip4(last_colon + 1);	/* past last column is ipv4 mapped address */
	else
		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Parameters: ip - [IN]                                                      *
 *                                                                            *
 * Return value: SUCCEED - input is IP address                                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_supported_ip(const char *ip)
{
	if (SUCCEED == zbx_is_ip4(ip))
		return SUCCEED;
#ifdef HAVE_IPV6
	if (SUCCEED == zbx_is_ip6(ip))
		return SUCCEED;
#endif
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Parameters: ip - [IN]                                                      *
 *                                                                            *
 * Return value: SUCCEED - input is IP address                                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_ip(const char *ip)
{
	return SUCCEED == zbx_is_ip4(ip) ? SUCCEED : zbx_is_ip6(ip);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if IP matches range of IP addresses                        *
 *                                                                            *
 * Parameters: list - [IN] comma-separated list of IP ranges                  *
 *                         192.168.0.1-64,192.168.0.128,10.10.0.0/24,12fc::21 *
 *             ip   - [IN]                                                    *
 *                                                                            *
 * Return value: FAIL - out of range, SUCCEED - within range                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_ip_in_list(const char *list, const char *ip)
{
	int		ipaddress[8];
	zbx_iprange_t	iprange;
	char		*address = NULL;
	size_t		address_alloc = 0, address_offset;
	const char	*ptr;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() list:'%s' ip:'%s'", __func__, list, ip);

	if (SUCCEED != zbx_iprange_parse(&iprange, ip))
		goto out;
#ifndef HAVE_IPV6
	if (ZBX_IPRANGE_V6 == iprange.type)
		goto out;
#endif
	zbx_iprange_first(&iprange, ipaddress);

	for (ptr = list; '\0' != *ptr; list = ptr + 1)
	{
		if (NULL == (ptr = strchr(list, ',')))
			ptr = list + strlen(list);

		address_offset = 0;
		zbx_strncpy_alloc(&address, &address_alloc, &address_offset, list, (size_t)(ptr - list));

		if (SUCCEED != zbx_iprange_parse(&iprange, address))
			continue;
#ifndef HAVE_IPV6
		if (ZBX_IPRANGE_V6 == iprange.type)
			continue;
#endif
		if (SUCCEED == zbx_iprange_validate(&iprange, ipaddress))
		{
			ret = SUCCEED;
			break;
		}
	}

	zbx_free(address);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses ServerActive element like "IP<:port>" or "[IPv6]<:port>"   *
 *                                                                            *
 ******************************************************************************/
int	zbx_parse_serveractive_element(const char *str, char **host, unsigned short *port, unsigned short port_default)
{
#ifdef HAVE_IPV6
	char	*r1 = NULL;
#endif
	char	*r2 = NULL;
	int	res = FAIL;

	*port = port_default;

#ifdef HAVE_IPV6
	if ('[' == *str)
	{
		str++;

		if (NULL == (r1 = strchr(str, ']')))
			goto fail;

		if (':' != r1[1] && '\0' != r1[1])
			goto fail;

		if (':' == r1[1] && SUCCEED != zbx_is_ushort(r1 + 2, port))
			goto fail;

		*r1 = '\0';

		if (SUCCEED != zbx_is_ip6(str))
			goto fail;

		*host = zbx_strdup(*host, str);
	}
	else if (SUCCEED == zbx_is_ip6(str))
	{
		*host = zbx_strdup(*host, str);
	}
	else
	{
#endif
		if (NULL != (r2 = strchr(str, ':')))
		{
			if (SUCCEED != zbx_is_ushort(r2 + 1, port))
				goto fail;

			*r2 = '\0';
		}

		*host = zbx_strdup(NULL, str);
#ifdef HAVE_IPV6
	}
#endif

	res = SUCCEED;
fail:
#ifdef HAVE_IPV6
	if (NULL != r1)
		*r1 = ']';
#endif
	if (NULL != r2)
		*r2 = ':';

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: combines host and port into a network address "host:port"         *
 *                                                                            *
 * Parameters: hostport       - [IN/OUT] string formatting buffer pointer     *
 *             hostport_sz    - [IN] size of buffer                           *
 *             host           - [IN]                                          *
 *             port           - [IN]                                          *
 *                                                                            *
 * Return value: pointer to hostport buffer                                   *
 *                                                                            *
 ******************************************************************************/
char	*zbx_join_hostport(char *hostport, size_t hostport_sz, const char *host, unsigned short port)
{
	const char	*format = "%s:%hu";

	if (NULL != strchr(host, ':'))
		format = "[%s]:%hu";

	zbx_snprintf(hostport, hostport_sz, format, host, port);

	return hostport;
}

static int	subnet_match(int af, unsigned int prefix_size, const void *address1, const void *address2)
{
	unsigned char	netmask[16] = {0};
	int		i, j, bytes;

	if (af == AF_INET)
	{
		if (prefix_size > ZBX_IPV4_MAX_CIDR_PREFIX)
			return FAIL;
		bytes = 4;
	}
	else
	{
		if (prefix_size > ZBX_IPV6_MAX_CIDR_PREFIX)
			return FAIL;
		bytes = 16;
	}

	/* CIDR notation to subnet mask */
	for (i = (int)prefix_size, j = 0; i > 0 && j < bytes; i -= 8, j++)
		netmask[j] = (unsigned char)(i >= 8 ? 0xFF : ~((1 << (8 - i)) - 1));

	/* The result of the bitwise AND operation of IP address and the subnet mask is the network prefix. */
	/* All hosts on a subnetwork have the same network prefix. */
	for (i = 0; i < bytes; i++)
	{
		if ((((const unsigned char *)address1)[i] & netmask[i]) !=
				(((const unsigned char *)address2)[i] & netmask[i]))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if address belongs to given subnet                          *
 *                                                                            *
 * Parameters: prefix_size - [IN] subnet prefix size                          *
 *             current_ai  - [IN] subnet                                      *
 *             name        - [IN] address                                     *
 *             ipv6v4_mode - [IN] compare IPv6 IPv4-mapped address with       *
 *                                IPv4 addresses only                         *
 *                                                                            *
 * Return value: SUCCEED - address belongs to subnet                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
#ifndef HAVE_IPV6
int	zbx_ip_cmp(int prefix_size, const struct sockaddr *ai_addr, int ai_family, const ZBX_SOCKADDR *name,
		int ipv6v4_mode)
{
	const struct sockaddr_in	*name4 = (const struct sockaddr_in *)name,
					*ai_addr4 = (const struct sockaddr_in *)ai_addr;
	if (-1 == prefix_size)
		prefix_size = ai_family == AF_INET ? ZBX_IPV4_MAX_CIDR_PREFIX : ZBX_IPV6_MAX_CIDR_PREFIX;

	ZBX_UNUSED(ipv6v4_mode);

	return subnet_match(ai_family, prefix_size, &name4->sin_addr.s_addr, &ai_addr4->sin_addr.s_addr);
}
#else
int	zbx_ip_cmp(int prefix_size, const struct sockaddr *ai_addr, int ai_family, const ZBX_SOCKADDR *name,
		int ipv6v4_mode)
{
	/* Network Byte Order is ensured */
	/* IPv4-compatible, the first 96 bits are zeros */
	const unsigned char	ipv4_compat_mask[12] = {0};
	/* IPv4-mapped, the first 80 bits are zeros, 16 next - ones */
	const unsigned char	ipv4_mapped_mask[12] = {0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 255, 255};

	const struct sockaddr_in	*name4 = (const struct sockaddr_in *)name,
					*ai_addr4 = (const struct sockaddr_in *)ai_addr;
	const struct sockaddr_in6	*name6 = (const struct sockaddr_in6 *)name,
					*ai_addr6 = (const struct sockaddr_in6 *)ai_addr;

	if (-1 == prefix_size)
		prefix_size = ai_family == AF_INET ? ZBX_IPV4_MAX_CIDR_PREFIX : ZBX_IPV6_MAX_CIDR_PREFIX;

#ifdef HAVE_SOCKADDR_STORAGE_SS_FAMILY
	if (ai_family == name->ss_family)
#else
	if (ai_family == name->__ss_family)
#endif
	{
		switch (ai_family)
		{
			case AF_INET:
				if (SUCCEED == subnet_match(ai_family, prefix_size, &name4->sin_addr.s_addr,
						&ai_addr4->sin_addr.s_addr))
				{
					return SUCCEED;
				}
				break;
			case AF_INET6:
				if ((0 == ipv6v4_mode || 0 != memcmp(name6->sin6_addr.s6_addr, ipv4_mapped_mask, 12)) &&
						SUCCEED == subnet_match(ai_family, prefix_size,
						name6->sin6_addr.s6_addr,
						ai_addr6->sin6_addr.s6_addr))
				{
					return SUCCEED;
				}
				break;
		}
	}
	else
	{
		unsigned char	ipv6_compat_address[16], ipv6_mapped_address[16];

		if (AF_INET == ai_family)
		{
			/* incoming AF_INET6, must see whether it is compatible or mapped */

			if (((0 == memcmp(name6->sin6_addr.s6_addr, ipv4_mapped_mask, 12)) ||
					(0 == ipv6v4_mode && 0 == memcmp(name6->sin6_addr.s6_addr,
					ipv4_compat_mask, 12))) && SUCCEED == subnet_match(AF_INET, prefix_size,
					&name6->sin6_addr.s6_addr[12], &ai_addr4->sin_addr.s_addr))
			{
				return SUCCEED;
			}
		}
		else if (AF_INET6 == ai_family && 0 == ipv6v4_mode)
		{
			/* incoming AF_INET, must see whether the given is compatible or mapped */

			memcpy(ipv6_compat_address, ipv4_compat_mask, sizeof(ipv4_compat_mask));
			memcpy(&ipv6_compat_address[sizeof(ipv4_compat_mask)], &name4->sin_addr.s_addr, 4);

			memcpy(ipv6_mapped_address, ipv4_mapped_mask, sizeof(ipv4_mapped_mask));
			memcpy(&ipv6_mapped_address[sizeof(ipv4_mapped_mask)], &name4->sin_addr.s_addr, 4);

			if (SUCCEED == subnet_match(AF_INET6, prefix_size,
					&ai_addr6->sin6_addr.s6_addr, ipv6_compat_address) ||
					SUCCEED == subnet_match(AF_INET6, prefix_size,
					&ai_addr6->sin6_addr.s6_addr, ipv6_mapped_address))
			{
				return SUCCEED;
			}
		}
	}
	return FAIL;
}
#endif

int	zbx_validate_cidr(const char *ip, const char *cidr, void *value)
{
	if (SUCCEED == zbx_is_ip4(ip))
		return zbx_is_uint_range(cidr, value, 0, ZBX_IPV4_MAX_CIDR_PREFIX);
#ifdef HAVE_IPV6
	if (SUCCEED == zbx_is_ip6(ip))
		return zbx_is_uint_range(cidr, value, 0, ZBX_IPV6_MAX_CIDR_PREFIX);
#endif
	return FAIL;
}

