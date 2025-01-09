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

#include "zbxtagfilter.h"

#include "zbxexpr.h"
#include "zbxdbhigh.h"
#include "zbxalgo.h"

ZBX_PTR_VECTOR_IMPL(match_tags_ptr, zbx_match_tag_t*)

static int	match_single_tag(const zbx_match_tag_t *mtag, zbx_tag_t * const *tags, int tags_num)
{
	int	i;

	switch (mtag->op)
	{
		case ZBX_CONDITION_OPERATOR_EXIST:
			return NULL == tags ? FAIL : SUCCEED;
		case ZBX_CONDITION_OPERATOR_NOT_EXIST:
			return NULL == tags ? SUCCEED : FAIL;
		case ZBX_CONDITION_OPERATOR_EQUAL:
		case ZBX_CONDITION_OPERATOR_LIKE:
			for (i = 0; i < tags_num; i++)
			{
				const zbx_tag_t	*etag = tags[i];

				if (SUCCEED == zbx_strmatch_condition(etag->value, mtag->value, mtag->op))
					return SUCCEED;
			}
			return FAIL;
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			for (i = 0; i < tags_num; i++)
			{
				const zbx_tag_t	*etag = tags[i];

				if (SUCCEED != zbx_strmatch_condition(etag->value, mtag->value, mtag->op))
					return FAIL;
			}
			return SUCCEED;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: matches tags with [*mt_pos] match tag name                        *
 *                                                                            *
 * Parameters: mtags    - [IN] match tags, sorted by tag names                *
 *             etags    - [IN] entity tags, sorted by tag names               *
 *             mt_pos   - [IN/OUT] next match tag index                       *
 *             et_pos   - [IN/OUT] next entity tag index                      *
 *                                                                            *
 * Return value: SUCCEED - found matching tag                                 *
 *               FAIL    - no matching tags found                             *
 *                                                                            *
 ******************************************************************************/
static int	match_tag_range(const zbx_vector_match_tags_ptr_t *mtags, const zbx_vector_tags_ptr_t *etags,
		int *mt_pos, int *et_pos)
{
	const char	*tag_name;
	int		i, ret = -1, mt_start, mt_end, et_start;
	zbx_tag_t	* const *tags;
	int		tags_num;

	/* get the match tag name */
	tag_name = mtags->values[*mt_pos]->tag;

	/* find match tag and entity tag ranges matching the first match tag name  */
	/* (match tag range [mt_start,mt_end], entity tag range [et_start,et_end]) */

	mt_start = *mt_pos;
	et_start = *et_pos;

	/* find last match tag with the required name */

	for (i = mt_start + 1; i < mtags->values_num; i++)
	{
		if (0 != strcmp(mtags->values[i]->tag, tag_name))
			break;
	}
	mt_end = i - 1;
	*mt_pos = i;

	/* find first entity tag with the required name */

	for (i = et_start; i < etags->values_num; i++)
	{
		if (0 <= (ret = strcmp(etags->values[i]->tag, tag_name)))
			break;
	}

	if (i == etags->values_num || 0 < ret)
	{
		/* entity tags with matching name not found */

		tags = NULL;
		tags_num = 0;
	}
	else
	{
		et_start = i++;

		/* find last entity tag with the required name */

		for (; i < etags->values_num; i++)
		{
			if (0 != strcmp(etags->values[i]->tag, tag_name))
				break;
		}

		tags = etags->values + et_start;
		tags_num = i - et_start;
	}

	*et_pos = i;

	/* cross-compare match tags and entity tags within the found ranges */

	for (i = mt_start; i <= mt_end; i++)
	{
		const zbx_match_tag_t	*mtag = mtags->values[i];

		if (SUCCEED == match_single_tag(mtag, tags, tags_num))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: matches filter tags and entity tags using AND/OR eval type        *
 *                                                                            *
 * Parameters: mtags    - [IN] match tags, sorted by tag names                *
 *             etags    - [IN] entity tags, sorted by tag names               *
 *                                                                            *
 * Return value: SUCCEED - entity tags do match                               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	match_tags_andor(const zbx_vector_match_tags_ptr_t *mtags, const zbx_vector_tags_ptr_t *etags)
{
	int	mt_pos = 0, et_pos = 0;

	while (mt_pos < mtags->values_num)
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
 * Parameters: mtags    - [IN] filter tags, sorted by tag names               *
 *             etags    - [IN] entity tags, sorted by tag names               *
 *                                                                            *
 * Return value: SUCCEED - entity tags do match                               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	match_tags_or(const zbx_vector_match_tags_ptr_t *mtags, const zbx_vector_tags_ptr_t *etags)
{
	int	mt_pos = 0, et_pos = 0;

	while (mt_pos < mtags->values_num)
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
 *             match_tags  - [IN] filter tags, sorted by tag names            *
 *             entity_tags - [IN] entity tags, sorted by tag names            *
 *                                                                            *
 * Return value: SUCCEED - entity tags do match                               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_match_tags(int eval_type, const zbx_vector_match_tags_ptr_t *match_tags,
		const zbx_vector_tags_ptr_t *entity_tags)
{
	if (ZBX_CONDITION_EVAL_TYPE_AND_OR != eval_type && ZBX_CONDITION_EVAL_TYPE_OR != eval_type)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	if (0 == match_tags->values_num)
		return SUCCEED;

	if (ZBX_CONDITION_EVAL_TYPE_AND_OR == eval_type)
		return match_tags_andor(match_tags, entity_tags);
	else
		return match_tags_or(match_tags, entity_tags);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare match tags by tag name for sorting                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_compare_match_tags(const void *d1, const void *d2)
{
	const zbx_match_tag_t	*tag1 = *(const zbx_match_tag_t * const *)d1;
	const zbx_match_tag_t	*tag2 = *(const zbx_match_tag_t * const *)d2;

	return strcmp(tag1->tag, tag2->tag);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release memory                                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_match_tag_free(zbx_match_tag_t *tag)
{
	zbx_free(tag->tag);
	zbx_free(tag->value);
	zbx_free(tag);
}
