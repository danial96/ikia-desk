<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 text-center">
    <a href="{{ route('profile.show.user', $emp->id) }}" style="display:block;text-decoration:none;">
        <img src="{{ $emp->avatar_url }}" class="w-16 h-16 rounded-full mx-auto mb-3 object-cover" style="transition:opacity .15s;" onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'" alt="">
        <h3 class="font-semibold text-gray-800" style="margin-bottom:2px;">{{ $emp->name }}</h3>
        @if($emp->position)
        <p style="font-size:12px;color:#0ea5e9;font-weight:500;margin:0 0 2px;">{{ $emp->position }}</p>
        @endif
    </a>
    <p class="text-xs text-gray-500 mb-1">{{ $emp->email }}</p>
    @if($emp->department)
    <p class="text-xs text-gray-400 mb-2">{{ $emp->department }}</p>
    @endif
    <div class="flex items-center justify-center gap-2 mb-3">
        <span class="text-xs px-2 py-1 rounded-full font-medium
            {{ $emp->role === 'super_admin' ? 'bg-purple-100 text-purple-700' :
               ($emp->role === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
            {{ str_replace('_', ' ', ucfirst($emp->role)) }}
        </span>
        <span class="text-xs px-2 py-1 rounded-full {{ $emp->is_active ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' }}">
            {{ $emp->is_active ? 'Active' : 'Inactive' }}
        </span>
    </div>
    <div class="flex gap-2 justify-center">
        <form action="{{ route('chat.direct.create') }}" method="POST">
            @csrf
            <input type="hidden" name="user_id" value="{{ $emp->id }}">
            <button type="submit" class="px-3 py-1.5 text-xs border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition">Message</button>
        </form>
        @if(auth()->user()->isAdmin() && $emp->id !== auth()->id())
        <form action="{{ route('employees.toggle-active', $emp) }}" method="POST">
            @csrf
            <button type="submit" class="px-3 py-1.5 text-xs border {{ $emp->is_active ? 'border-orange-200 text-orange-600 hover:bg-orange-50' : 'border-green-200 text-green-600 hover:bg-green-50' }} rounded-lg transition">
                {{ $emp->is_active ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
        @endif
    </div>
    @if(auth()->user()->isAdmin())
    <button onclick="empEditOpen({{ $emp->toJson() }})"
            class="mt-2 w-full px-3 py-1.5 text-xs border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition">
        Edit
    </button>
    @endif
</div>
