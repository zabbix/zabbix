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

#ifndef ZABBIX_COMPRESS_H
#define ZABBIX_COMPRESS_H

#include "zbxtypes.h"

int	zbx_compress(const char *in, size_t size_in, char **out, size_t *size_out);
int	zbx_uncompress(const char *in, size_t size_in, char *out, size_t *size_out);
const char	*zbx_compress_strerror(void);

#endif
