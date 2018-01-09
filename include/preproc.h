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

#ifndef ZABBIX_PREPROC_H
#define ZABBIX_PREPROC_H

#include "common.h"
#include "module.h"

/* the following functions are implemened differently for server and proxy */

void	zbx_preprocess_item_value(zbx_uint64_t itemid, unsigned char item_flags, AGENT_RESULT *result,
		zbx_timespec_t *ts, unsigned char state, char *error);
void	zbx_preprocessor_flush(void);
zbx_uint64_t	zbx_preprocessor_get_queue_size(void);

#endif /* ZABBIX_PREPROC_H */
