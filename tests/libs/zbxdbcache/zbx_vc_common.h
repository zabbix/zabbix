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

#ifndef ZBX_VC_COMMON_H
#define ZBX_VC_COMMON_H

#include "zbxalgo.h"
#include "zbxhistory.h"
#include "zbxmockdata.h"

typedef void	(*zbx_vc_test_add_values_setup_cb)(zbx_mock_handle_t *handle, zbx_vector_ptr_t *history, int *err,
		const char **data, int *ret_flush);
typedef void	(*zbx_vc_test_get_value_setup_cb)(zbx_mock_handle_t *handle, zbx_uint64_t *itemid,
		unsigned char *value_type, zbx_timespec_t *ts, int *err, zbx_vector_history_record_t *expected,
		zbx_vector_history_record_t *returned);
typedef void	(*zbx_vc_test_get_values_setup_cb)(zbx_mock_handle_t *handle, zbx_uint64_t *itemid,
		unsigned char *value_type, zbx_timespec_t *ts, int *err, zbx_vector_history_record_t *expected,
		zbx_vector_history_record_t *returned, int *seconds, int *count);

void	zbx_vc_test_add_values_setup(zbx_mock_handle_t *handle, zbx_vector_ptr_t *history, int *err, const char **data,
		int *ret_flush);
void	zbx_vc_test_get_value_setup(zbx_mock_handle_t *handle, zbx_uint64_t *itemid, unsigned char *value_type,
		zbx_timespec_t *ts, int *err, zbx_vector_history_record_t *expected,
		zbx_vector_history_record_t *returned);
void	zbx_vc_test_get_values_setup(zbx_mock_handle_t *handle, zbx_uint64_t *itemid, unsigned char *value_type,
		zbx_timespec_t *ts, int *err, zbx_vector_history_record_t *expected,
				zbx_vector_history_record_t *returned, int *seconds, int *count);

void	zbx_vc_common_test_func(
		void **state,
		zbx_vc_test_add_values_setup_cb add_values_cb,
		zbx_vc_test_get_value_setup_cb get_value_cb,
		zbx_vc_test_get_values_setup_cb get_values_cb,
		int test_check_result);
#endif
