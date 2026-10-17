/**
 * Formulaire de séance : la date choisie est le jour de monte de quelqu'un d'autre → on le dit avant d'enregistrer
 * (la personne est prévenue à l'enregistrement). hints : { horseId: { 'AAAA-MM-JJ': [userId, nom] | null } }.
 */
export function ridingDayHint({ hints, me }) {
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
                this.text = entry && entry[0] !== rider
                    ? `C'est le jour de monte de ${entry[1]} : une notification lui sera envoyée à l'enregistrement.`
                    : '';
            };
            form.addEventListener('input', update);
            form.addEventListener('change', update);
            update();
        },
    };
}
