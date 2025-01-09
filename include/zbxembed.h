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

#ifndef ZABBIX_ZBXEMBED_H
#define ZABBIX_ZBXEMBED_H

#include "zbxjson.h"

#define ZBX_ES_TIMEOUT	10

typedef struct zbx_es_env zbx_es_env_t;

typedef struct
{
	zbx_es_env_t	*env;
}
zbx_es_t;

void	zbx_es_init(zbx_es_t *es);
void	zbx_es_destroy(zbx_es_t *es);
int	zbx_es_init_env(zbx_es_t *es, const char *config_source_ip, char **error);
int	zbx_es_destroy_env(zbx_es_t *es, char **error);
int	zbx_es_is_env_initialized(zbx_es_t *es);
int	zbx_es_init_browser_env(zbx_es_t *es, const char *endpoint, char **error);

int		zbx_es_fatal_error(zbx_es_t *es);
int		zbx_es_compile(zbx_es_t *es, const char *script, char **code, int *size, char **error);
int		zbx_es_execute(zbx_es_t *es, const char *script, const char *code, int size, const char *param,
		char **script_ret, char **error);
void		zbx_es_set_timeout(zbx_es_t *es, int timeout);
void		zbx_es_debug_enable(zbx_es_t *es);
void		zbx_es_debug_disable(zbx_es_t *es);
const char	*zbx_es_debug_info(const zbx_es_t *es);
int		zbx_es_execute_command(const char *command, const char *param, int timeout,
		const char *config_source_ip, char **result, char *error, size_t max_error_len, char **debug);

#endif /* ZABBIX_ZBXEMBED_H */
