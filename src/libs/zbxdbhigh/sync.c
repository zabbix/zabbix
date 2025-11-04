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

#include "zbxdbhigh.h"
#include "zbxalgo.h"
#include "zbxdb.h"

ZBX_PTR_VECTOR_IMPL(sync_row_ptr, zbx_sync_row_t *)

typedef enum
{
	ZBX_SYNC_ROW_SRC,
	ZBX_SYNC_ROW_DST
}
zbx_sync_role_t;

typedef struct zbx_sync_node zbx_sync_node_t;

struct zbx_sync_node
{
	zbx_sync_role_t		role;

	int			match_next;

	zbx_sync_row_t		*row;

	zbx_sync_node_t		*prev;
	zbx_sync_node_t		*next;
};

typedef struct
{
	zbx_sync_node_t	*head;
	zbx_sync_node_t	*tail;
}
zbx_sync_list_t;

static void	sync_list_init(zbx_sync_list_t *list)
{
	list->head = list->tail = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: append a new node to the end of the sync list                     *
 *                                                                            *
 * Parameters: list - [IN/OUT] sync list to append to                         *
 *             row  - [IN] row to be added                                    *
 *             role - [IN] role of the sync row (source or destination)       *
 *             node - [IN/OUT] the node to append                             *
 *                                                                            *
 ******************************************************************************/
static void	sync_list_append(zbx_sync_list_t *list, zbx_sync_row_t *row, zbx_sync_role_t role,
		zbx_sync_node_t *node)
{
	memset(node, 0, sizeof(zbx_sync_node_t));
	node->row = row;
	node->role = role;

	if (NULL != list->tail)
	{
		list->tail->next = node;
		node->prev = list->tail;
		list->tail = node;
	}
	else
		list->head = list->tail = node;
}


/******************************************************************************
 *                                                                            *
 * Purpose: remove a node from the sync list                                  *
 *                                                                            *
 * Parameters: list - [IN/OUT] sync list to remove from                       *
 *             node - [IN] node to be removed                                 *
 *                                                                            *
 ******************************************************************************/
static void	sync_list_remove(zbx_sync_list_t *list, zbx_sync_node_t *node)
{
	if (node != list->head)
		node->prev->next = node->next;
	else
		list->head = node->next;

	if (node != list->tail)
		node->next->prev = node->prev;
	else
		list->tail = node->prev;
}

static int	strcmp_null(const char *s1, const char *s2)
{
	if (NULL == s1)
	{
		if (NULL != s2)
			return -1;
		else
			return 0;
	}

	if (NULL == s2)
		return 1;

	return strcmp(s1, s2);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two rows                                                  *
 *                                                                            *
 * Parameters: d1 - [IN] first sync row                                       *
 *             d2 - [IN] second sync row                                      *
 *                                                                            *
 * Return value: -N - first row is less than the second, where N is the       *
 *                    number of last columns that differ                      *
 *               +N - first row is greater than the second, where N is the    *
 *                    number of last columns that differ                      *
 *               0  - the rows are equal                                      *
 *                                                                            *
 ******************************************************************************/
static int	sync_row_compare(const void *d1, const void *d2)
{
	const zbx_sync_row_t        *row1 = *(const zbx_sync_row_t * const *)d1;
	const zbx_sync_row_t        *row2 = *(const zbx_sync_row_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(row1->cols_num, row2->cols_num);

	for (int i = 0; i < row1->cols_num; i++)
	{
		int	ret;

		if (0 != (ret = strcmp_null(row1->cols[i], row2->cols[i])))
		{
			if (0 > ret)
				return i - row1->cols_num;
			else
				return row1->cols_num - i;
		}
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two sync rows by their rowid                              *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
static int	sync_row_compare_by_rowid(const void *d1, const void *d2)
{
	const zbx_sync_row_t        *row1 = *(const zbx_sync_row_t * const *)d1;
	const zbx_sync_row_t        *row2 = *(const zbx_sync_row_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(row1->rowid, row2->rowid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two sync rows by their rowid                              *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
static int	sync_row_compare_by_parent_rowid(const void *d1, const void *d2)
{
	const zbx_sync_row_t        *row1 = *(const zbx_sync_row_t * const *)d1;
	const zbx_sync_row_t        *row2 = *(const zbx_sync_row_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(row1->parent_rowid, row2->parent_rowid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: roll back changes made to a specific column in a row              *
 *                                                                            *
 * Parameters: row     - [IN/OUT] sync row to be modified                     *
 *             col_num - [IN] number of the column to roll back               *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_row_rollback_col(zbx_sync_row_t *row, int col_num)
{
	if (0 != (row->flags & (UINT32_C(1) << col_num)))
	{
		zbx_free(row->cols[col_num]);
		row->cols[col_num] = row->cols_orig[col_num];
		row->cols_orig[col_num] = NULL;
		row->flags &= ~(UINT32_C(1) << col_num);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize a sync rowset                                          *
 *                                                                            *
 * Parameters: rowset   - [OUT] sync rowset to initialize                     *
 *             cols_num - [IN] number of columns in the rowset excluding the  *
 *                             first id column                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_init(zbx_sync_rowset_t *rowset, int cols_num)
{
	rowset->cols_num = cols_num;
	zbx_vector_sync_row_ptr_create(&rowset->rows);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free memory allocated for a sync row                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_row_free(zbx_sync_row_t *row)
{
	for (int i = 0; i < row->cols_num; i++)
	{
		zbx_free(row->cols[i]);
		zbx_free(row->cols_orig[i]);
	}

	zbx_free(row->cols);
	zbx_free(row->cols_orig);
	zbx_free(row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear a sync rowset and free allocated memory                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_clear(zbx_sync_rowset_t *rowset)
{
	zbx_vector_sync_row_ptr_clear_ext(&rowset->rows, zbx_sync_row_free);
	zbx_vector_sync_row_ptr_destroy(&rowset->rows);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear a sync rowset and free allocated memory                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_clear_ext(zbx_sync_rowset_t *rowset, void (*free_func)(void *data))
{
	for (int i = 0; i < rowset->rows.values_num; i++)
	{
		free_func(rowset->rows.values[i]->data);
		zbx_sync_row_free(rowset->rows.values[i]);
	}

	zbx_vector_sync_row_ptr_destroy(&rowset->rows);
}

static zbx_sync_row_t	*sync_row_create(zbx_uint64_t rowid, int cols_num)
{
	zbx_sync_row_t	*row;

	row = (zbx_sync_row_t *)zbx_malloc(NULL, sizeof(zbx_sync_row_t));
	row->rowid = rowid;
	row->parent_rowid = 0;
	row->cols = (char **)zbx_malloc(NULL, sizeof(char *) * (size_t)cols_num);
	row->cols_orig = (char **)zbx_malloc(NULL, sizeof(char *) * (size_t)cols_num);
	memset(row->cols_orig, 0, sizeof(char *) * (size_t)cols_num);
	row->cols_num = cols_num;
	row->flags = ZBX_SYNC_ROW_NONE;
	row->data = NULL;

	return row;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a new row to the sync rowset                                  *
 *                                                                            *
 * Parameters: rowset - [IN/OUT] sync rowset to add the row to                *
 *             ...    - [IN] column values, first column always being object  *
 *                           identifier (itemid, triggerid ...)               *
 *                                                                            *
 * Return value: added row                                                    *
 *                                                                            *
 ******************************************************************************/
zbx_sync_row_t	*zbx_sync_rowset_add_row(zbx_sync_rowset_t *rowset, ...)
{
	va_list		args;
	zbx_sync_row_t	*row;
	char		*value;
	zbx_uint64_t	rowid;

	va_start(args, rowset);
	value = va_arg(args, char *);

	ZBX_DBROW2UINT64(rowid, value);
	row = sync_row_create(rowid, rowset->cols_num);

	for (int i = 0; i < rowset->cols_num; i++)
	{
		value = va_arg(args, char *);
		row->cols[i] = (NULL != value ? zbx_strdup(NULL, value) : NULL);
	}
	va_end(args);

	zbx_vector_sync_row_ptr_append(&rowset->rows, row);

	return row;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy a row from source to destination rowset                      *
 *                                                                            *
 * Parameters: rowset - [IN/OUT] destination rowset                           *
 *             src    - [IN] source row to be copied                          *
 *                                                                            *
 ******************************************************************************/
static void	sync_rowset_copy_row(zbx_sync_rowset_t *rowset, zbx_sync_row_t *src)
{
	zbx_sync_row_t	*row = sync_row_create(0, rowset->cols_num);

	row->flags = ZBX_SYNC_ROW_INSERT;
	row->parent_rowid = src->rowid;

	for (int i = 0; i < rowset->cols_num; i++)
		row->cols[i] = (NULL != src->cols[i] ? zbx_strdup(NULL, src->cols[i]) : NULL);

	zbx_vector_sync_row_ptr_append(&rowset->rows, row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sort rows in a sync rowset                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_sort_by_rows(zbx_sync_rowset_t *rowset)
{
	zbx_vector_sync_row_ptr_sort(&rowset->rows, sync_row_compare);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sort rows in a sync rowset                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_sort_by_id(zbx_sync_rowset_t *rowset)
{
	zbx_vector_sync_row_ptr_sort(&rowset->rows, sync_row_compare_by_rowid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare sync list by calculating match levels between nodes       *
 *                                                                            *
 * Parameters: sync_list - [IN/OUT] sync list to be prepared                  *
 *                                                                            *
 ******************************************************************************/
static void	sync_list_prepare(zbx_sync_list_t *sync_list)
{
	for (zbx_sync_node_t *node = sync_list->head; node != NULL && NULL != node->next; node = node->next)
		node->match_next = abs(sync_row_compare(&node->row, &node->next->row));
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge nodes in sync list based on specified match level           *
 *                                                                            *
 * Parameters: sync_list   - [IN/OUT] sync list to be processed               *
 *             match_level - [IN] level of matching for merging nodes         *
 *                                                                            *
 * Comments: The result will be stored into matched destination rows.         *
 *                                                                            *
 ******************************************************************************/
static void	sync_merge_nodes(zbx_sync_list_t *sync_list, int match_level)
{
	for (zbx_sync_node_t *node = sync_list->head; node != NULL && NULL != node->next; )
	{
		if (match_level != node->match_next || node->role == node->next->role)
		{
			node = node->next;
			continue;
		}

		zbx_sync_node_t	*src, *dst, *prev, *next;

		if (ZBX_SYNC_ROW_SRC == node->role)
		{
			src = node;
			dst = node->next;
		}
		else
		{
			src = node->next;
			dst = node;
		}

		prev = node->prev;
		next = node->next->next;

		if (NULL != prev && NULL != next)
			prev->match_next = abs(sync_row_compare(&prev->row, &next->row));

		int	diff_col = dst->row->cols_num - match_level;

		for (int i = diff_col; i < dst->row->cols_num; i++)
		{
			if (i == diff_col || 0 != strcmp_null(dst->row->cols[i], src->row->cols[i]))
			{
				dst->row->cols_orig[i] = dst->row->cols[i];
				dst->row->cols[i] = (NULL != src->row->cols[i] ?
						zbx_strdup(NULL, src->row->cols[i]) : NULL);
				dst->row->flags |= UINT32_C(1) << i;
			}
		}

		dst->row->parent_rowid = src->row->rowid;

		sync_list_remove(sync_list, src);
		sync_list_remove(sync_list, dst);

		node = NULL != prev ? prev : sync_list->head;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush remaining nodes from sync list to destination rowset        *
 *                                                                            *
 * Parameters: sync_list - [IN] sync list to be flushed                       *
 *             dst       - [OUT] destination rowset to receive flushed nodes  *
 *                                                                            *
 * Comments: The sync list will contain only nodes of one role - either       *
 *           source or destination.                                           *
 *                                                                            *
 ******************************************************************************/
static void	sync_list_flush(zbx_sync_list_t *sync_list, zbx_sync_rowset_t *dst)
{
	for (zbx_sync_node_t *node = sync_list->head; node != NULL; )
	{
		zbx_sync_node_t	*next = node->next;

		if (ZBX_SYNC_ROW_DST == node->role)
			node->row->flags = ZBX_SYNC_ROW_DELETE;
		else
			sync_rowset_copy_row(dst, node->row);

		sync_list_remove(sync_list, node);
		node = next;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge source rowset into destination rowset                       *
 *                                                                            *
 * Parameters: dst - [IN/OUT] destination rowset/merge data sorted by rowid   *
 *             src - [IN] source rowset                                       *
 *                                                                            *
 * Comments: The merge operation modifies the destination rows as follows:    *
 *           - unmodified rows have flags set to ZBX_SYNC_ROW_NONE            *
 *           - rows marked for deletion have flags set to ZBX_SYNC_ROW_DELETE *
 *           - new rows (to be inserted) have flags set to ZBX_SYNC_ROW_INSERT,*
 *             rowid 0, and column values copied from source                  *
 *           - updated rows have flags set as a bitset indicating which       *
 *             columns were modified. Modified column values are copied       *
 *             from the source rowset                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_merge(zbx_sync_rowset_t *dst, const zbx_sync_rowset_t *src)
{
	zbx_sync_list_t		sync_list;
	zbx_sync_node_t		*nodes;
	int			i, j, next_node = 0;
	size_t			list_len = (size_t)(dst->rows.values_num + src->rows.values_num);

	if (0 == list_len)
		return;

	zbx_sync_rowset_sort_by_rows(dst);

	sync_list_init(&sync_list);
	nodes = (zbx_sync_node_t *)zbx_malloc(NULL, list_len * sizeof(zbx_sync_node_t));

	for (i = 0, j = 0; i < src->rows.values_num && j < dst->rows.values_num; )
	{
		if (0 > sync_row_compare(&src->rows.values[i], &dst->rows.values[j]))
			sync_list_append(&sync_list, src->rows.values[i++], ZBX_SYNC_ROW_SRC, &nodes[next_node++]);
		else
			sync_list_append(&sync_list, dst->rows.values[j++], ZBX_SYNC_ROW_DST, &nodes[next_node++]);
	}

	for (int k = i; k < src->rows.values_num; k++)
		sync_list_append(&sync_list, src->rows.values[k], ZBX_SYNC_ROW_SRC, &nodes[next_node++]);

	for (int k = j; k < dst->rows.values_num; k++)
		sync_list_append(&sync_list, dst->rows.values[k], ZBX_SYNC_ROW_DST, &nodes[next_node++]);

	sync_list_prepare(&sync_list);

	for (i = 0; i <= dst->cols_num; i++)
		sync_merge_nodes(&sync_list, i);

	sync_list_flush(&sync_list, dst);

	zbx_sync_rowset_sort_by_id(dst);

	zbx_free(nodes);
}

/******************************************************************************
 *                                                                            *
 * Purpose: search for a row in a rowset by its row ID                        *
 *                                                                            *
 * Parameters: rowset       - [IN] rowset to search in                        *
 *             parent_rowid - [IN] row ID to search for                       *
 *                                                                            *
 * Return value: pointer to the found row or NULL if not found                *
 *                                                                            *
 ******************************************************************************/
const zbx_sync_row_t	*zbx_sync_rowset_search_by_id(const zbx_sync_rowset_t *rowset, zbx_uint64_t rowid)
{
	zbx_sync_row_t	row_local;
	int		i;

	row_local.rowid = rowid;

	if (FAIL == (i = zbx_vector_sync_row_ptr_search(&rowset->rows, &row_local, sync_row_compare_by_rowid)))
		return NULL;

	return rowset->rows.values[i];
}

/******************************************************************************
 *                                                                            *
 * Purpose: search for a row in a rowset by its parent row ID                 *
 *                                                                            *
 * Parameters: rowset       - [IN] rowset to search in                        *
 *             parent_rowid - [IN] parent row ID to search for                *
 *                                                                            *
 * Return value: pointer to the found row or NULL if not found                *
 *                                                                            *
 ******************************************************************************/
zbx_sync_row_t	*zbx_sync_rowset_search_by_parent(zbx_sync_rowset_t *rowset, zbx_uint64_t parent_rowid)
{
	zbx_sync_row_t	row_local;
	int		i;

	row_local.parent_rowid = parent_rowid;

	if (FAIL == (i = zbx_vector_sync_row_ptr_search(&rowset->rows, &row_local, sync_row_compare_by_parent_rowid)))
		return NULL;

	return rowset->rows.values[i];
}

/******************************************************************************
 *                                                                            *
 * Purpose: discard any changes made to rowset                                *
 *                                                                            *
 * Parameters: rowset - [IN/OUT] sync rowset to be reset                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_rollback(zbx_sync_rowset_t *rowset)
{
	for (int i = 0; i < rowset->rows.values_num; i++)
		rowset->rows.values[i]->flags = ZBX_SYNC_ROW_NONE;
}


/******************************************************************************
 *                                                                            *
 * Purpose: copy rows from source rowset to destination rowset                *
 *                                                                            *
 * Parameters: dst - [OUT] destination rowset                                 *
 *             src - [IN] source rowset                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_copy(zbx_sync_rowset_t *dst, const zbx_sync_rowset_t *src)
{
	for (int i = 0; i < src->rows.values_num; i++)
		sync_rowset_copy_row(dst, src->rows.values[i]);
}
