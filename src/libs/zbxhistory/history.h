/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_HISTORY_H
#define ZABBIX_HISTORY_H

#include "zbxalgo.h"
#include "zbxtypes.h"
#include "zbxhistory.h"

#define ZBX_HISTORY_TRAIT_DBL			__UINT64_C(0x00000001)
#define ZBX_HISTORY_TRAIT_STR			__UINT64_C(0x00000002)
#define ZBX_HISTORY_TRAIT_LOG			__UINT64_C(0x00000004)
#define ZBX_HISTORY_TRAIT_UINT			__UINT64_C(0x00000008)
#define ZBX_HISTORY_TRAIT_TEXT			__UINT64_C(0x00000010)
#define ZBX_HISTORY_TRAIT_BIN			__UINT64_C(0x00000020)
#define ZBX_HISTORY_TRAIT_JSON			__UINT64_C(0x00000040)

#define ZBX_HISTORY_TRAIT_REQUIRES_TRENDS	0x10000000
#define ZBX_HISTORY_TRAIT_REQUIRES_HOUSEKEEPING	0x20000000
#define ZBX_HISTORY_TRAIT_REQUIRES_PRECACHING	0x40000000

#define ZBX_HISTORY_TRAIT_DEFAULT_PROVIDER	__UINT64_C(0x0100000000000000)

#define ZBX_HISTORY_TRAIT_TYPES_NOBIN  \
		(ZBX_HISTORY_TRAIT_DBL | ZBX_HISTORY_TRAIT_STR | ZBX_HISTORY_TRAIT_LOG | \
		ZBX_HISTORY_TRAIT_UINT | ZBX_HISTORY_TRAIT_TEXT | ZBX_HISTORY_TRAIT_JSON)

#define ZBX_HISTORY_TRAIT_TYPES_ALL    \
		(ZBX_HISTORY_TRAIT_DBL | ZBX_HISTORY_TRAIT_STR | ZBX_HISTORY_TRAIT_LOG | \
		ZBX_HISTORY_TRAIT_UINT | ZBX_HISTORY_TRAIT_TEXT | ZBX_HISTORY_TRAIT_BIN | ZBX_HISTORY_TRAIT_JSON)


#define ZBX_HISTORY_FLUSH_ERR_BITS	8

#define ZBX_HISTORY_FLUSH_RET_SUCCEED		0x0
#define ZBX_HISTORY_FLUSH_RET_FAIL		0x1
#define ZBX_HISTORY_FLUSH_RET_FAIL_DUPL		0x2
#define ZBX_HISTORY_FLUSH_RET_FAIL_UNKNOWN	((UINT64_C(1) << (ZBX_HISTORY_FLUSH_ERR_BITS + 1)) - 1)

#define ZBX_HISTORY_FLUSH_SUCCEED		0
#define ZBX_HISTORY_FLUSH_FAIL			-1
#define ZBX_HISTORY_FLUSH_DUPL_REJECTED		-2
#define ZBX_HISTORY_FLUSH_UNKNOWN		-3

#define		ZBX_HISTORY_STORAGE_DOWN_DELAY	10
#define		ZBX_HISTORY_STORAGE_TIMEOUT_MS	(ZBX_HISTORY_STORAGE_DOWN_DELAY * 1000)

#define HISTORY_FLUSH_RET_FAIL_ALL	((UINT64_C(1) << (ITEM_VALUE_TYPE_COUNT + 1)) - 1)

typedef struct
{
	unsigned char	value_type;
	time_t		ttl;
}
zbx_history_provider_value_type_info_t;

ZBX_VECTOR_DECL(history_provider_value_type_info, zbx_history_provider_value_type_info_t)

typedef struct
{
	char		*database;
	char		*provider;

	zbx_uint32_t	current_version;
	zbx_uint32_t	min_version;
	zbx_uint32_t	max_version;
	zbx_uint32_t	min_supported_version;

	char		*friendly_current_version;
	char		*friendly_min_version;
	char		*friendly_max_version;
	char		*friendly_min_supported_version;

	zbx_vector_history_provider_value_type_info_t	value_types;
}
zbx_history_provider_info_t;

/* history provider API */

/******************************************************************************
 *                                                                            *
 * Purpose: close and clean up the history storage provider                   *
 *                                                                            *
 * Parameters:                                                                *
 *     data - [IN] history provider internal data                             *
 *                                                                            *
 ******************************************************************************/
typedef void (*zbx_history_provider_close_t)(void *data);

/******************************************************************************
 *                                                                            *
 * Purpose: write history data to the history storage provider                *
 *                                                                            *
 * Parameters:                                                                *
 *     data        - [IN] history provider internal data                      *
 *     value_type  - [IN] type of values being written                        *
 *     entries     - [IN] array of history entry pointers to write            *
 *     entries_num - [IN] number of entries in the array                      *
 *                                                                            *
 * Comments: This function can be called multiple times for one provider, with*
 *           different value_type for each call.                              *
 *           The values can be cached internally to be flushed later with     *
 *           flush method.                                                    *
 *                                                                            *
 ******************************************************************************/
typedef void (*zbx_history_provider_write_t)(void *data, unsigned char value_type,
		const zbx_history_entry_t * const *entries, int entries_num);

/******************************************************************************
 *                                                                            *
 * Purpose: flush cached history data to the storage provider                 *
 *                                                                            *
 * Parameters:                                                                *
 *     data - [IN] history provider internal data                             *
 *                                                                            *
 * Return value: A bitmask containing flush error statuses for each value     *
 *               type. Each value type uses ZBX_HISTORY_FLUSH_ERR_BITS (8)    *
 *               bits in the bitmask. See HISTORY_FLUSH_RET_* defines for     *
 *               possible status codes.                                       *
 *                                                                            *
 ******************************************************************************/
typedef zbx_uint64_t(*zbx_history_provider_flush_t)(void *data);

/******************************************************************************
 *                                                                            *
 * Purpose: fetch history data from the storage provider                      *
 *                                                                            *
 * Parameters:                                                                *
 *     data       - [IN] history provider internal data                       *
 *     itemid     - [IN] item identifier                                      *
 *     value_type - [IN] type of values to fetch                              *
 *     start      - [IN] start timestamp of the requested period              *
 *                       (greater than, optional)                             *
 *     end        - [IN] end timestamp of the requested period                *
 *                       (less or equal, optional)                            *
 *     count      - [IN] maximum number of values to retrieve                 *
 *     rows       - [OUT] pointer to store the retrieved history records      *
 *     error      - [OUT] error message in case of failure                    *
 *                                                                            *
 * Return value: SUCCEED - data fetched successfully                          *
 *               FAIL    - an error occurred during data retrieval            *
 *                                                                            *
 * Comments: This function retrieves history data for a specific item within  *
 *           the given time range. It should allocate memory for the values   *
 *           array and set the error message if the operation fails.          *
 *                                                                            *
 ******************************************************************************/
typedef int (*zbx_history_provider_fetch_t)(void *data, zbx_uint64_t itemid, unsigned char value_type, time_t start,
		time_t end, int count, zbx_history_record_t **rows, char **error);

/******************************************************************************
 *                                                                            *
 * Purpose: fetch batch of history data from the storage provider             *
 *                                                                            *
 * Parameters:                                                                *
 *     data       - [IN] history provider internal data                       *
 *     results    - [IN/OUT] vector of result batch containing itemids on     *
 *                      input and filled history records on output            *
 *     value_type - [IN] type of values to fetch                              *
 *     start      - [IN] start timestamp of the requested period              *
 *                       (greater than)                                       *
 *     limit      - [IN] maximum number of values to retrieve per item        *
 *     error      - [OUT] error message in case of failure                    *
 *                                                                            *
 * Return value: SUCCEED - data fetched successfully                          *
 *               FAIL    - an error occurred during data retrieval            *
 *                                                                            *
 * Comments: This function retrieves history data for a specific item within  *
 *           the given time range. It should allocate memory for the values   *
 *           array and set the error message if the operation fails.          *
 *                                                                            *
 ******************************************************************************/
typedef int (*zbx_history_provider_fetch_batch_t)(void *data, zbx_vector_item_history_t *results,
		unsigned char value_type, time_t start, int limit, char **error);

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve information about the history storage provider           *
 *                                                                            *
 * Parameters:                                                                *
 *     data  - [IN] internal ClickHouse data                                  *
 *     info  - [OUT] pointer to structure for storing provider information    *
 *     error - [OUT] error message in case of failure                         *
 *                                                                            *
 * Return value: SUCCEED - information retrieved successfully                 *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
typedef int (*zbx_history_provider_get_info_t)(void *data, zbx_history_provider_info_t *info, char **error);

typedef struct
{
	char	*name;
	char	*value;
}
zbx_history_option_t;

typedef struct
{
	zbx_history_provider_write_t		write;
	zbx_history_provider_flush_t		flush;
	zbx_history_provider_fetch_t		fetch;
	zbx_history_provider_fetch_batch_t	fetch_batch;
	zbx_history_provider_close_t		close;
	zbx_history_provider_get_info_t		get_info;
}
zbx_history_provider_impl_t;

typedef struct
{
	char				*name;
	void				*data;
	zbx_history_provider_impl_t	impl;
	zbx_uint64_t			traits;
}
zbx_history_provider_t;

zbx_uint64_t	history_make_flush_error(int ret, unsigned char value_type);

const char	*history_value_type_desc(unsigned char value_type);

#endif
