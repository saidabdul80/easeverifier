```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register } from '@/routes';
</script>

<template>
    <Head title="Privacy Policy - Data Protection & Security | EaseVerifier">
        <meta
            name="description"
            content="EaseVerifier's privacy policy explains how we collect, use, protect, and process data for identity verification services. EaseVerifier does not retain submitted verification data after processing."
        />
        <meta
            name="keywords"
            content="privacy policy, data protection Nigeria, NDPA compliance, NDPR compliance, identity verification privacy, NIN verification privacy, BVN verification privacy, CAC verification privacy, data security"
        />
        <meta property="og:title" content="Privacy Policy - EaseVerifier" />
        <meta
            property="og:description"
            content="Learn how EaseVerifier protects data and processes verification requests securely without retaining submitted verification data."
        />
        <meta property="og:type" content="article" />
        <link rel="canonical" href="https://verify.ashlabtech.ng/privacy" />
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
                    <h1 class="text-h3 font-weight-bold text-white mb-2">Privacy Policy</h1>
                    <p class="text-body-1 text-white opacity-80">Last updated: June 19, 2026</p>
                </v-container>
            </section>

            <section class="py-12">
                <v-container>
                    <v-card class="pa-8 mx-auto" max-width="950">
                        <p class="text-body-1 text-grey-darken-1 mb-8">
                            This Privacy Policy explains how EaseVerifier collects, uses, processes,
                            protects, and handles personal data when you access our website, create an
                            account, use our dashboard, connect to our APIs, or use our identity
                            verification services. EaseVerifier is committed to handling personal data
                            responsibly, securely, and in accordance with applicable Nigerian data
                            protection laws.
                        </p>

                        <v-alert
                            type="info"
                            variant="tonal"
                            color="primary"
                            class="mb-8"
                        >
                            <strong>Important:</strong>
                            EaseVerifier does not retain submitted verification data after processing
                            a verification request. We only retain limited account, billing,
                            transaction, security, compliance, and audit records necessary to operate
                            the platform, prevent abuse, resolve disputes, and meet legal or
                            regulatory obligations.
                        </v-alert>

                        <h2 class="text-h5 font-weight-bold mb-4">1. Who This Policy Applies To</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            This Policy applies to customers who register on EaseVerifier, businesses
                            that use our API or dashboard, authorized users of customer accounts,
                            visitors to our website, and end-users whose information is submitted by
                            our customers for verification. Where a customer submits an end-user's
                            data for verification, the customer is responsible for obtaining the
                            required consent and providing the required privacy notice to that
                            end-user.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">2. Information We Collect From Customers</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            When you register for an account or apply for access to our services, we
                            may collect:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Full name</li>
                            <li>Email address</li>
                            <li>Phone number</li>
                            <li>Password or authentication credentials</li>
                            <li>Business or company name</li>
                            <li>Business address and contact details</li>
                            <li>CAC registration number and business documents where applicable</li>
                            <li>TIN or other business identification information where applicable</li>
                            <li>Representative name, role, contact details, and authorization documents</li>
                            <li>Use case, business category, expected API volume, and compliance information</li>
                            <li>Payment, wallet, invoice, and billing-related information</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">3. KYC and Compliance Information</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            Because EaseVerifier provides identity verification gateway services, we
                            may collect and review KYC and compliance information from customers
                            before granting production access. This may include individual identity
                            details, business registration documents, director or representative
                            details, authorization letters, regulatory licence information where
                            applicable, consent-flow information, privacy policy URL, and other
                            documents required for risk assessment, fraud prevention, and compliance
                            review.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">4. Verification Data Submitted Through Our Services</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-4">
                            Customers may submit verification data through our API or dashboard for
                            services such as NIN, BVN, CAC, or other identity and business
                            verification checks. This may include identity numbers, names, dates of
                            birth, phone numbers, business registration numbers, or other information
                            required to process a verification request.
                        </p>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            EaseVerifier processes this verification data only for the purpose of
                            completing the requested verification. We do not retain the submitted
                            verification data after the request has been processed. We may retain
                            limited non-sensitive transaction metadata, such as request reference,
                            customer ID, endpoint used, request status, billing status, timestamp, and
                            error or response status, for audit, billing, security, abuse prevention,
                            and dispute-resolution purposes.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">5. Information We Collect Automatically</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            When you use our website, dashboard, or API, we may automatically collect:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>IP address</li>
                            <li>Device and browser information</li>
                            <li>Login history</li>
                            <li>API usage logs</li>
                            <li>Endpoint activity</li>
                            <li>Request timestamps</li>
                            <li>Error logs and system performance logs</li>
                            <li>Security events and suspicious activity indicators</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">6. How We Use Customer Information</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            We use customer information to:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Create and manage customer accounts</li>
                            <li>Verify customer identity and business information</li>
                            <li>Review and approve sandbox or production API access</li>
                            <li>Provide, maintain, secure, and improve our services</li>
                            <li>Process payments, wallet funding, billing, invoices, and refunds</li>
                            <li>Send technical notices, security alerts, and support messages</li>
                            <li>Respond to questions, complaints, and support requests</li>
                            <li>Monitor usage and prevent fraud, abuse, unauthorized access, or misuse</li>
                            <li>Comply with legal, regulatory, contractual, security, and audit obligations</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">7. How Verification Data Is Used</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            Verification data submitted through our services is used only to process
                            the specific verification request made by the customer. EaseVerifier does
                            not use submitted verification data to build a separate identity database,
                            resell personal data, train unrelated systems, market to end-users, or
                            create unrelated profiles. After processing, the submitted verification
                            data is not retained by EaseVerifier, except for limited transaction
                            metadata required for audit, billing, security, abuse prevention, and
                            dispute resolution.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">8. Customer Responsibility for Consent</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-4">
                            Customers are responsible for obtaining valid, informed, specific, and
                            lawful consent from each end-user before submitting that end-user's data
                            for verification through EaseVerifier.
                        </p>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            Customers must inform end-users about:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>The type of personal data being collected</li>
                            <li>The purpose of the verification</li>
                            <li>That the data may be submitted to EaseVerifier and relevant verification providers</li>
                            <li>How the verification result will be used</li>
                            <li>The customer's own privacy policy and data retention practices</li>
                            <li>Any rights available to the end-user under applicable data protection laws</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">9. Legal Basis for Processing</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            We process personal data only where there is a lawful basis to do so. This
                            may include:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Your consent or the consent obtained by our customer from an end-user</li>
                            <li>Performance of a contract with you</li>
                            <li>Compliance with legal or regulatory obligations</li>
                            <li>Legitimate interests, such as fraud prevention, security, abuse prevention, service improvement, and audit controls</li>
                            <li>Any other lawful basis permitted under applicable data protection laws</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">10. Data Sharing and Third-party Providers</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-4">
                            To provide our verification services, we may transmit verification requests
                            to authorized or relevant verification providers, government data sources,
                            licensed service providers, payment processors, hosting providers,
                            communication providers, security providers, professional advisers, or
                            other service providers that support our operations.
                        </p>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            We do not sell personal data. We only share data where necessary to provide
                            the requested service, operate the platform, comply with law, enforce our
                            terms, prevent fraud, resolve disputes, or protect our rights, users, and
                            systems.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">11. Data Retention</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-4">
                            EaseVerifier does not retain submitted verification data after processing
                            the verification request.
                        </p>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            However, we may retain limited records necessary for platform operation,
                            compliance, billing, security, and audit purposes, including:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Customer account records</li>
                            <li>KYC and onboarding records</li>
                            <li>Wallet, payment, invoice, and billing records</li>
                            <li>API request metadata, such as reference, status, endpoint, and timestamp</li>
                            <li>Security logs and access logs</li>
                            <li>Support messages and complaint records</li>
                            <li>Compliance review records</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">12. Security Measures</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            We implement reasonable technical and organizational measures designed to
                            protect personal data and platform access, including:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>HTTPS and encryption in transit</li>
                            <li>Access controls and authentication</li>
                            <li>API key management</li>
                            <li>Logging and monitoring</li>
                            <li>Rate limiting and abuse prevention controls</li>
                            <li>Credential protection and secure infrastructure practices</li>
                            <li>Administrative access restrictions</li>
                            <li>Periodic review of security and compliance controls</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">13. Customer Security Responsibilities</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            Customers are responsible for protecting their own account and integration.
                            You must:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Keep API keys, tokens, passwords, and webhook secrets confidential</li>
                            <li>Use secure server-side API calls</li>
                            <li>Use HTTPS on your own systems</li>
                            <li>Restrict access to authorized staff only</li>
                            <li>Notify us immediately if you suspect unauthorized access or credential compromise</li>
                            <li>Comply with applicable privacy and data protection laws in your own handling of end-user data</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">14. Cookies and Similar Technologies</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            We may use cookies and similar technologies to operate our website,
                            maintain sessions, improve user experience, remember preferences, secure
                            accounts, and understand website usage. You may control cookies through
                            your browser settings, but some features may not work properly if cookies
                            are disabled.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">15. International Data Transfers</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            Some of our service providers, infrastructure providers, or technical
                            systems may process data outside Nigeria. Where this occurs, we take
                            reasonable steps to ensure that appropriate safeguards are applied in
                            accordance with applicable data protection requirements.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">16. Your Data Protection Rights</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            Subject to applicable law and verification of your identity, you may have
                            the right to:
                        </p>
                        <ul class="text-body-1 text-grey-darken-1 mb-6 ml-4">
                            <li>Request access to your personal data</li>
                            <li>Request correction of inaccurate or incomplete data</li>
                            <li>Request deletion of your personal data where legally permitted</li>
                            <li>Object to certain processing activities</li>
                            <li>Withdraw consent where processing is based on consent</li>
                            <li>Request restriction of processing</li>
                            <li>Request data portability where applicable</li>
                            <li>Lodge a complaint with the appropriate data protection authority</li>
                        </ul>

                        <h2 class="text-h5 font-weight-bold mb-4">17. End-user Requests</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            If you are an end-user whose data was submitted for verification by one of
                            our customers, you should first contact that customer because they
                            determine why your data was collected and submitted. Where appropriate, we
                            may assist our customer in responding to lawful data protection requests.
                            Since EaseVerifier does not retain submitted verification data after
                            processing, we may only be able to reference limited transaction metadata
                            where available and legally permitted.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">18. Children's Data</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            Our services are intended for business and compliance use by authorized
                            customers. Customers must not submit children's personal data unless they
                            have the lawful authority and required consent to do so. Customers are
                            responsible for ensuring that any submission involving a minor complies
                            with applicable law.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">19. Data Breach and Incident Handling</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            If we become aware of a security incident affecting personal data under
                            our control, we will take reasonable steps to investigate, contain, and
                            remediate the incident. Where required by law, we will notify affected
                            customers, users, regulators, or other relevant parties.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">20. Changes to This Privacy Policy</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-6">
                            We may update this Privacy Policy from time to time to reflect changes in
                            our services, legal requirements, security practices, or business
                            operations. Where changes are material, we will make reasonable efforts to
                            notify users through the platform, email, or other appropriate means. Your
                            continued use of our services after the updated Policy becomes effective
                            means you accept the updated Policy.
                        </p>

                        <h2 class="text-h5 font-weight-bold mb-4">21. Contact Us</h2>
                        <p class="text-body-1 text-grey-darken-1 mb-2">
                            If you have questions, requests, complaints, or concerns about this
                            Privacy Policy or how we handle personal data, contact us at:
                        </p>
                        <p class="text-body-1 text-grey-darken-1">
                            <a href="mailto:privacy@verify.ashlabtech.ng" class="text-primary">
                                privacy@verify.ashlabtech.ng
                            </a>
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
