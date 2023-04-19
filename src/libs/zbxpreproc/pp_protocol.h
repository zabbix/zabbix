/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_PP_PROTOCOL_H
#define ZABBIX_PP_PROTOCOL_H

#include "zbxpreproc.h"
#include "zbxipcservice.h"
#include "zbxtime.h"
#include "zbxalgo.h"

#define ZBX_IPC_SERVICE_PREPROCESSING	"preprocessing"

/* start with 10000 to avoid conflicts with RTC messages */
#define ZBX_IPC_PREPROCESSOR_REQUEST			10001
#define ZBX_IPC_PREPROCESSOR_QUEUE			10002
#define ZBX_IPC_PREPROCESSOR_TEST_REQUEST		10003
#define ZBX_IPC_PREPROCESSOR_TEST_RESULT		10004
#define ZBX_IPC_PREPROCESSOR_DIAG_STATS			10005
#define ZBX_IPC_PREPROCESSOR_DIAG_STATS_RESULT		10006
#define ZBX_IPC_PREPROCESSOR_TOP_SEQUENCES		10007
#define ZBX_IPC_PREPROCESSOR_TOP_SEQUENCES_RESULT	10008
#define ZBX_IPC_PREPROCESSOR_USAGE_STATS		10009

/* item value data used in preprocessing manager */
typedef struct
{
	zbx_uint64_t		itemid;		 /* item id */
	zbx_uint64_t		hostid;		 /* host id */
	unsigned char		item_value_type; /* item value type */
	AGENT_RESULT		*result;	 /* item value (if any) */
	zbx_timespec_t		*ts;		 /* timestamp of a value */
	char			*error;		 /* error message (if any) */
	unsigned char		item_flags;	 /* item flags */
	unsigned char		state;		 /* item state */
}
zbx_preproc_item_value_t;

ZBX_PTR_VECTOR_DECL(ipcmsg, zbx_ipc_message_t *)

/* packed field data description */
typedef struct
{
	const void	*value;	/* value to be packed */
	zbx_uint32_t	size;	/* size of a value (can be 0 for strings) */
	unsigned char	type;	/* field type */
}
zbx_packed_field_t;

zbx_uint32_t	zbx_preprocessor_unpack_value(zbx_preproc_item_value_t *value, unsigned char *data);

void	zbx_preprocessor_unpack_test_request(zbx_pp_item_preproc_t *preproc, zbx_variant_t *value, zbx_timespec_t *ts,
		const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_test_result(unsigned char **data, const zbx_pp_result_t *results,
		int results_num, const zbx_pp_history_t *history);

void	zbx_preprocessor_unpack_test_result(zbx_vector_pp_result_ptr_t *results, zbx_pp_history_t *history,
		const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_diag_stats(unsigned char **data, zbx_uint64_t preproc_num,
		zbx_uint64_t pending_num, zbx_uint64_t finished_num, zbx_uint64_t sequences_num);

void	zbx_preprocessor_unpack_diag_stats(zbx_uint64_t *preproc_num, zbx_uint64_t *pending_num,
		zbx_uint64_t *finished_num, zbx_uint64_t *sequences_num, const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_top_sequences_request(unsigned char **data, int limit);

void	zbx_preprocessor_unpack_top_request(int *limit, const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_top_sequences_result(unsigned char **data,
		zbx_vector_pp_sequence_stats_ptr_t *sequences, int sequences_num);

void	zbx_preprocessor_unpack_top_sequences_result(zbx_vector_pp_sequence_stats_ptr_t *sequences,
		const unsigned char *data);

zbx_uint32_t	zbx_preprocessor_pack_usage_stats(unsigned char **data, const zbx_vector_dbl_t *usage, int count);

#endif
