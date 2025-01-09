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

#ifndef ZABBIX_ZBXPROXYBUFFER_H
#define ZABBIX_ZBXPROXYBUFFER_H

#include "zbxalgo.h"
#include "zbxtime.h"
#include "zbxjson.h"
#include "zbxshmem.h"

typedef struct
{
	zbx_uint64_t	mem_used;
	zbx_uint64_t	mem_total;
}
zbx_pb_mem_info_t;

typedef struct
{
	int		state;
	zbx_uint64_t	changes_num;
}
zbx_pb_state_info_t;

#define ZBX_PB_MODE_DISK	0
#define ZBX_PB_MODE_MEMORY	1
#define ZBX_PB_MODE_HYBRID	2

int	zbx_pb_parse_mode(const char *str, int *mode);
int	zbx_pb_create(int mode, zbx_uint64_t size, int age, int offline_buffer, char **error);
void	zbx_pb_init(void);
void	zbx_pb_destroy(void);

void	zbx_pb_update_state(int more);
void	zbx_pb_disable(void);
void	zbx_pb_flush(void);

int	zbx_pb_get_mem_info(zbx_pb_mem_info_t *info, char **error);
void	zbx_pb_get_state_info(zbx_pb_state_info_t *info);
void	zbx_pb_get_mem_stats(zbx_shmem_stats_t *stats);

/* discovery */

typedef struct zbx_pb_discovery_data zbx_pb_discovery_data_t;

zbx_pb_discovery_data_t	*zbx_pb_discovery_open(void);

void	zbx_pb_discovery_close(zbx_pb_discovery_data_t *data);

void	zbx_pb_discovery_write_service(zbx_pb_discovery_data_t *data, zbx_uint64_t druleid, zbx_uint64_t dcheckid,
		const char *ip, const char *dns, int port, int status, const char *value, int clock);

void	zbx_pb_discovery_write_host(zbx_pb_discovery_data_t *data, zbx_uint64_t druleid, const char *ip,
		const char *dns, int status, int clock, const char *error);

int	zbx_pb_discovery_get_rows(struct zbx_json *j, zbx_uint64_t *lastid, int *more);

void	zbx_pb_discovery_set_lastid(const zbx_uint64_t lastid);

/* auto registration */

void	zbx_pb_autoreg_write_host(const char *host, const char *ip, const char *dns, unsigned short port,
		unsigned int connection_type, const char *host_metadata, int flags, int clock);

int	zbx_pb_autoreg_get_rows(struct zbx_json *j, zbx_uint64_t *lastid, int *more);

void	zbx_pb_autoreg_set_lastid(const zbx_uint64_t lastid);


/* history */

typedef struct zbx_pb_history_data zbx_pb_history_data_t;

zbx_pb_history_data_t	*zbx_pb_history_open(void);

void	zbx_pb_history_close(zbx_pb_history_data_t *data);

void	zbx_pb_history_write_value(zbx_pb_history_data_t *data, zbx_uint64_t itemid, int state, const char *value,
		const zbx_timespec_t *ts, int flags, time_t now);

void	zbx_pb_history_write_meta_value(zbx_pb_history_data_t *data, zbx_uint64_t itemid, int state,
		const char *value, const zbx_timespec_t *ts, int flags, zbx_uint64_t lastlogsize, int mtime,
		int timestamp, int logeventid, int severity, const char *source, time_t now);

int	zbx_pb_history_get_rows(struct zbx_json *j, zbx_uint64_t *lastid, int *more);

void	zbx_pb_set_history_lastid(const zbx_uint64_t lastid);

zbx_uint64_t	zbx_pb_history_get_unsent_num(void);

#endif
