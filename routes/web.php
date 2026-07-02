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
use App\Http\Controllers\Admin\CampaignEmailController as AdminCampaignEmailController;
use App\Http\Controllers\Admin\ResultPinController as AdminResultPinController;
use App\Http\Controllers\Auth\EmailOtpVerificationController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\PublicResultPinController;
use App\Http\Controllers\PublicPaygoVerificationController;

use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\VerificationController as CustomerVerificationController;
use App\Http\Controllers\Customer\ResultPinController as CustomerResultPinController;
use App\Http\Controllers\Customer\WalletController as CustomerWalletController;
use App\Http\Controllers\Customer\TransactionController as CustomerTransactionController;
use App\Http\Controllers\Customer\ApiKeyController;
use App\Http\Controllers\Customer\BranchController as CustomerBranchController;
use App\Http\Controllers\Customer\PaygoServiceController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\SitemapController;

Route::get('/.well-known/acme-challenge/{token}', function (string $token) {
    $challengePath = public_path(".well-known/acme-challenge/{$token}");

    abort_unless(is_file($challengePath), 404);

    return response()->file($challengePath, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
})->where('token', '[A-Za-z0-9._-]+');

// SEO Routes
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'hottestPosts' => \App\Models\BlogPost::with('author:id,name')
            ->published()
            ->orderByDesc('views')
            ->limit(3)
            ->get(),
        'resultPinProducts' => \App\Models\ResultPinProduct::active()
            ->ordered()
            ->get(['id', 'name', 'board', 'price']),
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

Route::get('/result-pins', [PublicResultPinController::class, 'index'])->name('public.result-pins.index');
Route::get('/result-pins/kit/{referralCode}/{email?}', [PublicResultPinController::class, 'kit'])->name('public.result-pins.kit');
Route::post('/result-pins/purchase', [PublicResultPinController::class, 'purchase'])->name('public.result-pins.purchase');
Route::get('/result-pins/callback', [PublicResultPinController::class, 'callback'])->name('public.result-pins.callback');
Route::get('/result-pins/login', [PublicResultPinController::class, 'login'])->name('public.result-pins.login');
Route::post('/result-pins/login', [PublicResultPinController::class, 'loginWithEmail'])->name('public.result-pins.login.store');
Route::get('/result-pins/my-pins', [PublicResultPinController::class, 'orders'])->name('public.result-pins.orders');
Route::get('/result-pins/orders/{order:reference}', [PublicResultPinController::class, 'show'])->name('public.result-pins.show');

Route::match(['get', 'post'], '/paygo/{publicSlug}/initiate/{nin?}', [PublicPaygoVerificationController::class, 'initiate'])->name('paygo.initiate');
Route::get('/paygo/callback', [PublicPaygoVerificationController::class, 'callback'])->name('paygo.callback');

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

Route::middleware(['auth', 'throttle:6,1'])->group(function () {
    Route::post('email/verify-otp', [EmailOtpVerificationController::class, 'store'])->name('verification.otp.verify');
});

// Admin Routes
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Customers
    Route::resource('customers', AdminCustomerController::class);
    Route::post('customers/{customer}/pricing', [AdminCustomerController::class, 'updatePricing'])->name('customers.pricing');
    Route::post('customers/{customer}/result-pin-pricing', [AdminCustomerController::class, 'updateResultPinPricing'])->name('customers.result-pin-pricing');
    Route::post('customers/{customer}/result-fetch-access', [AdminCustomerController::class, 'updateResultFetchAccess'])->name('customers.result-fetch-access');

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
    Route::get('transactions/export', [AdminTransactionController::class, 'export'])->name('transactions.export');
    Route::get('transactions/{transaction}', [AdminTransactionController::class, 'show'])->name('transactions.show');

    // Verifications
    Route::get('verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
    Route::get('verifications/export', [AdminVerificationController::class, 'export'])->name('verifications.export');
    Route::get('verifications/{verification}', [AdminVerificationController::class, 'show'])->name('verifications.show');

    // Blog
    Route::resource('blog', AdminBlogController::class)->except(['show']);

    // Campaign Emails
    Route::get('campaign-emails', [AdminCampaignEmailController::class, 'index'])->name('campaign-emails.index');
    Route::get('campaign-emails/create', [AdminCampaignEmailController::class, 'create'])->name('campaign-emails.create');
    Route::post('campaign-emails', [AdminCampaignEmailController::class, 'store'])->name('campaign-emails.store');

    // Result PINs
    Route::get('result-pins', [AdminResultPinController::class, 'index'])->name('result-pins.index');
    Route::post('result-pins/sync', [AdminResultPinController::class, 'sync'])->name('result-pins.sync');
    Route::post('result-pins/purchase', [AdminResultPinController::class, 'purchase'])->name('result-pins.purchase');
    Route::get('result-pins/callback', [AdminResultPinController::class, 'callback'])->name('result-pins.callback');
    Route::put('result-pins/products/{product}', [AdminResultPinController::class, 'updateProductPrice'])->name('result-pins.products.update');
});

// Customer Routes
Route::middleware(['auth', 'verified', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/', [CustomerDashboardController::class, 'index'])->name('dashboard');

    // Verification
    Route::get('verify', [CustomerVerificationController::class, 'index'])->name('verification.index');
    Route::get('verify/{service}/form-fields', [CustomerVerificationController::class, 'resultFormFields'])->name('verification.result-form');
    Route::get('verify/{service}/schools', [CustomerVerificationController::class, 'resultSchools'])->name('verification.result-schools');
    Route::get('verify/{service}', [CustomerVerificationController::class, 'show'])->name('verification.show');
    Route::post('verify/{service}', [CustomerVerificationController::class, 'verify'])->name('verification.verify');
    Route::get('history', [CustomerVerificationController::class, 'history'])->name('verification.history');
    Route::get('history/export', [CustomerVerificationController::class, 'exportHistory'])->name('verification.export');
    Route::get('history/{verification}', [CustomerVerificationController::class, 'showResult'])->name('verification.result');
    Route::get('verification/{verification}', [CustomerVerificationController::class, 'showResult'])->name('verification.show-result');
    Route::get('verification/{verification}/download', [CustomerVerificationController::class, 'download'])->name('verification.download');

    // Result PINs
    Route::get('result-pins', [CustomerResultPinController::class, 'index'])->name('result-pins.index');
    Route::post('result-pins/purchase', [CustomerResultPinController::class, 'purchase'])->name('result-pins.purchase');
    Route::get('result-pins/{order}', [CustomerResultPinController::class, 'show'])->name('result-pins.show');

    // Wallet
    Route::get('wallet', [CustomerWalletController::class, 'index'])->name('wallet.index');
    Route::get('wallet/export', [CustomerWalletController::class, 'export'])->name('wallet.export');
    Route::get('wallet/fund', [CustomerWalletController::class, 'fund'])->name('wallet.fund');

    // Transactions
    Route::get('transactions', [CustomerTransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/export', [CustomerTransactionController::class, 'export'])->name('transactions.export');
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

    // Pay-on-the-go verification services
    Route::get('paygo-services', [PaygoServiceController::class, 'index'])->name('paygo.index');
    Route::get('paygo-analytics', fn () => redirect()->route('customer.paygo.index'))->name('paygo.analytics');
    Route::get('paygo-transactions', fn () => redirect()->route('customer.paygo.index'))->name('paygo.transactions');
    Route::post('paygo-services', [PaygoServiceController::class, 'store'])->name('paygo.store');
    Route::get('paygo-services/{paygoService}/transactions', [PaygoServiceController::class, 'serviceTransactions'])->name('paygo.service-transactions');
    Route::put('paygo-services/{paygoService}', [PaygoServiceController::class, 'update'])->name('paygo.update');
    Route::post('paygo-services/{paygoService}/toggle', [PaygoServiceController::class, 'toggle'])->name('paygo.toggle');
    Route::delete('paygo-services/{paygoService}', [PaygoServiceController::class, 'destroy'])->name('paygo.destroy');
    Route::post('paygo-wallet/withdraw', [PaygoServiceController::class, 'withdraw'])->name('paygo.withdraw');

    // Branches
    Route::get('branches', [CustomerBranchController::class, 'index'])->name('branches.index');
    Route::post('branches', [CustomerBranchController::class, 'store'])->name('branches.store');
    Route::put('branches/{branch}', [CustomerBranchController::class, 'update'])->name('branches.update');
    Route::post('branches/transfer', [CustomerBranchController::class, 'transfer'])->name('branches.transfer');
    
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
