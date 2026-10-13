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

#ifndef ZABBIX_APM_CONFIG_H
#define ZABBIX_APM_CONFIG_H

#include "zbxalgo.h"
#include "zbxtypes.h"
#include "zbxtypes_ext.h"

#define APM_STATUS_DISABLED	0
#define APM_STATUS_ENABLED	1

typedef enum
{
	ZBX_APM_CONFIG_SET = 1,
	ZBX_APM_CONFIG_DEFAULT
}
zbx_apm_config_state_t;

typedef struct
{
	zbx_vector_tag_t	metrics;
	zbx_vector_tag_t	logs;
	zbx_vector_tag_t	traces;

	zbx_atomic_uint32_t	refcount;
}
zbx_apm_config_attrs_t;

typedef struct
{
	zbx_uint64_t		revision;
	zbx_uint64_t		quota;
	int			enabled;

	zbx_apm_config_state_t	state;

	zbx_apm_config_attrs_t	*attrs;
}
zbx_apm_config_t;

zbx_apm_config_attrs_t	*apm_config_attrs_acquire(zbx_apm_config_attrs_t *attrs);
void	apm_config_attrs_release(zbx_apm_config_attrs_t *attrs);

void	apm_config_init(zbx_apm_config_t *cfg);
void	apm_config_clear(zbx_apm_config_t *cfg);
void	apm_config_reset(zbx_apm_config_t *cfg);
int	apm_config_set(zbx_apm_config_t *cfg, char *apm_config, zbx_uint64_t revision);

typedef struct
{
	const char	*ca_file;
	const char	*cert_file;
	const char	*key_file;
}
zbx_apm_config_tls_t;

#endif
