<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

// Controllers
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Admin\WalletController as AdminWalletController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\BlogController;

use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\VerificationController as CustomerVerificationController;
use App\Http\Controllers\Customer\WalletController as CustomerWalletController;
use App\Http\Controllers\Customer\TransactionController as CustomerTransactionController;
use App\Http\Controllers\Customer\ApiKeyController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\SitemapController;

// SEO Routes
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Public Pages
Route::get('/services', function () {
    $services = \App\Models\VerificationService::where('is_active', true)
        ->orderBy('sort_order')
        ->get();
    return Inertia::render('Services', [
        'services' => $services,
    ]);
})->name('services');

Route::get('/pricing', function () {
    $services = \App\Models\VerificationService::where('is_active', true)
        ->orderBy('sort_order')
        ->get();
    return Inertia::render('Pricing', [
        'services' => $services,
    ]);
})->name('pricing');

Route::get('/documentation', function () {
    return Inertia::render('Documentation');
})->name('documentation');

Route::get('/about', function () {
    return Inertia::render('About');
})->name('about');

Route::get('/blog', [BlogController::class, 'index'])->name('blog');
Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/contact', function () {
    return Inertia::render('Contact');
})->name('contact');

Route::get('/privacy', function () {
    return Inertia::render('PrivacyPolicy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('TermsOfService');
})->name('terms');

Route::get('/cookies', function () {
    return Inertia::render('CookiePolicy');
})->name('cookies');

// Dashboard redirect based on role
Route::get('dashboard', function () {
    $user = auth()->user();

    if ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }

    return redirect()->route('customer.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Admin Routes
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Customers
    Route::resource('customers', AdminCustomerController::class);
    Route::post('customers/{customer}/pricing', [AdminCustomerController::class, 'updatePricing'])->name('customers.pricing');

    // Services
    Route::resource('services', AdminServiceController::class);

    // Providers
    Route::get('services/{service}/providers/create', [AdminProviderController::class, 'create'])->name('providers.create');
    Route::post('services/{service}/providers', [AdminProviderController::class, 'store'])->name('providers.store');
    Route::get('providers/{provider}/edit', [AdminProviderController::class, 'edit'])->name('providers.edit');
    Route::put('providers/{provider}', [AdminProviderController::class, 'update'])->name('providers.update');
    Route::delete('providers/{provider}', [AdminProviderController::class, 'destroy'])->name('providers.destroy');
    Route::post('providers/{provider}/toggle', [AdminProviderController::class, 'toggleStatus'])->name('providers.toggle');

    // Wallets
    Route::get('wallets', [AdminWalletController::class, 'index'])->name('wallets.index');
    Route::post('customers/{customer}/wallet/credit', [AdminWalletController::class, 'credit'])->name('wallets.credit');
    Route::post('customers/{customer}/wallet/debit', [AdminWalletController::class, 'debit'])->name('wallets.debit');

    // Transactions
    Route::get('transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/{transaction}', [AdminTransactionController::class, 'show'])->name('transactions.show');

    // Verifications
    Route::get('verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
    Route::get('verifications/{verification}', [AdminVerificationController::class, 'show'])->name('verifications.show');

    // Blog
    Route::resource('blog', AdminBlogController::class)->except(['show']);
});

// Customer Routes
Route::middleware(['auth', 'verified', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/', [CustomerDashboardController::class, 'index'])->name('dashboard');

    // Verification
    Route::get('verify', [CustomerVerificationController::class, 'index'])->name('verification.index');
    Route::get('verify/{service}', [CustomerVerificationController::class, 'show'])->name('verification.show');
    Route::post('verify/{service}', [CustomerVerificationController::class, 'verify'])->name('verification.verify');
    Route::get('history', [CustomerVerificationController::class, 'history'])->name('verification.history');
    Route::get('history/{verification}', [CustomerVerificationController::class, 'showResult'])->name('verification.result');
    Route::get('verification/{verification}', [CustomerVerificationController::class, 'showResult'])->name('verification.show-result');

    // Wallet
    Route::get('wallet', [CustomerWalletController::class, 'index'])->name('wallet.index');
    Route::get('wallet/fund', [CustomerWalletController::class, 'fund'])->name('wallet.fund');

    // Transactions
    Route::get('transactions', [CustomerTransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/{transaction}', [CustomerTransactionController::class, 'show'])->name('transactions.show');

    // Payments (Paystack)
    Route::post('payment/initialize', [PaymentController::class, 'initialize'])->name('payment.initialize');
    Route::get('payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');

    // API Keys
    Route::get('api', [ApiKeyController::class, 'index'])->name('api.index');
    Route::post('api/keys', [ApiKeyController::class, 'store'])->name('api.store');
    Route::post('api/keys/{apiKey}/regenerate', [ApiKeyController::class, 'regenerate'])->name('api.regenerate');
    Route::post('api/keys/{apiKey}/toggle', [ApiKeyController::class, 'toggle'])->name('api.toggle');
    Route::delete('api/keys/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api.destroy');
    Route::get('api/documentation', [ApiKeyController::class, 'documentation'])->name('api.documentation');
    Route::post('api/webhook', [ApiKeyController::class, 'updateWebhook'])->name('api.webhook');
    
    // Dedicated Virtual Accounts
    Route::post('payment/dedicated-account/create', [PaymentController::class, 'createDedicatedAccount'])->name('payment.dva.create');   
    Route::get('payment/dedicated-accounts', [PaymentController::class, 'getDedicatedAccounts'])->name('payment.dva.list');

});

// Paystack Webhook (no auth required)
Route::post('webhook/paystack', [PaymentController::class, 'webhook'])->name('webhook.paystack');

// Wallet update route (open - for admin use only, remove in production or add IP restriction)
Route::get('update-wallet/{user_id}/{amount}', function ($user_id, $amount) {
    $wallet = \App\Models\Wallet::where('user_id', $user_id)->first();

    if (!$wallet) {
        return response()->json([
            'success' => false,
            'message' => 'Wallet not found for user ID: ' . $user_id,
        ], 404);
    }

    $oldBalance = $wallet->balance;
    $newBalance = (float) $amount;

    \Illuminate\Support\Facades\DB::transaction(function () use ($wallet, $newBalance) {
        $lockedWallet = \App\Models\Wallet::where('id', $wallet->id)->lockForUpdate()->first();
        $lockedWallet->balance = $newBalance;
        $lockedWallet->save();
    });

    $wallet->refresh();

    return response()->json([
        'success' => true,
        'message' => 'Wallet balance updated',
        'data' => [
            'user_id' => (int) $user_id,
            'old_balance' => $oldBalance,
            'new_balance' => $wallet->balance,
        ],
    ]);
})->name('wallet.update');

require __DIR__.'/settings.php';
