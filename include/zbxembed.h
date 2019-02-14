/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_EMBED_H
#define ZABBIX_EMBED_H

typedef struct zbx_es_env zbx_es_env_t;

typedef struct
{
	zbx_es_env_t	*env;
}
zbx_es_t;

void	zbx_es_init(zbx_es_t *es);
void	zbx_es_destroy(zbx_es_t *es);
int	zbx_es_init_env(zbx_es_t *es, char **error);
int	zbx_es_destroy_env(zbx_es_t *es, char **error);
int	zbx_es_is_env_initialized(zbx_es_t *es);
int	zbx_es_fatal_error(zbx_es_t *es);
int	zbx_es_compile(zbx_es_t *es, const char *script, char **code, int *size, char **error);
int	zbx_es_execute(zbx_es_t *es, const char *script, const char *code, int size, const char *param, char **output,
	char **error);

#endif /* ZABBIX_EMBED_H */
