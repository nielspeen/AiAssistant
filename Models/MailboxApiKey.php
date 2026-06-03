<?php

namespace Modules\AiAssistant\Models;

use App\Mailbox;
use Illuminate\Database\Eloquent\Model;

class MailboxApiKey extends Model
{
    protected $table = 'aiassistant_mailbox_api_keys';

    protected $fillable = [
        'mailbox_id',
        'key_hash',
        'key_preview',
        'enabled',
        'last_used_at',
    ];

    protected $dates = [
        'last_used_at',
        'created_at',
        'updated_at',
    ];

    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    public static function issueForMailbox(int $mailboxId): array
    {
        $token = static::generateToken();
        $key = static::firstOrNew(['mailbox_id' => $mailboxId]);
        $key->key_hash = static::hashToken($token);
        $key->key_preview = static::preview($token);
        $key->enabled = true;
        $key->save();

        return [$key, $token];
    }

    public static function findEnabledByToken(string $token)
    {
        $token = trim($token);

        if (!$token) {
            return null;
        }

        return static::where('key_hash', static::hashToken($token))
            ->where('enabled', true)
            ->first();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', trim($token));
    }

    public static function preview(string $token): string
    {
        $token = trim($token);

        if (strlen($token) <= 12) {
            return $token;
        }

        return substr($token, 0, 6) . '...' . substr($token, -6);
    }

    private static function generateToken(): string
    {
        return 'fsai_' . bin2hex(random_bytes(32));
    }
}
