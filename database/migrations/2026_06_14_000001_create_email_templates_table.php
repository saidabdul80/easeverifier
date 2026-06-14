<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category')->default('general');
            $table->string('subject');
            $table->string('heading')->nullable();
            $table->text('body');
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $timestamp = now();

        DB::table('email_templates')->insert([
            [
                'key' => 'welcome_onboard',
                'name' => 'Welcome Onboard',
                'category' => 'onboarding',
                'subject' => 'Welcome to EaseVerifier, {{first_name}}',
                'heading' => 'Your verification workspace is ready',
                'body' => "Hi {{customer_name}},\n\nWelcome to EaseVerifier. Your account is now ready for fast identity and business verification workflows.\n\nSign in to your dashboard to review services, top up your wallet, and complete your first request in minutes.\n\nIf you need help getting started, reply to this email and our team will assist you.",
                'cta_label' => 'Open Dashboard',
                'cta_url' => '{{dashboard_url}}',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'first_verification_guide',
                'name' => 'First Verification Guide',
                'category' => 'onboarding',
                'subject' => 'Complete your first verification with EaseVerifier',
                'heading' => 'Start with a simple verification run',
                'body' => "Hello {{customer_name}},\n\nYour account is set up and the fastest next step is to run your first verification.\n\nChoose a service, enter the required identifier, and submit the request from your dashboard. This gives your team a live reference point for how the platform behaves in production.\n\nWe recommend starting with the service most relevant to {{company_name}}.",
                'cta_label' => 'View Services',
                'cta_url' => '{{services_url}}',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'api_setup_kickoff',
                'name' => 'API Setup Kickoff',
                'category' => 'onboarding',
                'subject' => 'Connect your product to the EaseVerifier API',
                'heading' => 'API access is available for your team',
                'body' => "Hi {{customer_name}},\n\nIf your team wants to automate verification checks, you can begin API setup from your EaseVerifier account.\n\nReview the documentation, generate your credentials, and test with a controlled workflow before moving traffic into production.\n\nThis is the fastest path to embedding verification directly into your product or internal operations.",
                'cta_label' => 'Read API Docs',
                'cta_url' => '{{api_docs_url}}',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'fund_wallet_reminder',
                'name' => 'Fund Wallet Reminder',
                'category' => 'activation',
                'subject' => 'Top up your EaseVerifier wallet to begin',
                'heading' => 'Add funds and start processing checks',
                'body' => "Hello {{customer_name}},\n\nYour current wallet balance is {{wallet_balance}}.\n\nTo avoid interruptions when you start verifying records, fund your wallet before your first live usage session.\n\nKeeping a working balance helps your team move straight from setup into execution without delays.",
                'cta_label' => 'Fund Wallet',
                'cta_url' => '{{dashboard_url}}',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'account_activation_nudge',
                'name' => 'Account Activation Nudge',
                'category' => 'activation',
                'subject' => 'Your EaseVerifier account is waiting for activation',
                'heading' => 'Pick up where you left off',
                'body' => "Hi {{customer_name}},\n\nWe noticed your account is still in the early setup stage.\n\nA quick dashboard review is enough to confirm your services, pricing, and workflow readiness. Once that is done, your team can begin using the platform immediately.\n\nIf anything is blocking you, contact us and we will help resolve it.",
                'cta_label' => 'Resume Setup',
                'cta_url' => '{{dashboard_url}}',
                'is_active' => true,
                'sort_order' => 5,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'product_update_announcement',
                'name' => 'Product Update Announcement',
                'category' => 'announcement',
                'subject' => 'New updates are now live on EaseVerifier',
                'heading' => 'Platform improvements for your team',
                'body' => "Hello {{customer_name}},\n\nWe have shipped new updates across EaseVerifier to improve reliability, workflow speed, and day-to-day usage for customer teams.\n\nLog in to review the latest changes and assess how they fit into your current verification process.\n\nWe will continue releasing improvements that reduce turnaround time and operational friction.",
                'cta_label' => 'Review Updates',
                'cta_url' => '{{dashboard_url}}',
                'is_active' => true,
                'sort_order' => 6,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'usage_reengagement',
                'name' => 'Usage Re-engagement',
                'category' => 're_engagement',
                'subject' => 'Bring your verification workflow back into motion',
                'heading' => 'EaseVerifier is ready when your team is',
                'body' => "Hi {{customer_name}},\n\nIf usage has slowed down recently, this is a good time to reconnect your team with EaseVerifier.\n\nReturn to the dashboard, review the available services, and restart the workflows that matter most to {{company_name}}.\n\nA short reset usually helps teams recover speed quickly.",
                'cta_label' => 'Return to Dashboard',
                'cta_url' => '{{dashboard_url}}',
                'is_active' => true,
                'sort_order' => 7,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'wallet_topup_drive',
                'name' => 'Wallet Top-up Drive',
                'category' => 'retention',
                'subject' => 'Keep your verification balance ready',
                'heading' => 'Maintain a healthy wallet balance',
                'body' => "Hello {{customer_name}},\n\nYour current wallet balance is {{wallet_balance}}.\n\nIf your team expects more verification traffic soon, topping up now helps you avoid interruptions during active operations.\n\nA ready balance keeps turnaround predictable and reduces stop-start work inside your process.",
                'cta_label' => 'Top Up Balance',
                'cta_url' => '{{dashboard_url}}',
                'is_active' => true,
                'sort_order' => 8,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'support_check_in',
                'name' => 'Support Check-in',
                'category' => 'support',
                'subject' => 'Do you need any help using EaseVerifier?',
                'heading' => 'We want to remove any blockers for your team',
                'body' => "Hi {{customer_name}},\n\nWe wanted to check in and ask whether you have faced any issues while using EaseVerifier.\n\nIf anything in the platform is unclear, blocked, or slower than expected for {{company_name}}, reply to this email and let us know. Our team can help with setup questions, workflow guidance, wallet issues, API usage, or day-to-day support.\n\nWe would like to make sure your team gets full value from the system.",
                'cta_label' => 'Contact Support',
                'cta_url' => 'mailto:{{support_email}}',
                'is_active' => true,
                'sort_order' => 9,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'win_back_campaign',
                'name' => 'Win-back Campaign',
                'category' => 're_engagement',
                'subject' => 'We would like to support your next verification cycle',
                'heading' => 'Revisit EaseVerifier for your next run',
                'body' => "Hello {{customer_name}},\n\nIf your team has not used EaseVerifier recently, this is a good opportunity to come back and restart with a clean review of your workflow.\n\nOur platform remains available to help you handle customer checks, business verification, and operational validation tasks with less manual effort.\n\nWe would be glad to support your next cycle.",
                'cta_label' => 'Sign In',
                'cta_url' => '{{dashboard_url}}',
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
