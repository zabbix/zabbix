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

#ifndef ZABBIX_ANOMALYSTL_H
#define ZABBIX_ANOMALYSTL_H

#include "zbxvariant.h"
#include "zbxhistory.h"

/* default values which can be used in zbx_STL() arguments */
#define STL_DEF_DEVIATIONS	3
#define S_DEGREE_DEF		0
#define S_WINDOW_DEF		0
#define T_WINDOW_DEF		ZBX_INFINITY
#define T_DEGREE_DEF		1
#define L_WINDOW_DEF		-1
#define L_DEGREE_DEF		-1
#define S_JUMP_DEF		-1
#define T_JUMP_DEF		-1
#define L_JUMP_DEF		-1
#define ROBUST_DEF		0
#define INNER_DEF		-1
#define OUTER_DEF		-1

int	zbx_STL(const zbx_vector_history_record_t *values_in, int freq, int is_robust, int s_window, int s_degree,
		double t_window, int t_degree, int l_window, int l_degree, int nsjump, int ntjump, int nljump,
		int inner, int outer, zbx_vector_history_record_t *trend, zbx_vector_history_record_t *seasonal,
		zbx_vector_history_record_t *remainder, char **error);

int	zbx_get_percentage_of_deviations_in_stl_remainder(const zbx_vector_history_record_t *remainder,
		double deviations_count, const char* devalg, int detect_period_start, int detect_period_end,
		double *result, char **error);
#endif
