<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolLog extends Model
{
    protected $fillable = ['conversation_id', 'tool_name', 'parameters', 'result_summary'];

    protected function casts(): array
    {
        return ['parameters' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class);
    }
}
