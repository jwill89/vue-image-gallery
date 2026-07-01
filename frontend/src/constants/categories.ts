/**
 * Tag category display utilities.
 *
 * Categories are fetched from the API and stored in the gallery store.
 * This module provides helper functions that derive CSS classes
 * from the category color stored in the database.
 *
 * Supports both Bulma built-in colors (white, light, dark, primary,
 * link, info, success, warning, danger) and extended custom colors
 * (teal, purple, pink, orange, cyan, lime, indigo, rose, amber, emerald)
 * defined in style.css.
 */

import { useGalleryStore, type TagCategory } from '../stores/gallery'

/**
 * All color options available for tag categories.
 * Bulma built-in colors + custom extended palette.
 */
export const VALID_COLORS = [
  // Bulma built-in
  'white', 'light', 'dark', 'primary', 'link', 'info', 'success', 'warning', 'danger',
  // Extended palette
  'teal', 'purple', 'pink', 'orange', 'cyan', 'lime', 'indigo', 'rose', 'amber', 'emerald',
] as const

export type ValidColor = typeof VALID_COLORS[number]

// ── Color → CSS class derivations ───────────────────────────

export function colorToTagClass(color: string): string {
  return `is-${color || 'white'}`
}

export function colorToTextClass(color: string): string {
  return `has-text-${color || 'white'}`
}

// ── Lookup helpers (use store data) ─────────────────────────

function findCategory(predicate: (c: TagCategory) => boolean): TagCategory | undefined {
  const store = useGalleryStore()
  return store.categories.find(predicate)
}

/**
 * Get the tag class for a given category ID.
 */
export function getCategoryClassById(categoryId: number): string {
  const cat = findCategory(c => c.category_id === categoryId)
  return cat ? colorToTagClass(cat.color) : 'is-white'
}

/**
 * Get the tag class for a given category name. A null/unknown name yields the
 * default class (category_name can be null on tags whose category was removed).
 */
export function getCategoryClassByName(categoryName: string | null | undefined): string {
  const cat = findCategory(c => c.category_name === categoryName)
  return cat ? colorToTagClass(cat.color) : 'is-white'
}

/**
 * Get the text class for a given category name. A null/unknown name yields the
 * default class.
 */
export function getTextClassByName(categoryName: string | null | undefined): string {
  const cat = findCategory(c => c.category_name === categoryName)
  return cat ? colorToTextClass(cat.color) : 'has-text-white'
}
