/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef DBHIGH_TEST_H
#define DBHIGH_TEST_H

#include "zbxtypes.h"
#include "zbxmockdata.h"
#include "zbxdbhigh.h"

int	db_tags_and_values_compare(const void *d1, const void *d2);
void	tags_read(const char *path, zbx_vector_db_tag_ptr_t *tags);

#endif
