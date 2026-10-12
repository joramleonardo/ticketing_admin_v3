/**
 * Composable for building and handling CSM (Customer Satisfaction Measurement)
 * Google Form prefilled URLs.
 */

// Google Form base URL with entry prefill flag
export const CSM_FORM_BASE_URL =
  'https://docs.google.com/forms/d/e/1FAIpQLSfda5Huf3g5zB5kIoemYLrM-SPR41VmTYRQ2gyeGwaeTtXPsg/viewform?usp=pp_url'

// Form entry IDs mapped to English and Tagalog fields
export const CSM_ENTRY_IDS = {
  CAT_ENG: 'entry.613601820', // Government (Employee or another agency)
  DATE_ENG: 'entry.204278290', // Completed date (EN)
  SEX_ENG: 'entry.468511028', // Sex (EN)
  SERVICE_ENG: 'entry.474414778', // Technical Support Services (EN)

  CAT_TAG: 'entry.1426259693', // Gobyerno (Empleyado o Ahensya)
  DATE_TAG: 'entry.1416427164', // Completed date (TAG)
  SEX_TAG: 'entry.2016337212', // Sex (TAG)
  SERVICE_TAG: 'entry.2008685738', // Technical Support Services (TAG)

  STATIC_1: 'entry.1725600', // Static metric (0)
  STATIC_2: 'entry.1819805617', // Static metric (0)

  REF_ENG: 'entry.298800992', // Reference No (EN)
  NAME_ENG: 'entry.855472974', // Client Name (EN)
  REF_TAG: 'entry.1449935561', // Reference No (TAG)
  NAME_TAG: 'entry.1990048049', // Client Name (TAG)
}

function formatDateYmd(val) {
  if (!val) return ''
  if (typeof val === 'string') {
    const match = val.match(/^(\d{4}-\d{2}-\d{2})/)
    if (match) return match[1]
  }
  const d = new Date(val)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

/**
 * Builds the prefilled Google Form URL given ticket details.
 * @param {Object} details - Object containing client_referenceNo, client_name, client_sex, date_completed
 * @returns {string} - Full Google Form prefill URL
 */
export function buildCsmUrl(details = {}) {
  const url = new URL(CSM_FORM_BASE_URL)
  const params = new URLSearchParams(url.search)

  // Static prefilled defaults
  params.set(CSM_ENTRY_IDS.CAT_ENG, 'Government (Employee or another agency)')
  params.set(CSM_ENTRY_IDS.SERVICE_ENG, 'Technical Support Services')
  params.set(CSM_ENTRY_IDS.CAT_TAG, 'Gobyerno (Empleyado o Ahensya)')
  params.set(CSM_ENTRY_IDS.SERVICE_TAG, 'Technical Support Services')
  params.set(CSM_ENTRY_IDS.STATIC_1, '0')
  params.set(CSM_ENTRY_IDS.STATIC_2, '0')

  if (details) {
    const completed = formatDateYmd(details.date_completed || details.updated_at)
    const sex = details.client_sex || ''
    const ref = details.client_referenceNo || ''
    const name = details.client_name || ''

    if (completed) {
      params.set(CSM_ENTRY_IDS.DATE_ENG, completed)
      params.set(CSM_ENTRY_IDS.DATE_TAG, completed)
    }

    if (sex) {
      params.set(CSM_ENTRY_IDS.SEX_ENG, sex)
      params.set(CSM_ENTRY_IDS.SEX_TAG, sex)
    }

    if (ref) {
      params.set(CSM_ENTRY_IDS.REF_ENG, ref)
      params.set(CSM_ENTRY_IDS.REF_TAG, ref)
    }

    if (name) {
      params.set(CSM_ENTRY_IDS.NAME_ENG, name)
      params.set(CSM_ENTRY_IDS.NAME_TAG, name)
    }
  }

  url.search = params.toString()
  return url.toString()
}

/**
 * Opens the prefilled CSM survey in a new tab.
 * @param {Object} details - Object containing ticket information
 */
export function openCsmSurvey(details = {}) {
  const url = buildCsmUrl(details)
  window.open(url, '_blank', 'noopener,noreferrer')
}

export function useCsmPrefill() {
  return {
    buildCsmUrl,
    openCsmSurvey,
    CSM_FORM_BASE_URL,
    CSM_ENTRY_IDS,
  }
}

