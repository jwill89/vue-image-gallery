import type { MediaItem } from '../stores/gallery'

/**
 * Derive thumbnail URLs from a list of media items.
 * Returns both 1x and 2x WebP thumbnail paths.
 */
function thumbnailUrls(items: MediaItem[]): string[] {
  const urls: string[] = []
  for (const item of items) {
    const baseName = item.file_name.split('.').slice(0, -1).join('.')
    urls.push(`/media/thumbs/${baseName}.webp`)
    urls.push(`/media/thumbs/${baseName}@2x.webp`)
  }
  return urls
}

/**
 * Ask the service worker to prefetch thumbnail images for given media items.
 * Silently does nothing if the SW isn't available.
 */
export function prefetchThumbnails(items: MediaItem[]): void {
  if (!navigator.serviceWorker?.controller || items.length === 0) return

  const urls = thumbnailUrls(items)
  navigator.serviceWorker.controller.postMessage({
    type: 'PREFETCH_THUMBNAILS',
    urls,
  })
}
