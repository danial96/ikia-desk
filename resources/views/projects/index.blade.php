@extends('layouts.app')
@section('title', 'Projects')
@section('page-title', 'Projects')

@section('content')
<style>
.glass { background:rgba(255,255,255,.07); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.1); border-radius:14px; }
.txt-main { color:rgba(255,255,255,.88); }
.txt-sub  { color:rgba(255,255,255,.4); }
.proj-card { background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1); border-radius:14px; overflow:hidden; transition:background .2s,transform .2s; }
.proj-card:hover { background:rgba(255,255,255,.1); transform:translateY(-2px); }
</style>

<div x-data="{ showModal: false }">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
        <p class="txt-sub" style="font-size:13px;margin:0;">{{ $projects->total() }} projects total</p>
        @if(auth()->user()->isAdmin())
        <button @click="showModal = true" class="ikia-btn">
            <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Project
        </button>
        @endif
    </div>

    <div id="proj-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
        @forelse($projects as $project)
        @include('projects._project_card')
        @empty
        <div style="grid-column:1/-1;padding:60px 20px;text-align:center;">
            <div style="width:60px;height:60px;border-radius:16px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;background:rgba(27,114,232,.1);">
                <svg style="width:28px;height:28px;" fill="none" stroke="#1B72E8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
            </div>
            <p class="txt-sub" style="font-size:13px;margin:0 0 12px;">No projects yet</p>
            @if(auth()->user()->isAdmin())
            <button @click="showModal = true" style="font-size:13px;font-weight:600;color:#1B72E8;background:none;border:none;cursor:pointer;">
                Create your first project →
            </button>
            @endif
        </div>
        @endforelse
    </div>

    <div id="proj-sentinel" style="height:1px;margin-top:8px;"></div>
    <div id="proj-scroll-status" style="text-align:center;padding:16px 0;color:rgba(255,255,255,.3);font-size:12px;display:none;">All projects loaded</div>

    <script>
    (function(){
        var _page = {{ $projects->currentPage() }};
        var _hasMore = {{ $projects->hasMorePages() ? 'true' : 'false' }};
        var _loading = false;
        var _total = {{ $projects->total() }};

        var grid = document.getElementById('proj-grid');
        var sentinel = document.getElementById('proj-sentinel');
        var statusEl = document.getElementById('proj-scroll-status');

        async function loadMore() {
            if (!_hasMore || _loading) return;
            _loading = true;
            sentinel.innerHTML = '<div style="text-align:center;padding:16px;grid-column:1/-1;"><div style="display:inline-block;width:20px;height:20px;border:2px solid rgba(255,255,255,.2);border-top-color:#00D4E8;border-radius:50%;animation:spin .7s linear infinite;"></div></div>';

            try {
                var r = await fetch(location.pathname + '?page=' + (_page + 1), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                var d = await r.json();
                grid.insertAdjacentHTML('beforeend', d.html);
                _page++;
                _hasMore = d.hasMore;
                sentinel.innerHTML = '';
                if (!_hasMore) {
                    sentinel.style.display = 'none';
                    statusEl.style.display = 'block';
                    statusEl.textContent = 'All ' + _total + ' projects loaded';
                }
            } catch(e) {
                sentinel.innerHTML = '<div style="text-align:center;padding:16px;color:rgba(255,82,82,.6);font-size:12px;">Load failed. <button onclick="projLoadMore()" style="color:#00D4E8;background:none;border:none;cursor:pointer;font-size:12px;">Retry</button></div>';
            }
            _loading = false;
        }

        window.projLoadMore = loadMore;

        new IntersectionObserver(function(entries){
            if (entries[0].isIntersecting) loadMore();
        }, { rootMargin: '300px' }).observe(sentinel);
    })();
    </script>

    {{-- Create Project Modal --}}
    @if(auth()->user()->isAdmin())
    <div x-show="showModal" x-cloak
         style="position:fixed;inset:0;z-index:100;display:flex;align-items:center;justify-content:center;padding:20px;"
         @keydown.escape.window="showModal = false">
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);"
             @click="showModal = false"></div>
        <div style="position:relative;background:rgba(15,20,50,.92);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.12);border-radius:18px;width:100%;max-width:440px;padding:28px;"
             @click.stop>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;">
                <h2 class="txt-main" style="font-size:15px;font-weight:700;margin:0;">Create New Project</h2>
                <button @click="showModal = false"
                        style="background:rgba(255,255,255,.08);border:none;border-radius:8px;color:rgba(255,255,255,.5);cursor:pointer;width:30px;height:30px;display:flex;align-items:center;justify-content:center;">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <form action="{{ route('projects.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px;">Project Name *</label>
                    <input type="text" name="name" required
                           style="width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.9);border-radius:10px;padding:11px 14px;font-size:13.5px;outline:none;box-sizing:border-box;transition:border-color .2s;"
                           onfocus="this.style.borderColor='#00D4E8'" onblur="this.style.borderColor='rgba(255,255,255,.12)'"
                           placeholder="Enter project name">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px;">Description</label>
                    <textarea name="description" rows="3"
                              style="width:100%;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.9);border-radius:10px;padding:11px 14px;font-size:13.5px;outline:none;box-sizing:border-box;resize:none;transition:border-color .2s;"
                              onfocus="this.style.borderColor='#00D4E8'" onblur="this.style.borderColor='rgba(255,255,255,.12)'"
                              placeholder="Project description..."></textarea>
                </div>
                <div style="margin-bottom:22px;">
                    <label style="display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;">Color</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        @foreach(['#1B72E8','#00D4E8','#7B2FBE','#10b981','#f59e0b','#ef4444','#ec4899','#0f172a'] as $color)
                        <label style="cursor:pointer;">
                            <input type="radio" name="color" value="{{ $color }}" class="sr-only" {{ $loop->first ? 'checked' : '' }}>
                            <span style="width:30px;height:30px;border-radius:50%;display:block;background:{{ $color }};transition:transform .15s,box-shadow .15s;"
                                  onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform=''"></span>
                        </label>
                        @endforeach
                    </div>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" @click="showModal = false"
                            style="flex:1;padding:11px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);color:rgba(255,255,255,.6);border-radius:10px;font-size:13.5px;font-weight:500;cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit" class="ikia-btn" style="flex:1;justify-content:center;">
                        Create Project
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
