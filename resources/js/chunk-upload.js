/** Envoi d'un fichier par morceaux (contourne les limites d'envoi de l'hébergeur). */
export function chunkUpload(url, chunkSize, extensions = []) {
    return {
        file: null,
        progress: 0,
        busy: false,
        error: '',
        pick(e) {
            this.file = e.target.files[0] || null;
            this.error = '';
            const ext = (this.file?.name.split('.').pop() || '').toLowerCase();
            if (this.file && extensions.length && !extensions.includes(ext)) {
                this.error = 'Format non accepté. Formats possibles : ' + extensions.join(', ') + '.';
                this.file = null;
            }
            if (this.file) this.send();
        },
        async send() {
            if (!this.file || this.busy) return;
            this.busy = true;
            this.error = '';
            this.progress = 0;
            const id = crypto.randomUUID();
            const size = Math.max(256 * 1024, Math.min(chunkSize, 4 * 1024 * 1024));
            const total = Math.max(1, Math.ceil(this.file.size / size));
            const token = document.querySelector('meta[name=csrf-token]')?.content ?? '';
            try {
                for (let i = 0; i < total; i++) {
                    const form = new FormData();
                    form.append('upload_id', id);
                    form.append('index', i);
                    form.append('total', total);
                    form.append('name', this.file.name);
                    form.append('chunk', this.file.slice(i * size, (i + 1) * size), 'part');
                    let res;
                    for (let attempt = 0; attempt < 3; attempt++) {
                        res = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: form }).catch(() => null);
                        if (res && res.status < 500) break;
                        await new Promise((r) => setTimeout(r, 1500 * (attempt + 1)));
                    }
                    const data = res ? await res.json().catch(() => ({})) : {};
                    if (!res || !res.ok) throw new Error(data.message || 'Envoi interrompu. Vérifiez la connexion et recommencez.');
                    this.progress = Math.round(((i + 1) / total) * 100);
                    if (data.redirect) { window.location.href = data.redirect; return; }
                }
            } catch (e) {
                this.error = e.message;
                this.busy = false;
            }
        },
    };
}
