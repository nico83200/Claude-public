/**
 * Calendrier sur téléphone : le haut de la page (titre, boutons, filtres) reste fixe ; seule la liste défile,
 * centrée à l'ouverture sur « Aujourd'hui » (ou sur l'événement qui vient d'être ajouté).
 */
export function agendaScroll() {
    return {
        mobile: false,
        init() {
            const mq = window.matchMedia('(max-width: 767px)');
            const apply = () => {
                this.mobile = mq.matches && this.$el.offsetParent !== null;
                this.size();
            };
            mq.addEventListener?.('change', apply);
            window.addEventListener('resize', () => this.size());
            const top = document.querySelector('[data-calendar-top]');
            if (top && 'ResizeObserver' in window) new ResizeObserver(() => this.size()).observe(top);
            this.$nextTick(() => { apply(); this.center(false); });
        },
        size() {
            const el = this.$el;
            if (!this.mobile) {
                el.style.height = '';
                document.documentElement.classList.remove('agenda-locked');
                return;
            }
            const bar = document.querySelector('nav[aria-label="Navigation rapide"]');
            const bottom = bar ? bar.getBoundingClientRect().height : 0;
            const height = window.innerHeight - el.getBoundingClientRect().top - bottom - 8;
            el.style.height = Math.max(240, Math.round(height)) + 'px';
            document.documentElement.classList.add('agenda-locked');
        },
        /** Centre la liste sur l'événement mis en avant ou sur « Aujourd'hui » ; retourne false s'il n'y a rien à centrer. */
        center(smooth = true) {
            const target = this.$el.querySelector('[data-highlight]') || this.$el.querySelector('[data-today-divider]');
            if (!target) return false;
            if (this.mobile) {
                const offset = target.getBoundingClientRect().top - this.$el.getBoundingClientRect().top + this.$el.scrollTop;
                this.$el.scrollTo({ top: Math.max(0, offset - this.$el.clientHeight / 2 + target.offsetHeight / 2), behavior: smooth ? 'smooth' : 'auto' });
            } else {
                target.scrollIntoView({ block: 'center', behavior: smooth ? 'smooth' : 'auto' });
            }
            return true;
        },
        destroy() {
            document.documentElement.classList.remove('agenda-locked');
        },
    };
}
