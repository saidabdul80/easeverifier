<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MissingNinIntentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();
        $authorId = $admin?->id ?? 1;

        $posts = [
            [
                'title' => 'Code to Check NIN Number...',
                'slug' => 'code-to-check-nin-number',
                'excerpt' => 'This covers the generic retrieval query from users who want the shortest route back to their NIN without choosing a network-specific guide first.',
                'meta_title' => 'Code to Check NIN Number',
                'meta_description' => 'A practical guide to the current NIN retrieval code, when it works, and the fastest fallback when it fails.',
                'published_at' => '2026-06-02 09:15:00',
                'views' => 14600,
                'tags' => ['NIN', 'Code', 'USSD', 'Guide'],
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>The generic retrieval path most users rely on is <strong>*346#</strong>. Airtel's current NIN information page still points users to that route for retrieving a NIN, and it works best when you use the phone number originally linked to your record.</p>

<h2>What this query usually means</h2>
<p>People who type “code to check NIN number” usually want a fast answer, not a long portal tutorial. The important detail is that the code only works reliably when the SIM in your phone matches the number already tied to your NIN.</p>

<h2>What to do first</h2>
<ul>
<li>Use the SIM connected to your NIN record.</li>
<li>Dial <strong>*346#</strong> and follow the retrieval prompt.</li>
<li>If it fails, do not keep retrying different lines. Move to your network's NIN status tool or an official NIMC support route.</li>
</ul>

<h2>Why it fails for some users</h2>
<ul>
<li>The line in hand is not the line originally linked during enrollment.</li>
<li>The NIN was entered wrongly or the line was linked later than expected.</li>
<li>The user has changed SIM access and now needs an app, portal, or support-based recovery path.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://www.airtel.com.ng/NIN_information" target="_blank" rel="noopener noreferrer">Airtel NIN information page</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'Code to Get NIN Number...',
                'slug' => 'code-to-get-nin-number',
                'excerpt' => 'This covers users who search with “get” instead of “check” and usually expect a straight recovery path with no extra reading.',
                'meta_title' => 'Code to Get NIN Number',
                'meta_description' => 'A direct guide for users searching the code to get their NIN number back, including the best fallback when the line route stops working.',
                'published_at' => '2026-06-01 10:25:00',
                'views' => 12100,
                'tags' => ['NIN', 'Retrieval', 'Code', 'Guide'],
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If your goal is to get your NIN number back quickly, the usual first step is the official retrieval code used from the phone number already tied to your NIN.</p>

<h2>Why this query matters</h2>
<p>This search is broad, but the intent is strong. Most people using it have forgotten their NIN and want the shortest route before they try an app, a portal, or a center visit.</p>

<h2>Best path</h2>
<ul>
<li>Use the linked SIM.</li>
<li>Try the NIN retrieval code first.</li>
<li>If the linked SIM is gone, switch to an official support or self-service path instead of borrowing another person's line to test random codes.</li>
</ul>

<h2>Best fallback when the code does not work</h2>
<p>If the retrieval step fails because your phone number changed, your next safe option is to use official NIMC-controlled channels. For digital identity tasks, NINAuth is active. For complaints or portal trouble, the February 2026 NIMC service charter lists <strong>nimccustomercare@nimc.gov.ng</strong>, <strong>nimcmodification@nimc.gov.ng</strong>, and <strong>0700-2255-6462</strong> as support routes.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.airtel.com.ng/NIN_information" target="_blank" rel="noopener noreferrer">Airtel NIN information page</a></li>
<li><a href="https://app.ninauth.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NINAuth official access page</a></li>
<li><a href="https://nimc-cms.idvault.co/storage/Uploads/nimc-service-charter-february-2026.pdf" target="_blank" rel="noopener noreferrer">NIMC service charter, February 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Print NIN Slip with NIN Number...',
                'slug' => 'how-to-print-nin-slip-with-nin-number',
                'excerpt' => 'This is a high-intent help query because the user usually already has the NIN and now needs a printable or reissued slip that can actually be used.',
                'meta_title' => 'How to Print NIN Slip with NIN Number',
                'meta_description' => 'A practical explanation of what users can do when they already have a NIN number and need a valid slip or printable identity record.',
                'published_at' => '2026-05-31 08:50:00',
                'views' => 11400,
                'tags' => ['NIN', 'Slip', 'Print', 'Guide'],
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If you already have your NIN and need a slip, stay inside official NIMC-controlled channels. Having the number helps, but it does not mean every printable version should be downloaded from random third-party sites.</p>

<h2>What current official sources show</h2>
<ul>
<li>Your NIN slip contains the 11-digit number.</li>
<li>NIMC's February 2026 service charter lists <strong>NIN Slip Re-issuance (Lost/Damaged Slip)</strong> at <strong>₦600</strong>.</li>
<li>The same charter lists <strong>NIN Slip via Self-Service Portal</strong> at <strong>₦1,000</strong>.</li>
<li>The public e-card request portal also exists for eligible users who need NIMC-issued card access after the slip stage.</li>
</ul>

<h2>Practical takeaway</h2>
<ol>
<li>Confirm the NIN number is correct.</li>
<li>Use the official self-service path if your need fits a portal-issued slip route.</li>
<li>Use an authorised center if the request is really for re-issuance, replacement, or a problem that the online step does not resolve.</li>
</ol>

<h2>What to avoid</h2>
<p>Avoid pages that ask you to upload identity details for a “free printout” without any clear NIMC ownership. That is where users lose time and sometimes expose personal data.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://nimc-cms.idvault.co/storage/Uploads/nimc-service-charter-february-2026.pdf" target="_blank" rel="noopener noreferrer">NIMC service charter, February 2026</a></li>
<li><a href="https://pubecardrequest.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NIMC public e-card request portal</a></li>
<li><a href="https://selfservicemodification.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NIMC self-service modification portal</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Check My NIMC Details Online...',
                'slug' => 'how-to-check-my-nimc-details-online',
                'excerpt' => 'Users searching this usually want to confirm identity details, fix access issues, or continue a self-service task without visiting a center first.',
                'meta_title' => 'How to Check My NIMC Details Online',
                'meta_description' => 'A current guide to the online routes users can rely on when checking NIMC-related details, records, and self-service status.',
                'published_at' => '2026-05-30 09:35:00',
                'views' => 10900,
                'tags' => ['NIMC', 'NIN', 'Online', 'Guide'],
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If you want to check your NIMC details online, start from official NIMC-controlled routes only. In practice, that usually means the NIMC app or digital identity flow, the pre-enrollment portal if your task is application-related, or the self-service/support route if the issue is about corrections or access.</p>

<h2>Why this search matters</h2>
<p>People who search this are usually not just asking for their NIN. They want to know whether their record is still accessible, whether their details are correct, and where to go when one portal does not do everything.</p>

<h2>What to use now</h2>
<ul>
<li><strong>NINAuth</strong> for supported digital identity authentication use cases.</li>
<li><strong>Pre-enrollment and booking</strong> if you need to start, resume, or rebook an enrollment process.</li>
<li><strong>Self-service modification and support channels</strong> if the issue is about corrections, portal failure, or record trouble.</li>
</ul>

<h2>Important reality check</h2>
<p>There is no single public “master dashboard” that does everything for every NIN task. Users lose time when they keep looking for one universal login instead of matching the task to the correct official NIMC route.</p>

<h2>If the portal or app is not working</h2>
<p>The February 2026 NIMC service charter points users to <strong>nimc.esupport.ng</strong> for complaints, plus <strong>nimccustomercare@nimc.gov.ng</strong>, <strong>nimcmodification@nimc.gov.ng</strong>, and <strong>0700-2255-6462</strong> for help when technology or record issues block progress.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://app.ninauth.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NINAuth official login</a></li>
<li><a href="https://penrol.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NIMC pre-enrollment portal</a></li>
<li><a href="https://selfservicemodification.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NIMC self-service modification portal</a></li>
<li><a href="https://nimc-cms.idvault.co/storage/Uploads/nimc-service-charter-february-2026.pdf" target="_blank" rel="noopener noreferrer">NIMC service charter, February 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'NIMC Correction Portal Login...',
                'slug' => 'nimc-correction-portal-login',
                'excerpt' => 'This keyword has strong intent and high scam risk, so the title must win the click and the content must send users to the real correction route fast.',
                'meta_title' => 'NIMC Correction Portal Login',
                'meta_description' => 'A careful guide to NIMC correction portal-style searches, including the real portal to use, what it handles, and how to avoid fake links.',
                'published_at' => '2026-05-28 10:10:00',
                'views' => 11800,
                'tags' => ['NIMC', 'Correction', 'Portal', 'Security'],
                'content' => <<<'HTML'
<h2>Quick answer</h2>
<p>If you are looking for a NIMC correction portal login, the official route is the <strong>NIMC Self-Service Modification Portal</strong> at <strong>selfservicemodification.nimc.gov.ng</strong>. The bigger problem right now is not finding a portal, but avoiding fake ones.</p>

<h2>Latest warning</h2>
<p>On <strong>June 2, 2026</strong>, Channels Television reported NIMC's latest warning against fake NIN correction links and fake “free correction” claims. That makes this search query unusually sensitive because users often click the first convincing-looking result they see.</p>

<h2>What to do</h2>
<ul>
<li>Start from the official self-service modification portal only.</li>
<li>Do not trust forwarded “free correction” links.</li>
<li>If the page looks unfamiliar, stop before entering any identity details.</li>
</ul>

<h2>What the official portal is for</h2>
<p>The self-service modification portal is the route NIMC uses for correction-style tasks. The February 2026 NIMC service charter also points users to official digital channels and lists dedicated emails for modification and customer care.</p>

<h2>If the portal is not working</h2>
<ul>
<li>Use <strong>nimccustomercare@nimc.gov.ng</strong>.</li>
<li>Use <strong>nimcmodification@nimc.gov.ng</strong>.</li>
<li>Call <strong>0700-2255-6462</strong>.</li>
<li>Submit a complaint through <strong>nimc.esupport.ng</strong>.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://selfservicemodification.nimc.gov.ng/" target="_blank" rel="noopener noreferrer">NIMC self-service modification portal</a></li>
<li><a href="https://nimc-cms.idvault.co/storage/Uploads/nimc-service-charter-february-2026.pdf" target="_blank" rel="noopener noreferrer">NIMC service charter, February 2026</a></li>
<li><a href="https://www.channelstv.com/2026/06/02/nimc-alerts-nigerians-to-fake-nin-correction-portal-scam/" target="_blank" rel="noopener noreferrer">Channels TV fake portal alert report</a></li>
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
