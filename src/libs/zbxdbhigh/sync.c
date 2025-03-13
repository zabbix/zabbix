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

ZBX_PTR_VECTOR_IMPL(sync_row_ptr, zbx_sync_row_t *)

typedef enum
{
	ZBX_SYNC_SRC,
	ZBX_SYNC_DST
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

void	sync_list_init(zbx_sync_list_t *list)
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
 *                                                                            *
 ******************************************************************************/
void	sync_list_append(zbx_sync_list_t *list, zbx_sync_row_t *row, zbx_sync_role_t role)
{
	zbx_sync_node_t	*node;

	node = (zbx_sync_node_t *)zbx_malloc(NULL, sizeof(zbx_sync_node_t));
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
void	sync_list_remove(zbx_sync_list_t *list, zbx_sync_node_t *node)
{
	if (node != list->head)
		node->prev->next = node->next;
	else
		list->head = node->next;

	if (node != list->tail)
		node->next->prev = node->prev;
	else
		list->tail = node->prev;

	zbx_free(node);
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
		int	ret = strcmp(row1->cols[i], row2->cols[i]);

		if (0 != ret)
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
 * Purpose: initialize a sync rowset                                          *
 *                                                                            *
 * Parameters: rowset   - [OUT] sync rowset to initialize                     *
 *             cols_num - [IN] number of columns in the rowset                *
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
static void	sync_row_free(zbx_sync_row_t *row)
{
	for (int i = 0; i < row->cols_num; i++)
		zbx_free(row->cols[i]);

	zbx_free(row->cols);
	zbx_free(row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear a sync rowset and free allocated memory                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_clear(zbx_sync_rowset_t *rowset)
{
	zbx_vector_sync_row_ptr_clear_ext(&rowset->rows, sync_row_free);
	zbx_vector_sync_row_ptr_destroy(&rowset->rows);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add a new row to the sync rowset                                  *
 *                                                                            *
 * Parameters: rowset - [IN/OUT] sync rowset to add the row to                *
 *             ...    - [IN] column values, first column always being object  *
 *                           identifier (itemid, triggerid ...)               *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_add_row(zbx_sync_rowset_t *rowset, ...)
{
	va_list		args;
	zbx_sync_row_t	*row;
	char		*value;

	row = (zbx_sync_row_t *)zbx_malloc(NULL, sizeof(zbx_sync_row_t));
	row->cols = (char **)zbx_malloc(NULL, sizeof(char *) * rowset->cols_num);
	row->cols_num = rowset->cols_num;
	row->update_num = 0;

	va_start(args, rowset);
	value = va_arg(args, char *);

	ZBX_DBROW2UINT64(row->rowid, value);

	for (int i = 0; i < rowset->cols_num; i++)
	{
		value = va_arg(args, char *);
		row->cols[i] = zbx_strdup(NULL, NULL != value ? value : "");
	}
	va_end(args);

	zbx_vector_sync_row_ptr_append(&rowset->rows, row);
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
	zbx_sync_row_t	*row;

	row = (zbx_sync_row_t *)zbx_malloc(NULL, sizeof(zbx_sync_row_t));
	row->cols = (char **)zbx_malloc(NULL, sizeof(char *) * rowset->cols_num);
	row->cols_num = rowset->cols_num;
	row->rowid = 0;
	row->update_num = rowset->cols_num;

	for (int i = 0; i < rowset->cols_num; i++)
		row->cols[i] = zbx_strdup(NULL, src->cols[i]);

	zbx_vector_sync_row_ptr_append(&rowset->rows, row);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sort rows in a sync rowset                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_sort(zbx_sync_rowset_t *rowset)
{
	zbx_vector_sync_row_ptr_sort(&rowset->rows, sync_row_compare);
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

		if (ZBX_SYNC_SRC == node->role)
		{
			src = node;
			dst = node->next;
		}
		else
		{
			src = node->next;
			dst = node;
		}

		dst->row->update_num = match_level;
		prev = node->prev;
		next = node->next->next;

		if (NULL != prev && NULL != next)
			prev->match_next = abs(sync_row_compare(&prev->row, &next->row));

		for (int i = dst->row->cols_num - 1; i >= dst->row->cols_num - match_level; i--)
			dst->row->cols[i] = zbx_strdup(dst->row->cols[i], src->row->cols[i]);

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

		if (ZBX_SYNC_DST == node->role)
			node->row->update_num = -1;
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
 *           - unmodified rows are discarded                                  *
 *           - rows marked for deletion have update_num set to -1             *
 *           - new rows (to be inserted) are assigned update_num equal to     *
 *             cols_num and rowid 0, with column values copied from source    *
 *           - updated rows have update_num set to the count of modified      *
 *             columns, starting from the last column. Modified column values *
 *             values are copied from the source rowset.                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_sync_rowset_merge(zbx_sync_rowset_t *dst, const zbx_sync_rowset_t *src)
{
	zbx_sync_list_t		sync_list;
	int			i, j;

	zbx_sync_rowset_sort(dst);

	sync_list_init(&sync_list);

	for (i = 0, j = 0; i < src->rows.values_num && j < dst->rows.values_num; )
	{
		if (0 > sync_row_compare(&src->rows.values[i], &dst->rows.values[j]))
			sync_list_append(&sync_list, src->rows.values[i++], ZBX_SYNC_SRC);
		else
			sync_list_append(&sync_list, dst->rows.values[j++], ZBX_SYNC_DST);
	}

	for (int k = i; k < src->rows.values_num; k++)
		sync_list_append(&sync_list, src->rows.values[k], ZBX_SYNC_SRC);

	for (int k = j; k < dst->rows.values_num; k++)
		sync_list_append(&sync_list, dst->rows.values[k], ZBX_SYNC_DST);

	sync_list_prepare(&sync_list);

	for (i = 0; i <= dst->cols_num; i++)
		sync_merge_nodes(&sync_list, i);

	sync_list_flush(&sync_list, dst);

	for (i = 0; i < dst->rows.values_num;)
	{
		if (0 == dst->rows.values[i]->update_num)
		{
			sync_row_free(dst->rows.values[i]);
			zbx_vector_sync_row_ptr_remove_noorder(&dst->rows, i);
		}
		else
			i++;
	}

	zbx_vector_sync_row_ptr_sort(&dst->rows, sync_row_compare_by_rowid);
}
