<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MessagingController extends Controller
{
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = $user->conversations()
            ->with(['participants:id,name,username,avatar_url', 'messages' => fn ($q) => $q->latest('id')->limit(1)->with('sender:id,name,username')])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->paginate(30);

        $items->getCollection()->transform(function (Conversation $conversation) use ($user): Conversation {
            $membership = $conversation->participants->firstWhere('id', $user->id)?->pivot;
            $lastReadAt = $membership?->last_read_at;
            $conversation->setAttribute('unread_count', $conversation->messages()
                ->where('sender_id', '!=', $user->id)
                ->when($lastReadAt, fn ($query) => $query->where('created_at', '>', $lastReadAt))
                ->when(!$lastReadAt, fn ($query) => $query->where('sender_id', '!=', $user->id))
                ->count());
            return $conversation;
        });

        return response()->json($items);
    }

    public function createConversation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(['direct', 'group'])],
            'title' => ['nullable', 'string', 'max:120'],
            'participant_ids' => ['required', 'array', 'min:1', 'max:99'],
            'participant_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $participantIds = collect($data['participant_ids'])->push($request->user()->id)->unique()->values();
        $type = $data['type'] ?? ($participantIds->count() > 2 ? 'group' : 'direct');

        if ($type === 'direct' && $participantIds->count() !== 2) {
            return response()->json(['message' => 'A direct conversation must have exactly two participants.'], 422);
        }

        if ($type === 'direct') {
            $existing = Conversation::query()->where('type', 'direct')
                ->whereHas('participants', fn ($q) => $q->whereIn('users.id', $participantIds), '=', 2)
                ->withCount(['participants as matching_participants' => fn ($q) => $q->whereIn('users.id', $participantIds)])
                ->get()
                ->first(fn ($conversation) => (int) $conversation->matching_participants === 2
                    && $conversation->participants()->count() === 2);

            if ($existing) {
                return response()->json(['conversation' => $existing->load('participants:id,name,username,avatar_url')]);
            }
        }

        $conversation = DB::transaction(function () use ($request, $participantIds, $data, $type): Conversation {
            $conversation = Conversation::create([
                'type' => $type,
                'title' => $data['title'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $conversation->participants()->sync($participantIds);
            return $conversation;
        });

        return response()->json(['conversation' => $conversation->load('participants:id,name,username,avatar_url')], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);
        $messages = $conversation->messages()
            ->whereNull('deleted_at')
            ->with('sender:id,name,username,avatar_url')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return response()->json($messages);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'type' => ['sometimes', Rule::in(['text'])],
        ]);

        $message = DB::transaction(function () use ($request, $conversation, $data): Message {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'type' => $data['type'] ?? 'text',
                'body' => $data['body'],
            ]);
            $conversation->forceFill(['last_message_at' => $message->created_at])->save();
            return $message;
        });

        return response()->json(['message' => $message->load('sender:id,name,username,avatar_url')], 201);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);
        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);
        return response()->json(['message' => 'Conversation marked as read.']);
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->participants()->where('users.id', $request->user()->id)->exists(), 403, 'You are not a participant in this conversation.');
    }
}
