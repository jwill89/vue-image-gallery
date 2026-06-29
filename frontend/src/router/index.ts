import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    {
      path: '/',
      redirect: '/media/1/40'
    },
    {
      path: '/media/:page?/:perPage?',
      name: 'media',
      component: () => import('../views/GalleryView.vue'),
      meta: { title: 'Media' },
      props: route => {
        const pp = Number(route.params.perPage)
        return {
          page: Number(route.params.page) || 1,
          perPage: isNaN(pp) ? 40 : pp
        }
      }
    },
    {
      path: '/media/:page/:perPage/with-tags/:tags',
      name: 'media-with-tags',
      component: () => import('../views/GalleryView.vue'),
      meta: { title: 'Media' },
      props: route => {
        const pp = Number(route.params.perPage)
        return {
          page: Number(route.params.page) || 1,
          perPage: isNaN(pp) ? 40 : pp,
          tags: route.params.tags as string
        }
      }
    },
    {
      path: '/media/:id/tags',
      name: 'media-tags',
      component: () => import('../views/MediaTagsView.vue'),
      meta: { title: 'Media Tags' },
      props: route => ({
        mediaId: Number(route.params.id)
      })
    },
    {
      path: '/tags',
      name: 'tags',
      meta: { title: 'Tags' },
      component: () => import('../views/TagListView.vue')
    },
    {
      path: '/tags/categories',
      name: 'tag-categories',
      meta: { title: 'Tag Categories' },
      component: () => import('../views/TagCategoriesView.vue')
    },
    {
      path: '/tags/danbooru-rules',
      name: 'danbooru-rules',
      meta: { title: 'Danbooru Import Rules' },
      component: () => import('../views/DanbooruRulesView.vue')
    },
    {
      path: '/tags/:tagId',
      name: 'tag-implications',
      meta: { title: 'Tag Implications' },
      component: () => import('../views/TagImplicationsView.vue'),
      props: route => ({
        tagId: Number(route.params.tagId)
      })
    },
    {
      path: '/favorites',
      name: 'favorites',
      meta: { title: 'Favorites' },
      component: () => import('../views/FavoritesView.vue')
    },
    {
      path: '/upload',
      name: 'upload',
      meta: { title: 'Upload' },
      component: () => import('../views/UploadView.vue')
    },
    {
      path: '/duplicates',
      name: 'duplicates',
      meta: { title: 'Duplicates' },
      component: () => import('../views/DuplicatesView.vue')
    },
    {
      path: '/login',
      name: 'login',
      meta: { title: 'Admin Login' },
      component: () => import('../views/LoginView.vue')
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      meta: { title: 'Not Found' },
      component: () => import('../views/NotFoundView.vue')
    }
  ],
  scrollBehavior() {
    return { top: 0, behavior: 'smooth' }
  }
})

// Set document title from route meta
router.afterEach((to) => {
  const title = to.meta.title as string | undefined
  document.title = title ? `Gallery - ${title}` : 'Gallery'
})

export default router
