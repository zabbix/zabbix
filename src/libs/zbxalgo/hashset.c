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

#include "zbxalgo.h"
#include "algodefs.h"

static void	__hashset_free_entry(zbx_hashset_t *hs, ZBX_HASHSET_ENTRY_T *entry);

#define	CRIT_LOAD_FACTOR	4/5
#define	SLOT_GROWTH_FACTOR	3/2

#define ZBX_HASHSET_DEFAULT_SLOTS	10

/* private hashset functions */

static void	__hashset_free_entry(zbx_hashset_t *hs, ZBX_HASHSET_ENTRY_T *entry)
{
	if (NULL != hs->clean_func)
		hs->clean_func(entry->data);

	hs->mem_free_func(entry);
}

static int	zbx_hashset_init_slots(zbx_hashset_t *hs, size_t init_size)
{
	hs->num_data = 0;

	if (0 < init_size)
	{
		hs->num_slots = next_prime(init_size);

		if (NULL == (hs->slots = (ZBX_HASHSET_ENTRY_T **)hs->mem_malloc_func(NULL, hs->num_slots *
				sizeof(ZBX_HASHSET_ENTRY_T *))))
		{
			return FAIL;
		}

		memset(hs->slots, 0, hs->num_slots * sizeof(ZBX_HASHSET_ENTRY_T *));
	}
	else
	{
		hs->num_slots = 0;
		hs->slots = NULL;
	}

	return SUCCEED;
}

/* public hashset interface */

void	zbx_hashset_create(zbx_hashset_t *hs, size_t init_size,
				zbx_hash_func_t hash_func,
				zbx_compare_func_t compare_func)
{
	zbx_hashset_create_ext(hs, init_size, hash_func, compare_func, NULL,
					ZBX_DEFAULT_MEM_MALLOC_FUNC,
					ZBX_DEFAULT_MEM_REALLOC_FUNC,
					ZBX_DEFAULT_MEM_FREE_FUNC);
}

void	zbx_hashset_create_ext(zbx_hashset_t *hs, size_t init_size,
				zbx_hash_func_t hash_func,
				zbx_compare_func_t compare_func,
				zbx_clean_func_t clean_func,
				zbx_mem_malloc_func_t mem_malloc_func,
				zbx_mem_realloc_func_t mem_realloc_func,
				zbx_mem_free_func_t mem_free_func)
{
	hs->hash_func = hash_func;
	hs->compare_func = compare_func;
	hs->clean_func = clean_func;
	hs->mem_malloc_func = mem_malloc_func;
	hs->mem_realloc_func = mem_realloc_func;
	hs->mem_free_func = mem_free_func;

	zbx_hashset_init_slots(hs, init_size);
}

void	zbx_hashset_destroy(zbx_hashset_t *hs)
{
	ZBX_HASHSET_ENTRY_T	*entry, *next_entry;

	for (int i = 0; i < hs->num_slots; i++)
	{
		entry = hs->slots[i];

		while (NULL != entry)
		{
			next_entry = entry->next;
			__hashset_free_entry(hs, entry);
			entry = next_entry;
		}
	}

	hs->num_data = 0;
	hs->num_slots = 0;

	if (NULL != hs->slots)
	{
		hs->mem_free_func(hs->slots);
		hs->slots = NULL;
	}

	hs->hash_func = NULL;
	hs->compare_func = NULL;
	hs->mem_malloc_func = NULL;
	hs->mem_realloc_func = NULL;
	hs->mem_free_func = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: allocation not less than the required number of slots for hashset *
 *                                                                            *
 * Parameters: hs            - [IN] destination hashset                       *
 *             num_slots_req - [IN] number of required slots                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_hashset_reserve(zbx_hashset_t *hs, int num_slots_req)
{
	if (0 == hs->num_slots)
	{
		/* correction to prevent the second relocation in case the same number of slots is required */
		if (SUCCEED != zbx_hashset_init_slots(hs, MAX(ZBX_HASHSET_DEFAULT_SLOTS,
				num_slots_req * (2 - CRIT_LOAD_FACTOR) + 1)))
		{
			return FAIL;
		}
	}
	else if (num_slots_req >= hs->num_slots * CRIT_LOAD_FACTOR)
	{
		int			inc_slots, new_slot, slot;
		void			*slots;
		ZBX_HASHSET_ENTRY_T	**prev_next, *curr_entry, *tmp;

		inc_slots = next_prime(hs->num_slots * SLOT_GROWTH_FACTOR);

		if (NULL == (slots = hs->mem_realloc_func(hs->slots, inc_slots * sizeof(ZBX_HASHSET_ENTRY_T *))))
			return FAIL;

		hs->slots = (ZBX_HASHSET_ENTRY_T **)slots;

		memset(hs->slots + hs->num_slots, 0, (inc_slots - hs->num_slots) * sizeof(ZBX_HASHSET_ENTRY_T *));

		for (slot = 0; slot < hs->num_slots; slot++)
		{
			prev_next = &hs->slots[slot];
			curr_entry = hs->slots[slot];

			while (NULL != curr_entry)
			{
				if (slot != (new_slot = curr_entry->hash % inc_slots))
				{
					tmp = curr_entry->next;
					curr_entry->next = hs->slots[new_slot];
					hs->slots[new_slot] = curr_entry;

					*prev_next = tmp;
					curr_entry = tmp;
				}
				else
				{
					prev_next = &curr_entry->next;
					curr_entry = curr_entry->next;
				}
			}
		}

		hs->num_slots = inc_slots;
	}

	return SUCCEED;
}

void	*zbx_hashset_insert(zbx_hashset_t *hs, const void *data, size_t size)
{
	return zbx_hashset_insert_ext(hs, data, size, 0, size, ZBX_HASHSET_UNIQ_FALSE);
}

void	*zbx_hashset_insert_ext(zbx_hashset_t *hs, const void *data, size_t size, size_t offset, size_t n,
		zbx_hashset_uniq_t uniq)
{
	int			slot;
	zbx_hash_t		hash;
	ZBX_HASHSET_ENTRY_T	*entry;

	if (0 == hs->num_slots && SUCCEED != zbx_hashset_init_slots(hs, ZBX_HASHSET_DEFAULT_SLOTS))
		return NULL;

	hash = hs->hash_func(data);

	if (ZBX_HASHSET_UNIQ_FALSE == uniq)
	{
		slot = hash % hs->num_slots;
		entry = hs->slots[slot];

		while (NULL != entry)
		{
			if (entry->hash == hash && hs->compare_func(entry->data, data) == 0)
				break;

			entry = entry->next;
		}
	}
	else
		entry = NULL;

	if (NULL == entry)
	{
		if (SUCCEED != zbx_hashset_reserve(hs, hs->num_data + 1))
			return NULL;

		/* recalculate new slot */
		slot = hash % hs->num_slots;

		if (NULL == (entry = (ZBX_HASHSET_ENTRY_T *)hs->mem_malloc_func(NULL, ZBX_HASHSET_ENTRY_OFFSET + size)))
			return NULL;

		if (0 != offset)
			memset(entry->data, 0, offset);
		memcpy((char *)entry->data + offset, (const char *)data + offset, n - offset);
		entry->hash = hash;
		entry->next = hs->slots[slot];
		hs->slots[slot] = entry;
		hs->num_data++;
	}

	return entry->data;
}

void	*zbx_hashset_search(const zbx_hashset_t *hs, const void *data)
{
	int			slot;
	zbx_hash_t		hash;
	ZBX_HASHSET_ENTRY_T	*entry;

	if (0 == hs->num_slots)
		return NULL;

	hash = hs->hash_func(data);

	slot = hash % hs->num_slots;
	entry = hs->slots[slot];

	while (NULL != entry)
	{
		if (entry->hash == hash && hs->compare_func(entry->data, data) == 0)
			break;

		entry = entry->next;
	}

	return (NULL != entry ? entry->data : NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove a hashset entry using comparison with the given data       *
 *                                                                            *
 ******************************************************************************/
void	zbx_hashset_remove(zbx_hashset_t *hs, const void *data)
{
	int			slot;
	zbx_hash_t		hash;
	ZBX_HASHSET_ENTRY_T	*entry;

	if (0 == hs->num_slots)
		return;

	hash = hs->hash_func(data);

	slot = hash % hs->num_slots;
	entry = hs->slots[slot];

	if (NULL != entry)
	{
		if (entry->hash == hash && hs->compare_func(entry->data, data) == 0)
		{
			hs->slots[slot] = entry->next;
			__hashset_free_entry(hs, entry);
			hs->num_data--;
		}
		else
		{
			ZBX_HASHSET_ENTRY_T	*prev_entry;

			prev_entry = entry;
			entry = entry->next;

			while (NULL != entry)
			{
				if (entry->hash == hash && hs->compare_func(entry->data, data) == 0)
				{
					prev_entry->next = entry->next;
					__hashset_free_entry(hs, entry);
					hs->num_data--;
					break;
				}

				prev_entry = entry;
				entry = entry->next;
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove a hashset entry using a data pointer returned to the user  *
 *          by zbx_hashset_insert[_ext]() and zbx_hashset_search() functions  *
 *                                                                            *
 ******************************************************************************/
void	zbx_hashset_remove_direct(zbx_hashset_t *hs, void *data)
{
	int			slot;
	ZBX_HASHSET_ENTRY_T	*data_entry, *iter_entry;

	if (0 == hs->num_slots)
		return;

	data_entry = (ZBX_HASHSET_ENTRY_T *)((char *)data - ZBX_HASHSET_ENTRY_OFFSET);

	slot = data_entry->hash % hs->num_slots;
	iter_entry = hs->slots[slot];

	if (NULL != iter_entry)
	{
		if (iter_entry == data_entry)
		{
			hs->slots[slot] = data_entry->next;
			__hashset_free_entry(hs, data_entry);
			hs->num_data--;
		}
		else
		{
			while (NULL != iter_entry->next)
			{
				if (iter_entry->next == data_entry)
				{
					iter_entry->next = data_entry->next;
					__hashset_free_entry(hs, data_entry);
					hs->num_data--;
					break;
				}

				iter_entry = iter_entry->next;
			}
		}
	}
}

void	zbx_hashset_clear(zbx_hashset_t *hs)
{
	ZBX_HASHSET_ENTRY_T	*entry;

	for (int slot = 0; slot < hs->num_slots; slot++)
	{
		while (NULL != hs->slots[slot])
		{
			entry = hs->slots[slot];
			hs->slots[slot] = entry->next;
			__hashset_free_entry(hs, entry);
		}
	}

	hs->num_data = 0;
}

#define	ITER_START	(-1)
#define	ITER_FINISH	(-2)

void	zbx_hashset_iter_reset(zbx_hashset_t *hs, zbx_hashset_iter_t *iter)
{
	iter->hashset = hs;
	iter->slot = ITER_START;
}

void	*zbx_hashset_iter_next(zbx_hashset_iter_t *iter)
{
	if (ITER_FINISH == iter->slot)
		return NULL;

	if (ITER_START != iter->slot && NULL != iter->entry && NULL != iter->entry->next)
	{
		iter->entry = iter->entry->next;
		return iter->entry->data;
	}

	while (1)
	{
		iter->slot++;

		if (iter->slot == iter->hashset->num_slots)
		{
			iter->slot = ITER_FINISH;
			return NULL;
		}

		if (NULL != iter->hashset->slots[iter->slot])
		{
			iter->entry = iter->hashset->slots[iter->slot];
			return iter->entry->data;
		}
	}
}

void	zbx_hashset_iter_remove(zbx_hashset_iter_t *iter)
{
	if (ITER_START == iter->slot || ITER_FINISH == iter->slot || NULL == iter->entry)
	{
		zabbix_log(LOG_LEVEL_CRIT, "removing a hashset entry through a bad iterator");
		exit(EXIT_FAILURE);
	}

	if (iter->hashset->slots[iter->slot] == iter->entry)
	{
		iter->hashset->slots[iter->slot] = iter->entry->next;
		__hashset_free_entry(iter->hashset, iter->entry);
		iter->hashset->num_data--;

		iter->slot--;
		iter->entry = NULL;
	}
	else
	{
		ZBX_HASHSET_ENTRY_T	*prev_entry = iter->hashset->slots[iter->slot];

		while (prev_entry->next != iter->entry)
			prev_entry = prev_entry->next;

		prev_entry->next = iter->entry->next;
		__hashset_free_entry(iter->hashset, iter->entry);
		iter->hashset->num_data--;

		iter->entry = prev_entry;
	}
}

void	zbx_hashset_const_iter_reset(const zbx_hashset_t *hs, zbx_hashset_const_iter_t *iter)
{
	iter->hashset = hs;
	iter->slot = ITER_START;
}

const void	*zbx_hashset_const_iter_next(zbx_hashset_const_iter_t *iter)
{
	if (ITER_FINISH == iter->slot)
		return NULL;

	if (ITER_START != iter->slot && NULL != iter->entry && NULL != iter->entry->next)
	{
		iter->entry = iter->entry->next;
		return iter->entry->data;
	}

	while (1)
	{
		iter->slot++;

		if (iter->slot == iter->hashset->num_slots)
		{
			iter->slot = ITER_FINISH;
			return NULL;
		}

		if (NULL != iter->hashset->slots[iter->slot])
		{
			iter->entry = iter->hashset->slots[iter->slot];
			return iter->entry->data;
		}
	}
}

/*********************************************************************************
 *                                                                               *
 * Purpose: copy hashset with fixed size entries                                 *
 *                                                                               *
 * Parameters:  dst  - [OUT] destination hashset                                 *
 *              src  - [IN] source hashset                                       *
 *              size - [IN] hashset entry data size                              *
 *                                                                               *
 * Comments: Do NOT use this function with hashsets having variable size entries,*
 *           for example zabbix string pools.                                    *
 *                                                                               *
 *********************************************************************************/
void	zbx_hashset_copy(zbx_hashset_t *dst, const zbx_hashset_t *src, size_t size)
{
	ZBX_HASHSET_ENTRY_T	*entry, **ref;

	*dst = *src;

	dst->slots = (ZBX_HASHSET_ENTRY_T **)dst->mem_malloc_func(NULL, (size_t)dst->num_slots *
			sizeof(ZBX_HASHSET_ENTRY_T *));
	memset(dst->slots, 0, (size_t)dst->num_slots * sizeof(ZBX_HASHSET_ENTRY_T *));

	for (int i = 0; i < src->num_slots; i++)
	{
		if (0 == src->slots[i])
			continue;

		for (ref = &dst->slots[i], entry = src->slots[i]; NULL != entry; entry = entry->next)
		{
			*ref = (ZBX_HASHSET_ENTRY_T *)src->mem_malloc_func(NULL, ZBX_HASHSET_ENTRY_OFFSET + size);
			memcpy(*ref, entry, ZBX_HASHSET_ENTRY_OFFSET + size);
			ref = &(*ref)->next;
		}
	}
}
