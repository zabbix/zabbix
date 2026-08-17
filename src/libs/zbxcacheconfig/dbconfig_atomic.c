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

#include "zbxcacheconfig.h"
#include "dbconfig.h"
#include "zbxcommon.h"

/******************************************************************************
 *                                                                            *
 * Purpose: set configuration cache revision                                  *
 *                                                                            *
 * Parameters: revision - [IN] the configuration revision                     *
 *                                                                            *
 * Comments: Used only by configuration syncer, configuration cache must be   *
 *           already locked.                                                  *
 *                                                                            *
 ******************************************************************************/
void	dc_config_set_config_revision(zbx_uint64_t revision)
{
	zbx_dc_config_t	*config = get_dc_config();

#if ATOMIC_LLONG_LOCK_FREE == 2
	atomic_store_explicit(&config->revision.config, revision, memory_order_release);
#else
	config->revision.config = revision;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: get configuration cache revision                                  *
 *                                                                            *
 * Return value: configuration revision                                       *
 *                                                                            *
 * Comments: Either used by configuration syncer or requires configuration    *
 *           cache to be already locked.                                      *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	dc_config_get_config_revision(void)
{
	zbx_dc_config_t	*config = get_dc_config();
	zbx_uint64_t	revision;

#if ATOMIC_LLONG_LOCK_FREE == 2
	revision = atomic_load_explicit(&config->revision.config, memory_order_acquire);
#else
	revision = config->revision.config;
#endif

	return revision;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy configuration cache revision data                            *
 *                                                                            *
 * Parameters: revision - [OUT] the configuration revision data               *
 *                                                                            *
 * Comments: Either used by configuration syncer or requires configuration    *
 *           cache to be already locked.                                      *
 *                                                                            *
 ******************************************************************************/
void	dc_config_get_revision(zbx_dc_revision_t *revision)
{
	zbx_dc_config_t	*config = get_dc_config();

#if ATOMIC_LLONG_LOCK_FREE == 2
	revision->config = atomic_load_explicit(&config->revision.config, memory_order_acquire);

	revision->expression = config->revision.expression;
	revision->autoreg_tls = config->revision.autoreg_tls;
	revision->drules = config->revision.drules;
	revision->upstream = config->revision.upstream;
	revision->upstream_hostmap = config->revision.upstream_hostmap;
	revision->settings_table = config->revision.settings_table;
	revision->connector = config->revision.connector;
	revision->proxy_group = config->revision.proxy_group;
	revision->proxy = config->revision.proxy;
#else
	*revision = config->revision;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: get configuration cache revision                                  *
 *                                                                            *
 * Return value: configuration revision                                       *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_dc_config_get_config_revision(void)
{
	zbx_dc_config_t	*config = get_dc_config();
	zbx_uint64_t	revision;

#if ATOMIC_LLONG_LOCK_FREE == 2
	revision = atomic_load_explicit(&config->revision.config, memory_order_acquire);
#else
	RDLOCK_CACHE_CONFIG_HISTORY;
	revision = config->revision.config;
	UNLOCK_CACHE_CONFIG_HISTORY;
#endif
	return revision;
}

