<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatAttachment extends Model
{
    use HasFactory;

    protected $fillable = ['session_id', 'file_path', 'original_name', 'file_type', 'summary'];

    public function session()
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }
}
