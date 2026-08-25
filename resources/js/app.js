import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const refreshBookingPage = () => {
    if (document.visibilityState !== 'visible') {
        return;
    }

    window.location.reload();
};

const updateNotificationBadge = (count) => {
    const notificationLink = document.querySelector('[data-notification-link]');
    const notificationBadge = document.querySelector('[data-notification-badge]');

    if (!notificationLink || !notificationBadge) {
        return;
    }

    const unreadCount = Math.max(Number.parseInt(count, 10) || 0, 0);

    notificationBadge.dataset.count = unreadCount;
    notificationBadge.textContent = Math.min(unreadCount, 99).toString();
    notificationBadge.classList.toggle('hidden', unreadCount === 0);
    notificationLink.setAttribute('aria-label', `Notifications, ${unreadCount} unread`);
};

const incrementNotificationBadge = () => {
    const notificationBadge = document.querySelector('[data-notification-badge]');
    const currentCount = Number.parseInt(notificationBadge?.dataset.count ?? '0', 10) || 0;

    updateNotificationBadge(currentCount + 1);
};

const showRealtimeNotification = (notification, iconText = '★') => {
    let container = document.querySelector('[data-realtime-notification-container]');

    if (!container) {
        container = document.createElement('div');
        container.dataset.realtimeNotificationContainer = '';
        container.className = 'fixed left-4 right-4 top-4 z-[100] flex flex-col gap-3 sm:left-auto sm:right-5 sm:w-96';
        document.body.appendChild(container);
    }

    const toast = document.createElement('a');
    toast.href = notification.url || '/notifications';
    toast.className = 'flex translate-y-[-0.5rem] items-start gap-3 rounded-2xl border border-green-200 bg-white p-4 opacity-0 shadow-xl transition duration-200';
    toast.setAttribute('role', 'status');

    const icon = document.createElement('span');
    icon.className = 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 text-xl text-amber-400';
    icon.textContent = iconText;

    const content = document.createElement('span');
    content.className = 'min-w-0 flex-1';

    const title = document.createElement('strong');
    title.className = 'block text-sm font-bold text-slate-900';
    title.textContent = notification.title || 'New rating received';

    const message = document.createElement('span');
    message.className = 'mt-1 block text-sm leading-5 text-slate-500';
    message.textContent = notification.message || 'You received a new rating.';

    content.append(title, message);
    toast.append(icon, content);
    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-[-0.5rem]', 'opacity-0');
    });

    window.setTimeout(() => {
        toast.classList.add('translate-y-[-0.5rem]', 'opacity-0');
        window.setTimeout(() => toast.remove(), 200);
    }, 6000);
};

const subscribeToRatingNotifications = () => {
    const userId = document.body.dataset.authId;
    const userRole = document.body.dataset.authRole;

    if (!userId || !userRole || !window.Echo) {
        return;
    }

    window.Echo.private(`${userRole}.${userId}`)
        .listen('RatingReceived', (notification) => {
            incrementNotificationBadge();
            showRealtimeNotification(notification);
        });
};

const subscribeToPaymentNotifications = () => {
    const userId = document.body.dataset.authId;
    const userRole = document.body.dataset.authRole;

    if (!userId || !userRole || !window.Echo) {
        return;
    }

    if (userRole === 'driver') {
        window.Echo.private(`driver.${userId}`)
            .listen('PaymentReceived', (notification) => {
                incrementNotificationBadge();
                showRealtimeNotification(notification, 'RM');
            });
    }

};

const subscribeToBookingUpdates = () => {
    const userId = document.body.dataset.authId;
    const userRole = document.body.dataset.authRole;
    const path = window.location.pathname;

    if (!userId || !userRole || !window.Echo) {
        return;
    }

    if (userRole === 'driver' && (path.startsWith('/driver/booking') || path === '/driver/home')) {
        window.Echo.private(`driver.${userId}`)
            .listen('BookingCreated', refreshBookingPage)
            .listen('BookingStatusUpdated', refreshBookingPage);
    }

    if (userRole === 'driver') {
        window.Echo.private(`driver.${userId}`)
            .listen('BookingCreated', incrementNotificationBadge)
            .listen('BookingStatusUpdated', (event) => {
                if (event.driver_notification_type === 'booking_request_cancelled') {
                    incrementNotificationBadge();
                }
            });
    }

    if (userRole === 'passenger' && (path.startsWith('/passenger/booking') || path === '/passenger/home')) {
        window.Echo.private(`passenger.${userId}`)
            .listen('BookingStatusUpdated', refreshBookingPage);
    }

    if (userRole === 'passenger') {
        window.Echo.private(`passenger.${userId}`)
            .listen('BookingStatusUpdated', (event) => {
                if (event.passenger_notification_type) {
                    incrementNotificationBadge();
                }
            });
    }

    if (userRole === 'passenger' && path.startsWith('/passenger/booking')) {
        window.Echo.channel('trips')
            .listen('TripCreated', refreshBookingPage);
    }
};

const subscribeToChatListUpdates = () => {
    const userId = document.body.dataset.authId;
    const userRole = document.body.dataset.authRole;
    const bookingIds = [...document.querySelectorAll('[data-chat-booking-id]')]
        .map((element) => element.dataset.chatBookingId)
        .filter(Boolean);

    if (!userId || !userRole || !window.Echo || bookingIds.length === 0) {
        return;
    }

    bookingIds.forEach((bookingId) => {
        window.Echo.private(`booking.${bookingId}`)
            .listen('MessageSent', (event) => {
                if (Number.parseInt(event.sender_id, 10) !== Number.parseInt(userId, 10)) {
                    refreshBookingPage();
                }
            });
    });
};

Alpine.data('driverNavigation', () => ({
    sidebarExpanded: true,
    mobileDrawerOpen: false,
    previousOverflow: '',
    mobileMenuTrigger: null,

    init() {
        this.sidebarExpanded = true;

        this.$watch('mobileDrawerOpen', (isOpen) => {
            if (isOpen) {
                this.previousOverflow = document.body.style.overflow;
                document.body.style.overflow = 'hidden';
                this.$nextTick(() => {
                    document.querySelector('#driver-mobile-navigation button')?.focus();
                });
            } else {
                document.body.style.overflow = this.previousOverflow;
            }
        });
    },

    openMobileDrawer() {
        this.mobileMenuTrigger = document.activeElement;
        this.mobileDrawerOpen = true;
    },

    closeMobileDrawer() {
        if (!this.mobileDrawerOpen) {
            return;
        }

        this.mobileDrawerOpen = false;
        this.$nextTick(() => this.mobileMenuTrigger?.focus());
    },
}));

Alpine.data('passengerNavigation', () => ({
    sidebarExpanded: true,
    mobileDrawerOpen: false,
    mobileMenuTrigger: null,

    openMobileDrawer() {
        this.mobileMenuTrigger = document.activeElement;
        this.mobileDrawerOpen = true;
    },

    closeMobileDrawer() {
        if (!this.mobileDrawerOpen) {
            return;
        }

        this.mobileDrawerOpen = false;
        this.$nextTick(() => this.mobileMenuTrigger?.focus());
    },
}));

Alpine.data('bookingChat', (config) => ({
    bookingId: config.bookingId,
    currentUserId: Number.parseInt(config.currentUserId, 10),
    messages: config.messages || [],
    message: '',
    sending: false,
    canSend: Boolean(config.canSend),
    endpoint: config.endpoint,

    init() {
        this.scrollToBottom();

        if (window.Echo && this.bookingId) {
            window.Echo.private(`booking.${this.bookingId}`)
                .listen('MessageSent', (event) => {
                    this.appendMessage(event);
                });
        }
    },

    isMine(message) {
        return Number.parseInt(message.sender_id, 10) === this.currentUserId;
    },

    appendMessage(message) {
        if (this.messages.some((existing) => existing.id === message.id)) {
            return;
        }

        this.messages.push(message);
        this.scrollToBottom();
    },

    scrollToBottom() {
        this.$nextTick(() => {
            const container = this.$refs.messages;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    },

    async sendMessage() {
        const value = this.message.trim();

        if (!value || this.sending || !this.canSend) {
            return;
        }

        this.sending = true;

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ message: value }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Unable to send message.');
            }

            this.message = '';
            this.appendMessage(data.message);
        } catch (error) {
            window.alert(error.message);
        } finally {
            this.sending = false;
        }
    },
}));

subscribeToBookingUpdates();
subscribeToRatingNotifications();
subscribeToPaymentNotifications();
subscribeToChatListUpdates();

Alpine.start();
