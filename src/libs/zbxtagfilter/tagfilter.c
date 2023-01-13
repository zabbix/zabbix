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

#include "zbxtagfilter.h"

#include "zbxexpr.h"
#include "zbxdbhigh.h"	// for ZBX_CONDITION_EVAL_TYPE_*

ZBX_PTR_VECTOR_IMPL(match_tags, zbx_match_tag_t*)

/******************************************************************
 *                                                                *
 * Purpose: perform match tag comparison using match tag operator *
 *                                                                *
 ******************************************************************/
static int	match_tag_value(const zbx_match_tag_t *mtag, const zbx_tag_t *etag)
{
	switch (mtag->op)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
		case ZBX_CONDITION_OPERATOR_LIKE:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			return zbx_strmatch_condition(etag->value, mtag->value, mtag->op);
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: matches tags with [*mt_pos] match tag name                        *
 *                                                                            *
 * Parameters: mtags    - [IN] the match tags, sorted by tag names            *
 *             etags    - [IN] the entity tags, sorted by tag names           *
 *             mt_pos   - [IN/OUT] the next match tag index                   *
 *             et_pos   - [IN/OUT] the next entity tag index                  *
 *                                                                            *
 * Return value: SUCCEED - found matching tag                                 *
 *               FAIL    - no matching tags found                             *
 *                                                                            *
 ******************************************************************************/
static int	match_tag_range(const zbx_vector_match_tags_t *mtags, const zbx_vector_tags_t *etags,
		int *mt_pos, int *et_pos)
{
	const zbx_match_tag_t	*mtag;
	const zbx_tag_t		*etag;
	const char		*name;
	int			i, j, ret, mt_start, mt_end, et_start, et_end;

	/* get the match tag name */
	mtag = (const zbx_match_tag_t *)mtags->values[*mt_pos];
	name = mtag->tag;

	/* find match tag and entity tag ranges matching the first match tag name  */
	/* (match tag range [mt_start,mt_end], entity tag range [et_start,et_end]) */

	mt_start = *mt_pos;
	et_start = *et_pos;

	/* find last match tag with the required name */

	for (i = mt_start + 1; i < mtags->values_num; i++)
	{
		mtag = (const zbx_match_tag_t *)mtags->values[i];
		if (0 != strcmp(mtag->tag, name))
			break;
	}
	mt_end = i - 1;
	*mt_pos = i;

	/* find first entity tag with the required name */

	for (i = et_start; i < etags->values_num; i++)
	{
		etag = (const zbx_tag_t *)&etags->values[i];
		if (0 < (ret = strcmp(etag->tag, name)))
		{
			*et_pos = i;
			i = etags->values_num;
			break;
		}

		if (0 == ret)
			break;
	}

	switch (mtag->op)
	{
		case ZBX_CONDITION_OPERATOR_EXIST:
			return i == etags->values_num ? FAIL : SUCCEED;
		case ZBX_CONDITION_OPERATOR_NOT_EXIST:
			return i == etags->values_num ? SUCCEED : FAIL;
	}

	if (i == etags->values_num)
	{
		*et_pos = i;
		return FAIL;
	}

	et_start = i++;

	/* find last entity tag with the required name */

	for (; i < etags->values_num; i++)
	{
		etag = (const zbx_tag_t *)&etags->values[i];
		if (0 != strcmp(etag->tag, name))
			break;
	}

	et_end = i - 1;
	*et_pos = i;

	/* cross-compare match tags and entity tags within the found ranges */

	for (i = mt_start; i <= mt_end; i++)
	{
		mtag = (const zbx_match_tag_t *)mtags->values[i];

		for (j = et_start; j <= et_end; j++)
		{
			etag = (const zbx_tag_t *)&etags->values[j];
			if (SUCCEED == match_tag_value(mtag, etag))
				return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: matches filter tags and entity tags using AND/OR eval type        *
 *                                                                            *
 * Parameters: mtags    - [IN] the match tags, sorted by tag names            *
 *             etags    - [IN] the entity tags, sorted by tag names           *
 *                                                                            *
 * Return value: SUCCEED - entity tags do match                               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	match_tags_andor(const zbx_vector_match_tags_t *mtags, const zbx_vector_tags_t *etags)
{
	int	mt_pos = 0, et_pos = 0;

	while (mt_pos < mtags->values_num && et_pos < etags->values_num)
	{
		if (FAIL == match_tag_range(mtags, etags, &mt_pos, &et_pos))
			return FAIL;
	}

	if (mt_pos != mtags->values_num)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: matches filter tags and entity tags using OR eval type            *
 *                                                                            *
 * Parameters: mtags    - [IN] the filter tags, sorted by tag names           *
 *             etags    - [IN] the entity tags, sorted by tag names           *
 *                                                                            *
 * Return value: SUCCEED - entity tags do match                               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	match_tags_or(const zbx_vector_match_tags_t *mtags, const zbx_vector_tags_t *etags)
{
	int	mt_pos = 0, et_pos = 0;

	while (mt_pos < mtags->values_num && et_pos < etags->values_num)
	{
		if (SUCCEED == match_tag_range(mtags, etags, &mt_pos, &et_pos))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the entity tags match filter tags                        *
 *                                                                            *
 * Parameters: eval_type   - [IN] evaluation type (and/or, or)                *
 *             match_tags  - [IN] the filter                                  *
 *             entity_tags - [IN] the tags to check                           *
 *                                                                            *
 * Return value: SUCCEED - the tags do match                                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_match_tags(unsigned char eval_type, const zbx_vector_match_tags_t *match_tags,
		const zbx_vector_tags_t *entity_tags)
{
	if (ZBX_CONDITION_EVAL_TYPE_AND_OR != eval_type && ZBX_CONDITION_EVAL_TYPE_OR != eval_type)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	if (0 == match_tags->values_num)
		return SUCCEED;

	if (0 == entity_tags->values_num)
		return FAIL;

	if (ZBX_CONDITION_EVAL_TYPE_AND_OR == eval_type)
		return match_tags_andor(match_tags, entity_tags);
	else
		return match_tags_or(match_tags, entity_tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare entity tags by tag name for sorting                       *
 *                                                                            *
 ******************************************************************************/
static int	compare_tags(const void *d1, const void *d2)
{
	const zbx_tag_t	*tag1 = *(const zbx_tag_t * const *)d1;
	const zbx_tag_t	*tag2 = *(const zbx_tag_t * const *)d2;

	return strcmp(tag1->tag, tag2->tag);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare filter tags by tag name for sorting                       *
 *                                                                            *
 ******************************************************************************/
static int	compare_match_tags(const void *d1, const void *d2)
{
	const zbx_match_tag_t	*tag1 = *(const zbx_match_tag_t * const *)d1;
	const zbx_match_tag_t	*tag2 = *(const zbx_match_tag_t * const *)d2;

	return strcmp(tag1->tag, tag2->tag);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sort entity tags by tag name                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_sort_tags(zbx_vector_tags_t *tags)
{
	zbx_vector_tags_sort(tags, compare_tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sort filter tags by tag name                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_sort_match_tags(zbx_vector_match_tags_t *tags)
{
	zbx_vector_match_tags_sort(tags, compare_match_tags);
}

void	zbx_match_tag_free(zbx_match_tag_t *tag)
{
	zbx_free(tag->tag);
	zbx_free(tag->value);
}
