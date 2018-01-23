/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#ifndef ZABBIX_MOCK_HELPER_H
#define ZABBIX_MOCK_HELPER_H

#include "common.h"

int		zbx_read_yaml_expected_ret(void);
zbx_uint64_t	zbx_read_yaml_expected_uint64(const char *out);

char		*zbx_yaml_assemble_binary_sequence(const char *in, size_t expected);

#endif
