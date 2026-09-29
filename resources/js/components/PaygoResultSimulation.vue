<script setup lang="ts">
import {
    AlertCircle,
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Check,
    CheckCircle2,
    ChevronDown,
    CreditCard,
    FileSearch,
    GraduationCap,
    LockKeyhole,
    LoaderCircle,
    Pause,
    Play,
    RotateCcw,
    ScanSearch,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const stages = [
    { label: 'School portal', icon: GraduationCap, delay: 1800 },
    { label: 'Choose exam', icon: FileSearch, delay: 2200 },
    { label: 'Result details', icon: FileSearch, delay: 2600 },
    { label: 'Confirm', icon: CheckCircle2, delay: 2200 },
    { label: 'Payment', icon: CreditCard, delay: null },
    { label: 'Verification', icon: ScanSearch, delay: 2800 },
    { label: 'Result ready', icon: BadgeCheck, delay: null },
];
const journeyStages = [
    { label: 'School portal', icon: GraduationCap, scene: 0 },
    { label: 'EaseVerifier', icon: FileSearch, scene: 1 },
    { label: 'Paystack', icon: CreditCard, scene: 4 },
    { label: 'Result returned', icon: BadgeCheck, scene: 6 },
];

const examOptions = ['NABTEB', 'NBAIS', 'NECO-EVERIFY', 'NECO', 'WAEC'];
const resultFields = [
    ['Exam number', '1234567890'],
    ['Exam year', '2024'],
    ['Exam type', 'MAY/JUN'],
    ['Checker PIN', '********9012'],
    ['Card serial number', 'WRN123456789'],
    ['Email', 'amina@example.com'],
    ['Phone number', '08012345678'],
];
const stageUrls = [
    'https://portal.northfield.edu.ng/students/STU-12345/results',
    'https://easeverifier.com/paygo/results/customer/EVR-E2UPGRSD?candidate_id=STU-12345&portal_ref=APP-90210',
    'https://easeverifier.com/paygo/results/waec-result-verification?candidate_id=STU-12345&portal_ref=APP-90210',
    'https://easeverifier.com/paygo/results/waec-result-verification?candidate_id=STU-12345&portal_ref=APP-90210',
    'https://checkout.paystack.com/test/easeverifier-demo',
    'https://easeverifier.com/paygo/callback?reference=DEMO_PAYSTACK_REFERENCE',
    'https://portal.northfield.edu.ng/results/APP-90210',
];

const activeStage = ref(0);
const browserUrl = ref(stageUrls[0]);
const isPlaying = ref(false);
const checkoutLoading = ref(false);
const checkoutError = ref('');
const checkoutUrl = ref('');
const checkoutFrameUrl = ref('');
const paymentReference = ref('');
const paymentVerificationToken = ref('');
let stageTimer: ReturnType<typeof setTimeout> | null = null;
let paymentTimer: ReturnType<typeof setTimeout> | null = null;
let verificationAttempts = 0;

const journeyStep = computed(() => {
    if (activeStage.value === 0) return 0;
    if (activeStage.value <= 3) return 1;
    if (activeStage.value <= 5) return 2;

    return 3;
});
const progress = computed(() => (journeyStep.value / (journeyStages.length - 1)) * 100);

watch(activeStage, (stage) => {
    browserUrl.value = stageUrls[stage];
});

const clearStageTimer = () => {
    if (stageTimer) clearTimeout(stageTimer);
    stageTimer = null;
};

const clearPaymentTimer = () => {
    if (paymentTimer) clearTimeout(paymentTimer);
    paymentTimer = null;
};

const scheduleNextStage = () => {
    clearStageTimer();
    const delay = stages[activeStage.value]?.delay;

    if (!isPlaying.value || activeStage.value >= stages.length - 1 || delay === null) {
        isPlaying.value = false;
        return;
    }

    stageTimer = setTimeout(() => {
        activeStage.value += 1;

        if (activeStage.value === 4 && !checkoutUrl.value) {
            void initializePaystackCheckout();
        }

        scheduleNextStage();
    }, delay);
};

const play = () => {
    if (activeStage.value >= stages.length - 1) activeStage.value = 0;
    isPlaying.value = true;
    scheduleNextStage();
};

const pause = () => {
    isPlaying.value = false;
    clearStageTimer();
};

const resetPayment = () => {
    clearPaymentTimer();
    checkoutLoading.value = false;
    checkoutError.value = '';
    checkoutUrl.value = '';
    checkoutFrameUrl.value = '';
    paymentReference.value = '';
    paymentVerificationToken.value = '';
    verificationAttempts = 0;
};

const restart = () => {
    pause();
    resetPayment();
    activeStage.value = 0;
};

const selectStage = (index: number) => {
    pause();
    activeStage.value = index;

    if (index === 4 && !checkoutUrl.value && !checkoutLoading.value) {
        void initializePaystackCheckout();
    }
};

const continueFlow = (nextStage: number) => {
    pause();
    activeStage.value = nextStage;
    isPlaying.value = true;
    scheduleNextStage();
};

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';

const postJson = async (url: string, body: Record<string, string> = {}) => {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok && response.status !== 202) {
        throw new Error(payload.message || 'Unable to complete the Paystack test payment.');
    }

    return payload;
};

const verifyPayment = async () => {
    if (!paymentReference.value) return;

    try {
        const payment = await postJson('/paygo/demo/payment/verify', {
            reference: paymentReference.value,
            verification_token: paymentVerificationToken.value,
        });

        if (payment.complete) {
            clearPaymentTimer();
            activeStage.value = 5;
            isPlaying.value = true;
            scheduleNextStage();
            return;
        }

        verificationAttempts += 1;
        if (verificationAttempts < 25 && payment.status !== 'abandoned') {
            paymentTimer = setTimeout(verifyPayment, 4000);
        }
    } catch (error) {
        checkoutError.value = error instanceof Error ? error.message : 'Unable to verify the test payment.';
    }
};

const initializePaystackCheckout = async () => {
    pause();
    resetPayment();
    checkoutLoading.value = true;

    try {
        const payment = await postJson('/paygo/demo/payment/initialize');
        paymentReference.value = payment.reference;
        paymentVerificationToken.value = payment.verification_token;
        checkoutUrl.value = payment.checkout_url;
        checkoutFrameUrl.value = payment.frame_url;
        browserUrl.value = payment.checkout_url;
        paymentTimer = setTimeout(verifyPayment, 4000);
    } catch (error) {
        checkoutError.value = error instanceof Error ? error.message : 'Unable to open Paystack test checkout.';
    } finally {
        checkoutLoading.value = false;
    }
};

const goToPayment = () => {
    pause();
    activeStage.value = 4;
    void initializePaystackCheckout();
};

const handlePaymentMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin || event.data !== 'easeverifier-paystack-demo-returned') return;

    clearPaymentTimer();
    void verifyPayment();
};

onMounted(() => window.addEventListener('message', handlePaymentMessage));

onBeforeUnmount(() => {
    clearStageTimer();
    clearPaymentTimer();
    window.removeEventListener('message', handlePaymentMessage);
});
</script>

<template>
     <section class="paygo-simulation" style="margin-bottom: 5px;">
    <header class="simulation-header">
        <div>
            <div class="simulation-kicker"><span class="live-dot" /> Student result verification</div>
        </div>
        <div class="simulation-controls">
            <button v-if="!isPlaying" type="button" style="padding: 7px;min-width: 60px !important; max-height: 25px;" class="control-button control-button2 control-primary" title="Play simulation" @click="play">
                <Play :size="8" fill="currentColor" /><span style="font-size: 11px;">Play</span>
            </button>
            <button v-else type="button" style="padding: 7px;min-width: 60px !important; max-height: 25px;" class="control-button control-primary" title="Pause simulation" @click="pause">
                <Pause :size="8" fill="currentColor" /><span style="font-size: 11px;">Pause</span>
            </button>
            <button style="padding: 7px;min-width: 60px !important; max-height: 25px;"  type="button" class="icon-button" title="Restart simulation" @click="restart"><RotateCcw :size="10" /></button>
        </div>
    </header>

    <div class="stage-navigation" aria-label="Simulation stages">
        <div class="progress-track"><span :style="{ width: `${progress}%` }" /></div>
        <button
            v-for="(stage, index) in journeyStages"
            :key="stage.label"
            type="button"
            class="stage-button"
            :class="{ active: journeyStep === index, complete: journeyStep > index }"
            :aria-current="journeyStep === index ? 'step' : undefined"
            @click="selectStage(stage.scene)"
        >
            <span class="stage-icon">
                <Check v-if="journeyStep > index" :size="15" stroke-width="3" />
                <component :is="stage.icon" v-else :size="15" />
            </span>
            <span>{{ stage.label }}</span>
        </button>
    </div>
    </section>
    <section class="paygo-simulation" :class="{ 'is-playing': isPlaying }" aria-label="Interactive PayGo result verification simulation">

        <div class="simulation-viewport">
            <div class="browser-toolbar">
                <div class="browser-lights" aria-hidden="true"><span /><span /><span /></div>
                <label class="browser-address">
                    <LockKeyhole :size="13" />
                    <input v-model="browserUrl" type="text" aria-label="Simulation URL" spellcheck="false" />
                </label>
                <span class="browser-menu" aria-hidden="true">&#8942;</span>
            </div>
            <Transition name="stage-change" mode="out-in">
                <div v-if="activeStage === 0" key="portal" class="screen school-screen">
                    <div class="app-toolbar school-toolbar">
                        <div class="brand-lockup">
                            <span class="school-mark"><GraduationCap :size="21" /></span>
                            <div><strong>Northfield Academy</strong><small>Student Records</small></div>
                        </div>
                        <div class="portal-user">AO</div>
                    </div>
                    <div class="screen-content portal-content">
                        <div class="screen-heading">
                            <div><span class="eyebrow">2025/2026 academic session</span><h4>Student result records</h4></div>
                            <span class="record-count">1 student</span>
                        </div>
                        <div class="student-table">
                            <div class="table-head"><span>Student</span><span>Application ID</span><span>Result status</span><span /></div>
                            <div class="student-row">
                                <div class="student-name"><span class="student-avatar">AI</span><div><strong>Amina Ibrahim</strong><small>Science applicant</small></div></div>
                                <code>STU-12345</code>
                                <span class="status-badge status-pending">Not verified</span>
                                <button type="button" class="action-button pulse-action" @click="continueFlow(1)">Verify result <ArrowRight :size="16" /></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="activeStage === 1" key="exam-selector" class="screen paygo-page">
                    <div class="paygo-card selector-card">
                        <span class="result-chip">Result Verification</span>
                        <h4>Select exam result</h4>
                        <div class="select-shell">
                            <span class="floating-label">Exam</span>
                            <div class="select-value">Select an examination board <ChevronDown :size="18" /></div>
                        </div>
                        <div class="exam-menu">
                            <button v-for="board in examOptions" :key="board" type="button" @click="continueFlow(2)">
                                <strong>{{ board }} - ₦1,500</strong>
                            </button>
                        </div>
                        <div class="portal-context"><span>Portal context preserved</span><code>STU-12345 / APP-90210</code></div>
                    </div>
                </div>

                <div v-else-if="activeStage === 2" key="result-details" class="screen paygo-page details-page">
                    <div class="paygo-card details-card">
                        <span class="result-chip">Result Verification</span>
                        <h4>WAEC Result Verification</h4>
                        <button type="button" class="change-exam" @click="selectStage(1)"><ArrowLeft :size="14" /> Change exam</button>
                        <div class="price-strip"><span>WAEC amount</span><strong>NGN 1,500</strong></div>
                        <div class="field-grid">
                            <label v-for="field in resultFields" :key="field[0]"><span>{{ field[0] }}</span><b>{{ field[1] }}</b></label>
                        </div>
                        <button type="button" class="paygo-action pulse-action" @click="continueFlow(3)">Proceed to Payment</button>
                    </div>
                </div>

                <div v-else-if="activeStage === 3" key="confirmation" class="screen paygo-page confirmation-page">
                    <div class="paygo-card details-card behind-dialog">
                        <span class="result-chip">Result Verification</span><h4>WAEC Result Verification</h4>
                        <div class="price-strip"><span>WAEC amount</span><strong>NGN 1,500</strong></div>
                    </div>
                    <div class="dialog-scrim">
                        <div class="confirmation-dialog">
                            <h4>Confirm your details</h4>
                            <p>Please confirm that the result-check details below are correct before we continue to payment.</p>
                            <div class="confirmation-list">
                                <div><span>Exam number</span><b>1234567890</b></div>
                                <div><span>Exam year</span><b>2024</b></div>
                                <div><span>Exam type</span><b>MAY/JUN</b></div>
                                <div><span>Email</span><b>amina@example.com</b></div>
                            </div>
                            <label class="consent-row"><span><Check :size="14" stroke-width="3" /></span>I confirm that the information provided is correct and belongs to me.</label>
                            <div class="dialog-actions">
                                <button type="button" class="review-button" @click="selectStage(2)">Review again</button>
                                <button type="button" class="confirm-button pulse-action" @click="goToPayment">Confirm and Pay</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="activeStage === 4" key="payment" class="screen checkout-screen">
                    <div v-if="checkoutLoading" class="checkout-frame-state">
                        <LoaderCircle :size="30" class="spin" />
                        <strong>Opening Paystack test checkout</strong>
                        <span>Initializing the NGN 1,500 transaction...</span>
                    </div>
                    <iframe
                        v-else-if="checkoutFrameUrl"
                        class="paystack-checkout-frame"
                        :src="checkoutFrameUrl"
                        title="Paystack test checkout"
                        allow="payment *"
                    />
                    <div v-else class="checkout-frame-state checkout-failed">
                        <AlertCircle :size="30" />
                        <strong>Unable to load Paystack checkout</strong>
                        <span>{{ checkoutError }}</span>
                        <button type="button" class="checkout-button" @click="initializePaystackCheckout">Try again</button>
                    </div>
                </div>

                <div v-else-if="activeStage === 5" key="verification" class="screen processing-screen">
                    <div class="processing-panel">
                        <div class="scanner"><ScanSearch :size="34" /><span /></div>
                        <span class="eyebrow">Payment confirmed</span><h4>Verifying with WAEC</h4>
                        <p>EaseVerifier is checking the candidate details and normalizing the returned subjects.</p>
                        <div class="verification-steps">
                            <div class="done"><CheckCircle2 :size="19" /><span>Paystack reference confirmed</span><small>Complete</small></div>
                            <div class="done delayed-done"><CheckCircle2 :size="19" /><span>Result provider contacted</span><small>Complete</small></div>
                            <div class="active delayed-active"><LoaderCircle :size="19" /><span>Returning result to school portal</span><small>Working</small></div>
                        </div>
                    </div>
                </div>

                <div v-else key="result" class="screen school-screen">
                    <div class="app-toolbar school-toolbar">
                        <div class="brand-lockup"><span class="school-mark"><GraduationCap :size="21" /></span><div><strong>Northfield Academy</strong><small>Student Records</small></div></div>
                        <span class="return-label"><CheckCircle2 :size="16" /> Returned from EaseVerifier</span>
                    </div>
                    <div class="screen-content result-content">
                        <div class="result-banner">
                            <span class="result-check"><BadgeCheck :size="25" /></span>
                            <div><strong>Result verified successfully</strong><small>WAEC provider response received and saved</small></div>
                            <span class="status-badge status-verified">Verified</span>
                        </div>
                        <div class="candidate-strip">
                            <div><span>Candidate</span><strong>Amina Ibrahim</strong></div>
                            <div><span>Exam number</span><strong>1234567890</strong></div>
                            <div><span>Board / year</span><strong>WAEC / 2024</strong></div>
                            <div><span>Portal reference</span><strong>APP-90210</strong></div>
                        </div>
                        <div class="results-table">
                            <div class="result-table-head"><span>Subject</span><span>Grade</span><span>Outcome</span></div>
                            <div><strong>English Language</strong><span class="grade">A1</span><span class="pass">Pass</span></div>
                            <div><strong>Mathematics</strong><span class="grade">A1</span><span class="pass">Pass</span></div>
                            <div><strong>Physics</strong><span class="grade">B2</span><span class="pass">Pass</span></div>
                            <div><strong>Chemistry</strong><span class="grade">B3</span><span class="pass">Pass</span></div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </section>
</template>

<style scoped>
.paygo-simulation { --ink:#17221c; --muted:#66716a; --line:#dfe5e1; --green:#1c6434; --green-dark:#0f3e20; --green-soft:#e9f6ef; --yellow:#fecd07; overflow:hidden; border:1px solid #d7ded9; border-radius:8px; background:#f7f9f8; box-shadow:0 16px 40px rgba(23,34,28,.08); color:var(--ink); }
.simulation-header { display:flex;  align-items:center; justify-content:space-between; gap:24px; padding:18px 22px 0px 22px; background:#fff; }
.simulation-kicker,.eyebrow { display:flex; align-items:center; gap:7px; color:var(--green); font-size:11px; font-weight:700; text-transform:uppercase; }
.live-dot { width:7px; height:7px; border-radius:50%; background:#21a766; box-shadow:0 0 0 4px #ddf5e8; }
.simulation-header h3 { margin:4px 0 1px; font-size:19px; line-height:1.25; }
.simulation-header p { margin:0; color:var(--muted); font-size:13px; }
.simulation-controls { display:flex; flex:0 0 auto; gap:8px; }
.control-button,.icon-button,.action-button,.paygo-action,.review-button,.confirm-button,.checkout-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:0; font:inherit; font-weight:700; cursor:pointer; }
.control-button { min-width:88px; height:38px; padding:0 14px; border-radius:6px; }
.control-button2 { min-width:88px !important; height:38px !important; padding:0 7px !important; border-radius:6px !important; }
.control-primary { background:var(--green); color:#fff; }
.icon-button { width:38px; height:38px; border:1px solid var(--line); border-radius:6px; background:#fff; color:#33443a; }
button:focus-visible,a:focus-visible { outline:3px solid rgba(35,100,170,.28); outline-offset:2px; }
.stage-navigation { position:relative; display:grid; grid-template-columns:repeat(4,minmax(100px,1fr)); overflow-x:auto; padding:14px 22px 16px; border-bottom:1px solid var(--line); background:#fbfcfb; }
.progress-track { position:absolute; top:31px; right:12.5%; left:12.5%; height:2px; overflow:hidden; background:#dfe6e1; }
.progress-track span { display:block; height:100%; background:var(--green); transition:width 450ms ease; }
.stage-button { position:relative; z-index:1; display:flex; min-width:0; flex-direction:column; align-items:center; gap:7px; border:0; background:transparent; color:#7a847e; font-size:10px; font-weight:600; cursor:pointer; }
.stage-icon { display:grid; width:34px; height:34px; place-items:center; border:2px solid #dfe6e1; border-radius:50%; background:#fff; transition:250ms ease; }
.stage-button.active,.stage-button.complete { color:var(--green-dark); }
.stage-button.active .stage-icon,.stage-button.complete .stage-icon { border-color:var(--green); background:var(--green); color:#fff; }
.stage-button.active .stage-icon { box-shadow:0 0 0 5px var(--green-soft); }
.simulation-viewport { min-height:668px; }
.screen { min-height:620px; }
.simulation-viewport { background:#edf2ef; }
.browser-toolbar { display:grid; grid-template-columns:58px minmax(0,1fr) 22px; align-items:center; gap:12px; min-height:48px; padding:0 14px; border-bottom:1px solid #d8dfe3; background:#f7f9fa; }
.browser-lights { display:flex; gap:6px; }
.browser-lights span { width:9px; height:9px; border-radius:50%; background:#ff665c; }
.browser-lights span:nth-child(2) { background:#ffbd44; }
.browser-lights span:nth-child(3) { background:#00ca4e; }
.browser-address { display:flex; min-width:0; height:30px; align-items:center; gap:7px; padding:0 10px; border:1px solid #d8e0e4; border-radius:6px; background:#fff; color:#5f6d74; }
.browser-address input { width:100%; min-width:0; border:0; outline:0; background:transparent; color:#35434a; font:inherit; font-size:11px; letter-spacing:0; }
.browser-menu { color:#68757b; font-size:20px; line-height:1; text-align:center; }
.app-toolbar { display:flex; min-height:70px; align-items:center; justify-content:space-between; padding:0 28px; }
.school-toolbar { border-bottom:1px solid #d9e0dc; background:#fff; }
.brand-lockup,.student-name,.paystack-heading { display:flex; align-items:center; gap:11px; }
.school-mark { display:grid; width:38px; height:38px; place-items:center; border-radius:6px; background:#143d59; color:#fff; }
.brand-lockup div,.student-name div,.paystack-heading div { display:flex; flex-direction:column; }
.brand-lockup strong { font-size:14px; }
.brand-lockup small,.student-name small { color:#758078; font-size:11px; }
.portal-user { display:grid; width:34px; height:34px; place-items:center; border-radius:50%; background:#e8eef2; color:#143d59; font-size:12px; font-weight:800; }
.screen-content { padding:38px; }
.screen-heading { display:flex; align-items:end; justify-content:space-between; gap:20px; margin-bottom:22px; }
.screen-heading h4,.processing-panel h4 { margin:6px 0 0; font-size:23px; line-height:1.25; }
.record-count { color:var(--muted); font-size:12px; }
.student-table,.results-table { overflow:hidden; border:1px solid #dce3df; border-radius:6px; background:#fff; }
.table-head,.student-row { display:grid; grid-template-columns:minmax(180px,1.4fr) minmax(115px,.8fr) minmax(105px,.7fr) minmax(130px,auto); align-items:center; gap:14px; padding:14px 18px; }
.table-head { border-bottom:1px solid #e3e8e5; background:#f6f8f7; color:#748078; font-size:10px; font-weight:700; text-transform:uppercase; }
.student-avatar { display:grid; width:36px; height:36px; flex:0 0 auto; place-items:center; border-radius:50%; background:#e8eef2; color:#143d59; font-size:11px; font-weight:800; }
.student-name strong { font-size:13px; }
.student-row code { color:#3d4b43; font-size:12px; }
.status-badge { width:fit-content; padding:5px 9px; border-radius:999px; font-size:10px; font-weight:700; }
.status-pending { background:#fff5d7; color:#816100; }
.status-verified { background:#dff5e8; color:#087443; }
.action-button { min-height:38px; padding:0 13px; border-radius:6px; background:#143d59; color:#fff; font-size:12px; }
.paygo-page { position:relative; display:grid; place-items:center; padding:30px 18px; background:linear-gradient(135deg,#0f3e20 0%,#082716 58%,#04150d 100%); }
.paygo-card { width:min(100%,520px); padding:26px; border:1px solid rgba(255,255,255,.18); border-radius:8px; background:#fff; box-shadow:0 16px 40px rgba(0,0,0,.2); }
.result-chip { display:inline-flex; padding:6px 11px; border-radius:999px; background:var(--yellow); color:#312700; font-size:10px; font-weight:800; }
.paygo-card h4,.confirmation-dialog h4 { margin:14px 0 4px; font-size:22px; line-height:1.25; }
.selector-card h4 { margin-bottom:22px; }
.select-shell { position:relative; height:54px; border:1px solid #87948c; border-radius:4px; background:#fff; }
.floating-label { position:absolute; top:-8px; left:11px; padding:0 4px; background:#fff; color:#5f6f65; font-size:10px; }
.select-value { display:flex; height:100%; align-items:center; justify-content:space-between; padding:0 14px; color:#66716a; font-size:13px; }
.exam-menu { margin-top:5px; overflow:hidden; border:1px solid #dbe2dd; border-radius:4px; box-shadow:0 8px 22px rgba(15,62,32,.15); }
.exam-menu button { display:flex; width:100%; min-height:52px; align-items:center; justify-content:space-between; gap:18px; padding:8px 14px; border:0; border-bottom:1px solid #edf0ee; background:#fff; color:var(--ink); text-align:left; cursor:pointer; }
.exam-menu button:last-child { border-bottom:0; }
.exam-menu button:hover { background:#f4f8f5; }
.exam-menu button span { display:flex; flex-direction:column; }
.exam-menu strong,.exam-menu b { font-size:12px; }
.exam-menu small { margin-top:2px; color:#758078; font-size:10px; }
.exam-menu b { color:var(--green-dark); }
.portal-context { display:flex; justify-content:space-between; gap:12px; margin-top:16px; color:#6d7871; font-size:10px; }
.portal-context code { color:var(--green-dark); }
.details-page { align-items:start; overflow-y:auto; }
.details-card { margin:auto; padding:21px 24px; }
.details-card h4 { margin-top:10px; font-size:19px; }
.change-exam { display:inline-flex; align-items:center; gap:5px; margin:6px 0 9px; padding:0; border:0; background:transparent; color:var(--green); font-size:11px; font-weight:700; cursor:pointer; }
.price-strip { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; padding:10px 12px; border-radius:6px; background:#f6f8f6; color:#5f6f65; font-size:11px; }
.price-strip strong { color:var(--green-dark); font-size:14px; }
.field-grid { display:grid; gap:7px; }
.field-grid label { display:flex; min-height:42px; align-items:center; justify-content:space-between; gap:14px; padding:6px 12px; border:1px solid #cdd7d1; border-radius:4px; }
.field-grid span { color:#66716a; font-size:10px; }
.field-grid b { overflow-wrap:anywhere; color:#253129; font-size:11px; text-align:right; }
.paygo-action { width:100%; min-height:42px; margin-top:11px; border-radius:4px; background:var(--yellow); color:#312700; font-size:12px; }
.confirmation-page { overflow:hidden; }
.behind-dialog { opacity:.75; transform:scale(.98); }
.dialog-scrim { position:absolute; inset:0; display:grid; place-items:center; padding:20px; background:rgba(0,0,0,.56); }
.confirmation-dialog { width:min(100%,520px); padding:24px; border-radius:8px; background:#fff; box-shadow:0 18px 50px rgba(0,0,0,.28); }
.confirmation-dialog h4 { margin-top:0; font-size:19px; }
.confirmation-dialog>p { margin:0 0 15px; color:#647068; font-size:12px; line-height:1.5; }
.confirmation-list { overflow:hidden; border:1px solid rgba(15,62,32,.12); border-radius:6px; }
.confirmation-list div { display:flex; justify-content:space-between; gap:16px; padding:9px 12px; border-bottom:1px solid rgba(15,62,32,.08); font-size:11px; }
.confirmation-list div:last-child { border-bottom:0; }
.confirmation-list span { color:#66716a; }
.consent-row { display:flex; align-items:flex-start; gap:8px; margin:14px 0; color:#3f4a43; font-size:11px; line-height:1.45; }
.consent-row span { display:grid; width:17px; height:17px; flex:0 0 auto; place-items:center; border-radius:3px; background:var(--yellow); color:#312700; }
.dialog-actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.review-button,.confirm-button { min-height:40px; border-radius:4px; font-size:11px; }
.review-button { border:1px solid var(--green); background:#fff; color:var(--green); }
.confirm-button { background:var(--yellow); color:#312700; }
.checkout-screen { display:grid; background:#eef3f6; }
.paystack-checkout-frame { width:100%; height:620px; border:0; background:#fff; }
.checkout-frame-state { display:flex; min-height:620px; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:30px; color:#0a76a8; text-align:center; }
.checkout-frame-state strong { color:#213239; font-size:16px; }
.checkout-frame-state span { color:#66747b; font-size:12px; }
.checkout-failed { color:#a12d2d; }
.checkout-button { min-height:40px; margin-top:6px; padding:0 18px; border-radius:5px; background:#0ba4db; color:#fff; font-size:12px; }
.processing-screen { display:grid; place-items:center; padding:32px 18px; background:#f2f6f3; }
.processing-panel { width:min(100%,570px); text-align:center; }
.processing-panel .eyebrow { justify-content:center; }
.processing-panel p { max-width:460px; margin:10px auto 24px; color:var(--muted); font-size:13px; line-height:1.6; }
.scanner { position:relative; display:grid; width:72px; height:72px; margin:0 auto 16px; place-items:center; overflow:hidden; border:1px solid #cce1d4; border-radius:8px; background:#fff; color:var(--green); }
.scanner span { position:absolute; right:8px; left:8px; height:2px; background:#20a461; animation:scan 1.5s ease-in-out infinite; }
.verification-steps { overflow:hidden; border:1px solid #dbe4de; border-radius:6px; background:#fff; text-align:left; }
.verification-steps>div { display:grid; grid-template-columns:24px 1fr auto; align-items:center; gap:9px; padding:14px 16px; border-bottom:1px solid #e7ece8; color:#657168; font-size:12px; }
.verification-steps>div:last-child { border-bottom:0; }
.verification-steps .done { color:#16824d; }
.verification-steps .active svg { animation:spin 1s linear infinite; }
.verification-steps small { color:#8a958e; font-size:10px; }
.return-label { display:inline-flex; align-items:center; gap:6px; color:var(--green); font-size:11px; font-weight:700; }
.result-banner { display:grid; grid-template-columns:40px 1fr auto; align-items:center; gap:12px; margin-bottom:17px; padding:14px; border:1px solid #cfe8d9; border-radius:6px; background:#f1faf5; }
.result-check { display:grid; width:36px; height:36px; place-items:center; border-radius:50%; background:#dff5e8; color:#16824d; }
.result-banner div { display:flex; flex-direction:column; }
.result-banner strong { font-size:13px; }
.result-banner small { margin-top:3px; color:#66716a; font-size:10px; }
.candidate-strip { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; overflow:hidden; margin-bottom:17px; border:1px solid #dce3df; border-radius:6px; background:#dce3df; }
.candidate-strip div { min-width:0; padding:11px; background:#fff; }
.candidate-strip span,.candidate-strip strong { display:block; }
.candidate-strip span { margin-bottom:4px; color:#78827c; font-size:9px; text-transform:uppercase; }
.candidate-strip strong { overflow:hidden; font-size:11px; text-overflow:ellipsis; white-space:nowrap; }
.results-table>div { display:grid; grid-template-columns:1fr 90px 90px; align-items:center; gap:14px; padding:10px 15px; border-bottom:1px solid #e7ebe8; font-size:11px; }
.results-table>div:last-child { border-bottom:0; }
.result-table-head { background:#f6f8f7; color:#748078; font-size:9px!important; font-weight:700; text-transform:uppercase; }
.grade { color:#143d59; font-weight:800; }
.pass { color:#16824d; font-weight:700; }
.spin { animation:spin 1s linear infinite; }
.is-playing .pulse-action { animation:button-pulse 1.4s ease-in-out infinite; }
.stage-change-enter-active,.stage-change-leave-active { transition:opacity 220ms ease,transform 220ms ease; }
.stage-change-enter-from { opacity:0; transform:translateX(10px); }
.stage-change-leave-to { opacity:0; transform:translateX(-10px); }
@keyframes scan { 0%,100% { transform:translateY(-22px); opacity:.45; } 50% { transform:translateY(22px); opacity:1; } }
@keyframes spin { to { transform:rotate(360deg); } }
@keyframes button-pulse { 0%,100% { box-shadow:0 0 0 0 rgba(28,100,52,0); } 50% { box-shadow:0 0 0 6px rgba(28,100,52,.16); } }
@media (max-width:760px) {
    .simulation-header { align-items:flex-start; padding:16px; }
    .simulation-controls .control-button span { display:none; }
    .simulation-controls .control-button { min-width:38px; padding:0; }
    .stage-navigation { grid-template-columns:repeat(4,minmax(82px,1fr)); padding-right:14px; padding-left:14px; }
    .progress-track { display:none; }
    .simulation-viewport { min-height:738px; }
    .screen { min-height:690px; }
    .browser-toolbar { grid-template-columns:42px minmax(0,1fr) 14px; gap:7px; padding:0 10px; }
    .browser-address input { font-size:10px; }
    .screen-content { padding:24px 16px; }
    .table-head { display:none; }
    .student-row { grid-template-columns:1fr auto; gap:14px; }
    .student-row code,.action-button { grid-column:1/-1; }
    .action-button { width:100%; }
    .portal-context { align-items:flex-start; flex-direction:column; }
    .paygo-page,.checkout-screen { padding:20px 12px; }
    .paygo-card,.confirmation-dialog { padding:20px; }
    .paystack-checkout-frame,.checkout-frame-state { min-height:690px; height:690px; }
    .candidate-strip { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .results-table>div { grid-template-columns:1fr 56px 54px; gap:8px; padding:10px; }
    .return-label { max-width:145px; text-align:right; }
}
@media (prefers-reduced-motion:reduce) { *,*::before,*::after { scroll-behavior:auto!important; animation-duration:.01ms!important; animation-iteration-count:1!important; transition-duration:.01ms!important; } }
</style>
