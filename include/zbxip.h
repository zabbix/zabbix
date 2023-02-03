/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_IP_H
#define ZABBIX_IP_H

#include "zbxtypes.h"

int	zbx_is_ip4(const char *ip);
int	zbx_is_ip6(const char *ip);
int	zbx_is_supported_ip(const char *ip);
int	zbx_is_ip(const char *ip);

int	zbx_ip_in_list(const char *list, const char *ip);

int	zbx_parse_serveractive_element(char *str, char **host, unsigned short *port, unsigned short port_default);

#define ZBX_IPRANGE_V4	0
#define ZBX_IPRANGE_V6	1

#define ZBX_IPRANGE_GROUPS_V4	4
#define ZBX_IPRANGE_GROUPS_V6	8

typedef struct
{
	int	from;
	int	to;
}
zbx_range_t;

typedef struct
{
	/* contains groups of ranges for either ZBX_IPRANGE_V4 or ZBX_IPRANGE_V6 */
	/* ex. 127-127.0-0.0-0.2-254 (from-to.from-to.from-to.from-to)           */
	/*                                  0       1       2       3            */
	zbx_range_t	range[ZBX_IPRANGE_GROUPS_V6];

	/* range type - ZBX_IPRANGE_V4 or ZBX_IPRANGE_V6 */
	unsigned char	type;

	/* 1 if the range was defined with network mask, 0 otherwise */
	unsigned char   mask;
}
zbx_iprange_t;

int	zbx_iprange_parse(zbx_iprange_t *iprange, const char *address);
void	zbx_iprange_first(const zbx_iprange_t *iprange, int *address);
int	zbx_iprange_next(const zbx_iprange_t *iprange, int *address);
int	zbx_iprange_validate(const zbx_iprange_t *iprange, const int *address);
zbx_uint64_t	zbx_iprange_volume(const zbx_iprange_t *iprange);

#endif /* ZABBIX_IP_H */
