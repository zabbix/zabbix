/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "log.h"
#include "zbxnix.h"

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
/******************************************************************************
 *                                                                            *
 * Function: zbx_coredump_disable                                             *
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
