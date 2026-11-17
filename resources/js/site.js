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

/**
 * The notification bell: opens a list of the latest notices, refreshes every minute while
 * the tab is visible (and when it comes back into view), and marks all as read.
 */
Alpine.data('bell', (initial, urls) => ({
    open: false,
    unread: initial.unread,
    items: initial.items,
    timer: null,

    init() {
        this.timer = setInterval(() => this.refresh(), 60000);
        document.addEventListener('visibilitychange', () => this.refresh());
    },

    destroy() {
        clearInterval(this.timer);
    },

    toggle() {
        this.open = !this.open;
        if (this.open) this.refresh();
    },

    async refresh() {
        if (document.hidden) return;
        try {
            const response = await fetch(urls.feed, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            this.unread = data.unread;
            this.items = data.items;
        } catch {
            // Offline for a moment: keep what is shown.
        }
    },

    async readAll() {
        const token = this.$root.querySelector('input[name=_token]')?.value;
        const response = await fetch(urls.read, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
        });
        if (response.ok) {
            this.unread = 0;
            this.items = this.items.map((item) => ({ ...item, read: true }));
        }
    },
}));

/** Arabic search key, as App\Services\Import\ArabicNormalizer: "ارز" finds "أرز". */
function normalizeArabic(text) {
    return String(text ?? '')
        .trim()
        .replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d))
        .replace(/[ً-ٰٟـ]/g, '')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .toLowerCase()
        .replace(/\s+/g, ' ');
}

/**
 * "اطبخها على طريقتك": a kitchen dish's recipe, changed by the visitor. The ingredient list
 * comes once from the server's cache; "= … غ" is worked out here while typing, and the server
 * converts and analyses everything again on «حلّل». The draft stays in this tab only
 * (sessionStorage), never on the server.
 */
Alpine.data('customRecipe', (config) => ({
    ingredients: [],
    byId: {},
    loading: true,
    lines: [],
    servings: 1,
    weightAfter: null,
    maxLines: config.maxLines,
    busy: false,
    error: '',
    resultHtml: '',
    nextKey: 1,
    applied: config.applied ?? null,

    async init() {
        // A suggestion opened from the meal page ("اطبخها هكذا") starts from it, not the kept draft.
        this.load(this.applied ? config.recipe : (this.saved() ?? config.recipe));
        this.$watch('lines', () => this.save(), { deep: true });
        this.$watch('servings', () => this.save());
        this.$watch('weightAfter', () => this.save());
        try {
            const response = await fetch(config.ingredientsUrl, { headers: { Accept: 'application/json' } });
            this.ingredients = await response.json();
            this.byId = Object.fromEntries(this.ingredients.map((item) => [item.id, item]));
        } catch {
            this.error = 'تعذّر تحميل قائمة المكوّنات. حدّث الصفحة.';
        } finally {
            this.loading = false;
        }
    },

    load(recipe) {
        this.lines = recipe.lines.map((line) => this.line(line));
        this.servings = recipe.servings || 1;
        this.weightAfter = recipe.weight_after || null;
    },

    line(data = {}) {
        return {
            key: this.nextKey++,
            record_id: data.record_id ?? null,
            unit_id: data.unit_id ?? null,
            amount: data.amount ?? null,
            portion_id: data.portion_id ?? null,
            after: Boolean(data.after),
            query: '',
            open: false,
        };
    },

    saved() {
        try {
            return JSON.parse(sessionStorage.getItem(config.storageKey));
        } catch {
            return null;
        }
    },

    save() {
        try {
            sessionStorage.setItem(config.storageKey, JSON.stringify({
                lines: this.lines.map(({ record_id, unit_id, amount, portion_id, after }) => ({ record_id, unit_id, amount, portion_id, after })),
                servings: this.servings,
                weight_after: this.weightAfter,
            }));
        } catch {
            // Private mode or full storage: the draft simply is not kept.
        }
    },

    reset() {
        try { sessionStorage.removeItem(config.storageKey); } catch { /* nothing kept */ }
        this.load(config.original ?? config.recipe);
        this.applied = null;
        this.resultHtml = '';
        this.error = '';
    },

    // "جرّبها" in the result (step 6): the same cut applied to the recipe being edited, then
    // analysed again. An amount cut the way the server cuts it (KitchenRecipeController::scaled).
    apply(change) {
        const fractions = { '0.75': 'بثلاثة أرباع الكمية', '0.6667': 'بثلثي الكمية', '0.5': 'بنصف الكمية' };
        const said = [];
        change.c.forEach((cut) => {
            const matches = (line) => this.byId[line.record_id]?.name === cut.i && Boolean(line.after) === Boolean(cut.a);
            if (cut.k <= 0) {
                this.lines = this.lines.filter((line) => !matches(line));
            } else {
                this.lines.forEach((line) => {
                    if (!matches(line) || !(line.amount > 0)) return;
                    const amount = line.amount * cut.k;
                    line.amount = amount >= 10 ? Math.round(amount) : Math.max(0.5, Math.round(amount * 2) / 2);
                });
            }
            const name = cut.i + (cut.a ? ' على الوجه' : '');
            said.push(cut.k <= 0 ? 'بلا ' + name : name + ' ' + (fractions[String(cut.k)] ?? 'بكمية أقل'));
        });
        this.applied = said.join('، و');
        this.weightAfter = null; // less of an ingredient weighs less: estimated again from ours
        this.analyze();
    },

    label(id) {
        const item = this.byId[id];
        return item ? item.name + (item.form ? ' (' + item.form + ')' : '') : '';
    },

    matches(query) {
        const q = normalizeArabic(query);
        const found = q === '' ? this.ingredients : this.ingredients.filter((item) => item.search.includes(q));
        return found.slice(0, 12);
    },

    openSearch(line) {
        line.query = '';
        line.open = true;
    },

    closeLater(line) {
        setTimeout(() => { line.open = false; }, 150);
    },

    choose(line, option) {
        line.record_id = option.id;
        line.open = false;
        const units = option.units;
        if (!units.some((unit) => unit.id === line.unit_id)) {
            line.unit_id = units[0]?.id ?? null;
        }
        this.presetSize(line);
    },

    unitsOf(line) {
        return this.byId[line.record_id]?.units ?? [];
    },

    unitOf(line) {
        return this.unitsOf(line).find((unit) => unit.id === line.unit_id) ?? null;
    },

    choicesOf(line) {
        return this.unitOf(line)?.choices ?? [];
    },

    presetSize(line) {
        const unit = this.unitOf(line);
        const valid = unit?.choices.some((choice) => choice.id === line.portion_id);
        if (!valid) line.portion_id = unit?.default ?? null;
    },

    grams(line) {
        const unit = this.unitOf(line);
        if (!unit || !(line.amount > 0)) return null;
        const choice = unit.choices.find((c) => c.id === line.portion_id);
        const perUnit = choice ? choice.grams : unit.grams;
        return perUnit === null ? null : line.amount * perUnit;
    },

    gramsText(line) {
        if (!line.record_id) return 'اختر المكوّن';
        if (!this.unitOf(line)) return 'اختر الوحدة';
        if (this.choicesOf(line).length && !line.portion_id && this.unitOf(line).grams === null) return 'اختر حجم الحصة';
        const grams = this.grams(line);
        return grams === null ? 'اكتب الكمية' : '= ' + Math.round(grams * 10) / 10 + ' غرام';
    },

    totalText() {
        const total = this.lines.reduce((sum, line) => sum + (this.grams(line) ?? 0), 0);
        return 'المجموع ' + Math.round(total) + ' غرام';
    },

    add() {
        if (this.lines.length < this.maxLines) this.lines.push(this.line({ amount: 1 }));
    },

    remove(i) {
        this.lines.splice(i, 1);
    },

    async analyze() {
        this.error = '';
        this.busy = true;
        try {
            const response = await fetch(config.analyzeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.$root.querySelector('input[name=_token]')?.value,
                },
                body: JSON.stringify({
                    lines: this.lines.map(({ record_id, unit_id, amount, portion_id, after }) => ({ record_id, unit_id, amount, portion_id, after })),
                    servings: this.servings,
                    weight_after: this.weightAfter || null,
                }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                this.error = first || data.message || 'تعذّر التحليل. حاول مرة أخرى.';
                return;
            }
            this.resultHtml = data.html;
            this.$nextTick(() => this.$refs.result.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        } catch {
            this.error = 'لا اتصال الآن. حاول بعد قليل.';
        } finally {
            this.busy = false;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
