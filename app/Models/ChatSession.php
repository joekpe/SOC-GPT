<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSession extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'user_id', 'incident_ids'];

    protected $casts = [
        'incident_ids' => 'array',
    ];

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'session_id');
    }

    public function attachments()
    {
        return $this->hasMany(ChatAttachment::class, 'session_id');
    }
}
