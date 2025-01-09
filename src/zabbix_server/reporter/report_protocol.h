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

#ifndef ZABBIX_REPORT_PROTOCOL_H
#define ZABBIX_REPORT_PROTOCOL_H

#include "zbxalerter.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"

#define ZBX_REPORT_PARAM_SUBJECT	"subject"
#define ZBX_REPORT_PARAM_BODY		"body"

#define ZBX_IPC_SERVICE_REPORTER	"reporter"

/* manager -> writer */
#define ZBX_IPC_REPORTER_BEGIN_REPORT		1000
#define ZBX_IPC_REPORTER_SEND_REPORT		1001
#define ZBX_IPC_REPORTER_END_REPORT		1002

/* writer -> manager */
#define ZBX_IPC_REPORTER_REGISTER		1100
#define ZBX_IPC_REPORTER_RESULT			1101

/* process -> manager */
#define ZBX_IPC_REPORTER_TEST			1010

/* manager -> process */
#define ZBX_IPC_REPORTER_TEST_RESULT		1011

void	report_clear_params(zbx_vector_ptr_pair_t *params);
void	report_destroy_params(zbx_vector_ptr_pair_t *params);

void	report_deserialize_test_report(const unsigned char *data, char **name, zbx_uint64_t *dashboardid,
		zbx_uint64_t *userid, zbx_uint64_t *viewer_userid, int *report_time, unsigned char *period,
		zbx_vector_ptr_pair_t *params);

zbx_uint32_t	report_serialize_response(unsigned char **data, int status, const char *error,
		const zbx_vector_alerter_dispatch_result_t *results);
void	report_deserialize_response(const unsigned char *data, int *status, char **error,
		zbx_vector_alerter_dispatch_result_t *results);

zbx_uint32_t	report_serialize_begin_report(unsigned char **data, const char *name, const char *url,
		const char *cookie, const zbx_vector_ptr_pair_t *params);
void	report_deserialize_begin_report(const unsigned char *data, char **name, char **url, char **cookie,
		zbx_vector_ptr_pair_t *params);

zbx_uint32_t	report_serialize_send_report(unsigned char **data, const zbx_db_mediatype *mt,
		const zbx_vector_str_t *emails);
void	report_deserialize_send_report(const unsigned char *data, zbx_db_mediatype *mt, zbx_vector_str_t *sendtos);
#endif
