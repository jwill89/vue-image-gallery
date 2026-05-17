import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    {
      path: '/',
      redirect: '/images/1/40'
    },
    {
      path: '/images/:page?/:perPage?',
      name: 'images',
      component: () => import('../views/GalleryView.vue'),
      meta: { title: 'Images' },
      props: route => ({
        mediaType: 'images',
        page: Number(route.params.page) || 1,
        perPage: Number(route.params.perPage) || 40
      })
    },
    {
      path: '/images/:page/:perPage/with-tags/:tags',
      name: 'images-with-tags',
      component: () => import('../views/GalleryView.vue'),
      meta: { title: 'Images' },
      props: route => ({
        mediaType: 'images',
        page: Number(route.params.page) || 1,
        perPage: Number(route.params.perPage) || 40,
        tags: route.params.tags as string
      })
    },
    {
      path: '/videos/:page?/:perPage?',
      name: 'videos',
      component: () => import('../views/GalleryView.vue'),
      meta: { title: 'Videos' },
      props: route => ({
        mediaType: 'videos',
        page: Number(route.params.page) || 1,
        perPage: Number(route.params.perPage) || 40
      })
    },
    {
      path: '/videos/:page/:perPage/with-tags/:tags',
      name: 'videos-with-tags',
      component: () => import('../views/GalleryView.vue'),
      meta: { title: 'Videos' },
      props: route => ({
        mediaType: 'videos',
        page: Number(route.params.page) || 1,
        perPage: Number(route.params.perPage) || 40,
        tags: route.params.tags as string
      })
    },
    {
      path: '/images/:id/tags',
      name: 'image-tags',
      component: () => import('../views/MediaTagsView.vue'),
      meta: { title: 'Image Tags' },
      props: route => ({
        mediaType: 'images',
        mediaId: Number(route.params.id)
      })
    },
    {
      path: '/videos/:id/tags',
      name: 'video-tags',
      component: () => import('../views/MediaTagsView.vue'),
      meta: { title: 'Video Tags' },
      props: route => ({
        mediaType: 'videos',
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
      path: '/duplicates',
      name: 'duplicates',
      meta: { title: 'Duplicates' },
      component: () => import('../views/DuplicatesView.vue')
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
