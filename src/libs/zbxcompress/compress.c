/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxcompress.h"

#include "common.h"

#ifdef HAVE_ZLIB
#include "zlib.h"
#include "log.h"

#define ZBX_COMPRESS_STRERROR_LEN	512

static int	zbx_zlib_errno = 0;

/******************************************************************************
 *                                                                            *
 * Purpose: returns last conversion error message                             *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_compress_strerror(void)
{
	static char	message[ZBX_COMPRESS_STRERROR_LEN];

	switch (zbx_zlib_errno)
	{
		case Z_ERRNO:
			zbx_strlcpy(message, zbx_strerror(errno), sizeof(message));
			break;
		case Z_MEM_ERROR:
			zbx_strlcpy(message, "not enough memory", sizeof(message));
			break;
		case Z_BUF_ERROR:
			zbx_strlcpy(message, "not enough space in output buffer", sizeof(message));
			break;
		case Z_DATA_ERROR:
			zbx_strlcpy(message, "corrupted input data", sizeof(message));
			break;
		default:
			zbx_snprintf(message, sizeof(message), "unknown error (%d)", zbx_zlib_errno);
			break;
	}

	return message;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compress data                                                     *
 *                                                                            *
 * Parameters: in       - [IN] the data to compress                           *
 *             size_in  - [IN] the input data size                            *
 *             out      - [OUT] the compressed data                           *
 *             size_out - [OUT] the compressed data size                      *
 *                                                                            *
 * Return value: SUCCEED - the data was compressed successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: In the case of success the output buffer must be freed by the    *
 *           caller.                                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_compress(const char *in, size_t size_in, char **out, size_t *size_out)
{
	Bytef	*buf;
	uLongf	buf_size;

	buf_size = compressBound(size_in);
	buf = (Bytef *)zbx_malloc(NULL, buf_size);

	if (Z_OK != (zbx_zlib_errno = compress(buf, &buf_size, (const Bytef *)in, size_in)))
	{
		zbx_free(buf);
		return FAIL;
	}

	*out = (char *)buf;
	*size_out = buf_size;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: uncompress data                                                   *
 *                                                                            *
 * Parameters: in       - [IN] the data to uncompress                         *
 *             size_in  - [IN] the input data size                            *
 *             out      - [OUT] the uncompressed data                         *
 *             size_out - [IN/OUT] the buffer and uncompressed data size      *
 *                                                                            *
 * Return value: SUCCEED - the data was uncompressed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_uncompress(const char *in, size_t size_in, char *out, size_t *size_out)
{
	uLongf	size_o = *size_out;

	if (Z_OK != (zbx_zlib_errno = uncompress((Bytef *)out, &size_o, (const Bytef *)in, size_in)))
		return FAIL;

	*size_out = size_o;

	return SUCCEED;
}

#else

int	zbx_compress(const char *in, size_t size_in, char **out, size_t *size_out)
{
	ZBX_UNUSED(in);
	ZBX_UNUSED(size_in);
	ZBX_UNUSED(out);
	ZBX_UNUSED(size_out);
	return FAIL;
}

int	zbx_uncompress(const char *in, size_t size_in, char *out, size_t *size_out)
{
	ZBX_UNUSED(in);
	ZBX_UNUSED(size_in);
	ZBX_UNUSED(out);
	ZBX_UNUSED(size_out);
	return FAIL;
}

const char	*zbx_compress_strerror(void)
{
	return "";
}

#endif
