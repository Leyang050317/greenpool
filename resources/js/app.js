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

const bookingUpdateNotification = (event, role) => {
    const type = role === 'passenger' ? event.passenger_notification_type : event.driver_notification_type;
    const route = event.trip_route || 'your trip';
    const passengerMessages = {
        booking_request_accepted: ['Booking Accepted', `Your booking for ${route} has been accepted.`, '✓'],
        booking_request_rejected: ['Booking Rejected', `Your booking request for ${route} was rejected.`, '×'],
        trip_started: ['Trip Started', `Your trip from ${route} has started.`, '▶'],
        trip_completed: ['Trip Completed', `Your trip from ${route} has been completed. Review your payment and rating information.`, '✓'],
        trip_cancelled: ['Trip Cancelled', `Your booked trip from ${route} has been cancelled.`, '×'],
    };
    const driverMessages = {
        booking_request_cancelled: ['Booking Request Cancelled', `A passenger cancelled their booking request for ${route}.`, '×'],
    };
    const [title, message, icon] = (role === 'passenger' ? passengerMessages : driverMessages)[type] || [];

    return title ? { title, message, url: role === 'passenger' ? event.passenger_url : event.driver_url, icon } : null;
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

const notificationIcon = (notification) => ({
    'message-square': '✉',
    route: '↗',
    'credit-card': 'RM',
    'circle-check': '✓',
    star: '★',
}[notification.icon] || '●');

const subscribeToInAppNotifications = () => {
    const userId = document.body.dataset.authId;
    const userRole = document.body.dataset.authRole;

    if (!userId || !userRole || !window.Echo) {
        return;
    }

    window.Echo.private(`${userRole}.${userId}`)
        .listen('InAppNotificationCreated', (notification) => {
            incrementNotificationBadge();
            showRealtimeNotification(notification, notificationIcon(notification));
        });
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
            .listen('BookingCreated', (event) => {
                incrementNotificationBadge();
                showRealtimeNotification({
                    title: 'New Booking Request',
                    message: `${event.passenger_name} requested a seat on your trip from ${event.trip_route || 'your trip'}.`,
                    url: event.url,
                }, '★');
            })
            .listen('BookingStatusUpdated', (event) => {
                if (event.driver_notification_type === 'booking_request_cancelled') {
                    incrementNotificationBadge();
                    const notification = bookingUpdateNotification(event, 'driver');
                    if (notification) showRealtimeNotification(notification, notification.icon);
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
                    const notification = bookingUpdateNotification(event, 'passenger');
                    if (notification) showRealtimeNotification(notification, notification.icon);
                }
            });
    }

    if (userRole === 'passenger' && path.startsWith('/passenger/booking')) {
        window.Echo.channel('trips')
            .listen('TripCreated', refreshBookingPage);
    }
};

const subscribeToTripReminders = () => {
    const userId = document.body.dataset.authId;
    const userRole = document.body.dataset.authRole;

    if (!userId || !userRole || !window.Echo) {
        return;
    }

    window.Echo.private(`${userRole}.${userId}`)
        .listen('TripReminderSent', (notification) => {
            incrementNotificationBadge();
            showRealtimeNotification(notification, '◷');
        });
};

const subscribeToEmergencyAlerts = () => {
    const userId = document.body.dataset.authId;
    const userRole = document.body.dataset.authRole;

    if (!userId || !userRole || !window.Echo) {
        return;
    }

    window.Echo.private(`${userRole}.${userId}`)
        .listen('EmergencyTriggered', (notification) => {
            incrementNotificationBadge();
            showRealtimeNotification(notification, '⚠');
        });
};

const dispatchTripLocation = (location) => {
    document.dispatchEvent(new CustomEvent('greenpool:trip-location', { detail: location }));
};

const subscribeToTripLocations = () => {
    const tripIds = [...document.querySelectorAll('[data-trip-static-map][data-live-tracking="true"]')]
        .map((element) => element.dataset.tripId)
        .filter(Boolean);

    if (!window.Echo) {
        return;
    }

    [...new Set(tripIds)].forEach((tripId) => {
        window.Echo.private(`trip.${tripId}`)
            .listen('TripLocationUpdated', dispatchTripLocation);
    });
};

const startDriverLocationTracking = () => {
    const tracker = document.querySelector('[data-driver-location-tracker]');

    if (!tracker || !navigator.geolocation) {
        return;
    }

    const tripId = tracker.dataset.tripId;
    const endpoint = tracker.dataset.locationEndpoint;
    let lastSent = null;
    let sending = false;
    const distanceMeters = (from, to) => {
        const earthRadius = 6371000;
        const latitudeDelta = (to.latitude - from.latitude) * Math.PI / 180;
        const longitudeDelta = (to.longitude - from.longitude) * Math.PI / 180;
        const value = Math.sin(latitudeDelta / 2) ** 2
            + Math.cos(from.latitude * Math.PI / 180) * Math.cos(to.latitude * Math.PI / 180) * Math.sin(longitudeDelta / 2) ** 2;

        return earthRadius * 2 * Math.atan2(Math.sqrt(value), Math.sqrt(1 - value));
    };
    const watchId = navigator.geolocation.watchPosition(async (position) => {
        const location = {
            trip_id: tripId,
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy_meters: position.coords.accuracy,
            heading: Number.isFinite(position.coords.heading) ? position.coords.heading : null,
            speed_mps: Number.isFinite(position.coords.speed) ? position.coords.speed : null,
            recorded_at: new Date().toISOString(),
        };

        if (location.accuracy_meters > 150) {
            return;
        }

        dispatchTripLocation(location);
        const elapsed = lastSent ? Date.now() - lastSent.sentAt : Infinity;
        const moved = lastSent ? distanceMeters(lastSent, location) : Infinity;
        if (sending || (elapsed < 10000 && moved < 25)) {
            return;
        }

        sending = true;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(location),
            });

            if (response.ok) {
                lastSent = { ...location, sentAt: Date.now() };
            }
        } finally {
            sending = false;
        }
    }, () => {}, { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 });

    window.addEventListener('pagehide', () => navigator.geolocation.clearWatch(watchId), { once: true });
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

Alpine.data('faqBot', ({ featuredEndpoint, answerEndpoint }) => ({
    open: false,
    loading: false,
    question: '',
    featured: [],
    suggestions: [],
    messages: [],
    messageId: 0,
    featuredEndpoint,
    answerEndpoint,

    async loadFeatured() {
        try {
            const response = await fetch(this.featuredEndpoint, {
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();
            this.featured = response.ok ? (data.data || []) : [];
        } catch {
            this.featured = [];
        }
    },

    addMessage(role, text) {
        this.messages.push({ id: ++this.messageId, role, text });
        this.$nextTick(() => {
            const conversation = this.$refs.conversation;
            if (conversation) conversation.scrollTop = conversation.scrollHeight;
        });
    },

    async ask(value) {
        const question = (value || '').trim();
        if (!question || this.loading) return;

        this.question = '';
        this.suggestions = [];
        this.addMessage('user', question);
        this.loading = true;

        try {
            const response = await fetch(this.answerEndpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ question }),
            });
            const data = await response.json();

            if (!response.ok) throw new Error(data.message || 'Unable to get an answer right now.');

            if (data.matched) {
                this.addMessage('bot', `${data.faq.question}\n\n${data.faq.answer}`);
            } else {
                this.addMessage('bot', `${data.title}\n\n${data.answer}`);
                this.suggestions = data.suggestions || [];
            }
        } catch (error) {
            this.addMessage('bot', error.message || 'Sorry, GreenPool Help is temporarily unavailable. Please try again.');
        } finally {
            this.loading = false;
            this.$nextTick(() => this.$refs.question?.focus());
        }
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
subscribeToTripReminders();
subscribeToEmergencyAlerts();
subscribeToTripLocations();
startDriverLocationTracking();
subscribeToRatingNotifications();
subscribeToPaymentNotifications();
subscribeToInAppNotifications();
subscribeToChatListUpdates();

Alpine.start();
