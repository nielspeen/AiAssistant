<?php

namespace Modules\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    protected $table = 'aiassistant_document_chunks';

    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'content_hash',
        'token_count',
        'embedding',
        'embedding_model',
        'metadata',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function embedding(): array
    {
        return $this->decodeJsonAttribute($this->embedding);
    }

    public function metadata(): array
    {
        return $this->decodeJsonAttribute($this->metadata);
    }

    public function setEmbeddingAttribute($value)
    {
        $this->attributes['embedding'] = $this->encodeJsonAttribute($value);
    }

    public function setMetadataAttribute($value)
    {
        $this->attributes['metadata'] = $this->encodeJsonAttribute($value);
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
