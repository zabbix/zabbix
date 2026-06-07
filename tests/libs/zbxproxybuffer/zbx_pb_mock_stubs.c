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

/*
 * Shared stubs for all zbxproxybuffer cmocka tests.
 *
 * Defining every __wrap_* symbol here prevents zbxmockdb.o, zbxmockexit.o
 * and zbxmockfile.o from being extracted out of libzbxmockdata.a — their
 * duplicate definitions of zbx_db_fetch / zbx_db_free_result / __wrap_fopen
 * etc. would otherwise conflict with the stubs below.
 *
 * Each test binary lists this file alongside its own test source in _SOURCES;
 * automake compiles a separate object for each binary so there are no
 * multiple-definition problems across binaries.
 */

#include "zbx_pb_mock_stubs.h"

#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxnix.h"

#include <sys/stat.h>

/* ── fopen / fclose ────────────────────────────────────────────────────────── */
FILE	*__real_fopen(const char *path, const char *mode);
FILE	*__wrap_fopen(const char *path, const char *mode);
int	 __real_fclose(FILE *fp);
int	 __wrap_fclose(FILE *fp);

FILE	*__wrap_fopen(const char *path, const char *mode) { return __real_fopen(path, mode); }
int	 __wrap_fclose(FILE *fp)                           { return __real_fclose(fp); }

/* ── open / close / stat ────────────────────────────────────────────────────
 * libzbxlog.a references open/close/stat; --wrap is only active when
 * COMMON_WRAP_FUNCS is injected (tests_build), so __real_* do not exist in
 * a direct local build.  Return failure values — the test never exercises
 * log-file I/O paths anyway.
 * EXCEPTION: pass .gcda paths through to __real_open so the gcov atexit
 * handler can write coverage data; without this, --wrap=open silently blocks
 * all gcov output and no .gcda files are produced.                           */
int	__real_open(const char *path, int oflag, ...);
int	__wrap_open(const char *path, int oflag, ...);
int	__wrap_close(int fd);
int	__wrap_stat(const char *path, struct stat *buf);

int	__wrap_open(const char *path, int oflag, ...)
{
	size_t	len = strlen(path);

	if (len > 5 && 0 == strcmp(path + len - 5, ".gcda"))
	{
		va_list	args;
		int	mode;

		va_start(args, oflag);
		mode = va_arg(args, int);
		va_end(args);
		return __real_open(path, oflag, mode);
	}

	ZBX_UNUSED(oflag);
	return -1;
}

int	__wrap_close(int fd)			       { ZBX_UNUSED(fd);  return 0; }
int	__wrap_stat(const char *path, struct stat *buf){ ZBX_UNUSED(path); ZBX_UNUSED(buf); return -1; }

/* ── exit ───────────────────────────────────────────────────────────────────
 * Fatal paths in libzbxproxybuffer.a call exit(); use __real_exit so gcov
 * atexit handlers flush .gcda files before the process terminates.           */
void	__real_exit(int status);
void	__wrap_exit(int status);
void	__wrap_exit(int status) { ZBX_UNUSED(status); __real_exit(EXIT_SUCCESS); }

/* ── __wrap_zbx_db_* ────────────────────────────────────────────────────────
 * libzbxproxybuffer.a calls these in DB/hybrid mode only; they are never
 * reached when running tests in ZBX_PB_MODE_MEMORY.                          */
zbx_db_result_t	__wrap_zbx_db_select(const char *fmt, ...);
zbx_db_result_t	__wrap_zbx_db_vselect(const char *fmt, va_list args);
zbx_db_result_t	__wrap_zbx_db_select_n(const char *q, int n);
int		__wrap_zbx_db_execute(const char *fmt, ...);
void		__wrap_zbx_db_begin(void);
int		__wrap_zbx_db_commit(void);
int		__wrap_zbx_db_execute_multiple_query(const char *q, const char *f,
		const zbx_vector_uint64_t *v);

zbx_db_result_t	__wrap_zbx_db_select(const char *fmt, ...)
		{ ZBX_UNUSED(fmt); return NULL; }
zbx_db_result_t	__wrap_zbx_db_vselect(const char *fmt, va_list args)
		{ ZBX_UNUSED(fmt); ZBX_UNUSED(args); return NULL; }
zbx_db_result_t	__wrap_zbx_db_select_n(const char *q, int n)
		{ ZBX_UNUSED(q); ZBX_UNUSED(n); return NULL; }
int		__wrap_zbx_db_execute(const char *fmt, ...) { ZBX_UNUSED(fmt); return 0; }
void		__wrap_zbx_db_begin(void)		    {}
int		__wrap_zbx_db_commit(void)		    { return ZBX_DB_OK; }
int		__wrap_zbx_db_execute_multiple_query(const char *q, const char *f,
		const zbx_vector_uint64_t *v)
		{ ZBX_UNUSED(q); ZBX_UNUSED(f); ZBX_UNUSED(v); return SUCCEED; }

/* ── zbx_init_library_nix / zbx_backtrace ──────────────────────────────────
 * Required by libzbxmocktest.a; avoids linking libzbxnix.                    */
void	zbx_init_library_nix(zbx_get_progname_f get_progname_cb,
		zbx_get_process_info_by_thread_f get_process_info_by_thread_cb)
{
	ZBX_UNUSED(get_progname_cb);
	ZBX_UNUSED(get_process_info_by_thread_cb);
}

void	zbx_backtrace(void) {}

/* ── DC stubs ───────────────────────────────────────────────────────────────
 * Real cacheconfig is not linked; ID counter resets per process invocation.  */
zbx_uint64_t	g_nextid;

zbx_uint64_t	zbx_dc_get_nextid(const char *t, int n);
void		zbx_dc_config_get_items_by_itemids(void *items, const zbx_uint64_t *itemids,
			int *errcodes, size_t num);
void		zbx_dc_config_clean_items(void *items, int *errcodes, size_t num);

zbx_uint64_t	zbx_dc_get_nextid(const char *t, int n)
{
	zbx_uint64_t	id = g_nextid;

	ZBX_UNUSED(t);
	g_nextid += (zbx_uint64_t)n;
	return id;
}

void	zbx_dc_config_get_items_by_itemids(void *items, const zbx_uint64_t *itemids,
		int *errcodes, size_t num)
{
	ZBX_UNUSED(items); ZBX_UNUSED(itemids); ZBX_UNUSED(errcodes); ZBX_UNUSED(num);
}

void	zbx_dc_config_clean_items(void *items, int *errcodes, size_t num)
{
	ZBX_UNUSED(items); ZBX_UNUSED(errcodes); ZBX_UNUSED(num);
}

/* ── direct zbx_db_* stubs ──────────────────────────────────────────────────
 * zbx_db_insert_* and the non-wrapped DB functions are called directly
 * (not through --wrap); stubs here prevent the real libzbxdb from being
 * required at link time.                                                      */
void		zbx_db_insert_prepare(zbx_db_insert_t *s, const char *t, ...)
		{ ZBX_UNUSED(s); ZBX_UNUSED(t); }
void		zbx_db_insert_add_values(zbx_db_insert_t *s, ...)  { ZBX_UNUSED(s); }
int		zbx_db_insert_execute(zbx_db_insert_t *s)          { ZBX_UNUSED(s); return SUCCEED; }
void		zbx_db_insert_clean(zbx_db_insert_t *s)            { ZBX_UNUSED(s); }
void		zbx_db_insert_autoincrement(zbx_db_insert_t *s, const char *f)
		{ ZBX_UNUSED(s); ZBX_UNUSED(f); }
zbx_uint64_t	zbx_db_insert_get_lastid(zbx_db_insert_t *s)       { ZBX_UNUSED(s); return 0; }
void		zbx_db_begin(void)                                  {}
int		zbx_db_commit(void)                                 { return ZBX_DB_OK; }
int		zbx_db_execute(const char *fmt, ...)                { ZBX_UNUSED(fmt); return 0; }
zbx_db_result_t	zbx_db_select(const char *fmt, ...)             { ZBX_UNUSED(fmt); return NULL; }
zbx_db_result_t	zbx_db_select_n(const char *q, int n)
		{ ZBX_UNUSED(q); ZBX_UNUSED(n); return NULL; }
zbx_db_row_t	zbx_db_fetch(zbx_db_result_t r)                 { ZBX_UNUSED(r); return NULL; }
void		zbx_db_free_result(zbx_db_result_t r)              { ZBX_UNUSED(r); }
zbx_uint64_t	zbx_db_get_maxid_num(const char *t, int n)
		{ ZBX_UNUSED(t); ZBX_UNUSED(n); return 0; }
int		zbx_db_is_null(const char *f)                       { ZBX_UNUSED(f); return SUCCEED; }
