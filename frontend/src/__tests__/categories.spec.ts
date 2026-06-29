import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import {
  VALID_COLORS,
  colorToTagClass,
  colorToTextClass,
  getCategoryClassById,
  getCategoryClassByName,
  getTextClassByName,
} from '../constants/categories'
import { useGalleryStore, type TagCategory } from '../stores/gallery'

const category = (over: Partial<TagCategory> = {}): TagCategory => ({
  category_id: 1,
  category_name: 'Character',
  category_short: 'char',
  color: 'teal',
  description: '',
  sort_order: 0,
  ...over,
})

describe('categories helpers', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('builds Bulma tag/text classes from a color', () => {
    expect(colorToTagClass('teal')).toBe('is-teal')
    expect(colorToTextClass('danger')).toBe('has-text-danger')
  })

  it('falls back to white for empty colors', () => {
    expect(colorToTagClass('')).toBe('is-white')
    expect(colorToTextClass('')).toBe('has-text-white')
  })

  it('exposes the Bulma + extended palette in VALID_COLORS', () => {
    expect(VALID_COLORS).toContain('primary')
    expect(VALID_COLORS).toContain('emerald')
  })

  it('looks up a category class by id and name from the store', () => {
    const store = useGalleryStore()
    store.categories = [category({ category_id: 1, category_name: 'Character', color: 'teal' })]

    expect(getCategoryClassById(1)).toBe('is-teal')
    expect(getCategoryClassByName('Character')).toBe('is-teal')
    expect(getTextClassByName('Character')).toBe('has-text-teal')
  })

  it('returns white defaults for unknown categories', () => {
    useGalleryStore()

    expect(getCategoryClassById(999)).toBe('is-white')
    expect(getCategoryClassByName('Nope')).toBe('is-white')
    expect(getTextClassByName('Nope')).toBe('has-text-white')
  })
})
