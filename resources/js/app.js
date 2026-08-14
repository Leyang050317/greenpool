import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const refreshBookingPage = () => {
    if (document.visibilityState !== 'visible') {
        return;
    }

    window.location.reload();
};

const subscribeToBookingUpdates = () => {
    const userId = document.body.dataset.authId;
    const userRole = document.body.dataset.authRole;
    const path = window.location.pathname;

    if (!userId || !userRole || !window.Echo) {
        return;
    }

    if (userRole === 'driver' && path.startsWith('/driver/booking')) {
        window.Echo.private(`driver.${userId}`)
            .listen('BookingCreated', refreshBookingPage);
    }

    if (userRole === 'passenger' && (path.startsWith('/passenger/booking') || path === '/passenger/home')) {
        window.Echo.private(`passenger.${userId}`)
            .listen('BookingStatusUpdated', refreshBookingPage);
    }
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

subscribeToBookingUpdates();

Alpine.start();
