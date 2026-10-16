/** Formulaire de soin : fréquence du prochain passage, préremplie selon la règle du cheval. */
export function careNext(opts) {
    return {
        ...opts,
        init() {
            if (!this.fromOld) this.applyRule();
            this.$watch('cat', () => { this.applyRule(); this.proposePro(); });
            // Un montant saisi coche « Ajouter le coût aux dépenses ».
            this.$watch('cost', (v) => { if (String(v ?? '').trim() !== '' && Number(v) > 0) this.addExpense = true; });
        },
        /** Intervenant habituel de la catégorie, proposé seulement si aucun n'est déjà choisi. */
        proposePro() {
            const pro = (this.usualPros || {})[this.cat];
            const select = this.$root.querySelector('select[name=professional_id]');
            if (!pro || !select || select.value || !select.querySelector(`option[value="${pro}"]`)) return;
            select.value = pro;
            select.dispatchEvent(new Event('change', { bubbles: true }));
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
