<?php

namespace App\Services;

use App\Support\BusinessProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key') ?? '';
        $this->baseUrl = config('services.openai.base_url') ?? 'https://api.openai.com/v1';
    }

    public function generateReply(string $message, array $context = []): ?string
    {
        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured');
            return null;
        }

        $systemPrompt = $this->buildSystemPrompt($context);

        // Start with system prompt
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Append conversation history in chronological order BEFORE the current message
        if (!empty($context['conversation_history'])) {
            foreach ($context['conversation_history'] as $historyMessage) {
                $content = $historyMessage['message'] ?? '';
                // Skip empty or placeholder messages
                if (empty(trim($content)) || in_array($content, ['[Image]', '[Video]', '[Audio]', '[Document]'])) {
                    continue;
                }
                $messages[] = [
                    'role' => $historyMessage['direction'] === 'incoming' ? 'user' : 'assistant',
                    'content' => $content,
                ];
            }
        }

        // Append the current incoming message last
        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => config('services.openai.model', 'gpt-3.5-turbo'),
                    'messages' => $messages,
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $reply = $data['choices'][0]['message']['content'] ?? null;

                Log::info('AI reply generated', [
                    'model' => config('services.openai.model'),
                    'input_message' => $message,
                    'reply_length' => $reply ? strlen($reply) : 0,
                ]);

                return $reply;
            }

            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('OpenAI service error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function qualifyLead(string $message, array $leadInfo = []): array
    {
        if (empty($this->apiKey)) {
            return ['qualified' => false, 'reason' => 'AI not configured'];
        }

        $prompt = "Analyze this message from a potential lead and determine if they are qualified:\n\n";
        $prompt .= "Message: {$message}\n\n";
        
        if (!empty($leadInfo)) {
            $prompt .= "Lead Information:\n";
            foreach ($leadInfo as $key => $value) {
                $prompt .= "- {$key}: {$value}\n";
            }
        }

        $prompt .= "\nProvide a JSON response with:\n";
        $prompt .= "- qualified: boolean\n";
        $prompt .= "- confidence: number (0-1)\n";
        $prompt .= "- reason: string\n";
        $prompt .= "- suggested_action: string\n";
        $prompt .= "\nQualify based on tour and travel interest (destinations, dates, travelers, budget, package type, booking readiness).";

        try {
            $response = Http::timeout(30)
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => config('services.openai.model', 'gpt-3.5-turbo'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a tour and travel lead qualification assistant for Shrishti Trip. Always respond with valid JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 300,
                    'temperature' => 0.3,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '{}';
                
                $result = json_decode($content, true);
                
                return [
                    'qualified' => $result['qualified'] ?? false,
                    'confidence' => $result['confidence'] ?? 0,
                    'reason' => $result['reason'] ?? 'Unable to determine',
                    'suggested_action' => $result['suggested_action'] ?? 'Contact the lead',
                ];
            }

            return ['qualified' => false, 'reason' => 'API error'];
        } catch (\Throwable $e) {
            Log::error('OpenAI lead qualification error', ['error' => $e->getMessage()]);
            return ['qualified' => false, 'reason' => 'Service error'];
        }
    }

    public function generateFollowupSuggestion(array $leadInfo, array $conversationHistory = []): ?string
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $prompt = "Generate a follow-up message suggestion for this lead:\n\n";
        $prompt .= "Lead Information:\n";
        foreach ($leadInfo as $key => $value) {
            $prompt .= "- {$key}: {$value}\n";
        }

        if (!empty($conversationHistory)) {
            $prompt .= "\nRecent Conversation:\n";
            foreach (array_slice($conversationHistory, -5) as $message) {
                $prompt .= "- {$message['direction']}: {$message['message']}\n";
            }
        }

        $prompt .= "\nProvide a professional, friendly follow-up message (max 200 characters).";

        try {
            $response = Http::timeout(30)
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => config('services.openai.model', 'gpt-3.5-turbo'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful tour and travel sales assistant for Shrishti Trip. Focus on destinations, travel dates, number of travelers, budget, packages, hotels, transport, and bookings. Keep responses concise, warm, and professional.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 150,
                    'temperature' => 0.8,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? null;
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('OpenAI followup suggestion error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function analyzeSentiment(string $message): array
    {
        if (empty($this->apiKey)) {
            return ['sentiment' => 'neutral', 'score' => 0];
        }

        try {
            $response = Http::timeout(30)
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => config('services.openai.model', 'gpt-3.5-turbo'),
                    'messages' => [
                        ['role' => 'system', 'content' => 'Analyze sentiment and respond with JSON: {"sentiment": "positive|negative|neutral", "score": number (-1 to 1)}'],
                        ['role' => 'user', 'content' => $message],
                    ],
                    'max_tokens' => 100,
                    'temperature' => 0.1,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '{}';
                $result = json_decode($content, true);

                return [
                    'sentiment' => $result['sentiment'] ?? 'neutral',
                    'score' => $result['score'] ?? 0,
                ];
            }

            return ['sentiment' => 'neutral', 'score' => 0];
        } catch (\Throwable $e) {
            Log::error('OpenAI sentiment analysis error', ['error' => $e->getMessage()]);
            return ['sentiment' => 'neutral', 'score' => 0];
        }
    }

    private function buildSystemPrompt(array $context): string
    {
        $businessName = $context['business_name']
            ?? config('services.whatsapp.ai_business_name')
            ?? config('app.name', 'Shrishti Trip');
        $companyProfile = (string) config(
            'services.whatsapp.ai_company_profile',
            'Shrishti Trip is a tour and travel company offering domestic and international tour packages, custom itineraries, hotel bookings, and transport arrangements.'
        );

        $prompt = "You are a real human travel consultant replying on WhatsApp for {$businessName}, not a bot. ";
        $prompt .= "{$companyProfile} ";
        $prompt .= "Help customers with tour and travel questions: destinations, travel dates, number of travelers, budget, tour packages, hotels, flights, transport, visa guidance (general only), itinerary planning, group tours, family holidays, and how to book. ";
        $prompt .= "Do not talk about fashion, clothing, IT, software, CRM, or unrelated retail products. Stay focused on tours and travel only. ";
        $prompt .= "Write natural conversational Hinglish in Roman script, mixing simple Hindi and English like a friendly Indian travel agent. Never use Hindi/Devanagari script. ";
        $prompt .= "Match the customer's tone and language. If they write only English, use easy English with a light Hinglish touch; if they use Hinglish, reply naturally in Hinglish. ";
        $prompt .= "Keep replies short, usually 1 to 3 sentences, and vary the wording so replies do not feel copied or robotic. Avoid long menus, formal marketing language, and unnecessary bullet lists. Use at most one suitable emoji occasionally, not in every reply. ";
        $prompt .= "Ask only one useful follow-up question at a time when needed, such as destination, travel month, number of people, or budget range. ";
        $prompt .= "Do not repeatedly say 'our team will contact you' or repeat the business name. ";
        $prompt .= "Do not invent exact package prices, seat availability, or confirmed bookings. If unsure, naturally say 'main check karke batata/batati hoon' without claiming it is confirmed. ";
        $prompt .= "Never ask for passwords, OTPs, or payment card details over chat.";

        $contactBlock = $context['contact_info'] ?? BusinessProfile::aiContactBlock();
        if ($contactBlock !== '') {
            $prompt .= " Official contact details you may share when asked: {$contactBlock}.";
        }

        if (!empty($context['lead_info']['name'])) {
            $prompt .= " You are speaking with {$context['lead_info']['name']}.";
        }

        if (!empty($context['lead_info']['status'])) {
            $prompt .= " Their current lead status is: {$context['lead_info']['status']}.";
        }

        if (!empty($context['products'])) {
            $prompt .= " Popular offerings: " . implode(', ', $context['products']) . ".";
        }

        return $prompt;
    }
}
