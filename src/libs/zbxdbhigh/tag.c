/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
#include "db.h"
#include "log.h"
#include "../zbxalgo/vectorimpl.h"

void	zbx_db_tag_free(zbx_db_tag_t *tag)
{
	zbx_free(tag->tag);
	zbx_free(tag->value);
	zbx_free(tag);
}

int	zbx_db_tag_compare_func(const void *d1, const void *d2)
{
	const zbx_db_tag_t	*tag1 = *(const zbx_db_tag_t **)d1;
	const zbx_db_tag_t	*tag2 = *(const zbx_db_tag_t **)d2;
	int			ret;

	if (0 != (ret = strcmp(tag1->tag, tag2->tag)))
		return ret;

	return strcmp(tag1->value, tag2->value);
}

ZBX_PTR_VECTOR_IMPL(db_tag_ptr, zbx_db_tag_t *);
