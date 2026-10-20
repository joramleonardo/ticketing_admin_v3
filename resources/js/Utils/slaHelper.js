/**
 * SLA, Response Time (Assigned -> Attended), and Resolution Turnaround Time (TAT: Attended -> Completed) Helper
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
            responseHours: null,
            responseFormatted: null,
            tooltip: 'No ticket data available',
            isBreached: false,
            isWarning: false,
        }
    }

    const detail = ticket.detail || ticket
    const status = (detail.ticket_status || ticket.ticket_status || '').trim()
    const assignedStaff = (detail.ticket_assigned_staff || ticket.ticket_assigned_staff || '').trim()

    const rawReq = detail.date_requested || ticket.date_requested || detail.created_at || ticket.created_at
    const rawAssigned = detail.date_assigned || ticket.date_assigned
    const rawAttended = detail.date_attended || ticket.date_attended || detail.ticket_attended_at || ticket.ticket_attended_at
    const rawCompleted = detail.date_completed || ticket.date_completed || detail.updated_at || ticket.updated_at

    const requestedDate = parseDate(rawReq)
    const assignedDate = parseDate(rawAssigned) || (assignedStaff || status !== 'New' ? requestedDate : null)
    const attendedDate = parseDate(rawAttended)

    // 1. Unassigned / New ticket: SLA & TAT clock has not started yet
    if (
        (status.toLowerCase() === 'new' || (!assignedStaff && !['in progress', 'completed', 'closed'].includes(status.toLowerCase()))) &&
        !parseDate(rawAssigned) &&
        !attendedDate
    ) {
        return {
            type: 'unassigned',
            state: 'awaiting_assignment',
            label: 'Awaiting Assignment',
            badgeClass: 'bg-slate-100 text-slate-600 border-slate-200',
            dotClass: 'bg-slate-400',
            elapsedHours: 0,
            formattedDuration: '—',
            responseHours: null,
            responseFormatted: null,
            tooltip: 'SLA & TAT start once the ticket is assigned and attended by IT Staff',
            isBreached: false,
            isWarning: false,
        }
    }

    // Calculate Response Time (Assigned -> Attended) when both exist
    let responseHours = null
    let responseFormatted = null
    if (assignedDate && attendedDate) {
        const respMins = Math.max(0, (attendedDate.getTime() - assignedDate.getTime()) / (1000 * 60))
        responseHours = Math.round((respMins / 60) * 10) / 10
        responseFormatted = formatDuration(respMins)
    }

    const isResolved = status === 'Completed' || status === 'Closed'

    // 2. Resolved Ticket (Completed / Closed): TAT = Attended -> Completed
    if (isResolved) {
        const tatStart = attendedDate || assignedDate || requestedDate
        if (!tatStart) {
            return {
                type: 'unknown',
                state: 'unknown',
                label: 'No Date',
                badgeClass: 'bg-gray-100 text-gray-500 border-gray-200',
                dotClass: 'bg-gray-400',
                elapsedHours: 0,
                formattedDuration: '—',
                responseHours,
                responseFormatted,
                tooltip: 'Missing timestamp',
                isBreached: false,
                isWarning: false,
            }
        }

        const end = parseDate(rawCompleted) || new Date()
        const diffMinutes = Math.max(0, (end.getTime() - tatStart.getTime()) / (1000 * 60))
        const diffHours = Math.round((diffMinutes / 60) * 10) / 10
        const formatted = formatDuration(diffMinutes)
        const isBreached = diffMinutes > 24 * 60

        const respNote = responseFormatted ? ` • Response Time (Assigned → Attended): ${responseFormatted}` : ''

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
            responseHours,
            responseFormatted,
            tooltip: `Resolution TAT (Attended → Completed): ${formatted}${respNote}`,
            isBreached,
            isWarning: false,
        }
    }

    // 3. Pending Ticket (Assigned to IT Staff, waiting to be Attended): Response Time Clock
    if (status.toLowerCase() === 'pending' && !attendedDate) {
        const respStart = assignedDate || requestedDate
        if (!respStart) {
            return {
                type: 'unknown',
                state: 'unknown',
                label: 'No Date',
                badgeClass: 'bg-gray-100 text-gray-500 border-gray-200',
                dotClass: 'bg-gray-400',
                elapsedHours: 0,
                formattedDuration: '—',
                responseHours: null,
                responseFormatted: null,
                tooltip: 'Missing assignment date',
                isBreached: false,
                isWarning: false,
            }
        }

        const now = new Date()
        const diffMinutes = Math.max(0, (now.getTime() - respStart.getTime()) / (1000 * 60))
        const diffHours = Math.round((diffMinutes / 60) * 10) / 10
        const formatted = formatDuration(diffMinutes)

        if (diffMinutes > 48 * 60) {
            return {
                type: 'response',
                state: 'breached',
                label: `Unattended (${formatted})`,
                badgeClass: 'bg-rose-50 text-rose-800 border-rose-300 animate-pulse',
                dotClass: 'bg-rose-600',
                elapsedHours: diffHours,
                formattedDuration: formatted,
                responseHours: diffHours,
                responseFormatted: formatted,
                tooltip: `Assigned ${formatted} ago — awaiting technician attendance`,
                isBreached: true,
                isWarning: false,
            }
        }

        if (diffMinutes > 24 * 60) {
            return {
                type: 'response',
                state: 'warning',
                label: `Due Soon (${formatted})`,
                badgeClass: 'bg-amber-50 text-amber-800 border-amber-300',
                dotClass: 'bg-amber-500',
                elapsedHours: diffHours,
                formattedDuration: formatted,
                responseHours: diffHours,
                responseFormatted: formatted,
                tooltip: `Assigned ${formatted} ago — awaiting technician attendance`,
                isBreached: false,
                isWarning: true,
            }
        }

        return {
            type: 'response',
            state: 'awaiting_attendance',
            label: `Awaiting Attend (${formatted})`,
            badgeClass: 'bg-indigo-50 text-indigo-800 border-indigo-200',
            dotClass: 'bg-indigo-500',
            elapsedHours: diffHours,
            formattedDuration: formatted,
            responseHours: diffHours,
            responseFormatted: formatted,
            tooltip: `Assigned ${formatted} ago — Resolution TAT starts when Attended`,
            isBreached: false,
            isWarning: false,
        }
    }

    // 4. Active In-Progress Ticket (Attended -> Now): Resolution SLA Clock
    const activeStart = attendedDate || assignedDate || requestedDate
    if (!activeStart) {
        return {
            type: 'unknown',
            state: 'unknown',
            label: 'No Date',
            badgeClass: 'bg-gray-100 text-gray-500 border-gray-200',
            dotClass: 'bg-gray-400',
            elapsedHours: 0,
            formattedDuration: '—',
            responseHours,
            responseFormatted,
            tooltip: 'Missing attendance date',
            isBreached: false,
            isWarning: false,
        }
    }

    const now = new Date()
    const diffMinutes = Math.max(0, (now.getTime() - activeStart.getTime()) / (1000 * 60))
    const diffHours = Math.round((diffMinutes / 60) * 10) / 10
    const formatted = formatDuration(diffMinutes)
    const respNote = responseFormatted ? ` • Response Time (Assigned → Attended): ${responseFormatted}` : ''

    if (diffMinutes > 48 * 60) {
        return {
            type: 'sla',
            state: 'breached',
            label: `Overdue (${formatted})`,
            badgeClass: 'bg-rose-50 text-rose-800 border-rose-300 animate-pulse',
            dotClass: 'bg-rose-600',
            elapsedHours: diffHours,
            formattedDuration: formatted,
            responseHours,
            responseFormatted,
            tooltip: `Active Resolution Time (since Attended): ${formatted} (>48h SLA)${respNote}`,
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
            responseHours,
            responseFormatted,
            tooltip: `Active Resolution Time (since Attended): ${formatted} (24h–48h SLA)${respNote}`,
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
        responseHours,
        responseFormatted,
        tooltip: `Active Resolution Time (since Attended): ${formatted} (Within 24h SLA)${respNote}`,
        isBreached: false,
        isWarning: false,
    }
}
