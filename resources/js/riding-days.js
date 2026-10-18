/**
 * Formulaire de séance : la date choisie est le jour de monte de quelqu'un d'autre, ou un jour de repos du cheval
 * → on le dit avant d'enregistrer (la personne du jour, ou le propriétaire, est prévenue à l'enregistrement).
 * hints : { horseId: { 'AAAA-MM-JJ': [userId, nom] | null } } ; rest : { horseId: { 'AAAA-MM-JJ': 'raisons' } }.
 */
export function ridingDayHint({ hints, rest = {}, managed = [], me }) {
    return {
        text: '',
        init() {
            const form = this.$el.closest('form');
            if (!form) return;
            const update = () => {
                const horse = form.querySelector('[name=horse_id]')?.value;
                const date = (form.querySelector('[name=scheduled_at]')?.value || '').slice(0, 10);
                const rider = parseInt(form.querySelector('[name=rider_id]')?.value, 10) || me;
                const entry = hints?.[horse]?.[date];
                const resting = rest?.[horse]?.[date];
                this.text = [
                    resting ? `Jour de repos du cheval (${resting}) : la séance reste possible${managed.includes(parseInt(horse, 10)) ? '.' : ', le propriétaire sera prévenu.'}` : '',
                    entry && entry[0] !== rider ? `C'est le jour de monte de ${entry[1]} : une notification lui sera envoyée à l'enregistrement.` : '',
                ].filter(Boolean).join(' ');
            };
            form.addEventListener('input', update);
            form.addEventListener('change', update);
            update();
        },
    };
}
