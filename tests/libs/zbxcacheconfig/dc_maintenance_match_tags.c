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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"
#include "zbxmutexs.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "dbconfig.h"
#include "dbconfig_maintenance_test.h"

static int	dc_compare_maintenance_tags(const void *d1, const void *d2)
{
	const zbx_dc_maintenance_tag_t	*tag1 = *(const zbx_dc_maintenance_tag_t * const *)d1;
	const zbx_dc_maintenance_tag_t	*tag2 = *(const zbx_dc_maintenance_tag_t * const *)d2;

	return strcmp(tag1->tag, tag2->tag);
}

static void	get_maintenance_tags(zbx_mock_handle_t handle, zbx_vector_ptr_t *tags)
{
	zbx_mock_error_t		mock_err;
	zbx_mock_handle_t		htag;
	zbx_dc_maintenance_tag_t	*tag;
	const char			*key, *value, *op;

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(handle, &htag))))
	{
		key = zbx_mock_get_object_member_string(htag, "tag");
		value = zbx_mock_get_object_member_string(htag, "value");
		op = zbx_mock_get_object_member_string(htag, "operator");

		tag = (zbx_dc_maintenance_tag_t *)zbx_malloc(NULL, sizeof(zbx_dc_maintenance_tag_t));
		tag->tag = key;
		tag->value = value;

		if (0 == strcmp(op, "like"))
			tag->op = ZBX_MAINTENANCE_TAG_OPERATOR_LIKE;
		else if (0 == strcmp(op, "equal"))
			tag->op = ZBX_MAINTENANCE_TAG_OPERATOR_EQUAL;
		else
			fail_msg("unknown maintenance tag operator '%s'", op);

		zbx_vector_ptr_append(tags, tag);
	}

	zbx_vector_ptr_sort(tags, dc_compare_maintenance_tags);
}

static void	get_tags(zbx_mock_handle_t handle, zbx_vector_tags_ptr_t *tags)
{
	zbx_mock_error_t	mock_err;
	zbx_mock_handle_t	htag;
	zbx_tag_t		*tag;
	const char		*key, *value;

	while (ZBX_MOCK_END_OF_VECTOR != (mock_err = (zbx_mock_vector_element(handle, &htag))))
	{
		key = zbx_mock_get_object_member_string(htag, "tag");
		value = zbx_mock_get_object_member_string(htag, "value");

		tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
		tag->tag = zbx_strdup(NULL, key);
		tag->value = zbx_strdup(NULL, value);

		zbx_vector_tags_ptr_append(tags, tag);
	}

	zbx_vector_tags_ptr_sort(tags, zbx_compare_tags);
}

static void	get_maintenance(zbx_dc_maintenance_t *maintenance)
{
	const char	*tags_evaltype;

	tags_evaltype = zbx_mock_get_parameter_string("in.maintenance.tags_evaltype");

	if (0 == strcasecmp(tags_evaltype, "AND/OR"))
		maintenance->tags_evaltype = ZBX_MAINTENANCE_TAG_EVAL_TYPE_AND_OR;
	else if (0 == strcasecmp(tags_evaltype, "OR"))
		maintenance->tags_evaltype = ZBX_MAINTENANCE_TAG_EVAL_TYPE_OR;
	else
		fail_msg("unknown tags_evaltype value '%s'", tags_evaltype);

	get_maintenance_tags(zbx_mock_get_parameter_handle("in.maintenance.tags"), &maintenance->tags);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_vector_tags_ptr_t	tags;
	zbx_dc_maintenance_t	maintenance;
	int			returned_ret, expected_ret;

	ZBX_UNUSED(state);

	zbx_vector_tags_ptr_create(&tags);
	zbx_vector_ptr_create(&maintenance.tags);

	get_maintenance(&maintenance);
	get_tags(zbx_mock_get_parameter_handle("in.tags"), &tags);

	returned_ret = dc_maintenance_match_tags_test(&maintenance, &tags);
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	zbx_mock_assert_int_eq("dc_maintenance_match_tags return value", expected_ret, returned_ret);

	zbx_vector_ptr_clear_ext(&maintenance.tags, zbx_ptr_free);
	zbx_vector_ptr_destroy(&maintenance.tags);

	zbx_vector_tags_ptr_clear_ext(&tags, zbx_free_tag);
	zbx_vector_tags_ptr_destroy(&tags);
}
