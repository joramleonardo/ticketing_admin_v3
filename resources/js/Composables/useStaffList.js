import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export const DEFAULT_STAFF = [
  'Aerone Dominhick Sebastian',
  'Etienne Wayne Amparado',
  'Mc Gyver Basaya',
  'Michael Binondo',
  'John Michael Cagadas',
  'Chester Francisco',
  'Joram Kate Leonardo',
  'Lloyd Mandapat',
  'Jomar Rabanera',
  'Mark Jayson Sison',
]

/**
 * Composable providing the dynamic active staff members list.
 * Reads dynamically from Inertia page props with fallback to defaults.
 */
export function useStaffList() {
  const page = usePage()

  const staffOptions = computed(() => {
    const list = page.props?.staffMembers
    if (Array.isArray(list) && list.length > 0) {
      return list
    }
    return DEFAULT_STAFF
  })

  return {
    staffOptions,
    DEFAULT_STAFF,
  }
}

