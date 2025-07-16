<div class="flex space-x-4">
    <!-- Session Sidebar -->
    <div class="w-1/4 bg-gray-100 p-4 rounded-lg">
        <h2 class="text-lg font-semibold mb-4">Chat Sessions</h2>
        <button wire:click="createNewSession" class="mb-4 bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">New Session</button>
        <ul>
            @foreach ($sessions as $session)
                <li wire:key="session-{{ $session->id }}"
                    wire:click="switchSession({{ $session->id }})"
                    class="p-2 cursor-pointer {{ $sessionId == $session->id ? 'bg-indigo-200' : 'hover:bg-gray-200' }}">
                    {{ $session->title }} @if ($session->incident_ids) ({{ implode(', ', $session->incident_ids) }}) @endif
                </li>
            @endforeach
        </ul>
    </div>

    <!-- Chat Area -->
    <div class="w-3/4 bg-white p-6 rounded-lg shadow-lg">
        <div class="h-96 overflow-y-auto overflow-x-hidden mb-4 p-4 border rounded-md" x-data="{ scrollToBottom() { this.querySelector('.h-96').scrollTop = this.querySelector('.h-96').scrollHeight } }" x-init="scrollToBottom">
            <div wire:loading wire:target="sendMessage" wire:loading.delay.longer class="flex justify-center items-center my-4 z-10">
                <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-indigo-600 mr-2" style="display: block;"></div>
                <span class="text-gray-500 text-sm">Processing...</span>
            </div>
            @foreach ($items as $item)
                @if ($item->type === 'message')
                    <div wire:key="message-{{ $item->id }}"
                         class="mb-4 flex {{ $item->role === 'user' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] p-3 rounded-lg {{ $item->role === 'user' ? 'bg-indigo-100 ml-auto' : 'bg-gray-100 mr-auto' }} break-words">
                            <strong>{{ $item->role === 'user' ? 'You' : 'AI' }}:</strong>
                            <div class="whitespace-pre-wrap break-words">{{ $item->message }}</div>
                            <div class="text-xs text-gray-500 mt-1 text-center">{{ $item->created_at->format('Y-m-d H:i') }}</div>
                        </div>
                    </div>
                @else
                    <div wire:key="attachment-{{ $item->id }}"
                         class="mb-4 flex justify-start">
                        <div class="max-w-[80%] p-3 rounded-lg bg-gray-100 break-words">
                            <strong>Attachment:</strong> {{ $item->original_name }} ({{ $item->file_type }})<br>
                            <strong>Summary:</strong> {{ $item->summary }}
                            <div class="text-xs text-gray-500 mt-1 text-center">{{ $item->created_at->format('Y-m-d H:i') }}</div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
        <form wire:submit.prevent="sendMessage">
            <textarea wire:model="message"
                      rows="4"
                      class="w-full p-2 border rounded-md focus:ring-indigo-500 focus:border-indigo-500 mb-2"
                      placeholder="Type your message (e.g., 'Check INC123456')"></textarea>
            <input wire:model="file" type="file" class="mb-2" accept=".pdf,.csv,.xml">
            <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700" wire:loading.attr="disabled">Send</button>
        </form>
    </div>
</div>

@script
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('message-updated', () => {
            const chatArea = document.querySelector('.h-96');
            chatArea.scrollTop = chatArea.scrollHeight;
        });
        Livewire.on('start-loading', () => {
            const spinner = document.querySelector('.animate-spin');
            if (spinner) spinner.style.display = 'block';
        });
        Livewire.on('stop-loading', () => {
            const spinner = document.querySelector('.animate-spin');
            if (spinner) spinner.style.display = 'none';
        });
    });
</script>
@endscript
