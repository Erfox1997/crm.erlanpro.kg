let badgePermissionRequested = false;

/**
 * Keep the Android launcher badge in sync with CRM unread count.
 * Re-applies on resume so the badge does not stay cleared just because the app was opened.
 */
export async function syncAppBadge(count) {
    const capacitor = typeof window !== 'undefined' ? window.Capacitor : null;
    if (!capacitor?.isNativePlatform?.()) {
        return;
    }

    const n = Math.max(0, Math.floor(Number(count) || 0));
    const Badge = capacitor.Plugins?.Badge;
    const PushNotifications = capacitor.Plugins?.PushNotifications;

    if (!badgePermissionRequested && Badge?.requestPermissions) {
        badgePermissionRequested = true;
        try {
            const perm = await Badge.checkPermissions?.() || {};
            if (perm.display !== 'granted') {
                await Badge.requestPermissions();
            }
        } catch {
            // optional
        }
    }

    try {
        if (Badge?.isSupported) {
            const supported = await Badge.isSupported();
            if (supported?.isSupported !== false) {
                if (n > 0 && Badge.set) {
                    await Badge.set({ count: n });
                } else if (n === 0 && Badge.clear) {
                    await Badge.clear();
                }
            }
        } else if (Badge?.set) {
            if (n > 0) {
                await Badge.set({ count: n });
            } else if (Badge.clear) {
                await Badge.clear();
            }
        }
    } catch {
        // Badge plugin optional / launcher may ignore
    }

    try {
        if (n === 0 && PushNotifications?.removeAllDeliveredNotifications) {
            await PushNotifications.removeAllDeliveredNotifications();
        }
    } catch {
        // ignore
    }
}

export async function fetchAndSyncAppBadge() {
    const capacitor = typeof window !== 'undefined' ? window.Capacitor : null;
    if (!capacitor?.isNativePlatform?.()) {
        return;
    }

    try {
        const { data } = await window.axios.get(route('messenger.unread-count'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        await syncAppBadge(data?.unread_count ?? 0);
    } catch {
        // ignore network errors on badge sync
    }
}

export function bindNativeAppBadgeLifecycle(getCount) {
    const capacitor = typeof window !== 'undefined' ? window.Capacitor : null;
    if (!capacitor?.isNativePlatform?.()) {
        return () => {};
    }

    const App = capacitor.Plugins?.App;
    let removeAppListener = null;

    const refresh = async () => {
        if (typeof getCount === 'function') {
            const local = getCount();
            if (local !== null && local !== undefined) {
                await syncAppBadge(local);
            }
        }
        await fetchAndSyncAppBadge();
    };

    refresh();

    if (App?.addListener) {
        App.addListener('appStateChange', ({ isActive }) => {
            if (isActive) {
                refresh();
            }
        }).then((handle) => {
            removeAppListener = () => handle?.remove?.();
        }).catch(() => {});
    }

    const onVisible = () => {
        if (document.visibilityState === 'visible') {
            refresh();
        }
    };
    document.addEventListener('visibilitychange', onVisible);

    return () => {
        removeAppListener?.();
        document.removeEventListener('visibilitychange', onVisible);
    };
}
