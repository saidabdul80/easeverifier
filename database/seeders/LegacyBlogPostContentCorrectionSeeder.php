<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class LegacyBlogPostContentCorrectionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();
        $authorId = $admin?->id ?? 1;

        $posts = [
            [
                'title' => 'How to Know Your NIN Number - Complete Guide',
                'slug' => 'how-to-know-your-nin-number',
                'excerpt' => 'A clearer guide to finding your NIN using your registered phone number, USSD, the NIMC app, or an authorised center.',
                'category' => 'Guides',
                'tags' => ['NIN', 'Guide', 'Phone Number', 'USSD'],
                'meta_title' => 'How to Know Your NIN Number in Nigeria',
                'meta_description' => 'Use current, safer methods to retrieve your NIN in Nigeria, including the registered-line route, app access, and in-person support.',
                'published_at' => '2026-05-25 09:00:00',
                'views' => 8400,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>For most people, finding a forgotten NIN starts with the phone number used during enrollment. That is why retrieval by phone remains the easiest and most searched path.</p>

<h2>The simplest route</h2>
<p>The most common self-service method is the <strong>*346#</strong> flow. It works best when you use the same number that was originally tied to your NIN record.</p>

<h2>Other safe options</h2>
<ul>
<li>Use the NIMC Mobile ID app if you want a more account-based route.</li>
<li>Check the official NIMC website for approved support channels.</li>
<li>Visit an authorised enrollment centre if you no longer control the registered line.</li>
</ul>

<h2>Good habit</h2>
<p>Once you retrieve your NIN, store it securely. The stress usually starts when a service asks for it urgently and the original slip is nowhere to be found.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.payora.app/blog/how-to-check-nin-number-online-nigeria" target="_blank" rel="noopener noreferrer">Payora - May 2, 2026</a></li>
<li><a href="https://play.google.com/store/apps/details?hl=en&id=com.nimcmobile" target="_blank" rel="noopener noreferrer">Official NIMC Mobile ID app listing</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'NIMC Portal Login - Access Your NIN Dashboard',
                'slug' => 'nimc-portal-login-guide',
                'excerpt' => 'A cleaner walkthrough of the current NIMC portal and app flow for viewing identity details and handling self-service tasks.',
                'category' => 'Guides',
                'tags' => ['NIMC', 'NIN', 'Portal', 'Guide'],
                'meta_title' => 'NIMC Portal Login Guide',
                'meta_description' => 'Understand how Nigerians are using the NIMC portal and app flow in 2026 for retrieval, review, and self-service identity tasks.',
                'published_at' => '2026-05-24 10:00:00',
                'views' => 7600,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>Many users think of the NIMC portal as a single login page, but in practice the experience often starts with the official app, then moves into portal-based self-service.</p>

<h2>What people usually need from the portal</h2>
<ul>
<li>Viewing identity details after enrollment.</li>
<li>Managing follow-up tasks related to their NIN profile.</li>
<li>Moving away from manual, office-only support where possible.</li>
</ul>

<h2>Best way to think about it</h2>
<p>The portal is useful when paired with the official NIMC tools and verified contact points. It is not just about “logging in”; it is about reaching a trusted path for your own record.</p>

<h2>Practical advice</h2>
<p>If a website or link looks unfamiliar, do not use it for identity changes. Stick to the official website, the official app, or an authorised centre when dealing with NIN information.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.channelstv.com/2026/06/02/nimc-alerts-nigerians-to-fake-nin-correction-portal-scam/" target="_blank" rel="noopener noreferrer">Channels TV - June 2, 2026</a></li>
<li><a href="https://play.google.com/store/apps/details?hl=en&id=com.nimcmobile" target="_blank" rel="noopener noreferrer">Official NIMC Mobile ID app listing</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Check Your NIN on Airtel - USSD Code & Steps',
                'slug' => 'how-to-check-nin-on-airtel',
                'excerpt' => 'Airtel users usually want the fastest possible NIN retrieval route. This refreshed version focuses on the safest and most practical options.',
                'category' => 'Guides',
                'tags' => ['NIN', 'Airtel', 'USSD', 'Guide'],
                'meta_title' => 'How to Check NIN on Airtel',
                'meta_description' => 'A practical Airtel-focused NIN retrieval guide covering the registered-line method, app route, and what to do when retrieval fails.',
                'published_at' => '2026-05-23 09:30:00',
                'views' => 6800,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>If your Airtel line is the one attached to your NIN, you usually do not need a complicated process. Most users start with the same retrieval flow used across the major networks.</p>

<h2>What matters most</h2>
<p>The key detail is not the network alone. It is whether the number in your hand is the same one connected to your NIN profile. That is what determines whether the quick self-service route works smoothly.</p>

<h2>When the easy route fails</h2>
<ul>
<li>The number may not be the one tied to your NIN.</li>
<li>Your records may need review or correction through official channels.</li>
<li>The app or in-person route may be the safer fallback.</li>
</ul>

<h2>Practical takeaway</h2>
<p>Use the fastest self-service option first, then escalate to the NIMC app or an authorised centre if the line you are using no longer matches the enrollment record.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.payora.app/blog/how-to-check-nin-number-online-nigeria" target="_blank" rel="noopener noreferrer">Payora - May 2, 2026</a></li>
<li><a href="https://www.legit.ng/ask-legit/guides/1698686-how-check-numbers-linked-nin-identity-safe/" target="_blank" rel="noopener noreferrer">Legit.ng - March 3, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Get Your NIN Number on MTN - Easy Steps',
                'slug' => 'how-to-get-nin-on-mtn',
                'excerpt' => 'This corrected MTN guide focuses on the registered-line reality behind successful NIN retrieval rather than generic copy.',
                'category' => 'Guides',
                'tags' => ['NIN', 'MTN', 'USSD', 'Guide'],
                'meta_title' => 'How to Get Your NIN Number on MTN',
                'meta_description' => 'Retrieve your NIN on MTN using the current practical approach, with guidance on what to do if the usual flow does not work.',
                'published_at' => '2026-05-22 10:15:00',
                'views' => 7200,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>MTN remains one of the most common networks used for NIN retrieval, but the same principle still applies: the registered line is what makes the process easy.</p>

<h2>What to focus on</h2>
<p>The real question is not just “what code should I dial?” It is whether the MTN line you are holding is the same one the identity record expects. When it is, the retrieval path is usually straightforward.</p>

<h2>What if it does not work?</h2>
<ul>
<li>Check whether another number was used during enrollment.</li>
<li>Use the NIMC app route if you need another self-service option.</li>
<li>Visit a support centre when the linked line is no longer accessible.</li>
</ul>

<h2>Practical takeaway</h2>
<p>Think of MTN retrieval as part of a larger identity workflow. The network helps, but the real foundation is the quality of the underlying NIN record.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.payora.app/blog/how-to-check-nin-number-online-nigeria" target="_blank" rel="noopener noreferrer">Payora - May 2, 2026</a></li>
<li><a href="https://www.legit.ng/ask-legit/guides/1698686-how-check-numbers-linked-nin-identity-safe/" target="_blank" rel="noopener noreferrer">Legit.ng - March 3, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'NIMC Self Service Portal - Complete User Guide',
                'slug' => 'nimc-self-service-portal-guide',
                'excerpt' => 'This updated version explains the self-service angle more realistically and keeps the guidance anchored to trusted channels.',
                'category' => 'Guides',
                'tags' => ['NIMC', 'NIN', 'Self Service', 'Guide'],
                'meta_title' => 'NIMC Self Service Portal Guide',
                'meta_description' => 'A practical guide to the NIMC self-service path, with emphasis on official access points, corrections, and safe usage.',
                'published_at' => '2026-05-21 08:45:00',
                'views' => 7900,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>The phrase “self-service portal” often gets used loosely, but the real value is simple: giving users a safer digital path for identity tasks that do not always need a physical visit.</p>

<h2>What people usually want</h2>
<ul>
<li>To retrieve or confirm identity details.</li>
<li>To start correction or update-related processes through approved channels.</li>
<li>To reduce unnecessary trips to enrollment centres.</li>
</ul>

<h2>Where caution is needed</h2>
<p>Because NIN-related services attract scammers, users should treat every correction or login link carefully. If the source is unofficial, it should not be trusted with identity data.</p>

<h2>Best practice</h2>
<p>Use the official website, the official app, or an authorised centre. In identity management, the safest route is usually more valuable than the fastest-looking one.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.channelstv.com/2026/06/02/nimc-alerts-nigerians-to-fake-nin-correction-portal-scam/" target="_blank" rel="noopener noreferrer">Channels TV - June 2, 2026</a></li>
<li><a href="https://punchng.com/nimc-debunks-fake-free-nin-data-correction-link-flyer/" target="_blank" rel="noopener noreferrer">PUNCH - March 21, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Check My NIN Number - All Methods Explained',
                'slug' => 'how-to-check-my-nin-number',
                'excerpt' => 'A cleaner all-methods guide that explains the common retrieval paths without sounding copied or overly mechanical.',
                'category' => 'Guides',
                'tags' => ['NIN', 'Guide', 'SMS', 'USSD', 'Portal'],
                'meta_title' => 'How to Check My NIN Number - All Methods Explained',
                'meta_description' => 'A straightforward explanation of the main ways Nigerians retrieve their NIN in 2026, including USSD, app, portal, and in-person support.',
                'published_at' => '2026-05-20 08:00:00',
                'views' => 8800,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>There is no single perfect method for checking a NIN. The best option depends on whether you still control the registered phone number, whether you need only the number, or whether you need deeper record access.</p>

<h2>The main routes</h2>
<ul>
<li>USSD retrieval for speed.</li>
<li>The NIMC app for a more structured self-service path.</li>
<li>Official web access for users working through approved digital channels.</li>
<li>In-person support when the digital options are blocked by missing or outdated records.</li>
</ul>

<h2>How to choose the right one</h2>
<p>If you need the number urgently and still have the registered line, start there. If you need a broader identity review or your line has changed, use the app or an authorised centre instead of guessing.</p>

<h2>Why this guide stays relevant</h2>
<p>NIN requests come from many directions: telecom, banking, schools, travel, and regulated services. That keeps “how do I check my NIN?” permanently useful.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.payora.app/blog/how-to-check-nin-number-online-nigeria" target="_blank" rel="noopener noreferrer">Payora - May 2, 2026</a></li>
<li><a href="https://play.google.com/store/apps/details?hl=en&id=com.nimcmobile" target="_blank" rel="noopener noreferrer">Official NIMC Mobile ID app listing</a></li>
</ul>
HTML,
            ],
        ];

        foreach ($posts as $post) {
            BlogPost::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'author_id' => $authorId,
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'category' => $post['category'],
                    'tags' => $post['tags'],
                    'status' => 'published',
                    'published_at' => Carbon::parse($post['published_at']),
                    'meta_title' => $post['meta_title'],
                    'meta_description' => $post['meta_description'],
                    'views' => $post['views'],
                ]
            );
        }
    }
}
