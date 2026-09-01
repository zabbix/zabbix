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

#ifndef ZABBIX_TRAPPER_PUSH_TEST_H
#define ZABBIX_TRAPPER_PUSH_TEST_H

#include "zbxdb.h"

int	trapper_process_push_test(const char *sendto, const char *subject, const char *message,
		zbx_uint64_t mediatypeid, unsigned char type, const zbx_db_row_t row, unsigned short smtp_port,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, unsigned char message_format, char **error, char **debug);

#endif
