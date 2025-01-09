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

#ifndef ZABBIX_PREPROC_PREPROC_SERVER_H
#define ZABBIX_PREPROC_PREPROC_SERVER_H

#include "zbxpreproc.h"
#include "zbxtime.h"
#include "zbxtypes.h"
#include "zbxcachehistory.h"

int	preproc_prepare_value_proxy(const zbx_variant_t *value, const zbx_pp_value_opt_t *value_opt);
void	preproc_flush_value_proxy(zbx_pp_manager_t *manager, zbx_uint64_t itemid, unsigned char value_type,
	unsigned char flags, zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_value_opt_t *value_opt);

#endif
