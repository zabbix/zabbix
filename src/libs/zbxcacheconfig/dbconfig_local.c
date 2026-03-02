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

#include "dbconfig_local.h"
#include "dbconfig_correlation.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcommon.h"

static zbx_dc_config_local_t	*config_local = NULL;

void	zbx_dc_config_local_init(void)
{
	config_local = (zbx_dc_config_local_t *)zbx_malloc(NULL, sizeof(zbx_dc_config_local_t));
	config_local->itservices_num = 0;

	zbx_hashset_create(&config_local->item_tag_links, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	config_local->correlation_cache = correlation_cache_create();
}

void	zbx_dc_config_local_destroy(void)
{
	zbx_hashset_destroy(&config_local->item_tag_links);
	correlation_cache_destroy(config_local->correlation_cache);
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

