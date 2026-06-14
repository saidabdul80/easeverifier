<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    protected $fillable = [
        'admin_user_id',
        'email_template_id',
        'title',
        'recipient_scope',
        'selected_customer_ids',
        'subject',
        'heading',
        'body',
        'cta_label',
        'cta_url',
        'total_recipients',
        'sent_count',
        'failed_count',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_customer_ids' => 'array',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }
}
