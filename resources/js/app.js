import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Fajr check-in widget: polls the server for the state (the code only arrives after it is revealed)
// and submits the typed code.
Alpine.data('checkIn', ({ stateUrl, submitUrl, initial }) => ({
    state: initial,
    code: '',
    message: '',
    error: false,
    busy: false,
    remaining: '',
    offset: 0,

    init() {
        this.apply(initial);
        setInterval(() => this.tick(), 1000);
        setInterval(() => this.refresh(), 15000);
    },

    apply(state) {
        this.state = state;
        // Use the server clock for the countdown so a wrong device clock cannot mislead the user.
        this.offset = Date.parse(state.server_now) - Date.now();
        this.tick();
    },

    tick() {
        if (this.state.status !== 'revealed') {
            return;
        }

        const left = Math.max(0, Date.parse(this.state.expires_at) - (Date.now() + this.offset));
        const seconds = Math.floor(left / 1000);
        this.remaining = String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');

        if (left === 0) {
            this.refresh();
        }
    },

    async refresh() {
        if (['confirmed', 'missed', 'locked'].includes(this.state.status) || document.hidden) {
            return;
        }

        try {
            const response = await fetch(stateUrl, { headers: { Accept: 'application/json' } });
            if (response.ok) {
                this.apply(await response.json());
            }
        } catch (e) {
            // Network hiccup: the next poll will try again.
        }
    },

    async submit() {
        this.busy = true;
        this.message = '';

        try {
            const response = await fetch(submitUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ code: this.code }),
            });
            const data = await response.json();

            this.error = !response.ok;
            this.message = data.message ?? 'Не удалось отправить код. Попробуйте ещё раз.';
            if (data.state) {
                this.apply(data.state);
            }
            if (response.ok) {
                this.code = '';
            }
        } catch (e) {
            this.error = true;
            this.message = 'Нет связи с сервером. Попробуйте ещё раз.';
        } finally {
            this.busy = false;
        }
    },
}));

Alpine.start();
