/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "macroindex.h"

#include "common.h"
#include "log.h"
#include "dbcache.h"

#define ZBX_DBCONFIG_IMPL
#include "dbconfig.h"

typedef struct
{
	/* the hostid for host macros or 0 for global macros */
	zbx_uint64_t		hostid;
	/* the macro name */
	const char		*name;
	/* the macro references */
	zbx_vector_ptr_t	macros;
}
zbx_um_macro_t;

/* macro index hashset support */
static zbx_hash_t	um_macro_hash_func(const void *data)
{
	const zbx_um_macro_t	*macro = (const zbx_um_macro_t *)data;

	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&macro->hostid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(macro->name, strlen(macro->name), hash);

	return hash;
}

static int	um_macro_compare_func(const void *d1, const void *d2)
{
	const zbx_um_macro_t	*m1 = (const zbx_um_macro_t *)d1;
	const zbx_um_macro_t	*m2 = (const zbx_um_macro_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(m1->hostid, m2->hostid);

	return m1->name == m1->name ? 0 : strcmp(m1->name, m2->name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_init                                                      *
 *                                                                            *
 * Purpose: initializes user macro index                                      *
 *                                                                            *
 * Parameters: index  - [IN/OUT] macro index                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_init(zbx_macro_index_t *index)
{
	zbx_hashset_create(&index->macros, 0, um_macro_hash_func, um_macro_compare_func);
	zbx_hashset_create(&index->htmpls, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	index->malloc_func = ZBX_DEFAULT_MEM_MALLOC_FUNC;
	index->realloc_func = ZBX_DEFAULT_MEM_REALLOC_FUNC;
	index->free_func = ZBX_DEFAULT_MEM_FREE_FUNC;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_init_ext                                                  *
 *                                                                            *
 * Purpose: initializes user macro index                                      *
 *                                                                            *
 * Parameters: index        - [IN/OUT] the macro index                        *
 *             malloc_func  - the memory allocation function                  *
 *             realloc_func - the memory reallocation function                *
 *             free_func    - the memory freeing function                     *
 *                                                                            *
 * Comments: Use this function when index must be kept in shared memory.      *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_init_ext(zbx_macro_index_t *index, zbx_mem_malloc_func_t malloc_func,
		zbx_mem_realloc_func_t realloc_func, zbx_mem_free_func_t free_func)
{
	zbx_hashset_create_ext(&index->macros, 0, um_macro_hash_func, um_macro_compare_func, NULL,
			malloc_func, realloc_func, free_func);
	zbx_hashset_create_ext(&index->htmpls, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			NULL, malloc_func, realloc_func, free_func);

	index->malloc_func = malloc_func;
	index->realloc_func = realloc_func;
	index->free_func = free_func;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_clear                                                     *
 *                                                                            *
 * Purpose: clears resources allocated by user macro index                    *
 *                                                                            *
 * Parameters: index  - [IN/OUT] macro index                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_clear(zbx_macro_index_t *index)
{
	zbx_hashset_iter_t	iter;
	ZBX_DC_HTMPL		*htmpl;
	zbx_um_macro_t		*um_macro;

	zbx_hashset_iter_reset(&index->macros, &iter);
	while (NULL != (um_macro = (zbx_um_macro_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_ptr_destroy(&um_macro->macros);
	zbx_hashset_destroy(&index->macros);

	zbx_hashset_iter_reset(&index->htmpls, &iter);
	while (NULL != (htmpl = (ZBX_DC_HTMPL *)zbx_hashset_iter_next(&iter)))
		zbx_vector_uint64_destroy(&htmpl->templateids);
	zbx_hashset_destroy(&index->htmpls);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_add_host_template                                         *
 *                                                                            *
 * Purpose: adds host template link to user macro index                       *
 *                                                                            *
 * Parameters: index      - [IN/OUT] macro index                              *
 *             hostid     - [IN] the host identifier                          *
 *             templateid - [IN] the linked template identifier               *
 *             sort       - [OUT] the list of host templates to be sorted     *
 *                                                                            *
 * Comments: The host template links are used to resolve macros in linked     *
 *           templates.                                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_add_host_template(zbx_macro_index_t *index, zbx_uint64_t hostid, zbx_uint64_t templateid,
		zbx_vector_ptr_t *sort)
{
	ZBX_DC_HTMPL	*htmpl, htmpl_local;

	if (NULL == (htmpl = (ZBX_DC_HTMPL *)zbx_hashset_search(&index->htmpls, &hostid)))
	{
		htmpl_local.hostid = hostid;
		htmpl = (ZBX_DC_HTMPL *)zbx_hashset_insert(&index->htmpls, &htmpl_local, sizeof(htmpl_local));
		zbx_vector_uint64_create_ext(&htmpl->templateids, index->malloc_func, index->realloc_func,
				index->free_func);
	}

	zbx_vector_uint64_append(&htmpl->templateids, templateid);
	zbx_vector_ptr_append(sort, htmpl);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_remove_host_template                                      *
 *                                                                            *
 * Purpose: removes host template link from user macro index                  *
 *                                                                            *
 * Parameters: index      - [IN/OUT] macro index                              *
 *             hostid     - [IN] the host identifier                          *
 *             templateid - [IN] the linked template identifier               *
 *             sort       - [OUT] the list of host templates to be sorted     *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_remove_host_template(zbx_macro_index_t *index, zbx_uint64_t hostid, zbx_uint64_t templateid,
		zbx_vector_ptr_t *sort)
{
	ZBX_DC_HTMPL	*htmpl;
	int		idx;

	if (NULL == (htmpl = (ZBX_DC_HTMPL *)zbx_hashset_search(&index->htmpls, &hostid)))
		return;

	if (-1 == (idx = zbx_vector_uint64_search(&htmpl->templateids, templateid, ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		return;

	zbx_vector_uint64_remove_noorder(&htmpl->templateids, idx);
	zbx_vector_ptr_append(sort, htmpl);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_sort_host_templates                                       *
 *                                                                            *
 * Purpose: removes host template link from user macro index                  *
 *                                                                            *
 * Parameters: index      - [IN/OUT] macro index                              *
 *             sort       - [IN] the host templates to sort                   *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_sort_host_templates(zbx_macro_index_t *index, zbx_vector_ptr_t *sort)
{
	int		i;
	ZBX_DC_HTMPL	*htmpl;

	zbx_vector_ptr_sort(sort, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_ptr_uniq(sort, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (i = 0; i < sort->values_num; i++)
	{
		htmpl = (ZBX_DC_HTMPL *)sort->values[i];
		if (0 == htmpl->templateids.values_num)
		{
			zbx_vector_uint64_destroy(&htmpl->templateids);
			zbx_hashset_remove_direct(&index->htmpls, htmpl);
		}
		else
			zbx_vector_uint64_sort(&htmpl->templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_add_macro                                                 *
 *                                                                            *
 * Purpose: adds user macro to index                                          *
 *                                                                            *
 * Parameters: index  - [IN/OUT] macro index                                  *
 *             macro  - [IN] the macro to index                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_add_macro(zbx_macro_index_t *index, zbx_dc_macro_t *macro)
{
	zbx_um_macro_t	*um_macro, um_macro_local;

	um_macro_local.hostid = macro->hostid;
	um_macro_local.name = macro->macro;

	if (NULL == (um_macro = (zbx_um_macro_t *)zbx_hashset_search(&index->macros, &um_macro_local)))
	{
		um_macro = zbx_hashset_insert(&index->macros, &um_macro_local, sizeof(um_macro_local));

		zbx_vector_ptr_create_ext(&um_macro->macros, index->malloc_func, index->realloc_func,
				index->free_func);
	}

	zbx_vector_ptr_append(&um_macro->macros, macro);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_remove_macro                                              *
 *                                                                            *
 * Purpose: adds user macro to index                                          *
 *                                                                            *
 * Parameters: index  - [IN/OUT] macro index                                  *
 *             macro  - [IN] the macro to remove from index                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_remove_macro(zbx_macro_index_t *index, zbx_dc_macro_t *macro)
{
	zbx_um_macro_t	*um_macro, um_macro_local;
	int		idx;

	um_macro_local.hostid = macro->hostid;
	um_macro_local.name = macro->macro;

	if (NULL == (um_macro = (zbx_um_macro_t *)zbx_hashset_search(&index->macros, &um_macro_local)))
	{
		if (FAIL != (idx = zbx_vector_ptr_search(&um_macro->macros, macro, ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			zbx_vector_ptr_remove(&um_macro->macros, idx);

		if (0 == um_macro->macros.values_num)
		{
			zbx_vector_ptr_destroy(&um_macro->macros);
			zbx_hashset_remove(&index->macros, &um_macro_local);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: mi_get_macro_value                                               *
 *                                                                            *
 * Purpose: gets macro value from a host                                      *
 *                                                                            *
 * Parameters: index         - [IN/OUT] the macro index                       *
 *             hostid        - [IN] the host identifier, 0 for global macros  *
 *             name          - [IN] the macro name                            *
 *             context       - [IN] the macro context                         *
 *             value         - [OUT] the context macro value                  *
 *             value_default - [OUT] the base macro value                     *
 *                                                                            *
 * Return value: SUCCEED - the context macro was found and value returned     *
 *               FAIL    - otherwise, even if base macro was found and the    *
 *                         default value returned                             *
 *                                                                            *
 ******************************************************************************/
static int	mi_get_macro_value(zbx_macro_index_t *index, zbx_uint64_t hostid, const char *name,
		const char *context, char **value, char **value_default)
{
	zbx_um_macro_t	*um_macro, um_macro_local;
	int		i;

	um_macro_local.hostid = hostid;
	um_macro_local.name = name;

	if (NULL != (um_macro = (zbx_um_macro_t *)zbx_hashset_search(&index->macros, &um_macro_local)))
	{
		for (i = 0; i < um_macro->macros.values_num; i++)
		{
			zbx_dc_macro_t	*macro = (zbx_dc_macro_t *)um_macro->macros.values[i];

			if (0 == zbx_strcmp_null(macro->context, context))
			{
				*value = zbx_strdup(*value, macro->value);
				return SUCCEED;
			}

			/* check for the default (without parameters) macro value */
			if (NULL == *value_default && NULL != context && NULL == macro->context)
				*value_default = zbx_strdup(*value_default, macro->value);
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: mi_get_host_macro_value                                          *
 *                                                                            *
 * Purpose: gets macro value from a list of hosts and linked templates        *
 *                                                                            *
 * Parameters: index         - [IN/OUT] the macro index                       *
 *             hostids       - [IN] the host identifiers                      *
 *             hostids_num   - [IN] the number of host identifiers            *
 *             name          - [IN] the macro name                            *
 *             context       - [IN] the macro context                         *
 *             value         - [OUT] the context macro value                  *
 *             value_default - [OUT] the base macro value                     *
 *                                                                            *
 * Return value: SUCCEED - the context macro was found and value returned     *
 *               FAIL    - otherwise, even if base macro was found and the    *
 *                         default value returned                             *
 *                                                                            *
 * Comments: This function will recursively call itself to resolved macros    *
 *           in lined templates, returning when context macro found.          *
 *                                                                            *
 ******************************************************************************/
static void	mi_get_host_macro_value(zbx_macro_index_t *index, const zbx_uint64_t *hostids, int hostids_num,
		const char *name, const char *context, char **value, char **value_default)
{
	int			i, j;
	ZBX_DC_HTMPL		*htmpl;
	zbx_vector_uint64_t	templateids;

	if (0 == hostids_num)
		return;

	for (i = 0; i < hostids_num; i++)
	{
		if (SUCCEED == mi_get_macro_value(index, hostids[i], name, context, value, value_default))
			return;
	}

	/* look for the macro in linked templates */

	zbx_vector_uint64_create(&templateids);
	zbx_vector_uint64_reserve(&templateids, 32);

	for (i = 0; i < hostids_num; i++)
	{
		if (NULL != (htmpl = (ZBX_DC_HTMPL *)zbx_hashset_search(&index->htmpls, &hostids[i])))
		{
			for (j = 0; j < htmpl->templateids.values_num; j++)
				zbx_vector_uint64_append(&templateids, htmpl->templateids.values[j]);
		}
	}

	if (0 != templateids.values_num)
	{
		zbx_vector_uint64_sort(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&templateids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		mi_get_host_macro_value(index, templateids.values, templateids.values_num, name, context, value,
				value_default);
	}

	zbx_vector_uint64_destroy(&templateids);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_get_macro_value                                           *
 *                                                                            *
 * Purpose: gets user macro value                                             *
 *                                                                            *
 * Parameters: index       - [IN/OUT] the macro index                         *
 *             hostids     - [IN] the host identifiers                        *
 *             hostids_num - [IN] the number of host identifiers              *
 *             name        - [IN] the macro name                              *
 *             context     - [IN] the macro context                           *
 *             replace_to  - [OUT] the resolved macro value                   *
 *                                                                            *
 * Comments:                                                                  *
 *      User macros should be expanded according to the following priority:   *
 *         1) host context macro                                              *
 *         2) global context macro                                            *
 *         3) host base (default) macro                                       *
 *         4) global base (default) macro                                     *
 *                                                                            *
 *      Try to expand host macros first. If there is no perfect match on      *
 *      the host level, try to expand global macros, passing the default      *
 *      macro value found on the host level, if any.                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_get_macro_value(zbx_macro_index_t *index, const zbx_uint64_t *hostids, int hostids_num,
		const char *name, const char *context, char **replace_to)
{
	char	*value = NULL, *value_default = NULL;

	mi_get_host_macro_value(index, hostids, hostids_num, name, context, &value, &value_default);

	if (NULL == value)
		mi_get_macro_value(index, 0, name, context, &value, &value_default);

	if (NULL != value)
	{
		zbx_free(*replace_to);
		*replace_to = value;

		zbx_free(value_default);
	}
	else if (NULL != value_default)
	{
		zbx_free(*replace_to);
		*replace_to = value_default;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mi_get_host_templates                                        *
 *                                                                            *
 * Purpose: gets host-template links                                          *
 *                                                                            *
 * Parameters: index  - [IN] the macro index                                  *
 *             htmpls - [OUT] the hostid-templateid pairs. This hashset must  *
 *                            be created with uint64_pair_hash_func and       *
 *                            uint64_pair_compare_func functions.             *
 *                                                                            *
 ******************************************************************************/
void	zbx_mi_get_host_templates(zbx_macro_index_t *index, zbx_hashset_t *htmpls)
{
	zbx_hashset_iter_t	iter;
	zbx_uint64_pair_t	ht_local;
	ZBX_DC_HTMPL		*htmpl;
	int			i;

	zbx_hashset_iter_reset(&index->htmpls, &iter);
	while (NULL != (htmpl = (ZBX_DC_HTMPL *)zbx_hashset_iter_next(&iter)))
	{
		ht_local.first = htmpl->hostid;

		for (i = 0; i < htmpl->templateids.values_num; i++)
		{
			ht_local.second = htmpl->templateids.values[i];
			zbx_hashset_insert(htmpls, &ht_local, sizeof(ht_local));
		}
	}
}
