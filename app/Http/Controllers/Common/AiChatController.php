<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AiChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    public function history(Request $request): JsonResponse
    {
        $chats = AiChat::where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->paginate(20);

        return ApiResponse::success($chats);
    }

    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|min:2|max:1000',
        ]);

        try {
            $answer = $this->callOpenAi($request->question);
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error('AI service temporarily unavailable. Please try again later.', 503);
        }

        $chat = AiChat::create([
            'user_id'  => $request->user()->id,
            'question' => $request->question,
            'answer'   => $answer,
        ]);

        return ApiResponse::created($chat, 'Answer received');
    }

    public function clearHistory(Request $request): JsonResponse
    {
        AiChat::where('user_id', $request->user()->id)->delete();

        return ApiResponse::success(null, 'Chat history cleared');
    }

    private function callOpenAi(string $question): string
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            Log::warning('OpenAI API key not configured');
            return 'AI service is temporarily unavailable. Please try again later.';
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model'    => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an educational tutor assistant. Help students learn effectively.'],
                ['role' => 'user',   'content' => $question],
            ],
            'max_tokens' => 1000,
        ]);

        if ($response->successful()) {
            return $response->json('choices.0.message.content', 'No answer received.');
        }

        return 'AI service temporarily unavailable. Please try again later.';
    }
}
