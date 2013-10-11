/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#if defined(HAVE_SSL)
#	include <openssl/ssl.h>
#endif

/******************************************************************************
 *                                                                            *
 * Function: init_tls                                                         *
 *                                                                            *
 * Purpose: initialize crypto libraries.                                      *
 *                                                                            *
 ******************************************************************************/
void	init_tls(void)
{
	const char	*__function_name = "init_tls";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#if defined(HAVE_SSL)
	SSL_load_error_strings();
	SSL_library_init();
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
