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

#ifndef ZABBIX_ZBXHASH_H
#define ZABBIX_ZBXHASH_H

/* ------------ md5.h file included with Zabbix modifications ------------ */
/*
** Copyright (C) 1999, 2002 Aladdin Enterprises.  All rights reserved.
**
** This software is provided 'as-is', without any express or implied
** warranty.  In no event will the authors be held liable for any damages
** arising from the use of this software.
**
** Permission is granted to anyone to use this software for any purpose,
** including commercial applications, and to alter it and redistribute it
** freely, subject to the following restrictions:
**
** 1. The origin of this software must not be misrepresented; you must not
**    claim that you wrote the original software. If you use this software
**    in a product, an acknowledgment in the product documentation would be
**    appreciated but is not required.
** 2. Altered source versions must be plainly marked as such, and must not be
**    misrepresented as being the original software.
** 3. This notice may not be removed or altered from any source distribution.
**
** L. Peter Deutsch
** ghost@aladdin.com
**
 */
/* $Id$ */
/*
** Independent implementation of MD5 (RFC 1321).
**
** This code implements the MD5 Algorithm defined in RFC 1321, whose
** text is available at
**	http://www.ietf.org/rfc/rfc1321.txt
** The code is derived from the text of the RFC, including the test suite
** (section A.5) but excluding the rest of Appendix A.  It does not include
** any code or documentation that is identified in the RFC as being
** copyrighted.
**
** The original and principal author of md5.h is L. Peter Deutsch
** <ghost@aladdin.com>.  Other authors are noted in the change history
** that follows (in reverse chronological order):
**
** 2002-04-13 lpd Removed support for non-ANSI compilers; removed
**	references to Ghostscript; clarified derivation from RFC 1321;
**	now handles byte order either statically or dynamically.
** 1999-11-04 lpd Edited comments slightly for automatic TOC extraction.
** 1999-10-18 lpd Fixed typo in header comment (ansi2knr rather than md5);
**	added conditionalization for C++ compilation from Martin
**	Purschke <purschke@bnl.gov>.
** 1999-05-03 lpd Original version.
*/

/*
** This package supports both compile-time and run-time determination of CPU
** byte order.  If ARCH_IS_BIG_ENDIAN is defined as 0, the code will be
** compiled to run only on little-endian CPUs; if ARCH_IS_BIG_ENDIAN is
** defined as non-zero, the code will be compiled to run only on big-endian
** CPUs; if ARCH_IS_BIG_ENDIAN is not defined, the code will be compiled to
** run on either big- or little-endian CPUs, but will run slightly less
** efficiently on either one than if ARCH_IS_BIG_ENDIAN is defined.
*/

#define ZBX_MD5_DIGEST_SIZE 16

typedef unsigned char md5_byte_t; /* 8-bit byte */
typedef unsigned int md5_word_t; /* 32-bit word */

/* Define the state of the MD5 Algorithm. */
typedef struct md5_state_s
{
	md5_word_t	count[2];	/* message length in bits, lsw first */
	md5_word_t	abcd[4];	/* digest buffer */
	md5_byte_t	buf[64];	/* accumulate block */
} md5_state_t;

#ifdef __cplusplus
extern "C"
{
#endif

/* Initialize the algorithm. */
void zbx_md5_init(md5_state_t *pms);

/* Append a string to the message. */
void zbx_md5_append(md5_state_t *pms, const md5_byte_t *data, int nbytes);

/* Finish the message and return the digest. */
void zbx_md5_finish(md5_state_t *pms, md5_byte_t digest[16]);

#ifdef __cplusplus
}  /* end extern "C" */
#endif

/* ------------------ end of included md5.h file ------------------------- */

#include "zbxcommon.h"

void	zbx_md5buf2str(const md5_byte_t *md5, char *str);

/* SHA BLOCK */
/* Based on SHA256 implementation released into the Public Domain by Ulrich Drepper <drepper@redhat.com>.  */

#define ZBX_SHA256_DIGEST_SIZE	32

/* Structure to save state of computation between the single steps. */
typedef struct
{
	uint32_t	H[8];
	uint32_t	total[2];
	uint32_t	buflen;
	char		buffer[128];	/* NB: always correctly aligned for uint32_t. */
}
sha256_ctx;

void	zbx_sha256_init(sha256_ctx *ctx);
void	zbx_sha256_process_bytes(const void *buffer, size_t len, sha256_ctx *ctx);
void	*zbx_sha256_finish(sha256_ctx *ctx, void *resbuf);
void	zbx_sha256_hash(const char *in, char *out);
void	zbx_sha256_hash_len(const char *in, size_t len, char *out);

/* Based on SHA512 implementation released into the Public Domain by Ulrich Drepper <drepper@redhat.com>.  */
void	zbx_sha512_hash(const char *in, char *out);
/* SHA BLOCK END */

#endif /* ZABBIX_ZBXHASH_H */
