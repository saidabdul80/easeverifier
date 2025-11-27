<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VerificationController extends Controller
{
    public function index(Request $request)
    {
        $verifications = VerificationRequest::with(['user', 'verificationService', 'serviceProvider'])
            ->when($request->search, function ($query, $search) {
                $query->where('reference', 'like', "%{$search}%")
                    ->orWhere('search_parameter', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($q) => 
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%")
                    );
            })
            ->when($request->service, fn($q, $service) => $q->where('verification_service_id', $service))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->source, fn($q, $source) => $q->where('source', $source))
            ->when($request->date_from, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => VerificationRequest::count(),
            'completed' => VerificationRequest::where('status', 'completed')->count(),
            'failed' => VerificationRequest::where('status', 'failed')->count(),
            'pending' => VerificationRequest::where('status', 'pending')->count(),
        ];

        return Inertia::render('Admin/Verifications/Index', [
            'verifications' => $verifications,
            'stats' => $stats,
            'filters' => $request->only(['search', 'service', 'status', 'source', 'date_from', 'date_to']),
        ]);
    }

    public function show(VerificationRequest $verification)
    {
        $verification->load(['user', 'verificationService', 'serviceProvider', 'transaction']);

        return Inertia::render('Admin/Verifications/Show', [
            'verification' => $verification,
        ]);
    }
}

