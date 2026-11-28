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

#include "zbxcompress.h"

#include "zbxcommon.h"
#include "zbxcomms.h"

#ifdef HAVE_ZLIB
#include "zlib.h"
#endif

#ifdef HAVE_ZSTD
#include <zstd.h>
#endif

#define ZBX_COMPRESS_STRERROR_LEN	512

typedef enum
{
	ZBX_COMPRESS_ERR_NONE = 0,
	ZBX_COMPRESS_ERR_ZLIB,
	ZBX_COMPRESS_ERR_ZSTD,
	ZBX_COMPRESS_ERR_UNSUPPORTED
}
zbx_compress_err_type_t;

static ZBX_THREAD_LOCAL zbx_compress_err_type_t	zbx_last_err_type = ZBX_COMPRESS_ERR_NONE;
#ifdef HAVE_ZLIB
static ZBX_THREAD_LOCAL int			zbx_zlib_errno = 0;
#endif
#ifdef HAVE_ZSTD
static ZBX_THREAD_LOCAL size_t			zbx_zstd_errcode = 0;
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: returns last compression/decompression error message              *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_compress_strerror(void)
{
	static ZBX_THREAD_LOCAL char	message[ZBX_COMPRESS_STRERROR_LEN];

	switch (zbx_last_err_type)
	{
#ifdef HAVE_ZLIB
		case ZBX_COMPRESS_ERR_ZLIB:
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
					zbx_snprintf(message, sizeof(message), "unknown zlib error (%d)",
							zbx_zlib_errno);
					break;
			}
			break;
#endif
#ifdef HAVE_ZSTD
		case ZBX_COMPRESS_ERR_ZSTD:
			zbx_snprintf(message, sizeof(message), "zstd error: %s",
					ZSTD_getErrorName(zbx_zstd_errcode));
			break;
#endif
		case ZBX_COMPRESS_ERR_UNSUPPORTED:
			zbx_strlcpy(message, "requested compression algorithm is not compiled in", sizeof(message));
			break;
		default:
			zbx_strlcpy(message, "", sizeof(message));
			break;
	}

	return message;
}

#ifdef HAVE_ZLIB
/******************************************************************************
 *                                                                            *
 * Purpose: compress data using zlib                                          *
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
		zbx_last_err_type = ZBX_COMPRESS_ERR_ZLIB;
		zbx_free(buf);
		return FAIL;
	}

	*out = (char *)buf;
	*size_out = buf_size;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: uncompress data using zlib                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_uncompress(const char *in, size_t size_in, char *out, size_t *size_out)
{
	uLongf	size_o = *size_out;

	if (Z_OK != (zbx_zlib_errno = uncompress((Bytef *)out, &size_o, (const Bytef *)in, size_in)))
	{
		zbx_last_err_type = ZBX_COMPRESS_ERR_ZLIB;
		return FAIL;
	}

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
	zbx_last_err_type = ZBX_COMPRESS_ERR_UNSUPPORTED;
	return FAIL;
}

int	zbx_uncompress(const char *in, size_t size_in, char *out, size_t *size_out)
{
	ZBX_UNUSED(in);
	ZBX_UNUSED(size_in);
	ZBX_UNUSED(out);
	ZBX_UNUSED(size_out);
	zbx_last_err_type = ZBX_COMPRESS_ERR_UNSUPPORTED;
	return FAIL;
}
#endif

#ifdef HAVE_ZSTD
/******************************************************************************
 *                                                                            *
 * Purpose: compress data using Zstandard (zstd)                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_compress_zstd(const char *in, size_t size_in, char **out, size_t *size_out)
{
	size_t	bound, res;
	char	*buf;

	bound = ZSTD_compressBound(size_in);
	buf = (char *)zbx_malloc(NULL, bound);

	res = ZSTD_compress(buf, bound, in, size_in, ZSTD_CLEVEL_DEFAULT);
	if (0 != ZSTD_isError(res))
	{
		zbx_zstd_errcode = res;
		zbx_last_err_type = ZBX_COMPRESS_ERR_ZSTD;
		zbx_free(buf);
		return FAIL;
	}

	*out = buf;
	*size_out = res;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: uncompress data using Zstandard (zstd)                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_uncompress_zstd(const char *in, size_t size_in, char *out, size_t *size_out)
{
	size_t	res;

	res = ZSTD_decompress(out, *size_out, in, size_in);
	if (0 != ZSTD_isError(res))
	{
		zbx_zstd_errcode = res;
		zbx_last_err_type = ZBX_COMPRESS_ERR_ZSTD;
		return FAIL;
	}

	*size_out = res;

	return SUCCEED;
}
#else
int	zbx_compress_zstd(const char *in, size_t size_in, char **out, size_t *size_out)
{
	ZBX_UNUSED(in);
	ZBX_UNUSED(size_in);
	ZBX_UNUSED(out);
	ZBX_UNUSED(size_out);
	zbx_last_err_type = ZBX_COMPRESS_ERR_UNSUPPORTED;
	return FAIL;
}

int	zbx_uncompress_zstd(const char *in, size_t size_in, char *out, size_t *size_out)
{
	ZBX_UNUSED(in);
	ZBX_UNUSED(size_in);
	ZBX_UNUSED(out);
	ZBX_UNUSED(size_out);
	zbx_last_err_type = ZBX_COMPRESS_ERR_UNSUPPORTED;
	return FAIL;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: compress proxy-server payload with ZSTD (fallback to zlib)        *
 *                                                                            *
 ******************************************************************************/
int	zbx_compress_proxy(const char *in, size_t size_in, char **out, size_t *size_out)
{
#ifdef HAVE_ZSTD
	return zbx_compress_zstd(in, size_in, out, size_out);
#else
	return zbx_compress(in, size_in, out, size_out);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: compress payload according to negotiated ZBXD compression flags   *
 *                                                                            *
 ******************************************************************************/
int	zbx_compress_with_flags(const char *in, size_t size_in, char **out, size_t *size_out, unsigned char flags)
{
	if (0 != (flags & ZBX_TCP_COMPRESS_ZSTD))
		return zbx_compress_zstd(in, size_in, out, size_out);

	return zbx_compress(in, size_in, out, size_out);
}
