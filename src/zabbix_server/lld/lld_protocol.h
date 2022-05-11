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

#ifndef ZABBIX_LLD_PROTOCOL_H
#define ZABBIX_LLD_PROTOCOL_H

#include "common.h"
#include "lld_manager.h"

#define ZBX_IPC_SERVICE_LLD	"lld"

/* poller -> manager */
#define ZBX_IPC_LLD_REGISTER		1000
#define ZBX_IPC_LLD_DONE		1001

/* manager -> poller */
#define ZBX_IPC_LLD_TASK		1100

/* manager -> poller */
#define ZBX_IPC_LLD_REQUEST		1200

/* poller -> poller */
#define ZBX_IPC_LLD_QUEUE		1300

/* process -> manager */
#define ZBX_IPC_LLD_DIAG_STATS		1400

/* manager -> process */
#define ZBX_IPC_LLD_DIAG_STATS_RESULT	1401

/* process -> manager */
#define ZBX_IPC_LLD_TOP_ITEMS		1402

/* manager -> process */
#define ZBX_IPC_LLD_TOP_ITEMS_RESULT	1403

zbx_uint32_t	zbx_lld_serialize_item_value(unsigned char **data, zbx_uint64_t itemid, zbx_uint64_t hostid,
		const char *value, const zbx_timespec_t *ts, unsigned char meta, zbx_uint64_t lastlogsize, int mtime,
		const char *error);

void	zbx_lld_deserialize_item_value(const unsigned char *data, zbx_uint64_t *itemid, zbx_uint64_t *hostid,
		char **value, zbx_timespec_t *ts, unsigned char *meta, zbx_uint64_t *lastlogsize, int *mtime,
		char **error);

zbx_uint32_t	zbx_lld_serialize_diag_stats(unsigned char **data, zbx_uint64_t items_num, zbx_uint64_t values_num);

void	zbx_lld_deserialize_top_items_request(const unsigned char *data, int *limit);

zbx_uint32_t	zbx_lld_serialize_top_items_result(unsigned char **data, const zbx_lld_rule_info_t **rule_infos,
		int num);

#endif
