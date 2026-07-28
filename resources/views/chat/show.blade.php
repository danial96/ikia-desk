@extends('layouts.app')

@php
$other = $conversation->type === 'direct' ? $conversation->members->where('id', '!=', auth()->id())->first() : null;
$convName = $conversation->type === 'direct' ? ($other?->name ?? 'Unknown') : ($conversation->name ?? 'Group Chat');
@endphp

@section('title', $convName)
@section('page-title', $convName)

@section('content')
<div x-data="chatApp()" class="flex h-[calc(100vh-160px)] gap-4 -m-6">

    {{-- Conversations sidebar --}}
    <div class="w-72 flex-shrink-0 bg-white border-r border-gray-200 overflow-y-auto">
        <div class="p-4 border-b border-gray-100">
            <p class="font-semibold text-gray-700 text-sm">Conversations</p>
        </div>
        @if($generalChat)
        <a href="{{ route('chat.show', $generalChat) }}"
           class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition {{ $conversation->id === $generalChat->id ? 'bg-indigo-50 border-r-2 border-indigo-500' : '' }}">
            <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-lg flex-shrink-0">🌐</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800">General Chat</p>
            </div>
        </a>
        @endif
        @foreach($conversations as $conv)
        @php
            $cOther = $conv->type === 'direct' ? $conv->members->where('id', '!=', auth()->id())->first() : null;
            $cName = $conv->type === 'direct' ? ($cOther?->name ?? 'Unknown') : ($conv->name ?? 'Group');
        @endphp
        <a href="{{ route('chat.show', $conv) }}"
           class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition {{ $conversation->id === $conv->id ? 'bg-indigo-50 border-r-2 border-indigo-500' : '' }}">
            <div class="w-9 h-9 rounded-full flex-shrink-0 overflow-hidden">
                @if($conv->type === 'direct' && $cOther)
                <img src="{{ $cOther->avatar_url }}" class="w-full h-full object-cover" alt="">
                @else
                <div class="w-full h-full bg-purple-100 flex items-center justify-center text-purple-600 text-sm font-bold">G</div>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">{{ $cName }}</p>
                @if($conv->lastMessage)
                <p class="text-xs text-gray-400 truncate">{{ $conv->lastMessage->content }}</p>
                @endif
            </div>
        </a>
        @endforeach
    </div>

    {{-- Chat area --}}
    <div class="flex-1 flex flex-col bg-white">
        {{-- Chat header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            @if($conversation->type === 'direct' && $other)
            <img src="{{ $other->avatar_url }}" class="w-9 h-9 rounded-full" alt="">
            @elseif($conversation->type === 'general')
            <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-lg">🌐</div>
            @else
            <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            @endif
            <div>
                <p class="font-semibold text-gray-800">{{ $convName }}</p>
                @if($conversation->type !== 'direct')
                <p class="text-xs text-gray-400">{{ $conversation->members->count() }} members</p>
                @endif
            </div>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4" id="messages-container" style="background-image:url('{{ asset('pattern-chat.svg') }}');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:local;">
            @foreach($messages as $msg)
            @php $isMe = $msg->user_id === auth()->id(); @endphp
            <div class="flex {{ $isMe ? 'justify-end' : 'justify-start' }} gap-3 items-end">
                @if(!$isMe)
                <img src="{{ $msg->user->avatar_url }}" class="w-7 h-7 rounded-full flex-shrink-0 mb-1" alt="">
                @endif
                <div class="max-w-xs lg:max-w-md">
                    @if(!$isMe)
                    <p class="text-xs text-gray-500 mb-1 ml-1">{{ $msg->user->name }}</p>
                    @endif
                    <div class="px-4 py-2.5 rounded-2xl text-sm leading-relaxed
                        {{ $isMe ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-gray-100 text-gray-800 rounded-bl-sm' }}">
                        {!! preg_replace('/@(\w+)/', '<span class="' . ($isMe ? 'text-indigo-200' : 'text-indigo-600') . ' font-medium">@$1</span>', e($msg->content)) !!}
                    </div>
                    <p class="text-xs text-gray-400 mt-1 {{ $isMe ? 'text-right' : 'text-left' }} ml-1">{{ $msg->created_at->format('H:i') }}</p>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Message input --}}
        <div class="px-6 py-4 border-t border-gray-100">
            <form action="{{ route('chat.send', $conversation) }}" method="POST"
                  x-data="mentionInput()" @submit="$el.querySelector('textarea').value = content">
                @csrf
                <div class="relative">
                    <textarea name="content" rows="1" required x-model="content" @input="handleInput" @keydown="handleKeydown"
                              @keydown.enter.prevent="if(!$event.shiftKey) $el.closest('form').submit()"
                              class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm resize-none"
                              placeholder="Type a message... @mention someone, Enter to send"></textarea>

                    <div x-show="showMentions && mentionResults.length > 0"
                         class="absolute bottom-full left-0 mb-1 bg-white border border-gray-200 rounded-lg shadow-lg z-10 min-w-48">
                        <template x-for="(user, idx) in mentionResults" :key="user.id">
                            <button type="button" @click="selectMention(user)"
                                    class="flex items-center gap-2 w-full px-3 py-2 text-sm hover:bg-indigo-50 text-left">
                                <span x-text="user.name" class="text-gray-700"></span>
                            </button>
                        </template>
                    </div>

                    <button type="submit" class="absolute right-3 bottom-3 text-indigo-600 hover:text-indigo-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function chatApp() {
    return {
        init() {
            const container = document.getElementById('messages-container');
            if (container) container.scrollTop = container.scrollHeight;
        }
    }
}

function mentionInput() {
    return {
        content: '',
        showMentions: false,
        mentionResults: [],
        mentionQuery: '',
        mentionStart: -1,
        users: @json($employees->map(fn($u) => ['id' => $u->id, 'name' => $u->name])),

        handleInput(e) {
            const text = this.content;
            const pos = e.target.selectionStart;
            const before = text.substring(0, pos);
            const match = before.match(/@(\w*)$/);
            if (match) {
                this.mentionQuery = match[1].toLowerCase();
                this.mentionStart = pos - match[0].length;
                this.mentionResults = this.users.filter(u => u.name.toLowerCase().includes(this.mentionQuery));
                this.showMentions = this.mentionResults.length > 0;
            } else {
                this.showMentions = false;
            }
        },

        handleKeydown(e) {
            if (this.showMentions && e.key === 'Escape') this.showMentions = false;
        },

        selectMention(user) {
            const before = this.content.substring(0, this.mentionStart);
            const after = this.content.substring(this.mentionStart + this.mentionQuery.length + 1);
            this.content = before + '@' + user.name + ' ' + after;
            this.showMentions = false;
        }
    }
}
</script>
@endsection
