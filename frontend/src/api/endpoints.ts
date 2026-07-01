/**
 * Central registry of API paths (relative to the `/api` base handled by useApi).
 *
 * Keeping paths here — rather than scattered string literals — means a route
 * rename touches one file, and the query builder for `GET /media` lives beside
 * the routes it serves.
 */

type Id = string | number

export const endpoints = {
  version: '/version',
  auth: {
    login: '/auth/login',
  },
  media: {
    page: (page: Id, perPage: Id) => `/media/page/${page}/${perPage}`,
    untagged: (page: Id, perPage: Id) => `/media/untagged/${page}/${perPage}`,
    /** `tagList` is a comma-separated list; a leading '-' excludes (e.g. "cat,-dog"). */
    withTags: (tagList: string, page: Id, perPage: Id) =>
      `/media/with-tags/${encodeURIComponent(tagList)}/${page}/${perPage}`,
    byId: (id: Id) => `/media/${id}`,
    random: '/media/random',
    count: '/media/count',
    byIds: '/media/by-ids',
    bulkDelete: '/media/bulk-delete',
    upload: '/media',
    tags: (id: Id) => `/media/${id}/tags`,
    removeTag: (mediaId: Id, tagId: Id) => `/media/${mediaId}/tags/${tagId}`,
    danbooruTags: (id: Id) => `/media/${id}/danbooru-tags`,
  },
  tags: {
    list: '/tags',
    display: '/tags/display',
    byId: (id: Id) => `/tags/${id}`,
    create: '/tags',
    migrate: (id: Id) => `/tags/${id}/migrate`,
    deleteMigrateTo: (id: Id, targetId: Id) => `/tags/${id}/migrate-to/${targetId}`,
  },
  tagCategories: {
    list: '/tag-categories',
    byId: (id: Id) => `/tag-categories/${id}`,
  },
  tagImplications: {
    list: '/tag-implications',
    byPair: (tagId: Id, impliedTagId: Id) => `/tag-implications/${tagId}/${impliedTagId}`,
  },
  danbooru: {
    categoryMappings: '/danbooru/category-mappings',
    categoryMapping: (danbooruCategoryId: Id) => `/danbooru/category-mappings/${danbooruCategoryId}`,
    tagMappings: '/danbooru/tag-mappings',
    tagMapping: (id: Id) => `/danbooru/tag-mappings/${id}`,
  },
  duplicates: {
    report: '/duplicates/report',
    scan: '/duplicates/scan',
    dismissals: '/duplicates/dismissals',
  },
} as const
