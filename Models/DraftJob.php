<?php

namespace Modules\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;

class DraftJob extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $table = 'aiassistant_draft_jobs';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'status',
        'locale',
        'document_limit',
        'result',
        'error_type',
        'error_message',
        'error_detail',
        'started_at',
        'completed_at',
    ];

    protected $dates = [
        'started_at',
        'completed_at',
        'created_at',
        'updated_at',
    ];

    public function result(): array
    {
        return $this->decodeJsonAttribute($this->result);
    }

    public function setResultAttribute($value)
    {
        $this->attributes['result'] = $this->encodeJsonAttribute($value);
    }

    private function decodeJsonAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function encodeJsonAttribute($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
