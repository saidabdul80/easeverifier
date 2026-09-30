<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ExternalLink, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const phoneNumber = '2348113074019';
const supportMessage =
    'Hello EaseVerifier, I would like help with your verification services.';
const whatsappUrl = computed(
    () =>
        `https://wa.me/${phoneNumber}?text=${encodeURIComponent(supportMessage)}`,
);

const isOpen = ref(false);
const isVisible = ref(false);
let openTimer: ReturnType<typeof setTimeout> | undefined;
let removeNavigateListener: (() => void) | undefined;

const hiddenPathPattern = /^\/(admin|paygo)(\/|$)/;

function syncVisibility(): void {
    isVisible.value = !hiddenPathPattern.test(window.location.pathname);
}

function openChat(): void {
    if (openTimer) window.clearTimeout(openTimer);
    window.sessionStorage.removeItem('easeverifier-whatsapp-closed');
    isOpen.value = true;
}

function closeChat(): void {
    if (openTimer) window.clearTimeout(openTimer);
    window.sessionStorage.setItem('easeverifier-whatsapp-closed', 'true');
    isOpen.value = false;
}

function handleEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape' && isOpen.value) {
        closeChat();
    }
}

onMounted(() => {
    syncVisibility();
    const wasClosed =
        window.sessionStorage.getItem('easeverifier-whatsapp-closed') ===
        'true';

    if (!wasClosed) {
        openTimer = window.setTimeout(() => {
            isOpen.value = true;
        }, 650);
    }

    window.addEventListener('keydown', handleEscape);
    removeNavigateListener = router.on('navigate', syncVisibility);
});

onBeforeUnmount(() => {
    if (openTimer) window.clearTimeout(openTimer);
    window.removeEventListener('keydown', handleEscape);
    removeNavigateListener?.();
});
</script>

<template>
    <div v-if="isVisible" class="whatsapp-widget">
        <Transition name="whatsapp-panel">
            <section
                v-if="isOpen"
                id="easeverifier-whatsapp-chat"
                class="whatsapp-panel"
                aria-label="EaseVerifier WhatsApp support"
                aria-live="polite"
            >
                <header class="whatsapp-header">
                    <div class="whatsapp-brand">
                        <span class="whatsapp-brand-mark" aria-hidden="true">
                            <img src="/ashlabtech.png" alt="" />
                        </span>
                        <span class="whatsapp-brand-copy">
                            <strong>EaseVerifier Online</strong>
                            <span><i aria-hidden="true"></i>Online</span>
                        </span>
                    </div>

                    <button
                        class="whatsapp-close"
                        type="button"
                        aria-label="Close WhatsApp support"
                        title="Close WhatsApp support"
                        @click="closeChat"
                    >
                        <X :size="20" :stroke-width="2" aria-hidden="true" />
                    </button>
                </header>

                <div class="whatsapp-conversation">
                    <div class="whatsapp-message">
                        <strong>Welcome to EaseVerifier</strong>
                        <span>How can we help you today?</span>
                        <time>now</time>
                    </div>
                </div>

                <footer class="whatsapp-footer">
                    <a
                        class="whatsapp-action"
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Chat with EaseVerifier on WhatsApp"
                    >
                        <v-icon
                            icon="mdi-whatsapp"
                            size="25"
                            aria-hidden="true"
                        />
                        <span>WhatsApp Us</span>
                        <ExternalLink
                            :size="17"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                    </a>
                </footer>
            </section>
        </Transition>

        <button
            class="whatsapp-launcher"
            type="button"
            :aria-expanded="isOpen"
            aria-controls="easeverifier-whatsapp-chat"
            :aria-label="
                isOpen ? 'Close WhatsApp support' : 'Open WhatsApp support'
            "
            :title="
                isOpen ? 'Close WhatsApp support' : 'Chat with us on WhatsApp'
            "
            @click="isOpen ? closeChat() : openChat()"
        >
            <v-icon icon="mdi-whatsapp" size="32" aria-hidden="true" />
            <span
                v-if="!isOpen"
                class="whatsapp-pulse"
                aria-hidden="true"
            ></span>
        </button>
    </div>
</template>

<style scoped>
.whatsapp-widget {
    position: fixed;
    right: 24px;
    bottom: 22px;
    z-index: 1900;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 14px;
    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        sans-serif;
    letter-spacing: 0;
}

.whatsapp-panel {
    width: min(382px, calc(100vw - 32px));
    overflow: hidden;
    border-radius: 8px;
    background: #ffffff;
    box-shadow:
        0 18px 52px rgba(19, 42, 32, 0.2),
        0 4px 14px rgba(19, 42, 32, 0.1);
    transform-origin: right bottom;
}

.whatsapp-header {
    display: flex;
    min-height: 84px;
    align-items: center;
    justify-content: space-between;
    padding: 15px 16px 15px 18px;
    color: #ffffff;
    background: #075e4b;
}

.whatsapp-brand {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 12px;
}

.whatsapp-brand-mark {
    display: grid;
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    place-items: center;
    overflow: hidden;
    border: 2px solid rgba(255, 255, 255, 0.88);
    border-radius: 50%;
    background: #ffffff;
}

.whatsapp-brand-mark img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.whatsapp-brand-copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 4px;
}

.whatsapp-brand-copy strong {
    overflow: hidden;
    font-size: 17px;
    font-weight: 700;
    line-height: 1.2;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.whatsapp-brand-copy span {
    display: flex;
    align-items: center;
    gap: 6px;
    color: rgba(255, 255, 255, 0.84);
    font-size: 13px;
    line-height: 1.2;
}

.whatsapp-brand-copy i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #25d366;
    box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.17);
}

.whatsapp-close {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    place-items: center;
    border: 0;
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.9);
    background: transparent;
    cursor: pointer;
    transition:
        background-color 160ms ease,
        transform 160ms ease;
}

.whatsapp-close:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: rotate(5deg);
}

.whatsapp-close:focus-visible,
.whatsapp-launcher:focus-visible,
.whatsapp-action:focus-visible {
    outline: 3px solid #f4c74c;
    outline-offset: 3px;
}

.whatsapp-conversation {
    display: flex;
    min-height: 192px;
    align-items: flex-start;
    padding: 29px 22px;
    background-color: #efeae2;
    background-image: url('/images/whatsapp-chat-bg.webp');
    background-repeat: no-repeat;
    background-position: center 43%;
    background-size: cover;
}

.whatsapp-message {
    position: relative;
    display: flex;
    width: min(292px, calc(100% - 16px));
    flex-direction: column;
    gap: 4px;
    padding: 13px 44px 17px 15px;
    border-radius: 7px;
    color: #17221d;
    background: #d9fdd3;
    box-shadow: 0 1px 2px rgba(17, 27, 22, 0.14);
    animation: whatsapp-message-in 480ms 400ms cubic-bezier(0.22, 1, 0.36, 1)
        both;
}

.whatsapp-message::before {
    position: absolute;
    top: 0;
    left: -8px;
    width: 0;
    height: 0;
    border-top: 10px solid #d9fdd3;
    border-left: 10px solid transparent;
    content: '';
}

.whatsapp-message strong {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.4;
}

.whatsapp-message span {
    font-size: 15px;
    line-height: 1.45;
}

.whatsapp-message time {
    position: absolute;
    right: 9px;
    bottom: 7px;
    color: #68766f;
    font-size: 10px;
    line-height: 1;
}

.whatsapp-footer {
    padding: 14px 16px 15px;
    background: #ffffff;
}

.whatsapp-action {
    display: grid;
    min-height: 50px;
    grid-template-columns: 25px auto 17px;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border-radius: 999px;
    color: #ffffff;
    background: #1fbd5a;
    box-shadow: 0 8px 20px rgba(37, 211, 102, 0.22);
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
    transition:
        background-color 160ms ease,
        box-shadow 160ms ease,
        transform 160ms ease;
}

.whatsapp-action:hover {
    background: #1fbd5a;
    box-shadow: 0 11px 24px rgba(37, 211, 102, 0.3);
    transform: translateY(-1px);
}

.whatsapp-launcher {
    position: relative;
    display: grid;
    width: 60px;
    height: 60px;
    place-items: center;
    overflow: visible;
    /* border: 3px solid #ffffff; */
    border-radius: 50%;
    color: #ffffff;
    background: #25d366;
    box-shadow: 0 10px 30px rgba(19, 42, 32, 0.26);
    cursor: pointer;
    transition:
        background-color 160ms ease,
        transform 180ms ease,
        box-shadow 180ms ease;
}

.whatsapp-launcher:hover {
    background: #1fbd5a;
    box-shadow: 0 13px 34px rgba(19, 42, 32, 0.32);
    transform: translateY(-2px) scale(1.03);
}

.whatsapp-pulse {
    position: absolute;
    inset: -3px;
    z-index: -1;
    border-radius: 50%;
    background: rgba(37, 211, 102, 0.4);
    animation: whatsapp-pulse 2.4s ease-out infinite;
}

.whatsapp-panel-enter-active {
    transition:
        opacity 280ms ease,
        transform 430ms cubic-bezier(0.16, 1, 0.3, 1);
}

.whatsapp-panel-leave-active {
    transition:
        opacity 180ms ease,
        transform 220ms ease;
}

.whatsapp-panel-enter-from,
.whatsapp-panel-leave-to {
    opacity: 0;
    transform: translateY(18px) scale(0.94);
}

@keyframes whatsapp-message-in {
    from {
        opacity: 0;
        transform: translateY(9px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes whatsapp-pulse {
    0% {
        opacity: 0.55;
        transform: scale(1);
    }
    75%,
    100% {
        opacity: 0;
        transform: scale(1.42);
    }
}

@media (max-width: 640px) {
    .whatsapp-widget {
        right: 12px;
        bottom: max(14px, env(safe-area-inset-bottom));
    }

    .whatsapp-panel {
        width: calc(100vw - 24px);
    }

    .whatsapp-header {
        min-height: 76px;
    }

    .whatsapp-conversation {
        min-height: 168px;
        padding: 24px 18px;
    }

    .whatsapp-launcher {
        width: 56px;
        height: 56px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .whatsapp-message,
    .whatsapp-pulse {
        animation: none;
    }

    .whatsapp-panel-enter-active,
    .whatsapp-panel-leave-active,
    .whatsapp-action,
    .whatsapp-close,
    .whatsapp-launcher {
        transition: none;
    }
}

@media print {
    .whatsapp-widget {
        display: none;
    }
}
</style>
