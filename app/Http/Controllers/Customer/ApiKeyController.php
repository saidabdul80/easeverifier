<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $apiKeys = $user->apiKeys()->with('branch')->orderBy('created_at', 'desc')->get()->map(fn($key) => [
            'id' => $key->id,
            'name' => $key->name,
            'key' => $key->key,
            'branch' => $key->branch ? [
                'id' => $key->branch->id,
                'name' => $key->branch->name,
            ] : null,
            'environment' => $key->environment,
            'is_active' => $key->is_active,
            'rate_limit' => $key->rate_limit,
            'last_used_at' => $key->last_used_at?->diffForHumans(),
            'created_at' => $key->created_at->format('M d, Y'),
        ]);

        return Inertia::render('Customer/Api/Index', [
            'apiKeys' => $apiKeys,
            'customer' => $user->customer,
            'branches' => $user->branches()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'environment' => 'required|in:live,test',
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $user = $request->user();
        $branchId = $validated['branch_id'] ?? null;

        $existingKeys = $user->apiKeys()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $branchId, fn ($query) => $query->whereNull('branch_id'))
            ->count();

        if ($existingKeys >= 5) {
            return back()->withErrors(['limit' => 'You can only have up to 5 API keys per account or branch.']);
        }

        $apiKey = ApiKey::generate(
            $user->id,
            $validated['name'],
            $validated['environment'],
            $branchId,
        );

        // Get the bearer token before clearing the plain secret
        $bearerToken = $apiKey->getBearerToken();

        return back()->with([
            'success' => 'API key created successfully. Copy your credentials now - the secret will not be shown again.',
            'newKey' => [
                'key' => $apiKey->key,
                'secret' => $apiKey->plain_secret,
                'bearer_token' => $bearerToken,
            ],
        ]);
    }

    public function regenerate(Request $request, ApiKey $apiKey)
    {
        // Ensure the key belongs to the user
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403);
        }

        $newSecret = $apiKey->regenerateSecret();
        $bearerToken = base64_encode($apiKey->key . ':' . $newSecret);

        return back()->with([
            'success' => 'API secret regenerated. Copy your new credentials now.',
            'newKey' => [
                'key' => $apiKey->key,
                'secret' => $newSecret,
                'bearer_token' => $bearerToken,
            ],
        ]);
    }

    public function destroy(Request $request, ApiKey $apiKey)
    {
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403);
        }

        $apiKey->delete();

        return back()->with('success', 'API key deleted successfully.');
    }

    public function toggle(Request $request, ApiKey $apiKey)
    {
        if ($apiKey->user_id !== $request->user()->id) {
            abort(403);
        }

        $apiKey->update(['is_active' => !$apiKey->is_active]);

        return back()->with('success', $apiKey->is_active ? 'API key activated.' : 'API key deactivated.');
    }

    public function updateWebhook(Request $request)
    {
        $validated = $request->validate([
            'webhook_url' => 'nullable|url|max:255',
        ]);

        $customer = $request->user()->customer;

        if (!$customer) {
            return back()->withErrors(['error' => 'Customer profile not found.']);
        }

        $customer->update(['webhook_url' => $validated['webhook_url']]);

        return back()->with('success', 'Webhook URL updated successfully.');
    }

    public function documentation()
    {
        return Inertia::render('Customer/Api/Documentation');
    }
}
