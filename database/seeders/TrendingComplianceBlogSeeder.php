<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TrendingComplianceBlogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();
        $authorId = $admin?->id ?? 1;

        $posts = [
            [
                'title' => 'NIMC Warns Nigerians About Fake NIN Correction Portal',
                'slug' => 'nimc-warns-nigerians-about-fake-nin-correction-portal',
                'excerpt' => 'On June 2, 2026, NIMC warned Nigerians not to use phishing links offering free NIN correction services.',
                'category' => 'Security',
                'tags' => ['NIN', 'NIMC', 'Security', 'Phishing'],
                'meta_title' => 'NIMC Fake NIN Correction Portal Alert | June 2026 Update',
                'meta_description' => 'NIMC warned Nigerians on June 2, 2026 about fake NIN correction links. Here is what happened and the official channel to use.',
                'published_at' => '2026-06-02 09:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>NIMC has again warned the public about fake websites pretending to offer NIN correction services. The latest alert, published on June 2, 2026, points to phishing attempts built to collect personal identity data from unsuspecting users.</p>

<h2>Why people are paying attention</h2>
<p>NIN updates are no longer a niche issue. They affect banking, telecom onboarding, travel-related applications, and other regulated services. That makes scam links around "free correction" highly believable, especially when they spread through social media.</p>

<h2>Practical takeaway</h2>
<ul>
<li>Ignore any correction link that does not come from an official NIMC channel.</li>
<li>Use the approved self-service portal when you need to edit your record.</li>
<li>If you are unsure, confirm through NIMC's official communication channels before entering any data.</li>
</ul>

<h2>Why businesses should care</h2>
<p>If your product touches identity onboarding, this is a good reminder to guide users toward trusted flows. Clear user education reduces support issues and lowers the risk of identity-related fraud affecting your customer base.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.channelstv.com/2026/06/02/nimc-alerts-nigerians-to-fake-nin-correction-portal-scam/" target="_blank" rel="noopener noreferrer">Channels TV - June 2, 2026</a></li>
<li><a href="https://punchng.com/nimc-debunks-fake-free-nin-data-correction-link-flyer/" target="_blank" rel="noopener noreferrer">PUNCH - March 21, 2026</a></li>
<li><a href="https://selfservicemodification.nimc.gov.ng" target="_blank" rel="noopener noreferrer">Official NIMC self-service modification portal</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'Free Ward-Level NIN Enrollment Has Started Nationwide',
                'slug' => 'free-ward-level-nin-enrollment-has-started-nationwide',
                'excerpt' => 'NIMC began free ward-level NIN enrollment on February 16, 2026 to push registration deeper into local communities.',
                'category' => 'Updates',
                'tags' => ['NIN', 'NIMC', 'Enrollment', 'Nigeria'],
                'meta_title' => 'Ward-Level NIN Enrollment in Nigeria | February 2026',
                'meta_description' => 'NIMC started free ward-level NIN enrollment on February 16, 2026. Here is what it means for Nigerians and service providers.',
                'published_at' => '2026-02-16 08:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>NIMC moved NIN enrollment deeper into local communities by launching a free ward-level registration drive from February 16, 2026. The idea is simple: reduce the distance between citizens and identity services.</p>

<h2>Why this update matters</h2>
<p>Large-scale registration drives change the identity landscape quickly. When enrollment gets closer to markets, neighborhoods, and local government areas, more first-time users can enter the formal identity system without the usual travel burden.</p>

<h2>Main points</h2>
<ul>
<li>The exercise is meant to run at ward level rather than only at central offices.</li>
<li>Adults and children are part of the target population.</li>
<li>The rollout is positioned as a free public enrollment effort.</li>
</ul>

<h2>Business angle</h2>
<p>Broader NIN coverage creates better conditions for KYC, customer onboarding, and regulated digital services. The more people are enrolled, the easier it becomes to serve them through verified channels.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://punchng.com/nimc-begins-ward-level-nin-enrolment-february-16/" target="_blank" rel="noopener noreferrer">PUNCH - February 11, 2026</a></li>
<li><a href="https://www.nigerianeye.com/2026/02/nimc-launches-free-nationwide-ward.html" target="_blank" rel="noopener noreferrer">NigerianEye - February 11, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'Diaspora NIN Registrations Surge 58% as Overseas Demand Rises',
                'slug' => 'diaspora-nin-registrations-surge-58-percent',
                'excerpt' => 'NIMC said diaspora NIN registrations rose by 58% by February 2026, showing strong demand from Nigerians abroad.',
                'category' => 'News',
                'tags' => ['NIN', 'Diaspora', 'NIMC', 'Identity'],
                'meta_title' => 'Diaspora NIN Registrations Surge 58% | 2026',
                'meta_description' => 'Diaspora NIN enrollment rose sharply by February 2026. This post breaks down the numbers and what is driving the demand.',
                'published_at' => '2026-03-13 12:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>Demand for NIN outside Nigeria is climbing fast. By February 2026, overseas enrollments had grown to more than 1.5 million, representing a 58 percent rise from the June 2025 level.</p>

<h2>What is behind the growth</h2>
<p>NIMC linked the increase to smoother processes, more partner coverage, and better pre-enrollment support for applicants abroad. In practical terms, the system became easier to access and more responsive to diaspora demand.</p>

<h2>Why this matters now</h2>
<p>Nigerians abroad increasingly need NIN for services that connect back to home, especially banking and other regulated workflows. That makes diaspora identity enrollment more than a government statistic; it is a live demand signal.</p>

<h2>Product and compliance takeaway</h2>
<p>If you serve diaspora Nigerians, identity verification should not be treated as a local-only use case. The audience is growing, and the demand is being driven by real service requirements.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://punchng.com/diaspora-nin-registrations-surge-58-nimc/" target="_blank" rel="noopener noreferrer">PUNCH - March 13, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'CBN Tightens BVN Rules Effective May 1, 2026',
                'slug' => 'cbn-tightens-bvn-rules-effective-may-1-2026',
                'excerpt' => 'The CBN amended the BVN framework in March 2026, with new controls on phone number changes, access, and fraud watchlisting.',
                'category' => 'Compliance',
                'tags' => ['BVN', 'CBN', 'Compliance', 'Fraud'],
                'meta_title' => 'New BVN Rules Effective May 1, 2026 | CBN Update',
                'meta_description' => 'The CBN updated Nigeria’s BVN rules in March 2026. Here are the key changes that took effect on May 1, 2026.',
                'published_at' => '2026-05-01 08:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>The CBN refreshed the BVN rulebook in March 2026, and the updated controls started applying on May 1, 2026. The direction is clear: tighter identity governance and more aggressive fraud prevention.</p>

<h2>What changed</h2>
<ul>
<li>Fresh BVN enrollment is limited to people aged 18 and above.</li>
<li>A BVN-linked phone number can only be updated once.</li>
<li>BVN database access is reserved for licensed institutions.</li>
<li>Suspect BVNs can be placed on a short watch window while clarification is sought.</li>
</ul>

<h2>Why this matters</h2>
<p>These are not abstract policy changes. They affect customer recovery journeys, KYC support flows, fraud investigations, and how financial platforms explain identity rules to users.</p>

<h2>Practical takeaway</h2>
<p>Any business that relies on bank-linked identity data should review its messaging and support logic around BVN updates, especially for phone changes and fraud reviews.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.cbn.gov.ng/AboutCBN/Reforms.html" target="_blank" rel="noopener noreferrer">CBN Reforms and Initiatives</a></li>
<li><a href="https://www.cbn.gov.ng/PaymentsSystem/BVN.html" target="_blank" rel="noopener noreferrer">CBN BVN overview page</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'BVN Registrations Reach 68.6 Million in Q1 2026',
                'slug' => 'bvn-registrations-reach-68-6-million-in-q1-2026',
                'excerpt' => 'Fresh NIBSS data cited in April 2026 reports showed BVN registrations rising to 68.6 million by March 2026.',
                'category' => 'News',
                'tags' => ['BVN', 'NIBSS', 'Banking', 'Fintech'],
                'meta_title' => 'BVN Registrations Hit 68.6 Million in Q1 2026',
                'meta_description' => 'BVN registrations rose to 68.6 million in March 2026. This post explains the growth and the policy context around it.',
                'published_at' => '2026-04-07 09:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>BVN adoption kept moving upward in the first quarter of 2026, reaching 68.6 million registrations by March. The pace was not explosive, but the trend still points to steady expansion of bank-linked identity coverage.</p>

<h2>Why the figure matters</h2>
<p>BVN remains one of the clearest signals of formal financial onboarding in Nigeria. When the number moves, it says something about inclusion, compliance, and the reach of digital financial services.</p>

<h2>What stands out from the reports</h2>
<ul>
<li>Growth continued even as regulators introduced tighter controls.</li>
<li>Diaspora-friendly enrollment initiatives appear to have supported momentum.</li>
<li>There is still a notable coverage gap between total bank accounts and BVN-linked users.</li>
</ul>

<h2>Why teams should watch this</h2>
<p>For fintech, lending, and verification products, BVN growth is a market signal. It helps show whether the identity layer under financial services is widening or stalling.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://techeconomy.ng/bvn-increases-by-over-700k-to-68-6-million-in-q1-2026/" target="_blank" rel="noopener noreferrer">TechEconomy - April 7, 2026</a></li>
<li><a href="https://www.cbn.gov.ng/AboutCBN/Reforms.html" target="_blank" rel="noopener noreferrer">CBN Reforms and Initiatives</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'CBN Wants 95% Financial Inclusion and Wider BVN Coverage by 2028',
                'slug' => 'cbn-wants-95-percent-financial-inclusion-and-wider-bvn-coverage-by-2028',
                'excerpt' => 'On June 2, 2026, the CBN said its Payments System Vision 2028 aims to bring 50 million more Nigerians into the formal system with BVN-backed accounts.',
                'category' => 'Updates',
                'tags' => ['BVN', 'CBN', 'Financial Inclusion', 'Payments'],
                'meta_title' => 'CBN PSV 2028 and BVN Expansion | June 2026',
                'meta_description' => 'CBN’s new payments vision targets 95% financial inclusion and millions of additional BVN-backed accounts by 2028.',
                'published_at' => '2026-06-02 10:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>The CBN's latest payments roadmap puts financial inclusion, digital trust, and identity-backed banking in the same conversation. The target is ambitious: 95 percent inclusion by 2028.</p>

<h2>Where BVN fits in</h2>
<p>According to the June 2, 2026 reporting around the launch, the plan would bring millions of additional Nigerians into the banking system with accounts attached to verified identities and BVNs.</p>

<h2>Why this matters</h2>
<p>This is more than a payments story. It shows that the official policy direction still treats identity as a core part of safe digital finance, especially for new users entering the formal system.</p>

<h2>What businesses should watch</h2>
<ul>
<li>More demand for low-friction onboarding.</li>
<li>Continued pressure to improve fraud tooling.</li>
<li>Stronger emphasis on identity-linked consumer protection.</li>
</ul>

<h2>Sources</h2>
<ul>
<li><a href="https://guardian.ng/business-services/cbn-targets-95-financial-inclusion-in-new-payment-system-goal/" target="_blank" rel="noopener noreferrer">The Guardian - June 2, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'CAC Launches Direct Payments on iCRP Through ReVOps',
                'slug' => 'cac-launches-direct-payments-on-icrp-through-revops',
                'excerpt' => 'CAC introduced direct portal payments in May 2026 for filings like annual returns, status reports, and letters of good standing.',
                'category' => 'Updates',
                'tags' => ['CAC', 'iCRP', 'Payments', 'Business Registration'],
                'meta_title' => 'CAC Direct Payments on iCRP | May 2026 Update',
                'meta_description' => 'CAC added ReVOps-based direct payments to iCRP in May 2026. Here is what changed and why it matters to business owners.',
                'published_at' => '2026-05-08 09:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>CAC has added a direct payment route to iCRP through ReVOps, giving users another way to pay for core filings without depending only on the older gateway flow.</p>

<h2>Why it matters</h2>
<p>For many founders and compliance teams, the payment step is where online filing starts to break down. Any change that reduces failed payments or delays quickly becomes relevant across the SME ecosystem.</p>

<h2>Covered filings include</h2>
<ul>
<li>Annual returns</li>
<li>Business address updates</li>
<li>Business name changes</li>
<li>Status reports and certified copies</li>
<li>Letters of good standing</li>
</ul>

<h2>Practical takeaway</h2>
<p>This update suggests CAC is still trying to smooth out the operational side of digital compliance. If the new flow performs well, it should reduce friction for routine post-incorporation work.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://msmeafricaonline.com/cac-introduces-direct-payment-on-icrp-portal-to-boost-service-delivery-and-transparency/" target="_blank" rel="noopener noreferrer">MSME Africa - May 7, 2026</a></li>
<li><a href="https://crispng.com/cac-direct-payment-update-icrp-revops-nigeria/" target="_blank" rel="noopener noreferrer">CrispNG - May 8, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'CAC Enforces Password Reset and 2FA After Portal Security Incident',
                'slug' => 'cac-enforces-password-reset-and-2fa-after-portal-security-incident',
                'excerpt' => 'Following an April 2026 security incident, CAC required iCRP users to reset passwords and activate app-based 2FA.',
                'category' => 'Security',
                'tags' => ['CAC', 'iCRP', 'Cybersecurity', '2FA'],
                'meta_title' => 'CAC Password Reset and 2FA Update | April 2026',
                'meta_description' => 'CAC required password resets and app-based two-factor authentication after a portal security incident in April 2026.',
                'published_at' => '2026-04-23 09:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>After a security incident, CAC tightened access to iCRP by forcing password resets and introducing app-based two-factor authentication for users returning to the platform.</p>

<h2>Why this stands out</h2>
<p>Corporate registry systems hold sensitive data. Once a breach scare happens, login hardening moves from being a technical upgrade to a business continuity issue.</p>

<h2>What changed for users</h2>
<ul>
<li>A fresh password reset became mandatory.</li>
<li>Authenticator-app verification was added to the login process.</li>
<li>Users had to adjust to a more security-first access flow.</li>
</ul>

<h2>Broader takeaway</h2>
<p>This is the kind of shift more public-service portals will likely follow. Security overhead may increase, but so does protection around high-value records.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.legit.ng/business-economy/industry/1706794-cac-issues-step-access-icrp-account-cybersecurity-breach/" target="_blank" rel="noopener noreferrer">Legit.ng - April 23, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'CAC Portal Outage Stalls New Registrations Across Nigeria',
                'slug' => 'cac-portal-outage-stalls-new-registrations-across-nigeria',
                'excerpt' => 'A prolonged CAC portal outage in May 2026 disrupted company registrations, filings, and name reservations nationwide.',
                'category' => 'News',
                'tags' => ['CAC', 'iCRP', 'Outage', 'SMEs'],
                'meta_title' => 'CAC Portal Outage Stalls Registrations | May 2026',
                'meta_description' => 'CAC’s portal outage in May 2026 disrupted business registrations and filings. Here is why the issue became a major talking point.',
                'published_at' => '2026-05-19 09:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>CAC's portal outage in May 2026 became a major talking point because it froze the practical side of doing business: name reservations, fresh registrations, and routine filings.</p>

<h2>Why this spread fast</h2>
<p>When a compliance platform goes down, the effect is immediate. Entrepreneurs cannot complete incorporation steps, agents cannot process applications, and businesses waiting on official documents get stuck.</p>

<h2>Why it matters</h2>
<ul>
<li>Delays at CAC can ripple into banking, contracting, and vendor onboarding.</li>
<li>Portal uptime is now part of the ease-of-doing-business conversation.</li>
<li>Digital reform is only credible when the service remains stable under pressure.</li>
</ul>

<h2>What this signals</h2>
<p>The conversation around CAC is no longer only about digitization. Reliability, recovery speed, and user trust have become just as important.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://guardian.ng/business-services/new-business-registrations-stall-as-cac-portal-suffers-prolonged-outage/" target="_blank" rel="noopener noreferrer">The Guardian - May 19, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'CAC Says AI Now Supports About 10,000 Daily Registration Requests',
                'slug' => 'cac-says-ai-now-supports-about-10000-daily-registration-requests',
                'excerpt' => 'CAC said in February 2026 that AI adoption had pushed daily business registration requests to around 10,000.',
                'category' => 'News',
                'tags' => ['CAC', 'AI', 'Business Registration', 'Digital Government'],
                'meta_title' => 'CAC AI and 10,000 Daily Registrations | February 2026',
                'meta_description' => 'CAC said AI adoption helped it handle around 10,000 business registration requests daily in February 2026.',
                'published_at' => '2026-02-09 16:00:00',
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>CAC says its AI-led operating model is now handling around 10,000 registration requests a day. That figure, shared during the agency's 35th anniversary period, shows how central automation has become to business registration in Nigeria.</p>

<h2>Why people are talking about it</h2>
<p>The number is large enough to signal both demand and dependence. More entrepreneurs are formalizing, and CAC is relying on AI systems to cope with the volume.</p>

<h2>What stands out</h2>
<ul>
<li>Registration demand is being linked to formalization and digital entrepreneurship.</li>
<li>CAC says customer-support traffic is also high.</li>
<li>Automation is being framed as necessary infrastructure rather than an experiment.</li>
</ul>

<h2>Why this matters</h2>
<p>If you work with founders, agents, or SME compliance, CAC throughput is a useful market signal. High daily filing volume usually means strong demand for business setup and post-registration services.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://punchng.com/cac-records-10000-business-registrations-daily-with-ai-adoption-registrar-general/" target="_blank" rel="noopener noreferrer">PUNCH - February 9, 2026</a></li>
<li><a href="https://www.cac.gov.ng/5700-2/" target="_blank" rel="noopener noreferrer">CAC 35th anniversary public notice</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Check the Phone Numbers Linked to Your NIN in 2026',
                'slug' => 'how-to-check-the-phone-numbers-linked-to-your-nin-in-2026',
                'excerpt' => 'One of the biggest NIN-related concerns in 2026 is unknown phone numbers attached to personal identity records. Here is how people are checking theirs.',
                'category' => 'Guides',
                'tags' => ['NIN', 'Phone Number', 'SIM Registration', 'Guide'],
                'meta_title' => 'How to Check Numbers Linked to Your NIN in 2026',
                'meta_description' => 'Learn how Nigerians are checking which phone numbers are linked to their NIN using USSD, network portals, and official channels.',
                'published_at' => '2026-03-03 09:00:00',
                'views' => 18750,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>One of the most practical identity questions right now is simple: which phone numbers are attached to my NIN? With fraud concerns rising, this has become one of the hottest help topics around telecom and identity compliance.</p>

<h2>What people are using</h2>
<p>Recent guides point to a few popular routes. The most talked-about option is dialling <strong>*996#</strong> to view NIN status and check linked numbers. People are also using their network apps, provider portals, or service centres when they want a more complete review.</p>

<h2>Why this is trending</h2>
<ul>
<li>People want to confirm that no unfamiliar SIM is attached to their identity.</li>
<li>Linked-number checks have become part of routine self-protection.</li>
<li>The topic sits at the intersection of telecom compliance and fraud prevention.</li>
</ul>

<h2>Best practice</h2>
<p>If you notice a number you do not recognise, escalate quickly through your telecom provider or an authorised NIMC support channel. The value of this check is not just visibility, but fast correction.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.legit.ng/ask-legit/guides/1698686-how-check-numbers-linked-nin-identity-safe/" target="_blank" rel="noopener noreferrer">Legit.ng - March 3, 2026</a></li>
<li><a href="https://play.google.com/store/apps/details?hl=en&id=com.nimcmobile" target="_blank" rel="noopener noreferrer">Official NIMC Mobile ID app listing</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Retrieve Your NIN with Your Phone Number, SMS, or *346#',
                'slug' => 'how-to-retrieve-your-nin-with-your-phone-number-sms-or-346',
                'excerpt' => 'NIN retrieval remains one of the most searched identity tasks in 2026, especially for people who need a quick answer from their phone.',
                'category' => 'Guides',
                'tags' => ['NIN', 'SMS', 'USSD', 'Phone Number', 'Guide'],
                'meta_title' => 'How to Retrieve Your NIN by SMS, USSD, or Phone Number',
                'meta_description' => 'A plain-language guide to retrieving your NIN in 2026 using your registered phone number, the *346# USSD method, or the NIMC app.',
                'published_at' => '2026-05-02 08:30:00',
                'views' => 21400,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>If there is one evergreen NIN guide that keeps trending, it is retrieval. People lose slips, forget the number, or suddenly need it for SIM registration, banking, travel, or school-related processes.</p>

<h2>The fastest route most people use</h2>
<p>The most common method in current guides is the <strong>*346#</strong> retrieval flow. It is popular because it works directly from the registered line and returns the NIN by SMS after the request is confirmed.</p>

<h2>Other options people rely on</h2>
<ul>
<li>The NIMC mobile app and portal flow.</li>
<li>Visiting an authorised enrollment centre when the registered line is unavailable.</li>
<li>Using only official or provider-approved channels rather than informal agents.</li>
</ul>

<h2>Why this keeps trending</h2>
<p>Retrieval is not a one-time need. It spikes whenever regulations or service providers ask for identity confirmation, so it keeps returning as a high-intent search topic.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.payora.app/blog/how-to-check-nin-number-online-nigeria" target="_blank" rel="noopener noreferrer">Payora - May 2, 2026</a></li>
<li><a href="https://www.legit.ng/ask-legit/guides/1698686-how-check-numbers-linked-nin-identity-safe/" target="_blank" rel="noopener noreferrer">Legit.ng - March 3, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Link Extra Mobile Numbers to Your NIN with the NIMC Mobile ID App',
                'slug' => 'how-to-link-extra-mobile-numbers-to-your-nin-with-the-nimc-mobile-id-app',
                'excerpt' => 'The NIMC Mobile ID app remains one of the easiest self-service tools for adding more mobile numbers to a NIN profile.',
                'category' => 'Guides',
                'tags' => ['NIN', 'Mobile App', 'Phone Number', 'Guide'],
                'meta_title' => 'How to Link Extra Mobile Numbers to Your NIN',
                'meta_description' => 'Use the NIMC Mobile ID app to manage extra mobile numbers linked to your NIN. Here is why that feature remains relevant in 2026.',
                'published_at' => '2026-03-10 10:15:00',
                'views' => 12600,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>The NIMC Mobile ID app is still one of the most useful self-service tools in the identity space because it lets users manage additional mobile numbers connected to their NIN.</p>

<h2>What the app offers</h2>
<p>The official app listing explains that users can add extra mobile numbers through the <strong>My Devices</strong> area. That makes the app relevant for people managing multiple lines tied to one identity profile.</p>

<h2>Why this topic is worth publishing</h2>
<ul>
<li>It answers a recurring user question in a direct way.</li>
<li>It supports a self-service habit instead of centre-only dependence.</li>
<li>It fits naturally with telecom compliance and SIM identity workflows.</li>
</ul>

<h2>Practical note</h2>
<p>Any update involving mobile numbers should still be handled carefully and through official interfaces. Identity convenience is useful, but accuracy matters more.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://play.google.com/store/apps/details?hl=en&id=com.nimcmobile" target="_blank" rel="noopener noreferrer">Official NIMC Mobile ID app listing</a></li>
<li><a href="https://www.legit.ng/ask-legit/guides/1698686-how-check-numbers-linked-nin-identity-safe/" target="_blank" rel="noopener noreferrer">Legit.ng - March 3, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'What the New One-Time BVN Phone Number Change Rule Means for Users',
                'slug' => 'what-the-new-one-time-bvn-phone-number-change-rule-means-for-users',
                'excerpt' => 'The new CBN limit on changing the phone number attached to a BVN is one of the most important consumer-facing banking changes of 2026.',
                'category' => 'Guides',
                'tags' => ['BVN', 'Phone Number', 'CBN', 'Guide'],
                'meta_title' => 'One-Time BVN Phone Number Change Rule Explained',
                'meta_description' => 'The CBN now allows BVN-linked phone number changes only once. This post explains why that matters and what users should do differently.',
                'published_at' => '2026-03-14 08:45:00',
                'views' => 16200,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>The new BVN phone-number restriction is more than a policy headline. It changes how users should think about bank identity records, especially when they replace SIMs, relocate, or lose long-used numbers.</p>

<h2>What changed</h2>
<p>Under the 2026 CBN update, the phone number linked to a BVN can only be changed once. The move is part of a wider anti-fraud push focused on identity controls in digital banking.</p>

<h2>Why this is a hot topic</h2>
<ul>
<li>Phone numbers are central to OTP delivery, alerts, and account recovery.</li>
<li>A stricter change rule affects ordinary users, not just institutions.</li>
<li>People now need to treat their BVN-linked line as a more strategic record.</li>
</ul>

<h2>Practical takeaway</h2>
<p>If your current banking line is stable, protect it. If you truly need an update, understand the implications before making the request because the new rule reduces room for repeated changes.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.cbn.gov.ng/AboutCBN/Reforms.html" target="_blank" rel="noopener noreferrer">CBN Reforms and Initiatives</a></li>
<li><a href="https://techcabal.com/2026/03/13/cbn-restricts-bvn-phone-number-changes-to-once-in-a-lifetime/" target="_blank" rel="noopener noreferrer">TechCabal - March 13, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'BVN Does Not Expire: What Nigerian Bank Customers Should Know',
                'slug' => 'bvn-does-not-expire-what-nigerian-bank-customers-should-know',
                'excerpt' => 'BVN expiry rumours still return in search traffic, making this one of the most useful clarification posts for bank users.',
                'category' => 'Guides',
                'tags' => ['BVN', 'CBN', 'Banking', 'Guide'],
                'meta_title' => 'Does BVN Expire? What Customers Should Know',
                'meta_description' => 'The CBN says BVN issued in Nigeria does not expire. Here is why that clarification still matters in 2026.',
                'published_at' => '2026-05-18 07:30:00',
                'views' => 9300,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>BVN expiry rumours continue to circulate, which is why this remains a useful high-intent explainer. The Central Bank of Nigeria has already clarified that a BVN issued in Nigeria does not expire.</p>

<h2>Why the clarification matters</h2>
<p>People often confuse record updates with expiry. A bank may ask for profile cleanup or further verification, but that is different from saying the BVN itself has a lifespan.</p>

<h2>What users should understand</h2>
<ul>
<li>BVN is designed as a long-term biometric identifier.</li>
<li>Record changes are governed by regulation and verification controls.</li>
<li>Customers should confirm viral banking claims with primary sources before reacting.</li>
</ul>

<h2>Why this will likely keep trending</h2>
<p>Any time banking fraud, KYC updates, or policy rumours rise, users search for clear answers about whether their BVN is still valid. This makes the topic consistently relevant.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://www.cbn.gov.ng/out/2023/ccd/cbn%20press%20release%20bvn%20100523.pdf" target="_blank" rel="noopener noreferrer">CBN press release - May 10, 2023</a></li>
<li><a href="https://www.cbn.gov.ng/AboutCBN/Reforms.html" target="_blank" rel="noopener noreferrer">CBN Reforms and Initiatives</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Log In to CAC iCRP with Your Email, Phone Number, or Accreditation Number',
                'slug' => 'how-to-log-in-to-cac-icrp-with-your-email-phone-number-or-accreditation-number',
                'excerpt' => 'CAC’s new iCRP login flow supports multiple identifiers, and many users still need a simple guide to get through it.',
                'category' => 'Guides',
                'tags' => ['CAC', 'iCRP', 'Phone Number', 'Guide'],
                'meta_title' => 'How to Log In to CAC iCRP in 2026',
                'meta_description' => 'A simple guide to CAC iCRP login, including the current options for using your email, phone number, username, or accreditation number.',
                'published_at' => '2026-05-05 11:00:00',
                'views' => 14100,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>The current CAC FAQ makes one thing clear: iCRP login is no longer just a username-and-password issue. Users can sign in with a username, email address, phone number, or accreditation number.</p>

<h2>Why this matters</h2>
<p>Many of the frustrations around the new portal come from basic access confusion. A clear login guide performs well because it solves a first-step problem before users even begin a filing.</p>

<h2>What users should expect</h2>
<ul>
<li>Multiple login identifiers are accepted.</li>
<li>First-time users may be prompted to enable 2FA.</li>
<li>Email-linked checks and account recovery paths are part of the current flow.</li>
</ul>

<h2>Why this is trending</h2>
<p>Every portal transition creates support demand around sign-in. Because CAC now sits at the center of daily business registration tasks, login help naturally becomes a high-traffic guide topic.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://icrp.cac.gov.ng/support/faq/" target="_blank" rel="noopener noreferrer">CAC iCRP FAQ</a></li>
<li><a href="https://www.legit.ng/business-economy/industry/1706794-cac-issues-step-access-icrp-account-cybersecurity-breach/" target="_blank" rel="noopener noreferrer">Legit.ng - April 23, 2026</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Fix Duplicate Phone Number or Username on CAC iCRP',
                'slug' => 'how-to-fix-duplicate-phone-number-or-username-on-cac-icrp',
                'excerpt' => 'Duplicate phone number and username prompts on CAC iCRP are turning into one of the most useful search-driven support topics.',
                'category' => 'Guides',
                'tags' => ['CAC', 'iCRP', 'Phone Number', 'Username', 'Guide'],
                'meta_title' => 'How to Fix Duplicate Phone Number or Username on CAC iCRP',
                'meta_description' => 'CAC’s own FAQ explains what happens when iCRP detects a duplicate username or phone number. Here is a cleaner breakdown for users.',
                'published_at' => '2026-05-06 11:15:00',
                'views' => 11800,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>Duplicate account details are becoming a frequent iCRP support headache. CAC's FAQ already acknowledges the issue and explains that users may be required to update the old username or phone number before logging in.</p>

<h2>Why this post matters</h2>
<p>This is a classic “stuck at login” problem. It tends to generate urgent searches because the user usually discovers it while trying to complete a real filing or access an existing business record.</p>

<h2>What the current process looks like</h2>
<ul>
<li>The system flags a duplicate username or phone number.</li>
<li>The user is asked to provide the old detail and a replacement.</li>
<li>Login continues after the update is accepted.</li>
</ul>

<h2>Why this can trend further</h2>
<p>As more old and new CAC users converge on the same portal, identity collisions and legacy account issues are likely to remain a recurring support theme.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://icrp.cac.gov.ng/support/faq/" target="_blank" rel="noopener noreferrer">CAC iCRP FAQ</a></li>
</ul>
HTML,
            ],
            [
                'title' => 'How to Reset Your CAC Password and Recover Access to iCRP',
                'slug' => 'how-to-reset-your-cac-password-and-recover-access-to-icrp',
                'excerpt' => 'Password recovery remains one of the most practical CAC how-to topics because filing cannot start until access is restored.',
                'category' => 'Guides',
                'tags' => ['CAC', 'iCRP', 'Password Reset', 'Guide'],
                'meta_title' => 'How to Reset Your CAC Password and Recover iCRP Access',
                'meta_description' => 'Use CAC’s password recovery flow to regain access to iCRP. This post breaks down the current support path in plain language.',
                'published_at' => '2026-05-04 10:45:00',
                'views' => 10950,
                'content' => <<<'HTML'
<h2>Quick summary</h2>
<p>Password recovery may not sound glamorous, but it is one of the most commercially relevant CAC help topics because it blocks filings, name reservations, and document access when it goes wrong.</p>

<h2>What CAC says</h2>
<p>The portal's FAQ points users to the forgot-password flow, which sends reset instructions to the registered email address. CAC also provides helpdesk routes for users who no longer control the linked email.</p>

<h2>Why this is worth publishing</h2>
<ul>
<li>It addresses a real blocker rather than a general information query.</li>
<li>It aligns with the current login and security transition on iCRP.</li>
<li>It remains relevant as more users are pushed through stronger account protection steps.</li>
</ul>

<h2>Practical takeaway</h2>
<p>For CAC users, the quality of your account recovery path matters as much as your filing knowledge. If the login credentials are outdated, update them before an urgent filing window arrives.</p>

<h2>Sources</h2>
<ul>
<li><a href="https://icrp.cac.gov.ng/support/faq/" target="_blank" rel="noopener noreferrer">CAC iCRP FAQ</a></li>
<li><a href="https://www.legit.ng/business-economy/industry/1706794-cac-issues-step-access-icrp-account-cybersecurity-breach/" target="_blank" rel="noopener noreferrer">Legit.ng - April 23, 2026</a></li>
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
                    'views' => $post['views'] ?? rand(250, 5000),
                ]
            );
        }
    }
}
