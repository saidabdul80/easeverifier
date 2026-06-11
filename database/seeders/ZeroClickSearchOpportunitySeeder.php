<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ZeroClickSearchOpportunitySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();
        $authorId = $admin?->id ?? 1;

        $posts = [
            [
                'title' => 'Code to Check NIN on MTN...',
                'slug' => 'code-to-check-nin-on-mtn',
                'excerpt' => 'This is one of your highest-impression MTN queries, and the answer depends on both the retrieval code and the SIM already linked to the NIN.',
                'meta_title' => 'Code to Check NIN on MTN',
                'meta_description' => 'A current MTN-specific guide to checking your NIN, confirming linkage status, and fixing failed retrieval attempts.',
                'views' => 19100,
                'published_at' => '2026-06-02 08:10:00',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>For MTN users, the common retrieval route is to dial <strong>*346#</strong> from the line already linked to your NIN. If you only want to confirm whether the line is linked, MTN now points users to its NIN Status Portal.</p>

<h2>What to do</h2>
<ol>
<li>Use the MTN SIM originally tied to your NIN.</li>
<li>Dial <strong>*346#</strong> and follow the retrieval prompt.</li>
<li>If you are unsure whether the line is already linked, use the MTN NIN Status Portal instead of repeating the same USSD step.</li>
</ol>

<h2>If it fails</h2>
<ul>
<li>The line may not be the one attached to your NIN record.</li>
<li>The line may still need formal linkage through MTN.</li>
<li>You may need the NIMC route if your original number is no longer active.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://www.mtn.ng/helppersonal/nin-status-portal/" target="_blank" rel="noopener noreferrer">MTN NIN Status Portal guide</a></li>
<li><a href="https://www.mtn.ng/how-to-link-your-nin-to-your-mtn-number/" target="_blank" rel="noopener noreferrer">MTN NIN linking guide</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'MTN', 'USSD', 'Guide'],
            ],
            [
                'title' => 'NIN Code for MTN...',
                'slug' => 'nin-code-for-mtn',
                'excerpt' => 'Users searching this phrase usually want the fastest way to retrieve or confirm NIN activity on an MTN line.',
                'meta_title' => 'NIN Code for MTN',
                'meta_description' => 'The practical MTN NIN code guide for retrieval, status checks, and what to do when the line is not linked.',
                'views' => 16400,
                'published_at' => '2026-06-01 11:00:00',
                'content' => <<<'HTML'
<h2>What people usually mean</h2>
<p>When someone searches for the “NIN code for MTN”, they are often looking for one of three things: the code to retrieve their NIN, the code to link MTN to NIN, or a way to confirm whether their line is already linked.</p>

<h2>Current MTN routes</h2>
<ul>
<li><strong>*346#</strong> is the common retrieval route when the line is already tied to the NIN.</li>
<li><strong>*996*1#</strong> is the official MTN linking route highlighted by MTN.</li>
<li>MTN also provides an online NIN Status Portal for line confirmation.</li>
</ul>

<h2>Best way to use the right option</h2>
<p>If you already linked the line and only want the NIN, start with retrieval. If you are not sure the line is linked, check status first. That avoids repeating the wrong step.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.mtn.ng/how-to-link-your-nin-to-your-mtn-number/" target="_blank" rel="noopener noreferrer">MTN NIN linking guide</a></li>
<li><a href="https://www.mtn.ng/helppersonal/nin-status-portal/" target="_blank" rel="noopener noreferrer">MTN NIN Status Portal guide</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'MTN', 'Code', 'Guide'],
            ],
            [
                'title' => 'Code to Check NIN on Airtel...',
                'slug' => 'code-to-check-nin-on-airtel',
                'excerpt' => 'Airtel users searching this usually need either the retrieval route or the correct NIN submission path for their line.',
                'meta_title' => 'Code to Check NIN on Airtel',
                'meta_description' => 'Airtel-specific NIN guide covering retrieval, submission, and what the official Airtel channels currently say.',
                'views' => 14200,
                'published_at' => '2026-05-31 09:00:00',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>For Airtel users, the common NIN retrieval route is <strong>*346#</strong> on the line already linked to the NIN. If the goal is to submit or link your NIN to Airtel, Airtel also points users to <strong>*121*1#</strong>, SMS to 121, and its NIN web page.</p>

<h2>What to do</h2>
<ol>
<li>Use the Airtel SIM already tied to your NIN.</li>
<li>Dial <strong>*346#</strong> if you need to retrieve the NIN.</li>
<li>Use Airtel's NIN submission options if the line still needs to be linked.</li>
</ol>

<h2>Why people get stuck</h2>
<p>Many users mix up retrieval and submission. One gives you back the NIN tied to the line; the other sends your NIN to Airtel for linkage.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.airtel.com.ng/NIN_information" target="_blank" rel="noopener noreferrer">Airtel NIN information page</a></li>
<li><a href="https://www.airtel.com.ng/ng/personal/faq" target="_blank" rel="noopener noreferrer">Airtel FAQ</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'Airtel', 'USSD', 'Guide'],
            ],
            [
                'title' => 'Code to Retrieve NIN...',
                'slug' => 'code-to-retrieve-nin',
                'excerpt' => 'This query usually means the user wants the shortest self-service route to get a forgotten NIN back.',
                'meta_title' => 'Code to Retrieve NIN',
                'meta_description' => 'A simple guide to the current NIN retrieval route and the situations where it works or fails.',
                'views' => 15300,
                'published_at' => '2026-05-30 08:20:00',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>The standard NIN retrieval route is <strong>*346#</strong>, but it works best when you use the phone number that was originally linked to your NIN.</p>

<h2>When it works well</h2>
<ul>
<li>You still have the linked SIM.</li>
<li>Your NIN record was already completed correctly.</li>
<li>The network line is active and can receive the response.</li>
</ul>

<h2>When it does not</h2>
<p>If the number changed or the line was never properly linked, the retrieval code alone will not solve the issue. In that case, the app, a telecom status portal, or a NIMC support route is more useful.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.airtel.com.ng/NIN_information" target="_blank" rel="noopener noreferrer">Airtel NIN information page</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'Retrieval', 'USSD', 'Guide'],
            ],
            [
                'title' => 'NIMC Portal Login...',
                'slug' => 'nimc-portal-login',
                'excerpt' => 'This query has strong portal intent, but many users land on the wrong page or use unofficial links.',
                'meta_title' => 'NIMC Portal Login',
                'meta_description' => 'A current guide to official NIMC portal-style access points and how to avoid fake or irrelevant login pages.',
                'views' => 12600,
                'published_at' => '2026-05-29 10:00:00',
                'content' => <<<'HTML'
<h2>What users usually want</h2>
<p>People searching “NIMC portal login” usually want one of three things: to manage a NIN-related task, to authenticate identity digitally, or to continue a NIN enrollment or self-service flow.</p>

<h2>Current official routes</h2>
<ul>
<li><a href="https://app.ninauth.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NINAuth</a> for digital identity authentication.</li>
<li><a href="https://penrol.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NIN pre-enrollment portal</a> for new/resume booking flows.</li>
<li>The official NIMC channels for self-service and support-linked activities.</li>
</ul>

<h2>Important warning</h2>
<p>NIMC has warned Nigerians about fake correction portals. If a login page is unfamiliar or being forwarded informally, verify it before entering anything.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://app.ninauth.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NINAuth official login</a></li>
<li><a href="https://penrol.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NIMC pre-enrollment portal</a></li>
<li><a href="https://www.channelstv.com/2026/06/02/nimc-alerts-nigerians-to-fake-nin-correction-portal-scam/" target="_blank" rel="noopener noreferrer">Channels TV fake portal alert report</a></li>
</ul>
HTML,
                'tags' => ['NIMC', 'Portal', 'Login', 'Guide'],
            ],
            [
                'title' => 'NIN Self-Service Portal Login...',
                'slug' => 'nin-self-service-portal-login',
                'excerpt' => 'Users searching this usually need the proper digital route, not a random page that claims to be a NIN portal.',
                'meta_title' => 'NIN Self-Service Portal Login',
                'meta_description' => 'A focused guide to the official NIN self-service style access points and how users should approach them safely.',
                'views' => 9800,
                'published_at' => '2026-05-28 11:20:00',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>The safest way to approach any “NIN self-service portal” task is to begin from an official NIMC-controlled access point, not a forwarded link from social media or messaging apps.</p>

<h2>Good path</h2>
<ul>
<li>Use official NIMC pages such as NINAuth or the pre-enrollment portal when the task fits.</li>
<li>Use the NIMC app where the service specifically points there.</li>
<li>Stop if the page looks unfamiliar and confirm through official NIMC communication.</li>
</ul>

<h2>Why this matters</h2>
<p>Self-service intent is high because users want speed. That same urgency is exactly what fake portals try to exploit.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://app.ninauth.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NINAuth official login</a></li>
<li><a href="https://penrol.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NIMC pre-enrollment portal</a></li>
<li><a href="https://www.channelstv.com/2026/06/02/nimc-alerts-nigerians-to-fake-nin-correction-portal-scam/" target="_blank" rel="noopener noreferrer">Channels TV fake portal alert report</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'Portal', 'Self Service', 'Guide'],
            ],
            [
                'title' => 'Code to Check MTN Number Linked to NIN...',
                'slug' => 'code-to-check-mtn-number-linked-to-nin',
                'excerpt' => 'This is a strong intent query because the user usually wants to know whether the line is already connected before trying retrieval.',
                'meta_title' => 'Code to Check MTN Number Linked to NIN',
                'meta_description' => 'A guide to checking whether an MTN line is linked to NIN using the current MTN status route.',
                'views' => 10100,
                'published_at' => '2026-05-27 09:40:00',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If you want to know whether your MTN number is already linked to NIN, MTN's NIN Status Portal is the cleanest current route. It asks for the phone number, sends an OTP, and confirms the status.</p>

<h2>Why this is different from retrieval</h2>
<p>Retrieval gives you back a NIN. Status checking tells you whether the MTN line is already linked. The two tasks are related, but they solve different problems.</p>

<h2>Best use case</h2>
<ul>
<li>Check status first if you are unsure the line was ever linked.</li>
<li>Use retrieval only when you believe the line is already connected and you just need the NIN back.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://www.mtn.ng/helppersonal/nin-status-portal/" target="_blank" rel="noopener noreferrer">MTN NIN Status Portal guide</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'MTN', 'Status', 'Guide'],
            ],
            [
                'title' => 'How Many Digit Is NIN...',
                'slug' => 'how-many-digit-is-nin',
                'excerpt' => 'This simple query still matters because users often need quick confirmation before entering the number into a form.',
                'meta_title' => 'How Many Digit Is NIN',
                'meta_description' => 'A short explanation of the NIN format and how users can confirm the number they are using is the right one.',
                'views' => 7600,
                'published_at' => '2026-05-26 07:30:00',
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>NIN is <strong>11 digits</strong>. That is the standard format used across telecom, banking, and other identity-linked services.</p>

<h2>Why people search this</h2>
<p>This usually happens when a user is filling a form and wants to confirm that they are entering the right kind of number, not a BVN, phone number, or vNIN token.</p>

<h2>Important distinction</h2>
<ul>
<li>NIN is 11 digits.</li>
<li>vNIN is not the same thing; it is a tokenized version and can be alphanumeric.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://www.airtel.com.ng/NIN_information" target="_blank" rel="noopener noreferrer">Airtel NIN information page</a></li>
</ul>
HTML,
                'tags' => ['NIN', 'Format', 'Guide'],
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
