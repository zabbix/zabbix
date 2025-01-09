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

#include "zbxalgo.h"
#include "zbxstr.h"

ZBX_VECTOR_IMPL(uint64, zbx_uint64_t)
ZBX_VECTOR_IMPL(uint32, zbx_uint32_t)
ZBX_VECTOR_IMPL(int32, int)
ZBX_PTR_VECTOR_IMPL(str, char *)
ZBX_PTR_VECTOR_IMPL(ptr, void *)
ZBX_VECTOR_IMPL(ptr_pair, zbx_ptr_pair_t)
ZBX_VECTOR_IMPL(uint64_pair, zbx_uint64_pair_t)
ZBX_VECTOR_IMPL(dbl, double)

ZBX_PTR_VECTOR_IMPL(tags_ptr, zbx_tag_t*)

void	zbx_ptr_free(void *data)
{
	zbx_free(data);
}

void	zbx_str_free(char *data)
{
	zbx_free(data);
}

void	zbx_free_tag(zbx_tag_t *tag)
{
	zbx_free(tag->tag);
	zbx_free(tag->value);
	zbx_free(tag);
}

int	zbx_compare_tags(const void *d1, const void *d2)
{
	const zbx_tag_t *tag1 = *(const zbx_tag_t * const *)d1;
	const zbx_tag_t *tag2 = *(const zbx_tag_t * const *)d2;

	return strcmp(tag1->tag, tag2->tag);
}

/******************************************************************************
 *                                                                            *
 * Purpose: comparison function to sort tags by tag/value.                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_compare_tags_natural(const void *d1, const void *d2)
{
	int	ret;

	const zbx_tag_t	*tag1 = *(const zbx_tag_t * const *)d1;
	const zbx_tag_t	*tag2 = *(const zbx_tag_t * const *)d2;

	if (0 == (ret = zbx_strcmp_natural(tag1->tag, tag2->tag)))
		ret = zbx_strcmp_natural(tag1->value, tag2->value);

	return ret;
}

int	zbx_compare_tags_and_values(const void *d1, const void *d2)
{
	int ret;

	const zbx_tag_t *tag1 = *(const zbx_tag_t * const *)d1;
	const zbx_tag_t *tag2 = *(const zbx_tag_t * const *)d2;

	if (0 == (ret = strcmp(tag1->tag, tag2->tag)))
		ret = strcmp(tag1->value, tag2->value);

	return ret;
}
