/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxdbconfigworker.h"
#include "zbxserialize.h"
#include "zbxalgo.h"

void	zbx_dbconfig_worker_serialize_ids(unsigned char **data, size_t *data_offset, const zbx_vector_uint64_t *ids)
{
	zbx_uint32_t	data_len = 0, vector_uint64_len;

	zbx_serialize_prepare_vector_uint64_len(data_len, ids, vector_uint64_len);

	*data = (unsigned char *)zbx_malloc(NULL, data_len);

	*data_offset = data_len;

	zbx_serialize_vector_uint64(*data, ids, vector_uint64_len);
}

void	zbx_dbconfig_worker_deserialize_ids(const unsigned char *data, zbx_uint32_t size,
		zbx_vector_uint64_t *ids)
{
	zbx_uint32_t	vector_uint64_len;

	if (0 != size)
		(void)zbx_deserialize_vector_uint64(data, ids, vector_uint64_len);
}
