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
#include "zbxmockprimitive.h"
#include "zbxdbhigh.h"

#define MOCK_INDENT	"                                                                        "

static zbx_vector_sync_row_ptr_t	mock_read_zbx_vector_sync_row_ptr(zbx_mock_handle_t handle, int cols_num);
static zbx_sync_row_t	*mock_read_zbx_sync_row(zbx_mock_handle_t handle, int cols_num);
static zbx_sync_rowset_t	mock_read_zbx_sync_rowset(zbx_mock_handle_t handle);

static void	mock_assert_eq_zbx_vector_sync_row_ptr(const char *prefix, zbx_vector_sync_row_ptr_t *v1,
	zbx_vector_sync_row_ptr_t *v2);
static void	mock_assert_eq_zbx_sync_rowset(const char *prefix, zbx_sync_rowset_t *v1, zbx_sync_rowset_t *v2);
static void	mock_assert_eq_zbx_sync_row(const char *prefix, const zbx_sync_row_t *v1, const zbx_sync_row_t *v2);

static void	mock_dump_zbx_vector_sync_row_ptr(const char *name, zbx_vector_sync_row_ptr_t *v, int indent);
static void	mock_dump_zbx_sync_rowset(const char *name, zbx_sync_rowset_t *v, int indent);
static void	mock_dump_zbx_sync_row(const char *name, zbx_sync_row_t *v, int indent);

static zbx_vector_sync_row_ptr_t	mock_read_zbx_vector_sync_row_ptr(zbx_mock_handle_t handle, int cols_num)
{
	zbx_vector_sync_row_ptr_t	v;
	zbx_mock_handle_t		h;

	zbx_vector_sync_row_ptr_create(&v);
	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(handle, &h))
	{
		zbx_sync_row_t	*o;

		o = mock_read_zbx_sync_row(h, cols_num);
		zbx_vector_sync_row_ptr_append(&v, o);
	}
	return v;
}

static zbx_sync_rowset_t	mock_read_zbx_sync_rowset(zbx_mock_handle_t handle)
{
	zbx_sync_rowset_t	v;
	zbx_mock_handle_t	h;

	h = zbx_mock_get_object_member_handle(handle, "cols_num");
	v.cols_num = mock_read_int(h);

	h = zbx_mock_get_object_member_handle(handle, "rows");
	v.rows = mock_read_zbx_vector_sync_row_ptr(h, v.cols_num);

	return v;
}

static zbx_sync_row_t	*mock_read_zbx_sync_row(zbx_mock_handle_t handle, int cols_num)
{
	zbx_sync_row_t		*v;
	zbx_mock_handle_t	h, hval;
	zbx_mock_error_t	err;

	v = (zbx_sync_row_t *)zbx_malloc(NULL, sizeof(zbx_sync_row_t));

	v->cols_num = cols_num;

	h = zbx_mock_get_object_member_handle(handle, "rowid");
	v->rowid = mock_read_zbx_uint64(h);

	h = zbx_mock_get_object_member_handle(handle, "flags");
	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_uint64(h, &v->flags)))
		fail_msg("Cannot read flags: %s", zbx_mock_error_string(err));

	v->cols = (char **)zbx_malloc(NULL, sizeof(char *) * cols_num);
	v->cols_orig = (char **)zbx_malloc(NULL, sizeof(char *) * cols_num);
	memset(v->cols_orig, 0, sizeof(char *) * cols_num);

	h = zbx_mock_get_object_member_handle(handle, "cols");
	for (int i = 0; ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(h, &hval); i++)
		v->cols[i] = mock_read_char_ptr(hval);

	return v;
}

static void	mock_assert_eq_zbx_vector_sync_row_ptr(const char *prefix, zbx_vector_sync_row_ptr_t *v1,
		zbx_vector_sync_row_ptr_t *v2)
{
	char	buf[MAX_STRING_LEN];

	zbx_snprintf(buf, sizeof(buf), "%s: length check", prefix);
	zbx_mock_assert_int_eq(buf, v1->values_num, v2->values_num);

	for (int i = 0; i < v1->values_num; i++)
	{
		zbx_snprintf(buf, sizeof(buf), "%s: [%d]", prefix, i);
		mock_assert_eq_zbx_sync_row(buf, v1->values[i], v2->values[i]);
	}
}

static void	mock_assert_eq_zbx_sync_rowset(const char *prefix, zbx_sync_rowset_t *v1, zbx_sync_rowset_t *v2)
{
	char	buf[MAX_STRING_LEN];

	zbx_snprintf(buf, sizeof(buf), "%s: .rows", prefix);
	mock_assert_eq_zbx_vector_sync_row_ptr(buf, &v1->rows, &v2->rows);
	zbx_snprintf(buf, sizeof(buf), "%s: .cols_num", prefix);
	mock_assert_eq_int(buf, &v1->cols_num, &v2->cols_num);
}

static void	mock_assert_eq_zbx_sync_row(const char *prefix, const zbx_sync_row_t *v1, const zbx_sync_row_t *v2)
{
	char	buf[MAX_STRING_LEN];

	zbx_snprintf(buf, sizeof(buf), "%s: .rowid", prefix);
	mock_assert_eq_zbx_uint64(buf, &v1->rowid, &v2->rowid);
	zbx_snprintf(buf, sizeof(buf), "%s: .cols_num", prefix);
	mock_assert_eq_int(buf, &v1->cols_num, &v2->cols_num);

	for (int i = 0; i < v1->cols_num; i++)
	{
		zbx_snprintf(buf, sizeof(buf), "%s: .cols[%d]", prefix, i);
		zbx_mock_assert_str_eq(buf, v1->cols[i], v2->cols[i]);
	}

	zbx_snprintf(buf, sizeof(buf), "%s: .flags", prefix);
	mock_assert_eq_zbx_uint64(buf, &v1->flags, &v2->flags);
}

static void	mock_dump_zbx_vector_sync_row_ptr(const char *name, zbx_vector_sync_row_ptr_t *v, int indent)
{
	printf("%.*s%s (%d):\n", indent * 2, MOCK_INDENT, name, v->values_num);
	for (int i = 0; i < v->values_num; i++)
	{
		char	idx[MAX_ID_LEN];

		zbx_snprintf(idx, sizeof(idx), "[%d]", i);
		mock_dump_zbx_sync_row(idx, v->values[i], indent + 1);
	}
}

static void	mock_dump_zbx_sync_rowset(const char *name, zbx_sync_rowset_t *v, int indent)
{
	printf("%.*s%s:\n", indent * 2, MOCK_INDENT, name);
	mock_dump_zbx_vector_sync_row_ptr("rows", &v->rows, indent + 1);
	mock_dump_int("cols_num", &v->cols_num, indent + 1);
}

static void	mock_dump_zbx_sync_row(const char *name, zbx_sync_row_t *v, int indent)
{
	printf("%.*s%s:\n", indent * 2, MOCK_INDENT, name);
	mock_dump_zbx_uint64("rowid", &v->rowid, indent + 1);
	mock_dump_int("cols_num", &v->cols_num, indent + 1);
	for (int i = 0; i < v->cols_num; i++)
	{
		mock_dump_char_ptr("", &v->cols[i], indent + 2);
	}
	mock_dump_zbx_uint64("flags", &v->flags, indent + 1);
}

void	zbx_mock_test_entry(void **state)
{
	zbx_sync_rowset_t	dst;
	zbx_sync_rowset_t	out;
	zbx_sync_rowset_t	src;

	ZBX_UNUSED(state);

	dst = mock_read_zbx_sync_rowset(zbx_mock_get_parameter_handle("in.dst"));
	src = mock_read_zbx_sync_rowset(zbx_mock_get_parameter_handle("in.src"));
	out = mock_read_zbx_sync_rowset(zbx_mock_get_parameter_handle("out.dst"));

	zbx_sync_rowset_sort_by_rows(&src);

	zbx_sync_rowset_merge(&dst, &src);

	mock_dump_zbx_sync_rowset("out", &dst, 0);

	mock_assert_eq_zbx_sync_rowset("zbx_sync_rowset_merge()", &out, &dst);

	zbx_sync_rowset_clear(&src);
	zbx_sync_rowset_clear(&dst);
	zbx_sync_rowset_clear(&out);
}
