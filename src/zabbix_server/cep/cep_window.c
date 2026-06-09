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

#include "cep_window.h"
#include "zbxalgo.h"
#include "zbxtypes_ext.h"
#include <stdatomic.h>

/* TODO: sync naming with spec */
#define CEP_WINDOW_GROUP_NONE		0
#define CEP_WINDOW_GROUP_HOST		1
#define CEP_WINDOW_GROUP_HOSTGROUP	2
#define CEP_WINDOW_GROUP_TAG		3

static zbx_hash_t	cep_window_hash(const void *a)
{
	const zbx_cep_window_ref_t	*ref = (const zbx_cep_window_ref_t *)a;

	zbx_hash_t	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&ref->ruleid);

	if (CEP_WINDOW_GROUP_NONE == ref->key_type)
		return hash;

	hash = ZBX_DEFAULT_STRING_HASH_ALGO(ref->key_value, strlen(ref->key_value), hash);

	if (CEP_WINDOW_GROUP_TAG != ref->key_type)
		return hash;

	return ZBX_DEFAULT_STRING_HASH_ALGO(ref->key_tag, strlen(ref->key_tag), hash);
}

static int	cep_window_compare(const void *a1, const void *a2)
{
	const zbx_cep_window_ref_t	*ref1 = (const zbx_cep_window_ref_t *)a1;
	const zbx_cep_window_ref_t	*ref2 = (const zbx_cep_window_ref_t *)a2;
	int				ret;

	ZBX_RETURN_IF_NOT_EQUAL(ref1->ruleid, ref2->ruleid);
	ZBX_RETURN_IF_NOT_EQUAL(ref1->key_type, ref2->key_type);

	if (CEP_WINDOW_GROUP_NONE == ref1->key_type)
		return 0;

	if (0 != (ret = strcmp(ref1->key_value, ref2->key_value)))
		return ret;

	if (CEP_WINDOW_GROUP_TAG != ref1->key_type)
		return 0;

	return strcmp(ref1->key_tag, ref2->key_tag);
}

static void	cep_window_release(void *a)
{
	zbx_cep_window_t	*window = (zbx_cep_window_t *)a;
	zbx_cep_event_handle_t	h;

	if (1 != atomic_fetch_sub(&window->refcount, 1))
		return;

	while (NULL != (h = (zbx_cep_event_handle_t)zbx_queue_ptr_pop(&window->hevents)))
		zbx_cep_event_handle_release(h);

	pthread_mutex_destroy(&window->lock);

	zbx_free(window);
}

static void	cep_window_ref_clear(void *a)
{
	zbx_cep_window_ref_t	*ref = (zbx_cep_window_ref_t *)a;

	cep_window_release(ref->window);
}

void	cep_window_index_init(zbx_hashset_t *windows)
{
	zbx_hashset_create_ext(windows, 0, cep_window_hash, cep_window_compare, cep_window_ref_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
}
