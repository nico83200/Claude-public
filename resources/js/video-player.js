/**
 * Lecteur des tutoriels et nouveautés : vidéo + voix off (piste audio séparée, synchronisée)
 * + sous-titres. Utilisé pour l'affichage automatique (file d'attente) et sur la page Tutoriels.
 */
export function videoPlayer({ videos = [], auto = false }) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
    return {
        videos,
        index: 0,
        open: auto && videos.length > 0,
        playing: false,
        get current() { return this.videos[this.index] || null; },
        init() {
            if (this.open) document.documentElement.classList.add('overflow-hidden');
            this.$watch('open', (v) => document.documentElement.classList.toggle('overflow-hidden', v));
        },
        bind() {
            const v = this.$refs.video, a = this.$refs.audio;
            if (!v) return;
            // MP4 (H.264) en priorité ; WebM si le navigateur ne lit pas le H.264.
            const src = this.current?.videoAlt && !v.canPlayType('video/mp4; codecs="avc1.42E01E"') ? this.current.videoAlt : this.current?.video;
            if (src && v.getAttribute('src') !== src) v.src = src;
            v.muted = !!(a && this.current?.audio);
            if (!a || !this.current?.audio) return;
            const sync = () => { if (Math.abs(a.currentTime - v.currentTime) > 0.25) a.currentTime = v.currentTime; };
            v.onplay = () => { sync(); a.play().catch(() => {}); this.playing = true; };
            v.onpause = () => { a.pause(); this.playing = false; };
            v.onseeked = sync;
            v.onratechange = () => { a.playbackRate = v.playbackRate; };
            v.ontimeupdate = sync;
            v.onwaiting = () => a.pause();
            v.onplaying = () => { sync(); a.play().catch(() => {}); };
        },
        playFrom(i) {
            this.index = i;
            this.open = true;
            this.$nextTick(() => { this.bind(); this.$refs.video?.play().catch(() => {}); });
        },
        async markSeen(completed) {
            const video = this.current;
            if (!video?.seenUrl) return;
            await fetch(video.seenUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ completed }) }).catch(() => {});
        },
        stop() {
            this.$refs.video?.pause();
            this.$refs.audio?.pause();
        },
        async ended() {
            await this.markSeen(true);
            this.next();
        },
        async skip() {
            this.stop();
            await this.markSeen(false);
            this.next();
        },
        next() {
            if (auto && this.index < this.videos.length - 1) {
                this.index++;
                this.$nextTick(() => this.bind());
                return;
            }
            this.stop();
            this.open = false;
        },
    };
}
