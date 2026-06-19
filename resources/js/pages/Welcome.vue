<script setup lang="ts">
import PublicTopNav from '@/components/PublicTopNav.vue';
import { about, blog, contact, cookies, documentation, pricing, privacy, register, services as servicesPage, terms } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';

interface Post {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    category: string;
    published_at: string;
    views: number;
}

const structuredData = {
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    name: 'EaseVerifier',
    applicationCategory: 'BusinessApplication',
    operatingSystem: 'Web',
    description: 'Identity verification API for Nigerian businesses - verify NIN, BVN, and CAC records instantly',
    offers: { '@type': 'Offer', price: '0', priceCurrency: 'NGN' },
    aggregateRating: { '@type': 'AggregateRating', ratingValue: '4.8', ratingCount: '500' },
    provider: {
        '@type': 'Organization',
        name: 'EaseVerifier',
        url: 'https://verify.ashlabtech.ng',
        logo: 'https://verify.ashlabtech.ng/images/logo.png',
        sameAs: ['https://twitter.com/easeverifier', 'https://linkedin.com/company/easeverifier'],
    },
};

let jsonLdScript: HTMLScriptElement | null = null;
const adsenseScriptSelector = 'script[data-adsense-loader="true"]';

onMounted(() => {
    jsonLdScript = document.createElement('script');
    jsonLdScript.type = 'application/ld+json';
    jsonLdScript.textContent = JSON.stringify(structuredData);
    document.head.appendChild(jsonLdScript);

    if (!document.head.querySelector(adsenseScriptSelector)) {
        const adsenseScript = document.createElement('script');
        adsenseScript.async = true;
        adsenseScript.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5615909705062666';
        adsenseScript.crossOrigin = 'anonymous';
        adsenseScript.dataset.adsenseLoader = 'true';
        document.head.appendChild(adsenseScript);
    }
});

onUnmounted(() => {
    if (jsonLdScript?.parentNode) {
        jsonLdScript.parentNode.removeChild(jsonLdScript);
    }
});

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        hottestPosts?: Post[];
    }>(),
    {
        canRegister: true,
        hottestPosts: () => [],
    },
);

const servicesList = [
    { icon: 'mdi-card-account-details', title: 'NIN', description: 'Fast identity checks for onboarding.', accent: 'service-nin' },
    { icon: 'mdi-bank', title: 'BVN', description: 'Bank-grade verification for financial flows.', accent: 'service-bvn' },
    { icon: 'mdi-domain', title: 'CAC', description: 'Business record checks for company validation.', accent: 'service-cac' },
];

const quickStats = [
    { value: '100K+', label: 'Checks run' },
    { value: '60+', label: 'Teams onboarded' },
    { value: '<1s', label: 'Average response' },
];

const workflow = [
    { step: '01', title: 'Create account' },
    { step: '02', title: 'Fund wallet' },
    { step: '03', title: 'Verify instantly' },
];

const featuredPost = computed(() => props.hottestPosts[0] ?? null);
const secondaryPosts = computed(() => props.hottestPosts.slice(1, 3));

const getCategoryIcon = (category: string): string => {
    const icons: Record<string, string> = {
        Education: 'mdi-school',
        Compliance: 'mdi-clipboard-check',
        Technical: 'mdi-api',
        Security: 'mdi-shield-lock',
        News: 'mdi-newspaper',
        Updates: 'mdi-update',
        Guides: 'mdi-book-open-page-variant',
    };

    return icons[category] || 'mdi-post';
};

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('en-NG', { year: 'numeric', month: 'short', day: 'numeric' });
</script>

<template>
    <Head title="NIN, BVN & CAC Verification API for Nigerian Businesses | EaseVerifier">
        <meta name="description" content="EaseVerifier provides instant NIN, BVN, and CAC verification API services for Nigerian businesses. Verify identities in milliseconds with 99.9% uptime. Trusted by 500+ companies." />
        <meta name="keywords" content="NIN verification, BVN verification, CAC verification, identity verification Nigeria, KYC Nigeria, verification API, Nigerian identity verification, business verification" />
        <meta name="robots" content="index, follow" />
        <meta name="author" content="EaseVerifier" />
        <link rel="canonical" href="https://verify.ashlabtech.ng" />
        <meta property="og:type" content="website" />
        <meta property="og:url" content="https://verify.ashlabtech.ng/" />
        <meta property="og:title" content="NIN, BVN & CAC Verification API for Nigerian Businesses | EaseVerifier" />
        <meta property="og:description" content="Instant NIN, BVN, and CAC verification API. Verify identities in milliseconds with 99.9% uptime. Trusted by 500+ Nigerian businesses." />
        <meta property="og:image" content="https://verify.ashlabtech.ng/images/og-image.png" />
        <meta property="og:site_name" content="EaseVerifier" />
        <meta property="og:locale" content="en_NG" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:url" content="https://verify.ashlabtech.ng/" />
        <meta name="twitter:title" content="NIN, BVN & CAC Verification API | EaseVerifier" />
        <meta name="twitter:description" content="Instant identity verification API for Nigerian businesses. Verify NIN, BVN, CAC in milliseconds." />
        <meta name="twitter:image" content="https://verify.ashlabtech.ng/images/twitter-card.png" />
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <v-app class="public-app">
        <PublicTopNav current="home" :can-register="canRegister" />

        <v-main>
            <section class="hero-shell">
                <div class="hero-glow hero-glow-a" />
                <div class="hero-glow hero-glow-b" />
                <v-container class="hero-content">
                    <v-row align="center" class="ga-0">
                        <v-col cols="12" md="7">
                            <div class="hero-badge">
                                <v-icon size="16">mdi-lightning-bolt</v-icon>
                                API-first identity checks
                            </div>
                            <h1 class="hero-title">
                                Verify <span style="font-size: 3rem;">NIN, BVN, CAC</span><br>
                                without slowing the user down.
                            </h1>
                            <p class="hero-copy">
                                Fast checks, clean onboarding, simple pricing.
                            </p>

                            <div class="d-flex flex-wrap ga-3 mb-8">
                                <Link v-if="canRegister" :href="register()">
                                    <v-btn color="secondary" size="x-large" class="cta-primary">
                                        Start Free Trial
                                    </v-btn>
                                </Link>
                                <Link :href="documentation()">
                                    <v-btn variant="outlined" color="white" size="x-large" class="cta-secondary">View Docs</v-btn>
                                </Link>
                            </div>

                            <div class="stats-strip">
                                <div v-for="stat in quickStats" :key="stat.label" class="stat-pill">
                                    <div class="stat-value">{{ stat.value }}</div>
                                    <div class="stat-label">{{ stat.label }}</div>
                                </div>
                            </div>
                        </v-col>

                        <v-col cols="12" md="5">
                            <div class="hero-panel">
                                <div class="hero-panel-top">
                                    <div class="panel-dot red" />
                                    <div class="panel-dot amber" />
                                    <div class="panel-dot green" />
                                </div>
                                <div class="hero-panel-body">
                                    <div class="request-chip">POST /api/v1/verify/nin</div>
                                    <div class="code-card">
                                        <div class="code-line"><span class="code-key">status</span>: <span class="code-value">"verified"</span></div>
                                        <div class="code-line"><span class="code-key">match</span>: <span class="code-value">true</span></div>
                                        <div class="code-line"><span class="code-key">latency</span>: <span class="code-value">"0.7s"</span></div>
                                    </div>
                                    <div class="trust-grid">
                                        <div class="trust-card">
                                            <div class="trust-number">99.9%</div>
                                            <div class="trust-label">Uptime</div>
                                        </div>
                                        <div class="trust-card">
                                            <div class="trust-number">Live</div>
                                            <div class="trust-label">Wallet billing</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <section class="section-shell">
                <v-container>
                    <div class="section-head">
                        <div class="section-kicker">Core Services</div>
                        <h2 class="section-title">Three checks. One clean flow.</h2>
                    </div>

                    <v-row>
                        <v-col v-for="service in servicesList" :key="service.title" cols="12" md="4">
                            <Link :href="servicesPage()" class="text-decoration-none">
                                <v-card class="service-card" hover>
                                    <v-card-text class="pa-6">
                                        <div class="service-icon" :class="service.accent">
                                            <v-icon color="white" size="26">{{ service.icon }}</v-icon>
                                        </div>
                                        <h3 class="text-h6 font-weight-bold text-grey-darken-4 mb-2">{{ service.title }}</h3>
                                        <p class="text-body-2 text-grey-darken-1 mb-0">{{ service.description }}</p>
                                    </v-card-text>
                                </v-card>
                            </Link>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <section class="section-shell section-soft">
                <v-container>
                    <div class="section-head d-flex flex-column flex-md-row align-md-center justify-space-between ga-4">
                        <div>
                            <div class="section-kicker">How It Works</div>
                            <h2 class="section-title">Built for speed.</h2>
                        </div>
                        <Link :href="pricing()">
                            <v-btn variant="text" color="primary">
                                See pricing
                                <v-icon end>mdi-arrow-right</v-icon>
                            </v-btn>
                        </Link>
                    </div>

                    <v-row>
                        <v-col v-for="item in workflow" :key="item.step" cols="12" md="4">
                            <div class="workflow-card">
                                <div class="workflow-step">{{ item.step }}</div>
                                <div class="workflow-title">{{ item.title }}</div>
                            </div>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <section v-if="featuredPost" class="section-shell">
                <v-container>
                    <div class="section-head d-flex flex-column flex-md-row align-md-center justify-space-between ga-4">
                        <div>
                            <div class="section-kicker">Hot Right Now</div>
                            <h2 class="section-title">Useful posts without the noise.</h2>
                        </div>
                        <Link :href="blog()">
                            <v-btn variant="outlined" color="primary">Open Blog</v-btn>
                        </Link>
                    </div>

                    <v-row>
                        <v-col cols="12" md="7">
                            <Link :href="`/blog/${featuredPost.slug}`" class="text-decoration-none">
                                <v-card class="feature-post" hover>
                                    <v-card-text class="pa-7">
                                        <div class="d-flex align-center justify-space-between mb-4">
                                            <v-chip size="small" color="secondary" variant="flat">{{ featuredPost.category }}</v-chip>
                                            <span class="text-caption text-grey-darken-1">
                                                {{ featuredPost.views.toLocaleString() }} views
                                            </span>
                                        </div>
                                        <div class="feature-post-icon">
                                            <v-icon color="primary" size="30">{{ getCategoryIcon(featuredPost.category) }}</v-icon>
                                        </div>
                                        <h3 class="text-h5 font-weight-bold text-grey-darken-4 mb-3">{{ featuredPost.title }}</h3>
                                        <p class="text-body-1 text-grey-darken-1 mb-5">{{ featuredPost.excerpt }}</p>
                                        <div class="d-flex align-center justify-space-between">
                                            <span class="text-caption text-grey">{{ formatDate(featuredPost.published_at) }}</span>
                                            <span class="text-primary font-weight-medium">Read post</span>
                                        </div>
                                    </v-card-text>
                                </v-card>
                            </Link>
                        </v-col>

                        <v-col cols="12" md="5">
                            <div class="stack-list">
                                <Link
                                    v-for="post in secondaryPosts"
                                    :key="post.id"
                                    :href="`/blog/${post.slug}`"
                                    class="text-decoration-none"
                                >
                                    <v-card class="stack-card" hover>
                                        <v-card-text class="pa-5">
                                            <div class="d-flex align-center justify-space-between mb-3">
                                                <v-chip size="small" variant="outlined" color="primary">{{ post.category }}</v-chip>
                                                <span class="text-caption text-grey">{{ formatDate(post.published_at) }}</span>
                                            </div>
                                            <p class="font-weight-bold text-grey-darken-4 mb-1">{{ post.title }}</p>
                                            <p class="text-body-2 text-grey-darken-1 mb-0">{{ post.excerpt }}</p>
                                        </v-card-text>
                                    </v-card>
                                </Link>
                            </div>
                        </v-col>
                    </v-row>
                </v-container>
            </section>

            <section class="cta-shell">
                <v-container>
                    <div class="cta-band">
                        <div>
                            <div class="section-kicker text-white text-opacity-80">Ready</div>
                            <h2 class="cta-title">Start verifying in minutes.</h2>
                        </div>
                        <Link v-if="canRegister" :href="register()">
                            <v-btn color="secondary" size="large" class="cta-primary">Create Account</v-btn>
                        </Link>
                    </div>
                </v-container>
            </section>

            <v-footer class="site-footer">
                <v-container>
                    <v-row class="py-2">
                        <v-col cols="12" md="4">
                            <div class="d-flex align-center mb-3">
                                <v-avatar color="white" size="34" class="mr-3">
                                    <img src="/ashlabtech.png" alt="EaseVerifier" style="width: 100%; height: 100%; object-fit: contain;" />
                                </v-avatar>
                                <span class="text-subtitle-1 font-weight-bold text-white">EaseVerifier</span>
                            </div>
                            <p class="text-body-2 text-white text-opacity-70 mb-0">Verification for Nigerian products.</p>
                        </v-col>
                        <v-col cols="6" md="2">
                            <Link :href="servicesPage()" class="footer-link">Services</Link>
                            <Link :href="pricing()" class="footer-link">Pricing</Link>
                            <Link :href="documentation()" class="footer-link">Docs</Link>
                        </v-col>
                        <v-col cols="6" md="2">
                            <Link :href="about()" class="footer-link">About</Link>
                            <Link :href="blog()" class="footer-link">Blog</Link>
                            <Link :href="contact()" class="footer-link">Contact</Link>
                        </v-col>
                        <v-col cols="6" md="2">
                            <Link :href="privacy()" class="footer-link">Privacy</Link>
                            <Link :href="terms()" class="footer-link">Terms</Link>
                            <Link :href="cookies()" class="footer-link">Cookies</Link>
                        </v-col>
                    </v-row>
                </v-container>
            </v-footer>
        </v-main>
    </v-app>
</template>

<style scoped>
.public-app {
    background:
        radial-gradient(circle at top left, rgba(111, 211, 153, 0.18), transparent 32%),
        linear-gradient(180deg, #f6fbf7 0%, #eef6f0 100%);
}

.hero-shell {
    position: relative;
    overflow: hidden;
    padding: 7rem 0 4.5rem;
    background: linear-gradient(135deg, #0d3a1c 0%, #12542a 52%, #1b7a41 100%);
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-glow {
    position: absolute;
    border-radius: 999px;
    filter: blur(6px);
    opacity: 0.5;
}

.hero-glow-a {
    top: 80px;
    right: -120px;
    width: 340px;
    height: 340px;
    background: rgba(244, 199, 76, 0.24);
}

.hero-glow-b {
    left: -100px;
    bottom: -80px;
    width: 260px;
    height: 260px;
    background: rgba(255, 255, 255, 0.08);
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.9rem;
    margin-bottom: 1.3rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.92);
    font-size: 0.82rem;
    backdrop-filter: blur(14px);
}

.hero-title {
    margin: 0 0 1rem;
    color: #fff;
    font-size: clamp(2.7rem, 6vw, 4.8rem);
    line-height: 0.98;
    letter-spacing: -0.05em;
    max-width: 11ch;
}

.hero-title span {
    color: #f4c74c;
}

.hero-copy {
    max-width: 32rem;
    margin-bottom: 2rem;
    color: rgba(255, 255, 255, 0.76);
    font-size: 1.05rem;
}

.cta-primary,
.cta-secondary {
    border-radius: 999px;
    text-transform: none;
    font-weight: 700;
}

.stats-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.stat-pill {
    min-width: 140px;
    padding: 1rem 1.1rem;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
}

.stat-value {
    color: #fff;
    font-size: 1.25rem;
    font-weight: 700;
}

.stat-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.82rem;
}

.hero-panel {
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 30px;
    background: rgba(8, 26, 15, 0.44);
    backdrop-filter: blur(18px);
    box-shadow: 0 32px 70px rgba(3, 18, 9, 0.28);
}

.hero-panel-top {
    display: flex;
    gap: 0.45rem;
    padding: 1rem 1.1rem 0;
}

.panel-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
}

.red { background: #ff6b6b; }
.amber { background: #f4c74c; }
.green { background: #51cf66; }

.hero-panel-body {
    padding: 1.2rem;
}

.request-chip {
    display: inline-block;
    padding: 0.5rem 0.8rem;
    margin-bottom: 1rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: #dcefe2;
    font-size: 0.82rem;
}

.code-card {
    padding: 1.2rem;
    margin-bottom: 1rem;
    border-radius: 24px;
    background: #082413;
}

.code-line + .code-line {
    margin-top: 0.75rem;
}

.code-key {
    color: #9fe0b7;
}

.code-value {
    color: #fff1a0;
}

.trust-grid {
    display: grid;
    gap: 0.9rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.trust-card {
    padding: 1rem;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.08);
}

.trust-number {
    color: #fff;
    font-weight: 700;
}

.trust-label {
    color: rgba(255, 255, 255, 0.66);
    font-size: 0.82rem;
}

.section-shell {
    padding: 4.75rem 0;
}

.section-soft {
    background: rgba(255, 255, 255, 0.52);
}

.section-head {
    margin-bottom: 2rem;
}

.section-kicker {
    margin-bottom: 0.5rem;
    color: #1a7d42;
    font-size: 0.76rem;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.section-title {
    margin: 0;
    color: #13261c;
    font-size: clamp(1.75rem, 3.6vw, 2.8rem);
    line-height: 1.04;
    letter-spacing: -0.04em;
}

.service-card {
    height: 100%;
    border: 1px solid rgba(23, 83, 44, 0.08);
    border-radius: 26px;
    background: rgba(255, 255, 255, 0.82);
    box-shadow: 0 18px 40px rgba(22, 63, 37, 0.05);
    transition: transform 0.28s ease, box-shadow 0.28s ease;
}

.service-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 28px 60px rgba(22, 63, 37, 0.09);
}

.service-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    margin-bottom: 1.25rem;
    border-radius: 18px;
}

.service-nin { background: linear-gradient(135deg, #1a7d42, #26a269); }
.service-bvn { background: linear-gradient(135deg, #d18f10, #f4c74c); }
.service-cac { background: linear-gradient(135deg, #145c9e, #2f85de); }

.workflow-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.35rem 1.4rem;
    border: 1px solid rgba(19, 53, 30, 0.08);
    border-radius: 24px;
    background: #fff;
}

.workflow-step {
    color: #1a7d42;
    font-size: 1.2rem;
    font-weight: 800;
}

.workflow-title {
    color: #13261c;
    font-weight: 700;
}

.feature-post,
.stack-card {
    border: 1px solid rgba(23, 83, 44, 0.08);
    border-radius: 28px;
    background: rgba(255, 255, 255, 0.88);
    box-shadow: 0 20px 40px rgba(22, 63, 37, 0.05);
}

.feature-post-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 62px;
    height: 62px;
    margin-bottom: 1.25rem;
    border-radius: 20px;
    background: #eef7f0;
}

.stack-list {
    display: grid;
    gap: 1rem;
}

.cta-shell {
    padding: 0 0 4.5rem;
}

.cta-band {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 2rem;
    border-radius: 30px;
    background: linear-gradient(135deg, #0f3e20 0%, #196b37 100%);
}

.cta-title {
    margin: 0;
    color: #fff;
    font-size: clamp(1.8rem, 4vw, 2.8rem);
    line-height: 1;
    letter-spacing: -0.04em;
}

.site-footer {
    background: #102319;
    color: #fff;
}

.footer-link {
    display: block;
    margin-bottom: 0.55rem;
    color: rgba(255, 255, 255, 0.74);
    text-decoration: none;
    transition: color 0.2s ease;
}

.footer-link:hover {
    color: #f4c74c;
}

@media (max-width: 960px) {
    .hero-shell {
        padding-top: 6rem;
    }

    .hero-title {
        max-width: none;
    }

    .hero-panel {
        margin-top: 2rem;
    }

    .cta-band {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
