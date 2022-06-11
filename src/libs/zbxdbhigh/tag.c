/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxdbhigh.h"

ZBX_PTR_VECTOR_IMPL(db_tag_ptr, zbx_db_tag_t *)

zbx_db_tag_t	*zbx_db_tag_create(const char *tag_tag, const char *tag_value)
{
	zbx_db_tag_t	*tag;

	tag = (zbx_db_tag_t *)zbx_malloc(NULL, sizeof(zbx_db_tag_t));
	tag->tagid = 0;
	tag->flags = ZBX_FLAG_DB_TAG_UNSET;
	tag->tag = zbx_strdup(NULL, tag_tag);
	tag->value = zbx_strdup(NULL, tag_value);
	tag->tag_orig = NULL;
	tag->value_orig = NULL;

	/* Internally all tags are treated as 'automatic' by default, unless they have */
	/* explicit 'automatic' setting in database                                    */
	tag->automatic = ZBX_DB_TAG_AUTOMATIC;

	return tag;
}

void	zbx_db_tag_free(zbx_db_tag_t *tag)
{
	if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_TAG))
		zbx_free(tag->tag_orig);

	if (0 != (tag->flags & ZBX_FLAG_DB_TAG_UPDATE_VALUE))
		zbx_free(tag->value_orig);

	zbx_free(tag->tag);
	zbx_free(tag->value);
	zbx_free(tag);
}

int	zbx_db_tag_compare_func(const void *d1, const void *d2)
{
	const zbx_db_tag_t	* const tag1 = *(const zbx_db_tag_t * const *)d1;
	const zbx_db_tag_t	* const tag2 = *(const zbx_db_tag_t * const *)d2;
	int			ret;

	if (0 != (ret = strcmp(tag1->tag, tag2->tag)))
		return ret;

	return strcmp(tag1->value, tag2->value);
}

int	zbx_db_tag_compare_func_template(const void *d1, const void *d2)
{
	const zbx_db_tag_t	* const it1 = *(const zbx_db_tag_t * const *)d1;
	const zbx_db_tag_t	* const it2 = *(const zbx_db_tag_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(it1->tag, it2->tag);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge new tags into existing                                      *
 *                                                                            *
 * Parameters: dst - [IN/OUT] vector of existing tags                         *
 *             src - [IN/OUT] vector or new tags                              *
 *                                                                            *
 * Comments: The tags are merged using the following logic:                   *
 *           1) tags with matching name+value are left as it is               *
 *           2) tags with matching names will have their values updated       *
 *           3) tags without matches will have:                               *
 *              a) their name and value updated if there are new tags left    *
 *              b) flagged to be removed otherwise                            *
 *           4) all leftover new tags will be created                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_tag_merge(zbx_vector_db_tag_ptr_t *dst, zbx_vector_db_tag_ptr_t *src)
{
	int	i, j;

	/* perform exact tag + value match */
	for (i = 0; i < dst->values_num; i++)
	{
		for (j = 0; j < src->values_num; j++)
		{
			if (0 == strcmp(dst->values[i]->tag, src->values[j]->tag) &&
					0 == strcmp(dst->values[i]->value, src->values[j]->value))
			{
				break;
			}
		}

		if (j != src->values_num)
		{
			zbx_db_tag_free(src->values[j]);
			zbx_vector_db_tag_ptr_remove_noorder(src, j);
			continue;
		}

		dst->values[i]->flags = ZBX_FLAG_DB_TAG_REMOVE;
	}

	if (0 == src->values_num)
		return;

	/* perform tag match */
	for (i = 0; i < dst->values_num; i++)
	{
		if (ZBX_FLAG_DB_TAG_REMOVE != dst->values[i]->flags)
			continue;

		for (j = 0; j < src->values_num; j++)
		{
			if (0 == strcmp(dst->values[i]->tag, src->values[j]->tag))
				break;
		}

		if (j != src->values_num)
		{
			dst->values[i]->value_orig = dst->values[i]->value;
			dst->values[i]->value = src->values[j]->value;
			dst->values[i]->flags = ZBX_FLAG_DB_TAG_UPDATE_VALUE;
			src->values[j]->value = NULL;
			zbx_db_tag_free(src->values[j]);
			zbx_vector_db_tag_ptr_remove_noorder(src, j);
			continue;
		}
	}

	if (0 == src->values_num)
		return;

	/* update rest of the tags */
	for (i = 0; i < dst->values_num && 0 < src->values_num; i++)
	{
		if (ZBX_FLAG_DB_TAG_REMOVE != dst->values[i]->flags)
			continue;

		dst->values[i]->tag_orig = dst->values[i]->tag;
		dst->values[i]->value_orig = dst->values[i]->value;
		dst->values[i]->tag = src->values[0]->tag;
		dst->values[i]->value = src->values[0]->value;
		dst->values[i]->flags = ZBX_FLAG_DB_TAG_UPDATE_TAG | ZBX_FLAG_DB_TAG_UPDATE_VALUE;
		src->values[0]->tag = NULL;
		src->values[0]->value = NULL;
		zbx_db_tag_free(src->values[0]);
		zbx_vector_db_tag_ptr_remove_noorder(src, 0);
		continue;
	}

	/* add leftover new tags */
	zbx_vector_db_tag_ptr_append_array(dst, src->values, src->values_num);
	zbx_vector_db_tag_ptr_clear(src);
}



