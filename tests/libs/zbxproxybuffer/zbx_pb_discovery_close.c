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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxmutexs.h"
#include "zbxdb.h"
#include "zbxnix.h"
#include "zbxproxybuffer.h"
#include "proxybuffer.h"
#include "pb_discovery.h"

/* Pass-throughs for --wrap=fopen/fclose (in local LDFLAGS) and for all wraps
 * injected by COMMON_WRAP_FUNCS when building via "make tests_build".
 * Defining every __wrap_* symbol here prevents zbxmockdb.o, zbxmockexit.o and
 * zbxmockfile.o from being extracted out of libzbxmockdata.a — their duplicate
 * definitions of zbx_db_fetch / zbx_db_free_result / __wrap_fopen etc. would
 * otherwise conflict with the stubs below.                                     */

/* fopen / fclose — also wrapped explicitly in LDFLAGS */
FILE	*__real_fopen(const char *path, const char *mode);
FILE	*__wrap_fopen(const char *path, const char *mode) { return __real_fopen(path, mode); }
int	 __real_fclose(FILE *fp);
int	 __wrap_fclose(FILE *fp) { return __real_fclose(fp); }

/* open / close / stat — libzbxlog.a references these; --wrap=* is only active
 * via COMMON_WRAP_FUNCS so __real_* do not exist in a direct build.
 * Return failure values — the test never reaches log-file I/O paths anyway. */
int	__wrap_open(const char *path, int oflag, int mode)
		{ ZBX_UNUSED(path); ZBX_UNUSED(oflag); ZBX_UNUSED(mode); return -1; }
int	__wrap_close(int fd)				{ ZBX_UNUSED(fd); return 0; }
int	__wrap_stat(const char *path, struct stat *buf)
		{ ZBX_UNUSED(path); ZBX_UNUSED(buf); return -1; }

/* exit — fatal paths in libzbxproxybuffer.a; redirect to _exit (not wrapped) */
void	__wrap_exit(int status)				{ _exit(status); }

/* DB — libzbxproxybuffer.a calls these; never reached in ZBX_PB_MODE_MEMORY */
zbx_db_result_t	__wrap_zbx_db_select(const char *fmt, ...)
		{ ZBX_UNUSED(fmt); return NULL; }
zbx_db_result_t	__wrap_zbx_db_vselect(const char *fmt, va_list args)
		{ ZBX_UNUSED(fmt); ZBX_UNUSED(args); return NULL; }
zbx_db_result_t	__wrap_zbx_db_select_n(const char *q, int n)
		{ ZBX_UNUSED(q); ZBX_UNUSED(n); return NULL; }
int		__wrap_zbx_db_execute(const char *fmt, ...)	{ ZBX_UNUSED(fmt); return 0; }
void		__wrap_zbx_db_begin(void)			{}
int		__wrap_zbx_db_commit(void)			{ return ZBX_DB_OK; }
int		__wrap_zbx_db_execute_multiple_query(const char *q, const char *f,
		const zbx_vector_uint64_t *v)
		{ ZBX_UNUSED(q); ZBX_UNUSED(f); ZBX_UNUSED(v); return SUCCEED; }

/* Stubs for libzbxmocktest.a (avoids linking libzbxnix) */
void	zbx_init_library_nix(zbx_get_progname_f get_progname_cb,
		zbx_get_process_info_by_thread_f get_process_info_by_thread_cb)
{
	ZBX_UNUSED(get_progname_cb);
	ZBX_UNUSED(get_process_info_by_thread_cb);
}

void	zbx_backtrace(void) {}

/* DC stubs — real cacheconfig not linked; ID counter resets per process */
static zbx_uint64_t	g_nextid;

zbx_uint64_t	zbx_dc_get_nextid(const char *t, int n)
{
	zbx_uint64_t	id = g_nextid;

	ZBX_UNUSED(t);
	g_nextid += (zbx_uint64_t)n;
	return id;
}

/* zbx_dc_config_get_items_by_itemids / zbx_dc_config_clean_items are used only
 * in pb_history.c and are never reached through the discovery close path. */
void	zbx_dc_config_get_items_by_itemids(void *items, const zbx_uint64_t *itemids,
		int *errcodes, size_t num)
{
	ZBX_UNUSED(items);
	ZBX_UNUSED(itemids);
	ZBX_UNUSED(errcodes);
	ZBX_UNUSED(num);
}

void	zbx_dc_config_clean_items(void *items, int *errcodes, size_t num)
{
	ZBX_UNUSED(items);
	ZBX_UNUSED(errcodes);
	ZBX_UNUSED(num);
}

/* DB stubs — memory-mode close never touches the database */
void		zbx_db_insert_prepare(zbx_db_insert_t *s, const char *t, ...)
		{ ZBX_UNUSED(s); ZBX_UNUSED(t); }
void		zbx_db_insert_add_values(zbx_db_insert_t *s, ...)	{ ZBX_UNUSED(s); }
int		zbx_db_insert_execute(zbx_db_insert_t *s)		{ ZBX_UNUSED(s); return SUCCEED; }
void		zbx_db_insert_clean(zbx_db_insert_t *s)		{ ZBX_UNUSED(s); }
void		zbx_db_insert_autoincrement(zbx_db_insert_t *s, const char *f)
		{ ZBX_UNUSED(s); ZBX_UNUSED(f); }
zbx_uint64_t	zbx_db_insert_get_lastid(zbx_db_insert_t *s)		{ ZBX_UNUSED(s); return 0; }
void		zbx_db_begin(void)					{}
int		zbx_db_commit(void)					{ return ZBX_DB_OK; }
int		zbx_db_execute(const char *fmt, ...)			{ ZBX_UNUSED(fmt); return 0; }
zbx_db_result_t	zbx_db_select(const char *fmt, ...)		{ ZBX_UNUSED(fmt); return NULL; }
zbx_db_result_t	zbx_db_select_n(const char *q, int n)
		{ ZBX_UNUSED(q); ZBX_UNUSED(n); return NULL; }
zbx_db_row_t	zbx_db_fetch(zbx_db_result_t r)			{ ZBX_UNUSED(r); return NULL; }
void		zbx_db_free_result(zbx_db_result_t r)			{ ZBX_UNUSED(r); }
zbx_uint64_t	zbx_db_get_maxid_num(const char *t, int n)
		{ ZBX_UNUSED(t); ZBX_UNUSED(n); return 0; }
int		zbx_db_is_null(const char *f)				{ ZBX_UNUSED(f); return SUCCEED; }

void	zbx_mock_test_entry(void **state)
{
	char			*error = NULL;
	zbx_pb_t		*pb;
	zbx_pb_discovery_data_t	*handle;
	zbx_pb_discovery_t	*row;
	const char		*ip, *dns, *value;
	int			i, rows_written, expected_rows, count;
	zbx_list_iterator_t	li;

	ZBX_UNUSED(state);

	ip = zbx_mock_get_parameter_string("in.ip");
	dns = zbx_mock_get_parameter_string("in.dns");
	value = zbx_mock_get_parameter_string("in.value");
	rows_written = zbx_mock_get_parameter_int("in.rows");
	expected_rows = zbx_mock_get_parameter_int("out.rows");

	g_nextid = 1;

	zbx_mock_assert_int_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_int_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, 1024 * 1024, 0, 0, &error));
	zbx_pb_init();

	handle = zbx_pb_discovery_open();

	for (i = 0; i < rows_written; i++)
		zbx_pb_discovery_write_service(handle, 1, 1, ip, dns, 80, 0, value, 1234567890);

	zbx_pb_discovery_close(handle);

	pb = get_pb_data();

	count = 0;
	zbx_list_iterator_init(&pb->discovery, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		zbx_list_iterator_peek(&li, (void **)&row);
		zbx_mock_assert_str_eq("ip",    ip,    row->ip);
		zbx_mock_assert_str_eq("dns",   dns,   row->dns);
		zbx_mock_assert_str_eq("value", value, row->value);
		count++;
	}

	zbx_mock_assert_int_eq("row count", expected_rows, count);

	zbx_pb_destroy();
}
