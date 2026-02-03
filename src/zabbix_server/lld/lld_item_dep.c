/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "lld.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxstr.h"

#define	ZBX_DEPENDENT_ITEM_MAX_COUNT	30000
#define	ZBX_DEPENDENT_ITEM_MAX_LEVELS	3

typedef struct zbx_lld_item_node zbx_lld_item_node_t;

ZBX_PTR_VECTOR_DECL(lld_item_node_ptr, zbx_lld_item_node_t *)
ZBX_PTR_VECTOR_IMPL(lld_item_node_ptr, zbx_lld_item_node_t *)

struct zbx_lld_item_node
{
	zbx_lld_item_ref_t	self;
	zbx_lld_item_node_t	*parent;

	int			descendants[ZBX_DEPENDENT_ITEM_MAX_LEVELS];
};

static zbx_hash_t	lld_item_node_hash(const void *data)
{
	const zbx_lld_item_node_t	*node = (const zbx_lld_item_node_t *)data;

	if (0 != node->self.itemid)
		return ZBX_DEFAULT_UINT64_HASH_FUNC(&node->self.itemid);

	return ZBX_DEFAULT_PTR_HASH_FUNC(&node->self.item);
}

static int	lld_item_node_compare(const void *d1, const void *d2)
{
	const zbx_lld_item_node_t	*node1 = (const zbx_lld_item_node_t *)d1;
	const zbx_lld_item_node_t	*node2 = (const zbx_lld_item_node_t *)d2;

	if (0 != node1->self.itemid || 0 != node2->self.itemid)
	{
		ZBX_RETURN_IF_NOT_EQUAL(node1->self.itemid, node2->self.itemid);
	}
	else
	{
		ZBX_RETURN_IF_NOT_EQUAL(node1->self.item, node2->self.item);
	}

	return 0;
}

static zbx_lld_item_node_t	*lld_item_dep_get_node(zbx_hashset_t *nodes, zbx_uint64_t itemid, zbx_lld_item_full_t *item)
{
	zbx_lld_item_node_t	node_local = {.self = {.itemid = itemid, .item = item}}, *node;

	if (NULL != (node = (zbx_lld_item_node_t *)zbx_hashset_search(nodes, &node_local)))
		return node;

	node = zbx_hashset_insert(nodes, &node_local, sizeof(node_local));

	return node;
}

static void	lld_item_update_parent_stats(zbx_lld_item_node_t *node, int *stats, int levels)
{
	for (int i = 0; i < levels; i++)
		node->descendants[ZBX_DEPENDENT_ITEM_MAX_LEVELS - levels + i] += stats[i];

	if (1 == levels)
		return;

	if (0 != node->parent)
		lld_item_update_parent_stats(node->parent, stats, levels - 1);
}

static void	lld_item_node_add_parent(zbx_lld_item_node_t *node, zbx_lld_item_node_t *parent_node)
{
	int	stats[ZBX_DEPENDENT_ITEM_MAX_LEVELS] = {1, node->descendants[0], node->descendants[1]};

	node->parent = parent_node;
	lld_item_update_parent_stats(node->parent, stats, ZBX_DEPENDENT_ITEM_MAX_LEVELS);
}

static void	lld_item_dep_add_parent(zbx_hashset_t *nodes, zbx_uint64_t itemid, zbx_lld_item_full_t *item,
		zbx_uint64_t master_itemid, zbx_lld_item_full_t *master_item)
{
	zbx_lld_item_node_t	*node, *parent_node;

	node = lld_item_dep_get_node(nodes, itemid, item);

	if (NULL != node->parent)
		return;

	parent_node = lld_item_dep_get_node(nodes, master_itemid, master_item);
	lld_item_node_add_parent(node, parent_node);
}

static void	lld_item_node_remove_parent(zbx_lld_item_node_t *node)
{
	zbx_lld_item_node_t	*parent_node;

	if (NULL == (parent_node = node->parent))
		return;

	node->parent = NULL;

	int	stats[ZBX_DEPENDENT_ITEM_MAX_LEVELS] = {-1, -node->descendants[0], -node->descendants[1]};

	lld_item_update_parent_stats(parent_node, stats, ZBX_DEPENDENT_ITEM_MAX_LEVELS);
}

typedef enum
{
	ITEM_LINK_UP,
	ITEM_LINK_DOWN
}
zbx_lld_item_dep_link_t;

static void	lld_item_get_itemids(zbx_lld_item_dep_link_t link, zbx_hashset_t *skip_itemids,
		zbx_vector_uint64_t *in_itemids, zbx_vector_uint64_t *out_itemids, zbx_hashset_t *nodes)
{
	size_t	sql_alloc = 0, sql_reset = 0;
	char	*sql = NULL;
	const char	*field;
	int	offset;

	zbx_vector_uint64_sort(in_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(in_itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (ITEM_LINK_UP == link)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_reset, "select master_itemid");
		field = "itemid";
	}
	else
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_reset, "select itemid,master_itemid");
		field = "master_itemid";
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_reset, " from items where flags<>%d and ",
			ZBX_FLAG_DISCOVERY_PROTOTYPE);

	for (offset = 0; offset < in_itemids->values_num; offset += ZBX_DB_LARGE_QUERY_BATCH_SIZE)
	{
		zbx_db_result_t	result;
		zbx_db_row_t	row;
		zbx_uint64_t	itemid;
		size_t		sql_offset = sql_reset;
		int		size;

		size = ZBX_DB_LARGE_QUERY_BATCH_SIZE;
		if (offset + size > in_itemids->values_num)
			size = in_itemids->values_num - offset;

		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, field, in_itemids->values + offset, size);

		result = zbx_db_select("%s", sql);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			int	num_data = skip_itemids->num_data;

			if (SUCCEED == zbx_db_is_null(row[0]))
				continue;

			ZBX_STR2UINT64(itemid, row[0]);

			if (ITEM_LINK_DOWN == link)
			{
				zbx_uint64_t	master_itemid;

				ZBX_STR2UINT64(master_itemid, row[1]);

				lld_item_dep_add_parent(nodes, itemid, NULL, master_itemid, NULL);
			}

			zbx_hashset_insert(skip_itemids, &itemid, sizeof(itemid));

			/* itemid was already in hashset, skip it */
			if (num_data == skip_itemids->num_data)
				continue;

			zbx_vector_uint64_append(out_itemids, itemid);
		}
		zbx_db_free_result(result);
	}

	zbx_free(sql);
}

static void	lld_item_get_linked_items(const zbx_vector_uint64_t *itemids, zbx_hashset_t *nodes)
{
	zbx_vector_uint64_t	parent_itemids, child_itemids;
	zbx_hashset_t		skip_itemids;

	zbx_hashset_create(&skip_itemids, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_create(&parent_itemids);
	zbx_vector_uint64_create(&child_itemids);

	zbx_vector_uint64_append_array(&parent_itemids, itemids->values, itemids->values_num);

	while (0 != parent_itemids.values_num || 0 != child_itemids.values_num)
	{
		zbx_vector_uint64_t	upids, downids;

		zbx_vector_uint64_create(&upids);
		zbx_vector_uint64_create(&downids);

		if (0 != parent_itemids.values_num)
		{
			lld_item_get_itemids(ITEM_LINK_UP, &skip_itemids, &parent_itemids, &upids, nodes);

			zbx_vector_uint64_append_array(&child_itemids, parent_itemids.values,
					parent_itemids.values_num);
		}

		if (0 != child_itemids.values_num)
			lld_item_get_itemids(ITEM_LINK_DOWN, &skip_itemids, &child_itemids, &downids, nodes);

		zbx_vector_uint64_destroy(&parent_itemids);
		zbx_vector_uint64_destroy(&child_itemids);

		parent_itemids = upids;
		child_itemids = downids;
	}

	zbx_vector_uint64_destroy(&child_itemids);
	zbx_vector_uint64_destroy(&parent_itemids);
	zbx_hashset_destroy(&skip_itemids);
}

static zbx_lld_item_full_t	*lld_item_get_master_item(zbx_lld_item_full_t *item,
		zbx_vector_lld_item_prototype_ptr_t *item_prototypes, zbx_hashset_t *items_index)
{
	zbx_lld_item_prototype_t	proto_local;
	zbx_lld_item_index_t		*item_index, item_index_local;

	proto_local.itemid = item->master_itemid;

	if (FAIL == zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &proto_local,
			lld_item_prototype_compare_func))
	{
		return NULL;
	}

	item_index_local.parent_itemid = item->master_itemid;
	item_index_local.lld_row = (zbx_lld_row_t *)item->lld_row;

	if (NULL == (item_index = (zbx_lld_item_index_t *)zbx_hashset_search(items_index, &item_index_local)))
		return NULL;

	return item_index->item;
}

static void	lld_item_get_dependent_nodes(zbx_vector_lld_item_full_ptr_t *items,
		zbx_vector_lld_item_prototype_ptr_t *item_prototypes, zbx_hashset_t *items_index, zbx_hashset_t *nodes)
{
	zbx_lld_item_full_t	*item;
	zbx_vector_uint64_t	itemids;

	zbx_vector_uint64_create(&itemids);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*master_item;

		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == item->itemid)
		{
			if (ITEM_TYPE_DEPENDENT != item->type || 0 == item->master_itemid)
				continue;
		}
		else
		{
			if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_MASTER_ITEM))
				continue;

			zbx_vector_uint64_append(&itemids, item->itemid);
		}

		if (0 == item->master_itemid)
			continue;

		if (NULL == (master_item = lld_item_get_master_item(item, item_prototypes, items_index)))
			zbx_vector_uint64_append(&itemids, item->master_itemid);
		else
			zbx_vector_uint64_append(&itemids, master_item->itemid);
	}

	if (0 != itemids.values_num)
	{
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		lld_item_get_linked_items(&itemids, nodes);
	}

	zbx_vector_uint64_destroy(&itemids);
}


static int	lld_item_node_get_level(zbx_lld_item_node_t *node)
{
	int	level;

	for (level = 1; NULL != node->parent; node = node->parent)
		level++;

	return level;
}

static int	lld_item_node_get_depth(zbx_lld_item_node_t *node)
{
	int	depth = 0;

	for (int i = 0; i < ZBX_DEPENDENT_ITEM_MAX_LEVELS; i++)
	{
		if (0 != node->descendants[i])
			depth = i + 1;
	}

	return depth;
}

static int	lld_item_node_get_tree_size(zbx_lld_item_node_t *node)
{
	for (; NULL != node->parent; node = node->parent)
		;

	return 1 + node->descendants[0] + node->descendants[1] + node->descendants[2];
}

static void	lld_item_node_update_parent(zbx_vector_lld_item_full_ptr_t *items,
		zbx_vector_lld_item_prototype_ptr_t *item_prototypes, zbx_hashset_t *items_index, zbx_hashset_t *nodes,
		char **error)
{
	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i], *master_item;
		zbx_uint64_t		master_itemid;
		zbx_lld_item_node_t	*node, *parent_node = NULL;

		node = lld_item_dep_get_node(nodes, item->itemid, item);

		if (0 != item->master_itemid)
		{
			int	depth, total;

			if (NULL != (master_item = lld_item_get_master_item(item, item_prototypes, items_index)))
			{
				if (0 == (master_item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
				{
					item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
					continue;
				}

				master_itemid = master_item->itemid;
			}
			else
				master_itemid = item->master_itemid;

			parent_node = lld_item_dep_get_node(nodes, master_itemid, master_item);

			depth = lld_item_node_get_level(parent_node);
			depth += lld_item_node_get_depth(node);

			if (ZBX_DEPENDENT_ITEM_MAX_LEVELS < depth)
			{
				item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
				lld_update_dependent_item_discovery(item, 0);

				*error = zbx_strdcatf(*error, "Cannot %s item \"%s\": too many dependency levels.\n",
						(0 != item->itemid ? "update" : "create"), item->key);
				continue;
			}

			total = lld_item_node_get_tree_size(node);
			total += lld_item_node_get_tree_size(parent_node);

			if (ZBX_DEPENDENT_ITEM_MAX_COUNT < total)
			{
				item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
				lld_update_dependent_item_discovery(item, 0);

				*error = zbx_strdcatf(*error, "Cannot %s item \"%s\": too many dependent items.\n",
						(0 != item->itemid ? "update" : "create"), item->key);
				continue;
			}
		}

		if (NULL != node->parent)
			lld_item_node_remove_parent(node);

		if (NULL != parent_node)
			lld_item_node_add_parent(node, parent_node);
	}
}

void	lld_update_dependent_items_and_validate(zbx_vector_lld_item_full_ptr_t *items,
		zbx_vector_lld_item_prototype_ptr_t *item_prototypes, zbx_hashset_t *items_index, char **error)
{
	zbx_hashset_t			nodes;
	zbx_vector_lld_item_full_ptr_t	moved_items, unlinked_items, linked_items, new_items;

	zbx_hashset_create(&nodes, 0, lld_item_node_hash, lld_item_node_compare);

	zbx_vector_lld_item_full_ptr_create(&moved_items);
	zbx_vector_lld_item_full_ptr_create(&unlinked_items);
	zbx_vector_lld_item_full_ptr_create(&linked_items);
	zbx_vector_lld_item_full_ptr_create(&new_items);

	lld_item_get_dependent_nodes(items, item_prototypes, items_index, &nodes);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 != item->itemid)
		{
			if (0 != (item->flags & ZBX_FLAG_LLD_ITEM_UPDATE_MASTER_ITEM))
			{
				if (0 != item->master_itemid)
				{
					if (0 != item->master_itemid_orig)
						zbx_vector_lld_item_full_ptr_append(&moved_items, item);
					else
						zbx_vector_lld_item_full_ptr_append(&linked_items, item);
				}
				else
					zbx_vector_lld_item_full_ptr_append(&unlinked_items, item);
			}

			continue;
		}

		if (ITEM_TYPE_DEPENDENT == item->type)
			zbx_vector_lld_item_full_ptr_append(&new_items, item);
	}

	/* prioritize parent removal to avoid hitting false positive depth limits */

	lld_item_node_update_parent(&unlinked_items, item_prototypes, items_index, &nodes, error);
	lld_item_node_update_parent(&moved_items, item_prototypes, items_index, &nodes, error);
	lld_item_node_update_parent(&linked_items, item_prototypes, items_index, &nodes, error);
	lld_item_node_update_parent(&new_items, item_prototypes, items_index, &nodes, error);

	zbx_vector_lld_item_full_ptr_destroy(&new_items);
	zbx_vector_lld_item_full_ptr_destroy(&linked_items);
	zbx_vector_lld_item_full_ptr_destroy(&unlinked_items);
	zbx_vector_lld_item_full_ptr_destroy(&moved_items);

	zbx_hashset_destroy(&nodes);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reset discovery flags for all dependent item tree                 *
 *                                                                            *
 *****************************************************************************/
void	lld_update_dependent_item_discovery(zbx_lld_item_full_t *item, zbx_uint64_t reset_flags)
{
	if (0 == reset_flags && 0 != (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
		return;

	for (int i = 0; i < item->dependent_items.values_num; i++)
	{
		zbx_lld_item_full_t	*dep = item->dependent_items.values[i];

		dep->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
		lld_update_dependent_item_discovery(item->dependent_items.values[i], ZBX_FLAG_LLD_ITEM_DISCOVERED);
	}
}
