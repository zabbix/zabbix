/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "dbcache.h"

#define ZBX_PROXYMODE_ACTIVE	0
#define ZBX_PROXYMODE_PASSIVE	1

#define ZBX_MAX_HRECORDS	1000
#define ZBX_MAX_HRECORDS_TOTAL	10000

#define ZBX_PROXY_DATA_DONE	0
#define ZBX_PROXY_DATA_MORE	1

#define ZBX_PROXY_UPLOAD_UNDEFINED	0
#define ZBX_PROXY_UPLOAD_DISABLED	1
#define ZBX_PROXY_UPLOAD_ENABLED	2

int	get_active_proxy_from_request(struct zbx_json_parse *jp, DC_PROXY *proxy, char **error);
int	zbx_proxy_check_permissions(const DC_PROXY *proxy, const zbx_socket_t *sock, char **error);
int	check_access_passive_proxy(zbx_socket_t *sock, int send_response, const char *req);

void	update_proxy_lastaccess(const zbx_uint64_t hostid, time_t last_access);

int	get_proxyconfig_data(zbx_uint64_t proxy_hostid, struct zbx_json *j, char **error);
int	process_proxyconfig(struct zbx_json_parse *jp_data, struct zbx_json_parse *jp_kvs_paths);

int	get_interface_availability_data(struct zbx_json *json, int *ts);

int	proxy_get_hist_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more);
int	proxy_get_dhis_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more);
int	proxy_get_areg_data(struct zbx_json *j, zbx_uint64_t *lastid, int *more);
void	proxy_set_hist_lastid(const zbx_uint64_t lastid);
void	proxy_set_dhis_lastid(const zbx_uint64_t lastid);
void	proxy_set_areg_lastid(const zbx_uint64_t lastid);

void	calc_timestamp(const char *line, int *timestamp, const char *format);

int	process_history_data(DC_ITEM *items, zbx_agent_value_t *values, int *errcodes, size_t values_num,
		zbx_proxy_suppress_t *nodata_win);

int	lld_process_discovery_rule(zbx_uint64_t lld_ruleid, const char *value, char **error);

int	proxy_get_history_count(void);
int	proxy_get_delay(zbx_uint64_t lastid);

int	zbx_get_proxy_protocol_version(struct zbx_json_parse *jp);
void	zbx_update_proxy_data(DC_PROXY *proxy, int version, int lastaccess, int compress, zbx_uint64_t flags_add);

int	process_proxy_history_data(const DC_PROXY *proxy, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info);
int	process_agent_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info);
int	process_sender_history_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts, char **info);
int	process_proxy_data(const DC_PROXY *proxy, struct zbx_json_parse *jp, zbx_timespec_t *ts,
		unsigned char proxy_status, int *more, char **error);
int	zbx_check_protocol_version(DC_PROXY *proxy, int version);

#endif
