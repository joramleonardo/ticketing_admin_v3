<script setup>
import { computed } from 'vue'

const props = defineProps({
  logs: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

// Sort logs in chronological order or reverse chronological
const sortedLogs = computed(() => {
  return [...props.logs].sort((a, b) => {
    const da = new Date(a.activity_date || a.created_at || 0)
    const db = new Date(b.activity_date || b.created_at || 0)
    return db - da // newest first
  })
})

function formatDateTime(val) {
  if (!val) return '—'
  const d = new Date(val)
  if (isNaN(d.getTime())) return String(val)
  return d.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })
}

function timeAgo(val) {
  if (!val) return ''
  const d = new Date(val)
  if (isNaN(d.getTime())) return ''
  const diffSec = Math.floor((Date.now() - d.getTime()) / 1000)
  if (diffSec < 60) return 'just now'
  const diffMin = Math.floor(diffSec / 60)
  if (diffMin < 60) return `${diffMin}m ago`
  const diffHours = Math.floor(diffMin / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  const diffDays = Math.floor(diffHours / 24)
  if (diffDays < 7) return `${diffDays}d ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function getLogMeta(name = '') {
  const lower = String(name).toLowerCase()
  if (lower.includes('creat') || lower.includes('filed') || lower.includes('submit')) {
    return {
      bg: 'bg-blue-100 text-blue-700 border-blue-300',
      icon: '📝',
      dot: 'bg-blue-600 ring-blue-100',
      label: 'Created',
    }
  }
  if (lower.includes('assign') || lower.includes('reassign')) {
    return {
      bg: 'bg-indigo-100 text-indigo-700 border-indigo-300',
      icon: '👤',
      dot: 'bg-indigo-600 ring-indigo-100',
      label: 'Assigned',
    }
  }
  if (lower.includes('attend')) {
    return {
      bg: 'bg-amber-100 text-amber-700 border-amber-300',
      icon: '⚡',
      dot: 'bg-amber-600 ring-amber-100',
      label: 'Attended',
    }
  }
  if (lower.includes('complet')) {
    return {
      bg: 'bg-emerald-100 text-emerald-700 border-emerald-300',
      icon: '✅',
      dot: 'bg-emerald-600 ring-emerald-100',
      label: 'Completed',
    }
  }
  if (lower.includes('close')) {
    return {
      bg: 'bg-slate-200 text-slate-800 border-slate-300',
      icon: '🔒',
      dot: 'bg-slate-700 ring-slate-100',
      label: 'Closed',
    }
  }
  if (lower.includes('rat') || lower.includes('survey')) {
    return {
      bg: 'bg-purple-100 text-purple-700 border-purple-300',
      icon: '⭐',
      dot: 'bg-purple-600 ring-purple-100',
      label: 'Rated',
    }
  }
  if (lower.includes('message') || lower.includes('repl')) {
    return {
      bg: 'bg-sky-100 text-sky-700 border-sky-300',
      icon: '💬',
      dot: 'bg-sky-600 ring-sky-100',
      label: 'Message',
    }
  }
  return {
    bg: 'bg-slate-100 text-slate-700 border-slate-200',
    icon: '🔄',
    dot: 'bg-slate-500 ring-slate-100',
    label: 'Activity',
  }
}
</script>

<template>
  <div class="w-full">
    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-4 py-2 animate-pulse">
      <div v-for="i in 3" :key="i" class="flex items-start gap-3">
        <div class="w-8 h-8 rounded-full bg-slate-200 shrink-0"></div>
        <div class="flex-1 space-y-1.5">
          <div class="h-3.5 bg-slate-200 rounded w-1/3"></div>
          <div class="h-2.5 bg-slate-100 rounded w-1/4"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="sortedLogs.length === 0" class="py-6 text-center text-xs text-slate-400 italic">
      No activity logs recorded yet.
    </div>

    <!-- Timeline List -->
    <div v-else class="relative pl-6 space-y-6 before:absolute before:left-3 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
      <div
        v-for="(log, idx) in sortedLogs"
        :key="log.activity_id || log.id || idx"
        class="relative flex items-start gap-3 group"
      >
        <!-- Milestone Icon Node -->
        <div
          class="absolute -left-6 top-0 w-6 h-6 rounded-full flex items-center justify-center text-xs border bg-white shadow-xs transition-transform group-hover:scale-110"
          :class="getLogMeta(log.activity_name).bg"
        >
          <span>{{ getLogMeta(log.activity_name).icon }}</span>
        </div>

        <!-- Content Card -->
        <div class="flex-1 bg-slate-50/80 hover:bg-slate-50 rounded-xl p-3 border border-slate-200/80 transition shadow-2xs">
          <div class="flex items-center justify-between gap-2 flex-wrap">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-bold text-slate-800">
                {{ log.activity_name }}
              </span>
              <span
                v-if="log.activity_user"
                class="text-[11px] px-2 py-0.5 rounded-full font-medium bg-white border border-slate-200 text-slate-600 shadow-2xs"
              >
                👤 {{ log.activity_user }}
              </span>
            </div>

            <!-- Timestamp & Time-ago -->
            <div class="flex items-center gap-1.5 text-[11px] text-slate-400">
              <span class="font-mono">{{ formatDateTime(log.activity_date || log.created_at) }}</span>
              <span v-if="timeAgo(log.activity_date || log.created_at)" class="text-slate-400">
                ({{ timeAgo(log.activity_date || log.created_at) }})
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

