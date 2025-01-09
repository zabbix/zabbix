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

package dns

import (
	"errors"
	"fmt"
	"reflect"
	"testing"

	"github.com/miekg/dns"
)

func Test_dnsGetOptions_setFlags(t *testing.T) {
	tests := []struct {
		name        string
		flagsInArgs string
		flagsOut    map[string]bool
		err         error
	}{
		{
			"basic_scenario",
			"cdflag,rdflag,dnssec,nsid,edns0,aaflag,adflag",
			map[string]bool{"cdflag": true, "rdflag": true, "dnssec": true, "nsid": true, "edns0": true,
				"aaflag": true, "adflag": true},
			nil,
		},

		{
			"empty_flags",
			"",
			map[string]bool{"cdflag": false, "rdflag": true, "dnssec": false, "nsid": false, "edns0": true,
				"aaflag": false, "adflag": false},
			nil,
		},

		{
			"single_flag1",
			"cdflag",
			map[string]bool{"cdflag": true, "rdflag": true, "dnssec": false, "nsid": false, "edns0": true,
				"aaflag": false, "adflag": false},
			nil,
		},

		{
			"single_flag2",
			"rdflag",
			map[string]bool{"cdflag": false, "rdflag": true, "dnssec": false, "nsid": false, "edns0": true,
				"aaflag": false, "adflag": false},
			nil,
		},

		{
			"two_flags",
			"cdflag,rdflag",
			map[string]bool{"cdflag": true, "rdflag": true, "dnssec": false, "nsid": false, "edns0": true,
				"aaflag": false, "adflag": false},
			nil,
		},

		{
			"many_negative_flags",
			"nocdflag,nordflag,nodnssec,nonsid,noedns0,noaaflag,noadflag",
			map[string]bool{"cdflag": false, "rdflag": false, "dnssec": false, "nsid": false,
				"edns0": false, "aaflag": false, "adflag": false},
			nil,
		},

		{
			"many_negative_flags_reordered",
			"noadflag,noaaflag,noedns0,nonsid,nodnssec,nordflag,nocdflag",
			map[string]bool{"cdflag": false, "rdflag": false, "dnssec": false, "nsid": false,
				"edns0": false, "aaflag": false, "adflag": false},
			nil,
		},

		{
			"coma",
			",",
			nil,
			errors.New("Invalid flag supplied:."),
		},

		{
			"empty_space",
			" ",
			nil,
			errors.New("Invalid flag supplied: ."),
		},

		{
			"backslash",
			"\\",
			nil,
			errors.New("Invalid flag supplied:\\."),
		},

		{
			"coma_invalid_flag",
			",dflag",
			nil,
			errors.New("Invalid flag supplied:."),
		},

		{
			"invalid_flag",
			"dflag",
			nil,
			errors.New("Invalid flag supplied:dflag."),
		},

		{
			"invalid_flag_combo",
			"cdflagrdflag",
			nil,
			errors.New("Invalid flag supplied:cdflagrdflag."),
		},

		{
			"zero",
			"0",
			nil,
			errors.New("Invalid flag supplied:0."),
		},

		{
			"extra_coma",
			"noadflag,noaaflag,noedns0,nonsid,nodnssec,nordflag,",
			nil,
			errors.New("Invalid flag supplied:."),
		},

		{
			"opposite_flags",
			"aaflag,noaaflag",
			nil,
			errors.New("Invalid flags combination, cannot use noaaflag and aaflag together."),
		},

		{
			"regular_flag_before_opposite_flags",
			"rdflag,aaflag,noaaflag",
			nil,
			errors.New("Invalid flags combination, cannot use noaaflag and aaflag together."),
		},

		{
			"regular_flag_between_opposite_flags",
			"rdflag,aaflag,nsid,noaaflag",
			nil,
			errors.New("Invalid flags combination, cannot use noaaflag and aaflag together."),
		},

		{
			"noedns_and_nsid_used_together",
			"noedns0,nsid",
			nil,
			errors.New("Invalid flags combination, cannot use noedns0 and nsid together."),
		},

		{
			"too_many_flags_1",
			"noadflag,noaaflag,noedns0,nonsid,nodnssec,nordflag,nocdflag,",
			nil,
			errors.New("Too many flags supplied: 8."),
		},

		{
			"too_many_flags_2",
			",,,,,,,,,,",
			nil,
			errors.New("Too many flags supplied: 11."),
		},

		{
			"duplicate_flag",
			"noadflag,aaflag,noedns0,nordflag,aaflag,",
			nil,
			errors.New("Duplicate flag supplied: aaflag."),
		},

		{
			"invalid_flag",
			"noadflag,aaflag,x,aaflag,noadflag",
			nil,
			errors.New("Invalid flag supplied:x."),
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			var o dnsGetOptions
			err := o.setFlags(tt.flagsInArgs)

			if err != nil {
				if tt.err.Error() != err.Error() {
					t.Errorf("\nExpected error: ->%v<-, but received: ->%v<-\n", tt.err, err)
				}

				return
			}
			if fmt.Sprint(tt.flagsOut) != fmt.Sprint(o.flags) {
				t.Errorf("\nExpected options: ->%v<-\nFor input flags: ->%s<-\nBut received: ->%v<-\n",
					tt.flagsOut, tt.flagsInArgs, o.flags)
			}
		})
	}
}

func Test_insertAtEveryNthPosition(t *testing.T) {
	type args struct {
		s string
		n int
		r rune
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{
			"basic_scenario",
			args{s: "6770646e732d61726e", n: 2, r: ' '},
			"67 70 64 6e 73 2d 61 72 6e",
		},

		{
			"empty",
			args{s: "", n: 2, r: ' '},
			"",
		},

		{
			"1_char",
			args{s: "6", n: 2, r: ' '},
			"6",
		},

		{
			"2_chars",
			args{s: "67", n: 2, r: ' '},
			"67",
		},

		{
			"3_chars",
			args{s: "677", n: 2, r: ' '},
			"67 7",
		},

		{
			"3_chars_single_delimiter",
			args{s: "677", n: 1, r: ' '},
			"6 7 7",
		},

		{
			"3_chars_zero_delimiter",
			args{s: "677", n: 0, r: ' '},
			"677",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := insertAtEveryNthPosition(tt.args.s, tt.args.n, tt.args.r); got != tt.want {
				t.Errorf("insertAtEveryNthPosition() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_parseRRs(t *testing.T) {
	type args struct {
		rrs    []dns.RR
		source string
	}
	tests := []struct {
		name    string
		args    args
		want    map[string][]any
		wantErr bool
	}{
		// TODO: Add test cases.
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := parseRRs(tt.args.rrs, tt.args.source)
			if (err != nil) != tt.wantErr {
				t.Errorf("parseRRs() error = %v, wantErr %v", err, tt.wantErr)

				return
			}
			if !reflect.DeepEqual(got, tt.want) {
				t.Errorf("parseRRs() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_parseRespQuestion(t *testing.T) {
	type args struct {
		respQuestion []dns.Question
	}
	tests := []struct {
		name string
		args args
		want map[string][]any
	}{
		// TODO: Add test cases.
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := parseRespQuestion(tt.args.respQuestion); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("parseRespQuestion() = %v, want %v", got, tt.want)
			}
		})
	}
}
