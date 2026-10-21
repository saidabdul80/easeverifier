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
    LoaderCircle,
    LockKeyhole,
    Maximize2,
    Minimize2,
    Pause,
    Play,
    RotateCcw,
    ScanSearch,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

type SubjectRow = { subject: string; grade: string; sitting: number };
type PortalScenario = {
    id: string;
    label: string;
    title: string;
    description: string;
    tone: 'success' | 'warning' | 'error';
    status:
        | 'mapped_ready'
        | 'mapped_partial'
        | 'ownership_failed'
        | 'ownership_review'
        | 'result_pending'
        | 'pull_failed';
    candidateName: string;
    ownership: string;
    mapping: string;
    nextStep: string;
    subjects: SubjectRow[];
};

const sampleSubjects: SubjectRow[] = [
    { subject: 'English Language', grade: 'A1', sitting: 1 },
    { subject: 'Mathematics', grade: 'B2', sitting: 1 },
    { subject: 'Physics', grade: 'B3', sitting: 1 },
    { subject: 'Chemistry', grade: 'C4', sitting: 1 },
    { subject: 'Biology', grade: 'C5', sitting: 1 },
    { subject: 'Geography', grade: 'C6', sitting: 1 },
];
const scenarios: PortalScenario[] = [
    {
        id: 'matched',
        label: 'Name and subjects match',
        tone: 'success',
        status: 'mapped_ready',
        title: 'Result verified. Required subjects filled.',
        description:
            'Amina’s name matches her portal profile. All six subject slots in this example are filled, and the grades meet the example credit rule.',
        candidateName: 'Amina Ibrahim',
        ownership: 'Name matched',
        mapping: '6 of 6 required slots filled',
        nextStep:
            'Review the filled academic record and continue with the portal’s remaining clearance steps.',
        subjects: sampleSubjects,
    },
    {
        id: 'name-mismatch',
        label: 'Verified result, different name',
        tone: 'error',
        status: 'ownership_failed',
        title: 'Result verified. Name does not match.',
        description:
            'The exam provider returned a real result for David Okonkwo. The portal profile belongs to Amina Ibrahim, so the portal blocks auto-fill.',
        candidateName: 'David Okonkwo',
        ownership: 'Name mismatch',
        mapping: 'Auto-fill blocked',
        nextStep:
            'Check the exam number. If this is your result, contact the school to resolve the name difference before using it for clearance.',
        subjects: sampleSubjects,
    },
    {
        id: 'name-review',
        label: 'Partial name match needs review',
        tone: 'warning',
        status: 'ownership_review',
        title: 'Result verified. School review needed.',
        description:
            'The returned name is Amina Yusuf. Only part of Amina Ibrahim’s profile name matches, so ownership cannot be accepted automatically.',
        candidateName: 'Amina Yusuf',
        ownership: 'Partial match · review required',
        mapping: 'Auto-fill blocked pending review',
        nextStep:
            'Contact the school with supporting identity documents. The verified result is retained for review.',
        subjects: sampleSubjects,
    },
    {
        id: 'missing-name',
        label: 'Provider did not return a name',
        tone: 'warning',
        status: 'ownership_review',
        title: 'Result verified. Candidate name unavailable.',
        description:
            'Subjects and grades were returned, but there is no candidate name to compare with the student’s profile.',
        candidateName: 'Not returned',
        ownership: 'Cannot check ownership',
        mapping: 'Auto-fill blocked pending review',
        nextStep:
            'Ask the school to review result ownership before the grades are applied to your academic record.',
        subjects: sampleSubjects,
    },
    {
        id: 'duplicate-result',
        label: 'Result already used by another student',
        tone: 'error',
        status: 'ownership_failed',
        title: 'Result verified. Already linked to another student.',
        description:
            'The name matches, but the portal detects the same exam result on another student’s record. Duplicate use blocks auto-fill even when the grades are good.',
        candidateName: 'Amina Ibrahim',
        ownership: 'Duplicate result use detected',
        mapping: 'Auto-fill blocked',
        nextStep:
            'Contact the school to resolve the duplicate record. Paying again does not resolve an ownership conflict.',
        subjects: sampleSubjects,
    },
    {
        id: 'missing-subject',
        label: 'Good grades, required subject missing',
        tone: 'warning',
        status: 'mapped_partial',
        title: 'Result verified. Chemistry is missing.',
        description:
            'Amina passes the returned subjects, but this example programme requires Chemistry. Another passed subject cannot fill that specific slot.',
        candidateName: 'Amina Ibrahim',
        ownership: 'Name matched',
        mapping: '5 of 6 required slots filled',
        nextStep:
            'Review the missing requirement with the school. If allowed, verify a second sitting that contains Chemistry.',
        subjects: sampleSubjects.filter((row) => row.subject !== 'Chemistry'),
    },
    {
        id: 'low-grade',
        label: 'Subject present, grade below example rule',
        tone: 'warning',
        status: 'mapped_ready',
        title: 'Subjects filled. Check the Mathematics grade.',
        description:
            'Mathematics is present with D7. It can be mapped into the portal, but it falls below the C6 minimum used in this example. Mapping alone does not establish eligibility.',
        candidateName: 'Amina Ibrahim',
        ownership: 'Name matched',
        mapping: '6 of 6 slots filled · grade needs attention',
        nextStep:
            'Check the school’s actual grade requirements. The current mapping code does not enforce the example C6 cutoff.',
        subjects: sampleSubjects.map((row) =>
            row.subject === 'Mathematics' ? { ...row, grade: 'D7' } : row,
        ),
    },
    {
        id: 'two-sittings',
        label: 'Two sittings complete the requirements',
        tone: 'success',
        status: 'mapped_ready',
        title: 'Two verified sittings. Required subjects filled.',
        description:
            'Sitting 1 was missing Chemistry and had Physics at C6. Sitting 2 supplies Chemistry at C4 and improves Physics to B2. The portal uses the best grade for each subject.',
        candidateName: 'Amina Ibrahim',
        ownership: 'Name matched',
        mapping: '6 of 6 slots filled across 2 sittings',
        nextStep:
            'Review the source sitting shown for each subject. The school decides whether two sittings are accepted for the programme.',
        subjects: sampleSubjects.map((row) =>
            row.subject === 'Chemistry'
                ? { ...row, sitting: 2 }
                : row.subject === 'Physics'
                  ? { ...row, grade: 'B2', sitting: 2 }
                  : row,
        ),
    },
    {
        id: 'pending',
        label: 'Payment complete, result still preparing',
        tone: 'warning',
        status: 'result_pending',
        title: 'Payment confirmed. Result is still preparing.',
        description:
            'The portal has a payment reference, but the verified result is not ready to pull yet. No subjects or ownership decision are available.',
        candidateName: 'Awaiting result',
        ownership: 'Waiting for candidate details',
        mapping: 'Waiting for result',
        nextStep:
            'Refresh shortly or retry the result pull using the existing reference. A pending result does not call for another payment.',
        subjects: [],
    },
    {
        id: 'pull-failed',
        label: 'Portal could not retrieve the result',
        tone: 'error',
        status: 'pull_failed',
        title: 'Payment confirmed. Result pull failed.',
        description:
            'The portal could not retrieve a usable result response. This does not establish a name mismatch or a subject failure.',
        candidateName: 'Unavailable',
        ownership: 'Not checked',
        mapping: 'No result to map',
        nextStep:
            'Retry the result pull with the same reference. If it continues to fail, contact support with the displayed error and reference.',
        subjects: [],
    },
];
const selectedScenarioId = ref('matched');
const scenario = computed(
    () =>
        scenarios.find((item) => item.id === selectedScenarioId.value) ??
        scenarios[0],
);
const hasResult = computed(() => scenario.value.subjects.length > 0);
const ownershipBlocked = computed(() =>
    ['ownership_failed', 'ownership_review'].includes(scenario.value.status),
);
const requirementRows = computed(() =>
    sampleSubjects.map((required) => {
        const result = scenario.value.subjects.find(
            (row) => row.subject === required.subject,
        );
        const meetsExampleGrade = result
            ? /^(A1|B2|B3|C4|C5|C6)$/.test(result.grade)
            : false;

        return {
            subject: required.subject,
            grade: result?.grade ?? '—',
            source: result ? `Sitting ${result.sitting}` : '—',
            outcome: !result
                ? 'Missing'
                : meetsExampleGrade
                  ? 'Meets example'
                  : 'Below C6',
            tone: meetsExampleGrade ? 'pass' : 'needs-attention',
        };
    }),
);

const stages = [
    { label: 'School portal', icon: GraduationCap, delay: 1800 },
    { label: 'Choose exam', icon: FileSearch, delay: 2200 },
    { label: 'Result details', icon: FileSearch, delay: 2600 },
    { label: 'Confirm', icon: CheckCircle2, delay: 2200 },
    { label: 'Payment', icon: CreditCard, delay: null },
    { label: 'Verification', icon: ScanSearch, delay: 2800 },
    { label: 'Portal outcome', icon: BadgeCheck, delay: null },
];
const journeyStages = [
    { label: 'School portal', icon: GraduationCap, scene: 0 },
    { label: 'EaseVerifier', icon: FileSearch, scene: 1 },
    { label: 'Paystack', icon: CreditCard, scene: 4 },
    { label: 'Portal checks', icon: BadgeCheck, scene: 6 },
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
    'https://easeverifier.com/paygo/results/customer/EVR-E2UPGRSD?candidate_id=STU-12345&portal_ref=APP-90210&reference=APP-90210',
    'https://easeverifier.com/paygo/results/waec-result-verification?candidate_id=STU-12345&portal_ref=APP-90210&reference=APP-90210',
    'https://easeverifier.com/paygo/results/waec-result-verification?candidate_id=STU-12345&portal_ref=APP-90210&reference=APP-90210',
    'https://checkout.paystack.com/test/easeverifier-demo',
    'https://easeverifier.com/paygo/callback?reference=DEMO_PAYSTACK_REFERENCE',
    'https://portal.northfield.edu.ng/results/APP-90210',
];

const activeStage = ref(0);
const simulationRoot = ref<HTMLElement | null>(null);
const browserUrl = ref(stageUrls[0]);
const isPlaying = ref(false);
const isManuallyPaused = ref(false);
const isFullscreen = ref(false);
const fullscreenSupported = ref(false);
const checkoutLoading = ref(false);
const checkoutError = ref('');
const checkoutUrl = ref('');
const checkoutFrameUrl = ref('');
const paymentConfirmed = ref(false);
const paymentReference = ref('');
const paymentVerificationToken = ref('');
let stageTimer: ReturnType<typeof setTimeout> | null = null;
let paymentTimer: ReturnType<typeof setTimeout> | null = null;
let verificationAttempts = 0;
let paymentVerificationInFlight = false;
let paymentSession = 0;

const journeyStep = computed(() => {
    if (activeStage.value === 0) return 0;
    if (activeStage.value <= 3) return 1;
    if (activeStage.value <= 5) return 2;

    return 3;
});
const progress = computed(
    () => (journeyStep.value / (journeyStages.length - 1)) * 100,
);

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

    if (
        !isPlaying.value ||
        activeStage.value >= stages.length - 1 ||
        delay === null
    ) {
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
    if (activeStage.value >= stages.length - 1) restart();
    isManuallyPaused.value = false;
    isPlaying.value = true;
    scheduleNextStage();
};

const stopPlayback = () => {
    isPlaying.value = false;
    clearStageTimer();
};

const pause = () => {
    stopPlayback();
    isManuallyPaused.value = true;
};

const resetPayment = () => {
    paymentSession += 1;
    paymentVerificationInFlight = false;
    clearPaymentTimer();
    checkoutLoading.value = false;
    checkoutError.value = '';
    checkoutUrl.value = '';
    checkoutFrameUrl.value = '';
    paymentConfirmed.value = false;
    paymentReference.value = '';
    paymentVerificationToken.value = '';
    verificationAttempts = 0;
};

const restart = () => {
    stopPlayback();
    isManuallyPaused.value = false;
    resetPayment();
    activeStage.value = 0;
};

const previewScenario = () => {
    pause();
    resetPayment();
    activeStage.value = 6;
};

const selectStage = (index: number) => {
    pause();
    activeStage.value = index;

    if (index === 4 && !checkoutUrl.value && !checkoutLoading.value) {
        void initializePaystackCheckout();
    }
};

const continueFlow = (nextStage: number) => {
    stopPlayback();
    activeStage.value = nextStage;

    if (!isManuallyPaused.value) {
        isPlaying.value = true;
        scheduleNextStage();
    }
};

const advancePausedFlow = (event: MouseEvent) => {
    if (!isManuallyPaused.value || activeStage.value >= stages.length - 1)
        return;

    const target = event.target as HTMLElement;

    if (target.closest('button, a, input, select, textarea, label, iframe'))
        return;

    if (activeStage.value === 4) {
        if (paymentConfirmed.value) advancePaymentDemo();
        return;
    }

    activeStage.value += 1;

    if (activeStage.value === 4 && !checkoutUrl.value) {
        void initializePaystackCheckout();
    }
};

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content || '';

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
        throw new Error(
            payload.message || 'Unable to complete the Paystack test payment.',
        );
    }

    return payload;
};

const verifyPayment = async () => {
    if (
        activeStage.value !== 4 ||
        !paymentReference.value ||
        paymentVerificationInFlight
    )
        return;

    clearPaymentTimer();
    paymentVerificationInFlight = true;
    verificationAttempts += 1;
    let shouldRetry = false;
    const session = paymentSession;

    try {
        const payment = await postJson('/paygo/demo/payment/verify', {
            reference: paymentReference.value,
            verification_token: paymentVerificationToken.value,
        });

        if (session !== paymentSession || activeStage.value !== 4) return;

        if (payment.complete) {
            clearPaymentTimer();
            paymentConfirmed.value = true;

            if (isManuallyPaused.value) return;

            activeStage.value = 5;
            isPlaying.value = true;
            scheduleNextStage();
            return;
        }

        shouldRetry = true;
    } catch (error) {
        if (session !== paymentSession) return;
        checkoutError.value =
            error instanceof Error
                ? error.message
                : 'Unable to verify the test payment.';
        shouldRetry = true;
    } finally {
        if (session === paymentSession) paymentVerificationInFlight = false;

        if (
            session === paymentSession &&
            shouldRetry &&
            activeStage.value === 4 &&
            verificationAttempts < 150
        ) {
            paymentTimer = setTimeout(verifyPayment, 4000);
        }
    }
};

const initializePaystackCheckout = async () => {
    stopPlayback();
    resetPayment();
    checkoutLoading.value = true;
    const session = paymentSession;

    try {
        const payment = await postJson('/paygo/demo/payment/initialize');

        if (session !== paymentSession || activeStage.value !== 4) return;

        paymentReference.value = payment.reference;
        paymentVerificationToken.value = payment.verification_token;
        checkoutUrl.value = payment.checkout_url;
        checkoutFrameUrl.value = payment.frame_url;
        browserUrl.value = payment.checkout_url;
        paymentTimer = setTimeout(verifyPayment, 4000);
    } catch (error) {
        if (session !== paymentSession) return;
        checkoutError.value =
            error instanceof Error
                ? error.message
                : 'Unable to open Paystack test checkout.';
    } finally {
        if (session === paymentSession) checkoutLoading.value = false;
    }
};

const goToPayment = () => {
    stopPlayback();
    activeStage.value = 4;
    void initializePaystackCheckout();
};

const advancePaymentDemo = () => {
    if (activeStage.value !== 4) return;

    clearPaymentTimer();
    checkoutLoading.value = false;
    activeStage.value = 5;

    if (!isManuallyPaused.value) {
        isPlaying.value = true;
        scheduleNextStage();
    }
};

const handlePaymentMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return;

    if (event.data === 'easeverifier-paystack-demo-advance') {
        advancePaymentDemo();
        return;
    }

    if (event.data !== 'easeverifier-paystack-demo-returned') return;

    clearPaymentTimer();
    void verifyPayment();
};

const syncFullscreenState = () => {
    isFullscreen.value = document.fullscreenElement === simulationRoot.value;
};

const toggleFullscreen = async () => {
    if (!simulationRoot.value || !fullscreenSupported.value) return;

    try {
        if (document.fullscreenElement === simulationRoot.value) {
            await document.exitFullscreen();
            return;
        }

        await simulationRoot.value.requestFullscreen();
    } catch {
        fullscreenSupported.value = false;
    }
};

onMounted(() => {
    fullscreenSupported.value = Boolean(document.fullscreenEnabled);
    window.addEventListener('message', handlePaymentMessage);
    document.addEventListener('fullscreenchange', syncFullscreenState);
});

onBeforeUnmount(() => {
    clearStageTimer();
    resetPayment();
    window.removeEventListener('message', handlePaymentMessage);
    document.removeEventListener('fullscreenchange', syncFullscreenState);
});
</script>

<template>
    <section
        ref="simulationRoot"
        class="paygo-simulation"
        :class="{ 'is-playing': isPlaying, 'is-paused': isManuallyPaused }"
        aria-label="Interactive PayGo result verification simulation"
    >
        <header class="simulation-header">
            <div>
                <div class="simulation-kicker">
                    <span class="live-dot" /> Student result verification
                </div>
                <h3>What happens when the result returns?</h3>
                <p>
                    EaseVerifier retrieves the result. The school checks
                    ownership and fills the required subjects.
                </p>
            </div>
            <div class="simulation-controls">
                <button
                    v-if="!isPlaying"
                    type="button"
                    style="
                        padding: 7px;
                        min-width: 60px !important;
                        max-height: 25px;
                    "
                    class="control-button control-button2 control-primary"
                    title="Play simulation"
                    @click="play"
                >
                    <Play :size="8" fill="currentColor" /><span
                        style="font-size: 11px"
                        >Play</span
                    >
                </button>
                <button
                    v-else
                    type="button"
                    style="
                        padding: 7px;
                        min-width: 60px !important;
                        max-height: 25px;
                    "
                    class="control-button control-primary"
                    title="Pause simulation"
                    @click="pause"
                >
                    <Pause :size="8" fill="currentColor" /><span
                        style="font-size: 11px"
                        >Pause</span
                    >
                </button>
                <button
                    style="
                        padding: 7px;
                        min-width: 60px !important;
                        max-height: 25px;
                    "
                    type="button"
                    class="icon-button"
                    title="Restart simulation"
                    aria-label="Restart simulation"
                    @click="restart"
                >
                    <RotateCcw :size="10" />
                </button>
            </div>
        </header>

        <div class="stage-navigation" aria-label="Simulation stages">
            <div class="progress-track">
                <span :style="{ width: `${progress}%` }" />
            </div>
            <button
                v-for="(stage, index) in journeyStages"
                :key="stage.label"
                type="button"
                class="stage-button"
                :class="{
                    active: journeyStep === index,
                    complete: journeyStep > index,
                }"
                :aria-current="journeyStep === index ? 'step' : undefined"
                @click="selectStage(stage.scene)"
            >
                <span class="stage-icon">
                    <Check
                        v-if="journeyStep > index"
                        :size="15"
                        stroke-width="3"
                    />
                    <component :is="stage.icon" v-else :size="15" />
                </span>
                <span>{{ stage.label }}</span>
            </button>
        </div>
        <div class="scenario-picker">
            <label>
                <span>Choose a student scenario</span>
                <select v-model="selectedScenarioId" @change="previewScenario">
                    <option
                        v-for="item in scenarios"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.label }}
                    </option>
                </select>
            </label>
            <button
                type="button"
                class="review-button"
                @click="previewScenario"
            >
                View portal outcome <ArrowRight :size="15" />
            </button>
            <p>
                Fictional student and programme. Preview any outcome without
                payment, or press Play to follow the journey with Paystack test
                checkout.
            </p>
        </div>

        <div class="simulation-viewport" @click="advancePausedFlow">
            <div class="browser-toolbar" @dblclick.self="advancePaymentDemo">
                <div class="browser-lights" aria-hidden="true">
                    <span /><span /><span />
                </div>
                <label class="browser-address">
                    <LockKeyhole :size="13" />
                    <input
                        v-model="browserUrl"
                        type="text"
                        aria-label="Simulation URL"
                        spellcheck="false"
                    />
                </label>
                <div class="browser-actions">
                    <button
                        v-if="isFullscreen"
                        type="button"
                        class="browser-control"
                        :title="
                            activeStage === 4
                                ? 'Check payment status'
                                : isPlaying
                                  ? 'Pause simulation'
                                  : 'Resume simulation'
                        "
                        :aria-label="
                            activeStage === 4
                                ? 'Check payment status'
                                : isPlaying
                                  ? 'Pause simulation'
                                  : 'Resume simulation'
                        "
                        @click="
                            activeStage === 4
                                ? paymentConfirmed && isManuallyPaused
                                    ? advancePaymentDemo()
                                    : verifyPayment()
                                : isPlaying
                                  ? pause()
                                  : play()
                        "
                    >
                        <CheckCircle2 v-if="activeStage === 4" :size="16" />
                        <Pause v-else-if="isPlaying" :size="16" />
                        <Play v-else :size="16" fill="currentColor" />
                    </button>
                    <button
                        v-if="fullscreenSupported"
                        type="button"
                        class="browser-control"
                        :title="
                            isFullscreen
                                ? 'Exit fullscreen'
                                : 'Enter fullscreen'
                        "
                        :aria-label="
                            isFullscreen
                                ? 'Exit fullscreen'
                                : 'Enter fullscreen'
                        "
                        :aria-pressed="isFullscreen"
                        @click="toggleFullscreen"
                    >
                        <Minimize2 v-if="isFullscreen" :size="16" />
                        <Maximize2 v-else :size="16" />
                    </button>
                </div>
            </div>
            <Transition name="stage-change" mode="out-in">
                <div
                    v-if="activeStage === 0"
                    key="portal"
                    class="screen school-screen"
                >
                    <div class="app-toolbar school-toolbar">
                        <div class="brand-lockup">
                            <span class="school-mark"
                                ><GraduationCap :size="21"
                            /></span>
                            <div>
                                <strong>Northfield Academy</strong
                                ><small>Student Records</small>
                            </div>
                        </div>
                        <div class="portal-user">AO</div>
                    </div>
                    <div class="screen-content portal-content">
                        <div class="screen-heading">
                            <div>
                                <span class="eyebrow"
                                    >2025/2026 academic session</span
                                >
                                <h4>Student result records</h4>
                            </div>
                            <span class="record-count">1 student</span>
                        </div>
                        <div class="student-table">
                            <div class="table-head">
                                <span>Student</span><span>Application ID</span
                                ><span>Result status</span><span />
                            </div>
                            <div class="student-row">
                                <div class="student-name">
                                    <span class="student-avatar">AI</span>
                                    <div>
                                        <strong>Amina Ibrahim</strong
                                        ><small>Science applicant</small>
                                    </div>
                                </div>
                                <code>STU-12345</code>
                                <span class="status-badge status-pending"
                                    >Not verified</span
                                >
                                <button
                                    type="button"
                                    class="action-button pulse-action"
                                    @click="continueFlow(1)"
                                >
                                    Verify result <ArrowRight :size="16" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="activeStage === 1"
                    key="exam-selector"
                    class="screen paygo-page"
                >
                    <div class="paygo-card selector-card">
                        <span class="result-chip">Result Verification</span>
                        <h4>Select exam result</h4>
                        <div class="select-shell">
                            <span class="floating-label">Exam</span>
                            <div class="select-value">
                                Select an examination board
                                <ChevronDown :size="18" />
                            </div>
                        </div>
                        <div class="exam-menu">
                            <button
                                v-for="board in examOptions"
                                :key="board"
                                type="button"
                                @click="continueFlow(2)"
                            >
                                <strong>{{ board }} - ₦1,500</strong>
                            </button>
                        </div>
                        <div class="portal-context">
                            <span>Portal context preserved</span
                            ><code>STU-12345 / APP-90210</code>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="activeStage === 2"
                    key="result-details"
                    class="screen paygo-page details-page"
                >
                    <div class="paygo-card details-card">
                        <span class="result-chip">Result Verification</span>
                        <h4>WAEC Result Verification</h4>
                        <button
                            type="button"
                            class="change-exam"
                            @click="selectStage(1)"
                        >
                            <ArrowLeft :size="14" /> Change exam
                        </button>
                        <div class="price-strip">
                            <span>WAEC amount</span><strong>NGN 1,500</strong>
                        </div>
                        <div class="field-grid">
                            <label v-for="field in resultFields" :key="field[0]"
                                ><span>{{ field[0] }}</span
                                ><b>{{ field[1] }}</b></label
                            >
                        </div>
                        <button
                            type="button"
                            class="paygo-action pulse-action"
                            @click="continueFlow(3)"
                        >
                            Proceed to Payment
                        </button>
                    </div>
                </div>

                <div
                    v-else-if="activeStage === 3"
                    key="confirmation"
                    class="screen paygo-page confirmation-page"
                >
                    <div class="paygo-card details-card behind-dialog">
                        <span class="result-chip">Result Verification</span>
                        <h4>WAEC Result Verification</h4>
                        <div class="price-strip">
                            <span>WAEC amount</span><strong>NGN 1,500</strong>
                        </div>
                    </div>
                    <div class="dialog-scrim">
                        <div class="confirmation-dialog">
                            <h4>Confirm your details</h4>
                            <p>
                                Please confirm that the result-check details
                                below are correct before we continue to payment.
                            </p>
                            <div class="confirmation-list">
                                <div>
                                    <span>Exam number</span><b>1234567890</b>
                                </div>
                                <div><span>Exam year</span><b>2024</b></div>
                                <div><span>Exam type</span><b>MAY/JUN</b></div>
                                <div>
                                    <span>Email</span><b>amina@example.com</b>
                                </div>
                            </div>
                            <label class="consent-row"
                                ><span
                                    ><Check :size="14" stroke-width="3" /></span
                                >I confirm that the information provided is
                                correct and belongs to me.</label
                            >
                            <div class="dialog-actions">
                                <button
                                    type="button"
                                    class="review-button"
                                    @click="selectStage(2)"
                                >
                                    Review again
                                </button>
                                <button
                                    type="button"
                                    class="confirm-button pulse-action"
                                    @click="goToPayment"
                                >
                                    Confirm and Pay
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="activeStage === 4"
                    key="payment"
                    class="screen checkout-screen"
                    @dblclick.self="advancePaymentDemo"
                >
                    <div
                        v-if="checkoutLoading"
                        class="checkout-frame-state"
                        @dblclick="advancePaymentDemo"
                    >
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
                    <div
                        v-else
                        class="checkout-frame-state checkout-failed"
                        @dblclick="advancePaymentDemo"
                    >
                        <AlertCircle :size="30" />
                        <strong>Unable to load Paystack checkout</strong>
                        <span>{{ checkoutError }}</span>
                        <button
                            type="button"
                            class="checkout-button"
                            @click="initializePaystackCheckout"
                        >
                            Try again
                        </button>
                    </div>
                </div>

                <div
                    v-else-if="activeStage === 5"
                    key="verification"
                    class="screen processing-screen"
                >
                    <div class="processing-panel">
                        <div class="scanner">
                            <ScanSearch :size="34" /><span />
                        </div>
                        <span class="eyebrow">Demonstration</span>
                        <h4>Retrieving the exam result</h4>
                        <p>
                            After payment is confirmed, EaseVerifier retrieves
                            the result. The portal then compares the student’s
                            name and maps subjects to its programme
                            requirements.
                        </p>
                        <div class="verification-steps">
                            <div class="done">
                                <CheckCircle2 :size="19" /><span
                                    >Payment checked before result
                                    retrieval</span
                                ><small>Payment step</small>
                            </div>
                            <div class="done delayed-done">
                                <CheckCircle2 :size="19" /><span
                                    >Result provider contacted</span
                                ><small>Complete</small>
                            </div>
                            <div class="active delayed-active">
                                <LoaderCircle :size="19" /><span
                                    >Returning result to school portal</span
                                ><small>Working</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else key="result" class="screen school-screen">
                    <div class="app-toolbar school-toolbar">
                        <div class="brand-lockup">
                            <span class="school-mark"
                                ><GraduationCap :size="21"
                            /></span>
                            <div>
                                <strong>Northfield Academy</strong
                                ><small>Student Records</small>
                            </div>
                        </div>
                        <span class="return-label"
                            ><FileSearch :size="16" /> Example portal
                            outcome</span
                        >
                    </div>
                    <div class="screen-content result-content">
                        <div
                            class="result-banner"
                            :class="`outcome-${scenario.tone}`"
                            aria-live="polite"
                        >
                            <span class="result-check"
                                ><BadgeCheck
                                    v-if="scenario.tone === 'success'"
                                    :size="25" /><AlertCircle v-else :size="25"
                            /></span>
                            <div>
                                <strong>{{ scenario.title }}</strong
                                ><small>{{ scenario.description }}</small>
                            </div>
                        </div>
                        <div class="candidate-strip">
                            <div>
                                <span>Portal name</span
                                ><strong>Amina Ibrahim</strong>
                            </div>
                            <div>
                                <span>Result name</span
                                ><strong>{{ scenario.candidateName }}</strong>
                            </div>
                            <div>
                                <span>Exam</span
                                ><strong>WAEC · 1234567890 · 2024</strong>
                            </div>
                            <div>
                                <span>Portal reference</span
                                ><strong>APP-90210</strong>
                            </div>
                        </div>
                        <div class="portal-checks">
                            <div>
                                <span>1. Result retrieval</span
                                ><strong>{{
                                    hasResult
                                        ? 'Verified result received'
                                        : scenario.status === 'result_pending'
                                          ? 'Still preparing'
                                          : 'Pull failed'
                                }}</strong>
                            </div>
                            <div>
                                <span>2. Ownership check</span
                                ><strong>{{ scenario.ownership }}</strong>
                            </div>
                            <div>
                                <span>3. Subject mapping</span
                                ><strong>{{ scenario.mapping }}</strong>
                            </div>
                        </div>
                        <template v-if="hasResult">
                            <div class="requirement-heading">
                                <h4>
                                    {{
                                        ownershipBlocked
                                            ? 'Returned grades · not applied to student record'
                                            : 'Required subjects and returned grades'
                                    }}
                                </h4>
                                <p>
                                    Example only: these six subjects at C6 or
                                    better. Actual requirements depend on the
                                    programme.
                                </p>
                            </div>
                            <div class="requirement-table-scroll">
                                <table class="requirement-table">
                                    <caption class="sr-only">
                                        Example programme requirements compared
                                        with the returned result
                                    </caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">
                                                Required subject
                                            </th>
                                            <th scope="col">Grade</th>
                                            <th scope="col">Source</th>
                                            <th scope="col">
                                                Example grade check
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in requirementRows"
                                            :key="row.subject"
                                        >
                                            <th scope="row">
                                                {{ row.subject }}
                                            </th>
                                            <td class="grade">
                                                {{ row.grade }}
                                            </td>
                                            <td>{{ row.source }}</td>
                                            <td :class="row.tone">
                                                {{ row.outcome }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                        <div v-else class="result-waiting">
                            <FileSearch :size="24" />
                            <p>
                                Subjects and grades appear here after a result
                                is retrieved.
                            </p>
                        </div>
                        <div class="student-next-step">
                            <strong>What should the student do?</strong>
                            <p>{{ scenario.nextStep }}</p>
                        </div>
                        <details class="portal-status-note">
                            <summary>How the portal records this case</summary>
                            <p>
                                Portal status: <code>{{ scenario.status }}</code
                                >. Ownership examples assume the school has
                                enabled ownership checks.
                            </p>
                            <p>
                                <code>mapped_ready</code> means the required
                                fields were filled. The current mapping code
                                does not enforce a C6 minimum or grant
                                admission. The grade checks above explain the
                                example requirement.
                            </p>
                        </details>
                    </div>
                </div>
            </Transition>
        </div>
    </section>
</template>

<style scoped>
.paygo-simulation {
    --ink: #17221c;
    --muted: #66716a;
    --line: #dfe5e1;
    --green: #1c6434;
    --green-dark: #0f3e20;
    --green-soft: #e9f6ef;
    --yellow: #fecd07;
    overflow: hidden;
    border: 1px solid #d7ded9;
    border-radius: 8px;
    background: #f7f9f8;
    box-shadow: 0 16px 40px rgba(23, 34, 28, 0.08);
    color: var(--ink);
}
.simulation-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 18px 22px 0px 22px;
    background: #fff;
}
.simulation-kicker,
.eyebrow {
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--green);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}
.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #21a766;
    box-shadow: 0 0 0 4px #ddf5e8;
}
.simulation-header h3 {
    margin: 4px 0 1px;
    font-size: 19px;
    line-height: 1.25;
}
.simulation-header p {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
}
.simulation-controls {
    display: flex;
    flex: 0 0 auto;
    gap: 8px;
}
.control-button,
.icon-button,
.action-button,
.paygo-action,
.review-button,
.confirm-button,
.checkout-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    font: inherit;
    font-weight: 700;
    cursor: pointer;
}
.control-button {
    min-width: 88px;
    height: 38px;
    padding: 0 14px;
    border-radius: 6px;
}
.control-button2 {
    min-width: 88px !important;
    height: 38px !important;
    padding: 0 7px !important;
    border-radius: 6px !important;
}
.control-primary {
    background: var(--green);
    color: #fff;
}
.icon-button {
    width: 38px;
    height: 38px;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #fff;
    color: #33443a;
}
button:focus-visible,
a:focus-visible {
    outline: 3px solid rgba(35, 100, 170, 0.28);
    outline-offset: 2px;
}
.stage-navigation {
    position: relative;
    display: grid;
    grid-template-columns: repeat(4, minmax(100px, 1fr));
    overflow-x: auto;
    padding: 14px 22px 16px;
    border-bottom: 1px solid var(--line);
    background: #fbfcfb;
}
.progress-track {
    position: absolute;
    top: 31px;
    right: 12.5%;
    left: 12.5%;
    height: 2px;
    overflow: hidden;
    background: #dfe6e1;
}
.progress-track span {
    display: block;
    height: 100%;
    background: var(--green);
    transition: width 450ms ease;
}
.stage-button {
    position: relative;
    z-index: 1;
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    gap: 7px;
    border: 0;
    background: transparent;
    color: #7a847e;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
}
.stage-icon {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border: 2px solid #dfe6e1;
    border-radius: 50%;
    background: #fff;
    transition: 250ms ease;
}
.stage-button.active,
.stage-button.complete {
    color: var(--green-dark);
}
.stage-button.active .stage-icon,
.stage-button.complete .stage-icon {
    border-color: var(--green);
    background: var(--green);
    color: #fff;
}
.stage-button.active .stage-icon {
    box-shadow: 0 0 0 5px var(--green-soft);
}
.simulation-viewport {
    min-height: 668px;
}
.screen {
    min-height: 620px;
}
.simulation-viewport {
    background: #edf2ef;
}
.browser-toolbar {
    display: grid;
    grid-template-columns: 58px minmax(0, 1fr) auto;
    align-items: center;
    gap: 12px;
    min-height: 48px;
    padding: 0 14px;
    border-bottom: 1px solid #d8dfe3;
    background: #f7f9fa;
}
.browser-lights {
    display: flex;
    gap: 6px;
}
.browser-lights span {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #ff665c;
}
.browser-lights span:nth-child(2) {
    background: #ffbd44;
}
.browser-lights span:nth-child(3) {
    background: #00ca4e;
}
.browser-address {
    display: flex;
    min-width: 0;
    height: 30px;
    align-items: center;
    gap: 7px;
    padding: 0 10px;
    border: 1px solid #d8e0e4;
    border-radius: 6px;
    background: #fff;
    color: #5f6d74;
}
.browser-address input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    color: #35434a;
    font: inherit;
    font-size: 11px;
    letter-spacing: 0;
}
.browser-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
}
.browser-control {
    display: grid;
    width: 28px;
    height: 28px;
    place-items: center;
    padding: 0;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: #68757b;
    cursor: pointer;
}
.browser-control:hover {
    background: #e8eef1;
    color: #263740;
}
.paygo-simulation:fullscreen {
    display: flex;
    width: 100vw;
    height: 100vh;
    flex-direction: column;
    overflow: auto;
    border: 0;
    border-radius: 0;
    background: #edf2ef;
    box-shadow: none;
}
.paygo-simulation:fullscreen .simulation-viewport {
    display: flex;
    min-height: 0;
    flex: 1 0 500px;
    flex-direction: column;
}
.paygo-simulation:fullscreen .browser-toolbar {
    flex: 0 0 48px;
}
.paygo-simulation:fullscreen .screen {
    min-height: 0;
    flex: 1 1 auto;
    overflow: auto;
}
.paygo-simulation:fullscreen .school-screen {
    display: flex;
    flex-direction: column;
}
.paygo-simulation:fullscreen .school-screen .screen-content {
    flex: 1 1 auto;
}
.paygo-simulation:fullscreen .paystack-checkout-frame,
.paygo-simulation:fullscreen .checkout-frame-state {
    height: 100%;
    min-height: 0;
}
.app-toolbar {
    display: flex;
    min-height: 70px;
    align-items: center;
    justify-content: space-between;
    padding: 0 28px;
}
.school-toolbar {
    border-bottom: 1px solid #d9e0dc;
    background: #fff;
}
.brand-lockup,
.student-name,
.paystack-heading {
    display: flex;
    align-items: center;
    gap: 11px;
}
.school-mark {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 6px;
    background: #143d59;
    color: #fff;
}
.brand-lockup div,
.student-name div,
.paystack-heading div {
    display: flex;
    flex-direction: column;
}
.brand-lockup strong {
    font-size: 14px;
}
.brand-lockup small,
.student-name small {
    color: #758078;
    font-size: 11px;
}
.portal-user {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border-radius: 50%;
    background: #e8eef2;
    color: #143d59;
    font-size: 12px;
    font-weight: 800;
}
.screen-content {
    padding: 38px;
}
.screen-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
}
.screen-heading h4,
.processing-panel h4 {
    margin: 6px 0 0;
    font-size: 23px;
    line-height: 1.25;
}
.record-count {
    color: var(--muted);
    font-size: 12px;
}
.student-table,
.results-table {
    overflow: hidden;
    border: 1px solid #dce3df;
    border-radius: 6px;
    background: #fff;
}
.table-head,
.student-row {
    display: grid;
    grid-template-columns:
        minmax(180px, 1.4fr) minmax(115px, 0.8fr) minmax(105px, 0.7fr)
        minmax(130px, auto);
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
}
.table-head {
    border-bottom: 1px solid #e3e8e5;
    background: #f6f8f7;
    color: #748078;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}
.student-avatar {
    display: grid;
    width: 36px;
    height: 36px;
    flex: 0 0 auto;
    place-items: center;
    border-radius: 50%;
    background: #e8eef2;
    color: #143d59;
    font-size: 11px;
    font-weight: 800;
}
.student-name strong {
    font-size: 13px;
}
.student-row code {
    color: #3d4b43;
    font-size: 12px;
}
.status-badge {
    width: fit-content;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
}
.status-pending {
    background: #fff5d7;
    color: #816100;
}
.status-verified {
    background: #dff5e8;
    color: #087443;
}
.action-button {
    min-height: 38px;
    padding: 0 13px;
    border-radius: 6px;
    background: #143d59;
    color: #fff;
    font-size: 12px;
}
.paygo-page {
    position: relative;
    display: grid;
    place-items: center;
    padding: 30px 18px;
    background: linear-gradient(135deg, #0f3e20 0%, #082716 58%, #04150d 100%);
}
.paygo-card {
    width: min(100%, 520px);
    padding: 26px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
}
.result-chip {
    display: inline-flex;
    padding: 6px 11px;
    border-radius: 999px;
    background: var(--yellow);
    color: #312700;
    font-size: 10px;
    font-weight: 800;
}
.paygo-card h4,
.confirmation-dialog h4 {
    margin: 14px 0 4px;
    font-size: 22px;
    line-height: 1.25;
}
.selector-card h4 {
    margin-bottom: 22px;
}
.select-shell {
    position: relative;
    height: 54px;
    border: 1px solid #87948c;
    border-radius: 4px;
    background: #fff;
}
.floating-label {
    position: absolute;
    top: -8px;
    left: 11px;
    padding: 0 4px;
    background: #fff;
    color: #5f6f65;
    font-size: 10px;
}
.select-value {
    display: flex;
    height: 100%;
    align-items: center;
    justify-content: space-between;
    padding: 0 14px;
    color: #66716a;
    font-size: 13px;
}
.exam-menu {
    margin-top: 5px;
    overflow: hidden;
    border: 1px solid #dbe2dd;
    border-radius: 4px;
    box-shadow: 0 8px 22px rgba(15, 62, 32, 0.15);
}
.exam-menu button {
    display: flex;
    width: 100%;
    min-height: 52px;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 8px 14px;
    border: 0;
    border-bottom: 1px solid #edf0ee;
    background: #fff;
    color: var(--ink);
    text-align: left;
    cursor: pointer;
}
.exam-menu button:last-child {
    border-bottom: 0;
}
.exam-menu button:hover {
    background: #f4f8f5;
}
.exam-menu button span {
    display: flex;
    flex-direction: column;
}
.exam-menu strong,
.exam-menu b {
    font-size: 12px;
}
.exam-menu small {
    margin-top: 2px;
    color: #758078;
    font-size: 10px;
}
.exam-menu b {
    color: var(--green-dark);
}
.portal-context {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 16px;
    color: #6d7871;
    font-size: 10px;
}
.portal-context code {
    color: var(--green-dark);
}
.details-page {
    align-items: start;
    overflow-y: auto;
}
.details-card {
    margin: auto;
    padding: 21px 24px;
}
.details-card h4 {
    margin-top: 10px;
    font-size: 19px;
}
.change-exam {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin: 6px 0 9px;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--green);
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}
.price-strip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    padding: 10px 12px;
    border-radius: 6px;
    background: #f6f8f6;
    color: #5f6f65;
    font-size: 11px;
}
.price-strip strong {
    color: var(--green-dark);
    font-size: 14px;
}
.field-grid {
    display: grid;
    gap: 7px;
}
.field-grid label {
    display: flex;
    min-height: 42px;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 6px 12px;
    border: 1px solid #cdd7d1;
    border-radius: 4px;
}
.field-grid span {
    color: #66716a;
    font-size: 10px;
}
.field-grid b {
    overflow-wrap: anywhere;
    color: #253129;
    font-size: 11px;
    text-align: right;
}
.paygo-action {
    width: 100%;
    min-height: 42px;
    margin-top: 11px;
    border-radius: 4px;
    background: var(--yellow);
    color: #312700;
    font-size: 12px;
}
.confirmation-page {
    overflow: hidden;
}
.behind-dialog {
    opacity: 0.75;
    transform: scale(0.98);
}
.dialog-scrim {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgba(0, 0, 0, 0.56);
}
.confirmation-dialog {
    width: min(100%, 520px);
    padding: 24px;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 18px 50px rgba(0, 0, 0, 0.28);
}
.confirmation-dialog h4 {
    margin-top: 0;
    font-size: 19px;
}
.confirmation-dialog > p {
    margin: 0 0 15px;
    color: #647068;
    font-size: 12px;
    line-height: 1.5;
}
.confirmation-list {
    overflow: hidden;
    border: 1px solid rgba(15, 62, 32, 0.12);
    border-radius: 6px;
}
.confirmation-list div {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 9px 12px;
    border-bottom: 1px solid rgba(15, 62, 32, 0.08);
    font-size: 11px;
}
.confirmation-list div:last-child {
    border-bottom: 0;
}
.confirmation-list span {
    color: #66716a;
}
.consent-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin: 14px 0;
    color: #3f4a43;
    font-size: 11px;
    line-height: 1.45;
}
.consent-row span {
    display: grid;
    width: 17px;
    height: 17px;
    flex: 0 0 auto;
    place-items: center;
    border-radius: 3px;
    background: var(--yellow);
    color: #312700;
}
.dialog-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.review-button,
.confirm-button {
    min-height: 40px;
    border-radius: 4px;
    font-size: 11px;
}
.review-button {
    border: 1px solid var(--green);
    background: #fff;
    color: var(--green);
}
.confirm-button {
    background: var(--yellow);
    color: #312700;
}
.checkout-screen {
    display: grid;
    background: #eef3f6;
}
.paystack-checkout-frame {
    width: 100%;
    height: 620px;
    border: 0;
    background: #fff;
}
.checkout-frame-state {
    display: flex;
    min-height: 620px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 30px;
    color: #0a76a8;
    text-align: center;
}
.checkout-frame-state strong {
    color: #213239;
    font-size: 16px;
}
.checkout-frame-state span {
    color: #66747b;
    font-size: 12px;
}
.checkout-failed {
    color: #a12d2d;
}
.checkout-button {
    min-height: 40px;
    margin-top: 6px;
    padding: 0 18px;
    border-radius: 5px;
    background: #0ba4db;
    color: #fff;
    font-size: 12px;
}
.processing-screen {
    display: grid;
    place-items: center;
    padding: 32px 18px;
    background: #f2f6f3;
}
.processing-panel {
    width: min(100%, 570px);
    text-align: center;
}
.processing-panel .eyebrow {
    justify-content: center;
}
.processing-panel p {
    max-width: 460px;
    margin: 10px auto 24px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.6;
}
.scanner {
    position: relative;
    display: grid;
    width: 72px;
    height: 72px;
    margin: 0 auto 16px;
    place-items: center;
    overflow: hidden;
    border: 1px solid #cce1d4;
    border-radius: 8px;
    background: #fff;
    color: var(--green);
}
.scanner span {
    position: absolute;
    right: 8px;
    left: 8px;
    height: 2px;
    background: #20a461;
    animation: scan 1.5s ease-in-out infinite;
}
.verification-steps {
    overflow: hidden;
    border: 1px solid #dbe4de;
    border-radius: 6px;
    background: #fff;
    text-align: left;
}
.verification-steps > div {
    display: grid;
    grid-template-columns: 24px 1fr auto;
    align-items: center;
    gap: 9px;
    padding: 14px 16px;
    border-bottom: 1px solid #e7ece8;
    color: #657168;
    font-size: 12px;
}
.verification-steps > div:last-child {
    border-bottom: 0;
}
.verification-steps .done {
    color: #16824d;
}
.verification-steps .active svg {
    animation: spin 1s linear infinite;
}
.verification-steps small {
    color: #8a958e;
    font-size: 10px;
}
.return-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--green);
    font-size: 11px;
    font-weight: 700;
}
.result-banner {
    display: grid;
    grid-template-columns: 40px 1fr;
    align-items: center;
    gap: 12px;
    margin-bottom: 17px;
    padding: 14px;
    border: 1px solid #cfe8d9;
    border-radius: 6px;
    background: #f1faf5;
}
.result-check {
    display: grid;
    width: 36px;
    height: 36px;
    place-items: center;
    border-radius: 50%;
    background: #dff5e8;
    color: #16824d;
}
.result-banner div {
    display: flex;
    flex-direction: column;
}
.result-banner strong {
    font-size: 13px;
}
.result-banner small {
    margin-top: 3px;
    color: #66716a;
    font-size: 10px;
}
.candidate-strip {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1px;
    overflow: hidden;
    margin-bottom: 17px;
    border: 1px solid #dce3df;
    border-radius: 6px;
    background: #dce3df;
}
.candidate-strip div {
    min-width: 0;
    padding: 11px;
    background: #fff;
}
.candidate-strip span,
.candidate-strip strong {
    display: block;
}
.candidate-strip span {
    margin-bottom: 4px;
    color: #78827c;
    font-size: 9px;
    text-transform: uppercase;
}
.candidate-strip strong {
    overflow: hidden;
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.results-table > div {
    display: grid;
    grid-template-columns: 1fr 90px 90px;
    align-items: center;
    gap: 14px;
    padding: 10px 15px;
    border-bottom: 1px solid #e7ebe8;
    font-size: 11px;
}
.results-table > div:last-child {
    border-bottom: 0;
}
.result-table-head {
    background: #f6f8f7;
    color: #748078;
    font-size: 9px !important;
    font-weight: 700;
    text-transform: uppercase;
}
.grade {
    color: #143d59;
    font-weight: 800;
}
.pass {
    color: #16824d;
    font-weight: 700;
}
.scenario-picker {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    gap: 12px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--line);
    background: #fff;
}
.scenario-picker label {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 7px;
}
.scenario-picker label > span {
    font-size: 12px;
    font-weight: 700;
}
.scenario-picker select {
    width: 100%;
    min-height: 42px;
    padding: 8px 12px;
    border: 1px solid #b9c9bf;
    border-radius: 5px;
    background: #fff;
    color: var(--ink);
    font: inherit;
    font-size: 13px;
}
.scenario-picker .review-button {
    min-height: 42px;
    padding: 8px 14px;
}
.scenario-picker > p {
    grid-column: 1/-1;
    margin: 0;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
}
.result-banner small {
    font-size: 12px;
    line-height: 1.6;
}
.outcome-warning {
    border-color: #e7d6a8;
    background: #fffaeb;
}
.outcome-warning .result-check {
    background: #fff0c2;
    color: #8a6000;
}
.outcome-error {
    border-color: #edc8c8;
    background: #fff5f5;
}
.outcome-error .result-check {
    background: #f9dddd;
    color: #a12d2d;
}
.candidate-strip strong {
    overflow: visible;
    white-space: normal;
    overflow-wrap: anywhere;
}
.portal-checks {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}
.portal-checks > div {
    padding: 12px;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #fff;
}
.portal-checks span,
.portal-checks strong {
    display: block;
}
.portal-checks span {
    margin-bottom: 5px;
    color: var(--muted);
    font-size: 11px;
}
.portal-checks strong {
    font-size: 12px;
    line-height: 1.5;
}
.requirement-heading h4 {
    margin: 0 0 5px;
    font-size: 14px;
}
.requirement-heading p {
    margin: 0 0 12px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
}
.requirement-table-scroll {
    overflow-x: auto;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #fff;
}
.requirement-table {
    width: 100%;
    min-width: 480px;
    border-collapse: collapse;
    text-align: left;
}
.requirement-table th,
.requirement-table td {
    padding: 11px 13px;
    border-bottom: 1px solid #e7ebe8;
    font-size: 12px;
}
.requirement-table thead {
    background: #f6f8f7;
    color: #66716a;
}
.requirement-table thead th {
    font-size: 10px;
}
.requirement-table tbody tr:last-child > * {
    border-bottom: 0;
}
.needs-attention {
    color: #965300;
    font-weight: 700;
}
.student-next-step {
    margin-top: 16px;
    padding: 14px;
    border-left: 3px solid var(--green);
    background: #fff;
    font-size: 13px;
}
.student-next-step p {
    margin: 5px 0 0;
    color: #526259;
    line-height: 1.6;
}
.portal-status-note {
    margin-top: 15px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}
.portal-status-note summary {
    color: var(--green);
    font-weight: 700;
    cursor: pointer;
}
.portal-status-note p {
    margin: 8px 0 0;
}
.result-waiting {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    min-height: 120px;
    padding: 20px;
    border: 1px dashed #bac8c0;
    border-radius: 6px;
    color: var(--muted);
    font-size: 13px;
}
.spin {
    animation: spin 1s linear infinite;
}
.is-playing .pulse-action {
    animation: button-pulse 1.4s ease-in-out infinite;
}
.is-paused .simulation-viewport {
    cursor: pointer;
}
.is-paused input {
    cursor: text;
}
.is-paused button,
.is-paused a {
    cursor: pointer;
}
.stage-change-enter-active,
.stage-change-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}
.stage-change-enter-from {
    opacity: 0;
    transform: translateX(10px);
}
.stage-change-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}
@keyframes scan {
    0%,
    100% {
        transform: translateY(-22px);
        opacity: 0.45;
    }
    50% {
        transform: translateY(22px);
        opacity: 1;
    }
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
@keyframes button-pulse {
    0%,
    100% {
        box-shadow: 0 0 0 0 rgba(28, 100, 52, 0);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(28, 100, 52, 0.16);
    }
}
@media (max-width: 760px) {
    .simulation-header {
        align-items: flex-start;
        padding: 16px;
    }
    .simulation-controls .control-button span {
        display: none;
    }
    .simulation-controls .control-button {
        min-width: 38px;
        padding: 0;
    }
    .stage-navigation {
        grid-template-columns: repeat(4, minmax(82px, 1fr));
        padding-right: 14px;
        padding-left: 14px;
    }
    .progress-track {
        display: none;
    }
    .simulation-viewport {
        min-height: 738px;
    }
    .screen {
        min-height: 690px;
    }
    .browser-toolbar {
        grid-template-columns: 42px minmax(0, 1fr) auto;
        gap: 7px;
        padding: 0 10px;
    }
    .browser-address input {
        font-size: 10px;
    }
    .screen-content {
        padding: 24px 16px;
    }
    .table-head {
        display: none;
    }
    .student-row {
        grid-template-columns: 1fr auto;
        gap: 14px;
    }
    .student-row code,
    .action-button {
        grid-column: 1/-1;
    }
    .action-button {
        width: 100%;
    }
    .portal-context {
        align-items: flex-start;
        flex-direction: column;
    }
    .paygo-page,
    .checkout-screen {
        padding: 20px 12px;
    }
    .paygo-card,
    .confirmation-dialog {
        padding: 20px;
    }
    .paystack-checkout-frame,
    .checkout-frame-state {
        min-height: 690px;
        height: 690px;
    }
    .candidate-strip {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .results-table > div {
        grid-template-columns: 1fr 56px 54px;
        gap: 8px;
        padding: 10px;
    }
    .return-label {
        max-width: 145px;
        text-align: right;
    }
    .simulation-header {
        flex-wrap: wrap;
        gap: 14px;
    }
    .simulation-header h3 {
        font-size: 17px;
    }
    .simulation-header p {
        line-height: 1.5;
    }
    .scenario-picker {
        grid-template-columns: 1fr;
        padding: 16px;
    }
    .scenario-picker .review-button {
        width: 100%;
    }
    .portal-checks {
        grid-template-columns: 1fr;
        gap: 7px;
    }
    .portal-checks > div {
        padding: 10px 12px;
    }
    .result-banner {
        align-items: start;
    }
}
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
