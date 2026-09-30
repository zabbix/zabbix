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

#ifndef ZABBIX_DBCONFIG_LOCAL_H
#define ZABBIX_DBCONFIG_LOCAL_H

#include "dbconfig_cep.h"
#include "dbconfig_correlation.h"
#include "zbxalgo.h"
#include "zbxtypes_ext.h"

typedef struct
{
	zbx_hashset_t			item_tag_links;
	zbx_hashset_t			trigger_depends_links;
	zbx_atomic_int_t		itservices_num;

	zbx_correlation_config_t	*correlation_config;
	zbx_cep_config_t		*cep_config;
}
zbx_dc_config_local_t;

zbx_dc_config_local_t	*dc_local(void);

#endif
