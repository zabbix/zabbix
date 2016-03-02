
/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#ifndef ZABBIX_PROXY_H
#define ZABBIX_PROXY_H

#include "zbxjson.h"
#include "comms.h"

#define ZBX_PROXYMODE_ACTIVE	0
#define ZBX_PROXYMODE_PASSIVE	1

#define ZBX_MAX_HRECORDS	1000

#define AGENT_VALUE	struct zbx_agent_value_t

AGENT_VALUE
{
	zbx_timespec_t	ts;
	char		host_name[HOST_HOST_LEN_MAX];
	char		key[ITEM_KEY_LEN * 4 + 1];
	char		*value;
	char		*source;
	zbx_uint64_t	lastlogsize;
	int		mtime;
	int		timestamp;
	int		severity;
	int		logeventid;
	unsigned char	status;
};

int	get_proxy_id(struct zbx_json_parse *jp, zbx_uint64_t *hostid, char *host, char *error, int max_error_len);

void	update_proxy_lastaccess(const zbx_uint64_t hostid);

void	get_proxyconfig_data(zbx_uint64_t proxy_hostid, struct zbx_json *j);
void	process_proxyconfig(struct zbx_json_parse *jp_data);

int	get_host_availability_data(struct zbx_json *j);
void	process_host_availability(struct zbx_json_parse *jp_data);

int	proxy_get_hist_data(struct zbx_json *j, zbx_uint64_t *lastid);
int	proxy_get_dhis_data(struct zbx_json *j, zbx_uint64_t *lastid);
int	proxy_get_areg_data(struct zbx_json *j, zbx_uint64_t *lastid);
void	proxy_set_hist_lastid(const zbx_uint64_t lastid);
void	proxy_set_dhis_lastid(const zbx_uint64_t lastid);
void	proxy_set_areg_lastid(const zbx_uint64_t lastid);

void	calc_timestamp(char *line, int *timestamp, char *format);

void	process_mass_data(zbx_sock_t *sock, zbx_uint64_t proxy_hostid,
		AGENT_VALUE *values, int value_num, int *processed);
int	process_hist_data(zbx_sock_t *sock, struct zbx_json_parse *jp,
		const zbx_uint64_t proxy_hostid, char *info, int max_info_size);
void	process_dhis_data(struct zbx_json_parse *jp);
void	process_areg_data(struct zbx_json_parse *jp, zbx_uint64_t proxy_hostid);

void	DBlld_process_discovery_rule(zbx_uint64_t discovery_itemid, char *value, zbx_timespec_t *ts);

#endif
