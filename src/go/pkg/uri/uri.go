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

// Package uri provides a helper for URI validation and parsing
package uri

import (
	"errors"
	"fmt"
	"net"
	"net/url"
	"strconv"
	"strings"
)

type URI struct {
	scheme   string
	host     string
	port     string
	resource string
	socket   string
	user     string
	password string
}

func (u *URI) Scheme() string {
	return u.scheme
}

func (u *URI) Host() string {
	return u.host
}

func (u *URI) Port() string {
	return u.port
}

func (u *URI) Resource() string {
	return u.resource
}

func (u *URI) Password() string {
	return u.password
}

func (u *URI) User() string {
	return u.user
}

// Addr combines a host and a port into a network address ("host:port") or returns a socket.
func (u *URI) Addr() string {
	if u.socket != "" {
		return u.socket
	}

	if u.port == "" {
		return u.host
	}

	return net.JoinHostPort(u.host, u.port)
}

// String reassembles the URI to a valid URI string.
func (u *URI) String() string {
	t := &url.URL{Scheme: u.scheme}

	if u.socket != "" {
		t.Path = u.socket
	} else {
		if u.port == "" {
			t.Host = u.host
		} else {
			t.Host = net.JoinHostPort(u.host, u.port)
		}
		t.Path = u.resource
	}

	if u.user != "" {
		if u.password != "" {
			t.User = url.UserPassword(u.user, u.password)
		} else {
			t.User = url.User(u.user)
		}

	}

	return t.String()
}

func (u *URI) withCreds(user, password string) *URI {
	u.password = password
	u.user = user

	return u
}

type Defaults struct {
	Port   string
	Scheme string
}

// New parses a given rawuri and returns a new filled URI structure.
// It ignores embedded credentials according to https://www.ietf.org/rfc/rfc3986.txt.
// Use NewWithCreds to add credentials to a structure.
func New(rawuri string, defaults *Defaults) (res *URI, err error) {
	var (
		isSocket bool
		noScheme bool
		port     string
	)

	res = &URI{}

	rawuri = strings.TrimSpace(rawuri)

	// https://tools.ietf.org/html/rfc6874#section-2
	// %25 is allowed to escape a percent sign in IPv6 scoped-address literals
	if !strings.Contains(rawuri, "%25") {
		rawuri = strings.Replace(rawuri, "%", "%25", -1)
	}

	if noScheme = !strings.Contains(rawuri, "://"); noScheme {
		if defaults != nil && defaults.Scheme != "" {
			rawuri = defaults.Scheme + "://" + rawuri
		} else {
			rawuri = "tcp://" + rawuri
		}
	}

	u, err := url.Parse(rawuri)
	if err != nil {
		return nil, err
	}

	res.scheme = u.Scheme
	port = u.Port()

	if port == "" {
		if defaults != nil {
			port = defaults.Port
		}
	}

	if port != "" {
		if _, err = strconv.ParseUint(port, 10, 16); err != nil {
			return nil, errors.New("port must be integer and must be between 0 and 65535")
		}
	}

	isSocket = res.scheme == "unix" || (noScheme && u.Hostname() == "" && u.Path != "")
	if isSocket {
		if u.Path == "" {
			return nil, errors.New("socket is required")
		}

		res.scheme = "unix"
		res.socket = u.Path
	} else {
		if u.Hostname() == "" {
			return nil, errors.New("host is required")
		}

		res.host = u.Hostname()
		res.port = port
		res.resource = strings.Trim(u.Path, "/\\")
	}

	return res, err
}

func NewWithCreds(rawuri, user, password string, defaults *Defaults) (res *URI, err error) {
	res, err = New(rawuri, defaults)
	if err != nil {
		return nil, err
	}

	return res.withCreds(user, password), nil
}

type URIValidator struct {
	Defaults       *Defaults
	AllowedSchemes []string
}

func (v URIValidator) Validate(value *string) error {
	if value == nil {
		return nil
	}

	res, err := New(*value, v.Defaults)
	if err != nil {
		return err
	}

	if v.AllowedSchemes != nil {
		for _, s := range v.AllowedSchemes {
			if res.Scheme() == s {
				return nil
			}
		}

		return fmt.Errorf("allowed schemes: %s", strings.Join(v.AllowedSchemes, ", "))
	}

	return nil
}
