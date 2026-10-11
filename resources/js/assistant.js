/**
 * Assistant vocal : un appui sur le micro, on parle, l'enregistrement s'arrête
 * tout seul au silence. L'audio est envoyé pour transcription, puis la consigne
 * est exécutée côté serveur. Annulation possible 10 s (bandeau) puis via l'historique.
 */
const SILENCE_MS = 1500;      // silence après la parole qui déclenche l'arrêt
const NO_SPEECH_MS = 7000;    // arrêt si rien n'est dit
const MAX_MS = 60000;         // durée maximale d'une consigne
const UNDO_SECONDS = 10;

function csrf() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body ?? {}),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || (res.status === 429 ? 'Trop de consignes d\'affilée : patientez une minute.' : 'Le service est momentanément indisponible.'));
    return data;
}

function pickMime() {
    if (typeof MediaRecorder === 'undefined') return null;
    for (const m of ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus']) {
        if (MediaRecorder.isTypeSupported?.(m)) return m;
    }
    return '';
}

export function assistantStore(config) {
    return {
        isOpen: false,
        state: 'idle', // idle | listening | transcribing | thinking | result | error
        status: null,  // disponibilité (chargée à l'ouverture)
        transcript: '',
        draft: '',
        reply: '',
        error: '',
        actions: [],
        question: null,
        conversationId: null,
        level: 0,
        elapsed: 0,
        toast: null, // { text, actions, left }
        changed: false,
        _rec: null,

        get canRecord() {
            return !!(navigator.mediaDevices?.getUserMedia) && pickMime() !== null && this.status?.voice !== false;
        },

        async open(listen = false) {
            this.isOpen = true;
            document.documentElement.style.overflow = 'hidden';
            if (!this.status) await this.loadStatus();
            if (listen && this.status?.available && this.canRecord && this.state !== 'listening') this.listen();
        },

        close() {
            this.cancelRecording();
            this.isOpen = false;
            document.documentElement.style.overflow = '';
            if (this.state !== 'thinking') this.reset(false);
            // Les données de la page ont changé : rechargement, après le délai d'annulation s'il court encore.
            if (this.changed) {
                if (this.toast) this._reloadAfterToast = true;
                else { this.changed = false; window.location.reload(); }
            }
        },

        reset(keepConversation = false) {
            this.state = 'idle';
            this.transcript = '';
            this.draft = '';
            this.reply = '';
            this.error = '';
            this.actions = [];
            this.question = null;
            if (!keepConversation) this.conversationId = null;
        },

        async loadStatus() {
            try {
                const res = await fetch(config.statusUrl, { headers: { Accept: 'application/json' } });
                this.status = await res.json();
            } catch {
                this.status = { available: false, reason: navigator.onLine ? 'Assistant indisponible pour le moment.' : 'L\'assistant a besoin d\'une connexion Internet.', voice: false };
            }
        },

        // ------------------------------------------------------------ Voix

        async listen() {
            if (!navigator.onLine) { this.fail('L\'assistant a besoin d\'une connexion Internet.'); return; }
            this.reset(true);
            let stream;
            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
            } catch {
                this.fail('Accès au micro refusé. Autorisez le micro dans les réglages du navigateur, ou écrivez votre consigne.');
                return;
            }
            const mime = pickMime();
            const recorder = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
            const chunks = [];
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const analyser = ctx.createAnalyser();
            analyser.fftSize = 1024;
            ctx.createMediaStreamSource(stream).connect(analyser);
            const buf = new Float32Array(analyser.fftSize);
            const started = performance.now();
            let spoke = false;
            let lastVoice = started;
            let cancelled = false;

            const cleanup = () => {
                clearInterval(timer);
                stream.getTracks().forEach((t) => t.stop());
                ctx.close().catch(() => {});
                this.level = 0;
            };
            const timer = setInterval(() => {
                analyser.getFloatTimeDomainData(buf);
                let sum = 0;
                for (const v of buf) sum += v * v;
                const rms = Math.sqrt(sum / buf.length);
                this.level = Math.min(1, rms * 12);
                const now = performance.now();
                this.elapsed = Math.floor((now - started) / 1000);
                if (rms > 0.02) { spoke = true; lastVoice = now; }
                if ((spoke && now - lastVoice > SILENCE_MS) || (!spoke && now - started > NO_SPEECH_MS) || now - started > MAX_MS) {
                    if (!spoke) cancelled = true;
                    if (recorder.state === 'recording') recorder.stop();
                }
            }, 100);

            recorder.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
            recorder.onstop = async () => {
                cleanup();
                this._rec = null;
                if (cancelled || this.state !== 'listening') {
                    if (this.state === 'listening') this.fail('Je n\'ai rien entendu. Touchez le micro et parlez, ou écrivez votre consigne.');
                    return;
                }
                const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
                await this.transcribe(blob);
            };
            this._rec = { recorder, cancel: () => { cancelled = true; if (recorder.state === 'recording') recorder.stop(); else cleanup(); } };
            this.state = 'listening';
            this.elapsed = 0;
            recorder.start(250);
        },

        stopListening() {
            if (this._rec?.recorder.state === 'recording') this._rec.recorder.stop();
        },

        cancelRecording() {
            if (this._rec) { this.state = 'idle'; this._rec.cancel(); this._rec = null; }
        },

        async transcribe(blob) {
            this.state = 'transcribing';
            const ext = blob.type.includes('mp4') ? 'm4a' : blob.type.includes('ogg') ? 'ogg' : 'webm';
            const form = new FormData();
            form.append('audio', blob, `consigne.${ext}`);
            try {
                const res = await fetch(config.transcribeUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: form });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'La transcription a échoué.');
                if (!data.text?.trim()) { this.fail('Je n\'ai pas compris. Réessayez en parlant près du téléphone, ou écrivez votre consigne.'); return; }
                await this.send(data.text, 'voice');
            } catch (e) {
                this.fail(e.message);
            }
        },

        // ------------------------------------------------------------ Consigne

        async send(text, mode = 'text') {
            text = (text ?? '').trim();
            if (!text) return;
            this.transcript = text;
            this.draft = '';
            this.state = 'thinking';
            this.error = '';
            this.question = null;
            try {
                const data = await postJson(config.commandUrl, { text, mode, conversation_id: this.conversationId, horse_id: config.horseId || null });
                this.conversationId = data.conversation_id;
                this.reply = data.reply;
                this.actions = data.actions || [];
                this.question = data.question;
                if (this.status) this.status.remaining = data.remaining;
                this.state = data.status === 'error' ? 'error' : 'result';
                if (data.status === 'error') this.error = data.reply;
                if (this.actions.length) {
                    this.changed = true;
                    this.showToast(data.reply, this.actions);
                }
            } catch (e) {
                this.fail(e.message);
            }
        },

        answer(option) {
            this.send(option, 'text');
        },

        async undo(action) {
            try {
                await postJson(action.undo_url);
                action.undoable = false;
                action.undone = true;
                this.changed = true;
                if (this.toast?.actions.includes(action) && this.toast.actions.every((a) => a.undone)) this.toast = null;
            } catch (e) {
                this.error = e.message;
            }
        },

        async undoToast() {
            if (!this.toast) return;
            const list = this.toast.actions.filter((a) => a.undoable);
            this.toast = null;
            for (const a of list.reverse()) await this.undo(a);
            if (!this.isOpen) window.location.reload();
        },

        showToast(text, actions) {
            clearInterval(this._toastTimer);
            this.toast = { text, actions, left: UNDO_SECONDS };
            this._toastTimer = setInterval(() => {
                if (!this.toast) { clearInterval(this._toastTimer); return; }
                this.toast.left -= 1;
                if (this.toast.left <= 0) {
                    this.toast = null;
                    clearInterval(this._toastTimer);
                    if (this._reloadAfterToast && !this.isOpen) window.location.reload();
                }
            }, 1000);
        },

        fail(message) {
            this.error = message;
            this.state = 'error';
        },
    };
}
