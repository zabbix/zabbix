/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#ifndef ZABBIX_REPORT_PROTOCOL_H
#define ZABBIX_REPORT_PROTOCOL_H

#include "common.h"
#include "zbxalgo.h"

#define ZBX_IPC_SERVICE_REPORTER	"reporter"

#define ZBX_IPC_REPORTER_REGISTER		1000

#define ZBX_IPC_REPORTER_TEST_REPORT		1001
#define ZBX_IPC_REPORTER_TEST_REPORT_RESPONSE	1001

void	report_destroy_params(zbx_vector_ptr_pair_t *params);

void	report_deserialize_test_request(const unsigned char *data, zbx_uint64_t *dashboardid, zbx_uint64_t *userid,
		zbx_uint64_t *writer_userid, zbx_vector_ptr_pair_t *params);

zbx_uint32_t	report_serialize_test_response(unsigned char **data, int status, const char *error);

#endif
