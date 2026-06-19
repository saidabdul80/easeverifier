```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register } from '@/routes';
</script>

<template>
    <Head title="Terms of Service - Service Agreement | EaseVerifier">
        <meta
            name="description"
            content="Review EaseVerifier's terms of service for using our identity verification API and platform. Understand your rights and responsibilities as a user."
        />
        <meta
            name="keywords"
            content="terms of service, service agreement, API terms, verification service terms, user agreement Nigeria, NIN verification terms, BVN verification terms, CAC verification terms"
        />
        <meta property="og:title" content="Terms of Service - EaseVerifier" />
        <meta
            property="og:description"
            content="Terms and conditions for using EaseVerifier's identity verification services and API."
        />
        <meta property="og:type" content="article" />
        <link rel="canonical" href="https://verify.ashlabtech.ng/terms" />
    </Head>

    <v-app>
        <v-app-bar flat color="white" elevation="1">
            <v-container class="d-flex align-center">
                <Link :href="home()" class="text-decoration-none d-flex align-center">
                    <v-avatar color="primary" size="36" class="mr-2">
                        <img
                            src="/ashlabtech.png"
                            alt="EaseVerifier"
                            style="width: 100%; height: 100%; object-fit: contain;"
                        />
                    </v-avatar>
                    <span class="text-h6 font-weight-bold text-primary">EaseVerifier</span>
                </Link>

                <v-spacer />

                <div class="d-flex ga-2">
                    <v-btn variant="outlined" color="primary" href="/login">Login</v-btn>
                    <v-btn variant="flat" color="primary" href="/register" class="d-none d-sm-flex">
                        Get Started
                    </v-btn>
                </div>
            </v-container>
        </v-app-bar>

        <v-main>
            <section class="hero-section py-12 text-center">
                <v-container>
                    <h1 class="text-h3 font-weight-bold text-white mb-2">Terms of Service</h1>
                    <p class="text-body-1 text-white opacity-80">Last updated: June 19, 2026</p>
                </v-container>
            </section>

            <section class="py-12">
                <v-container>
                    <v-card class="pa-8 mx-auto" max-width="950">
                        <p class="text-body-1 text-grey-darken-1 mb-8">
                            These Terms of Service govern your access to and use of EaseVerifier's
                            identity verification platform, dashboard, APIs, documentation, sandbox
                            environment, production services, and related services. By creating an
                            account, accessing our platform, generating API credentials, or using any
                            of our services, you agree to be bound by these Terms.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">1. Acceptance of Terms</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            By accessing or using EaseVerifier's services, you agree to these Terms
                            of Service, our Privacy Policy, and any other policies referenced on our
                            platform. If you do not agree with these Terms, you must not access or
                            use our services.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">2. Definitions</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            In these Terms:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>
                                <strong>EaseVerifier, we, us, or our</strong> means the operator of
                                the EaseVerifier platform.
                            </li>
                            <li>
                                <strong>Customer, you, or your</strong> means the individual,
                                business, organization, developer, or entity that creates an account
                                or uses our services.
                            </li>
                            <li>
                                <strong>End-user</strong> means the person whose identity information
                                is submitted for verification.
                            </li>
                            <li>
                                <strong>Services</strong> means our identity verification services,
                                including NIN, BVN, CAC, API access, dashboard access, sandbox access,
                                production access, reporting, and related services.
                            </li>
                            <li>
                                <strong>Verification Data</strong> means any personal data, identity
                                number, verification response, status, metadata, or related
                                information processed through our services.
                            </li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">3. Description of Services</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            EaseVerifier provides identity and business verification services through
                            API access and a web dashboard. Our services may include NIN verification,
                            BVN verification, CAC verification, and other related verification
                            services. The services are provided to help approved customers verify
                            identities or business records for lawful, disclosed, and consent-based
                            purposes.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">4. Account Registration</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            You must create an account to use our services. You agree to provide
                            accurate, complete, and up-to-date information during registration,
                            onboarding, KYC review, compliance review, and API approval. You are
                            responsible for maintaining the confidentiality of your login credentials,
                            API keys, access tokens, and account information. You are responsible for
                            all activities carried out under your account.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">5. Customer KYC and Onboarding</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-4">
                            EaseVerifier may require customers to complete identity, business,
                            compliance, and risk checks before accessing some or all of our services.
                            Production API access may be subject to manual approval and may not be
                            granted automatically after registration.
                        </p>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            For individual customers, we may request:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-4 ml-4">
                            <li>Full name</li>
                            <li>Email address and phone number</li>
                            <li>NIN or other identification details</li>
                            <li>Residential address</li>
                            <li>Selfie or face verification where required</li>
                            <li>Use case for verification services</li>
                        </ul>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            For business customers, we may request:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Company name and CAC registration number</li>
                            <li>CAC certificate and related business documents</li>
                            <li>TIN, business address, website, and company profile</li>
                            <li>Representative details and authorization letter where applicable</li>
                            <li>Director or beneficial owner details where required</li>
                            <li>Regulatory licence where applicable</li>
                            <li>Privacy policy, consent process, intended use case, and expected verification volume</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">6. Customer Responsibility for End-user Consent</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-4">
                            You are solely responsible for obtaining valid, informed, specific, and
                            lawful consent from each End-user before submitting their personal data,
                            NIN, BVN, CAC information, identity document, or any other identity
                            information for verification through EaseVerifier.
                        </p>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            You must clearly inform End-users about:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>The type of data being collected</li>
                            <li>The purpose of the verification</li>
                            <li>That their data may be submitted to EaseVerifier and relevant verification providers</li>
                            <li>How the verification result will be used</li>
                            <li>Your privacy policy and data retention practices</li>
                            <li>Their rights under applicable data protection laws</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">7. Data Protection and Privacy</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            You agree to comply with all applicable data protection, privacy,
                            cybersecurity, consumer protection, identity management, financial
                            services, and sector-specific laws and regulations. You must process
                            Verification Data only for the approved use case submitted during
                            onboarding or otherwise approved by EaseVerifier in writing. You must not
                            retain, share, sell, publish, misuse, enrich, or repurpose Verification
                            Data except as permitted by law and by these Terms.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">8. Approved Use Case</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            You must use the services only for the purpose disclosed during onboarding
                            and approved by EaseVerifier. You must not materially change your use case,
                            business model, customer type, verification flow, API integration, data
                            retention process, or onward data sharing without notifying us and
                            obtaining approval where required.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">9. Acceptable Use</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            You agree not to:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Use our services for illegal, fraudulent, deceptive, harmful, or abusive purposes</li>
                            <li>Verify any person without their knowledge, consent, or lawful basis</li>
                            <li>Submit false, stolen, fabricated, or unauthorized identity data</li>
                            <li>Use the services for surveillance, stalking, harassment, intimidation, or blackmail</li>
                            <li>Use the services for unlawful profiling or discriminatory decisions</li>
                            <li>Share your API credentials with unauthorized parties</li>
                            <li>Resell, sublicense, broker, or provide access to the services without written approval</li>
                            <li>Attempt to bypass rate limits, security controls, monitoring systems, or access restrictions</li>
                            <li>Scrape, copy, reverse engineer, overload, disrupt, or interfere with our systems</li>
                            <li>Use the services to build an unauthorized identity verification database</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">10. API Access and Security</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            API credentials are issued only for your approved account and approved use
                            case. You must keep all API keys, tokens, secrets, webhook secrets, and
                            dashboard credentials confidential and secure. You are responsible for
                            implementing appropriate security controls, including secure storage of
                            credentials, server-side API calls, HTTPS, access control, IP whitelisting
                            where applicable, logging, and prompt credential rotation if compromise is
                            suspected.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">11. Verification Results</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            Verification results are provided based on information available from
                            relevant verification sources, databases, providers, or government-
                            authorized systems at the time of the request. EaseVerifier does not
                            guarantee that all verification results will be error-free, complete,
                            current, or sufficient for your specific purpose. You are responsible for
                            deciding how to use verification results in your own onboarding,
                            compliance, fraud prevention, risk management, or business process.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">12. Third-party and Government Data Sources</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            Some services may depend on third-party providers, government databases,
                            licensed service providers, telecommunications networks, payment
                            processors, hosting providers, or other external systems. We are not
                            responsible for delays, downtime, incomplete responses, inaccurate
                            responses, changes in availability, pricing changes, regulatory changes,
                            or service interruptions caused by third-party or government systems.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">13. Payment Terms</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            Services may be provided on a prepaid, wallet, subscription, or
                            pay-as-you-go basis as displayed on the platform or agreed with you. You
                            must maintain sufficient wallet balance or active billing status before
                            making verification requests. Charges may apply whether a verification
                            request is successful, failed, pending, duplicate, unavailable, or returned
                            with no match, depending on the verification type and provider billing
                            rules. All payments are non-refundable except where required by law or
                            expressly approved by EaseVerifier.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">14. Refunds and Failed Transactions</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            Wallet funding errors, duplicate payments, or failed payment issues may be
                            reviewed upon request. Refunds, reversals, or wallet adjustments are
                            subject to payment processor confirmation, provider billing rules, internal
                            review, and applicable law. Verification fees already charged by upstream
                            providers may not be refundable.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">15. Service Availability</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            We will make reasonable efforts to provide reliable services. However, we
                            do not guarantee uninterrupted, error-free, or always-available access.
                            Services may be unavailable due to maintenance, upgrades, provider
                            downtime, internet failure, force majeure, security incidents, regulatory
                            restrictions, or other events beyond our reasonable control.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">16. Monitoring, Audit, and Compliance Review</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            We may monitor usage of the services for security, fraud prevention,
                            compliance, billing, abuse prevention, and operational purposes. You agree
                            that we may review your account, API activity, request patterns,
                            verification volume, use case, consent process, and documentation to
                            ensure compliance with these Terms. You must cooperate with reasonable
                            compliance requests, including requests for updated KYC documents,
                            evidence of consent, privacy notices, regulatory licences, customer flow
                            screenshots, or explanation of unusual usage.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">17. Data Retention</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            We may retain account records, KYC records, billing records, verification
                            logs, request metadata, audit logs, consent confirmations, and compliance
                            records for as long as necessary for legal, regulatory, security, audit,
                            dispute resolution, fraud prevention, and business purposes. You are
                            responsible for implementing your own lawful data retention and deletion
                            policy for End-user data processed through your own systems.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">18. Confidentiality</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            Each party may receive confidential information from the other party.
                            Confidential information must be protected and must not be disclosed
                            except as required to provide the services, comply with law, enforce these
                            Terms, or with the disclosing party's consent. API keys, pricing, technical
                            documentation, provider information, non-public business information,
                            security procedures, and platform access details are confidential.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">19. Intellectual Property</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            EaseVerifier and its licensors retain all rights, title, and interest in
                            the platform, APIs, dashboard, documentation, software, trademarks, logos,
                            designs, workflows, and related intellectual property. You are granted a
                            limited, non-exclusive, non-transferable, revocable right to use the
                            services only in accordance with these Terms.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">20. Suspension and Termination</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            We may suspend, restrict, or terminate your account or API access
                            immediately if:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>You breach these Terms</li>
                            <li>You fail KYC, compliance, or risk review</li>
                            <li>You submit unauthorized or suspicious verification requests</li>
                            <li>You fail to obtain End-user consent</li>
                            <li>Your use may expose us, our providers, or End-users to legal, regulatory, security, or reputational risk</li>
                            <li>Required provider, government, or regulatory access is withdrawn or restricted</li>
                            <li>Payment fails or your wallet has insufficient balance</li>
                            <li>Your account is inactive, compromised, or used in an unauthorized manner</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">21. Indemnity</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            You agree to indemnify and hold EaseVerifier, its directors, officers,
                            employees, contractors, partners, and service providers harmless from any
                            claims, losses, damages, penalties, fines, costs, liabilities, and expenses
                            arising from your breach of these Terms, misuse of the services, failure
                            to obtain End-user consent, violation of data protection laws,
                            unauthorized disclosure or misuse of Verification Data, or use of
                            verification results in your own decisions or services.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">22. Limitation of Liability</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            To the maximum extent permitted by law, EaseVerifier shall not be liable
                            for indirect, incidental, special, punitive, exemplary, or consequential
                            damages, including loss of profit, revenue, goodwill, data, business
                            opportunity, or expected savings. Our total liability for any claim arising
                            from or relating to the services shall not exceed the amount paid by you to
                            EaseVerifier for the affected services in the twelve months immediately
                            preceding the claim.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">23. No Legal, Financial, or Compliance Advice</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            The services are provided as verification tools only. We do not provide
                            legal, financial, regulatory, compliance, credit, employment, or risk
                            decision advice. You are responsible for obtaining professional advice and
                            ensuring that your use of the services complies with applicable laws and
                            industry requirements.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">24. Changes to These Terms</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            We may update these Terms from time to time. Where changes are material,
                            we will make reasonable efforts to notify you through the platform, email,
                            or other appropriate means. Your continued use of the services after the
                            effective date of updated Terms means you accept the updated Terms.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">25. Governing Law and Dispute Resolution</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            These Terms shall be governed by the laws of the Federal Republic of
                            Nigeria. The parties shall first attempt to resolve disputes amicably. If a
                            dispute cannot be resolved amicably, it shall be submitted to the
                            competent courts of Nigeria, subject to any dispute resolution process
                            agreed by the parties in writing.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">26. Contact</h2>
                        <p class="text-body-1 text-grey-darken-1">
                            For questions about these Terms, compliance, privacy, or legal matters,
                            contact us at
                            <a href="mailto:legal@verify.ashlabtech.ng" class="text-primary">
                                legal@verify.ashlabtech.ng
                            </a>.
                        </p>
                    </v-card>
                </v-container>
            </section>
        </v-main>
    </v-app>
</template>

<style scoped>
.hero-section {
    background: linear-gradient(135deg, #1c6434 0%, #0d3d1f 100%);
}

ul {
    padding-left: 1.2rem;
}

li {
    margin-bottom: 0.35rem;
}
</style>
```
