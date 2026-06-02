<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ClickableNinGuideSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();
        $authorId = $admin?->id ?? 1;

        $posts = [
            [
                'title' => 'How to Check NIN on MTN...',
                'slug' => 'code-for-checking-nin-on-mtn-is-simpler-than-most-people-think',
                'excerpt' => 'If you are trying to check your NIN on MTN, the fastest route is usually shorter than people expect.',
                'meta_title' => 'How to Check NIN on MTN',
                'meta_description' => 'A simple MTN-focused guide for checking your NIN quickly using the most practical self-service route.',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If you need to check your NIN on MTN, the usual retrieval route is to use the MTN line that was originally linked to your NIN and dial <strong>*346#</strong>. That is the standard NIN retrieval flow used across the major networks.</p>

<h2>What to do step by step</h2>
<ol>
<li>Insert the MTN SIM that was used during NIN linkage or enrollment.</li>
<li>Dial <strong>*346#</strong> and follow the retrieval option.</li>
<li>If you are only checking linkage status, use MTN's NIN Status Portal instead of retrying the same USSD step.</li>
</ol>

<h2>If it still does not work</h2>
<ul>
<li>Confirm that the SIM is actually the one attached to your NIN record.</li>
<li>Check your line status through MTN's NIN status portal.</li>
<li>If you need to link the line, MTN's official linking instruction points to <strong>*996*1#</strong> or the online linking page.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://www.mtn.ng/helppersonal/nin-status-portal/" target="_blank" rel="noopener noreferrer">MTN NIN Status Portal guide</a></li>
<li><a href="https://www.mtn.ng/how-to-link-your-nin-to-your-mtn-number/" target="_blank" rel="noopener noreferrer">MTN NIN linking guide</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'MTN', 'USSD', 'Guide'],
                'views' => 17300,
                'published_at' => '2026-06-01 08:10:00',
            ],
            [
                'title' => 'How to Check Your NIN with Phone Number...',
                'slug' => 'checking-your-nin-with-just-your-phone-number-usually-starts-like-this',
                'excerpt' => 'People who want to check their NIN with only a phone number usually need one specific condition to be true first.',
                'meta_title' => 'How to Check Your NIN with Phone Number',
                'meta_description' => 'A simple guide to checking your NIN with your phone number and understanding what usually makes the process work.',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If you want to check your NIN with your phone number, the number has to be the same one used during enrollment or previous linkage. For most users, the retrieval action starts with <strong>*346#</strong> from that exact line.</p>

<h2>What usually works</h2>
<ol>
<li>Use the phone number that was tied to your NIN.</li>
<li>Dial <strong>*346#</strong> for retrieval.</li>
<li>If your number has changed, switch to the app or support route instead of forcing the wrong SIM.</li>
</ol>

<h2>Important detail</h2>
<ul>
<li>Retrieval depends heavily on the linked line.</li>
<li>The NIMC app and official portals are better fallback options if the original number is gone.</li>
<li>Do not trust random websites that ask for identity details without an official NIMC or telecom path.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://www.airtel.com.ng/NIN_information" target="_blank" rel="noopener noreferrer">Airtel NIN information page</a></li>
<li><a href="https://www.mtn.ng/helppersonal/nin-status-portal/" target="_blank" rel="noopener noreferrer">MTN NIN Status Portal guide</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'Phone Number', 'Guide', 'Retrieval'],
                'views' => 16100,
                'published_at' => '2026-05-31 07:50:00',
            ],
            [
                'title' => 'NIMC Portal Login for NIN...',
                'slug' => 'nimc-portal-login-for-nin-feels-hard-until-you-know-what-it-really-uses',
                'excerpt' => 'NIMC login issues often come down to using the wrong identity path or an unofficial link.',
                'meta_title' => 'NIMC Portal Login for NIN',
                'meta_description' => 'A simpler explanation of how the NIMC portal and official self-service access usually work for NIN users.',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>Most NIMC login confusion starts when users treat every NIN-related page as the same portal. The safer pattern in 2026 is to use official NIMC-controlled access points such as the recognised self-service flow, the NIMC app, or NINAuth where the service requires digital identity authentication.</p>

<h2>Why people get stuck</h2>
<p>Unofficial links, outdated instructions, and missing access to the registered line all create confusion. The solution is usually not “try more random portals”, but return to the official path.</p>

<h2>Safer approach</h2>
<ul>
<li>Use official NIMC channels only.</li>
<li>Avoid random “correction” or “free login” links.</li>
<li>Use the app or verified support options when the portal route fails.</li>
<li>If a service asks for NIN-based authentication, check whether it routes through NINAuth.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://app.ninauth.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NINAuth official login page</a></li>
<li><a href="https://www.channelstv.com/2026/06/02/nimc-alerts-nigerians-to-fake-nin-correction-portal-scam/" target="_blank" rel="noopener noreferrer">Channels TV on the fake correction portal alert</a></li>
</ul>
HTML,
                'tags' => ['NIMC', 'NIN', 'Portal', 'Guide'],
                'views' => 14900,
                'published_at' => '2026-05-30 09:20:00',
            ],
            [
                'title' => 'How to Check NIN on Airtel...',
                'slug' => 'airtel-users-checking-their-nin-usually-miss-this-one-important-step',
                'excerpt' => 'Most Airtel NIN retrieval issues are not about Airtel alone. The linked number is what usually decides the result.',
                'meta_title' => 'How to Check NIN on Airtel',
                'meta_description' => 'Airtel-focused NIN retrieval guide built around the most practical reason checks succeed or fail.',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If you want to check your NIN on Airtel, begin with the Airtel number that is already tied to your identity record and use the standard retrieval route, which is typically <strong>*346#</strong>.</p>

<h2>Why that matters</h2>
<p>Many people focus on the network and ignore the actual problem: the wrong line is being used. When the line matches the NIN record, the retrieval path becomes much easier.</p>

<h2>What to do next</h2>
<ul>
<li>Try <strong>*346#</strong> with the registered Airtel line.</li>
<li>If you want to submit or update linkage to Airtel, Airtel's own FAQ points to <strong>*121*1#</strong>, SMS to 121, or the Airtel NIN web page.</li>
<li>If it fails, confirm whether another number was used during enrollment.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://www.airtel.com.ng/ng/personal/faq" target="_blank" rel="noopener noreferrer">Airtel FAQ</a></li>
<li><a href="https://www.airtel.com.ng/NIN_information" target="_blank" rel="noopener noreferrer">Airtel NIN information page</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'Airtel', 'Guide', 'Phone Number'],
                'views' => 13800,
                'published_at' => '2026-05-29 08:40:00',
            ],
            [
                'title' => 'How to Get NIN Number on MTN...',
                'slug' => 'if-mtn-is-not-showing-your-nin-this-is-probably-the-real-reason',
                'excerpt' => 'When MTN does not return a NIN, the issue is often deeper than the code itself.',
                'meta_title' => 'How to Get NIN Number on MTN',
                'meta_description' => 'A practical guide to understanding why MTN may not return your NIN and what to check next.',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If MTN is not showing your NIN, the code may not be the problem. In many cases, the line being used is not the one connected to the NIN record, or the issue is actually a linkage-status problem rather than a retrieval problem.</p>

<h2>Why this happens</h2>
<p>People often retry the same request several times when the real issue sits in the underlying identity record. The mismatch is usually around the registered number, not the action itself.</p>

<h2>What to check next</h2>
<ul>
<li>Try the retrieval route on the registered MTN line first.</li>
<li>Check line linkage through MTN's NIN status portal.</li>
<li>If the line still needs linking, MTN directs users to <strong>*996*1#</strong> or the official online linking page.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://www.mtn.ng/helppersonal/nin-status-portal/" target="_blank" rel="noopener noreferrer">MTN NIN Status Portal guide</a></li>
<li><a href="https://www.mtn.ng/how-to-link-your-nin-to-your-mtn-number/" target="_blank" rel="noopener noreferrer">MTN NIN linking guide</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'MTN', 'Guide', 'Troubleshooting'],
                'views' => 15200,
                'published_at' => '2026-05-28 09:05:00',
            ],
            [
                'title' => 'How to Check for My NIN...',
                'slug' => 'want-to-check-your-nin-by-yourself-before-going-to-nimc-read-this-first',
                'excerpt' => 'Before visiting a center, there are a few self-service routes that can save time if your details still line up.',
                'meta_title' => 'How to Check for My NIN',
                'meta_description' => 'A self-service NIN guide for people who want to try the faster routes before visiting a NIMC center.',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If you want to check your NIN by yourself before going to a NIMC center, start with the retrieval option on your registered line, which is commonly <strong>*346#</strong>, then move to official app or portal options only if the line route is blocked.</p>

<h2>What changes the result</h2>
<p>If you still have access to the linked line, you are in the best position for self-service. If not, the in-person route becomes much more realistic.</p>

<h2>Simple takeaway</h2>
<ul>
<li>Use the registered number if possible.</li>
<li>Stick to official channels.</li>
<li>Visit a center only after the faster options clearly fail.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://www.airtel.com.ng/NIN_information" target="_blank" rel="noopener noreferrer">Airtel NIN information page</a></li>
<li><a href="https://app.ninauth.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NINAuth official login page</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'Guide', 'Self Service', 'Phone Number'],
                'views' => 13200,
                'published_at' => '2026-05-27 07:40:00',
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
                    'category' => 'Guides',
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
