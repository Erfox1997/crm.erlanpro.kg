<script setup>
import { ref } from 'vue';

const props = defineProps({
    targetId: {
        type: String,
        required: true,
    },
    filename: {
        type: String,
        required: true,
    },
});

const downloading = ref(false);
const error = ref('');

async function downloadPdf() {
    if (downloading.value) {
        return;
    }

    const el = document.getElementById(props.targetId);
    if (!el) {
        error.value = 'Не удалось найти текст документа.';
        return;
    }

    downloading.value = true;
    error.value = '';

    try {
        const html2pdf = (await import('html2pdf.js')).default;

        await html2pdf()
            .set({
                margin: [12, 12, 14, 12],
                filename: props.filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff',
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'] },
            })
            .from(el)
            .save();
    } catch (e) {
        error.value =
            'Не удалось сформировать PDF. Попробуйте ещё раз или используйте «Печать → сохранить как PDF» в браузере.';
        console.error(e);
    } finally {
        downloading.value = false;
    }
}
</script>

<template>
    <div class="mt-10 border-t border-slate-200 pt-8">
        <div
            class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-5 py-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-sm font-semibold text-slate-900">
                    Скачать документ
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    PDF-файл для сохранения у себя (удобно для архива или
                    ознакомления офлайн).
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="downloading"
                @click="downloadPdf"
            >
                <svg
                    class="h-4 w-4 shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"
                    />
                </svg>
                {{ downloading ? 'Формируем PDF…' : 'Скачать PDF' }}
            </button>
        </div>
        <p v-if="error" class="mt-2 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
