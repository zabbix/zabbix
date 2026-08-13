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

#include "history_curl.h"

#if defined(HAVE_LIBCURL)

#include "zbxstr.h"

size_t	history_curl_recv(void *ptr, size_t size, size_t nmemb, void *userdata)
{
	size_t	r_size = size * nmemb;

	zbx_httppage_t	*page = (zbx_httppage_t	*)userdata;

	zbx_str_memcpy_alloc(&page->data, &page->alloc, &page->offset, ptr, r_size);

	return r_size;
}

#endif
