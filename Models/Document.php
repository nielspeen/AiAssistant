<?php

namespace Modules\AiAssistant\Models;

use App\Mailbox;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    const SOURCE_TYPE_URL = 'url';
    const SOURCE_TYPE_API = 'api';

    const STATUS_PENDING = 'pending';
    const STATUS_INDEXED = 'indexed';
    const STATUS_FAILED = 'failed';

    const CANONICAL_LOCALE = 'en';

    const SUPPORTED_LOCALES = ['en', 'ja', 'zh', 'ko'];

    protected $table = 'aiassistant_documents';

    protected $fillable = [
        'mailbox_id',
        'title',
        'source_type',
        'source_url',
        'source_identifier',
        'canonical_locale',
        'localized_urls',
        'content',
        'content_hash',
        'status',
        'enabled',
        'last_indexed_at',
        'last_error',
        'metadata',
    ];

    protected $dates = [
        'last_indexed_at',
        'created_at',
        'updated_at',
    ];

    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class, 'document_id');
    }

    public function markdownUrl(): string
    {
        return static::markdownUrlFor($this->source_url);
    }

    public function localizedUrl(string $locale): string
    {
        $urls = $this->localizedUrls();

        if (!empty($urls[$locale])) {
            return $urls[$locale];
        }

        if ($this->source_type === static::SOURCE_TYPE_API && strpos($this->source_url, 'api://') === 0) {
            return '';
        }

        return $this->source_url;
    }

    public function localizedUrls(): array
    {
        return $this->decodeJsonAttribute($this->localized_urls);
    }

    public function metadata(): array
    {
        return $this->decodeJsonAttribute($this->metadata);
    }

    public function setLocalizedUrlsAttribute($value)
    {
        $this->attributes['localized_urls'] = $this->encodeJsonAttribute($value);
    }

    public function setMetadataAttribute($value)
    {
        $this->attributes['metadata'] = $this->encodeJsonAttribute($value);
    }

    public static function normalizeSourceUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\.md$/i', '', $url);

        return rtrim($url, '/');
    }

    public static function localizedUrlsFor(string $sourceUrl): array
    {
        $sourceUrl = static::normalizeSourceUrl($sourceUrl);
        $urls = [];

        foreach (static::SUPPORTED_LOCALES as $locale) {
            $urls[$locale] = preg_replace('#/en(/|$)#', '/'.$locale.'$1', $sourceUrl, 1);
        }

        return $urls;
    }

    public static function markdownUrlFor(string $sourceUrl): string
    {
        return rtrim(static::normalizeSourceUrl($sourceUrl), '/') . '.md';
    }

    public static function apiSourceIdentifier(string $identifier): string
    {
        return hash('sha256', 'api:' . trim($identifier));
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
