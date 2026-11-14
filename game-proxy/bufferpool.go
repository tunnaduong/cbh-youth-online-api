package main

import (
	"net/http/httputil"
	"sync"
)

type bufferPool struct{ pool sync.Pool }

func newBufferPool() httputil.BufferPool {
	return &bufferPool{pool: sync.Pool{New: func() any { b := make([]byte, 32*1024); return &b }}}
}

func (p *bufferPool) Get() []byte  { return *(p.pool.Get().(*[]byte)) }
func (p *bufferPool) Put(b []byte) { p.pool.Put(&b) }
