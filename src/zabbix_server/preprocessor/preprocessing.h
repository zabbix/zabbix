/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#ifndef ZABBIX_PREPROCESSING_H
#define ZABBIX_PREPROCESSING_H

#include "common.h"
#include "module.h"
#include "dbcache.h"

#define ZBX_IPC_SERVICE_PREPROCESSING	"preprocessing"

#define ZBX_IPC_PREPROCESSOR_WORKER	1
#define ZBX_IPC_PREPROCESSOR_REQUEST	2
#define ZBX_IPC_PREPROCESSOR_RESULT	3
#define ZBX_IPC_PREPROCESSOR_QUEUE	4

/* item value data used in preprocessing manager */
typedef struct
{
	zbx_uint64_t	itemid;		/* item id */
	AGENT_RESULT	*result;	/* item value (if any) */
	zbx_timespec_t	*ts;		/* timestamp of a value */
	char		*error;		/* error message (if any) */
	unsigned char	item_flags;	/* item flags */
	unsigned char	state;		/* item state */
}
zbx_preproc_item_value_t;

zbx_uint32_t	zbx_preprocessor_pack_task(unsigned char **data, zbx_uint64_t itemid, unsigned char value_type,
		zbx_timespec_t *ts, zbx_variant_t *value, zbx_item_history_value_t *history_value,
		const zbx_preproc_op_t *steps, int steps_num);
zbx_uint32_t	zbx_preprocessor_pack_result(unsigned char **data, zbx_variant_t *value,
		zbx_item_history_value_t *history_value, char *error);

zbx_uint32_t	zbx_preprocessor_unpack_value(zbx_preproc_item_value_t *value, unsigned char *data);
void	zbx_preprocessor_unpack_task(zbx_uint64_t *itemid, unsigned char *value_type, zbx_timespec_t **ts,
		zbx_variant_t *value, zbx_item_history_value_t **history_value, zbx_preproc_op_t **steps,
		int *steps_num, const unsigned char *data);
void	zbx_preprocessor_unpack_result(zbx_variant_t *value, zbx_item_history_value_t **history_value,
		char **error, const unsigned char *data);

#endif /* ZABBIX_PREPROCESSING_H */
