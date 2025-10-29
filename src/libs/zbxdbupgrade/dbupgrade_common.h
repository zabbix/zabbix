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

#ifndef ZABBIX_DBUPGRADE_COMMON_H
#define ZABBIX_DBUPGRADE_COMMON_H

#include "zbxalgo.h"

int	delete_problems_with_nonexistent_object(void);
#ifndef HAVE_SQLITE3
int	create_problem_3_index(void);
int	drop_c_problem_2_index(void);
#endif

int	permission_hgsets_add(zbx_vector_uint64_t *ids, zbx_vector_uint64_t *hgsetids);
int	permission_ugsets_add(zbx_vector_uint64_t *ids);
#endif
