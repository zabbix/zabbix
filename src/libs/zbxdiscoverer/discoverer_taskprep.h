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

#ifndef ZABBIX_DISCOVERER_TASKPREP_H_
#define ZABBIX_DISCOVERER_TASKPREP_H_

#include "discoverer_job.h"

#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdiscovery.h"

void	process_rule(zbx_dc_drule_t *drule, zbx_hashset_t *tasks, zbx_hashset_t *check_counts,
		zbx_vector_ds_dcheck_ptr_t *ds_dchecks_common, zbx_vector_iprange_t *ipranges,
		zbx_vector_discoverer_drule_error_t *drule_errors, zbx_vector_uint64_t *err_druleids);

#endif /* ZABBIX_DISCOVERER_TASKPREP_H_ */
