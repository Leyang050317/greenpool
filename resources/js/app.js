import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

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

Alpine.start();
