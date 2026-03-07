import './bootstrap';

const wakeLockMediaQuery = window.matchMedia('(max-width: 767px)');

const canUseWakeLock = () => {
    return window.isSecureContext
        && 'wakeLock' in navigator
        && typeof navigator.wakeLock.request === 'function';
};

class RecipeWakeLockController {
    constructor(root) {
        this.root = root;
        this.toggle = root.querySelector('[data-wake-lock-toggle]');
        this.message = root.querySelector('[data-wake-lock-message]');
        this.defaultLabel = this.toggle?.dataset.defaultLabel ?? 'Keep Screen Awake';
        this.activeLabel = this.toggle?.dataset.activeLabel ?? 'Screen Awake';
        this.sentinel = null;
        this.shouldResume = false;
        this.boundToggle = () => {
            this.handleToggle().catch(() => {});
        };
        this.boundVisibilityChange = () => {
            this.handleVisibilityChange().catch(() => {});
        };
        this.boundPageHide = () => {
            this.release({ shouldResume: false }).catch(() => {});
        };
        this.boundMediaChange = () => {
            this.handleViewportChange().catch(() => {});
        };
    }

    init() {
        if (!this.toggle || !canUseWakeLock()) {
            return;
        }

        this.toggle.addEventListener('click', this.boundToggle);
        document.addEventListener('visibilitychange', this.boundVisibilityChange);
        window.addEventListener('pagehide', this.boundPageHide);
        wakeLockMediaQuery.addEventListener('change', this.boundMediaChange);

        this.handleViewportChange().catch(() => {});
    }

    async handleToggle() {
        if (this.sentinel) {
            await this.release({ shouldResume: false });

            return;
        }

        this.shouldResume = true;
        await this.acquire();
    }

    async handleVisibilityChange() {
        if (document.visibilityState === 'visible' && this.shouldResume && !this.sentinel) {
            await this.acquire();
        }
    }

    async handleViewportChange() {
        if (!wakeLockMediaQuery.matches) {
            this.root.hidden = true;

            if (this.sentinel) {
                await this.release({ shouldResume: false });
            }

            return;
        }

        this.root.hidden = false;
    }

    async acquire() {
        if (!canUseWakeLock()) {
            return;
        }

        if (!wakeLockMediaQuery.matches) {
            this.showMessage('Keep Screen Awake is available on mobile recipe view only.', true);

            return;
        }

        try {
            this.sentinel = await navigator.wakeLock.request('screen');
            this.sentinel.addEventListener('release', () => {
                this.sentinel = null;
                this.updateState(false);

                if (this.shouldResume && document.visibilityState === 'visible' && wakeLockMediaQuery.matches) {
                    this.acquire().catch(() => {});
                }
            });

            this.updateState(true);
            this.showMessage('Your screen will stay awake while this recipe stays open.', false);
        } catch (error) {
            this.sentinel = null;
            this.shouldResume = false;
            this.updateState(false);
            this.showMessage(this.normalizeError(error), true);
        }
    }

    async release({ shouldResume }) {
        this.shouldResume = shouldResume;

        if (this.sentinel) {
            const wakeLock = this.sentinel;
            this.sentinel = null;

            try {
                await wakeLock.release();
            } catch {
                // Ignore release errors and reset UI state anyway.
            }
        }

        this.updateState(false);
        this.showMessage('Screen awake mode is off.', false);
    }

    updateState(active) {
        if (!this.toggle) {
            return;
        }

        this.toggle.textContent = active ? this.activeLabel : this.defaultLabel;
        this.toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
        this.toggle.classList.toggle('bg-orange-600', active);
        this.toggle.classList.toggle('border-orange-600', active);
        this.toggle.classList.toggle('text-white', active);
        this.toggle.classList.toggle('hover:bg-orange-700', active);
        this.toggle.classList.toggle('bg-white', !active);
        this.toggle.classList.toggle('border-orange-200', !active);
        this.toggle.classList.toggle('text-stone-700', !active);
        this.toggle.classList.toggle('hover:bg-orange-50', !active);
    }

    showMessage(message, isError) {
        if (!this.message) {
            return;
        }

        if (!message) {
            this.message.hidden = true;
            this.message.textContent = '';
            this.message.classList.remove('text-red-700');
            this.message.classList.add('text-stone-600');

            return;
        }

        this.message.hidden = false;
        this.message.textContent = message;
        this.message.classList.toggle('text-red-700', isError);
        this.message.classList.toggle('text-stone-600', !isError);
    }

    normalizeError(error) {
        if (error instanceof Error && error.message.trim() !== '') {
            return error.message;
        }

        return 'Unable to keep this screen awake on this device right now.';
    }
}

const initializeRecipeWakeLockControls = () => {
    document.querySelectorAll('[data-wake-lock-root]').forEach((root) => {
        if (root.dataset.wakeLockInitialized === 'true') {
            return;
        }

        root.dataset.wakeLockInitialized = 'true';

        const controller = new RecipeWakeLockController(root);
        controller.init();
    });
};

document.addEventListener('DOMContentLoaded', initializeRecipeWakeLockControls);
document.addEventListener('livewire:navigated', initializeRecipeWakeLockControls);
