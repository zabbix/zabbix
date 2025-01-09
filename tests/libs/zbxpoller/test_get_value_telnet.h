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

#ifndef POLLER_GET_VALUE_TELNET_TEST_H
#define POLLER_GET_VALUE_TELNET_TEST_H

#include "zbxcacheconfig.h"

int	zbx_get_value_telnet_test_run(zbx_dc_item_t *item, const char *config_ssh_key_location, char **error);

#endif /*POLLER_GET_VALUE_TELNET_TEST_H*/
