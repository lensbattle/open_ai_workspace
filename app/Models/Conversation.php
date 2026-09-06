<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    public const DEFAULT_TITLE = 'New conversation';

    protected $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Build a short, ChatGPT-style conversation name from the first prompt.
     */
    public static function titleFromPrompt(string $prompt): string
    {
        $title = trim(preg_replace('/\s+/', ' ', strip_tags($prompt)) ?? '');

        return $title === '' ? self::DEFAULT_TITLE : Str::limit($title, 45, '...');
    }
}
