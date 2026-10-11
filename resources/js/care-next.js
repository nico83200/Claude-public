/** Formulaire de soin : fréquence du prochain passage, préremplie selon la règle du cheval. */
export function careNext(opts) {
    return {
        ...opts,
        init() {
            if (!this.fromOld) this.applyRule();
            this.$watch('cat', () => this.applyRule());
        },
        applyRule() {
            const rule = this.rules[this.cat];
            if (rule) {
                this.value = rule.value;
                this.unit = rule.unit;
                if (this.mode === 'none' && rule.enabled && !this.$root.querySelector('input[name=version]')) this.mode = 'interval';
            } else if (!this.value) {
                this.value = '';
            }
        },
        get nextDate() {
            const n = parseInt(this.value, 10);
            if (!n || !this.performed) return '';
            const d = new Date(this.performed);
            if (Number.isNaN(d.getTime())) return '';
            if (this.unit === 'weeks') d.setDate(d.getDate() + n * 7);
            else {
                const day = d.getDate();
                d.setDate(1); d.setMonth(d.getMonth() + n);
                d.setDate(Math.min(day, new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate()));
            }
            return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        },
    };
}
