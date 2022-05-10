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

#ifndef ZABBIX_EXPRESSION_EVALUATE_H
#define ZABBIX_EXPRESSION_EVALUATE_H

#include "zbxalgo.h"

int	zbx_evaluate(double *value, const char *expression, char *error, size_t max_error_len,
		zbx_vector_ptr_t *unknown_msgs);
int	zbx_evaluate_unknown(const char *expression, double *value, char *error, size_t max_error_len);
double	zbx_evaluate_string_to_double(const char *in);

#endif /* ZABBIX_EXPRESSION_EVALUATE_H */
