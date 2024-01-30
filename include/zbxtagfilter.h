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

#ifndef ZABBIX_TAGFILTER_H
#define ZABBIX_TAGFILTER_H

#include "zbxalgo.h"

typedef struct
{
	unsigned char	op;	/* condition operator */
	char		*tag;
	char		*value;
}
zbx_match_tag_t;

ZBX_PTR_VECTOR_DECL(match_tags, zbx_match_tag_t*)

int	zbx_match_tags(int eval_type, const zbx_vector_match_tags_t *match_tags, const zbx_vector_tags_t *entity_tags);
int	zbx_compare_match_tags(const void *d1, const void *d2);
void	zbx_match_tag_free(zbx_match_tag_t *tag);

#endif /* ZABBIX_TAGFILTER_H */
