// +build windows

package win32

import (
	"fmt"
	"syscall"
	"testing"
	"unsafe"
)

func TestGetIfTable2(t *testing.T) {

	table, err := GetIfTable2()

	if err != nil {
		t.Errorf("%s", err)
		return
	}

	fmt.Printf("entries: %d\n", table.NumEntries)

	rows := (*[1 << 16]MIB_IF_ROW2)(unsafe.Pointer(&table.Table[0]))[:table.NumEntries:table.NumEntries]

	for i := range rows {
		row := &rows[i]
		fmt.Printf("<%d> %s\n", i, syscall.UTF16ToString(row.Description[:]))
	}

	FreeMibTable(table)
}

func TestGetIpAddrTable(t *testing.T) {
	size, err := GetIpAddrTable(nil, 0, false)

	if err != nil {
		t.Errorf("%s", err)
		return
	}

	if size > 0 {
		fmt.Printf("size: %d\n", size)
	}

	buf := make([]byte, size)
	table := (*MIB_IPADDRTABLE)(unsafe.Pointer(&buf[0]))
	size2, err2 := GetIpAddrTable(table, size, false)

	if err2 != nil {
		t.Errorf("%s", err)
		return
	}

	if size2 > size {
		t.Errorf("New returned size %d is not equal to the requested %d", size2, size)
		t.Fail()
	}

	fmt.Printf("entries: %d\n", table.NumEntries)
}
