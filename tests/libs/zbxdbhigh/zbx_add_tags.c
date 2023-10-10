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

#include "dbhigh_test.h"

#include "zbxmocktest.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxcommon.h"
#include "zbxdbhigh.h"

void	zbx_mock_test_entry(void **state)
{
	int			i;
	zbx_vector_db_tag_ptr_t	host_tags, add_tags, out_host_tags;

	ZBX_UNUSED(state);

	zbx_vector_db_tag_ptr_create(&host_tags);
	zbx_vector_db_tag_ptr_create(&add_tags);
	zbx_vector_db_tag_ptr_create(&out_host_tags);

	tags_read("in.host_tags", &host_tags);
	tags_read("in.add_tags", &add_tags);
	tags_read("out.host_tags", &out_host_tags);

	zbx_add_tags(&host_tags, &add_tags);

	zbx_mock_assert_int_eq("Unexpected host tag count", out_host_tags.values_num, host_tags.values_num);

	for (i = 0; i < host_tags.values_num; i++)
	{
		zbx_mock_assert_str_eq("Unexpected tag name", out_host_tags.values[i]->tag,
				host_tags.values[i]->tag);
		zbx_mock_assert_str_eq("Unexpected tag value", out_host_tags.values[i]->value,
				host_tags.values[i]->value);
		zbx_mock_assert_int_eq("Unexpected automatic", out_host_tags.values[i]->automatic,
				host_tags.values[i]->automatic);
		zbx_mock_assert_uint64_eq("Unexpected flags", out_host_tags.values[i]->flags,
				host_tags.values[i]->flags);
	}

	zbx_vector_db_tag_ptr_clear_ext(&host_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&host_tags);

	zbx_vector_db_tag_ptr_clear_ext(&add_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&add_tags);

	zbx_vector_db_tag_ptr_clear_ext(&out_host_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&out_host_tags);
}
