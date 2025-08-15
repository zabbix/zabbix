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

#ifndef ZABBIX_PP_PROTOCOL_H
#define ZABBIX_PP_PROTOCOL_H

#include "zbxpreproc.h"
#include "zbxipcservice.h"
#include "zbxtime.h"
#include "zbxalgo.h"
#include "zbxpreprocbase.h"
#include "zbxcachehistory.h"

#define ZBX_IPC_SERVICE_PREPROCESSING	"preprocessing"

/* start with 10000 to avoid conflicts with RTC messages */
#define ZBX_IPC_PREPROCESSOR_REQUEST			10001
#define ZBX_IPC_PREPROCESSOR_QUEUE			10002
#define ZBX_IPC_PREPROCESSOR_TEST_REQUEST		10003
#define ZBX_IPC_PREPROCESSOR_TEST_RESULT		10004
#define ZBX_IPC_PREPROCESSOR_DIAG_STATS			10005
#define ZBX_IPC_PREPROCESSOR_DIAG_STATS_RESULT		10006
#define ZBX_IPC_PREPROCESSOR_TOP_SEQUENCES		10007
#define ZBX_IPC_PREPROCESSOR_TOP_STATS_RESULT		10008
#define ZBX_IPC_PREPROCESSOR_USAGE_STATS		10009
#define ZBX_IPC_PREPROCESSOR_TOP_PEAK			10010
#define ZBX_IPC_PREPROCESSOR_SIZE			10011
#define ZBX_IPC_PREPROCESSOR_TOP_VALUES_NUM		10012
#define ZBX_IPC_PREPROCESSOR_TOP_VALUES_SZ		10013
#define ZBX_IPC_PREPROCESSOR_TOP_TIME_MS		10014
#define ZBX_IPC_PREPROCESSOR_TOP_TOTAL_MS		10015


ZBX_PTR_VECTOR_DECL(ipcmsg, zbx_ipc_message_t *)

/* packed field data description */
typedef struct
{
	const void	*value;	/* value to be packed */
	zbx_uint32_t	size;	/* size of a value (can be 0 for strings) */
	unsigned char	type;	/* field type */
}
zbx_packed_field_t;

void	zbx_preprocessor_unpack_test_request(zbx_pp_item_preproc_t *preproc, zbx_variant_t *value, zbx_timespec_t *ts,
		const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_test_result(unsigned char **data, const zbx_pp_result_t *results,
		int results_num, const zbx_pp_history_t *history);

void	zbx_preprocessor_unpack_test_result(zbx_vector_pp_result_ptr_t *results, zbx_pp_history_t *history,
		const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_diag_stats(unsigned char **data, zbx_uint64_t preproc_num,
		zbx_uint64_t pending_num, zbx_uint64_t finished_num, zbx_uint64_t sequences_num,
		zbx_uint64_t queued_num, zbx_uint64_t queued_sz, zbx_uint64_t direct_num, zbx_uint64_t direct_sz,
		zbx_uint64_t history_sz, zbx_uint64_t finished_peak_num, zbx_uint64_t pending_peak_num,
		zbx_uint64_t processed_num);

zbx_uint32_t	zbx_preprocessor_pack_values_stats(unsigned char **data, zbx_uint64_t queued_num,
		zbx_uint64_t queued_sz, zbx_uint64_t direct_num, zbx_uint64_t direct_sz, zbx_uint64_t enqueued_num);

void	zbx_preprocessor_unpack_diag_stats(zbx_uint64_t *preproc_num, zbx_uint64_t *pending_num,
		zbx_uint64_t *finished_num, zbx_uint64_t *sequences_num, zbx_uint64_t *queued_num,
		zbx_uint64_t *queued_sz, zbx_uint64_t *direct_num, zbx_uint64_t *direct_sz, zbx_uint64_t *history_sz,
		zbx_uint64_t *finished_peak_num, zbx_uint64_t *pending_peak_num, zbx_uint64_t *processed_num,
		const unsigned char *data);

void	zbx_preprocessor_unpack_values_stats(zbx_uint64_t *queued_num, zbx_uint64_t *queued_sz,
		zbx_uint64_t *direct_num, zbx_uint64_t *direct_sz, zbx_uint64_t *enqueued_num,
		const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_top_stats_request(unsigned char **data, int limit);

void	zbx_preprocessor_unpack_top_request(int *limit, const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_top_stats_result(unsigned char **data,
		zbx_vector_pp_top_stats_ptr_t *stats, int stats_num);

void	zbx_preprocessor_unpack_top_stats_result(zbx_vector_pp_top_stats_ptr_t *stats, const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_usage_stats(unsigned char **data, const zbx_vector_dbl_t *usage, int count);

zbx_uint32_t    zbx_preprocessor_deserialize_value(const unsigned char *data, zbx_uint64_t *itemid,
		unsigned char *value_type, unsigned char *item_flags, zbx_variant_t *value, zbx_timespec_t *ts,
		zbx_pp_value_opt_t *opt);

#endif
