<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick, computed } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    clientReferenceNo: {
        type: String,
        required: true,
    },
    senderRole: {
        type: String,
        default: 'staff', // 'staff' | 'client'
        validator: (v) => ['staff', 'client'].includes(v),
    },
    senderName: {
        type: String,
        default: '',
    },
})

const page = usePage()
const messages = ref([])
const newMessage = ref('')
const loading = ref(false)
const sending = ref(false)
const chatContainer = ref(null)
let pollTimer = null

const resolvedSenderName = computed(() => {
    if (props.senderName) return props.senderName
    if (props.senderRole === 'staff') {
        return page.props?.auth?.user?.name || 'Support Staff'
    }
    return 'Client'
})

const scrollToBottom = async () => {
    await nextTick()
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight
    }
}

const fetchMessages = async (silent = false) => {
    if (!props.clientReferenceNo) return
    if (!silent) loading.value = true

    try {
        const url = `/client/ticket/${encodeURIComponent(props.clientReferenceNo)}/messages`
        const { data } = await axios.get(url)
        const previousLength = messages.value.length
        messages.value = Array.isArray(data) ? data : []

        if (messages.value.length > previousLength || !silent) {
            await scrollToBottom()
        }
    } catch (err) {
        console.error('Failed to load messages', err)
    } finally {
        if (!silent) loading.value = false
    }
}

const sendMessage = async () => {
    const text = newMessage.value.trim()
    if (!text || sending.value || !props.clientReferenceNo) return

    sending.value = true
    try {
        const url = props.senderRole === 'staff'
            ? `/admin/tickets/${encodeURIComponent(props.clientReferenceNo)}/messages`
            : `/client/ticket/${encodeURIComponent(props.clientReferenceNo)}/messages`

        await axios.post(url, {
            message: text,
            sender_type: props.senderRole,
            sender_name: resolvedSenderName.value,
        })

        newMessage.value = ''
        await fetchMessages(true)
        await scrollToBottom()
    } catch (err) {
        console.error('Failed to send message', err)
    } finally {
        sending.value = false
    }
}

const onKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        sendMessage()
    }
}

const formatTime = (ts) => {
    if (!ts) return ''
    try {
        const d = new Date(ts)
        return isNaN(d.getTime()) ? ts : d.toLocaleString('en-PH', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        })
    } catch {
        return ts
    }
}

watch(
    () => props.clientReferenceNo,
    (newRef) => {
        if (newRef) {
            fetchMessages()
        }
    },
    { immediate: true }
)

onMounted(() => {
    // Poll every 8 seconds for new messages
    pollTimer = setInterval(() => {
        fetchMessages(true)
    }, 8000)
})

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
    <div class="flex flex-col h-full bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
        <!-- Thread Header -->
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <h4 class="text-sm font-semibold text-gray-800">Direct Communication</h4>
                <span class="text-xs bg-blue-100 text-blue-800 font-medium px-2 py-0.5 rounded-full">
                    {{ messages.length }}
                </span>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Live Thread</span>
                <button
                    type="button"
                    class="ml-1 text-gray-400 hover:text-blue-900 transition"
                    title="Refresh messages"
                    @click="fetchMessages(false)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Chat messages area -->
        <div
            ref="chatContainer"
            class="flex-1 p-4 overflow-y-auto space-y-3 min-h-[220px] max-h-[340px] bg-slate-50/50"
        >
            <div v-if="loading && messages.length === 0" class="flex items-center justify-center py-8 text-gray-400 text-sm">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                Loading messages…
            </div>

            <div v-else-if="messages.length === 0" class="text-center py-8">
                <div class="w-10 h-10 mx-auto rounded-full bg-blue-50 text-blue-900 flex items-center justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <p class="text-xs font-medium text-gray-600">No messages yet</p>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    {{ senderRole === 'staff' ? 'Reach out to the client directly on this ticket.' : 'Ask support staff for updates or clarify details.' }}
                </p>
            </div>

            <!-- Message bubbles -->
            <div
                v-for="msg in messages"
                :key="msg.id"
                class="flex flex-col"
                :class="msg.sender_type === senderRole ? 'items-end' : 'items-start'"
            >
                <div class="flex items-center gap-1.5 mb-1 text-[11px] text-gray-500">
                    <span
                        class="font-semibold"
                        :class="msg.sender_type === 'staff' ? 'text-blue-900' : 'text-emerald-700'"
                    >
                        {{ msg.sender_name || (msg.sender_type === 'staff' ? 'Staff' : 'Client') }}
                    </span>
                    <span class="text-[10px] px-1.5 py-0.2 rounded border text-gray-600"
                        :class="msg.sender_type === 'staff' ? 'bg-blue-50 border-blue-200' : 'bg-emerald-50 border-emerald-200'">
                        {{ msg.sender_type === 'staff' ? 'Staff' : 'Client' }}
                    </span>
                    <span>·</span>
                    <span class="text-[10px] text-gray-400">{{ formatTime(msg.created_at) }}</span>
                </div>

                <div
                    class="max-w-[85%] rounded-2xl px-3.5 py-2 text-sm shadow-sm whitespace-pre-wrap break-words leading-relaxed"
                    :class="msg.sender_type === senderRole
                        ? (senderRole === 'staff' ? 'bg-blue-900 text-white rounded-tr-none' : 'bg-sky-600 text-white rounded-tr-none')
                        : 'bg-white text-gray-800 border border-gray-200 rounded-tl-none'"
                >
                    {{ msg.message }}
                </div>
            </div>
        </div>

        <!-- Compose Box -->
        <div class="p-3 bg-white border-t border-gray-200">
            <div class="relative">
                <textarea
                    v-model="newMessage"
                    rows="2"
                    placeholder="Type a message… (Enter to send, Shift+Enter for newline)"
                    class="w-full text-xs sm:text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 p-2.5 pr-20 resize-none transition"
                    :disabled="sending"
                    @keydown="onKeyDown"
                ></textarea>
                <div class="absolute right-2 bottom-2.5">
                    <button
                        type="button"
                        :disabled="sending || !newMessage.trim()"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-md shadow-sm transition disabled:opacity-40 disabled:cursor-not-allowed text-white"
                        :class="senderRole === 'staff' ? 'bg-blue-900 hover:bg-blue-800' : 'bg-sky-600 hover:bg-sky-700'"
                        @click="sendMessage"
                    >
                        <span v-if="sending">…</span>
                        <template v-else>
                            <span>Send</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </template>
                    </button>
                </div>
            </div>
            <div class="mt-1 flex justify-between text-[10px] text-gray-400 px-1">
                <span>Sending as <strong class="text-gray-600">{{ resolvedSenderName }}</strong></span>
                <span>{{ newMessage.length }}/2000</span>
            </div>
        </div>
    </div>
</template>

