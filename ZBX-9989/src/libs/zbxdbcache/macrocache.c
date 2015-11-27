/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
 * For example when processing triggers we can have 1000+ expressions each containing
 * multiple user macros. So instead of locking configuration cache to resolve each
 * macro we first scan all expressions for user macros, link with involved hosts,
 * resolve cached macros with a single configuration cache lock and then use the
 * resolved macros from cache to evaluate trigger expressions.
 *
 * The macros in the cache are grouped by object id referring to the object 'owning'
 * them. For example when resolving trigger expressions macro owners are triggers.
 *
 * Each object in cache can have multiple macros and also several associated hosts
 * that are used to resolve host level user macros.
 *
 * The user macro cache usage is described in following steps:
 *
 *   1) Initialize cache with zbx_umc_init() function.
 *
 *   2) Fill cache with objects and their macros with zbx_umc_add_expression() function.
 *      This function parses expression and adds the specified object with parsed
 *      macros to the cache.
 *
 *      Cache now contains following records:
 *
 *       .-----------------------------------------------------.
 *       |                    cache record                     |-.
 *       |-----------------------------------------------------| |-.
 *       | objectid                                            |-| |
 *       | hostids[]                                           | |-|
 *       | macros[(name1,null),(name2,null),...,(nameN,null)]  | | |
 *       '-----------------------------------------------------' | |
 *         '-----------------------------------------------------' |
 *           '-----------------------------------------------------'
 *
 *      Each record has its owner object id, an empty list of associated hosts and
 *      a list of macros in a form of (name, value) pairs, where values are null.
 *
 *   3) Set associated hosts for cache objects with zbx_umc_add_hostids() function.
 *
 *      Cache now contains following records:
 *
 *       .-----------------------------------------------------.
 *       |                    cache record                     |-.
 *       |-----------------------------------------------------| |-.
 *       | objectid                                            |-| |
 *       | hostids[hostid1,hostid2,...,hostidK]                | |-|
 *       | macros[(name1,null),(name2,null),...,(nameN,null)]  | | |
 *       '-----------------------------------------------------' | |
 *         '-----------------------------------------------------' |
 *           '-----------------------------------------------------'
 *
 *   4) Resolve cached macros with zbx_umc_resolve() function (because this function
 *      locks configuration cache it is defined in dbconfig.c file).
 *
 *      Cache now contains following records:
 *
 *       .----------------------------------------------------------.
 *       |                       cache record                       |-.
 *       |----------------------------------------------------------| |-.
 *       | objectid                                                 |-| |
 *       | hostids[hostid1,hostid2,...,hostidK]                     | |-|
 *       | macros[(name1,value1),(name2,value2),...,(nameN,valueN)] | | |
 *       '----------------------------------------------------------' | |
 *         '----------------------------------------------------------' |
 *           '----------------------------------------------------------'
 *
 *   5) Access the resolved macro values with zbx_umc_get_macro_value() function.
 *
 *   6) Destroy the user macro cache with zbx_umc_destroy() function.
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_umc_free_macro                                               *
 *                                                                            *
 * Purpose: frees user macro cache macro                                      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_umc_free_macro(zbx_umc_macro_t *macro)
{
	zbx_free(macro->name);
	zbx_free(macro->context);
	zbx_free(macro->value);
	zbx_free(macro);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_umc_compare_macro                                            *
 *                                                                            *
 * Purpose: compares two user macros                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_umc_compare_macro(const void *d1, const void *d2)
{
	zbx_umc_macro_t	*m1 = *(zbx_umc_macro_t **)d1;
	zbx_umc_macro_t	*m2 = *(zbx_umc_macro_t **)d2;
	int		ret;

	if (0 != (ret = strcmp(m1->name, m2->name)))
		return ret;

	return zbx_strcmp_null(m1->context, m2->context);
}

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

	zbx_hashset_iter_reset(cache, &iter);

	while (NULL != (object = zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_uint64_destroy(&object->hostids);
		zbx_vector_ptr_clear_ext(&object->macros, (zbx_clean_func_t)zbx_umc_free_macro);
		zbx_vector_ptr_destroy(&object->macros);
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
	zbx_umc_object_t	*object;
	zbx_umc_macro_t		*macro, macro_local;
	const char		*br = expression, *bl;
	int			length;

	object = zbx_hashset_search(cache, &objectid);

	macro_local.value = NULL;

	while (NULL != (bl = strstr(br, "{$")))
	{
		char	*name = NULL, *context = NULL;

		if (SUCCEED != zbx_user_macro_parse_dyn(bl, &name, &context, &length))
			break;

		if (NULL == object)
		{
			zbx_umc_object_t	object_local;

			object_local.objectid = objectid;
			zbx_vector_uint64_create(&object_local.hostids);
			zbx_vector_ptr_create(&object_local.macros);

			object = zbx_hashset_insert(cache, &object_local, sizeof(zbx_umc_object_t));
		}

		br = bl + length;

		macro_local.name = name;
		macro_local.context = context;

		if (FAIL != zbx_vector_ptr_search(&object->macros, &macro_local, zbx_umc_compare_macro))
		{
			zbx_free(macro_local.name);
			zbx_free(macro_local.context);
			continue;
		}

		macro = (zbx_umc_macro_t *)zbx_malloc(NULL, sizeof(zbx_umc_macro_t));
		*macro = macro_local;

		zbx_vector_ptr_append(&object->macros, macro);
	}
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
	zbx_umc_object_t	*object;
	int			i;

	if (NULL == (object = zbx_hashset_search(cache, &objectid)))
	{
		zbx_umc_object_t	object_local;

		object_local.objectid = objectid;
		zbx_vector_uint64_create(&object_local.hostids);
		zbx_vector_ptr_create(&object_local.macros);

		object = zbx_hashset_insert(cache, &object_local, sizeof(zbx_umc_object_t));
	}

	for (i = 0; i < hostids_num; i++)
		zbx_vector_uint64_append(&object->hostids, hostids[i]);

	zbx_vector_uint64_sort(&object->hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&object->hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
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
	zbx_umc_object_t	*object;
	zbx_umc_macro_t		macro_local = {NULL};
	int			index;
	const char		*value = NULL;

	if (NULL == (object = zbx_hashset_search(cache, &objectid)) ||
			SUCCEED != zbx_user_macro_parse_dyn(macro, &macro_local.name, &macro_local.context, NULL))
	{
		return NULL;
	}

	if (FAIL != (index = zbx_vector_ptr_bsearch(&object->macros, &macro_local, zbx_umc_compare_macro)))
		value = ((zbx_umc_macro_t *)object->macros.values[index])->value;

	zbx_free(macro_local.context);
	zbx_free(macro_local.name);

	return value;
}
