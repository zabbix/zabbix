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

#include "common.h"
#include "log.h"
#include "zbxalgo.h"
#include "dbcache.h"
#include "macrocache.h"

/*
 * The purpose of user macro cache is to reduce configuration cache locking when
 * performing multiple macro resolves during single operation.
 *
 * Instead of resolving macros during expression evaluation and locking configuration
 * for every user macro, the user macro cache must be populated by scanning all
 * expressions involved in operation for user macros, then the user macro cache
 * is resolved (with a single configuration cache lock) and then the resolved
 * values from user macro cache are using during expression evaluation.
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_umc_init                                                     *
 *                                                                            *
 * Purpose: initialize user macro cache                                       *
 *                                                                            *
 * Parameters: cache  - [IN] the user macro cache                             *
 *                                                                            *
 ******************************************************************************/

void	zbx_umc_init(zbx_hashset_t *cache)
{
	zbx_hashset_create(cache, 10, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_umc_destroy                                                  *
 *                                                                            *
 * Purpose: destroy user macro cache                                          *
 *                                                                            *
 * Parameters: cache  - [IN] the user macro cache                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_umc_destroy(zbx_hashset_t *cache)
{
	zbx_hashset_iter_t	iter;
	zbx_umc_object_t	*object;
	int			i;

	zbx_hashset_iter_reset(cache, &iter);

	while (NULL != (object = zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_uint64_destroy(&object->hostids);

		for (i = 0; i < object->macros.values_num; i++)
		{
			zbx_ptr_pair_t	*pair = (zbx_ptr_pair_t *)&object->macros.values[i];

			zbx_free(pair->first);
			if (NULL != pair->second)
				zbx_free(pair->second);
		}
		zbx_vector_ptr_pair_destroy(&object->macros);
	}

	zbx_hashset_destroy(cache);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_umc_add_expression                                           *
 *                                                                            *
 * Purpose: add user macros from expression to user macro cache               *
 *                                                                            *
 * Parameters: cache      - [IN] the user macro cache                         *
 *             objectid   - [IN] the related object id                        *
 *             expression - [IN] the expression to parse                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_umc_add_expression(zbx_hashset_t *cache, zbx_uint64_t objectid, const char *expression)
{
	zbx_umc_object_t	*pobject;
	zbx_ptr_pair_t		pair = {NULL, NULL};
	const char		*br = expression, *bl;
	int 			len;

	pobject = zbx_hashset_search(cache, &objectid);

	while (NULL != (bl = strstr(br, "{$")))
	{
		if (NULL == (br = strchr(bl, '}')))
			break;

		if (NULL == pobject)
		{
			zbx_umc_object_t	object = {objectid};

			zbx_vector_uint64_create(&object.hostids);
			zbx_vector_ptr_pair_create(&object.macros);

			pobject = zbx_hashset_insert(cache, &object, sizeof(zbx_umc_object_t));
		}

		len = br - bl + 1;

		pair.first = zbx_malloc(NULL, len + 1);
		memcpy(pair.first, bl, len);
		((char *)pair.first)[len] = '\0';

		if (FAIL != zbx_vector_ptr_pair_search(&pobject->macros, pair, ZBX_DEFAULT_STR_COMPARE_FUNC))
		{
			zbx_free(pair.first);
			continue;
		}

		zbx_vector_ptr_pair_append_ptr(&pobject->macros, &pair);
	}

	if (NULL != pobject)
		zbx_vector_ptr_pair_sort(&pobject->macros, ZBX_DEFAULT_STR_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_umc_add_hostids                                              *
 *                                                                            *
 * Purpose: add source hostids to user macro cache                            *
 *                                                                            *
 * Parameters: cache       - [IN] the user macro cache                        *
 *             objectid    - [IN] the related object id                       *
 *             hostids     - [IN] an array of host ids to add                 *
 *             hostids_num - [IN] the number of items in hostids array        *
 *                                                                            *
 ******************************************************************************/
void	zbx_umc_add_hostids(zbx_hashset_t *cache, zbx_uint64_t objectid, const zbx_uint64_t *hostids, int hostids_num)
{
	zbx_umc_object_t	*pobject;
	int			i;

	if (NULL == (pobject = zbx_hashset_search(cache, &objectid)))
	{
		zbx_umc_object_t	object = {objectid};

		zbx_vector_uint64_create(&object.hostids);
		zbx_vector_ptr_pair_create(&object.macros);

		pobject = zbx_hashset_insert(cache, &object, sizeof(zbx_umc_object_t));
	}

	for (i = 0; i < hostids_num; i++)
		zbx_vector_uint64_append(&pobject->hostids, hostids[i]);

	zbx_vector_uint64_sort(&pobject->hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&pobject->hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_umc_get_macro_value                                          *
 *                                                                            *
 * Purpose: get macro value from user macro cache                             *
 *                                                                            *
 * Parameters: cache    - [IN] the user macro cache                           *
 *             objectid - [IN] the related object id                          *
 *             macro    - [IN] the macro name                                 *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_umc_get_macro_value(zbx_hashset_t *cache, zbx_uint64_t objectid, const char *macro)
{
	zbx_umc_object_t	*pobject;
	zbx_ptr_pair_t		pair;
	int			index;

	if (NULL != (pobject = zbx_hashset_search(cache, &objectid)))
	{
		pair.first = (char *)macro;

		if (FAIL != (index = zbx_vector_ptr_pair_bsearch(&pobject->macros, pair, ZBX_DEFAULT_STR_COMPARE_FUNC)))
			return pobject->macros.values[index].second;
	}
	return NULL;
}
