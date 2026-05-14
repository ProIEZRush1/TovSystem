<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCampaignMessage extends Model
{
    protected $table = 'whatsapp_campaign_messages';

    protected $fillable = [
        'whatsapp_campaign_id',
        'contact_id',
        'phone',
        'contact_name',
        'status',
        'wamid',
        'error_message',
        'error_code',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'whatsapp_campaign_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
