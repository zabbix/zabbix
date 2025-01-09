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

#ifndef DBHIGH_TEST_H
#define DBHIGH_TEST_H

#include "zbxtypes.h"
#include "zbxmockdata.h"
#include "zbxdbhigh.h"

int	db_tags_and_values_compare(const void *d1, const void *d2);
void	tags_read(const char *path, zbx_vector_db_tag_ptr_t *tags);

#endif
