

<script setup>
    import { ref, computed } from 'vue'

    const props = defineProps({
        items: { type: Array, default: () => [] } // [{ division: 'FAD', total: 12 }, ...]
    })

    const total = computed(() => props.items.reduce((s, r) => s + Number(r.total || 0), 0))
    const sorted = computed(() =>
    [...props.items].sort((a, b) => b.total - a.total)
    )

    function pct(n) {
        const t = total.value || 1
        return `${Math.round((Number(n || 0) / t) * 100)}%`
    }
</script>

<template>
    <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
        <div class="flex items-center justify-between mb-4">
            <div class="">
                <h3 class="font-bold text-base text-blueGray-700">
                    No. of support per division/agency
                </h3>
                <span class="text-sm text-gray-500">Total: {{ total }}</span>
            </div>
        </div>
        <div v-if="items.length" class="space-y-3">
            <div v-for="row in sorted" :key="row.division" class="space-y-1">
                <div class="flex items-center justify-between">
                <div class="text-sm font-medium text-gray-700">
                    {{ row.division ?? '—' }}
                </div>
                <div class="text-sm tabular-nums">
                    {{ row.total.toLocaleString() }}
                    <span class="text-gray-500"> ({{ pct(row.total) }})</span>
                </div>
                </div>
                <div class="h-2 w-full bg-gray-100 rounded">
                <div
                    class="h-2 rounded bg-indigo-500"
                    :style="{ width: pct(row.total) }"
                />
                </div>
            </div>
        </div>
        <div v-else class="text-sm text-gray-500">No data for the selected month/year.</div>
    </div>
</template>
