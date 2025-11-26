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

package udp

import (
	_ "embed"
	"os"
	"path/filepath"
	"testing"

	"github.com/google/go-cmp/cmp"
)

//go:embed testdata/udp_linux.txt
var mockUDP4Data []byte

//go:embed testdata/udp6_linux.txt
var mockUDP6Data []byte

//go:embed testdata/udp_broken.txt
var mockBrokenData []byte

func TestPlugin_exportNetUDPListen(t *testing.T) {
	t.Parallel()

	type args struct {
		params []string
	}

	tests := []struct {
		name          string
		args          args
		want          string
		wantErr       bool
		mockV4Content []byte
		mockV6Content []byte
	}{
		{
			name: "+port10051",
			args: args{
				params: []string{"10051"},
			},
			mockV4Content: mockUDP4Data,
			mockV6Content: mockUDP6Data,
			want:          "1",
			wantErr:       false,
		},
		{
			name: "+port123",
			args: args{
				params: []string{"123"},
			},
			mockV4Content: mockUDP4Data,
			mockV6Content: mockUDP6Data,
			want:          "1",
			wantErr:       false,
		},
		{
			name: "+noPort9999",
			args: args{
				params: []string{"9999"},
			},
			mockV4Content: mockUDP4Data,
			mockV6Content: mockUDP6Data,
			want:          "0",
			wantErr:       false,
		},
		{
			name: "ipv4_file_empty_check_ipv6",
			args: args{
				params: []string{"123"}, // 123 is in IPv6
			},
			mockV4Content: []byte{}, // Empty file
			mockV6Content: mockUDP6Data,
			want:          "1", // Should skip parsing V4 and find in V6
			wantErr:       false,
		},
		{
			name: "-ipv4_file_broken_content",
			args: args{
				params: []string{"10051"},
			},
			mockV4Content: mockBrokenData,
			mockV6Content: mockUDP6Data,
			want:          "1",   // Found in udp6.
			wantErr:       false, // Will not fail because we are just searching for a pattern.
		},
		{
			name: "-ipv6_file_broken_content",
			args: args{
				params: []string{"100510"},
			},
			mockV4Content: mockUDP4Data,
			mockV6Content: mockBrokenData,
			want:          "0",
			wantErr:       false, // Will not fail because we are just searching for a pattern.
		},
		{
			name: "-invalidPort",
			args: args{
				params: []string{"invalid"},
			},
			mockV4Content: mockUDP4Data,
			mockV6Content: mockUDP6Data,
			want:          "",
			wantErr:       true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			p := &Plugin{
				udp4Path: createMockFile(t, tt.mockV4Content),
				udp6Path: createMockFile(t, tt.mockV6Content),
			}

			got, err := p.exportNetUDPListen(tt.args.params)
			if (err != nil) != tt.wantErr {
				t.Fatalf("Plugin.exportNetUDPListen() error = %v, wantErr %v", err, tt.wantErr)
			}

			if diff := cmp.Diff(tt.want, got); diff != "" {
				t.Fatalf("Plugin.exportNetUDPListen() mismatch (-want +got):\n%s", diff)
			}
		})
	}
}

// Helper to write embedded data to a temp file.
func createMockFile(t *testing.T, content []byte) string {
	t.Helper()

	var (
		tmpDir = t.TempDir()
		path   = filepath.Join(tmpDir, "mock_udp")
	)

	err := os.WriteFile(path, content, 0600)
	if err != nil {
		t.Fatalf("failed to write temp mock file: %v", err)
	}

	return path
}
