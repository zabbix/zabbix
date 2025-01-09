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

#include "ip_reverse.h"

#include "zbxip.h"
#include "zbxcomms.h"

static void	reverse_digits(short unsigned int a, char s2[8])
{
	char	s[8];

	zbx_snprintf(s, 8, "%04x", a);

	for (int i = 0; i <= 3; i++)
	{
		(s2)[i * 2] = s[3 - i];
		(s2)[i * 2 + 1] = '.';
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Creates a new IP, from the provided one, that is suitabe for the  *
 *          reverse DNS lookup (PTR record type). IP needs to be reversed and *
 *          supplied in the special format for such queries. If the IP        *
 *          already is in the acceptable format - this will be detected, and  *
 *          new IP copy will not be changed, the returned IP will be          *
 *          identical to the supplied one. Otherwise, this functions attempts *
 *          to reverse and format it.                                         *
 *                                                                            *
 * Parameters:                                                                *
 *         src_ip - [IN]                                                      *
 *         dst_ip - [OUT] newly allocated string IP that is suitable to be    *
 *                        supplied to reverse DNS lookup                      *
 *         error  - [OUT]                                                     *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 * Comments: allocates memory                                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_ip_reverse(const char *src_ip, char **dst_ip, char **error)
{
	if (NULL != strstr(src_ip, ".ip6.arpa") || NULL != strstr(src_ip, ".in-addr.arpa"))
	{
		*dst_ip = zbx_strdup(*dst_ip, src_ip);

		return SUCCEED;
	}

	if (SUCCEED == zbx_is_ip6(src_ip))
	{
		char			*expanded_ip;
		short unsigned int	aa1, bb1, cc1, dd1, aa2, bb2, cc2, dd2;
		struct in6_addr		sin6_addr;

		int	inet_pton_res = zbx_inet_pton(AF_INET6, src_ip, &sin6_addr);

		if (FAIL == inet_pton_res)
		{
			*error = zbx_dsprintf(*error, "IP: '%s' could not be parsed: %s",
					src_ip, zbx_strerror(errno));

			return FAIL;
		}

		expanded_ip = zbx_dsprintf(NULL,
				"%02x%02x:%02x%02x:%02x%02x:%02x%02x:%02x%02x:%02x%02x:%02x%02x:%02x%02x",
				sin6_addr.s6_addr[0], sin6_addr.s6_addr[1],
				sin6_addr.s6_addr[2], sin6_addr.s6_addr[3],
				sin6_addr.s6_addr[4], sin6_addr.s6_addr[5],
				sin6_addr.s6_addr[6], sin6_addr.s6_addr[7],
				sin6_addr.s6_addr[8], sin6_addr.s6_addr[9],
				sin6_addr.s6_addr[10], sin6_addr.s6_addr[11],
				sin6_addr.s6_addr[12], sin6_addr.s6_addr[13],
				sin6_addr.s6_addr[14], sin6_addr.s6_addr[15]);

		if (8 != sscanf(expanded_ip, "%hx:%hx:%hx:%hx:%hx:%hx:%hx:%hx", &aa1, &bb1 ,&cc1, &dd1,
				&aa2, &bb2, &cc2, &dd2))
		{
			*error = zbx_dsprintf(*error, "IP: '%s' could not be parsed.", src_ip);
			zbx_free(expanded_ip);

			return FAIL;
		}

		zbx_free(expanded_ip);

		char	aa1r[8], bb1r[8], cc1r[8], dd1r[8], aa2r[8], bb2r[8], cc2r[8], dd2r[8];

		reverse_digits(aa1, aa1r);
		reverse_digits(bb1, bb1r);
		reverse_digits(cc1, cc1r);
		reverse_digits(dd1, dd1r);
		reverse_digits(aa2, aa2r);
		reverse_digits(bb2, bb2r);
		reverse_digits(cc2, cc2r);
		reverse_digits(dd2, dd2r);

		*dst_ip = zbx_dsprintf(NULL, "%.8s%.8s%.8s%.8s%.8s%.8s%.8s%.7s.ip6.arpa",
				dd2r, cc2r, bb2r, aa2r, dd1r, cc1r, bb1r, aa1r);
	}
	else if (SUCCEED == zbx_is_ip4(src_ip))
	{
		unsigned char	aa, bb, cc, dd;

		if (4 != sscanf(src_ip, "%hhu.%hhu.%hhu.%hhu", &aa, &bb, &cc, &dd))
		{
			*error = zbx_dsprintf(*error, "IP: '%s' could not be parsed.", src_ip);

			return FAIL;
		}

		*dst_ip = zbx_dsprintf(NULL, "%hhu.%hhu.%hhu.%hhu.in-addr.arpa", dd, cc, bb, aa);
	}
	else
	{
		*error = zbx_dsprintf(*error, "IP: '%s' could not be parsed.", src_ip);

		return FAIL;
	}

	return SUCCEED;
}
