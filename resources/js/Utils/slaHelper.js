/**
 * SLA & Turnaround Time (TAT) Calculation Helper
 *
 * Target SLA: 24 Hours
 * Warning SLA: 48 Hours
 */

export function parseDate(dateStr) {
    if (!dateStr) return null
    if (dateStr instanceof Date) return isNaN(dateStr.getTime()) ? null : dateStr

    const s = String(dateStr).trim()
    if (!s) return null

    // If ISO with timezone or standard format
    if (s.endsWith('Z') || /[+-]\d{2}:?\d{2}$/.test(s)) {
        const d = new Date(s)
        return isNaN(d.getTime()) ? null : d
    }

    // Typical MySQL / Laravel format "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DD"
    const parsed = new Date(s.replace(' ', 'T'))
    return isNaN(parsed.getTime()) ? null : parsed
}

export function formatDuration(totalMinutes) {
    const minutes = Math.max(0, Math.round(totalMinutes))
    if (minutes < 60) {
        return `${minutes}m`
    }

    const hours = Math.floor(minutes / 60)
    const remMin = minutes % 60

    if (hours < 24) {
        return remMin > 0 ? `${hours}h ${remMin}m` : `${hours}h`
    }

    const days = Math.floor(hours / 24)
    const remHours = hours % 24

    return remHours > 0 ? `${days}d ${remHours}h` : `${days}d`
}

export function getTicketSlaStatus(ticket) {
    if (!ticket) {
        return {
            type: 'unknown',
            state: 'unknown',
            label: 'No Data',
            badgeClass: 'bg-gray-100 text-gray-500 border-gray-200',
            dotClass: 'bg-gray-400',
            elapsedHours: 0,
            formattedDuration: '—',
            isBreached: false,
            isWarning: false,
        }
    }

    const detail = ticket.detail || ticket
    const status = (detail.ticket_status || ticket.ticket_status || '').trim()

    const rawReq = detail.date_requested || ticket.date_requested || detail.created_at || ticket.created_at
    const start = parseDate(rawReq)

    if (!start) {
        return {
            type: 'unknown',
            state: 'unknown',
            label: 'No Date',
            badgeClass: 'bg-gray-100 text-gray-500 border-gray-200',
            dotClass: 'bg-gray-400',
            elapsedHours: 0,
            formattedDuration: '—',
            isBreached: false,
            isWarning: false,
        }
    }

    const isResolved = status === 'Completed' || status === 'Closed'

    if (isResolved) {
        const rawComp = detail.date_completed || ticket.date_completed || detail.updated_at || ticket.updated_at
        const end = parseDate(rawComp) || new Date()
        const diffMinutes = Math.max(0, (end.getTime() - start.getTime()) / (1000 * 60))
        const diffHours = Math.round((diffMinutes / 60) * 10) / 10
        const formatted = formatDuration(diffMinutes)
        const isBreached = diffMinutes > 24 * 60

        return {
            type: 'tat',
            state: isBreached ? 'resolved_after_sla' : 'resolved_within_sla',
            label: isBreached ? `TAT: ${formatted} (Overdue)` : `TAT: ${formatted}`,
            badgeClass: isBreached
                ? 'bg-amber-50 text-amber-800 border-amber-300'
                : 'bg-emerald-50 text-emerald-800 border-emerald-300',
            dotClass: isBreached ? 'bg-amber-500' : 'bg-emerald-500',
            elapsedHours: diffHours,
            formattedDuration: formatted,
            isBreached,
            isWarning: false,
        }
    }

    // Active Ticket (New, Pending, In Progress)
    const now = new Date()
    const diffMinutes = Math.max(0, (now.getTime() - start.getTime()) / (1000 * 60))
    const diffHours = Math.round((diffMinutes / 60) * 10) / 10
    const formatted = formatDuration(diffMinutes)

    if (diffMinutes > 48 * 60) {
        return {
            type: 'sla',
            state: 'breached',
            label: `Overdue (${formatted})`,
            badgeClass: 'bg-rose-50 text-rose-800 border-rose-300 animate-pulse',
            dotClass: 'bg-rose-600',
            elapsedHours: diffHours,
            formattedDuration: formatted,
            isBreached: true,
            isWarning: false,
        }
    }

    if (diffMinutes > 24 * 60) {
        return {
            type: 'sla',
            state: 'warning',
            label: `Due Soon (${formatted})`,
            badgeClass: 'bg-amber-50 text-amber-800 border-amber-300',
            dotClass: 'bg-amber-500',
            elapsedHours: diffHours,
            formattedDuration: formatted,
            isBreached: false,
            isWarning: true,
        }
    }

    return {
        type: 'sla',
        state: 'on_track',
        label: `On Track (${formatted})`,
        badgeClass: 'bg-sky-50 text-sky-800 border-sky-300',
        dotClass: 'bg-sky-500',
        elapsedHours: diffHours,
        formattedDuration: formatted,
        isBreached: false,
        isWarning: false,
    }
}

