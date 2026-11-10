//go:build linux && (amd64 || arm64)

/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package wildcard

import (
	"testing"

	"github.com/google/go-cmp/cmp"
)

func TestMinimize(t *testing.T) {
	t.Parallel()

	type args struct {
		pattern string
	}

	tests := []struct {
		name string
		args args
		want string
	}{
		{
			name: "+valid",
			args: args{
				pattern: "pat*ern***123**",
			},
			want: "pat*ern*123*",
		},
		{
			name: "+collapsesConsecutiveAsterisks",
			args: args{
				pattern: "**",
			},
			want: "*",
		},
		{
			name: "+singleAsterisk",
			args: args{
				pattern: "*",
			},
			want: "*",
		},
		{
			name: "-emptyPattern",
			args: args{
				pattern: "",
			},
			want: "",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := Minimize(tt.args.pattern)

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("Minimize() mismatch (+want -got):\n%s", diff)
			}
		})
	}
}

func TestMatch(t *testing.T) {
	t.Parallel()

	type args struct {
		value   string
		pattern string
	}

	tests := []struct {
		name string
		args args
		want bool
	}{
		{
			name: "+valid",
			args: args{
				value:   "fabc",
				pattern: "fabc",
			},
			want: true,
		},
		{
			name: "+singleAsteriskMatchesAnyValue",
			args: args{
				value:   "abcd",
				pattern: "*",
			},
			want: true,
		},
		{
			name: "+singleAsteriskMatchesNumericValue",
			args: args{
				value:   "123",
				pattern: "*",
			},
			want: true,
		},
		{
			name: "+singleAsteriskMatchesEmptyValue",
			args: args{
				value:   "",
				pattern: "*",
			},
			want: true,
		},
		{
			name: "+emptyPatternMatchesEmptyValue",
			args: args{
				value:   "",
				pattern: "",
			},
			want: true,
		},
		{
			name: "+multipleAsterisksInMiddle",
			args: args{
				value:   "lorem_ipsum",
				pattern: "lo*ip*m",
			},
			want: true,
		},
		{
			name: "+asteriskMatchesMultipleCharacters",
			args: args{
				value:   "aaa123bc",
				pattern: "a*bc",
			},
			want: true,
		},
		{
			name: "+asteriskBacktracksToLaterMatch",
			args: args{
				value:   "aXbXbcXbc",
				pattern: "a*bc",
			},
			want: true,
		},
		{
			name: "+twoAsterisksMatch",
			args: args{
				value:   "aXbXbcXXd",
				pattern: "a*bc*d",
			},
			want: true,
		},
		{
			name: "+twoAsterisksBacktrack",
			args: args{
				value:   "aXbXabcXXd",
				pattern: "a*bc*d",
			},
			want: true,
		},
		{
			name: "+trailingAsteriskMatchesEmptySuffix",
			args: args{
				value:   "abcd",
				pattern: "abcd*",
			},
			want: true,
		},
		{
			name: "+trailingAsteriskMatchesSuffix",
			args: args{
				value:   "abcdef",
				pattern: "abcd*",
			},
			want: true,
		},
		{
			name: "+leadingAsteriskMatchesEmptyPrefix",
			args: args{
				value:   "ab",
				pattern: "*ab",
			},
			want: true,
		},
		{
			name: "+leadingAsteriskMatchesPrefix",
			args: args{
				value:   "123ab",
				pattern: "*ab",
			},
			want: true,
		},
		{
			name: "+surroundingAsterisksMatchExact",
			args: args{
				value:   "abcde",
				pattern: "*abcde*",
			},
			want: true,
		},
		{
			name: "+surroundingAsterisksMatchWithPrefixAndSuffix",
			args: args{
				value:   "123abcdef",
				pattern: "*abcde*",
			},
			want: true,
		},
		{
			name: "+consecutiveAsterisksMatchEmptyGap",
			args: args{
				value:   "ac",
				pattern: "a***c",
			},
			want: true,
		},
		{
			name: "+consecutiveAsterisksMatchSingleCharGap",
			args: args{
				value:   "abc",
				pattern: "a***c",
			},
			want: true,
		},
		{
			name: "+consecutiveAsterisksMatchMultiCharGap",
			args: args{
				value:   "a_whatever_c",
				pattern: "a***c",
			},
			want: true,
		},
		{
			name: "+leadingConsecutiveAsterisksMatch",
			args: args{
				value:   "cde",
				pattern: "***c*e",
			},
			want: true,
		},
		{
			name: "+leadingConsecutiveAsterisksMatchWithPrefix",
			args: args{
				value:   "abce",
				pattern: "***c*e",
			},
			want: true,
		},
		{
			name: "+leadingConsecutiveAsterisksMatchLongPrefix",
			args: args{
				value:   "whatever_c123e",
				pattern: "***c*e",
			},
			want: true,
		},
		{
			name: "+trailingConsecutiveAsterisksMatchEmptySuffix",
			args: args{
				value:   "ac",
				pattern: "a*c***",
			},
			want: true,
		},
		{
			name: "+trailingConsecutiveAsterisksMatchSingleCharSuffix",
			args: args{
				value:   "axc",
				pattern: "a*c***",
			},
			want: true,
		},
		{
			name: "+trailingConsecutiveAsterisksMatchLongSuffix",
			args: args{
				value:   "a1c_whatever",
				pattern: "a*c***",
			},
			want: true,
		},
		{
			name: "+literalCommandMatches",
			args: args{
				value:   "/usr/bin/uptime",
				pattern: "/usr/bin/uptime",
			},
			want: true,
		},
		{
			name: "+explicitAsteriskMatchesRepeatedLiteral",
			args: args{
				value:   "fooXfoo",
				pattern: "foo*foo",
			},
			want: true,
		},
		{
			name: "+utf8LiteralMatches",
			args: args{
				value:   "é",
				pattern: "é",
			},
			want: true,
		},
		{
			name: "+utf8PrefixMatches",
			args: args{
				value:   "é args",
				pattern: "é*",
			},
			want: true,
		},
		{
			name: "+utf8SuffixBacktracks",
			args: args{
				value:   "éXé",
				pattern: "*é",
			},
			want: true,
		},
		{
			name: "-literalValueDiffers",
			args: args{
				value:   "bar",
				pattern: "foo",
			},
			want: false,
		},
		{
			name: "-literalValueLongerThanPattern",
			args: args{
				value:   "fooo",
				pattern: "foo",
			},
			want: false,
		},
		{
			name: "-literalValueShorterThanPattern",
			args: args{
				value:   "fo",
				pattern: "foo",
			},
			want: false,
		},
		{
			name: "-literalValueEmpty",
			args: args{
				value:   "",
				pattern: "foo",
			},
			want: false,
		},
		{
			name: "-literalValueRepeated",
			args: args{
				value:   "foofoo",
				pattern: "foo",
			},
			want: false,
		},
		{
			name: "-literalValueSandwiched",
			args: args{
				value:   "fooXfoo",
				pattern: "foo",
			},
			want: false,
		},
		{
			name: "-literalCommandSandwiched",
			args: args{
				value:   "/usr/bin/uptime; cat /etc/passwd; /usr/bin/uptime",
				pattern: "/usr/bin/uptime",
			},
			want: false,
		},
		{
			name: "-utf8PrefixDiffers",
			args: args{
				value:   "é",
				pattern: "ê*",
			},
			want: false,
		},
		{
			name: "-utf8PrefixDiffersWithSuffix",
			args: args{
				value:   "é args",
				pattern: "ê*",
			},
			want: false,
		},
		{
			name: "-emptyPatternNonEmptyValue",
			args: args{
				value:   "abc",
				pattern: "",
			},
			want: false,
		},
		{
			name: "-emptyPatternNonEmptyNumericValue",
			args: args{
				value:   "123",
				pattern: "",
			},
			want: false,
		},
		{
			name: "-asteriskNoValidBacktrack",
			args: args{
				value:   "aXbXbcXb",
				pattern: "a*bc",
			},
			want: false,
		},
		{
			name: "-twoAsterisksNoValidBacktrack",
			args: args{
				value:   "aXbXbcXb",
				pattern: "a*bc*d",
			},
			want: false,
		},
		{
			name: "-prefixAndSuffixCannotOverlap",
			args: args{
				value:   "abc",
				pattern: "ab*bc",
			},
			want: false,
		},
		{
			name: "-middleCannotOverlapSuffix",
			args: args{
				value:   "abc",
				pattern: "a*b*bc",
			},
			want: false,
		},
		{
			name: "-middlePartsCannotOverlap",
			args: args{
				value:   "abbc",
				pattern: "a*bb*b*c",
			},
			want: false,
		},
		{
			name: "+literalFooMatches",
			args: args{
				value:   "foo",
				pattern: "foo",
			},
			want: true,
		},
		{
			name: "+singleAsteriskMatchesAbc",
			args: args{
				value:   "abc",
				pattern: "*",
			},
			want: true,
		},
		{
			name: "+trailingAsteriskMatchesAbc",
			args: args{
				value:   "abc",
				pattern: "abc*",
			},
			want: true,
		},
		{
			name: "+trailingAsteriskMatchesAbcWithSuffix",
			args: args{
				value:   "abcdef",
				pattern: "abc*",
			},
			want: true,
		},
		{
			name: "+leadingAsteriskMatchesAbc",
			args: args{
				value:   "abc",
				pattern: "*abc",
			},
			want: true,
		},
		{
			name: "+leadingAsteriskMatchesAbcWithPrefix",
			args: args{
				value:   "123abc",
				pattern: "*abc",
			},
			want: true,
		},
		{
			name: "+surroundingAsterisksMatchAbc",
			args: args{
				value:   "abc",
				pattern: "*abc*",
			},
			want: true,
		},
		{
			name: "+surroundingAsterisksMatchAbcWithPrefixAndSuffix",
			args: args{
				value:   "123abcdef",
				pattern: "*abc*",
			},
			want: true,
		},
		{
			name: "+trailingConsecutiveAsterisksMatchAbc",
			args: args{
				value:   "abc",
				pattern: "a*c***",
			},
			want: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			got := Match(tt.args.value, tt.args.pattern)

			diff := cmp.Diff(tt.want, got)
			if diff != "" {
				t.Fatalf("Match() mismatch (+want -got):\n%s", diff)
			}
		})
	}
}
