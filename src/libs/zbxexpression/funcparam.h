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

#ifndef ZABBIX_FUNCPARAM_H
#define ZABBIX_FUNCPARAM_H

#include "evalfunc.h"

int	get_function_parameter_uint64(const char *parameters, int Nparam, zbx_uint64_t *value);
int	get_function_parameter_float(const char *parameters, int Nparam, unsigned char flags, double *value);
int	get_function_parameter_str(const char *parameters, int Nparam, char **value);
int	get_function_parameter_hist_range(int from, const char *parameters, int Nparam, int *value,
		zbx_value_type_t *type, int *timeshift);
int	get_function_parameter_period(const char *parameters, int Nparam, int *value, zbx_value_type_t *type);
#endif
