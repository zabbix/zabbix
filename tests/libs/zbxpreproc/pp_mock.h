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

#ifndef ZABBIX_ZBXPREPROC_PP_MOCK_H
#define ZABBIX_ZBXPREPROC_PP_MOCK_H

#include "zbxtime.h"

int	str_to_preproc_type(const char *str);

void	mock_pp_read_variant(zbx_mock_handle_t handle, zbx_variant_t *value);
void	mock_pp_read_value(zbx_mock_handle_t handle, unsigned char *value_type, zbx_variant_t *value,
		zbx_timespec_t *ts);
void	mock_pp_read_step(zbx_mock_handle_t hop, zbx_pp_step_t *step);

#endif
