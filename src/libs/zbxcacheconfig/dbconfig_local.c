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

#include "dbconfig_local.h"
#include "dbconfig_correlation.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"
#include "zbxtypes_ext.h"

static zbx_dc_config_local_t	*config_local = NULL;
static zbx_atomic_uint32_t	config_local_refcount = 0;

void	zbx_dc_config_local_init(void)
{
	config_local = (zbx_dc_config_local_t *)zbx_malloc(NULL, sizeof(zbx_dc_config_local_t));
	config_local->itservices_num = 0;

	zbx_hashset_create(&config_local->item_tag_links, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&config_local->trigger_depends_links, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	config_local->correlation_config = correlation_config_create();

	atomic_fetch_add(&config_local_refcount, 1);
}

/******************************************************************************
 *                                                                            *
 * Purpose: increment local configuration cache reference count               *
 *                                                                            *
 * Comments: Must be called during startup of each thread-based component     *
 *           that uses the configuration cache.                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_local_acquire(void)
{
	atomic_fetch_add(&config_local_refcount, 1);
}

/******************************************************************************
 *                                                                            *
 * Purpose: decrement local configuration cache reference count and free      *
 *          resources when the last reference is released                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_dc_config_local_release(void)
{
	if (1 != atomic_fetch_sub(&config_local_refcount, 1))
		return;

	zbx_hashset_destroy(&config_local->trigger_depends_links);
	zbx_hashset_destroy(&config_local->item_tag_links);
	correlation_config_destroy(config_local->correlation_config);
	zbx_free(config_local);
}

zbx_dc_config_local_t	*dc_local(void)
{
	if (NULL == config_local)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("local configuration cache accessed by external process");
		exit(EXIT_FAILURE);
	}

	return config_local;
}

void	zbx_dc_local_set_itservices_num(int num)
{
	atomic_store(&dc_local()->itservices_num, num);
}

int	zbx_dc_local_get_itservices_num(void)
{
	return atomic_load(&dc_local()->itservices_num);
}

