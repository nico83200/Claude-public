/**
 * Assistant vocal : un appui sur le micro, on parle, l'enregistrement s'arrête
 * tout seul au silence. L'audio est envoyé pour transcription, puis la consigne
 * est exécutée côté serveur. Annulation possible 10 s (bandeau) puis via l'historique.
 */
const SILENCE_MS = 1500;      // silence après la parole qui déclenche l'arrêt
const NO_SPEECH_MS = 7000;    // arrêt si rien n'est dit
const MAX_MS = 60000;         // durée maximale d'une consigne
const UNDO_SECONDS = 10;
import { offlineQueue } from './offline-queue.js';

function userId() {
    return document.querySelector('meta[name=app-user]')?.content ?? '';
}

/** « Consigne notée hors connexion le mardi 14 octobre à 9 h 12 » : l'assistant date correctement ce qui a été dit. */
function offlinePrefix(iso) {
    const d = new Date(iso);
    const day = d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const time = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    return `(Consigne notée hors connexion le ${day} à ${time} : les mots « aujourd'hui », « ce matin », « hier » se rapportent à cette date.) `;
}

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
        closeIn: 0,     // secondes avant fermeture automatique de la réponse
        changed: false,
        speaking: false,
        pending: 0,     // consignes notées hors connexion, en attente d'envoi
        queued: false,  // la dernière consigne a été mise en attente
        _flushing: false,
        _rec: null,
        _audio: null,
        _lastMode: 'text',

        get canRecord() {
            return !!(navigator.mediaDevices?.getUserMedia) && pickMime() !== null && this.status?.voice !== false;
        },

        async open(listen = false) {
            this.isOpen = true;
            document.documentElement.style.overflow = 'hidden';
            if (!this.status) await this.loadStatus();
            if (listen && this.status?.available && this.canRecord && this.state !== 'listening') this.listen();
        },

        init() {
            // Bandeau d'annulation conservé après l'ouverture de l'élément concerné.
            try {
                const saved = JSON.parse(sessionStorage.getItem('jc-assistant-toast') || 'null');
                sessionStorage.removeItem('jc-assistant-toast');
                const left = saved ? saved.left - Math.round((Date.now() - saved.at) / 1000) : 0;
                if (saved && left > 0) this.showToast(saved.text, saved.actions, left);
            } catch { /* stockage indisponible */ }
            // Consignes notées hors connexion : envoyées dès que le réseau est là.
            this.refreshPending().then(() => { if (this.pending) this.flushQueue(); });
            window.addEventListener('online', () => { if (this.status?.offline) this.status = null; this.flushQueue(); });
        },

        /** Page de l'élément créé ou modifié par la réponse ; à défaut, le tableau de bord. */
        get target() {
            return this.actions.find((a) => a.url && !a.undone)?.url || config.homeUrl || '/dashboard';
        },

        get targetLabel() {
            const act = this.actions.find((a) => a.url && !a.undone);
            return act ? 'l\'élément concerné' : '';
        },

        startCountdown() {
            this.stopCountdown();
            const seconds = Number(config.resultSeconds) || 0;
            if (!seconds || this.question) return;
            this.closeIn = seconds;
            this._closeTimer = setInterval(() => {
                if (!this.isOpen || this.state !== 'result') { this.stopCountdown(); return; }
                this.closeIn -= 1;
                if (this.closeIn <= 0) { this.stopCountdown(); this.close(); }
            }, 1000);
        },

        stopCountdown() {
            clearInterval(this._closeTimer);
            this.closeIn = 0;
        },

        keepOpen() {
            this.stopCountdown();
        },

        // ------------------------------------------------------------ Réponse vocale (ElevenLabs)

        /** Préférence : '1' / '0' choisie par l'utilisateur ; sans choix, la réponse est lue quand la consigne a été dictée. */
        speechPref() {
            try { return localStorage.getItem('jc-assistant-speech'); } catch { return null; }
        },

        speechOn(mode = this._lastMode) {
            const pref = this.speechPref();
            return pref === null ? mode === 'voice' : pref === '1';
        },

        toggleSpeech() {
            const next = this.speechOn() ? '0' : '1';
            try { localStorage.setItem('jc-assistant-speech', next); } catch { /* ignoré */ }
            if (next === '0') this.stopSpeech();
            else this.unlockAudio();
        },

        /** Sur téléphone, le son doit être « débloqué » par un geste : appelé au toucher (micro, envoi). */
        unlockAudio() {
            if (!this.status?.speech) return;
            try {
                this._audio ??= new Audio();
                this._audio.muted = true;
                this._audio.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
                this._audio.play().then(() => { this._audio.pause(); this._audio.muted = false; }).catch(() => { this._audio.muted = false; });
            } catch { /* ignoré */ }
        },

        async speak(requestId) {
            if (!this.status?.speech || !requestId || !this.speechOn()) return false;
            try {
                const res = await fetch(config.speakUrl + '/' + requestId, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf() } });
                if (!res.ok) return false;
                const url = URL.createObjectURL(await res.blob());
                this._audio ??= new Audio();
                this._audio.muted = false;
                this._audio.src = url;
                this.speaking = true;
                this._audio.onended = this._audio.onerror = () => { this.speaking = false; URL.revokeObjectURL(url); if (this.state === 'result') this.startCountdown(); };
                await this._audio.play();
                return true;
            } catch {
                this.speaking = false;
                return false;
            }
        },

        stopSpeech() {
            if (this._audio) { this._audio.pause(); }
            this.speaking = false;
        },

        close() {
            this.stopCountdown();
            this.stopSpeech();
            this.cancelRecording();
            // Réponse terminée (sans question en attente) : ouverture de l'élément concerné, sinon du tableau de bord.
            const done = this.state === 'result' && !this.question && !this.queued;
            const target = done ? new URL(this.target, window.location.origin) : null;
            this.isOpen = false;
            document.documentElement.style.overflow = '';
            if (target && target.href !== window.location.href) {
                if (this.toast) {
                    try { sessionStorage.setItem('jc-assistant-toast', JSON.stringify({ ...this.toast, at: Date.now() })); } catch { /* ignoré */ }
                }
                this.changed = false;
                window.location.assign(target.href);
                return;
            }
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
                try { localStorage.setItem('jc-assistant-status', JSON.stringify({ available: this.status.available, voice: this.status.voice })); } catch { /* ignoré */ }
            } catch {
                // Hors connexion : si l'assistant était disponible, on peut noter la consigne (envoyée au retour du réseau).
                let last = null;
                try { last = JSON.parse(localStorage.getItem('jc-assistant-status') || 'null'); } catch { /* ignoré */ }
                this.status = !navigator.onLine && last?.available && offlineQueue.available
                    ? { available: true, voice: last.voice, speech: false, offline: true }
                    : { available: false, reason: navigator.onLine ? 'Assistant indisponible pour le moment.' : 'L\'assistant a besoin d\'une connexion Internet.', voice: false };
            }
        },

        // ------------------------------------------------------------ Hors connexion

        async refreshPending() {
            if (!offlineQueue.available) return;
            try { this.pending = (await offlineQueue.all(userId())).length; } catch { this.pending = 0; }
        },

        async enqueue(item) {
            await offlineQueue.add({ ...item, userId: userId(), horseId: config.horseId || null });
            await this.refreshPending();
            this.queued = true;
            this.actions = [];
            this.question = null;
            this.reply = 'Pas de réseau : c\'est noté dans le téléphone, et ce sera enregistré automatiquement dès le retour de la connexion.';
            this.state = 'result';
        },

        /** Envoi des consignes en attente, dans l'ordre où elles ont été notées. */
        async flushQueue() {
            if (this._flushing || !navigator.onLine || !offlineQueue.available) return;
            this._flushing = true;
            let sent = 0;
            const done = [];
            try {
                for (const item of await offlineQueue.all(userId())) {
                    let text = item.text;
                    if (item.kind === 'audio') {
                        const form = new FormData();
                        form.append('audio', item.blob, `consigne.${item.ext || 'webm'}`);
                        const res = await fetch(config.transcribeUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: form });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) { if (res.status >= 500 || res.status === 429) break; await offlineQueue.remove(item.id); continue; }
                        text = data.text?.trim();
                    }
                    if (text) {
                        try {
                            const data = await postJson(config.commandUrl, { text: offlinePrefix(item.at) + text, mode: item.kind === 'audio' ? 'voice' : 'text', horse_id: item.horseId });
                            if (data.status === 'error') throw new Error(data.reply || 'assistant indisponible');
                            done.push(...(data.actions || []));
                            sent++;
                        } catch (e) {
                            // Refus (quota, assistant indisponible…) : la consigne reste en attente, on prévient.
                            if (navigator.onLine) this.showToast('Consigne notée hors connexion pas encore enregistrée : ' + e.message, []);
                            break;
                        }
                    }
                    await offlineQueue.remove(item.id);
                }
            } catch { /* réessayé au prochain retour du réseau */ }
            this._flushing = false;
            await this.refreshPending();
            if (sent) {
                this.changed = true;
                this.showToast(sent > 1 ? `${sent} consignes notées hors connexion ont été enregistrées.` : 'La consigne notée hors connexion a été enregistrée.', done);
            }
        },

        // ------------------------------------------------------------ Voix

        async listen() {
            this.unlockAudio();
            this.stopCountdown();
            if (!navigator.onLine && !offlineQueue.available) { this.fail('L\'assistant a besoin d\'une connexion Internet.'); return; }
            this.reset(true);
            this.queued = false;
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
                if (!navigator.onLine && offlineQueue.available) {
                    const ext = blob.type.includes('mp4') ? 'm4a' : blob.type.includes('ogg') ? 'ogg' : 'webm';
                    this.transcript = 'Message vocal (' + Math.max(1, this.elapsed) + ' s)';
                    await this.enqueue({ kind: 'audio', blob, ext });
                    return;
                }
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
            this._lastMode = mode;
            this.unlockAudio();
            this.queued = false;
            if (!navigator.onLine && offlineQueue.available) {
                this.transcript = text;
                this.draft = '';
                await this.enqueue({ kind: 'text', text });
                return;
            }
            this.stopCountdown();
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
                // Lecture à voix haute : la fermeture automatique attend la fin de la lecture.
                const spoken = this.state !== 'error' && await this.speak(data.request_id);
                if (this.state === 'result' && !spoken) this.startCountdown();
            } catch (e) {
                this.fail(e.message);
            }
        },

        answer(option) {
            this.send(option, 'text');
        },

        async undo(action) {
            this.stopCountdown();
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

        showToast(text, actions, left = UNDO_SECONDS) {
            clearInterval(this._toastTimer);
            this.toast = { text, actions, left };
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
