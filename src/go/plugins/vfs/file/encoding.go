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

//#include <iconv.h>
//#include <stdlib.h>
//size_t call_iconv(iconv_t cd, char *inbuf, size_t *inbytesleft, char *outbuf, size_t *outbytesleft) {
//   return iconv(cd, &inbuf, inbytesleft, &outbuf, outbytesleft);
// }
//
// #cgo windows LDFLAGS: -liconv
import "C"

import (
	"syscall"
	"unsafe"

	"fmt"
	"git.zabbix.com/ap/plugin-support/log"
)

func decode(encoder string, inbuf []byte, bytecount int) (outbuf []byte) {
	fmt.Printf("BADGERL: %d\n", bytecount)

	if bytecount == 0 {
		return inbuf
	}

	for i := 0; i < bytecount; i++ {
		fmt.Printf("BADGER INBUF X: %x\n", inbuf[i])
	}

	if "" == encoder {
		if bytecount > 3 && 0xef == inbuf[0] && 0xbb == inbuf[1] && 0xbf == inbuf[2] {
			encoder = "UTF-8"
		} else if bytecount > 2 && 0xff == inbuf[0] && 0xfe == inbuf[1] {
			encoder = "UTF-16LE"
		} else if bytecount > 2 && 0xfe == inbuf[0] && 0xff == inbuf[1] {
			encoder = "UTF-16BE"
		} else {
			return inbuf
		}
	}
	fmt.Printf("ENCODER: %s\n", encoder)
	tocode := C.CString("UTF-8")
	log.Tracef("Calling C function \"free()\"")
	defer C.free(unsafe.Pointer(tocode))
	fromcode := C.CString(encoder)
	log.Tracef("Calling C function \"free()\"")
	defer C.free(unsafe.Pointer(fromcode))

	log.Tracef("Calling C function \"iconv_open()\"")

	fmt.Printf("BADGER tocode: %s", C.GoString(tocode))
	fmt.Printf("BADGER fromcode: %s", C.GoString(fromcode))

	cd, err := C.iconv_open(tocode, fromcode)

	if err != nil {
		return inbuf
	}

	outbuf = make([]byte, bytecount)
	inbytes := C.size_t(bytecount)
	outbytes := C.size_t(bytecount)

	for {
		fmt.Printf("inbuf len: %d, outbuf len: %d, bytecount: %d, inbytes: %d, outbytes: %d\n", len(inbuf), len(outbuf), bytecount, inbytes, outbytes)
		inptr := (*C.char)(unsafe.Pointer(&inbuf[bytecount-int(inbytes)]))
		outptr := (*C.char)(unsafe.Pointer(&outbuf[len(outbuf)-int(outbytes)]))

		log.Tracef("Calling C function \"call_iconv()\"")
		_, err := C.call_iconv(cd, inptr, &inbytes, outptr, &outbytes)
		if err == nil || err.(syscall.Errno) != syscall.E2BIG {
			//log.Infof("BADGER HELLO WORLD")
			break
		}

		outbytes += C.size_t(bytecount)
		tmp := make([]byte, len(outbuf)+bytecount)
		copy(tmp, outbuf)
		outbuf = tmp

		for i := 0; i < len(outbuf); i++ {
			fmt.Printf("BADGER OUTBUF X: %x\n", outbuf[i])
		}
	}

	outbuf = outbuf[:len(outbuf)-int(outbytes)]

	for i := 0; i < len(outbuf); i++ {
		fmt.Printf("BADGER OUTBUF2 X: %x\n", outbuf[i])
	}

	log.Tracef("Calling C function \"iconv_close()\"")
	C.iconv_close(cd)

	fmt.Printf("BADGER decode outbuf before: %v", outbuf)

	if len(outbuf) > 3 && 0xef == outbuf[0] && 0xbb == outbuf[1] && 0xbf == outbuf[2] {
		outbuf = outbuf[3:]
	}

	fmt.Printf("BADGER decode outbuf after %v", outbuf)
	return
}
