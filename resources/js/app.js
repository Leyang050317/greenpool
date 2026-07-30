import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('driverNavigation', () => ({
    sidebarExpanded: false,
    mobileDrawerOpen: false,
    previousOverflow: '',
    mobileMenuTrigger: null,

    init() {
        const savedPreference = window.localStorage.getItem('greenpool.driver.sidebar-expanded');
        this.sidebarExpanded = savedPreference === null
            ? window.matchMedia('(min-width: 1280px)').matches
            : savedPreference === 'true';

        if (window.matchMedia('(max-width: 1023px)').matches) {
            this.sidebarExpanded = false;
        }

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

    setSidebarExpanded(isExpanded) {
        this.sidebarExpanded = isExpanded;
        window.localStorage.setItem('greenpool.driver.sidebar-expanded', String(isExpanded));
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

Alpine.start();
