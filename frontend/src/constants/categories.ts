/**
 * Tag category definitions shared across all components.
 * This is the single source of truth for category IDs, names, shortcodes, and CSS classes.
 */

export interface TagCategoryDefinition {
  id: number
  name: string
  shortcode: string
  description: string
  textClass: string   // Bulma text color class
  tagClass: string    // Bulma tag color class
}

export const TAG_CATEGORIES: TagCategoryDefinition[] = [
  {
    id: 1,
    name: 'General',
    shortcode: 'g',
    description: 'General terms that describe features. Default if no shortcode is used.',
    textClass: 'has-text-white',
    tagClass: 'is-white'
  },
  {
    id: 2,
    name: 'Artist',
    shortcode: 'a',
    description: 'The artist who created this specific piece.',
    textClass: 'has-text-danger',
    tagClass: 'is-danger'
  },
  {
    id: 3,
    name: 'Character',
    shortcode: 'c',
    description: 'Character name. Use "First Last" format, e.g., c:ichigo kurosaki.',
    textClass: 'has-text-success',
    tagClass: 'is-success'
  },
  {
    id: 4,
    name: 'Source',
    shortcode: 's',
    description: 'Source material (movie, game, anime, etc.), e.g., s:dragonball super.',
    textClass: 'has-text-warning',
    tagClass: 'is-warning'
  },
  {
    id: 5,
    name: 'Personal List',
    shortcode: 'p',
    description: 'Personal lists for individuals to record favorites.',
    textClass: 'has-text-info',
    tagClass: 'is-info'
  }
]

/**
 * Lookup maps derived from TAG_CATEGORIES for quick access.
 */
export const CATEGORY_TEXT_CLASS_MAP: Record<string, string> = Object.fromEntries(
  TAG_CATEGORIES.map(c => [c.name, c.textClass])
)

export const CATEGORY_NAME_CLASS_MAP: Record<string, string> = Object.fromEntries(
  TAG_CATEGORIES.map(c => [c.name, c.tagClass])
)

export const CATEGORY_ID_CLASS_MAP: Record<number, string> = Object.fromEntries(
  TAG_CATEGORIES.map(c => [c.id, c.tagClass])
)

/**
 * Get the Bulma tag class for a given category ID.
 */
export function getCategoryClassById(categoryId: number): string {
  return CATEGORY_ID_CLASS_MAP[categoryId] || 'is-white'
}

/**
 * Get the Bulma tag class for a given category name.
 */
export function getCategoryClassByName(categoryName: string): string {
  return CATEGORY_NAME_CLASS_MAP[categoryName] || 'is-white'
}

/**
 * Get the Bulma text class for a given category name.
 */
export function getTextClassByName(categoryName: string): string {
  return CATEGORY_TEXT_CLASS_MAP[categoryName] || 'has-text-white'
}

