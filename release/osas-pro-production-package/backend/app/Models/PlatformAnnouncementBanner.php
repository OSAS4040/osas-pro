<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * صف واحد فعلي: إعلان المنصة لجميع المستأجرين (يُدار من مشغّلي المنصة).
 */
class PlatformAnnouncementBanner extends Model
{
    protected $fillable = [
        'is_enabled',
        'title',
        'message',
        'link_url',
        'link_text',
        'variant',
        'dismissible',
        'dismiss_token',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled'  => 'boolean',
            'dismissible' => 'boolean',
        ];
    }

    public static function theOne(): self
    {
        $row = static::query()->first();
        if ($row !== null) {
            return $row;
        }

        return static::query()->create([
            'is_enabled'    => false,
            'title'         => null,
            'message'       => null,
            'link_url'      => null,
            'link_text'     => null,
            'variant'       => 'promo',
            'dismissible'   => true,
            'dismiss_token' => Str::random(32),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicBannerPayload(): array
    {
        $message = trim((string) $this->message);
        if (! $this->is_enabled || $message === '') {
            return [
                'enabled'       => false,
                'dismiss_token' => (string) $this->dismiss_token,
            ];
        }

        $variant = in_array($this->variant, ['info', 'success', 'warning', 'promo'], true)
            ? (string) $this->variant
            : 'promo';

        return [
            'enabled'       => true,
            'title'         => $this->title ? (string) $this->title : null,
            'message'       => $message,
            'link_url'      => $this->link_url ? (string) $this->link_url : null,
            'link_text'     => $this->link_text ? (string) $this->link_text : null,
            'variant'       => $variant,
            'dismissible'   => (bool) $this->dismissible,
            'dismiss_token' => (string) $this->dismiss_token,
        ];
    }
}
