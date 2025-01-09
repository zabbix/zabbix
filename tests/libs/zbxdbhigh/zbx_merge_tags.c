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

#include "dbhigh_test.h"

#include "zbxmocktest.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxcommon.h"
#include "zbxdbhigh.h"

void	zbx_mock_test_entry(void **state)
{
	int			i;
	zbx_vector_db_tag_ptr_t	dst_tags, src_tags, expected_dst_tags;

	ZBX_UNUSED(state);

	zbx_vector_db_tag_ptr_create(&dst_tags);
	zbx_vector_db_tag_ptr_create(&src_tags);
	zbx_vector_db_tag_ptr_create(&expected_dst_tags);

	tags_read("in.dst_tags", &dst_tags);
	tags_read("in.src_tags", &src_tags);
	tags_read("out.dst_tags", &expected_dst_tags);

	(void)zbx_merge_tags(&dst_tags, &src_tags, NULL, NULL);

	zbx_mock_assert_int_eq("Unexpected host tag count", expected_dst_tags.values_num, dst_tags.values_num);
	zbx_vector_db_tag_ptr_sort(&dst_tags, db_tags_and_values_compare);

	for (i = 0; i < dst_tags.values_num; i++)
	{
		zbx_mock_assert_str_eq("Unexpected tag name", expected_dst_tags.values[i]->tag,
				dst_tags.values[i]->tag);
		zbx_mock_assert_str_eq("Unexpected tag value", expected_dst_tags.values[i]->value,
				dst_tags.values[i]->value);
		zbx_mock_assert_int_eq("Unexpected automatic", expected_dst_tags.values[i]->automatic,
				dst_tags.values[i]->automatic);
		zbx_mock_assert_uint64_eq("Unexpected flags", expected_dst_tags.values[i]->flags,
				dst_tags.values[i]->flags);

	}

	zbx_vector_db_tag_ptr_clear_ext(&dst_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&dst_tags);

	zbx_vector_db_tag_ptr_clear_ext(&src_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&src_tags);

	zbx_vector_db_tag_ptr_clear_ext(&expected_dst_tags, zbx_db_tag_free);
	zbx_vector_db_tag_ptr_destroy(&expected_dst_tags);
}
