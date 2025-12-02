<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first();
        $authorId = $admin?->id ?? 1;

        $posts = [
            [
                'title' => 'How to Know Your NIN Number - Complete Guide',
                'slug' => 'how-to-know-your-nin-number',
                'excerpt' => 'Lost or forgotten your NIN? Learn all the easy ways to retrieve your National Identification Number (NIN) in Nigeria.',
                'category' => 'Education',
                'meta_title' => 'How to Know Your NIN Number in Nigeria | 5 Easy Methods',
                'meta_description' => 'Discover 5 easy ways to find your NIN number in Nigeria. Check via USSD, SMS, NIMC app, or online portal. Step-by-step guide with direct links.',
                'content' => $this->getNinKnowContent(),
            ],
            [
                'title' => 'NIMC Portal Login - Access Your NIN Dashboard',
                'slug' => 'nimc-portal-login-guide',
                'excerpt' => 'Step-by-step guide to log in to the NIMC portal and access your NIN details, update information, and download your NIN slip.',
                'category' => 'Education',
                'meta_title' => 'NIMC Portal Login Guide - Access Your NIN Online',
                'meta_description' => 'Learn how to log in to the NIMC self-service portal. Access your NIN dashboard, download NIN slip, and update your details online.',
                'content' => $this->getNimcPortalContent(),
            ],
            [
                'title' => 'How to Check Your NIN on Airtel - USSD Code & Steps',
                'slug' => 'how-to-check-nin-on-airtel',
                'excerpt' => 'Quick guide to retrieve your NIN using your Airtel line. Simple USSD codes and SMS methods explained.',
                'category' => 'Education',
                'meta_title' => 'Check NIN on Airtel - USSD Code & SMS Method',
                'meta_description' => 'Check your NIN on Airtel using USSD code *346# or SMS. Step-by-step instructions to retrieve your National Identification Number.',
                'content' => $this->getAirtelNinContent(),
            ],
            [
                'title' => 'How to Get Your NIN Number on MTN - Easy Steps',
                'slug' => 'how-to-get-nin-on-mtn',
                'excerpt' => 'Retrieve your NIN instantly using your MTN line. Learn the USSD codes and SMS methods that work.',
                'category' => 'Education',
                'meta_title' => 'Get Your NIN on MTN - USSD Code & SMS Guide',
                'meta_description' => 'Retrieve your NIN on MTN using USSD code *346# or SMS to 346. Complete guide with step-by-step instructions.',
                'content' => $this->getMtnNinContent(),
            ],
            [
                'title' => 'NIMC Self Service Portal - Complete User Guide',
                'slug' => 'nimc-self-service-portal-guide',
                'excerpt' => 'Everything you need to know about the NIMC self-service portal. Register, check NIN status, download slip, and more.',
                'category' => 'Education',
                'meta_title' => 'NIMC Self Service Portal - Registration & Features Guide',
                'meta_description' => 'Complete guide to NIMC self-service portal. Learn how to register, check NIN status, download NIN slip, and update your information.',
                'content' => $this->getNimcSelfServiceContent(),
            ],
            [
                'title' => 'How to Check My NIN Number - All Methods Explained',
                'slug' => 'how-to-check-my-nin-number',
                'excerpt' => 'Multiple ways to check your NIN number in Nigeria. USSD, SMS, mobile app, and online methods all in one guide.',
                'category' => 'Education',
                'meta_title' => 'How to Check My NIN Number - 6 Easy Methods',
                'meta_description' => 'Check your NIN number using USSD (*346#), SMS, NIMC app, or online portal. All methods explained with step-by-step instructions.',
                'content' => $this->getCheckNinContent(),
            ],
        ];

        foreach ($posts as $index => $post) {
            BlogPost::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'author_id' => $authorId,
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'category' => $post['category'],
                    'tags' => ['NIN', 'NIMC', 'Identity Verification', 'Nigeria'],
                    'status' => 'published',
                    'published_at' => now()->subDays(count($posts) - $index),
                    'meta_title' => $post['meta_title'],
                    'meta_description' => $post['meta_description'],
                    'views' => rand(100, 5000),
                ]
            );
        }
    }

    private function getNinKnowContent(): string
    {
        return <<<'HTML'
        <h2>What is NIN?</h2>
        <p>The National Identification Number (NIN) is an 11-digit unique identifier assigned to every Nigerian citizen and legal resident. It is issued by the National Identity Management Commission (NIMC) and is essential for various services including SIM registration, banking, and government services.</p>

        <h2>5 Ways to Know Your NIN Number</h2>

        <h3>1. USSD Code (All Networks)</h3>
        <p>The easiest way to check your NIN is by dialing:</p>
        <p><strong>*346#</strong></p>
        <p>This works on all Nigerian networks (MTN, Airtel, Glo, 9mobile). You'll receive your NIN via SMS. <em>Note: This costs ₦20.</em></p>

        <h3>2. SMS Method</h3>
        <p>Send <strong>NIN</strong> to <strong>346</strong> from any registered phone number. Your NIN will be sent back via SMS.</p>

        <h3>3. NIMC Mobile App</h3>
        <p>Download the NIMC Mobile ID app:</p>
        <ul>
        <li><a href="https://play.google.com/store/apps/details?id=com.nimc.nin" target="_blank">Download for Android</a></li>
        <li><a href="https://apps.apple.com/ng/app/nimc-mobile-id/id1442691752" target="_blank">Download for iOS</a></li>
        </ul>
        <p>Log in with your registered phone number to view your NIN.</p>

        <h3>4. NIMC Self-Service Portal</h3>
        <p>Visit the official NIMC portal at <a href="https://nimc.gov.ng" target="_blank">nimc.gov.ng</a> to access your NIN online.</p>

        <h3>5. Visit NIMC Office</h3>
        <p>If all else fails, visit any NIMC enrollment center with a valid ID to retrieve your NIN.</p>

        <h2>Need to Verify NIN for Your Business?</h2>
        <p>If you're a business owner needing to verify customer NINs, <a href="/services">EaseVerifier's NIN Verification API</a> provides instant, reliable verification at affordable rates.</p>
        HTML;
            }

            private function getNimcPortalContent(): string
            {
                return <<<'HTML'
        <h2>Accessing the NIMC Portal</h2>
        <p>The National Identity Management Commission (NIMC) provides an online portal where you can access your NIN details, download your NIN slip, and manage your information.</p>

        <h3>Official NIMC Portal Links</h3>
        <ul>
        <li><strong>Main Website:</strong> <a href="https://nimc.gov.ng" target="_blank">nimc.gov.ng</a></li>
        <li><strong>NIN Slip Download:</strong> <a href="https://nimc.gov.ng/nin-slip" target="_blank">nimc.gov.ng/nin-slip</a></li>
        <li><strong>Enrollment Centers:</strong> <a href="https://nimc.gov.ng/nin-enrollment-centres" target="_blank">Find nearest center</a></li>
        </ul>

        <h2>How to Log In to NIMC Portal</h2>
        <ol>
        <li>Visit <a href="https://nimc.gov.ng" target="_blank">nimc.gov.ng</a></li>
        <li>Click on "NIN Services" or "Self Service"</li>
        <li>Enter your NIN or registered phone number</li>
        <li>Complete the verification process</li>
        <li>Access your dashboard</li>
        </ol>

        <h2>What You Can Do on NIMC Portal</h2>
        <ul>
        <li>✅ Check your NIN enrollment status</li>
        <li>✅ Download your NIN slip (Standard or Improved)</li>
        <li>✅ View your registered details</li>
        <li>✅ Apply for data modification</li>
        <li>✅ Link your NIN to other services</li>
        </ul>

        <h2>Common Issues & Solutions</h2>
        <p><strong>Can't log in?</strong> Ensure you're using the phone number registered during NIN enrollment.</p>
        <p><strong>Portal not loading?</strong> The portal may experience high traffic. Try during off-peak hours or use the USSD method (*346#).</p>

        <h2>Business NIN Verification</h2>
        <p>For businesses needing bulk NIN verification, <a href="/services">EaseVerifier API</a> offers a faster, more reliable solution with 99.9% uptime.</p>
        HTML;
            }

            private function getAirtelNinContent(): string
            {
                return <<<'HTML'
        <h2>Check Your NIN on Airtel</h2>
        <p>If you registered your NIN with your Airtel number, you can easily retrieve it using these methods:</p>

        <h3>Method 1: USSD Code</h3>
        <ol>
        <li>Open your phone's dialer</li>
        <li>Dial <strong>*346#</strong></li>
        <li>Select "NIN Retrieval" from the menu</li>
        <li>Your NIN will be displayed and sent via SMS</li>
        </ol>
        <p><em>Cost: ₦20 per request</em></p>

        <h3>Method 2: SMS</h3>
        <ol>
        <li>Open your messaging app</li>
        <li>Type <strong>NIN</strong></li>
        <li>Send to <strong>346</strong></li>
        <li>Wait for the reply SMS with your NIN</li>
        </ol>

        <h3>Method 3: Airtel Self Service</h3>
        <ol>
        <li>Dial <strong>*121#</strong></li>
        <li>Navigate to NIN services</li>
        <li>Follow the prompts to retrieve your NIN</li>
        </ol>

        <h2>Requirements</h2>
        <ul>
        <li>Your Airtel SIM must be linked to your NIN</li>
        <li>You need at least ₦20 airtime balance</li>
        <li>Use the same number registered with NIMC</li>
        </ul>

        <h2>NIN Not Found?</h2>
        <p>If you get an error, your Airtel number may not be linked to your NIN. Visit a NIMC center or use the <a href="https://nimc.gov.ng" target="_blank">NIMC portal</a> to update your records.</p>

        <h2>For Businesses</h2>
        <p>Need to verify customer NINs at scale? <a href="/pricing">Check our API pricing</a> for affordable bulk verification.</p>
        HTML;
            }

            private function getMtnNinContent(): string
            {
                return <<<'HTML'
        <h2>Retrieve Your NIN on MTN</h2>
        <p>MTN subscribers can easily check their NIN using these simple methods:</p>

        <h3>Method 1: USSD Code (*346#)</h3>
        <ol>
        <li>Insert your MTN SIM card</li>
        <li>Dial <strong>*346#</strong></li>
        <li>A menu will appear - select "NIN Retrieval"</li>
        <li>Your 11-digit NIN will be displayed</li>
        </ol>
        <p><em>Service charge: ₦20</em></p>

        <h3>Method 2: Send SMS to 346</h3>
        <ol>
        <li>Open your SMS/Messages app</li>
        <li>Create a new message</li>
        <li>Type: <strong>NIN</strong></li>
        <li>Send to: <strong>346</strong></li>
        <li>You'll receive your NIN via SMS within seconds</li>
        </ol>

        <h3>Method 3: MyMTN App</h3>
        <ol>
        <li>Download MyMTN App from Play Store or App Store</li>
        <li>Log in with your MTN number</li>
        <li>Navigate to "More Services"</li>
        <li>Select "NIN Services"</li>
        <li>View your linked NIN</li>
        </ol>

        <h2>Troubleshooting</h2>
        <p><strong>"NIN not found" error?</strong></p>
        <ul>
        <li>Ensure your MTN number is linked to your NIN</li>
        <li>Visit <a href="https://nimc.gov.ng" target="_blank">NIMC portal</a> to check your registered numbers</li>
        <li>Visit any MTN service center for assistance</li>
        </ul>

        <h2>Link Your MTN Number to NIN</h2>
        <p>If your number isn't linked:</p>
        <ol>
        <li>Dial <strong>*785*Your-NIN#</strong> (e.g., *785*12345678901#)</li>
        <li>Follow the prompts to complete linking</li>
        </ol>

        <h2>Business Verification API</h2>
        <p>Businesses can verify NINs instantly using <a href="/documentation">EaseVerifier's API</a>. Get started in minutes!</p>
        HTML;
            }

            private function getNimcSelfServiceContent(): string
            {
                return <<<'HTML'
        <h2>NIMC Self-Service Portal Overview</h2>
        <p>The NIMC Self-Service Portal allows Nigerians to manage their National Identification Number (NIN) online without visiting an enrollment center.</p>

        <h3>Official Portal URL</h3>
        <p><a href="https://nimc.gov.ng" target="_blank" class="btn">Visit NIMC Self-Service Portal →</a></p>

        <h2>Available Services</h2>
        <table>
        <tr><td>✅ NIN Retrieval</td><td>Get your 11-digit NIN</td></tr>
        <tr><td>✅ NIN Slip Download</td><td>Download Standard or Premium slip</td></tr>
        <tr><td>✅ Enrollment Status</td><td>Check if enrollment is complete</td></tr>
        <tr><td>✅ Data Modification</td><td>Update your personal information</td></tr>
        <tr><td>✅ Phone Number Linking</td><td>Link new numbers to your NIN</td></tr>
        </table>

        <h2>How to Register on NIMC Portal</h2>
        <ol>
        <li>Go to <a href="https://nimc.gov.ng" target="_blank">nimc.gov.ng</a></li>
        <li>Click "Create Account" or "Register"</li>
        <li>Enter your NIN and registered phone number</li>
        <li>Verify via OTP sent to your phone</li>
        <li>Create a password</li>
        <li>Login and access all services</li>
        </ol>

        <h2>Download Your NIN Slip</h2>
        <p>Two types of NIN slips are available:</p>
        <ul>
        <li><strong>Standard Slip:</strong> Basic NIN details (Free)</li>
        <li><strong>Premium/Improved Slip:</strong> Photo ID format (₦500-₦1,000)</li>
        </ul>

        <h2>Contact NIMC</h2>
        <ul>
        <li><strong>Website:</strong> <a href="https://nimc.gov.ng" target="_blank">nimc.gov.ng</a></li>
        <li><strong>Email:</strong> info@nimc.gov.ng</li>
        <li><strong>Helpline:</strong> 0700-CALL-NIMC (0700-2255-6462)</li>
        </ul>

        <h2>For Developers & Businesses</h2>
        <p>Need NIN verification for your app or business? <a href="/documentation">Explore our API documentation</a> for seamless integration.</p>
        HTML;
            }

            private function getCheckNinContent(): string
            {
                return <<<'HTML'
        <h2>6 Ways to Check Your NIN Number</h2>
        <p>Your National Identification Number (NIN) is essential for many services in Nigeria. Here are all the ways to retrieve it:</p>

        <h3>1. USSD Code - All Networks</h3>
        <p>The universal method that works on MTN, Airtel, Glo, and 9mobile:</p>
        <p><strong>Dial *346#</strong></p>
        <p>Cost: ₦20 | Works instantly</p>

        <h3>2. SMS Method</h3>
        <p>Send <strong>NIN</strong> as SMS to <strong>346</strong></p>
        <p>Your NIN will be sent back via SMS.</p>

        <h3>3. NIMC Mobile App</h3>
        <p>Download the official app:</p>
        <ul>
        <li><a href="https://play.google.com/store/apps/details?id=com.nimc.nin" target="_blank">Google Play Store (Android)</a></li>
        <li><a href="https://apps.apple.com/ng/app/nimc-mobile-id/id1442691752" target="_blank">Apple App Store (iOS)</a></li>
        </ul>

        <h3>4. NIMC Online Portal</h3>
        <p>Visit <a href="https://nimc.gov.ng" target="_blank">nimc.gov.ng</a> and use the self-service option.</p>

        <h3>5. Network-Specific USSD</h3>
        <ul>
        <li><strong>MTN:</strong> *785# → NIN Services</li>
        <li><strong>Airtel:</strong> *121# → NIN Services</li>
        <li><strong>Glo:</strong> *109# → NIN Services</li>
        <li><strong>9mobile:</strong> *200# → NIN Services</li>
        </ul>

        <h3>6. Visit NIMC Enrollment Center</h3>
        <p>Find the nearest center at <a href="https://nimc.gov.ng/nin-enrollment-centres" target="_blank">NIMC Enrollment Centers</a></p>

        <h2>Quick Comparison</h2>
        <table>
        <tr><th>Method</th><th>Cost</th><th>Speed</th></tr>
        <tr><td>USSD *346#</td><td>₦20</td><td>Instant</td></tr>
        <tr><td>SMS to 346</td><td>₦20</td><td>1-2 mins</td></tr>
        <tr><td>NIMC App</td><td>Free</td><td>Instant</td></tr>
        <tr><td>Online Portal</td><td>Free</td><td>5-10 mins</td></tr>
        <tr><td>NIMC Office</td><td>Free</td><td>30+ mins</td></tr>
        </table>

        <h2>Verify NIN for Your Business</h2>
        <p>If you need to verify customer NINs for KYC compliance, <a href="/register">sign up for EaseVerifier</a> and get instant API access. We offer:</p>
        <ul>
        <li>✅ 99.9% uptime guarantee</li>
        <li>✅ Response time under 1 second</li>
        <li>✅ Pay-as-you-go pricing</li>
        <li>✅ Comprehensive API documentation</li>
        </ul>
        <p><a href="/pricing">View our pricing →</a></p>
        HTML;
    }
}

