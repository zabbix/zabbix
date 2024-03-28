/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_DISCOVERER_TASKPREP_H_
#define ZABBIX_DISCOVERER_TASKPREP_H_

#include "discoverer_job.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"

void	process_rule(zbx_dc_drule_t *drule, zbx_hashset_t *tasks, zbx_hashset_t *check_counts,
		zbx_vector_ds_dcheck_ptr_t *ds_dchecks_common, zbx_vector_iprange_t *ipranges,
		zbx_vector_discoverer_drule_error_t *drule_errors, zbx_vector_uint64_t *err_druleids);

#endif /* ZABBIX_DISCOVERER_TASKPREP_H_ */
