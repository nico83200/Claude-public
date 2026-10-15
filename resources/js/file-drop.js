// Envoi d'une photo ou d'un fichier : sur téléphone, « Photographier » ; sur ordinateur (souris),
// « Choisir un fichier » et glisser-déposer du fichier sur la carte. Le formulaire part dès le choix.
// extra : données propres à la carte (ex. cheval choisi et adresses d'envoi sur la page Santé).
export function fileDrop(extra = {}) {
    return {
        ...extra,
        busy: false,
        over: false,
        desktop: window.matchMedia('(hover: hover) and (pointer: fine)').matches,
        enter(e) {
            if (this.busy || !e.dataTransfer?.types?.includes('Files')) return;
            e.preventDefault();
            this.over = true;
        },
        leave(e) {
            if (!this.$root.contains(e.relatedTarget)) this.over = false;
        },
        drop(e) {
            this.over = false;
            const files = e.dataTransfer?.files;
            if (this.busy || !files?.length) return;
            e.preventDefault();
            const input = this.$root.querySelector('input[type=file]');
            if (!input) return;
            const transfer = new DataTransfer();
            transfer.items.add(files[0]);
            input.files = transfer.files;
            this.send(input);
        },
        send(input) {
            if (!input.files?.length || this.busy) return;
            this.busy = true;
            input.form.requestSubmit();
        },
    };
}
