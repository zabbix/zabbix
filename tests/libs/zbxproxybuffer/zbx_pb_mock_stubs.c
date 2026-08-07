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
 * Module-specific stubs for the zbxproxybuffer cmocka tests.
 *
 * DB access (zbx_db_select/execute/begin/commit/...), exit() and file I/O are handled by the
 * existing tests/zbxmockdb.c, zbxmockexit.c mocks (linked from libzbxmockdata.a, activated by the
 * --wrap flags below) - they fail the test on any unexpected call instead of silently succeeding.
 *
 * What remains here is only what has no existing mock, proven by unresolved symbols at link time:
 *   - libzbxproxybuffer.a calls the zbx_db_insert_*()/zbx_db_get_maxid_num() family directly (not
 *     through --wrap; that layer lives in libzbxdbhigh, which these tests do not link). All of the
 *     tested code paths run in ZBX_PB_MODE_MEMORY, so these are never actually reached - fail loudly
 *     if that assumption ever breaks.
 *   - zbx_dc_get_nextid()/zbx_dc_config_*(): the config cache is not linked; nextid is a real
 *     stateful counter used by the tests, the rest are unreachable in these tests.
 *   - zbx_init_library_nix()/zbx_backtrace(): required by libzbxmocktest.a; avoids linking libzbxnix.
 *   - __wrap___zbx_shmem_malloc(): deterministic allocation-failure injection for
 *     pb_discovery_add_row_mem()/pb_autoreg_add_row_mem() - the only way to reach their
 *     allocation-failure branches without guessing buffer sizes/allocator chunk overhead.
 */

#include "zbx_pb_mock_stubs.h"

#include "zbxmocktest.h"

#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxnix.h"
#include "zbxshmem.h"

void	zbx_init_library_nix(zbx_get_progname_f get_progname_cb,
		zbx_get_process_info_by_thread_f get_process_info_by_thread_cb)
{
	ZBX_UNUSED(get_progname_cb);
	ZBX_UNUSED(get_process_info_by_thread_cb);
}

void	zbx_backtrace(void)
{
}

static zbx_uint64_t	pb_mock_nextid;

void	zbx_pb_mock_set_nextid(zbx_uint64_t id)
{
	pb_mock_nextid = id;
}

zbx_uint64_t	zbx_dc_get_nextid(const char *table, int num)
{
	zbx_uint64_t	id = pb_mock_nextid;

	ZBX_UNUSED(table);
	pb_mock_nextid += (zbx_uint64_t)num;

	return id;
}

void	zbx_dc_config_get_items_by_itemids(void *items, const zbx_uint64_t *itemids, int *errcodes, size_t num)
{
	ZBX_UNUSED(items);
	ZBX_UNUSED(itemids);
	ZBX_UNUSED(errcodes);
	ZBX_UNUSED(num);

	fail_msg("unexpected zbx_dc_config_get_items_by_itemids() call - not exercised by these tests");
}

void	zbx_dc_config_clean_items(void *items, int *errcodes, size_t num)
{
	ZBX_UNUSED(items);
	ZBX_UNUSED(errcodes);
	ZBX_UNUSED(num);

	fail_msg("unexpected zbx_dc_config_clean_items() call - not exercised by these tests");
}

void	zbx_db_insert_prepare(zbx_db_insert_t *self, const char *table, ...)
{
	ZBX_UNUSED(self);
	ZBX_UNUSED(table);

	fail_msg("unexpected zbx_db_insert_prepare() call - these tests force ZBX_PB_MODE_MEMORY");
}

void	zbx_db_insert_add_values(zbx_db_insert_t *self, ...)
{
	ZBX_UNUSED(self);

	fail_msg("unexpected zbx_db_insert_add_values() call - these tests force ZBX_PB_MODE_MEMORY");
}

int	zbx_db_insert_execute(zbx_db_insert_t *self)
{
	ZBX_UNUSED(self);

	fail_msg("unexpected zbx_db_insert_execute() call - these tests force ZBX_PB_MODE_MEMORY");

	return FAIL;
}

void	zbx_db_insert_clean(zbx_db_insert_t *self)
{
	ZBX_UNUSED(self);

	fail_msg("unexpected zbx_db_insert_clean() call - these tests force ZBX_PB_MODE_MEMORY");
}

void	zbx_db_insert_autoincrement(zbx_db_insert_t *self, const char *field)
{
	ZBX_UNUSED(self);
	ZBX_UNUSED(field);

	fail_msg("unexpected zbx_db_insert_autoincrement() call - these tests force ZBX_PB_MODE_MEMORY");
}

zbx_uint64_t	zbx_db_insert_get_lastid(zbx_db_insert_t *self)
{
	ZBX_UNUSED(self);

	fail_msg("unexpected zbx_db_insert_get_lastid() call - these tests force ZBX_PB_MODE_MEMORY");

	return 0;
}

zbx_uint64_t	zbx_db_get_maxid_num(const char *table, int num)
{
	ZBX_UNUSED(table);
	ZBX_UNUSED(num);

	fail_msg("unexpected zbx_db_get_maxid_num() call - these tests force ZBX_PB_MODE_MEMORY");

	return 0;
}

int	zbx_db_is_null(const char *field)
{
	return (NULL == field) ? SUCCEED : FAIL;
}

static int	pb_mock_fail_alloc_at;
static int	pb_mock_alloc_call_no;

void	zbx_pb_mock_fail_alloc_at(int call_no)
{
	pb_mock_fail_alloc_at = call_no;
	pb_mock_alloc_call_no = 0;
}

void	*__real___zbx_shmem_malloc(const char *file, int line, zbx_shmem_info_t *info, const void *old, size_t size);
void	*__wrap___zbx_shmem_malloc(const char *file, int line, zbx_shmem_info_t *info, const void *old, size_t size);

void	*__wrap___zbx_shmem_malloc(const char *file, int line, zbx_shmem_info_t *info, const void *old, size_t size)
{
	pb_mock_alloc_call_no++;

	if (0 != pb_mock_fail_alloc_at && pb_mock_alloc_call_no == pb_mock_fail_alloc_at)
		return NULL;

	return __real___zbx_shmem_malloc(file, line, info, old, size);
}
