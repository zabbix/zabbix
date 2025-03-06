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
#include "src/zabbix_server/lld/lld.h"

#include "src/zabbix_server/lld/lld_rule.c"

#define MOCK_INDENT	"                                                                        "

static zbx_vector_lld_ext_macro_t	mock_read_zbx_vector_lld_ext_macro(zbx_mock_handle_t handle);
static zbx_vector_lld_macro_t	mock_read_zbx_vector_lld_macro(zbx_mock_handle_t handle);
static zbx_lld_ext_macro_t	mock_read_zbx_lld_ext_macro(zbx_mock_handle_t handle);
static zbx_lld_macro_t	mock_read_zbx_lld_macro(zbx_mock_handle_t handle);

static void	mock_clear_zbx_vector_lld_ext_macro(zbx_vector_lld_ext_macro_t *v);
static void	mock_clear_zbx_vector_lld_macro(zbx_vector_lld_macro_t *v);
static void	mock_clear_zbx_lld_ext_macro(zbx_lld_ext_macro_t *v);
static void	mock_clear_zbx_lld_macro(zbx_lld_macro_t *v);

static void	mock_assert_eq_zbx_vector_lld_ext_macro(const char *prefix, zbx_vector_lld_ext_macro_t *v1,
		zbx_vector_lld_ext_macro_t *v2);
static void	mock_assert_eq_zbx_lld_ext_macro(const char *prefix, zbx_lld_ext_macro_t *v1, zbx_lld_ext_macro_t *v2);

static void	mock_dump_zbx_vector_lld_ext_macro(const char *name, zbx_vector_lld_ext_macro_t *v, int indent);
static void	mock_dump_zbx_vector_lld_macro(const char *name, zbx_vector_lld_macro_t *v, int indent);
static void	mock_dump_zbx_lld_ext_macro(const char *name, zbx_lld_ext_macro_t *v, int indent);
static void	mock_dump_zbx_lld_macro(const char *name, zbx_lld_macro_t *v, int indent);

static zbx_vector_lld_ext_macro_t	mock_read_zbx_vector_lld_ext_macro(zbx_mock_handle_t handle)
{
	zbx_vector_lld_ext_macro_t	v;
	zbx_mock_handle_t	h;

	zbx_vector_lld_ext_macro_create(&v);
	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(handle, &h))
	{
		zbx_lld_ext_macro_t	o;

		o = mock_read_zbx_lld_ext_macro(h);
		zbx_vector_lld_ext_macro_append(&v, o);
	}
	return v;
}

static zbx_vector_lld_macro_t	mock_read_zbx_vector_lld_macro(zbx_mock_handle_t handle)
{
	zbx_vector_lld_macro_t	v;
	zbx_mock_handle_t	h;

	zbx_vector_lld_macro_create(&v);
	while (ZBX_MOCK_END_OF_VECTOR != zbx_mock_vector_element(handle, &h))
	{
		zbx_lld_macro_t	o;

		o = mock_read_zbx_lld_macro(h);
		zbx_vector_lld_macro_append(&v, o);
	}
	return v;
}

static zbx_lld_ext_macro_t	mock_read_zbx_lld_ext_macro(zbx_mock_handle_t handle)
{
	zbx_lld_ext_macro_t	v;
	zbx_mock_handle_t	h;
	zbx_mock_error_t	err;

	h = zbx_mock_get_object_member_handle(handle, "lld_macroid");
	v.lld_macroid = mock_read_zbx_uint64(h);

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_object_member(handle, "name", &h)))
		v.name = NULL;
	else
		v.name = mock_read_char_ptr(h);

	if (ZBX_MOCK_SUCCESS != (err = zbx_mock_object_member(handle, "value", &h)))
		v.value = NULL;
	else
		v.value = mock_read_char_ptr(h);

	return v;
}

static zbx_lld_macro_t	mock_read_zbx_lld_macro(zbx_mock_handle_t handle)
{
	zbx_lld_macro_t		v;
	zbx_mock_handle_t	h;

	h = zbx_mock_get_object_member_handle(handle, "macro");
	v.macro = mock_read_char_ptr(h);
	h = zbx_mock_get_object_member_handle(handle, "value");
	v.value = mock_read_char_ptr(h);
	return v;
}

static void	mock_clear_zbx_vector_lld_ext_macro(zbx_vector_lld_ext_macro_t *v)
{
	for (int i = 0; i < v->values_num; i++)
		mock_clear_zbx_lld_ext_macro(&v->values[i]);

	zbx_vector_lld_ext_macro_destroy(v);
}

static void	mock_clear_zbx_vector_lld_macro(zbx_vector_lld_macro_t *v)
{
	for (int i = 0; i < v->values_num; i++)
		mock_clear_zbx_lld_macro(&v->values[i]);

	zbx_vector_lld_macro_destroy(v);
}

static void	mock_clear_zbx_lld_ext_macro(zbx_lld_ext_macro_t *v)
{
	mock_clear_zbx_uint64(&v->lld_macroid);
	mock_clear_char_ptr(&v->name);
	mock_clear_char_ptr(&v->value);
}

static void	mock_clear_zbx_lld_macro(zbx_lld_macro_t *v)
{
	mock_clear_char_ptr(&v->macro);
	mock_clear_char_ptr(&v->value);
}

static void	mock_assert_eq_zbx_vector_lld_ext_macro(const char *prefix, zbx_vector_lld_ext_macro_t *v1,
		zbx_vector_lld_ext_macro_t *v2)
{
	char	buf[MAX_STRING_LEN];

	zbx_snprintf(buf, sizeof(buf), "%s: length check", prefix);
	zbx_mock_assert_int_eq(buf, v1->values_num, v2->values_num);

	for (int i = 0; i < v1->values_num; i++)
	{
		zbx_snprintf(buf, sizeof(buf), "%s: [%d]", prefix, i);
		mock_assert_eq_zbx_lld_ext_macro(buf, &v1->values[i], &v2->values[i]);
	}
}

static void	mock_assert_eq_zbx_lld_ext_macro(const char *prefix, zbx_lld_ext_macro_t *v1, zbx_lld_ext_macro_t *v2)
{
	char	buf[MAX_STRING_LEN];

	zbx_snprintf(buf, sizeof(buf), "%s: .lld_macroid", prefix);
	mock_assert_eq_zbx_uint64(buf, &v1->lld_macroid, &v2->lld_macroid);

	zbx_snprintf(buf, sizeof(buf), "%s: .name", prefix);
	if (NULL == v1->name || NULL == v2->name)
		zbx_mock_assert_ptr_eq(buf, v1->name, v2->name);
	else
		mock_assert_eq_char_ptr(buf, &v1->name, &v2->name);

	zbx_snprintf(buf, sizeof(buf), "%s: .value", prefix);
	if (NULL == v1->value || NULL == v2->value)
		zbx_mock_assert_ptr_eq(buf, v1->value, v2->value);
	else
		mock_assert_eq_char_ptr(buf, &v1->value, &v2->value);
}

static void	mock_dump_zbx_vector_lld_ext_macro(const char *name, zbx_vector_lld_ext_macro_t *v, int indent)
{
	printf("%.*s%s (%d):\n", indent * 2, MOCK_INDENT, name, v->values_num);
	for (int i = 0; i < v->values_num; i++)
	{
		char	idx[MAX_ID_LEN];

		zbx_snprintf(idx, sizeof(idx), "[%d]", i);
		mock_dump_zbx_lld_ext_macro(idx, &v->values[i], indent + 1);
	}
}

static void	mock_dump_zbx_vector_lld_macro(const char *name, zbx_vector_lld_macro_t *v, int indent)
{
	printf("%.*s%s (%d):\n", indent * 2, MOCK_INDENT, name, v->values_num);
	for (int i = 0; i < v->values_num; i++)
	{
		char	idx[MAX_ID_LEN];

		zbx_snprintf(idx, sizeof(idx), "[%d]", i);
		mock_dump_zbx_lld_macro(idx, &v->values[i], indent + 1);
	}
}

static void	mock_dump_zbx_lld_ext_macro(const char *name, zbx_lld_ext_macro_t *v, int indent)
{
	printf("%.*s%s:\n", indent * 2, MOCK_INDENT, name);
	mock_dump_zbx_uint64("lld_macroid", &v->lld_macroid, indent + 1);
	mock_dump_char_ptr("name", &v->name, indent + 1);
	mock_dump_char_ptr("value", &v->value, indent + 1);
}

static void	mock_dump_zbx_lld_macro(const char *name, zbx_lld_macro_t *v, int indent)
{
	printf("%.*s%s:\n", indent * 2, MOCK_INDENT, name);
	mock_dump_char_ptr("macro", &v->macro, indent + 1);
	mock_dump_char_ptr("value", &v->value, indent + 1);
}


void	zbx_mock_test_entry(void **state)
{
	zbx_vector_lld_ext_macro_t	in, out;
	zbx_vector_lld_macro_t		macros, exported_macros;
	zbx_lld_entry_t			entry;
	zbx_lld_rule_macros_t		rule_macros;

	ZBX_UNUSED(state);

	macros = mock_read_zbx_vector_lld_macro(zbx_mock_get_parameter_handle("in.entry.macros"));
	exported_macros = mock_read_zbx_vector_lld_macro(zbx_mock_get_parameter_handle("in.entry.exported_macros"));
	in = mock_read_zbx_vector_lld_ext_macro(zbx_mock_get_parameter_handle("in.db"));
	out = mock_read_zbx_vector_lld_ext_macro(zbx_mock_get_parameter_handle("out.macros"));

	entry.macros = macros;
	entry.exported_macros = &exported_macros;

	rule_macros.itemid = 0;
	rule_macros.macros = in;

	mock_dump_zbx_vector_lld_ext_macro("Macros in database", &in, 0);
	mock_dump_zbx_vector_lld_macro("LLD macros", &macros, 0);
	mock_dump_zbx_vector_lld_macro("Exported LLD macros", &exported_macros, 0);

	lld_rule_merge_exported_macros(&rule_macros, &entry);

	mock_dump_zbx_vector_lld_ext_macro("Merged macros", &rule_macros.macros, 0);
	mock_assert_eq_zbx_vector_lld_ext_macro("out.macros", &out, &rule_macros.macros);

	mock_clear_zbx_vector_lld_macro(&macros);
	mock_clear_zbx_vector_lld_macro(&exported_macros);
	mock_clear_zbx_vector_lld_ext_macro(&rule_macros.macros);
	mock_clear_zbx_vector_lld_ext_macro(&out);
}
