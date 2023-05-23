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
	"io"
	"os"
	"strings"
)

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

func (p *Plugin) readFile(targetFile *os.File, encoding string) (buf []byte, nbytes int, err error) {
	var i, szbyte int
	var offset int64
	var cr, lf []byte

	buf = make([]byte, MAX_BUFFER_LEN)

	offset, err = targetFile.Seek(0, os.SEEK_CUR)
	if err != nil {
		return nil, 0, err
	}

	nbytes, err = targetFile.Read(buf)
	if err != nil {
		if err != io.EOF {
			return nil, 0, err
		}
	}
	if 0 >= nbytes {
		return buf, nbytes, nil
	}

	cr, lf, szbyte = p.find_CR_LF_Szbyte(encoding)

	lf_found := 0

	for i = 0; i <= nbytes-szbyte; i += szbyte {
		if p.bytesCompare(buf, lf, szbyte, i, 0) == true { /* LF (Unix) */
			i += szbyte
			lf_found = 1
			break
		}

		if p.bytesCompare(buf, cr, szbyte, i, 0) == true { /* CR (Mac) */
			/* CR+LF (Windows) ? */
			if i < nbytes-szbyte && p.bytesCompare(buf, lf, szbyte, i+szbyte, 0) {
				i += szbyte
			}
			i += szbyte
			lf_found = 1
			break
		}
	}

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
		return nil, 0, fmt.Errorf("No line feed detected")
	}

	offset, err = targetFile.Seek(offset+int64(i), os.SEEK_SET)
	if err != nil {
		return nil, 0, err
	}

	return buf, int(i), nil
}
