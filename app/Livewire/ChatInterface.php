<?php

namespace App\Livewire;

use App\Models\ChatSession;
use App\Models\ChatMessage;
use App\Models\ChatAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Collection;

class ChatInterface extends Component
{
    use WithFileUploads;

    public $message = '';
    public $sessionId;
    public $sessions;
    public $file;
    public $aiResponse = '';
    public $isLoading = false;

    public function mount()
    {
        $this->sessions = ChatSession::where('user_id', Auth::id())->latest()->get();
        $this->sessionId = $this->sessions->first()?->id ?? $this->createNewSession();
    }

    public function createNewSession()
    {
        $session = ChatSession::create([
            'user_id' => Auth::id(),
            'title' => 'New Session ' . now()->format('Y-m-d H:i:s'),
        ]);
        $this->sessions = ChatSession::where('user_id', Auth::id())->latest()->get();
        return $session->id;
    }

    public function switchSession($sessionId)
    {
        $this->sessionId = $sessionId;
        $this->aiResponse = '';
        $this->isLoading = false;
    }

    public function sendMessage()
    {
        if (trim($this->message) === '' && !$this->file) {
            return;
        }

        // Save user message
        if (trim($this->message) !== '') {
            ChatMessage::create([
                'session_id' => $this->sessionId,
                'role' => 'user',
                'message' => $this->message,
            ]);
        }

        // Handle file upload
        $fileSummary = null;
        if ($this->file) {
            $this->validate([
                'file' => 'file|mimetypes:application/pdf,text/csv,application/xml|max:10240',
            ]);

            $filePath = $this->file->store('attachments', 'local');
            $fileType = $this->file->getClientMimeType();
            $fileType = $fileType === 'application/xml' ? 'stix' : str_replace('application/', '', $fileType);
            $fileSummary = $this->mockFileSummary($fileType, $this->file->getClientOriginalName());

            ChatAttachment::create([
                'session_id' => $this->sessionId,
                'file_path' => $filePath,
                'original_name' => $this->file->getClientOriginalName(),
                'file_type' => $fileType,
                'summary' => $fileSummary,
            ]);
        }

        // Detect incident ID
        $incidentId = null;
        if ($this->message && preg_match('/INC-?\d{6,7}\b/', $this->message, $matches)) {
            $incidentId = $matches[0];
            $session = ChatSession::find($this->sessionId);
            $incidentIds = $session->incident_ids ?? [];
            if (!in_array($incidentId, $incidentIds)) {
                $incidentIds[] = $incidentId;
                $session->update(['incident_ids' => $incidentIds]);
            }
        }

        // Mock SIEM data
        $siemData = $incidentId ? $this->mockSiemQuery($incidentId) : null;

        // Build prompt
        $prompt = "You are a SOC assistant. Provide concise, accurate, security-focused responses.\n";
        if ($this->message) {
            $prompt .= "User message: " . $this->message . "\n";
        }
        if ($incidentId) {
            $prompt .= "Incident Data: " . json_encode($siemData, JSON_PRETTY_PRINT) . "\n";
        }
        if ($fileSummary) {
            $prompt .= "File Summary: " . $fileSummary . "\n";
        }

        // Send to Ollama and stream response
        $this->isLoading = true;
        $this->dispatch('start-loading');
        try {
            $this->streamOllamaResponse($prompt);
        } catch (\Exception $e) {
            $this->isLoading = false;
            ChatMessage::create([
                'session_id' => $this->sessionId,
                'role' => 'ai',
                'message' => 'Error: Failed to get AI response: ' . $e->getMessage(),
            ]);
            $this->dispatch('message-updated');
            $this->dispatch('stop-loading');
        }

        $this->message = '';
        $this->file = null;
    }

    protected function mockSiemQuery($incidentId)
    {
        return [
            'incident_id' => $incidentId,
            'alert_name' => 'Suspicious Login Attempt',
            'affected_user' => 'john.doe@example.com',
            'device' => 'DESKTOP-XYZ123',
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    protected function mockFileSummary($fileType, $fileName)
    {
        return "Mock summary for $fileType file '$fileName': Contains threat intelligence data.";
    }

    protected function streamOllamaResponse($prompt)
    {
        $messageId = ChatMessage::create([
            'session_id' => $this->sessionId,
            'role' => 'ai',
            'message' => '',
        ])->id;

        $response = Http::withOptions(['stream' => true])
            ->timeout(60)
            ->post(config('services.ollama.url') . '/api/generate', [
                'model' => 'mistral',
                'prompt' => $prompt,
                'stream' => true,
            ]);

        if (!$response->successful()) {
            $this->isLoading = false;
            $this->dispatch('stop-loading');
            throw new \Exception('Ollama API request failed: ' . $response->status());
        }

        $stream = $response->getBody();
        $buffer = '';
        while (!$stream->eof()) {
            $chunk = $stream->read(512);
            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $data = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['response'])) {
                    $this->aiResponse .= $data['response'];
                    ChatMessage::find($messageId)->update(['message' => $this->aiResponse]);
                    $this->dispatch('message-updated');
                    ob_flush();
                    flush();
                    usleep(30000);
                }
                if (isset($data['done']) && $data['done']) {
                    break 2;
                }
            }
        }

        $stream->close();
        $this->isLoading = false;
        $this->dispatch('stop-loading');
        $this->dispatch('message-updated');
    }

    public function render()
    {
        $messages = ChatMessage::where('session_id', $this->sessionId)
            ->select('id', 'session_id', 'role', 'message', 'created_at')
            ->get()
            ->map(function ($message) {
                return (object) [
                    'type' => 'message',
                    'id' => $message->id,
                    'role' => $message->role,
                    'message' => $message->message,
                    'created_at' => $message->created_at,
                ];
            });

        $attachments = ChatAttachment::where('session_id', $this->sessionId)
            ->select('id', 'session_id', 'original_name', 'file_type', 'summary', 'created_at')
            ->get()
            ->map(function ($attachment) {
                return (object) [
                    'type' => 'attachment',
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'file_type' => $attachment->file_type,
                    'summary' => $attachment->summary,
                    'created_at' => $attachment->created_at,
                ];
            });

        $items = $messages->merge($attachments)->sortBy('created_at');

        return view('livewire.chat-interface', [
            'items' => $items,
            'sessions' => $this->sessions,
        ]);
    }
}
