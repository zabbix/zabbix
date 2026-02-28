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
#include "zbxcep.h"

int	cep_api_init(char **error);
void	cep_api_destroy(void);
void	cep_cache_acquire(zbx_cep_t **);
void	cep_cache_release(zbx_cep_t **);
void	cep_post_event_updates(zbx_cep_event_update_t *updates, int updates_num);
void	cep_post_event_handle_action(zbx_cep_event_handle_t *handles, int handles_num, zbx_cep_event_op_t action);

#endif
