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

#ifndef ZABBIX_PP_PREPROC_H
#define ZABBIX_PP_PREPROC_H

#include "zbxalgo.h"
#include "zbxvariant.h"
#include "zbxtime.h"

/* one preprocessing step history */
typedef struct
{
	int		index;
	zbx_variant_t	value;
	zbx_timespec_t	ts;
}
zbx_pp_step_history_t;

ZBX_VECTOR_DECL(pp_step_history, zbx_pp_step_history_t);

/* item preprocessing history for preprocessing steps using previous values */
typedef struct
{
	zbx_vector_pp_step_history_t	step_history;
}
zbx_pp_history_t;

void	zbx_pp_history_init(zbx_pp_history_t *history);
void	zbx_pp_history_clear(zbx_pp_history_t *history);

typedef enum
{
	ZBX_PP_PROCESS_PARALLEL,
	ZBX_PP_PROCESS_SERIAL
}
zbx_pp_process_mode_t;

typedef struct
{
	unsigned char	type;
	unsigned char	error_handler;
	char		*params;
	char		*error_handler_params;
}
zbx_pp_step_t;

typedef struct
{
	zbx_uint32_t		refcount;

	int			steps_num;
	zbx_pp_step_t		*steps;

	int			dep_itemids_num;
	zbx_uint64_t		*dep_itemids;

	unsigned char		type;
	unsigned char		value_type;
	unsigned char		flags;
	zbx_pp_process_mode_t	mode;

	zbx_pp_history_t	*history;
	int			history_num;
}
zbx_pp_item_preproc_t;

#define ZBX_PP_VALUE_OPT_NONE		0x0000
#define ZBX_PP_VALUE_OPT_META		0x0001
#define ZBX_PP_VALUE_OPT_LOG		0x0002

typedef struct
{
	zbx_uint32_t	flags;
	int		mtime;
	int		timestamp;
	int		severity;
	int		logeventid;
	zbx_uint64_t	lastlogsize;
	char		*source;
}
zbx_pp_value_opt_t;

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_uint64_t		hostid;
	zbx_uint64_t		revision;

	zbx_pp_item_preproc_t	*preproc;
}
zbx_pp_item_t;


/* preprocessing step execution result */
typedef struct
{
	zbx_variant_t	value;
	unsigned char	action;
}
zbx_pp_result_t;

ZBX_PTR_VECTOR_DECL(pp_result, zbx_pp_result_t *);

void	zbx_pp_result_free(zbx_pp_result_t *result);


zbx_pp_item_preproc_t	*zbx_pp_item_preproc_create(unsigned char type, unsigned char value_type, unsigned char flags);
void	zbx_pp_item_preproc_release(zbx_pp_item_preproc_t *preproc);
int	zbx_pp_preproc_has_history(unsigned char type);

#endif
