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

	fmt.Printf("BADGER zbx_read 2: offset: %d\n", offset)

	nbytes, err = targetFile.Read(buf)
	if err != nil {
		if err != io.EOF {
			fmt.Printf("BADGER READ ERROR: %+v\n", err)
			return nil, 0, err
		} else {
			fmt.Printf("BADGER EOF\n")
		}

	}
	if 0 >= nbytes {
		fmt.Printf("BADGER nbytes: %d\n", nbytes)
		return buf, nbytes, nil
	}

	fmt.Printf("BADGER zbx_read 3\n")
	cr, lf, szbyte = p.find_CR_LF_Szbyte(encoding)

	fmt.Printf("BADGER cr: ->%d<-\n", cr)
	fmt.Printf("BADGER lf ->%d<-\n", lf)
	fmt.Printf("BADGER szbyte ->%d<-\n", szbyte)
	lf_found := 0

	for i = 0; i <= nbytes-szbyte; i += szbyte {

		fmt.Printf("BADGER i ->%d<-\n", i)

		fmt.Printf("BADGER buf ->%d<- and ->%d<-\n", buf[i], buf[i+1])
		fmt.Printf("BADGER lf ->%d<-\n", lf[0])
		if len(lf) > 1 {
			fmt.Printf("BADGER lf2: %d\n", lf[1])
		}

		fmt.Printf("BADGER buf size: %d, lf size: %d\n", len(buf), len(lf))
		for x := i; x < szbyte; x++ {
			fmt.Printf("BADGER buf val: %d\n", buf[x])
		}
		for x := 0; x < szbyte; x++ {
			fmt.Printf("BADGER lf val: %d\n", lf[x])
		}

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

	fmt.Printf("BADGER AFTER i ->%d<-\n", i)

	fmt.Printf("BADGER AFTER nbytes ->%d<-\n", nbytes)
	fmt.Printf("BADGER AFTER nbytes-szbyte ->%d<-\n", nbytes-szbyte)
	fmt.Printf("BADGER AFTER szbyte ->%d<-\n", szbyte)

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
		fmt.Printf("BADGER no line feed\n")
		return nil, 0, fmt.Errorf("No line feed detected")
	}

	fmt.Printf("BADGER zbx_read 33\n")

	offset, err = targetFile.Seek(offset+int64(i), os.SEEK_SET)
	if err != nil {
		return nil, 0, err
	}

	fmt.Printf("BADGER zbx_read 4; i: %d", i)

	return buf, int(i), nil
}
