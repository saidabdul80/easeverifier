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

interface ResultPinProduct {
    id: number;
    name: string;
    board?: string | null;
    price: number | string;
}

const structuredData = {
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    name: 'EaseVerifier',
    applicationCategory: 'BusinessApplication',
    operatingSystem: 'Web',
    description: 'Identity verification API for Nigerian businesses - verify NIN, BVN, CAC and Result PINs records instantly',
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
        resultPinProducts?: ResultPinProduct[];
    }>(),
    {
        canRegister: true,
        hottestPosts: () => [],
        resultPinProducts: () => [],
    },
);

const servicesList = [
    { icon: 'mdi-card-account-details', title: 'NIN', description: 'Fast identity checks for onboarding.', accent: 'service-nin' },
    { icon: 'mdi-bank', title: 'BVN', description: 'Bank-grade verification for financial flows.', accent: 'service-bvn' },
    { icon: 'mdi-domain', title: 'CAC', description: 'Business record checks for company validation.', accent: 'service-cac' },
    { icon: 'mdi-card-account-details-star-outline', title: 'Result PINs', description: 'Buy exam checker PINs without creating an account.', accent: 'service-pin', href: '/result-pins' },
];

const workflow = [
    { step: '01', title: 'Create account' },
    { step: '02', title: 'Fund wallet' },
    { step: '03', title: 'Verify instantly' },
];

const featuredPost = computed(() => props.hottestPosts[0] ?? null);
const secondaryPosts = computed(() => props.hottestPosts.slice(1, 3));
const fallbackPinProducts = [
    { id: 0, name: 'WAEC', board: 'waec', price: null },
    { id: 0, name: 'NECO', board: 'neco', price: null },
    { id: 0, name: 'NABTEB', board: 'nabteb', price: null },
    { id: 0, name: 'NBAIS', board: 'nbais', price: null },
];

const formatPinBoardLabel = (product: Pick<ResultPinProduct, 'name' | 'board'>) => {
    const source = (product.board || product.name).toLowerCase();
    const knownBoards = ['waec', 'neco', 'nabteb', 'nbais'];
    const matchedBoard = knownBoards.find((board) => source.includes(board));

    if (matchedBoard) {
        return matchedBoard.toUpperCase();
    }

    return product.name
        .replace(/scratch\s*card|result\s*checker|pin|token/gi, '')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .join(' ')
        .toUpperCase();
};

const pinBoardCards = computed(() =>
    (props.resultPinProducts.length ? props.resultPinProducts : fallbackPinProducts).map((product) => ({
        id: product.id,
        label: formatPinBoardLabel(product),
        price: product.price,
        href: product.id ? `/result-pins?product=${product.id}` : '/result-pins',
    })),
);

const formatCurrency = (amount: number | string | null) => {
    if (amount === null) {
        return 'Available';
    }

    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(Number(amount) || 0);
};

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
        <meta name="twitter:description" content="Instant identity verification API for Nigerian businesses. Verify NIN, BVN, CAC and Result PINs in milliseconds." />
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
                    <v-row align="center" class="ga-0 hero-row">
                        <v-col cols="12" lg="7">
                            <div class="hero-badge">
                                <v-icon size="16">mdi-lightning-bolt</v-icon>
                                NIN API verification + result PINs
                            </div>
                            <h1 class="hero-title">
                                Verify NIN API.<br>
                                Buy <span>result PINs.</span>
                            </h1>
                            <p class="hero-copy">
                                NIN, BVN and CAC checks for teams, plus quick exam PIN purchase for everyone.
                            </p>

                            <div class="d-flex flex-wrap ga-3">
                                <Link href="/result-pins">
                                    <v-btn color="secondary" size="x-large" class="cta-primary">
                                        Buy Result PINs
                                    </v-btn>
                                </Link>
                                <Link v-if="canRegister" :href="register()">
                                    <v-btn variant="outlined" color="white" size="x-large" class="cta-secondary">
                                        Create Account
                                    </v-btn>
                                </Link>
                            </div>

                        </v-col>

                        <v-col cols="12" lg="5">
                            <div class="hero-panel">
                                <div class="pin-showcase">
                                    <div class="pin-showcase-head">
                                        <div>
                                            <span>No dashboard required</span>
                                            <strong>PIN purchase</strong>
                                        </div>
                                        <v-icon color="secondary" size="34">mdi-ticket-confirmation</v-icon>
                                    </div>

                                    <div class="pin-board-row">
                                        <Link
                                            v-for="product in pinBoardCards"
                                            :key="`${product.id}-${product.label}`"
                                            :href="product.href"
                                            class="pin-board-card"
                                        >
                                            <span class="pin-board-name">{{ product.label }}</span>
                                            <span class="pin-board-price">{{ formatCurrency(product.price) }}</span>
                                        </Link>
                                    </div>

                                    <div class="pin-delivery-note">
                                        <v-icon color="secondary" size="20">mdi-email-fast-outline</v-icon>
                                        <span>Delivered to your email after payment.</span>
                                    </div>

                                    <Link href="/result-pins" class="text-decoration-none">
                                        <v-btn color="secondary" block size="large" class="cta-primary mt-4">Buy PIN Now</v-btn>
                                    </Link>
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
                        <h2 class="section-title">Verification and PINs, one flow.</h2>
                    </div>

                    <v-row>
                        <v-col v-for="service in servicesList" :key="service.title" cols="12" sm="6" md="3">
                            <Link :href="service.href || servicesPage()" class="text-decoration-none">
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

.hero-row {
    row-gap: 2rem;
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
    font-size: clamp(2.5rem, 5.4vw, 4.55rem);
    line-height: 0.98;
    letter-spacing: -0.05em;
    max-width: 13ch;
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

.hero-panel {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(244, 199, 76, 0.22);
    border-radius: 36px;
    background:
        radial-gradient(circle at 92% 8%, rgba(244, 199, 76, 0.24), transparent 30%),
        linear-gradient(145deg, rgba(13, 60, 31, 0.78), rgba(4, 39, 19, 0.92));
    backdrop-filter: blur(18px);
    box-shadow: 0 34px 80px rgba(3, 18, 9, 0.34), inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.pin-showcase {
    padding: 1.35rem;
}

.pin-showcase-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}

.pin-showcase-head .v-icon {
    flex: 0 0 auto;
    margin-top: 0.25rem;
}

.pin-showcase-head span {
    display: block;
    color: rgba(255, 255, 255, 0.58);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.pin-showcase-head strong {
    display: block;
    color: #fff;
    font-size: clamp(1.45rem, 2.8vw, 1.9rem);
    line-height: 1.02;
    letter-spacing: -0.03em;
}

.pin-board-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
    max-height: 226px;
    padding-right: 0.2rem;
    margin-bottom: 1rem;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(244, 199, 76, 0.4) transparent;
}

.pin-board-row::-webkit-scrollbar {
    width: 4px;
}

.pin-board-row::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(244, 199, 76, 0.4);
}

.pin-board-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
    min-width: 0;
    min-height: 66px;
    padding: 0.78rem 0.85rem;
    border: 1px solid rgba(244, 199, 76, 0.12);
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.085);
    text-decoration: none;
    transition: transform 0.22s ease, background 0.22s ease, border-color 0.22s ease;
}

.pin-board-card:hover {
    border-color: rgba(244, 199, 76, 0.35);
    background: rgba(255, 255, 255, 0.13);
    transform: translateY(-2px);
}

.pin-board-name {
    overflow: hidden;
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.74rem;
    font-weight: 800;
    line-height: 1.1;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pin-board-price {
    flex: 0 0 auto;
    color: #f4c74c;
    font-size: 1.02rem;
    font-weight: 900;
    line-height: 1.1;
    white-space: nowrap;
}

.pin-delivery-note {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.8rem 0.9rem;
    border-radius: 18px;
    background: rgba(244, 199, 76, 0.12);
    color: rgba(255, 255, 255, 0.84);
    font-size: 0.86rem;
    font-weight: 800;
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
.service-pin { background: linear-gradient(135deg, #53389e, #8b5cf6); }

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

@media (min-width: 960px) and (max-width: 1279px) {
    .hero-title {
        max-width: 14ch;
    }

    .hero-copy {
        max-width: 38rem;
    }

    .hero-panel {
        max-width: 620px;
        margin: 1.5rem auto 0;
    }
}

@media (max-width: 600px) {
    .hero-title {
        font-size: clamp(2.35rem, 13vw, 3.4rem);
    }

    .hero-copy {
        font-size: 0.98rem;
    }

    .pin-showcase {
        padding: 1rem;
    }

    .pin-board-row {
        max-height: 230px;
    }

    .pin-board-card {
        min-height: 58px;
        padding: 0.68rem;
    }

    .pin-delivery-note {
        align-items: flex-start;
        font-size: 0.8rem;
    }
}
</style>
