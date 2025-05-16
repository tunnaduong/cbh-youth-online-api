/**
 * Get the URL for a static asset
 * @param {string} path - The path to the asset relative to the public directory
 * @returns {string} The full URL to the asset
 */
export function asset(path) {
    // Remove leading slash if present
    const cleanPath = path.replace(/^\//, "");

    // For development
    if (import.meta.env.DEV) {
        return `/${cleanPath}`;
    }

    // For production (assuming assets are in the public directory)
    return `${window.location.origin}/${cleanPath}`;
}

/**
 * Get the URL for a storage asset
 * @param {string} path - The path to the asset relative to the storage directory
 * @returns {string} The full URL to the storage asset
 */
export function storage(path) {
    const cleanPath = path.replace(/^\//, "");
    return `${window.location.origin}/storage/${cleanPath}`;
}
