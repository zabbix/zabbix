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

#ifndef POLLER_GET_VALUE_SSH_TEST_H
#define POLLER_GET_VALUE_SSH_TEST_H

#include "zbxcacheconfig.h"

#if defined(HAVE_SSH2) || defined(HAVE_SSH)
int	zbx_get_value_ssh_test_run(zbx_dc_item_t *item, char **error);
#endif	/* defined(HAVE_SSH2) || defined(HAVE_SSH) */

#endif /*POLLER_GET_VALUE_SSH_TEST_H*/
