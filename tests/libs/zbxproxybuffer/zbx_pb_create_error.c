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

/* Same wrap/stub block as the other proxybuffer tests */

FILE	*__real_fopen(const char *path, const char *mode);
FILE	*__wrap_fopen(const char *path, const char *mode);
int	 __real_fclose(FILE *fp);
int	 __wrap_fclose(FILE *fp);

FILE	*__wrap_fopen(const char *path, const char *mode) { return __real_fopen(path, mode); }
int	 __wrap_fclose(FILE *fp) { return __real_fclose(fp); }

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

int	__wrap_close(int fd)				{ ZBX_UNUSED(fd); return 0; }
int	__wrap_stat(const char *path, struct stat *buf)
		{ ZBX_UNUSED(path); ZBX_UNUSED(buf); return -1; }

void	__real_exit(int status);
void	__wrap_exit(int status);
void	__wrap_exit(int status)				{ ZBX_UNUSED(status); __real_exit(EXIT_SUCCESS); }

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
int		__wrap_zbx_db_execute(const char *fmt, ...)	{ ZBX_UNUSED(fmt); return 0; }
void		__wrap_zbx_db_begin(void)			{}
int		__wrap_zbx_db_commit(void)			{ return ZBX_DB_OK; }
int		__wrap_zbx_db_execute_multiple_query(const char *q, const char *f,
		const zbx_vector_uint64_t *v)
		{ ZBX_UNUSED(q); ZBX_UNUSED(f); ZBX_UNUSED(v); return SUCCEED; }

void	zbx_init_library_nix(zbx_get_progname_f get_progname_cb,
		zbx_get_process_info_by_thread_f get_process_info_by_thread_cb)
{
	ZBX_UNUSED(get_progname_cb);
	ZBX_UNUSED(get_process_info_by_thread_cb);
}

void	zbx_backtrace(void) {}

zbx_uint64_t	zbx_dc_get_nextid(const char *t, int n);
void		zbx_dc_config_get_items_by_itemids(void *items, const zbx_uint64_t *itemids,
			int *errcodes, size_t num);
void		zbx_dc_config_clean_items(void *items, int *errcodes, size_t num);

zbx_uint64_t	zbx_dc_get_nextid(const char *t, int n)		{ ZBX_UNUSED(t); ZBX_UNUSED(n); return 0; }

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
	int			mode, create_result, expected_create_result;
	zbx_uint64_t		size;
	zbx_mock_handle_t	hparam;

	ZBX_UNUSED(state);

	mode = zbx_mock_get_parameter_int("in.mode");
	size = (zbx_uint64_t)zbx_mock_get_parameter_int("in.size");
	expected_create_result = zbx_mock_get_parameter_int("out.create_result");

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));

	create_result = zbx_pb_create(mode, size, 0, 0, &error);
	zbx_mock_assert_result_eq("create result", expected_create_result, create_result);

	if (SUCCEED == create_result)
	{
		zbx_pb_mem_info_t	mem_info;
		zbx_pb_state_info_t	state_info;
		char			*mem_error = NULL;

		zbx_pb_init();

		/* test get_mem_info */
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.mem_info_result", &hparam))
		{
			int	expected_mem_result, mem_result;

			zbx_mock_int(hparam, &expected_mem_result);

			mem_result = zbx_pb_get_mem_info(&mem_info, &mem_error);
			zbx_mock_assert_result_eq("mem_info result", expected_mem_result, mem_result);

			if (SUCCEED == mem_result)
			{
				/* memory mode: buffer must report non-zero usage */
				zbx_mock_assert_int_eq("mem_total > 0", 1,
						mem_info.mem_total > 0 ? 1 : 0);
				zbx_mock_assert_int_eq("mem_used > 0",  1,
						mem_info.mem_used  > 0 ? 1 : 0);
			}
			else
			{
				/* disk mode: error message must be set */
				zbx_mock_assert_ptr_ne("mem_error not NULL", NULL, mem_error);
				zbx_free(mem_error);
			}
		}

		/* test get_state_info */
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.state", &hparam))
		{
			int	expected_state;

			zbx_mock_int(hparam, &expected_state);
			zbx_pb_get_state_info(&state_info);
			zbx_mock_assert_int_eq("state", expected_state, state_info.state);
		}

		zbx_pb_destroy();
	}
	else
	{
		/* create failure: error message must be set */
		zbx_mock_assert_ptr_ne("error not NULL on failure", NULL, error);
		zbx_free(error);
	}
}
