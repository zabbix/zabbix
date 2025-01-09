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

#ifndef ZABBIX_ZBXICMPPING_H
#define ZABBIX_ZBXICMPPING_H

#include "zbxcommon.h"

typedef struct
{
	zbx_get_config_str_f	get_source_ip;
	zbx_get_config_str_f	get_fping_location;
	zbx_get_config_str_f	get_fping6_location;
	zbx_get_config_str_f	get_tmpdir;
	zbx_get_progname_f	get_progname;
}
zbx_config_icmpping_t;

typedef struct
{
	char	*addr;
	double	min;
	double	sum;
	double	max;
	int	rcv;
	int	cnt;
	char	*status;	/* array of individual response statuses: 1 - valid, 0 - timeout */
	char	*dnsname;
}
zbx_fping_host_t;

typedef enum
{
	ICMPPING = 0,
	ICMPPINGSEC,
	ICMPPINGLOSS
}
icmpping_t;

typedef enum
{
	ICMPPINGSEC_MIN = 0,
	ICMPPINGSEC_AVG,
	ICMPPINGSEC_MAX
}
icmppingsec_type_t;

typedef struct
{
	int			count;
	int			interval;
	int			size;
	int			timeout;
	zbx_uint64_t		itemid;
	char			*addr;
	icmpping_t		icmpping;
	icmppingsec_type_t	type;
	unsigned char		allow_redirect;
}
icmpitem_t;

void	zbx_init_library_icmpping(const zbx_config_icmpping_t *config);
void	zbx_init_icmpping_env(const char *prefix, long int id);

int	zbx_ping(zbx_fping_host_t *hosts, int hosts_count, int requests_count, int period, int size, int timeout,
		unsigned char allow_redirect, int rdns, char *error, size_t max_error_len);

#endif
