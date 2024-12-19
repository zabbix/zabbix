/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxautoreg.h"
#include "zbxalgo.h"

ZBX_PTR_VECTOR_IMPL(autoreg_host_ptr, zbx_autoreg_host_t*)

int	zbx_autoreg_host_compare_func(const void *d1, const void *d2)
{
	const zbx_autoreg_host_t  *autoreg_host_1 = (const zbx_autoreg_host_t *)d1;
	const zbx_autoreg_host_t  *autoreg_host_2 = (const zbx_autoreg_host_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(autoreg_host_1->autoreg_hostid, autoreg_host_2->autoreg_hostid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove autoregistration hosts from the autoregistration           *
 *          throttling cache                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_autoreg_host_invalidate_cache(const zbx_vector_autoreg_host_ptr_t *autoreg_hosts)
{
	zbx_vector_str_t	hosts;

	zbx_vector_str_create(&hosts);
	zbx_vector_str_reserve(&hosts, (size_t)autoreg_hosts->values_num);

	for (int i = 0; i < autoreg_hosts->values_num; i++)
		zbx_vector_str_append(&hosts, autoreg_hosts->values[i]->host);

	zbx_dc_config_delete_autoreg_host(&hosts);

	zbx_vector_str_destroy(&hosts);
}
