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

#include "zbxdbhigh.h"

typedef union
{
	zbx_db_tag_t			*tag;
	zbx_template_item_param_t	*item_param;
}
resouce_u;

#define RESOURCE_TAG		__UINT64_C(0x01)
#define RESOURCE_ITEM_PARAM	__UINT64_C(0x02)

typedef struct
{
	unsigned char	type;
	char		*name;
	resouce_u	*resource;
} resource_t;

#define GET_RESOURCE(func_name, struct_member_tag, struct_member_item_param, ret_type)	\
static ret_type	get_resource_##func_name(const resource_t* in)				\
{											\
	if (0 != (in->type & RESOURCE_TAG))						\
	{										\
		return in->resource->tag->struct_member_tag;				\
	}										\
	else if (0 != (in->type & RESOURCE_ITEM_PARAM))					\
	{										\
		return in->resource->item_param->struct_member_item_param;		\
	}										\
	else										\
	{										\
		THIS_SHOULD_NEVER_HAPPEN;						\
		exit(EXIT_FAILURE);							\
	}										\
}											\

GET_RESOURCE(first,		tag,		name,			char*)
GET_RESOURCE(first_orig,	tag_orig,	name_orig,		char*)
GET_RESOURCE(second,		value,		value,			char*)
GET_RESOURCE(second_orig,	value_orig,	value_orig,		char*)
GET_RESOURCE(id,		tagid,		item_parameterid,	uint64_t)

#define SET_RESOURCE(func_name, struct_member_tag, struct_member_item_param, set_type)	\
static void	set_resource_##func_name(resource_t* in, set_type value_in)		\
{											\
	if (0 != (in->type & RESOURCE_TAG))						\
	{										\
		in->resource->tag->struct_member_tag = value_in;			\
	}										\
	else if (0 != (in->type & RESOURCE_ITEM_PARAM))					\
	{										\
		in->resource->item_param->struct_member_item_param = value_in;		\
	}										\
	else										\
	{										\
		THIS_SHOULD_NEVER_HAPPEN;						\
		exit(EXIT_FAILURE);							\
	}										\
}											\

SET_RESOURCE(first,		tag,		name,		char*)
SET_RESOURCE(first_orig,	tag_orig,	name_orig,	char*)
SET_RESOURCE(second,		value,		value,		char*)
SET_RESOURCE(second_orig,	value_orig,	value_orig,	char*)

#define RESOURCE_FREE(func_name, struct_member_tag, struct_member_item_param)		\
static void	resource_free_##func_name(resource_t* in)				\
{											\
	if (0 != (in->type & RESOURCE_TAG))						\
	{										\
		zbx_free(in->resource->tag->struct_member_tag);				\
	}										\
	else if (0 != (in->type & RESOURCE_ITEM_PARAM))					\
	{										\
		zbx_free(in->resource->item_param->struct_member_item_param);		\
	}										\
	else										\
	{										\
		THIS_SHOULD_NEVER_HAPPEN;						\
		exit(EXIT_FAILURE);							\
	}										\
}											\

RESOURCE_FREE(first,	tag,	name)
RESOURCE_FREE(second,	value,	value)

void	resource_free(resource_t* in)
{
	if (0 == (in->type & RESOURCE_TAG))
	{
		zbx_db_tag_free(in->resource->tag);
	}
	else if (0 == (in->type & RESOURCE_TAG))
	{
		zbx_item_params_free(in->resource->item_param);
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
}

#define RESOURCE_FLAG_IS(func_name, tag_flag, item_param_flag, struct_member_tag, struct_member_item_param)	\
int	resource_flag_is_##func_name(resource_t* in)								\
{														\
	if (0 != (in->type & RESOURCE_TAG))									\
	{													\
		return tag_flag == in->resource->struct_member_tag->flags;					\
	}													\
	else if (0 != (in->type & RESOURCE_ITEM_PARAM))								\
	{													\
		return item_param_flag == in->resource->struct_member_item_param->upd_flags;			\
	}													\
	else													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		exit(EXIT_FAILURE);										\
	}													\
}														\

RESOURCE_FLAG_IS(delete, ZBX_FLAG_DB_TAG_REMOVE, ZBX_FLAG_TEMPLATE_ITEM_PARAM_DELETE, tag, item_param)
RESOURCE_FLAG_IS(update_first, ZBX_FLAG_DB_TAG_UPDATE_TAG, ZBX_FLAG_TEMPLATE_ITEM_PARAM_UPDATE_NAME, tag, item_param)
RESOURCE_FLAG_IS(update_second, ZBX_FLAG_DB_TAG_UPDATE_VALUE, ZBX_FLAG_TEMPLATE_ITEM_PARAM_UPDATE_VALUE, tag, item_param)

#define RESOURCE_FLAG(func_name, tag_flag, item_param_flag, struct_member_tag, struct_member_item_param)	\
void	resource_flag_##func_name(resource_t* in)								\
{														\
	if (0 != (in->type & RESOURCE_TAG))									\
	{													\
		in->resource->struct_member_tag->flags &= tag_flag;						\
	}													\
	else if (0 != (in->type & RESOURCE_ITEM_PARAM))								\
	{													\
		in->resource->struct_member_item_param->upd_flags &= item_param_flag;				\
	}													\
	else													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		exit(EXIT_FAILURE);										\
	}													\
}														\

RESOURCE_FLAG(reset_first, (~ZBX_FLAG_DB_TAG_UPDATE_TAG), (~ZBX_FLAG_TEMPLATE_ITEM_PARAM_UPDATE_NAME), tag, item_param)
RESOURCE_FLAG(reset_second, (~ZBX_FLAG_DB_TAG_UPDATE_VALUE), (~ZBX_FLAG_TEMPLATE_ITEM_PARAM_UPDATE_VALUE), tag, item_param)
RESOURCE_FLAG(set_first, (ZBX_FLAG_DB_TAG_UPDATE_TAG), (ZBX_FLAG_TEMPLATE_ITEM_PARAM_UPDATE_NAME), tag, item_param)
RESOURCE_FLAG(set_second, (ZBX_FLAG_DB_TAG_UPDATE_VALUE), (ZBX_FLAG_TEMPLATE_ITEM_PARAM_UPDATE_VALUE), tag, item_param)

ZBX_PTR_VECTOR_DECL(resource_ptr, resource_t *)
ZBX_PTR_VECTOR_IMPL(resource_ptr, resource_t *)

static void	db_tag_merge_automatic(zbx_db_tag_t *dst, zbx_db_tag_t *src)
{
	if (dst->automatic == src->automatic)
		return;

	dst->automatic_orig = dst->automatic;
	dst->automatic = src->automatic;
	dst->flags |= ZBX_FLAG_DB_TAG_UPDATE_AUTOMATIC;
}

static void	resource_update_automatic(resource_t *in)
{
	if (0 == (in->type & RESOURCE_TAG))
	{
		if (0 != (in->resource->tag->flags & ZBX_FLAG_DB_TAG_UPDATE_AUTOMATIC))
		{
			in->resource->tag->automatic = in->resource->tag->automatic_orig;
			in->resource->tag->flags &= (~ZBX_FLAG_DB_TAG_UPDATE_AUTOMATIC);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: roll back resource updates done during merge process              *
 *                                                                            *
 * Return value: SUCCEED - updates were rolled back                           *
 *               FAIL    - new tag, rollback impossible                       *
 *                                                                            *
 ******************************************************************************/
static int	db_resource_rollback(resource_t *in)
{
	if (0 == get_resource_id(in))
		return FAIL;

	if (resource_flag_is_update_first(in))
	{
		resource_free_first(in);
		set_resource_first(in, get_resource_first_orig(in));
		set_resource_first_orig(in, NULL);
		resource_flag_reset_first(in);
	}

	if (resource_flag_is_update_second(in))
	{
		resource_free_second(in);
		set_resource_second(in, get_resource_second_orig(in));
		set_resource_second_orig(in, NULL);
		resource_flag_reset_second(in);
	}

	resource_update_automatic(in);

	return SUCCEED;
}

#define RESOURCE_OP(resource) (0 == get_resource_id(resource) ? "create" : "update")

typedef enum
{
	RESOURCE_FIRST,
	RESOURCE_SECOND
}
resource_field_t;

/******************************************************************************
 *                                                                            *
 * Purpose: check validness of a single tag/item_param field                  *
 *          (tag+value/name+value)                                            *
 *                                                                            *
 * Return value: SUCCEED - tag/item_param field is valid                      *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	db_resource_check_field(resource_t *in, resource_field_t type, const char *owner, char **error)
{
	const char	*field, *str;
	size_t		field_len, str_len;

	switch (type)
	{
		case RESOURCE_FIRST:
			if (0 != (in->type & RESOURCE_TAG))
			{
				field = "tag";
				str = in->resource->tag->tag;
				field_len = ZBX_DB_TAG_NAME_LEN;
			}
			else if (0 != (in->type & RESOURCE_ITEM_PARAM))
			{
				field = "name";
				str = in->resource->item_param->name;
				field_len = ZBX_ITEM_PARAMETER_NAME_LEN;
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
			}
			break;
		case RESOURCE_SECOND:
			if (0 != (in->type & RESOURCE_TAG))
			{
				field = "value";
				str = in->resource->tag->value;
				field_len = ZBX_DB_TAG_VALUE_LEN;
			}
			else if (0 != (in->type & RESOURCE_ITEM_PARAM))
			{
				field = "value";
				str = in->resource->item_param->value;
				field_len = ZBX_ITEM_PARAMETER_VALUE_LEN;
			}
			else
			{
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
			}
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;

			if (NULL != error)
			{
				*error = zbx_strdcatf(*error, "Cannot %s %s %s: invalid field type.\n",
						RESOURCE_OP(in), owner, in->name);
			}
			return FAIL;
	}

	if (SUCCEED != zbx_is_utf8(str))
	{
		if (NULL != error)
		{
			char	*ptr_utf8;

			ptr_utf8 = zbx_strdup(NULL, str);
			zbx_replace_invalid_utf8(ptr_utf8);
			*error = zbx_strdcatf(*error, "Cannot %s %s %s: %s \"%s\" has invalid UTF-8 sequence.\n",
					RESOURCE_OP(in), owner, in->name, field, ptr_utf8);
			zbx_free(ptr_utf8);
		}

		return FAIL;
	}

	str_len = zbx_strlen_utf8(str);

	if (field_len < str_len)
	{
		if (NULL != error)
		{
			*error = zbx_strdcatf(*error, "Cannot %s %s %s: %s \"%128s...\" is too long.\n",
					RESOURCE_OP(in), owner, field, in->name, str);
		}
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check validness of all fields for a list of tags/item_params      *
 *                                                                            *
 * Return value: SUCCEED - tags/item_params have valid fields                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	check_resource_fields(zbx_vector_resource_ptr_t *resources_in, const char *owner, char **error)
{
	int	i, ret = SUCCEED;

	for (i = 0; i < resources_in->values_num; i++)
	{
		int		errors = 0;

		if (resource_flag_is_delete(resources_in->values[i]))
			continue;

		if ('\0' == *get_resource_first(resources_in->values[i]))
		{
			if (NULL != error)
			{
				*error = zbx_strdcatf(*error, "Cannot %s %s %s: empty tag name.\n",
						RESOURCE_OP(resources_in->values[i]), owner,
						resources_in->values[i]->name);
			}
			errors += FAIL;
		}

		errors += db_resource_check_field(resources_in->values[i], RESOURCE_FIRST, owner, error);
		errors += db_resource_check_field(resources_in->values[i], RESOURCE_SECOND, owner, error);

		if (0 > errors)
		{
			if (SUCCEED != db_resource_rollback(resources_in->values[i]))
			{
				resource_free(resources_in->values[i]);
				zbx_vector_resource_ptr_remove_noorder(resources_in, i--);
			}

			ret = FAIL;
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check new tags/parameters for duplicate tag+value/name+value      *
 *          combinations                                                      *
 *                                                                            *
 * Parameters: resources  - [IN/OUT] tags/parameters to check                 *
 *             owner - [IN] the owned object (host, item, trigger)            *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - tags/parameters have no duplicates                 *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Existing tags/parameters are rolled back to their original       *
 *           values, while new tags/parameters are removed.                   *
 *                                                                            *
 ******************************************************************************/
static int	check_duplicate_resource(zbx_vector_resource_ptr_t *resources, const char *owner, char **error)
{
	int	i, j, ret = SUCCEED;

	for (i = 0; i < resources->values_num; i++)
	{
		resource_t	*left = resources->values[i];

		for (j = 0; j < i; j++)
		{
			resource_t	*right = resources->values[j];

			if (0 == strcmp(get_resource_first(left), get_resource_second(right)) && 0 ==
					strcmp(get_resource_second(left), get_resource_second(right)))
			{
				if (NULL != error)
				{
					*error = zbx_strdcatf(*error, "Cannot %s %s %s: \"%s: %s\" already exists.\n",
							RESOURCE_OP(left), owner, left->name, get_resource_first(left),
							get_resource_second(right));
				}

				resource_free(left);
				zbx_vector_resource_ptr_remove_noorder(resources, i--);

				ret = FAIL;
				break;
			}
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge new tags into existing                                      *
 *                                                                            *
 * Parameters: dst - [IN/OUT] vector of existing tags                         *
 *             src - [IN/OUT] vector or new tags                              *
 *             owner  - [IN] the tag owner (host, item, trigger),             *
 *                           optional - must be specified if error parameter  *
 *                           is not null                                      *
 *             error  - [IN,OUT] the error message (appended to existing),    *
 *                           optional                                         *
 *                                                                            *
 * Comments: The tags are merged using the following logic:                   *
 *           1) tags with matching name+value are left as it is               *
 *           2) tags with matching names will have their values updated       *
 *           3) tags without matches will have:                               *
 *              a) their name and value updated if there are new tags left    *
 *              b) flagged to be removed otherwise                            *
 *           4) all leftover new tags will be created                         *
 *                                                                            *
 * Return value: SUCCEED - tags were merged without issues                    *
 *               FAIL - tags were merged with errors                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_merge_resource(zbx_vector_resource_ptr_t *dst, zbx_vector_resource_ptr_t *src, const char *owner,
		char **error)
{
	int	i, j, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() old_resource:%d new_resource:%d", __func__, dst->values_num,
			src->values_num);

	ret = check_duplicate_resource(src, owner, error);

	/* perform exact tag+value/name+value match */
	for (i = 0; i < dst->values_num; i++)
	{
		for (j = 0; j < src->values_num; j++)
		{
			if (0 == strcmp(get_resource_first(dst->values[i]), get_resource_first(src->values[j])) &&
					0 == strcmp(get_resource_second(dst->values[i]),
					get_resource_second(src->values[j])))
			{
				break;
			}
		}

		if (j != src->values_num)
		{
			if (0 == (dst->values[i]->type & RESOURCE_TAG))
				db_tag_merge_automatic(dst->values[i]->resource->tag, src->values[j]->resource->tag);

			resource_free(src->values[j]);
			zbx_vector_resource_ptr_remove_noorder(src, j);
			continue;
		}

		if (0 == (dst->values[i]->type & RESOURCE_TAG) && ZBX_DB_TAG_AUTOMATIC ==
				dst->values[i]->resource->tag->automatic)
		{
			dst->values[i]->resource->tag->flags = ZBX_FLAG_DB_TAG_REMOVE;
		}
	}

	if (0 == src->values_num)
		goto out;

	/* perform tag match */
	for (i = 0; i < dst->values_num; i++)
	{
		if (resource_flag_is_delete(dst->values[i]))
			continue;

		for (j = 0; j < src->values_num; j++)
		{
			if (0 == strcmp(get_resource_first(dst->values[i]), get_resource_first(src->values[j])))
				break;
		}

		if (j != src->values_num)
		{
			set_resource_second_orig(dst->values[i], get_resource_second(dst->values[i]));
			set_resource_second(dst->values[i], get_resource_second(src->values[j]));
			resource_flag_set_second(dst->values[i]);
			set_resource_second(src->values[j], NULL);
			resource_free(src->values[j]);

			zbx_vector_resource_ptr_remove_noorder(src, j);
			continue;
		}
	}

	if (0 == src->values_num)
		goto out;

	/* update rest of the tags */
	for (i = 0; i < dst->values_num && 0 < src->values_num; i++)
	{
		if(resource_flag_is_delete(dst->values[i]))
		{
			continue;
		}

		set_resource_first_orig(dst->values[i], get_resource_first(dst->values[i]));
		set_resource_second_orig(dst->values[i], get_resource_second(dst->values[i]));
		set_resource_first(dst->values[i], get_resource_first(src->values[0]));
		set_resource_second(dst->values[i], get_resource_second(src->values[0]));

		resource_flag_set_first(dst->values[i]);
		resource_flag_set_second(dst->values[i]);
		set_resource_first(src->values[0], NULL);
		set_resource_second(src->values[0], NULL);
		resource_free(src->values[0]);
		zbx_vector_resource_ptr_remove_noorder(src, 0);

		continue;
	}

	/* add leftover new tags */
	zbx_vector_resource_ptr_append_array(dst, src->values, src->values_num);

	zbx_vector_resource_ptr_clear(src);
out:
	if (SUCCEED != check_resource_fields(dst, owner, error))
		ret = FAIL;

	zbx_vector_resource_ptr_sort(dst, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() resources :%d", __func__, dst->values_num);

	return ret;
}

#define ZBX_MERGE(func_name, vector_type, merge_type, merge_name, merge_struct)					\
int	zbx_merge_##func_name(zbx_vector_##vector_type##_ptr_t *dst, zbx_vector_##vector_type##_ptr_t *src,	\
		const char *owner, char **error)								\
{														\
	int	i, res;								\
	zbx_vector_resource_ptr_t	res_dst, res_src;							\
														\
	zbx_vector_resource_ptr_create(&res_dst);								\
	zbx_vector_resource_ptr_create(&res_src);								\
														\
	for (i = 0; i < dst->values_num; i++)									\
	{													\
		resource_t	*r; \
\
		r = (resource_t *)zbx_malloc(NULL, sizeof(resource_t));\
		r->type = merge_type;\
		r->name = zbx_strdup(NULL, merge_name);\
		r->resource->merge_struct = dst->values[i];\
		zbx_vector_resource_ptr_append(&res_dst, r);\
	}\
\
	for (i = 0; i < src->values_num; i++)\
	{\
		resource_t	*r;\
\
		r = (resource_t *)zbx_malloc(NULL, sizeof(resource_t));\
		r->type = merge_type;\
		r->name = zbx_strdup(NULL, merge_name);\
		r->resource->merge_struct = src->values[i];\
		zbx_vector_resource_ptr_append(&res_src, r);\
	}\
\
	zbx_vector_##vector_type##_ptr_destroy(dst);\
	zbx_vector_##vector_type##_ptr_destroy(src);\
	\
	\
	res = zbx_merge_resource(&res_dst, &res_src, owner, error);\
\
	zbx_vector_##vector_type##_ptr_create(dst);\
	zbx_vector_##vector_type##_ptr_create(src);\
\
	for (i = 0; i < res_dst.values_num; i++)\
	{\
		zbx_vector_##vector_type##_ptr_append(dst, res_dst.values[i]->resource->merge_struct);\
		zbx_free(res_dst.values[i]->name);\
		zbx_free(res_dst.values[i]);\
	}\
\
	for (i = 0; i < res_src.values_num; i++)\
	{\
		zbx_vector_##vector_type##_ptr_append(src, res_src.values[i]->resource->merge_struct);\
		zbx_free(res_src.values[i]->name);\
		zbx_free(res_src.values[i]);	  \
	}\
\
	zbx_vector_resource_ptr_destroy(&res_dst);\
	zbx_vector_resource_ptr_destroy(&res_src);\
\
	return res; \
}						\

ZBX_MERGE(tags, db_tag, RESOURCE_TAG, "tag", tag)
ZBX_MERGE(item_param, item_param, RESOURCE_ITEM_PARAM, "item_param", item_param)
