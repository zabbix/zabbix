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

package file

import (
	"errors"
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
			cr = []byte("\r\x00")
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

			break
		}
	}

	return ee
}

func (p *Plugin) checkLF(buf []byte, lf []byte, cr []byte, nbytes int, szbyte int) ([]byte, int) {
	var i int

	for i = 0; i <= nbytes-szbyte; i += szbyte {
		if p.bytesCompare(buf, lf, szbyte, i, 0) { /* LF (Unix) */
			i += szbyte

			break
		}

		if p.bytesCompare(buf, cr, szbyte, i, 0) { /* CR (Mac) */
			/* CR+LF (Windows) ? */
			if i < nbytes-szbyte && p.bytesCompare(buf, lf, szbyte, i+szbyte, 0) {
				i += szbyte
			}
			i += szbyte

			break
		}
	}

	return buf, i
}

func (p *Plugin) readTextLineFromFile(targetFile *os.File, encoding string) (buf []byte, nbytes int,
	updatedEncoding string, err error) {
	var szbyte int
	var offset int64
	var cr, lf []byte

	buf = make([]byte, MAX_BUFFER_LEN)

	offset, err = targetFile.Seek(0, io.SeekCurrent)
	if err != nil {
		return nil, 0, encoding, err
	}

	nbytes, err = targetFile.Read(buf)
	if err != nil {
		if !errors.Is(err, io.EOF) {
			return nil, 0, encoding, err
		}
	}
	if 0 >= nbytes {
		return buf, nbytes, encoding, nil
	}

	encoding = findEncodingFromBOM(encoding, buf, nbytes)
	cr, lf, szbyte = p.find_CR_LF_Szbyte(encoding)

	/* nbytes can be smaller than szbyte. If the target file was encoded in UTF-8 and contained a single
	   character, but the target encoding was mistakenly set to UTF-32. Then nbytes will be 1 and szbyte
	   will be 4. Similarly, if bytes read produces a remainder that does not fit szbyte - we can safely
	   assume the file contains the encoding different from the one provided to us.*/
	if nbytes < szbyte || (nbytes%szbyte != 0) {
		return nil, 0, encoding, fmt.Errorf("Cannot read from file. Wrong encoding detected.")
	}

	var i int
	buf, i = p.checkLF(buf, lf, cr, nbytes, szbyte)

	_, err = targetFile.Seek(offset+int64(i), io.SeekStart)
	if err != nil {
		return nil, 0, encoding, err
	}

	return buf, i, encoding, nil
}
