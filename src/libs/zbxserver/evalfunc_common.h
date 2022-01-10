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

#ifndef ZABBIX_EVALFUNC_COMMON_H
#define ZABBIX_EVALFUNC_COMMON_H

#include "zbxtypes.h"

typedef enum
{
	ZBX_VALUE_NONE,
	ZBX_VALUE_SECONDS,
	ZBX_VALUE_NVALUES
}
zbx_value_type_t;

const char	*zbx_type_string(zbx_value_type_t type);
int	get_function_parameter_uint64(const char *parameters, int Nparam, zbx_uint64_t *value);
int	get_function_parameter_float(const char *parameters, int Nparam, unsigned char flags, double *value);
int	get_function_parameter_str(const char *parameters, int Nparam, char **value);
int	get_function_parameter_hist_range(int from, const char *parameters, int Nparam, int *value,
		zbx_value_type_t *type, int *timeshift);
#endif
