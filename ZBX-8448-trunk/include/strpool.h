/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_STRPOOL_H
#define ZABBIX_STRPOOL_H

#include "mutexs.h"
#include "zbxalgo.h"
#include "memalloc.h"

typedef struct
{
	zbx_mem_info_t	*mem_info;
	zbx_hashset_t	*hashset;
}
zbx_strpool_t;

void		zbx_strpool_create(size_t size);
void		zbx_strpool_destroy();

const char	*zbx_strpool_intern(const char *str);
const char	*zbx_strpool_acquire(const char *str);
void		zbx_strpool_release(const char *str);

void		zbx_strpool_clear();

const zbx_strpool_t	*zbx_strpool_info();

#endif
