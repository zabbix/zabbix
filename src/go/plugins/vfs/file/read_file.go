/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

package file

import (
	"fmt"
	"git.zabbix.com/ap/plugin-support/log"
	"io"
	"os"
	"strings"
)

// void	zbx_find_cr_lf_szbyte(const char *encoding, const char **cr, const char **lf, size_t *szbyte)
// {
// 	/* default is single-byte character set */
// 	*cr = "\r";
// 	*lf = "\n";
// 	*szbyte = 1;

// 	if ('\0' != *encoding)
// 	{
// 		if (0 == strcasecmp(encoding, "UNICODE") || 0 == strcasecmp(encoding, "UNICODELITTLE") ||
// 				0 == strcasecmp(encoding, "UTF-16") || 0 == strcasecmp(encoding, "UTF-16LE") ||
// 				0 == strcasecmp(encoding, "UTF16") || 0 == strcasecmp(encoding, "UTF16LE") ||
// 				0 == strcasecmp(encoding, "UCS-2") || 0 == strcasecmp(encoding, "UCS-2LE"))
// 		{
// 			*cr = "\r\0";
// 			*lf = "\n\0";
// 			*szbyte = 2;
// 		}
// 		else if (0 == strcasecmp(encoding, "UNICODEBIG") || 0 == strcasecmp(encoding, "UNICODEFFFE") ||
// 				0 == strcasecmp(encoding, "UTF-16BE") || 0 == strcasecmp(encoding, "UTF16BE") ||
// 				0 == strcasecmp(encoding, "UCS-2BE"))
// 		{
// 			*cr = "\0\r";
// 			*lf = "\0\n";
// 			*szbyte = 2;
// 		}
// 		else if (0 == strcasecmp(encoding, "UTF-32") || 0 == strcasecmp(encoding, "UTF-32LE") ||
// 				0 == strcasecmp(encoding, "UTF32") || 0 == strcasecmp(encoding, "UTF32LE"))
// 		{
// 			*cr = "\r\0\0\0";
// 			*lf = "\n\0\0\0";
// 			*szbyte = 4;
// 		}
// 		else if (0 == strcasecmp(encoding, "UTF-32BE") || 0 == strcasecmp(encoding, "UTF32BE"))
// 		{
// 			*cr = "\0\0\0\r";
// 			*lf = "\0\0\0\n";
// 			*szbyte = 4;
// 		}
// 	}
// }

// /******************************************************************************
//  *                                                                            *
//  * Purpose: Read one text line from a file descriptor into buffer             *
//  *                                                                            *
//  * Parameters: fd       - [IN] file descriptor to read from                   *
//  *             buf      - [OUT] buffer to read into                           *
//  *             count    - [IN] buffer size in bytes                           *
//  *             encoding - [IN] pointer to a text string describing encoding.  *
//  *                        See function zbx_find_cr_lf_szbyte() for supported  *
//  *                        encodings.                                          *
//  *                        "" (empty string) means a single-byte character set.*
//  *                                                                            *
//  * Return value: On success, the number of bytes read is returned (0 (zero)   *
//  *               indicates end of file).                                      *
//  *               On error, -1 is returned and errno is set appropriately.     *
//  *                                                                            *
//  * Comments: Reading stops after a newline. If the newline is read, it is     *
//  *           stored into the buffer.                                          *
//  *                                                                            *
//  ******************************************************************************/
// int	zbx_read(int fd, char *buf, size_t count, const char *encoding)
// {
// 	size_t		i, szbyte;
// 	ssize_t		nbytes;
// 	const char	*cr, *lf;
// 	zbx_offset_t	offset;

// 	if ((zbx_offset_t)-1 == (offset = zbx_lseek(fd, 0, SEEK_CUR)))
// 		return -1;

// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER zbx_read 2");

// 	if (0 >= (nbytes = read(fd, buf, count)))
// 		return (int)nbytes;

// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER zbx_read 3");

// 	zbx_find_cr_lf_szbyte(encoding, &cr, &lf, &szbyte);

// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER cr: ->%s<-", cr);
// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER lf ->%s<-", lf);
// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER szbyte ->%lu<-", szbyte);
// 	int	lf_found = 0;
// 	for (i = 0; i <= (size_t)nbytes - szbyte; i += szbyte)
// 	{

// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER i ->%d<-", i);

// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER buf ->%d<- and ->%d<-", buf[i], buf[i+1]);
// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER lf ->%d<- and ->%d<-", lf[0], lf[1]);
// 		if (0 == memcmp(&buf[i], lf, szbyte))	/* LF (Unix) */
// 		{
// 			i += szbyte;
// 			lf_found = 1;
// 			break;
// 		}

// 		if (0 == memcmp(&buf[i], cr, szbyte))	/* CR (Mac) */
// 		{
// 			/* CR+LF (Windows) ? */
// 			if (i < (size_t)nbytes - szbyte && 0 == memcmp(&buf[i + szbyte], lf, szbyte))
// 				i += szbyte;

// 			i += szbyte;
// 			lf_found = 1;
// 			break;
// 		}
// 	}

// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER AFTER i ->%lu<-", i);

// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER AFTER nbytes ->%lu<-", nbytes);
// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER AFTER nbytes-szbyte ->%lu<-", nbytes-szbyte);
// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER AFTER szbyte ->%lu<-", szbyte);

// 	if ((0 == lf_found) &&
// 			(0 == strcasecmp(encoding, "UNICODE") || 0 == strcasecmp(encoding, "UNICODELITTLE") ||
// 			0 == strcasecmp(encoding, "UTF-16") || 0 == strcasecmp(encoding, "UTF-16LE") ||
// 			0 == strcasecmp(encoding, "UTF16") || 0 == strcasecmp(encoding, "UTF16LE") ||
// 			0 == strcasecmp(encoding, "UCS-2") || 0 == strcasecmp(encoding, "UCS-2LE") ||
// 				0 == strcasecmp(encoding, "UNICODEBIG") || 0 == strcasecmp(encoding, "UNICODEFFFE") ||
// 				0 == strcasecmp(encoding, "UTF-16BE") || 0 == strcasecmp(encoding, "UTF16BE") ||
// 				0 == strcasecmp(encoding, "UCS-2BE") ||
// 				0 == strcasecmp(encoding, "UTF-32") || 0 == strcasecmp(encoding, "UTF-32LE") ||
// 				0 == strcasecmp(encoding, "UTF32") || 0 == strcasecmp(encoding, "UTF32LE") ||
// 				0 == strcasecmp(encoding, "UTF-32BE") || 0 == strcasecmp(encoding, "UTF32BE")))
// 	{
// 		zabbix_log(LOG_LEVEL_INFORMATION, "BADGER no line feed");
// 		return -2;
// 	}

// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER zbx_read 33");

// 	if ((zbx_offset_t)-1 == zbx_lseek(fd, offset + (zbx_offset_t)i, SEEK_SET))
// 		return -1;

// 	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER zbx_read 4; i: %d", i);

// 	return (int)i;
// }

func (p *Plugin) find_CR_LF_Szbyte(encoding string) (cr []byte, lf []byte, szbyte int) {
	/* default is single-byte character set */
	cr = []byte("\r")
	lf = []byte("\n")
	szbyte = 1

	if "" != encoding {
		if strings.EqualFold(encoding, "UNICODE") || strings.EqualFold(encoding, "UNICODELITTLE") ||
			strings.EqualFold(encoding, "UTF-16") || strings.EqualFold(encoding, "UTF-16LE") ||
			strings.EqualFold(encoding, "UTF16") || strings.EqualFold(encoding, "UTF16LE") ||
			strings.EqualFold(encoding, "UCS-2") || strings.EqualFold(encoding, "UCS-2LE") {
			cr = []byte("\r\n")
			lf = []byte("\n\x00")
			szbyte = 2
		} else if strings.EqualFold(encoding, "UNICODEBIG") || strings.EqualFold(encoding, "UNICODEFFFE") ||
			strings.EqualFold(encoding, "UTF-16BE") || strings.EqualFold(encoding, "UTF16BE") ||
			strings.EqualFold(encoding, "UCS-2BE") {
			cr = []byte("\x00\r")
			lf = []byte("\x00\n")
			szbyte = 2
		} else if strings.EqualFold(encoding, "UTF-32") || strings.EqualFold(encoding, "UTF-32LE") ||
			strings.EqualFold(encoding, "UTF32") || strings.EqualFold(encoding, "UTF32LE") {
			cr = []byte("\r\x00\x00\x00")
			lf = []byte("\n\x00\x00\x00")

			szbyte = 4
		} else if strings.EqualFold(encoding, "UTF-32BE") || strings.EqualFold(encoding, "UTF32BE") {
			cr = []byte("\x00\x00\x00\r")
			lf = []byte("\x00\x00\x00\n")
			szbyte = 4
		}
	}

	return cr, lf, szbyte
}

func (p *Plugin) bytesCompare(a []byte, b []byte, szbyte int, aStartOffset int, bStartOffset int) bool {
	ee := true
	for ii := 0; ii < szbyte; ii++ {
		if a[aStartOffset+ii] != b[bStartOffset+ii] {
			ee = false
		}
	}
	return ee
}

/******************************************************************************
 *                                                                            *
 * Purpose: Read one text line from a file descriptor into buffer             *
 *                                                                            *
 * Parameters: fd       - [IN] file descriptor to read from                   *
 *             buf      - [OUT] buffer to read into                           *
 *             count    - [IN] buffer size in bytes                           *
 *             encoding - [IN] pointer to a text string describing encoding.  *
 *                        See function zbx_find_cr_lf_szbyte() for supported  *
 *                        encodings.                                          *
 *                        "" (empty string) means a single-byte character set.*
 *                                                                            *
 * Return value: On success, the number of bytes read is returned (0 (zero)   *
 *               indicates end of file).                                      *
 *               On error, -1 is returned and errno is set appropriately.     *
 *                                                                            *
 * Comments: Reading stops after a newline. If the newline is read, it is     *
 *           stored into the buffer.                                          *
 *                                                                            *
 ******************************************************************************/
func (p *Plugin) readFile(targetFile *os.File, encoding string) (buf []byte, nbytes int, err error) {
	var i int
	var szbyte int
	//	var nbytes int64;
	var cr []byte
	var lf []byte

	var offset int64

	buf = make([]byte, MAX_BUFFER_LEN)
	// if ((zbx_offset_t)-1 == (offset = zbx_lseek(fd, 0, SEEK_CUR)))
	// 	return -1;

	offset, err = targetFile.Seek(0, os.SEEK_CUR)
	if err != nil {
		return nil, 0, err
	}

	log.Infof("BADGER zbx_read 2: offset: %s", offset)

	// if (0 >= (nbytes = read(fd, buf, count)))
	// 	return (int)nbytes;
	nbytes, err = targetFile.Read(buf)
	if err != nil {
		if err != io.EOF {
			log.Infof("BADGER READ ERROR: %+v", err)
			return nil, 0, err
		} else {
			log.Infof("BADGER EOF")
		}

	}
	if 0 >= nbytes {
		log.Infof("BADGER nbytes: %d", nbytes)
		return buf, nbytes, nil
	}

	log.Infof("BADGER zbx_read 3")
	// func (p *Plugin) find_CR_LF_Szbyte(encoding string)(cr string, lf string, szbyte uint64)
	cr, lf, szbyte = p.find_CR_LF_Szbyte(encoding)

	log.Infof("BADGER cr: ->%d<-", cr)
	log.Infof("BADGER lf ->%d<-", lf)
	log.Infof("BADGER szbyte ->%lu<-", szbyte)
	lf_found := 0

	for i = 0; i <= nbytes-szbyte; i += szbyte {

		log.Infof("BADGER i ->%d<-", i)

		log.Infof("BADGER buf ->%d<- and ->%d<-", buf[i], buf[i+1])
		log.Infof("BADGER lf ->%d<- and ->%d<-", lf[0], lf[1])

		// if (0 == memcmp(&buf[i], lf, szbyte))	/* LF (Unix) */
		// {
		// 	i += szbyte;
		// 	lf_found = 1;
		// 	break;
		// }

		log.Infof("BADGER buf size: %d, lf size: %d", len(buf), len(lf))
		for x := 0; x < szbyte; x++ {
			log.Infof("BADGER buf val: %d", buf[x])
		}
		for x := 0; x < szbyte; x++ {
			log.Infof("BADGER lf val: %d", lf[x])
		}

		if p.bytesCompare(buf, lf, szbyte, i, 0) == true {
			i += szbyte
			lf_found = 1
			break
		}

		// if (0 == memcmp(&buf[i], cr, szbyte))	/* CR (Mac) */
		// {
		// 	/* CR+LF (Windows) ? */
		// 	if (i < (size_t)nbytes - szbyte && 0 == memcmp(&buf[i + szbyte], lf, szbyte))
		// 		i += szbyte;

		// 	i += szbyte;
		// 	lf_found = 1;
		// 	break;
		// }
		if p.bytesCompare(buf, cr, szbyte, i, 0) == true {
			if i < nbytes-szbyte && p.bytesCompare(buf, lf, szbyte, i+szbyte, 0) {
				i += szbyte
			}
			i += szbyte
			lf_found = 1
			break
		}
	}

	log.Infof("BADGER AFTER i ->%lu<-", i)

	log.Infof("BADGER AFTER nbytes ->%lu<-", nbytes)
	log.Infof("BADGER AFTER nbytes-szbyte ->%lu<-", nbytes-szbyte)
	log.Infof("BADGER AFTER szbyte ->%lu<-", szbyte)

	if (0 == lf_found) &&
		(strings.EqualFold(encoding, "UNICODE") || strings.EqualFold(encoding, "UNICODELITTLE") ||
			strings.EqualFold(encoding, "UTF-16") || strings.EqualFold(encoding, "UTF-16LE") ||
			strings.EqualFold(encoding, "UTF16") || strings.EqualFold(encoding, "UTF16LE") ||
			strings.EqualFold(encoding, "UCS-2") || strings.EqualFold(encoding, "UCS-2LE") ||
			strings.EqualFold(encoding, "UNICODEBIG") || strings.EqualFold(encoding, "UNICODEFFFE") ||
			strings.EqualFold(encoding, "UTF-16BE") || strings.EqualFold(encoding, "UTF16BE") ||
			strings.EqualFold(encoding, "UCS-2BE") ||
			strings.EqualFold(encoding, "UTF-32") || strings.EqualFold(encoding, "UTF-32LE") ||
			strings.EqualFold(encoding, "UTF32") || strings.EqualFold(encoding, "UTF32LE") ||
			strings.EqualFold(encoding, "UTF-32BE") || strings.EqualFold(encoding, "UTF32BE")) {
		log.Infof("BADGER no line feed")
		return nil, 0, fmt.Errorf("No line feed detected")
	}

	log.Infof("BADGER zbx_read 33")

	// if ((zbx_offset_t)-1 == zbx_lseek(fd, offset + (zbx_offset_t)i, SEEK_SET))
	// 	return -1;

	offset, err = targetFile.Seek(offset+int64(i), os.SEEK_SET)
	if err != nil {
		return nil, 0, err
	}

	log.Infof("BADGER zbx_read 4; i: %d", i)

	return buf, int(i), nil
}
