<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppCampaign extends Model
{
    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'whatsapp_account_id',
        'user_id',
        'name',
        'template_name',
        'template_language',
        'template_components',
        'audience_filters',
        'status',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'template_components' => 'array',
            'audience_filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaignMessages(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignMessage::class, 'whatsapp_campaign_id');
    }

    public function getProgressAttribute(): int
    {
        if ($this->total_recipients === 0) return 0;
        return (int) round(($this->sent_count + $this->failed_count) / $this->total_recipients * 100);
    }
}
