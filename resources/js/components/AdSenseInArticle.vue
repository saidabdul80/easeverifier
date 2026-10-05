<script setup lang="ts">
import { onMounted, ref } from 'vue';

const adsenseClient = 'ca-pub-5615909705062666';
const adsenseScriptSelector = 'script[data-adsense-loader="true"]';
const adElement = ref<HTMLElement | null>(null);

const ensureAdsenseScript = () => {
    if (document.head.querySelector(adsenseScriptSelector)) {
        return;
    }

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${adsenseClient}`;
    script.crossOrigin = 'anonymous';
    script.dataset.adsenseLoader = 'true';
    document.head.appendChild(script);
};

const initializeAd = () => {
    if (!adElement.value || adElement.value.dataset.adInitialized === 'true') {
        return;
    }

    try {
        const adsenseWindow = window as typeof window & { adsbygoogle?: Array<Record<string, never>> };
        (adsenseWindow.adsbygoogle ??= []).push({});
        adElement.value.dataset.adInitialized = 'true';
    } catch {
        // Ad blockers and browser privacy settings may prevent initialization.
    }
};

onMounted(() => {
    ensureAdsenseScript();
    initializeAd();
});
</script>

<template>
    <div class="in-article-ad" aria-label="Advertisement">
        <ins
            ref="adElement"
            class="adsbygoogle"
            style="display: block; text-align: center"
            data-ad-layout="in-article"
            data-ad-format="fluid"
            data-ad-client="ca-pub-5615909705062666"
            data-ad-slot="6577438014"
        />
    </div>
</template>

<style scoped>
.in-article-ad {
    width: 100%;
    min-height: 120px;
    margin: 2.5rem auto;
    overflow: hidden;
}
</style>
