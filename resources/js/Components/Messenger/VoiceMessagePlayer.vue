<script setup>
import { onUnmounted, ref } from 'vue';

const props = defineProps({
    src: {
        type: String,
        required: true,
    },
    outbound: {
        type: Boolean,
        default: false,
    },
});

const audioEl = ref(null);
const playing = ref(false);
const currentTime = ref(0);
const duration = ref(0);

const waveformHeights = [4, 7, 10, 6, 12, 8, 14, 5, 11, 7, 9, 6, 13, 8, 10, 5, 12, 7, 9, 6, 11, 8, 5];

function formatTime(value) {
    if (!Number.isFinite(value) || value < 0) {
        return '0:00';
    }

    const total = Math.floor(value);
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

function togglePlayback() {
    const audio = audioEl.value;

    if (!audio) {
        return;
    }

    if (playing.value) {
        audio.pause();

        return;
    }

    audio.play();
}

function onTimeUpdate() {
    currentTime.value = audioEl.value?.currentTime ?? 0;
}

function onLoadedMetadata() {
    duration.value = audioEl.value?.duration ?? 0;
}

function onPlay() {
    playing.value = true;
}

function onPause() {
    playing.value = false;
}

function onEnded() {
    playing.value = false;
    currentTime.value = 0;

    if (audioEl.value) {
        audioEl.value.currentTime = 0;
    }
}

function progressRatio() {
    if (!duration.value) {
        return 0;
    }

    return Math.min(1, currentTime.value / duration.value);
}

onUnmounted(() => {
    audioEl.value?.pause();
});
</script>

<template>
    <div class="flex w-[min(100%,13.5rem)] items-center gap-2 sm:w-[min(100%,15rem)]">
        <button
            type="button"
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white shadow-sm transition active:scale-95"
            :class="outbound ? 'bg-[#00a884]' : 'bg-[#8696a0]'"
            @click="togglePlayback"
        >
            <svg
                v-if="!playing"
                class="ml-0.5 h-3.5 w-3.5"
                viewBox="0 0 24 24"
                fill="currentColor"
            >
                <path d="M8 5v14l11-7z" />
            </svg>
            <svg
                v-else
                class="h-3.5 w-3.5"
                viewBox="0 0 24 24"
                fill="currentColor"
            >
                <path d="M6 5h4v14H6V5zm8 0h4v14h-4V5z" />
            </svg>
        </button>

        <div class="min-w-0 flex-1">
            <div class="flex h-4 items-end gap-px">
                <span
                    v-for="(height, index) in waveformHeights"
                    :key="index"
                    class="w-[3px] rounded-full transition-colors"
                    :class="index / waveformHeights.length <= progressRatio()
                        ? (outbound ? 'bg-[#53bdeb]' : 'bg-[#00a884]')
                        : (outbound ? 'bg-[#53bdeb]/35' : 'bg-[#00a884]/35')"
                    :style="{ height: `${height}px` }"
                />
            </div>
            <p class="mt-0.5 text-[10px] leading-none text-[#667781]">
                {{ formatTime(playing || currentTime > 0 ? currentTime : duration) }}
            </p>
        </div>

        <audio
            ref="audioEl"
            :src="src"
            preload="metadata"
            class="hidden"
            @timeupdate="onTimeUpdate"
            @loadedmetadata="onLoadedMetadata"
            @play="onPlay"
            @pause="onPause"
            @ended="onEnded"
        />
    </div>
</template>
