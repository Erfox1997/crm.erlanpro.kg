/**
 * Persistent messenger cache (IndexedDB) so chats open instantly in browser / Android WebView.
 * Network still refreshes in the background — same idea as WhatsApp local storage.
 */

const DB_NAME = 'crm-messenger-cache';
const DB_VERSION = 1;
const STORE = 'messages';
const MAX_MESSAGES_PER_CHAT = 250;

/** @type {Promise<IDBDatabase>|null} */
let dbPromise = null;

function openDb() {
    if (typeof indexedDB === 'undefined') {
        return Promise.reject(new Error('IndexedDB unavailable'));
    }

    if (!dbPromise) {
        dbPromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = () => {
                const db = request.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'key' });
                }
            };

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => {
                dbPromise = null;
                reject(request.error || new Error('IndexedDB open failed'));
            };
        });
    }

    return dbPromise;
}

/**
 * @param {number|string|null|undefined} companyId
 * @param {number|string|null|undefined} conversationId
 */
function cacheKey(companyId, conversationId) {
    return `${Number(companyId)}:${Number(conversationId)}`;
}

/**
 * Drop heavy / non-serializable fields before writing to disk.
 * @param {any[]} messages
 */
function sanitizeMessages(messages) {
    const list = Array.isArray(messages) ? messages : [];
    const trimmed = list.length > MAX_MESSAGES_PER_CHAT
        ? list.slice(list.length - MAX_MESSAGES_PER_CHAT)
        : list;

    return trimmed.map((message) => {
        const copy = { ...message };

        // Never persist optimistic in-flight blobs.
        if (copy._localBlobUrl) {
            delete copy._localBlobUrl;
        }
        if (copy._pendingFile) {
            delete copy._pendingFile;
        }

        return copy;
    });
}

/**
 * @param {number|string} companyId
 * @param {number|string} conversationId
 * @returns {Promise<any[]|null>}
 */
export async function loadCachedMessages(companyId, conversationId) {
    if (!companyId || !conversationId) {
        return null;
    }

    try {
        const db = await openDb();
        const key = cacheKey(companyId, conversationId);

        return await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readonly');
            const request = tx.objectStore(STORE).get(key);

            request.onsuccess = () => {
                const row = request.result;
                if (!row || !Array.isArray(row.messages) || row.messages.length === 0) {
                    resolve(null);
                    return;
                }
                resolve(row.messages.map((message) => ({ ...message })));
            };
            request.onerror = () => reject(request.error);
        });
    } catch {
        return null;
    }
}

/**
 * @param {number|string} companyId
 * @param {number|string} conversationId
 * @param {any[]} messages
 */
export async function saveCachedMessages(companyId, conversationId, messages) {
    if (!companyId || !conversationId) {
        return;
    }

    const payload = sanitizeMessages(messages);
    if (payload.length === 0) {
        return;
    }

    try {
        const db = await openDb();
        const key = cacheKey(companyId, conversationId);

        await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).put({
                key,
                companyId: Number(companyId),
                conversationId: Number(conversationId),
                messages: payload,
                updatedAt: Date.now(),
            });
            tx.oncomplete = () => resolve();
            tx.onerror = () => reject(tx.error);
        });
    } catch {
        // Cache is best-effort.
    }
}

/**
 * Warm memory map from disk for the first N chats in the list.
 * @param {number|string} companyId
 * @param {Array<{ id: number }>} conversations
 * @param {Map<number, any[]>} memoryCache
 * @param {number} [limit]
 */
export async function warmMessageCache(companyId, conversations, memoryCache, limit = 40) {
    if (!companyId || !memoryCache || !Array.isArray(conversations)) {
        return;
    }

    const targets = conversations
        .slice(0, limit)
        .map((row) => Number(row?.id))
        .filter((id) => id && !memoryCache.has(id));

    await Promise.all(targets.map(async (id) => {
        const messages = await loadCachedMessages(companyId, id);
        if (messages?.length && !memoryCache.has(id)) {
            memoryCache.set(id, messages);
        }
    }));
}
