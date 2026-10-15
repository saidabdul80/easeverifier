<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerKycSubmission extends Model
{
    protected $fillable = ['customer_id', 'questions', 'answers', 'documents', 'submitted_at', 'reviewed_by', 'reviewed_at', 'rejection_reason'];

    protected $hidden = ['questions', 'answers', 'documents'];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'answers' => 'encrypted:array',
            'documents' => 'encrypted:array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function status(): string
    {
        return $this->reviewed_at === null ? 'pending' : ($this->rejection_reason !== null ? 'rejected' : 'approved');
    }

    public function downloadDocument(string $key): StreamedResponse
    {
        $document = $this->documents[$key] ?? null;
        abort_unless($document && Storage::disk('local')->exists($document['path']), 404);

        return Storage::disk('local')->download($document['path'], basename($document['name']), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reviewPayload(bool $admin = false): array
    {
        return [
            'id' => $this->id,
            'questions' => $this->questions,
            'answers' => $this->answers,
            'status' => $this->status(),
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'reviewer' => $this->reviewer?->name,
            'rejection_reason' => $this->rejection_reason,
            'documents' => collect($this->documents)->map(function (array $document, string $key) use ($admin) {
                return [
                    'name' => $document['name'],
                    'label' => $document['label'],
                    'url' => route($admin ? 'admin.kyc.document' : 'customer.kyc.document', [$this, $key], false),
                ];
            })->all(),
        ];
    }
}
