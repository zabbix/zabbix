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

#include "dbconfigworker.h"

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
