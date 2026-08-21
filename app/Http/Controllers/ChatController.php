<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $conversations = $user->conversations()
            ->with(['lastMessage.user', 'members'])
            ->orderByDesc(function ($q) {
                $q->select('created_at')->from('messages')
                  ->whereColumn('conversation_id', 'conversations.id')
                  ->latest()->limit(1);
            })
            ->get();

        $generalChat = Conversation::where('type', 'general')->first();
        $employees = User::where('is_active', true)->where('id', '!=', $user->id)->get();

        return view('chat.index', compact('conversations', 'generalChat', 'employees'));
    }

    public function show(Conversation $conversation)
    {
        $user = Auth::user();

        if (!$conversation->members->contains('id', $user->id)) {
            if ($conversation->type === 'general') {
                $conversation->members()->syncWithoutDetaching([$user->id]);
            } else {
                abort(403);
            }
        }

        $messages = $conversation->messages()->with('user')->latest()->limit(50)->get()->reverse()->values();

        ConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        $conversations = $user->conversations()->with(['lastMessage.user', 'members'])->get();
        $employees = User::where('is_active', true)->where('id', '!=', $user->id)->get();
        $generalChat = Conversation::where('type', 'general')->first();

        return view('chat.show', compact('conversation', 'messages', 'conversations', 'employees', 'generalChat'));
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        $request->validate(['content' => 'required|string|max:5000']);

        $user = Auth::user();

        if (!$conversation->members->contains('id', $user->id)) {
            if ($conversation->type === 'general') {
                $conversation->members()->syncWithoutDetaching([$user->id]);
            } else {
                abort(403);
            }
        }

        $mentions = [];
        preg_match_all('/@(\w+)/', $request->content, $matches);
        if (!empty($matches[1])) {
            $mentions = User::whereIn('name', $matches[1])->pluck('id')->toArray();
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'content'         => $request->content,
            'mentions'        => $mentions,
        ]);

        ConversationMember::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        return back();
    }

    public function createDirect(Request $request)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $user = Auth::user();
        $otherId = $request->user_id;

        $existing = Conversation::where('type', 'direct')
            ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
            ->whereHas('members', fn($q) => $q->where('user_id', $otherId))
            ->first();

        if ($existing) {
            return redirect()->route('chat.show', $existing);
        }

        $conversation = Conversation::create([
            'type'       => 'direct',
            'created_by' => $user->id,
        ]);

        $conversation->members()->attach([$user->id, $otherId]);

        return redirect()->route('chat.show', $conversation);
    }

    public function createGroup(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'members' => 'required|array|min:1',
            'members.*' => 'exists:users,id',
        ]);

        $user = Auth::user();

        $conversation = Conversation::create([
            'type'       => 'group',
            'name'       => $request->name,
            'created_by' => $user->id,
        ]);

        $members = array_unique(array_merge($request->members, [$user->id]));
        $conversation->members()->attach($members);

        return redirect()->route('chat.show', $conversation);
    }
}
