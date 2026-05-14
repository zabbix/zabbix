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
 * Purpose: check if string is valid host name used for checking services     *
 *                                                                            *
 * Parameters: host - [IN]                                                    *
 *                                                                            *
 * Return value: SUCCEED - input is hostname address                          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: valid host names for this function are names with only ASCII     *
 *           characters 0-9, A-Z, a-z, hyphen ('-') and dot ('.').            *
 *           Additionally underscore ('_') is allowed as Windows host names   *
 *           allow it.                                                        *
 *           Internationalized Domain Names with multibyte UTF-8 characters   *
 *           will be rejected as not valid (Punycode can be used).            *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_rfc_extended_hostname(const char *host)
{
	const char	*p = host;
	int		label_len = 1;
	int		prev_hyphen = 0;
	int		is_purely_numeric = 1;	/* detect numeric-only names */

	/* Requirements and limits for host names are defined in RFC 1035, */
	/* with clarifications in RFC 1123, RFC 2181. */

	/* Host name should start with [0-9A-Za-z], additionally underscore ('_') is allowed. */
	if ('\0' == *p || 0x80 == (0x80 & *p) || (0 == isalnum(*p) && '_' != *p))
		return FAIL;
	else if (0 == isdigit(*p++))
		is_purely_numeric = 0;

	while ('\0' != *p)
	{
		if (0 != (0x80 & *p))
			return FAIL;

		if (0 != isalnum(*p) || '_' == *p)
		{
			label_len++;
			prev_hyphen = 0;

			if (0 == isdigit(*p))
				is_purely_numeric = 0;
		}
		else if ('-' == *p)
		{
			/* label must not start with hyphen */
			if (0 == label_len)
				return FAIL;

			label_len++;
			prev_hyphen = 1;
			is_purely_numeric = 0;
		}
		else if ('.' == *p)
		{
			/* empty label or label ending with hyphen */
			if (0 == label_len || 1 == prev_hyphen)
				return FAIL;

			label_len = 0;
			prev_hyphen = 0;
		}
		else
			return FAIL;

		/* label should not exceed 63 characters */
		if (63 < label_len)
			return FAIL;

		p++;

		/* Total length should not exceed 253 characters. */
		/* This is excluding trailing dot and additional byte usually used when saved. */
		if (253 < p - host)
			return FAIL;
	}

	/* last label must not be empty or end with hyphen */
	if (0 == label_len || 1 == prev_hyphen)
		return FAIL;

	/* reject purely numeric names */
	if (1 == is_purely_numeric)
		return FAIL;

	return SUCCEED;
}

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

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): ip:'%s' %s", __func__, ip, zbx_result_string(res));

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

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): ip:'%s' %s", __func__, ip, zbx_result_string(res));

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
