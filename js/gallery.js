/**
 * @file gallery.js
 * @author MathDad <https://www.mathdad.me>
 * @license MIT
 * @version 1.1.0
 * @description This file contains the JavaScript code for the site. It handles the loading of images and videos, pagination, and tag management.
 */

// For Linting
/* global DataTable */

/**
 * @const {number} PAGE_IMAGES - Defines if the page is displaying images
 * @const {number} PAGE_VIDEOS - Defines if the page is displaying videos
 * @const {number} PAGE_TAGS - Defines if the page is displaying tags
 * @const {string} API_BASE_URL - The API link for the gallery
 * @var {string} PAGE_TITLE - The title of the page
 * @var {Array} CURRENT_TAGS - The current searched tags
 * @var {Array} ALL_TAGS - The list of all tags
 * @var {number} CURRENT_PAGE - The current page number
 * @var {number} PAGE_TYPE - The type of page (images or videos)
 */
const PAGE_IMAGES = 1;
const PAGE_VIDEOS = 2;
const PAGE_TAGS = 3;
let BASE_URL = '';
let API_BASE_URL = '';
let PAGE_TITLE = 'Gallery';
let CURRENT_TAGS = [];
let ALL_TAGS = [];
let CURRENT_PAGE = 1;
let PAGE_TYPE = PAGE_IMAGES;
let ITEMS_PER_PAGE = 40;
let BLUR_THUMBNAILS = false;
let SHOWING_MEDIA_TAGS = false;
let MEDIA_ID = null;

/**
 * Reusable JSON headers for API requests.
 * @const {Object}
 */
const JSON_HEADERS = { 'Content-Type': 'application/json' };

/**
 * Map of category IDs to Bulma CSS tag classes.
 * @const {Object<number, string>}
 */
const CATEGORY_TAG_CLASS_MAP = {
    1: 'is-white',
    2: 'is-danger',
    3: 'is-success',
    4: 'is-warning',
    5: 'is-info'
};

/**
 * Map of category names to Bulma CSS text classes.
 * @const {Object<string, string>}
 */
const CATEGORY_TEXT_CLASS_MAP = {
    'General':       'has-text-white',
    'Artist':        'has-text-danger',
    'Character':     'has-text-success',
    'Source':        'has-text-warning',
    'Personal List': 'has-text-info'
};

/**
 * Map of category names to Bulma CSS tag classes.
 * @const {Object<string, string>}
 */
const CATEGORY_NAME_CLASS_MAP = {
    'General':       'is-white',
    'Artist':        'is-danger',
    'Character':     'is-success',
    'Source':        'is-warning',
    'Personal List': 'is-info'
};

// ============================================================
// Utility / Helper Functions
// ============================================================

/**
 * @function fetchApi
 * @description Generic API fetch wrapper. Handles response validation and JSON parsing.
 * @async
 * @param {string} url - The API endpoint URL.
 * @param {Object} [options={}] - Optional fetch options (method, headers, body, etc.).
 * @returns {Promise<*>} The parsed JSON response data.
 */
async function fetchApi(url, options = {}) {
    const response = await fetch(url, options);
    if (!response.ok) {
        throw new Error(`HTTP ${response.status} for ${url}`);
    }
    return response.json();
}

/**
 * @function getActiveMediaInfo
 * @description Returns the ID, URL, and hash of the currently-viewed media item on the tags page.
 * @returns {{ itemID: number, itemURL: string, itemHash: string }}
 */
function getActiveMediaInfo() {
    const selector = PAGE_TYPE === PAGE_IMAGES ? '#tag-image' : '#tag-video';
    const $el = $(selector);
    return {
        itemID:   $el.data('id'),
        itemURL:  $el.prop('src'),
        itemHash: ($('#hash-display').text() || '').replace('MD5 Hash: ', '')
    };
}

/**
 * @function createEl
 * @description Shorthand to create a DOM element with classes and attributes.
 * @param {string} tag - HTML tag name.
 * @param {string[]} [classes=[]] - CSS classes to add.
 * @param {Object<string,string>} [attrs={}] - Attributes to set.
 * @returns {HTMLElement}
 */
function createEl(tag, classes = [], attrs = {}) {
    const el = document.createElement(tag);
    if (classes.length) el.classList.add(...classes);
    for (const [key, value] of Object.entries(attrs)) {
        el.setAttribute(key, value);
    }
    return el;
}

// ============================================================
// Initialization
// ============================================================

/**
 * @description This function is called when the page is loaded. It initializes the page by setting the title, loading tags, and setting the total images/videos in the footer.
 */
$(function () {
    // Site Initialization
    SiteInit();

    // Bind Site Events
    AddEventListenersToSite();

    // Bind Element Events
    AddEventListenersNavigation();

    // Parse the current URL and route to the correct page
    const routeInfo = parseURL();
    navigateFromState(routeInfo);

    // Replace the initial history entry with proper state
    SetCurrentURL(true);
});

/**
 * @function SiteInit
 * @description Initializes the site by setting the title, loading tags, and setting the total images/videos in the footer.
 */
function SiteInit() {
    // Set Base URL Information
    BASE_URL = window.location.origin;
    API_BASE_URL = `${BASE_URL}/api`;

    // Run all initialization calls in parallel
    Promise.all([
        getPageTitle(),
        getTags(),
        getTotalImages(),
        getTotalVideos()
    ]).then(([title, tags, totalImages, totalVideos]) => {
        // Set gallery title
        PAGE_TITLE = title;
        setPageTitle();

        // Set all tags
        ALL_TAGS = tags || [];
        setTagList(ALL_TAGS);

        // Set totals in footer
        $('#total-images').text(totalImages);
        $('#total-videos').text(totalVideos);
    }).catch((error) => {
        console.error('Error during site initialization:', error);
    });
}

// ============================================================
// Page Rendering
// ============================================================

/**
 * @function RenderPageGallery
 * @description Generates the gallery content based on the current page and type (images or videos).
 */
function RenderPageGallery() {
    const gallerySection = $('#gallery-content');
    const galleryDisplay = $('#gallery-display');

    // Update the Page Title
    setPageTitle();

    // Determine which fetch to use
    const galleryPromise = (PAGE_TYPE === PAGE_IMAGES)
        ? getImagesForPage(CURRENT_PAGE)
        : getVideosForPage(CURRENT_PAGE);

    // Resolve the promise and create the gallery content
    galleryPromise.then((items) => {
        // Use a DocumentFragment for efficient batch DOM construction
        const fragment = document.createDocumentFragment();
        const columnDiv = createEl('div', ['column', 'is-full', 'is-align-content-end']);
        const parentDiv = createEl('div', ['parent']);
        columnDiv.appendChild(parentDiv);

        // Loop Through Images/Videos
        items.forEach(item => {
            parentDiv.appendChild(createGalleryCard(item));
        });

        fragment.appendChild(columnDiv);

        // Clear and Append New Page
        galleryDisplay.empty().append(fragment);

        // Show the Gallery Section
        gallerySection.removeClass('is-hidden');

        // Add Tag Bindings
        AddEventListenersGallery();

        // Generate Pagination
        RenderGalleryPagination();

        // Scroll to top of page
        document.body.scrollIntoView({ behavior: 'smooth' });
    });
}

/**
 * @function createGalleryCard
 * @description Builds a single gallery card element for an image or video item.
 * @param {Object} item - The image or video data object from the API.
 * @returns {HTMLElement} The constructed card flex wrapper element.
 */
function createGalleryCard(item) {
    let itemId, thumbnailPath, fullPath, hash;

    if (PAGE_TYPE === PAGE_IMAGES) {
        itemId = item.image_id;
        thumbnailPath = `${BASE_URL}/images/thumbs/${item.file_name}`;
        fullPath = `${BASE_URL}/images/full/${item.file_name}`;
        hash = item.hash;
    } else {
        itemId = item.video_id;
        const baseName = item.file_name.split('.').slice(0, -1).join('.');
        thumbnailPath = `${BASE_URL}/videos/thumbs/${baseName}.jpg`;
        fullPath = `${BASE_URL}/videos/full/${item.file_name}`;
        hash = item.hash;
    }

    // Card structure
    const flexDiv = createEl('div', ['is-flex', 'is-align-self-flex-end']);
    const cardDiv = createEl('div', ['card', 'child', 'has-border-white']);
    const cardContentDiv = createEl('div', ['card-content', 'has-text-centered', 'has-background-grey-darker']);
    const cardFigureDiv = createEl('figure', ['image']);

    // Thumbnail image
    const imgClasses = BLUR_THUMBNAILS ? ['gallery-image', 'thumb-blur'] : ['gallery-image'];
    const cardFigureImage = createEl('img', imgClasses, { alt: '', src: thumbnailPath });

    // Card Footer
    const cardFooterDiv = createEl('footer', ['card-footer', 'has-background-light']);

    // Footer link – Lightbox
    const lightboxLink = createEl('a', ['card-footer-item'], {
        href: fullPath,
        'data-lightbox': 'page-items',
        'data-title': 'Tags List Coming Soon'
    });
    lightboxLink.appendChild(createFooterIcon('fa-magnifying-glass-plus', 'Zoom In'));

    // Footer link – Full size
    const fullLink = createEl('a', ['card-footer-item'], {
        href: fullPath,
        target: '_blank',
        id: `item-full-${itemId}`
    });
    fullLink.appendChild(createFooterIcon('fa-up-right-from-square', 'View Full Size in New Tab'));

    // Footer link – Tags
    const tagsLink = createEl('a', ['card-footer-item', 'link-tags-page'], {
        'data-id': itemId,
        'data-hash': hash
    });
    tagsLink.appendChild(createFooterIcon('fa-tags', 'Add/View Tags'));

    // Assemble
    cardFigureDiv.appendChild(cardFigureImage);
    cardContentDiv.appendChild(cardFigureDiv);
    cardDiv.appendChild(cardContentDiv);
    cardFooterDiv.append(lightboxLink, fullLink, tagsLink);
    cardDiv.appendChild(cardFooterDiv);
    flexDiv.appendChild(cardDiv);

    return flexDiv;
}

/**
 * @function createFooterIcon
 * @description Creates a footer icon span with the specified FontAwesome icon class and title.
 * @param {string} iconClass - FontAwesome icon class name (without 'fa-solid' prefix).
 * @param {string} title - The title/tooltip text.
 * @returns {HTMLElement} The span element containing the icon.
 */
function createFooterIcon(iconClass, title) {
    const span = createEl('span', ['icon', 'has-text-info-dark']);
    span.appendChild(createEl('i', ['fa-solid', iconClass], { title }));
    return span;
}

/**
 * @function RenderGalleryPagination
 * @description Generates the pagination for the gallery based on the current page and type (images or videos).
 */
function RenderGalleryPagination() {
    const gallerySection = $('#gallery-content');
    const topPagination = $('#pagination-top');
    const bottomPagination = $('#pagination-bottom');

    const pagesPromise = (PAGE_TYPE === PAGE_IMAGES)
        ? getTotalImagePages()
        : getTotalVideoPages();

    pagesPromise.then((totalPages) => {
        const nextPage = CURRENT_PAGE + 1;
        const previousPage = CURRENT_PAGE - 1;

        // Pagination nav
        const paginationNav = createEl('nav', ['pagination', 'is-centered'], {
            role: 'navigation',
            'aria-label': 'pagination'
        });

        // Previous / Next links
        const previousLink = createEl('a', [
            'pagination-previous',
            ...(CURRENT_PAGE <= 1 ? ['is-disabled'] : [])
        ]);
        previousLink.textContent = 'Previous';

        const nextLink = createEl('a', [
            'pagination-next',
            ...(CURRENT_PAGE >= totalPages ? ['is-disabled'] : [])
        ]);
        nextLink.textContent = 'Next';

        // Page number list
        const pageNumberList = createEl('ul', ['pagination-list']);

        // Page 1 + early ellipsis (if current >= 3)
        if (CURRENT_PAGE >= 3) {
            pageNumberList.appendChild(createPaginationItem(1));
            pageNumberList.appendChild(createPaginationEllipsis());
        }

        // Previous page (if current >= 2)
        if (CURRENT_PAGE >= 2) {
            pageNumberList.appendChild(createPaginationItem(previousPage));
        }

        // Current page
        pageNumberList.appendChild(createPaginationItem(CURRENT_PAGE, true));

        // Next page
        if (CURRENT_PAGE < totalPages) {
            pageNumberList.appendChild(createPaginationItem(nextPage));
        }

        // Late ellipsis + last page
        if (CURRENT_PAGE <= (totalPages - 2)) {
            pageNumberList.appendChild(createPaginationEllipsis());
            pageNumberList.appendChild(createPaginationItem(totalPages));
        }

        // Assemble
        paginationNav.append(previousLink, nextLink, pageNumberList);

        // Clone for top and bottom
        const paginationBottom = paginationNav.cloneNode(true);

        topPagination.empty().append(paginationNav);
        bottomPagination.empty().append(paginationBottom);

        // Bind events
        AddEventListenersGalleryPagination();
        AddEventListenersGallery();

        // Show the Page
        gallerySection.removeClass('is-hidden');
    });
}

/**
 * @function createPaginationItem
 * @description Creates a pagination list item with a page link.
 * @param {number} page - The page number.
 * @param {boolean} [isCurrent=false] - Whether this is the current page.
 * @returns {HTMLElement} An li element containing the pagination link.
 */
function createPaginationItem(page, isCurrent = false) {
    const li = document.createElement('li');
    const classes = isCurrent ? ['pagination-link', 'is-current'] : ['pagination-link'];
    const attrs = {
        'data-page': page,
        'aria-label': isCurrent ? `Page ${page}` : `Goto page ${page}`
    };
    if (isCurrent) attrs['aria-current'] = 'page';

    const link = createEl('a', classes, attrs);
    link.textContent = page;
    li.appendChild(link);
    return li;
}

/**
 * @function createPaginationEllipsis
 * @description Creates a pagination ellipsis list item.
 * @returns {HTMLElement} An li element containing the ellipsis span.
 */
function createPaginationEllipsis() {
    const li = document.createElement('li');
    const span = createEl('span', ['pagination-ellipsis']);
    span.innerHTML = '&hellip;';
    li.appendChild(span);
    return li;
}

/**
 * @function RenderPageMediaTags
 * @description Generates the content for the tag page for an image or video.
 * @param {number} itemID
 * @param {string} itemURL
 * @param {string} itemHash
 */
function RenderPageMediaTags(itemID, itemURL, itemHash = null) {
    // Set our constants for use elsewhere
    SHOWING_MEDIA_TAGS = true;
    MEDIA_ID = itemID;

    // Update Page Title
    setPageTitle();

    // Define the Page Section
    const mediaTagsSection = $('#item-tags-content');
    const mediaExtension = itemURL.split('.').pop().toLowerCase();

    // Get Tags for Item
    getTagsForItem(itemID).then((tags) => {
        const mediaContainer = $('#tags-page-media');

        // Create media element (image or video)
        if (PAGE_TYPE === PAGE_IMAGES || mediaExtension === 'gif') {
            const mediaItem = createEl('img', [], {
                id: 'tag-image',
                'data-id': itemID,
                alt: '',
                src: itemURL
            });
            mediaContainer.empty().append(mediaItem);
        } else {
            const mediaItem = createEl('video', [], {
                id: 'tag-video',
                'data-id': itemID,
                controls: 'controls',
                src: itemURL,
                type: `video/${mediaExtension}`
            });
            mediaContainer.empty().append(mediaItem);
        }

        // Display MD5 Hash
        if (itemHash !== null) {
            const hashDisplay = createEl('p', ['help'], { id: 'hash-display' });
            hashDisplay.textContent = `MD5 Hash: ${itemHash}`;
            mediaContainer.append(hashDisplay);
        }

        // Build tag list using a fragment for efficiency
        const tagListEl = $('#tag-list');
        const fragment = document.createDocumentFragment();
        tags.forEach((tag) => {
            const categoryClass = CATEGORY_TAG_CLASS_MAP[tag.category_id] || 'is-white';
            const tagSpan = createEl('span', ['tag', 'media-tag', categoryClass]);
            tagSpan.textContent = tag.tag_name;
            const deleteBtn = createEl('button', ['delete'], {
                'data-id': tag.tag_id,
                'aria-label': 'delete'
            });
            tagSpan.appendChild(deleteBtn);
            fragment.appendChild(tagSpan);
        });
        tagListEl.append(fragment);

        // Add Bindings for the Tag Page
        AddEventListenersMediaTags();

        // Show the Tag Page
        mediaTagsSection.removeClass('is-hidden');
    });
}

/**
 * @function RenderPageTags
 * @description Generates the content for the tags page including rendering the DataTable.
 */
function RenderPageTags() {
    const tagsSection = $('#tags-list-content');

    // Update the Page Title
    setPageTitle();

    // Get the Table
    const tagsSectionTable = $('#tag-list-page-table');

    // Set up the Tags Page DataTable
    tagsSectionTable.DataTable({
        ajax: {
            url: `${API_BASE_URL}/tags/display/`,
            dataSrc: ''
        },
        destroy: true,
        processing: true,
        searching: true,
        autoWidth: true,
        paging: true,
        scrollCollapse: true,
        colReorder: true,
        fixedHeader: true,
        responsive: true,
        lengthMenu: [10, 25, 50, 100],
        pageLength: 10,
        order: [[1, 'asc']],
        rowId: 'tag_id',
        columns: [
            {
                // Tag ID - Used for Editing
                name: 'tag_id',
                data: 'tag_id',
                visible: false,
                searchable: false
            },
            {
                // Tag Name
                name: 'tag_name',
                data: 'tag_name',
                visible: true,
                searchable: true,
                render: function (data, type, row) {
                    if (type !== 'display') return data;
                    const categoryClass = CATEGORY_TEXT_CLASS_MAP[row.category_name] || 'has-text-white';
                    const span = document.createElement('span');
                    span.className = categoryClass;
                    span.textContent = data;
                    return span.outerHTML;
                }
            },
            {
                // Category ID - Used for Editing
                name: 'category_id',
                data: 'category_id',
                visible: false,
                searchable: false
            },
            {
                name: 'category_name',
                data: 'category_name',
                visible: true,
                searchable: true,
                render: function (data, type) {
                    if (type !== 'display') return data;
                    const categoryClass = CATEGORY_NAME_CLASS_MAP[data] || 'is-white';
                    const span = document.createElement('span');
                    span.className = `tag is-medium ${categoryClass}`;
                    span.textContent = data;
                    return span.outerHTML;
                }
            },
            {
                name: 'image_count',
                data: 'image_count',
                visible: true,
                searchable: true,
                render: function (data) {
                    return `<p class="has-text-center">${Number(data)}</p>`;
                }
            },
            {
                name: 'video_count',
                data: 'video_count',
                visible: true,
                searchable: true,
                render: function (data) {
                    return `<p class="has-text-center">${Number(data)}</p>`;
                }
            }
        ]
    });

    // Add Event Listeners
    AddEventListenersToTagsList();

    // Show the Tags Section
    tagsSection.removeClass('is-hidden');
}

// ============================================================
// Page State Management
// ============================================================

/**
 * @function ClearPages
 * @description Clears the content of all pages and hides them. Individual page render functions will show their pages.
 */
function ClearPages() {
    // Clear the Gallery Page
    $('#pagination-top').empty();
    $('#pagination-bottom').empty();
    $('#gallery-display').empty();

    // Clear the Media Item Tags Page
    $('#tags-page-media').empty();
    $('#tag-list').empty();

    // Clear the Tag List Page & DataTable
    if (DataTable.isDataTable('#tag-list-page-table')) {
        $('#tag-list-page-table').DataTable().clear().destroy();
        $('#tag-list-page-table-body').empty();
    }

    // Hide All Pages (Sections)
    $('#gallery-content, #item-tags-content, #tags-list-content').addClass('is-hidden');
}

/**
 * @function NavigationSetActive
 * @description Sets the appropriate link as active in the navigation bar.
 * @param {jQuery} activeLink
 */
function NavigationSetActive(activeLink) {
    $('a.navbar-item').removeClass('is-selected');
    activeLink.addClass('is-selected');
}

/**
 * @function SetCurrentURL
 * @description Sets the current URL in the browser to reflect the current app state.
 * URL patterns:
 *   /images/{page}/{perPage}/
 *   /images/{page}/{perPage}/with-tags/{tags}/
 *   /videos/{page}/{perPage}/
 *   /videos/{page}/{perPage}/with-tags/{tags}/
 *   /images/{id}/tags/   (media tags view)
 *   /videos/{id}/tags/   (media tags view)
 *   /tags/               (tag list page)
 * @param {boolean} [replace=false] - If true, replaces the current history entry instead of pushing.
 */
function SetCurrentURL(replace = false) {
    const pageTypeMap = {
        [PAGE_IMAGES]: 'images',
        [PAGE_VIDEOS]: 'videos',
        [PAGE_TAGS]:   'tags'
    };
    const pageType = pageTypeMap[PAGE_TYPE] || 'images';
    let newURL;

    if (SHOWING_MEDIA_TAGS) {
        newURL = `${BASE_URL}/${pageType}/${MEDIA_ID}/tags/`;
    } else if (PAGE_TYPE !== PAGE_TAGS) {
        newURL = `${BASE_URL}/${pageType}/${CURRENT_PAGE}/${ITEMS_PER_PAGE}/`;
        if (CURRENT_TAGS.length > 0) {
            newURL += `with-tags/${encodeURIComponent(CURRENT_TAGS.join(','))}/`;
        }
    } else {
        newURL = `${BASE_URL}/${pageType}/`;
    }

    const state = {
        pageType: PAGE_TYPE,
        currentPage: CURRENT_PAGE,
        itemsPerPage: ITEMS_PER_PAGE,
        currentTags: CURRENT_TAGS,
        showingMediaTags: SHOWING_MEDIA_TAGS,
        mediaId: MEDIA_ID
    };

    if (replace) {
        window.history.replaceState(state, '', newURL);
    } else {
        window.history.pushState(state, '', newURL);
    }
}

/**
 * @function parseURL
 * @description Parses the current URL and sets the app state accordingly.
 * Supports the following URL patterns:
 *   /                                          → images, page 1
 *   /images/{page}/{perPage}/                  → images gallery
 *   /images/{page}/{perPage}/with-tags/{tags}/ → images filtered by tags
 *   /videos/{page}/{perPage}/                  → videos gallery
 *   /videos/{page}/{perPage}/with-tags/{tags}/ → videos filtered by tags
 *   /images/{id}/tags/                         → media item tags view
 *   /videos/{id}/tags/                         → media item tags view
 *   /tags/                                     → tag list page
 * @returns {{ action: string, mediaId?: number }} The action to take after parsing.
 */
function parseURL() {
    const path = window.location.pathname.replace(/\/+$/, ''); // strip trailing slashes
    const segments = path.split('/').filter(Boolean);

    // Default state
    let action = 'gallery';

    // No segments → default images page
    if (segments.length === 0) {
        PAGE_TYPE = PAGE_IMAGES;
        CURRENT_PAGE = 1;
        ITEMS_PER_PAGE = 40;
        CURRENT_TAGS = [];
        SHOWING_MEDIA_TAGS = false;
        MEDIA_ID = null;
        return { action };
    }

    const section = segments[0].toLowerCase();

    // Set page type from first segment
    if (section === 'videos') {
        PAGE_TYPE = PAGE_VIDEOS;
    } else if (section === 'tags') {
        PAGE_TYPE = PAGE_TAGS;
        SHOWING_MEDIA_TAGS = false;
        MEDIA_ID = null;
        CURRENT_TAGS = [];
        return { action: 'tags' };
    } else {
        PAGE_TYPE = PAGE_IMAGES;
    }

    // Check for media tags view: /{type}/{id}/tags/
    if (segments.length >= 3 && segments[2].toLowerCase() === 'tags') {
        SHOWING_MEDIA_TAGS = true;
        MEDIA_ID = parseInt(segments[1], 10);
        return { action: 'mediaTags', mediaId: MEDIA_ID };
    }

    // Gallery view: /{type}/{page}/{perPage}/ or /{type}/{page}/{perPage}/with-tags/{tags}/
    SHOWING_MEDIA_TAGS = false;
    MEDIA_ID = null;
    CURRENT_PAGE = segments.length >= 2 ? parseInt(segments[1], 10) || 1 : 1;
    ITEMS_PER_PAGE = segments.length >= 3 ? parseInt(segments[2], 10) || 40 : 40;

    // Check for tag filter
    if (segments.length >= 5 && segments[3].toLowerCase() === 'with-tags') {
        CURRENT_TAGS = decodeURIComponent(segments[4]).split(',').map(t => t.trim()).filter(Boolean);
    } else {
        CURRENT_TAGS = [];
    }

    return { action };
}

/**
 * @function navigateFromState
 * @description Routes to the correct page based on parsed URL state.
 * @param {{ action: string, mediaId?: number }} routeInfo - The route info from parseURL().
 */
function navigateFromState(routeInfo) {
    // Sync UI controls with state
    syncUIWithState();

    ClearPages();

    switch (routeInfo.action) {
        case 'tags':
            NavigationSetActive($('#nav-tags-link'));
            RenderPageTags();
            break;
        case 'mediaTags':
            NavigationSetActive(PAGE_TYPE === PAGE_IMAGES ? $('#nav-images-link') : $('#nav-videos-link'));
            // For media tags we need to fetch the item URL; use the API to get item info
            loadMediaTagsFromURL(routeInfo.mediaId);
            break;
        default:
            NavigationSetActive(PAGE_TYPE === PAGE_IMAGES ? $('#nav-images-link') : $('#nav-videos-link'));
            RenderPageGallery();
            break;
    }
}

/**
 * @function syncUIWithState
 * @description Syncs UI controls (items-per-page select, search field) with current app state.
 */
function syncUIWithState() {
    // Sync items-per-page dropdown
    const $perPage = $('#items-per-page');
    if ($perPage.find(`option[value="${ITEMS_PER_PAGE}"]`).length) {
        $perPage.val(ITEMS_PER_PAGE);
    }

    // Sync tag search field
    if (CURRENT_TAGS.length > 0) {
        $('#nav_search_tags').val(CURRENT_TAGS.join(', '));
        $('#search-tags').addClass('is-hidden');
        $('#reset-tags').removeClass('is-hidden');
    } else {
        $('#nav_search_tags').val('');
        $('#reset-tags').addClass('is-hidden');
        $('#search-tags').removeClass('is-hidden');
    }
}

/**
 * @function loadMediaTagsFromURL
 * @description Loads the media tags page when navigating via URL (where we only have the item ID).
 * Fetches item details from the API to get the file URL and hash.
 * @param {number} mediaId - The ID of the media item.
 */
function loadMediaTagsFromURL(mediaId) {
    const mediaType = PAGE_TYPE === PAGE_IMAGES ? 'image' : 'video';
    fetchApi(`${API_BASE_URL}/${mediaType}s/${mediaId}/`).then((item) => {
        let itemURL, itemHash;
        if (PAGE_TYPE === PAGE_IMAGES) {
            itemURL = `${BASE_URL}/images/full/${item.file_name}`;
            itemHash = item.hash || null;
        } else {
            itemURL = `${BASE_URL}/videos/full/${item.file_name}`;
            itemHash = item.hash || null;
        }
        RenderPageMediaTags(mediaId, itemURL, itemHash);
    }).catch((error) => {
        console.error('Error loading media item from URL:', error);
        // Fallback to gallery
        SHOWING_MEDIA_TAGS = false;
        MEDIA_ID = null;
        RenderPageGallery();
    });
}

// ============================================================
// Event Listeners
// ============================================================

/**
 * @function AddEventListenersToSite
 * @description Binds site-wide listeners to their needed elements.
 */
function AddEventListenersToSite() {
    // Close All Modals - Buttons, background, and escape key
    $('.modal-close, .modal-delete, .modal-background').on('click', CloseModal);

    $(document).on('keyup', function (event) {
        if (event.key === 'Escape') {
            CloseModal();
        }
    });

    // Handle browser back/forward navigation
    window.addEventListener('popstate', function (event) {
        if (event.state) {
            // Restore state from history entry
            PAGE_TYPE = event.state.pageType;
            CURRENT_PAGE = event.state.currentPage;
            ITEMS_PER_PAGE = event.state.itemsPerPage;
            CURRENT_TAGS = event.state.currentTags || [];
            SHOWING_MEDIA_TAGS = event.state.showingMediaTags;
            MEDIA_ID = event.state.mediaId;

            syncUIWithState();
            ClearPages();

            if (SHOWING_MEDIA_TAGS && MEDIA_ID) {
                NavigationSetActive(PAGE_TYPE === PAGE_IMAGES ? $('#nav-images-link') : $('#nav-videos-link'));
                loadMediaTagsFromURL(MEDIA_ID);
            } else if (PAGE_TYPE === PAGE_TAGS) {
                NavigationSetActive($('#nav-tags-link'));
                RenderPageTags();
            } else {
                NavigationSetActive(PAGE_TYPE === PAGE_IMAGES ? $('#nav-images-link') : $('#nav-videos-link'));
                RenderPageGallery();
            }
        } else {
            // No state — re-parse URL
            const routeInfo = parseURL();
            navigateFromState(routeInfo);
        }
    });
}

/**
 * @function AddEventListenersNavigation
 * @description Binds the navigation links to their respective functions.
 */
function AddEventListenersNavigation() {
    // Navbar Mobile Burger Menu Toggle
    $('#nav_burger').on('click', function () {
        $(this).toggleClass('is-active');
        $('.navbar-menu').toggleClass('is-active');
    });

    // Main Links - Images
    $('#nav-images-link').on('click', function () {
        CURRENT_PAGE = 1;
        PAGE_TYPE = PAGE_IMAGES;
        CURRENT_TAGS = [];
        SHOWING_MEDIA_TAGS = false;
        MEDIA_ID = null;
        NavigationSetActive($(this));
        ClearPages();
        RenderPageGallery();
        SetCurrentURL();
    });

    // Main Links - Videos
    $('#nav-videos-link').on('click', function () {
        CURRENT_PAGE = 1;
        PAGE_TYPE = PAGE_VIDEOS;
        CURRENT_TAGS = [];
        SHOWING_MEDIA_TAGS = false;
        MEDIA_ID = null;
        NavigationSetActive($(this));
        ClearPages();
        RenderPageGallery();
        SetCurrentURL();
    });

    // Main Links - Tags
    $('#nav-tags-link').on('click', function () {
        PAGE_TYPE = PAGE_TAGS;
        CURRENT_TAGS = [];
        SHOWING_MEDIA_TAGS = false;
        MEDIA_ID = null;
        NavigationSetActive($(this));
        ClearPages();
        RenderPageTags();
        SetCurrentURL();
    });

    // Blur Images Button
    $('#blur-thumbs').on('click', function () {
        BLUR_THUMBNAILS = !BLUR_THUMBNAILS;
        $(this)
            .toggleClass('is-success', BLUR_THUMBNAILS)
            .text(BLUR_THUMBNAILS ? 'Blur: On' : 'Blur: Off');
        $('.gallery-image').toggleClass('thumb-blur', BLUR_THUMBNAILS);
    });

    // Items Per-Page
    $('#items-per-page').on('change', function () {
        ITEMS_PER_PAGE = parseInt($(this).val(), 10);
        CURRENT_PAGE = 1;
        RenderPageGallery();
        SetCurrentURL();
    });

    // Search Items with Tags
    $('#search-tags').on('click', function () {
        const searchTags = $('#nav_search_tags').val().split(',');
        CURRENT_TAGS = searchTags.map(tag => tag.trim()).filter(Boolean);
        CURRENT_PAGE = 1;
        $(this).addClass('is-hidden');
        $('#reset-tags').removeClass('is-hidden');
        RenderPageGallery();
        SetCurrentURL();
    });

    // Reset Search Items with Tags
    $('#reset-tags').on('click', function () {
        $('#nav_search_tags').val('');
        CURRENT_TAGS = [];
        CURRENT_PAGE = 1;
        $(this).addClass('is-hidden');
        $('#search-tags').removeClass('is-hidden');
        RenderPageGallery();
        SetCurrentURL();
    });
}

/**
 * @function AddEventListenersGallery
 * @description Binds the gallery links to their respective functions.
 */
function AddEventListenersGallery() {
    // Tag Links - unbind first to prevent accumulation
    $('.link-tags-page').off('click').on('click', function (event) {
        event.stopImmediatePropagation();
        const itemID = $(this).data('id');
        const itemHash = $(this).data('hash');
        const itemURL = $(`#item-full-${itemID}`).prop('href');
        ClearPages();
        RenderPageMediaTags(itemID, itemURL, itemHash);
        SetCurrentURL();
    });
}

/**
 * @function AddEventListenersGalleryPagination
 * @description Binds the pagination links to their respective functions.
 */
function AddEventListenersGalleryPagination() {
    // Pagination Links - unbind first to prevent accumulation
    $('.pagination-link').off('click').on('click', function () {
        CURRENT_PAGE = $(this).data('page');
        RenderPageGallery();
        SetCurrentURL();
    });

    // Pagination - Next
    $('.pagination-next').off('click').on('click', function () {
        if (!$(this).hasClass('is-disabled')) {
            CURRENT_PAGE++;
            RenderPageGallery();
            SetCurrentURL();
        }
    });

    // Pagination - Previous
    $('.pagination-previous').off('click').on('click', function () {
        if (!$(this).hasClass('is-disabled')) {
            CURRENT_PAGE--;
            RenderPageGallery();
            SetCurrentURL();
        }
    });
}

/**
 * @function AddEventListenersMediaTags
 * @description Binds the tag links to their respective functions.
 */
function AddEventListenersMediaTags() {
    // Tag Back - Back to Gallery - unbind first to prevent accumulation
    $('#back-to-gallery').off('click').on('click', function (event) {
        event.stopImmediatePropagation();
        SHOWING_MEDIA_TAGS = false;
        MEDIA_ID = null;
        ClearPages();
        RenderPageGallery();
        SetCurrentURL();
    });

    // Tag Category Shortcode Help Modal
    $('#help-shortcode').off('click').on('click', function (event) {
        event.stopImmediatePropagation();
        OpenModal('help-modal-shortcodes');
    });

    // Tag List - Enter Key
    $('#add_tag').off('keyup').on('keyup', function (event) {
        event.stopImmediatePropagation();
        if (event.key === 'Enter') {
            AddTagsToMedia();
        }
    });

    // Add Tags - Button
    $('#add-tags').off('click').on('click', function (event) {
        event.stopImmediatePropagation();
        AddTagsToMedia();
    });

    // Tags - Remove Tag "X"
    $('.media-tag').find('.delete').off('click').on('click', function (event) {
        event.stopImmediatePropagation();
        const tagID = $(this).data('id');
        if (confirm('Are you sure you want to remove this tag?')) {
            RemoveTagFromMedia(tagID);
        }
    });
}

/**
 * @function AddEventListenersToTagsList
 * @description Binds the tag list page items to their respective functions.
 */
function AddEventListenersToTagsList() {
    const formHeader = $('#tag-list-new-tag-form-header');
    const tagNameInput = $('#new_tag_tag_name');
    const tagCategorySelect = $('#new_tag_category_select');
    const tagEditID = $('#new_tag_edit_id');
    const tagSubmitButton = $('#new_tag_btn_submit');
    const tagResetButton = $('#new_tag_btn_reset');
    const helpText = $('#new_tag_tag_name_help');
    const tagTableDataTable = $('#tag-list-page-table').DataTable();

    // Double-Click to Edit Tag
    $('#tag-list-page-table tbody').on('dblclick', 'tr', function () {
        const rowData = tagTableDataTable.row(this).data();
        tagEditID.val(rowData.tag_id);
        tagNameInput.val(rowData.tag_name);
        tagCategorySelect.val(rowData.category_id);
        formHeader.text('Edit Tag Form');
        tagNameInput.addClass('is-warning').removeClass('is-success is-danger');
        helpText.html(`You are currently editing the tag:<br/>${rowData.tag_name}`)
            .addClass('is-warning')
            .removeClass('is-hidden is-success is-danger');
    });

    // New Tag Name - Keyup Check Existing
    tagNameInput.on('keyup', function (event) {
        const tagName = tagNameInput.val();

        if (event.key === 'Enter') {
            if (tagEditID.val() !== '') {
                EditExistingTag();
            } else {
                AddNewTag();
            }
            return;
        }

        // If we are editing, ignore validation
        if (tagEditID.val() !== '') return;

        // Get the existing tags from the DataTable
        const existingTags = tagTableDataTable.column(1).data().toArray();

        if (existingTags.includes(tagName)) {
            tagNameInput.addClass('is-danger').removeClass('is-success');
            helpText.text('Tag already exists.')
                .addClass('is-danger')
                .removeClass('is-hidden is-success');
        } else if (tagName.length > 0) {
            tagNameInput.addClass('is-success').removeClass('is-danger');
            helpText.text('Tag is available.')
                .addClass('is-success')
                .removeClass('is-hidden is-danger');
        } else {
            tagNameInput.removeClass('is-danger is-success');
            helpText.text('').addClass('is-hidden').removeClass('is-danger is-success');
        }
    });

    // New Tag - Submit Button
    tagSubmitButton.on('click', function () {
        if (tagEditID.val() !== '') {
            EditExistingTag();
        } else {
            AddNewTag();
        }
    });

    // New Tag - Reset Button
    tagResetButton.on('click', function () {
        ResetTagForm(formHeader, tagNameInput, tagCategorySelect, tagEditID, helpText);
    });
}

// ============================================================
// Tag Form Operations
// ============================================================

/**
 * @function ResetTagForm
 * @description Resets the tag form fields and help text to their default state.
 * @param {jQuery} formHeader
 * @param {jQuery} tagNameInput
 * @param {jQuery} tagCategorySelect
 * @param {jQuery} tagEditID
 * @param {jQuery} helpText
 */
function ResetTagForm(formHeader, tagNameInput, tagCategorySelect, tagEditID, helpText) {
    formHeader.text('New Tag Form');
    tagNameInput.val('').removeClass('is-danger is-success is-warning');
    tagCategorySelect.prop('selectedIndex', 0);
    tagEditID.val('');
    helpText.text('').addClass('is-hidden').removeClass('is-danger is-success is-warning');
}

/**
 * @function AddNewTag
 * @description Adds a new tag to the database and refreshes the tag list.
 */
function AddNewTag() {
    const tagNameInput = $('#new_tag_tag_name');
    const tagCategorySelect = $('#new_tag_category_select');
    const helpText = $('#new_tag_tag_name_help');
    const tagTable = $('#tag-list-page-table').DataTable();
    const tagName = tagNameInput.val();
    const tagCategory = tagCategorySelect.val();
    const existingTags = tagTable.column(1).data().toArray();

    if (tagName.length > 0 && !existingTags.includes(tagName)) {
        addTag(tagName, tagCategory).then(() => {
            tagNameInput.val('').removeClass('is-danger is-success');
            tagCategorySelect.prop('selectedIndex', 0);
            helpText.text('').addClass('is-hidden').removeClass('is-danger is-success');
            RefreshTags();
            tagTable.ajax.reload();
        });
    } else {
        helpText.text('You cannot submit an empty tag or a tag that already exists.').addClass('is-danger');
        tagNameInput.addClass('is-danger');
    }
}

/**
 * @function EditExistingTag
 * @description Edits an existing tag in the database and refreshes the tag list.
 */
function EditExistingTag() {
    const formHeader = $('#tag-list-new-tag-form-header');
    const tagEditID = $('#new_tag_edit_id');
    const tagNameInput = $('#new_tag_tag_name');
    const tagCategorySelect = $('#new_tag_category_select');
    const helpText = $('#new_tag_tag_name_help');
    const tagTable = $('#tag-list-page-table').DataTable();

    const tagID = tagEditID.val();
    const tagName = tagNameInput.val();
    const tagCategory = tagCategorySelect.val();

    if (tagName.length > 0) {
        editTag(tagID, tagName, tagCategory).then(() => {
            ResetTagForm(formHeader, tagNameInput, tagCategorySelect, tagEditID, helpText);
            RefreshTags();
            tagTable.ajax.reload();
        });
    } else {
        alert('You cannot submit an empty tag.');
    }
}

// ============================================================
// Media Tag Operations
// ============================================================

/**
 * @function AddTagsToMedia
 * @description Adds tags to the media item currently being viewed.
 */
function AddTagsToMedia() {
    const tagsInput = $('#add_tag');
    const tags = tagsInput.val();
    const { itemID, itemURL, itemHash } = getActiveMediaInfo();

    addTagsToItem(itemID, tags).then(() => {
        $('#tag-list').empty();
        RenderPageMediaTags(itemID, itemURL, itemHash);
        RefreshTags();
    });

    tagsInput.val('');
}

/**
 * @function RemoveTagFromMedia
 * @description Removes a tag from the media item currently being viewed.
 * @param {number} tagID
 */
function RemoveTagFromMedia(tagID) {
    const { itemID, itemURL, itemHash } = getActiveMediaInfo();

    removeTagFromItem(itemID, tagID).then(() => {
        $('#tag-list').empty();
        RenderPageMediaTags(itemID, itemURL, itemHash);
    });
}

// ============================================================
// Modal Helpers
// ============================================================

/**
 * @function OpenModal
 * @description Opens a modal with the specified ID.
 * @param {string} modalID
 */
function OpenModal(modalID) {
    $(`#${modalID}`).addClass('is-active');
}

/**
 * @function CloseModal
 * @description Closes a specific modal by ID, or all modals if no ID is provided.
 * @param {string} [modalID=null]
 */
function CloseModal(modalID = null) {
    if (modalID !== null && typeof modalID === 'string') {
        $(`#${modalID}`).removeClass('is-active');
    } else {
        $('.modal').removeClass('is-active');
    }
}

// ============================================================
// UI State Setters
// ============================================================

/**
 * @function RefreshTags
 * @description Refreshes the tags by fetching them from the API and updating the tag list.
 */
function RefreshTags() {
    getTags().then((tags) => {
        ALL_TAGS = tags || [];
        setTagList(ALL_TAGS);
    });
}

/**
 * @function setPageTitle
 * @description Sets the page title based on the current page type (images or videos).
 */
function setPageTitle() {
    const titleSuffix = {
        [PAGE_IMAGES]: 'Images',
        [PAGE_VIDEOS]: 'Videos',
        [PAGE_TAGS]:   'Tags'
    };

    const title = titleSuffix[PAGE_TYPE]
        ? `${PAGE_TITLE} - ${titleSuffix[PAGE_TYPE]}`
        : PAGE_TITLE;

    document.title = title;
    $('#gallery-title').text(title);
}

/**
 * @function setTagList
 * @description Sets the tag list for the datalist elements using DocumentFragment for efficiency.
 * @param {Array} tagsList
 */
function setTagList(tagsList) {
    $('.datalist-for-tags').each(function () {
        const datalist = $(this);
        datalist.empty();

        // Build all options in a fragment, then append once
        const fragment = document.createDocumentFragment();
        tagsList.forEach(tag => {
            const option = document.createElement('option');
            option.value = tag.tag_name;
            fragment.appendChild(option);
        });
        datalist.append(fragment);
    });
}

// ============================================================
// API Functions
// ============================================================

/**
 * @function getPageTitle
 * @description Fetches the page title from the API.
 * @async
 * @returns {Promise<string>} A promise that resolves to the page title.
 */
async function getPageTitle() {
    try {
        return await fetchApi(`${API_BASE_URL}/config/title/`);
    } catch (error) {
        console.error('Error fetching page title:', error);
    }
}

/**
 * @function getTags
 * @description Fetches the tags from the API.
 * @async
 * @returns {Promise<Array>} A promise that resolves to the tags.
 */
async function getTags() {
    try {
        return await fetchApi(`${API_BASE_URL}/tags/all/`);
    } catch (error) {
        console.error('Error fetching tags:', error);
    }
}

/**
 * @function getTotalImages
 * @description Fetches the total number of images from the API.
 * @async
 * @returns {Promise<number>} A promise that resolves to the total number of images.
 */
async function getTotalImages() {
    try {
        return await fetchApi(`${API_BASE_URL}/images/total/`);
    } catch (error) {
        console.error('Error fetching total images:', error);
    }
}

/**
 * @function getTotalVideos
 * @description Fetches the total number of videos from the API.
 * @async
 * @returns {Promise<number>} A promise that resolves to the total number of videos.
 */
async function getTotalVideos() {
    try {
        return await fetchApi(`${API_BASE_URL}/videos/total/`);
    } catch (error) {
        console.error('Error fetching total videos:', error);
    }
}

/**
 * @function getTotalImagePages
 * @description Fetches the total number of image pages from the API.
 * @async
 * @returns {Promise<number>} A promise that resolves to the total number of image pages.
 */
async function getTotalImagePages() {
    const tagsSegment = CURRENT_TAGS.length > 0
        ? `/with-tags/${encodeURIComponent(CURRENT_TAGS.join())}`
        : '';

    try {
        return await fetchApi(`${API_BASE_URL}/pages/images${tagsSegment}/${ITEMS_PER_PAGE}/`);
    } catch (error) {
        console.error('Error fetching total image pages:', error);
    }
}

/**
 * @function getTotalVideoPages
 * @description Fetches the total number of video pages from the API.
 * @async
 * @returns {Promise<number>} A promise that resolves to the total number of video pages.
 */
async function getTotalVideoPages() {
    const tagsSegment = CURRENT_TAGS.length > 0
        ? `/with-tags/${encodeURIComponent(CURRENT_TAGS.join())}`
        : '';

    try {
        return await fetchApi(`${API_BASE_URL}/pages/videos${tagsSegment}/${ITEMS_PER_PAGE}/`);
    } catch (error) {
        console.error('Error fetching total video pages:', error);
    }
}

/**
 * @function getImagesForPage
 * @description Fetches the images for a specific page from the API.
 * @async
 * @param {number} page The page number to fetch images for.
 * @returns {Promise<Array>} A promise that resolves to the images for the page.
 */
async function getImagesForPage(page) {
    const apiLink = CURRENT_TAGS.length > 0
        ? `${API_BASE_URL}/images/with-tags/${encodeURIComponent(CURRENT_TAGS.join())}/${page}/${ITEMS_PER_PAGE}/`
        : `${API_BASE_URL}/images/page/${page}/${ITEMS_PER_PAGE}/`;

    try {
        return await fetchApi(apiLink);
    } catch (error) {
        console.error('Error fetching images:', error);
    }
}

/**
 * @function getVideosForPage
 * @description Fetches the videos for a specific page from the API.
 * @async
 * @param {number} page The page number to fetch videos for.
 * @returns {Promise<Array>} A promise that resolves to the videos for the page.
 */
async function getVideosForPage(page) {
    const apiLink = CURRENT_TAGS.length > 0
        ? `${API_BASE_URL}/videos/with-tags/${encodeURIComponent(CURRENT_TAGS.join())}/${page}/${ITEMS_PER_PAGE}/`
        : `${API_BASE_URL}/videos/page/${page}/${ITEMS_PER_PAGE}/`;

    try {
        return await fetchApi(apiLink);
    } catch (error) {
        console.error('Error fetching videos:', error);
    }
}

/**
 * @function getTagsForItem
 * @description Fetches the tags for a specific image or video from the API.
 * @async
 * @param {number} itemID The ID of the image or video to fetch tags for.
 * @returns {Promise<Array>} A promise that resolves to the tags for the item.
 */
async function getTagsForItem(itemID) {
    const mediaType = PAGE_TYPE === PAGE_IMAGES ? 'image' : 'video';

    try {
        return await fetchApi(`${API_BASE_URL}/tags/for/${mediaType}/${itemID}/`);
    } catch (error) {
        console.error('Error fetching tags for item:', error);
    }
}

/**
 * @function addTagsToItem
 * @description Adds tags to a specific image or video.
 * @async
 * @param {number} itemID
 * @param {string} tags
 * @returns {Promise<*>} A promise that resolves to the updated tags for the item.
 */
async function addTagsToItem(itemID, tags) {
    const mediaType = PAGE_TYPE === PAGE_IMAGES ? 'image' : 'video';

    try {
        return await fetchApi(`${API_BASE_URL}/tags/${mediaType}/add/`, {
            method: 'PATCH',
            headers: JSON_HEADERS,
            body: JSON.stringify({ item_id: itemID, tag_list: tags })
        });
    } catch (error) {
        console.error('Error adding tags to item:', error);
    }
}

/**
 * @function removeTagFromItem
 * @description Removes a tag from a specific image or video.
 * @async
 * @param {number} itemID
 * @param {number} tagID
 * @returns {Promise<*>} A promise that resolves to the updated tags for the item.
 */
async function removeTagFromItem(itemID, tagID) {
    const mediaType = PAGE_TYPE === PAGE_IMAGES ? 'image' : 'video';

    try {
        return await fetchApi(`${API_BASE_URL}/tags/${mediaType}/remove/`, {
            method: 'PATCH',
            headers: JSON_HEADERS,
            body: JSON.stringify({ item_id: itemID, tag_id: tagID })
        });
    } catch (error) {
        console.error('Error removing tag from item:', error);
    }
}

/**
 * @function addTag
 * @description Adds a new tag to the database.
 * @async
 * @param {string} tagName The name of the tag to add.
 * @param {number} tagCategory The category ID of the tag.
 * @returns {Promise<*>} A promise that resolves to true on success.
 */
async function addTag(tagName, tagCategory) {
    try {
        return await fetchApi(`${API_BASE_URL}/tags/add/`, {
            method: 'POST',
            headers: JSON_HEADERS,
            body: JSON.stringify({ tag_name: tagName, category_id: tagCategory })
        });
    } catch (error) {
        console.error('Error adding tag:', error);
    }
}

/**
 * @function editTag
 * @description Edits an existing tag in the database.
 * @async
 * @param {number} tagID The ID of the tag to edit.
 * @param {string} tagName The new name of the tag.
 * @param {number} tagCategory The new category ID of the tag.
 * @returns {Promise<*>} A promise that resolves to true on success.
 */
async function editTag(tagID, tagName, tagCategory) {
    try {
        return await fetchApi(`${API_BASE_URL}/tags/edit/${tagID}/`, {
            method: 'PUT',
            headers: JSON_HEADERS,
            body: JSON.stringify({ tag_name: tagName, category_id: tagCategory })
        });
    } catch (error) {
        console.error('Error editing tag:', error);
    }
}