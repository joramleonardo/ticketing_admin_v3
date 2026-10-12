/**
 * Centralized ticket classifications and taxonomy for UI forms and filters.
 */

export const TICKET_TYPES = [
  'Technical Support',
  'Livestream',
  'Events',
  'Others',
]

export const SUPPORT_NATURES = [
  'Hardware',
  'Software',
  'Hardware & Software',
  'System Modification',
  'Livestream',
  'Events',
  'Others',
]

export const SUBCATEGORIES_BY_NATURE = {
  Hardware: ['PC', 'Printer', 'Peripherals', 'Others'],
  Software: ['Install', 'Configure', 'Troubleshoot', 'Others'],
  'Hardware & Software': ['Driver', 'Firmware', 'Compatibility', 'Others'],
  'System Modification': [],
  Events: [],
  Livestream: [],
  Others: ['Others'],
}

export const RELATED_TYPES = [
  'Installation related',
  'Livestream related',
  'Network related',
  'PC Setup & Troubleshooting',
  'Printer related',
  'STARBOOKS related',
  'Website and System related',
  'Events related',
  'Others',
]

export const REMARKS_OPTIONS = [
  'N/A',
  'Issue Resolved Upon Arrival',
  'No Issue Found',
  'Duplicate Ticket',
  'Request No Longer Needed',
  'Ticket Cancelled',
  'Ticket Rescheduled',
  'Outside the Scope of Support',
  'Others',
]

export const DIVISION_MAP = Object.freeze({
  1: 'FAD',
  2: 'IRAD',
  3: 'CRPD',
  4: 'OD-MISPS',
})

export const DIVISION_OPTIONS = [
  { value: '', label: 'All Divisions' },
  { value: '1', label: 'FAD' },
  { value: '2', label: 'IRAD' },
  { value: '3', label: 'CRPD' },
  { value: '4', label: 'OD-MISPS' },
  { value: 'OTHERS', label: 'OTHERS' },
]

export function mapDivision(value) {
  const v = (value ?? '').toString().trim()
  if (!v) return 'OTHERS'
  const n = Number(v)
  if (Number.isFinite(n) && DIVISION_MAP[n]) return DIVISION_MAP[n]
  const up = v.toUpperCase()
  if (['FAD', 'IRAD', 'CRPD', 'OD-MISPS'].includes(up)) return up
  return 'OTHERS'
}

export default {
  TICKET_TYPES,
  SUPPORT_NATURES,
  SUBCATEGORIES_BY_NATURE,
  RELATED_TYPES,
  REMARKS_OPTIONS,
  DIVISION_MAP,
  DIVISION_OPTIONS,
  mapDivision,
}

