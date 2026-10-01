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

#ifndef ZABBIX_APM_DECODE_H
#define ZABBIX_APM_DECODE_H

#ifdef __cplusplus
extern "C" {
#endif

#include "apm_dataset.h"
#include "apm_grpc.h"
#include "zbxalgo.h"

void	zbx_apm_request_decode(zbx_apm_request_t request, zbx_apm_request_type_t type, zbx_apm_dataset_t *ds,
		const zbx_vector_tag_t *resource_attrs);

#ifdef __cplusplus
}
#endif


#endif


