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

ZBX_PTR_VECTOR_DECL(match_tags_ptr, zbx_match_tag_t*)

int	zbx_match_tags(int eval_type, const zbx_vector_match_tags_ptr_t *match_tags,
		const zbx_vector_tags_ptr_t *entity_tags);
int	zbx_compare_match_tags(const void *d1, const void *d2);
void	zbx_match_tag_free(zbx_match_tag_t *tag);

#endif /* ZABBIX_TAGFILTER_H */
