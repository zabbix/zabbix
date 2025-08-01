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

#ifndef ZABBIX_ZBXHISTORY_PROVIDER_H
#define ZABBIX_ZBXHISTORY_PROVIDER_H

#include "zbxvariant.h"
#include "zbxjson.h"
#include "zbxtime.h"
#include "zbxtypes.h"

#define ZBX_DBVERSION_UNDEFINED			0

#define ZBX_HISTORY_TRAIT_DBL			__UINT64_C(0x00000001)
#define ZBX_HISTORY_TRAIT_STR			__UINT64_C(0x00000002)
#define ZBX_HISTORY_TRAIT_LOG			__UINT64_C(0x00000004)
#define ZBX_HISTORY_TRAIT_UINT			__UINT64_C(0x00000008)
#define ZBX_HISTORY_TRAIT_TEXT			__UINT64_C(0x00000010)
#define ZBX_HISTORY_TRAIT_BIN			__UINT64_C(0x00000020)

#define ZBX_HISTORY_TRAIT_REQUIRES_TRENDS	0x10000000
#define ZBX_HISTORY_TRAIT_REQUIRES_HOUSEKEEPING	0x20000000

/* reserved bits */
#define ZBX_HISTORY_TRAIT_MAX_BITS		48
#define ZBX_HISTORY_TRAIT_BITMASK	((~__UINT64_C(0)) >> (64 - ZBX_HISTORY_TRAIT_MAX_BITS))

#define ZBX_HISTORY_TRAIT_DEFAULT_PROVIDER	__UINT64_C(0x0100000000000000)

#define ZBX_HISTORY_TRAIT_TYPES_NOBIN  \
		(ZBX_HISTORY_TRAIT_DBL | ZBX_HISTORY_TRAIT_STR | ZBX_HISTORY_TRAIT_LOG | \
		ZBX_HISTORY_TRAIT_UINT | ZBX_HISTORY_TRAIT_TEXT)

#define ZBX_HISTORY_TRAIT_TYPES_ALL    \
		(ZBX_HISTORY_TRAIT_DBL | ZBX_HISTORY_TRAIT_STR | ZBX_HISTORY_TRAIT_LOG | \
		ZBX_HISTORY_TRAIT_UINT | ZBX_HISTORY_TRAIT_TEXT | ZBX_HISTORY_TRAIT_BIN)


#define ZBX_HISTORY_FLUSH_ERR_BITS	8

#define ZBX_HISTORY_FLUSH_RET_SUCCEED		0x0
#define ZBX_HISTORY_FLUSH_RET_FAIL		0x1
#define ZBX_HISTORY_FLUSH_RET_FAIL_DUPL		0x2
#define ZBX_HISTORY_FLUSH_RET_FAIL_UNKNOWN	((UINT64_C(1) << (ZBX_HISTORY_FLUSH_ERR_BITS + 1)) - 1)

#define ZBX_HISTORY_OPTION_VALUE_TYPES	"types"

#define ZBX_HISTORY_FLUSH_SUCCEED		0
#define ZBX_HISTORY_FLUSH_FAIL			-1
#define ZBX_HISTORY_FLUSH_DUPL_REJECTED		-2
#define ZBX_HISTORY_FLUSH_UNKNOWN		-3

typedef struct
{
	char	*name;
	char	*value;
}
zbx_history_option_t;

typedef struct
{
	int	timestamp;
	int	logeventid;
	int	severity;
	char	*source;
	char	*value;
}
zbx_log_value_t;

typedef union
{
	double		dbl;
	zbx_uint64_t	ui64;
	char		*str;
	char		*err;
	zbx_log_value_t	*log;
}
zbx_history_value_t;

/* the item history value */
typedef struct
{
	zbx_timespec_t		timestamp;
	zbx_history_value_t	value;
}
zbx_history_record_t;

ZBX_VECTOR_DECL(history_record, zbx_history_record_t)

typedef struct
{
	zbx_uint64_t		itemid;
	unsigned char		value_type;
	zbx_history_value_t	value;
	zbx_timespec_t		ts;
	int			ttl;
}
zbx_history_entry_t;

typedef struct
{
	char		*database;

	zbx_uint32_t	current_version;
	zbx_uint32_t	min_version;
	zbx_uint32_t	max_version;
	zbx_uint32_t	min_supported_version;

	char		*friendly_current_version;
	char		*friendly_min_version;
	char		*friendly_max_version;
	char		*friendly_min_supported_version;

	zbx_uint64_t	flags;
}
zbx_history_provider_info_t;

/******************************************************************************
 *                                                                            *
 * Purpose: open and initialize the history storage provider                  *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] array of configuration options                      *
 *     options_num - [IN] number of elements in the options array             *
 *     error       - [OUT] error message                                      *
 *     errsize     - [IN] size of error buffer                                *
 *                                                                            *
 * Return value: history provider internal data handle or NULL on error       *
 *                                                                            *
 * Comments: This function is called to open and initialize the history       *
 *           storage provider. It should allocate and return a pointer to     *
 *           the provider-specific data structure, which will be passed to    *
 *           other provider functions. If an error occurs during              *
 *           initialization, the function should return NULL and set an error *
 *           message.                                                         *
 *                                                                            *
 ******************************************************************************/
void	*zbx_history_provider_open(const zbx_history_option_t *options, int options_num, char *error, size_t errsize);

/******************************************************************************
 *                                                                            *
 * Purpose: close the history storage provider handle                         *
 *                                                                            *
 * Parameters:                                                                *
 *     data - [IN] history provider internal data                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_provider_close(void *data);

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
void	zbx_history_provider_write(void *data, unsigned char value_type, const zbx_history_entry_t * const *entries,
		int entries_num);

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
uint64_t	zbx_history_provider_flush(void *data);

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
 *     values     - [OUT] pointer to store the retrieved history records      *
 *     error      - [OUT] error message in case of failure                    *
 *     errsize    - [IN] size of error buffer                                 *
 *                                                                            *
 * Return value:  0 - data fetched successfully                               *
 *               -1 - an error occurred during data retrieval                 *
 *                                                                            *
 * Comments: This function retrieves history data for a specific item within  *
 *           the given time range. It should allocate memory for the values   *
 *           array and set the error message if the operation fails.          *
 *                                                                            *
 ******************************************************************************/
int	zbx_history_provider_fetch(void *data, zbx_uint64_t itemid, unsigned char value_type, time_t start,
		time_t end, int count, zbx_history_record_t **values, char *error, size_t errsize);

/******************************************************************************
 *                                                                            *
 * Purpose: free memory allocated to store fetch result                       *
 *                                                                            *
 * Parameters:                                                                *
 *     data       - [IN] history provider internal data                       *
 *     values     - [IN] array of history records to free                     *
 *     values_num - [IN] number of values in the array                        *
 *     value_type - [IN] type of values being freed                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_provider_free_result(void *data, zbx_history_record_t *values, int values_num,
		unsigned char value_type);

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve the traits of the history storage provider               *
 *                                                                            *
 * Return value: A bitmask of supported features and capabilities, (see       *
 *               ZBX_HISTORY_TRAIT_* constants)                               *
 *                                                                            *
 ******************************************************************************/
uint64_t	zbx_history_provider_get_traits(void);

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve information about the history storage provider           *
 *                                                                            *
 * Parameters:                                                                *
 *     data    - [IN] history provider internal data                          *
 *     error   - [OUT] error message                                          *
 *     errsize - [IN] size of error buffer                                    *
 *                                                                            *
 * Return value: pointer to zbx_history_provider_info_t structure or NULL on  *
 *               error                                                        *
 *                                                                            *
 ******************************************************************************/
zbx_history_provider_info_t	*zbx_history_provider_get_info(void *data, char *error, size_t errsize);

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated for history provider info                *
 *                                                                            *
 * Parameters: info - [IN/OUT] pointer to history provider info structure     *
 *                                                                            *
 ******************************************************************************/
void	zbx_history_provider_free_info(zbx_history_provider_info_t *info);

#define		ZBX_HISTORY_STORAGE_DOWN	10000

#define HISTORY_FLUSH_RET_FAIL_ALL	((UINT64_C(1) << (ITEM_VALUE_TYPE_COUNT + 1)) - 1)

/******************************************************************************
 *                                                                            *
 * Purpose: open and initialize the history storage provider                  *
 *                                                                            *
 * Parameters:                                                                *
 *     options     - [IN] array of configuration options                      *
 *     options_num - [IN] number of elements in the options array             *
 *     error       - [OUT] error message                                      *
 *                                                                            *
 * Return value: history provider internal data handle or NULL on error       *
 *                                                                            *
 * Comments: This function is called to open and initialize the history       *
 *           storage provider. It should allocate and return a pointer to     *
 *           the provider-specific data structure, which will be passed to    *
 *           other provider functions. If an error occurs during              *
 *           initialization, the function should return NULL and set an error *
 *           message.                                                         *
 *                                                                            *
 ******************************************************************************/
typedef void *(*zbx_history_provider_open_t)(const zbx_history_option_t *options, int options_num, char **error);

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
 *     values     - [OUT] pointer to store the retrieved history records      *
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
		time_t end, int count, zbx_history_record_t **values, char **error);

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve the traits of the history storage provider               *
 *                                                                            *
 * Return value: A bitmask of supported features and capabilities, (see       *
 *               ZBX_HISTORY_TRAIT_* constants)                               *
 *                                                                            *
 ******************************************************************************/
typedef zbx_uint64_t (*zbx_history_provider_get_traits_t)(void);

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve information about the history storage provider           *
 *                                                                            *
 * Parameters:                                                                *
 *     info  - [OUT] pointer to structure for storing provider information    *
 *     error - [OUT] error message in case of failure                         *
 *                                                                            *
 * Return value: SUCCEED - information retrieved successfully                 *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
typedef int (*zbx_history_provider_get_info_t)(void *data, zbx_history_provider_info_t *info, char **error);

#endif
