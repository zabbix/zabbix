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
#include "zbxlog.h"

/* log_type defaults to ZBX_LOG_TYPE_UNDEFINED (0) until zbx_open_log() is called, which this test    */
/* never does - that drives zbx_log_dispatch_formatted() into its stderr/zbx_error() fallback branch, */
/* which is exercised here instead of the real syslog()/ReportEvent() branch.                         */

static char	captured[MAX_BUFFER_LEN + 4096];
static int	captured_calls = 0;

void	__wrap_zbx_error(const char *fmt, ...) __zbx_attr_format_printf(1, 2);

void	__wrap_zbx_error(const char *fmt, ...)
{
	va_list	args;

	captured_calls++;

	va_start(args, fmt);
	zbx_vsnprintf(captured, sizeof(captured), fmt, args);
	va_end(args);
}

static void	call_dispatch(int level, const char *component, const char *fmt, ...)
{
	va_list	args;

	va_start(args, fmt);
	zbx_log_dispatch_formatted(level, component, fmt, args);
	va_end(args);
}

static int	level_from_string(const char *level_str)
{
	if (0 == strcmp(level_str, "LOG_LEVEL_CRIT"))
		return LOG_LEVEL_CRIT;
	if (0 == strcmp(level_str, "LOG_LEVEL_ERR"))
		return LOG_LEVEL_ERR;
	if (0 == strcmp(level_str, "LOG_LEVEL_WARNING"))
		return LOG_LEVEL_WARNING;
	if (0 == strcmp(level_str, "LOG_LEVEL_DEBUG"))
		return LOG_LEVEL_DEBUG;
	if (0 == strcmp(level_str, "LOG_LEVEL_TRACE"))
		return LOG_LEVEL_TRACE;
	if (0 == strcmp(level_str, "LOG_LEVEL_INFORMATION"))
		return LOG_LEVEL_INFORMATION;

	fail_msg("unknown log level \"%s\" in test case", level_str);

	return LOG_LEVEL_EMPTY;
}

void	zbx_mock_test_entry(void **state)
{
	const char		*level_str, *component, *message;
	int			level;
	zbx_mock_handle_t	hparam;

	ZBX_UNUSED(state);

	level_str = zbx_mock_get_parameter_string("in.level");
	component = zbx_mock_get_parameter_string("in.component");
	level = level_from_string(level_str);

	captured[0] = '\0';
	captured_calls = 0;

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.oversized_length", &hparam))
	{
		/* drive a message far larger than MAX_BUFFER_LEN through the real function to confirm it   */
		/* does not overflow the internal message[MAX_BUFFER_LEN] buffer and does not crash.        */
		zbx_uint64_t	length;
		char		*huge;

		length = zbx_mock_get_parameter_uint64("in.oversized_length");
		huge = (char *)zbx_malloc(NULL, length + 1);
		memset(huge, 'y', (size_t)length);
		huge[length] = '\0';

		call_dispatch(level, component, "%s", huge);

		zbx_mock_assert_int_eq("zbx_error must be called exactly once", 1, captured_calls);

		if (strlen(captured) >= sizeof(captured))
			fail_msg("captured output was not NUL-terminated within bounds");

		/* component + up to MAX_BUFFER_LEN-1 message chars + level prefix must still fit */
		if (strlen(captured) > strlen(component) + MAX_BUFFER_LEN + 32)
			fail_msg("captured output length %zu exceeds expected bound", strlen(captured));

		zbx_free(huge);

		return;
	}

	message = zbx_mock_get_parameter_string("in.message");
	call_dispatch(level, component, "%s", message);

	zbx_mock_assert_int_eq("zbx_error must be called exactly once", 1, captured_calls);
	zbx_mock_assert_str_eq("captured output must match expected", zbx_mock_get_parameter_string("out.captured"),
			captured);
}
