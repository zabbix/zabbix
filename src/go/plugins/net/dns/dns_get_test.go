//go:build linux && (amd64 || arm64)

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

package dns

import (
	"errors"
	"fmt"
	"testing"
)

func checkArgsFlagsParsing(t *testing.T, tc *argsFlagsParsingTestCase) {
	var o dnsGetOptions
	err := o.setFlags(tc.flagsInArgs)

	if err != nil {
		if tc.err.Error() != err.Error() {
			t.Errorf("\nExpected error: ->%v<-, but received: ->%v<-\n", tc.err, err)
		}

		return
	}

	if fmt.Sprint(tc.flagsOut) != fmt.Sprint(o.flags) {
		t.Errorf("\nExpected options: ->%v<-\nFor input flags: ->%s<-\nBut received: ->%v<-\n", tc.flagsOut,
			tc.flagsInArgs, o.flags)
	}
}

type argsFlagsParsingTestCase struct {
	flagsInArgs string
	err         error
	flagsOut    map[string]bool
}

func TestDNSGetFlagsArgsParsing(t *testing.T) {
	argsFlagsParsingTestCases := []*argsFlagsParsingTestCase{
		{
			flagsInArgs: "cdflag,rdflag,dnssec,nsid,edns0,aaflag,adflag",
			err:         nil,
			flagsOut:    map[string]bool{"cdflag": true, "rdflag": true, "dnssec": true, "nsid": true, "edns0": true, "aaflag": true, "adflag": true},
		},

		{
			flagsInArgs: "",
			err:         nil,
			flagsOut:    map[string]bool{"cdflag": false, "rdflag": true, "dnssec": false, "nsid": false, "edns0": true, "aaflag": false, "adflag": false},
		},

		{
			flagsInArgs: "cdflag",
			err:         nil,
			flagsOut:    map[string]bool{"cdflag": true, "rdflag": true, "dnssec": false, "nsid": false, "edns0": true, "aaflag": false, "adflag": false},
		},

		{
			flagsInArgs: "rdflag",
			err:         nil,
			flagsOut:    map[string]bool{"cdflag": false, "rdflag": true, "dnssec": false, "nsid": false, "edns0": true, "aaflag": false, "adflag": false},
		},

		{
			flagsInArgs: "cdflag,rdflag",
			err:         nil,
			flagsOut:    map[string]bool{"cdflag": true, "rdflag": true, "dnssec": false, "nsid": false, "edns0": true, "aaflag": false, "adflag": false},
		},

		{
			flagsInArgs: "nocdflag,nordflag,nodnssec,nonsid,noedns0,noaaflag,noadflag",
			err:         nil,
			flagsOut:    map[string]bool{"cdflag": false, "rdflag": false, "dnssec": false, "nsid": false, "edns0": false, "aaflag": false, "adflag": false},
		},

		{
			flagsInArgs: "noadflag,noaaflag,noedns0,nonsid,nodnssec,nordflag,nocdflag",
			err:         nil,
			flagsOut:    map[string]bool{"cdflag": false, "rdflag": false, "dnssec": false, "nsid": false, "edns0": false, "aaflag": false, "adflag": false},
		},

		{
			flagsInArgs: ",",
			err:         errors.New("Invalid flag supplied:."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: " ",
			err:         errors.New("Invalid flag supplied: ."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "\\",
			err:         errors.New("Invalid flag supplied:\\."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: ",dflag",
			err:         errors.New("Invalid flag supplied:."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "dflag",
			err:         errors.New("Invalid flag supplied:dflag."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "cdflagrdflag",
			err:         errors.New("Invalid flag supplied:cdflagrdflag."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "0",
			err:         errors.New("Invalid flag supplied:0."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "noadflag,noaaflag,noedns0,nonsid,nodnssec,nordflag,",
			err:         errors.New("Invalid flag supplied:."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "aaflag,noaaflag",
			err:         errors.New("Invalid flags combination, cannot use noaaflag and aaflag together."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "rdflag,aaflag,noaaflag",
			err:         errors.New("Invalid flags combination, cannot use noaaflag and aaflag together."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "rdflag,aaflag,nsid,noaaflag",
			err:         errors.New("Invalid flags combination, cannot use noaaflag and aaflag together."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "noedns0,nsid",
			err:         errors.New("Invalid flags combination, cannot use noedns0 and nsid together."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "noadflag,noaaflag,noedns0,nonsid,nodnssec,nordflag,nocdflag,",
			err:         errors.New("Too many flags supplied: 8."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: ",,,,,,,,,,",
			err:         errors.New("Too many flags supplied: 11."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "noadflag,aaflag,noedns0,nordflag,aaflag,",
			err:         errors.New("Duplicate flag supplied: aaflag."),
			flagsOut:    nil,
		},

		{
			flagsInArgs: "noadflag,aaflag,x,aaflag,noadflag",
			err:         errors.New("Invalid flag supplied:x."),
			flagsOut:    nil,
		},
	}

	for _, tc := range argsFlagsParsingTestCases {
		checkArgsFlagsParsing(t, tc)
	}
}
