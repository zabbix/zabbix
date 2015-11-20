/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include "common.h"
#include "log.h"

#include "zbxalgo.h"
#include "vectorimpl.h"

ZBX_VECTOR_IMPL(uint64, zbx_uint64_t);
ZBX_VECTOR_IMPL(str, char *);
ZBX_VECTOR_IMPL(ptr, void *);
ZBX_VECTOR_IMPL(ptr_pair, zbx_ptr_pair_t);
ZBX_VECTOR_IMPL(uint64_pair, zbx_uint64_pair_t);

void	zbx_vector_str_clean(zbx_vector_str_t *vector)
{
	int	i;

	for (i = 0; i < vector->values_num; i++)
		zbx_free(vector->values[i]);

	vector->values_num = 0;
}

void	zbx_vector_ptr_clean(zbx_vector_ptr_t *vector, zbx_mem_free_func_t free_func)
{
	int	i;

	for (i = 0; i < vector->values_num; i++)
		free_func(vector->values[i]);

	memset(vector->values, 0, sizeof(*vector->values) * vector->values_num);
	vector->values_num = 0;
}

void	zbx_ptr_free(void *ptr)
{
	zbx_free(ptr);
}
