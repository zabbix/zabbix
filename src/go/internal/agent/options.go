/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

package agent

import (
	"bytes"
	"errors"
	"fmt"
	"io/ioutil"
	"os"
	"strings"
	"unicode"

	"zabbix.com/pkg/plugin"
	"zabbix.com/pkg/tls"
)

var Options AgentOptions

func CutAfterN(s string, n int) (string, int) {
	var l int

	for i := range s {
		if i > n {
			s = s[:l]
			break
		}
		l = i
	}

	return s, l
}

func CheckHostname(s string) error {
	for i := 0; i < len(s); i++ {
		if s[i] == '.' || s[i] == ' ' || s[i] == '_' || s[i] == '-' ||
			(s[i] >= 'A' && s[i] <= 'Z') || (s[i] >= 'a' && s[i] <= 'z') || (s[i] >= '0' && s[i] <= '9') {
			continue
		}

		if unicode.IsPrint(rune(s[i])) {
			return fmt.Errorf("character \"%c\" is not allowed in host name", s[i])
		} else {
			return fmt.Errorf("character 0x%02x is not allowed in host name", s[i])
		}
	}

	return nil
}

func GetTLSConfig(options *AgentOptions) (cfg *tls.Config, err error) {
	if !tls.Supported() {
		if options.TLSAccept != "" ||
			options.TLSConnect != "" ||
			options.TLSPSKFile != "" ||
			options.TLSPSKIdentity != "" {
			return nil, errors.New(tls.SupportedErrMsg())
		}
		return
	}

	c := &tls.Config{}
	switch options.TLSConnect {
	case "", "unencrypted":
		c.Connect = tls.ConnUnencrypted
	case "psk":
		c.Connect = tls.ConnPSK
	case "cert":
		c.Connect = tls.ConnCert
	default:
		return nil, errors.New("invalid TLSConnect configuration parameter")
	}

	if options.TLSAccept != "" {
		opts := strings.Split(options.TLSAccept, ",")
		for _, o := range opts {
			switch strings.Trim(o, " \t") {
			case "unencrypted":
				c.Accept |= tls.ConnUnencrypted
			case "psk":
				c.Accept |= tls.ConnPSK
			case "cert":
				c.Accept |= tls.ConnCert
			default:
				return nil, errors.New("invalid TLSAccept configuration parameter")
			}
		}
	} else {
		c.Accept = tls.ConnUnencrypted
	}

	if (c.Accept|c.Connect)&tls.ConnPSK != 0 {
		if options.TLSPSKIdentity != "" {
			c.PSKIdentity = options.TLSPSKIdentity
		} else {
			return nil, errors.New("missing TLSPSKIdentity configuration parameter")
		}
		if options.TLSPSKFile != "" {
			var file *os.File
			if file, err = os.Open(options.TLSPSKFile); err != nil {
				return nil, fmt.Errorf("invalid TLSPSKFile configuration parameter: %s", err)
			}
			defer file.Close()
			var b []byte
			if b, err = ioutil.ReadAll(file); err != nil {
				return nil, fmt.Errorf("invalid TLSPSKFile configuration parameter: %s", err)
			}
			c.PSKKey = string(bytes.TrimRight(b, "\r\n \t"))
		} else {
			return nil, errors.New("missing TLSPSKFile configuration parameter")
		}
	} else {
		if options.TLSPSKIdentity != "" {
			return nil, errors.New("TLSPSKIdentity configuration parameter set without PSK being used")
		}
		if options.TLSPSKFile != "" {
			return nil, errors.New("TLSPSKFile configuration parameter set without PSK being used")
		}
	}

	if (c.Accept|c.Connect)&tls.ConnCert != 0 {
		if options.TLSCAFile != "" {
			c.CAFile = options.TLSCAFile
		} else {
			return nil, errors.New("missing TLSCAFile configuration parameter")
		}
		if options.TLSCertFile != "" {
			c.CertFile = options.TLSCertFile
		} else {
			return nil, errors.New("missing TLSCertFile configuration parameter")
		}
		if options.TLSKeyFile != "" {
			c.KeyFile = options.TLSKeyFile
		} else {
			return nil, errors.New("missing TLSKeyFile configuration parameter")
		}
		c.ServerCertIssuer = options.TLSServerCertIssuer
		c.ServerCertSubject = options.TLSServerCertSubject
		c.CRLFile = options.TLSCRLFile
	} else {
		if options.TLSCAFile != "" {
			return nil, errors.New("TLSCAFile configuration parameter set without certificates being used")
		}
		if options.TLSCertFile != "" {
			return nil, errors.New("TLSCertFile configuration parameter set without certificates being used")
		}
		if options.TLSKeyFile != "" {
			return nil, errors.New("TLSKeyFile configuration parameter set without certificates being used")
		}
		if options.TLSServerCertIssuer != "" {
			return nil, errors.New("TLSServerCertIssuer configuration parameter set without certificates being used")
		}
		if options.TLSServerCertSubject != "" {
			return nil, errors.New("TLSServerCertSubject configuration parameter set without certificates being used")
		}
		if options.TLSCRLFile != "" {
			return nil, errors.New("TLSCRLFile configuration parameter set without certificates being used")
		}
	}

	return c, nil
}

func GlobalOptions(all *AgentOptions) (options *plugin.GlobalOptions) {
	options = &plugin.GlobalOptions{
		Timeout:  Options.Timeout,
		SourceIP: Options.SourceIP,
	}
	return
}

func ValidateOptions(options AgentOptions) error {
	const hostNameLen = 128
	const hostMetadataLen = 255
	const hostInterfaceLen = 255
	var err error

	if len(options.Hostname) > hostNameLen {
		return fmt.Errorf("the value of \"Hostname\" configuration parameter cannot be longer than %d characters", hostNameLen)
	}
	if err = CheckHostname(options.Hostname); err != nil {
		return fmt.Errorf("invalid \"Hostname\" configuration parameter: %s", err.Error())
	}
	if len(options.HostMetadata) > 0 && len(options.HostMetadata) > hostMetadataLen {
		return fmt.Errorf("the value of \"HostMetadata\" configuration parameter cannot be longer than %d characters", hostMetadataLen)
	}
	if len(options.HostInterface) > hostInterfaceLen {
		return fmt.Errorf("the value of \"HostInterface\" configuration parameter cannot be longer than %d characters", hostInterfaceLen)
	}

	return nil
}
