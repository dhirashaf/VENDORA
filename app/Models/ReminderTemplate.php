<?php

namespace App\Models;

use App\Enums\Channel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderTemplate extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'subject', 'body', 'channel'];

    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(ReminderRule::class, 'template_id');
    }

    /**
     * Ganti placeholder {kunci} pada subjek dan isi pesan.
     *
     * @param  array<string, string>  $replacements
     * @return array{subject: string, body: string}
     */
    public function render(array $replacements): array
    {
        $placeholders = array_map(fn (string $key): string => '{'.$key.'}', array_keys($replacements));

        return [
            'subject' => str_replace($placeholders, array_values($replacements), $this->subject),
            'body' => str_replace($placeholders, array_values($replacements), $this->body),
        ];
    }
}
