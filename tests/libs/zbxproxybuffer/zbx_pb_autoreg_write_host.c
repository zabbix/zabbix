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
#include "zbxjson.h"
#include "zbxmutexs.h"
#include "zbxdb.h"
#include "zbxnix.h"
#include "zbxproxybuffer.h"
#include "proxybuffer.h"

/* Same wrap/stub block as zbx_pb_discovery_close.c */

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

static zbx_uint64_t	g_nextid;

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
	zbx_pb_t		*pb;
	zbx_pb_autoreg_t	*row;
	const char		*host, *ip, *dns, *host_metadata;
	int			i, rows_written, expected_rows, count, port, flags;
	zbx_list_iterator_t	li;
	struct zbx_json		j;
	zbx_uint64_t		lastid = 0;
	int			more, rows_got;

	ZBX_UNUSED(state);

	host          = zbx_mock_get_parameter_string("in.host");
	ip            = zbx_mock_get_parameter_string("in.ip");
	dns           = zbx_mock_get_parameter_string("in.dns");
	host_metadata = zbx_mock_get_parameter_string("in.host_metadata");
	port          = zbx_mock_get_parameter_int("in.port");
	flags         = zbx_mock_get_parameter_int("in.flags");
	rows_written  = zbx_mock_get_parameter_int("in.rows");
	expected_rows = zbx_mock_get_parameter_int("out.rows");

	g_nextid = 1;

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, 1024 * 1024, 0, 0, &error));
	zbx_pb_init();

	for (i = 0; i < rows_written; i++)
	{
		/* connection_type=1 == ZBX_TCP_SEC_UNENCRYPTED (zbxcomms.h not included) */
		zbx_pb_autoreg_write_host(host, ip, dns, (unsigned short)port,
				1, host_metadata, flags, 1234567890);
	}

	pb = get_pb_data();

	/* verify row fields in the memory buffer */
	count = 0;
	zbx_list_iterator_init(&pb->autoreg, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		zbx_list_iterator_peek(&li, (void **)&row);
		zbx_mock_assert_str_eq("host",          host,          row->host);
		zbx_mock_assert_str_eq("listen_ip",     ip,            row->listen_ip);
		zbx_mock_assert_str_eq("listen_dns",    dns,           row->listen_dns);
		zbx_mock_assert_str_eq("host_metadata", host_metadata, row->host_metadata);
		zbx_mock_assert_int_eq("listen_port",   port,          row->listen_port);
		zbx_mock_assert_int_eq("flags",         flags,         row->flags);
		count++;
	}

	zbx_mock_assert_int_eq("row count", expected_rows, count);

	/* test get_rows: must return the same count */
	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_autoreg_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("get_rows count", expected_rows, rows_got);
	zbx_json_free(&j);

	/* test set_lastid: must clear the buffer so a subsequent get_rows returns 0 */
	zbx_pb_autoreg_set_lastid(lastid);

	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_autoreg_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("get_rows after set_lastid", 0, rows_got);
	zbx_json_free(&j);

	zbx_pb_destroy();
}
