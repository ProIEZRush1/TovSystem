<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'whatsapp_account_id',
        'contact_id',
        'remote_phone',
        'contact_name',
        'unread_count',
        'assigned_to',
        'last_message_at',
        'last_message_preview',
        'last_message_direction',
        'is_archived',
        'window_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'last_message_at' => 'datetime',
            'window_expires_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id');
    }

    public function isWindowOpen(): bool
    {
        return $this->window_expires_at && $this->window_expires_at->isFuture();
    }
}
