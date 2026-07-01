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

#ifndef ZABBIX_CEP_API_H
#define ZABBIX_CEP_API_H

#include "cep.h"
#include "cep_window.h"
#include "zbx_cep.h"

int	cep_api_create(const char *config_source_ip, char **error);
void	zbx_cep_api_acquire(void);
void	zbx_cep_api_release(void);

void	cep_cache_acquire(zbx_cep_t **);
void	cep_cache_release(zbx_cep_t **);
void	cep_window_pool_acquire(zbx_cep_window_pool_t **pool);
void	cep_window_pool_release(zbx_cep_window_pool_t **pool);

void	cep_post_event_updates(zbx_cep_event_update_t *updates, int updates_num);
void	cep_post_event_handle_action(zbx_cep_event_handle_t *handles, int handles_num, zbx_cep_event_op_t action);

/* statistics */
void	cep_stats_update_events_accessed(zbx_uint64_t value);
void	cep_stats_update_events_processed(zbx_uint64_t value);
void	cep_stats_update_events_discarded(zbx_uint64_t value);
void	cep_stats_collect(zbx_cep_stats_t *stats);

/* server configuration parameters */
const char	*cep_config_get_source_ip(void);

#endif
