/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxautoreg.h"

ZBX_PTR_VECTOR_IMPL(autoreg_host_ptr, zbx_autoreg_host_t*)

int      zbx_autoreg_host_compare_func(const void *d1, const void *d2)
{
	const zbx_autoreg_host_t  *autoreg_host_1 = *(const zbx_autoreg_host_t **)d1;
	const zbx_autoreg_host_t  *autoreg_host_2 = *(const zbx_autoreg_host_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(autoreg_host_1->autoreg_hostid, autoreg_host_2->autoreg_hostid);

	return 0;
}
