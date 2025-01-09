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

#include "zbxcommon.h"

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#include "zbxnix.h"

/******************************************************************************
 *                                                                            *
 * Purpose: disable core dump                                                 *
 *                                                                            *
 * Return value: SUCCEED - core dump disabled                                 *
 *               FAIL - error                                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_coredump_disable(void)
{
	struct rlimit	limit;

	limit.rlim_cur = 0;
	limit.rlim_max = 0;

	if (0 != setrlimit(RLIMIT_CORE, &limit))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set resource limit: %s", zbx_strerror(errno));
		return FAIL;
	}

	return SUCCEED;
}
#endif
