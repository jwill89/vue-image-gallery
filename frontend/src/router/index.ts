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
      props: route => ({
        mediaType: 'images',
        mediaId: Number(route.params.id)
      })
    },
    {
      path: '/videos/:id/tags',
      name: 'video-tags',
      component: () => import('../views/MediaTagsView.vue'),
      props: route => ({
        mediaType: 'videos',
        mediaId: Number(route.params.id)
      })
    },
    {
      path: '/tags',
      name: 'tags',
      component: () => import('../views/TagListView.vue')
    },
    {
      path: '/duplicates',
      name: 'duplicates',
      component: () => import('../views/DuplicatesView.vue')
    }
  ],
  scrollBehavior() {
    return { top: 0, behavior: 'smooth' }
  }
})

export default router

