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

#define ZBX_IPC_REPORTER_TEST_REPORT		1010
#define ZBX_IPC_REPORTER_TEST_REPORT_RESULT	1011

#define ZBX_IPC_REPORTER_SEND_REPORT		1020
#define ZBX_IPC_REPORTER_SEND_REPORT_RESULT	1021

void	report_destroy_params(zbx_vector_ptr_pair_t *params);

void	report_deserialize_test_request(const unsigned char *data, zbx_uint64_t *dashboardid, zbx_uint64_t *userid,
		zbx_uint64_t *viewer_userid, int *report_time, int *period, zbx_vector_ptr_pair_t *params);

zbx_uint32_t	report_serialize_response(unsigned char **data, int status, const char *error);

void	report_deserialize_response(const unsigned char *data, int *status, char **error);

zbx_uint32_t	report_serialize_send_request(unsigned char **data, const char *url, const char *cookie,
		const zbx_vector_str_t *emails, const zbx_vector_ptr_pair_t *params);

void	report_deserialize_send_request(const unsigned char *data, char **url, char **cookie, zbx_vector_str_t *emails,
		zbx_vector_ptr_pair_t *params);
#endif
