import Alpine from 'alpinejs';

const QUARTERS = ['tl', 'tr', 'br', 'bl'];

/**
 * Turn the tiles of a chain field one step, nearest first, fading with distance.
 * Tiles carry their distance from the origin in data-d.
 */
function ripple(field, dir) {
    if (!field || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    field.querySelectorAll('.ns-tile[data-d]').forEach((tile, i) => {
        const d = Number(tile.dataset.d);
        if (d > 6 || (d > 0 && i % 3 === 0)) return;

        tile.style.transitionDelay = `${d * 70}ms`;
        const cl = tile.classList;
        const q = QUARTERS.findIndex((name) => cl.contains(`ns-tile--q-${name}`));

        if (q > -1) {
            cl.replace(`ns-tile--q-${QUARTERS[q]}`, `ns-tile--q-${QUARTERS[(q + dir + 4) % 4]}`);
        } else if (!cl.replace('ns-tile--p-a', 'ns-tile--p-b')) {
            cl.replace('ns-tile--p-b', 'ns-tile--p-a');
        }
    });
}

/**
 * Hero panel: one meal, judged for different people. Every person's verdict is
 * rendered by the server; this only switches between them and sets off the tiles.
 */
Alpine.data('fitDemo', () => ({
    active: 0,

    choose(index) {
        if (index === this.active) return;
        const dir = index > this.active ? 1 : -1;
        this.active = index;
        ripple(this.$refs.field, dir);
    },
}));

/**
 * A message that closes itself after `timeout` ms (0 = stays until closed). Pointing at it
 * or focusing inside it pauses the countdown, so it never vanishes while being read.
 */
Alpine.data('flash', (timeout) => ({
    open: true,
    paused: false,
    remaining: timeout,
    startedAt: 0,
    timer: null,

    init() {
        if (timeout > 0) this.start();
    },

    start() {
        this.startedAt = Date.now();
        this.timer = setTimeout(() => this.close(), this.remaining);
    },

    pause() {
        if (!this.timer) return;
        clearTimeout(this.timer);
        this.timer = null;
        this.remaining -= Date.now() - this.startedAt;
        this.paused = true;
    },

    resume() {
        if (!this.open || !this.paused) return;
        this.paused = false;
        this.start();
    },

    close() {
        clearTimeout(this.timer);
        this.open = false;
    },
}));

window.Alpine = Alpine;
Alpine.start();
